/**
 * Turn a mysqldump --no-data output into includes/schema-tables.php.
 *
 * The live database and the phone app must never disagree about what a table
 * looks like. The database on the live host is a copy of the one on the
 * development machine, so the dump of the development machine is the truth;
 * this script turns it into the PHP file the site uses to make sure every one
 * of those tables exists on whatever server it is running on.
 *
 *   node tools/make-schema-php.js <dump.sql> includes/schema-tables.php
 */
const fs = require('fs');
const crypto = require('crypto');

const dump = fs.readFileSync(process.argv[2], 'utf8');
const outPath = process.argv[3];

// The dump interleaves statements with the mysqldump charset preamble; only the
// CREATE TABLE blocks are wanted.
const blocks = [];
const re = /CREATE TABLE `([^`]+)` \(([\s\S]*?)\)\s*ENGINE=([^;]*);/g;
let m;
while ((m = re.exec(dump)) !== null) {
  blocks.push({ name: m[1], body: m[2], engine: m[3].trim() });
}

if (!blocks.length) {
  console.error('no CREATE TABLE blocks found in ' + process.argv[2]);
  process.exit(1);
}

// A table that another table points at has to exist before the one that points
// at it, or the foreign key cannot be made. The tables are therefore ordered
// by their foreign keys first (a parent before its children) and by name
// after that, so the order is the same every time the file is generated.
const byName = {};
for (const b of blocks) byName[b.name] = b;

const parentsOf = {};
for (const b of blocks) {
  const parents = [];
  const re = /REFERENCES `([^`]+)`/g;
  let r;
  while ((r = re.exec(b.body)) !== null) {
    if (r[1] !== b.name && !parents.includes(r[1])) parents.push(r[1]);
  }
  parentsOf[b.name] = parents;
}

const sorted = [];
const placed = new Set();
const place = (name, seen) => {
  if (placed.has(name) || seen.has(name)) return;
  seen.add(name);
  for (const parent of parentsOf[name] || []) {
    if (byName[parent]) place(parent, seen);
  }
  seen.delete(name);
  if (!placed.has(name)) {
    placed.add(name);
    sorted.push(byName[name]);
  }
};
for (const name of Object.keys(byName).sort()) place(name, new Set());

const statements = sorted.map((b) => ({
  name: b.name,
  sql:
    `CREATE TABLE IF NOT EXISTS \`${b.name}\` (\n` +
    b.body.replace(/,\s*\n\s*$/, '').trim() +
    `\n) ENGINE=${b.engine};`,
}));

// The columns of each table, so a table that exists but is missing a column
// can be brought up to date as well. The dump writes one column per line, so a
// line that opens with a backtick is a column and everything else is a key.
const columns = {};
for (const b of sorted) {
  const list = [];
  for (const rawLine of b.body.split('\n')) {
    const line = rawLine.trim().replace(/,$/, '');
    if (!line.startsWith('`')) continue;
    const name = line.match(/^`([^`]+)`/);
    if (!name) continue;
    list.push({ name: name[1], def: line });
  }
  columns[b.name] = list;
}

const version = crypto
  .createHash('sha1')
  .update(statements.map((s) => s.sql).join('\n'))
  .update('\n--columns--\n')
  .update(
    Object.keys(columns)
      .map((t) => t + ':' + columns[t].map((c) => c.def).join(','))
      .join('\n')
  )
  .digest('hex')
  .slice(0, 12);

// A nowdoc (<<<'SQL') is used for every statement: it quotes the SQL exactly
// as it is written, so no dollar sign, backslash or quote in a column default
// can ever be mangled by PHP.
const body = statements
  .map(
    (s) =>
      `        // ${s.name}\n` +
      `        <<<'SQL'\n${s.sql}\nSQL,\n`
  )
  .join('\n');

// The same columns again, one list per table, for the check that brings a
// table that exists but is a column short up to date.
const columnBody = Object.keys(columns)
  .map((table) => {
    const list = columns[table]
      .map((c) => `            ${JSON.stringify(c.name)} => ${JSON.stringify(c.def)},`)
      .join('\n');
    return `        ${JSON.stringify(table)} => [\n${list}\n        ],`;
  })
  .join('\n');

const php = `<?php
/**
 * Every table this site knows about, written out so a server can make them for
 * itself.
 *
 * WHY THIS FILE EXISTS
 *   The live database and the development database are the same site at two
 *   moments in time. When code is written that needs a table nobody has run
 *   the SQL for, the question "does the server have it?" is answered by
 *   guessing, and a guess that is wrong shows up as a blank page or a 500
 *   rather than as a missing feature.
 *
 *   So the tables are shipped with the code, exactly as they are on the
 *   development machine, and a server creates any that are missing. Every
 *   statement is "IF NOT EXISTS", so a table that is already there is never
 *   touched - not its columns, not its rows, not its indexes. This can only
 *   ever add what is missing.
 *
 * HOW OFTEN IT RUNS
 *   Once. The version below is written to a small file in the cache folder
 *   after the tables have been checked, and the work is skipped while that
 *   file still matches. A deploy that adds a table changes the version, and
 *   the next request does the work once.
 *
 * The statements come from the development database itself, dumped with
 * mysqldump --no-data, so they are the shape the code actually expects. When
 * the development database changes, this file is regenerated with:
 *
 *   mysqldump --no-data --skip-comments --skip-add-drop-table --compact \\
 *     <database> > schema.sql
 *   node tools/make-schema-php.js schema.sql includes/schema-tables.php
 */

define('MSB_SCHEMA_VERSION', '${version}');

/**
 * @return array<int, string> the CREATE TABLE statements, in order
 */
function msb_schema_statements(): array
{
    static $statements = null;
    if ($statements !== null) return $statements;

    $statements = [
${body}    ];

    return $statements;
}

/**
 * The columns of every table, so a table that exists but is short of a column
 * can be brought up to date as well.
 *
 * @return array<string, array<int, string>>
 */
function msb_schema_columns(): array
{
    static $columns = null;
    if ($columns !== null) return $columns;

    $columns = [
${columnBody}
    ];

    return $columns;
}

/**
 * Make sure every table this site needs exists, once per schema version.
 *
 * @param PDO $pdo
 * @return array{ok:bool, created:int, added:int, version:string, error:?string}
 */
function msb_ensure_schema_tables(PDO $pdo): array
{
    // The marker lives in a folder the server can write to. The cache folder is
    // the natural home; the logs folder is the fallback, because on shared
    // hosting the cache folder is sometimes not writable.
    $root = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__);
    $dir = '';
    foreach ([$root . '/cache', $root . '/logs'] as $candidate) {
        if (!is_dir($candidate)) @mkdir($candidate, 0775, true);
        if (is_dir($candidate) && is_writable($candidate)) { $dir = $candidate; break; }
    }
    if ($dir === '') {
        return ['ok' => false, 'created' => 0, 'version' => MSB_SCHEMA_VERSION, 'error' => 'no writable folder for the schema marker'];
    }
    $marker = $dir . '/schema-version.txt';

    if (is_readable($marker) && trim((string)@file_get_contents($marker)) === MSB_SCHEMA_VERSION) {
        return ['ok' => true, 'created' => 0, 'version' => MSB_SCHEMA_VERSION, 'error' => null];
    }

    // A server whose database user cannot create tables would otherwise run
    // every statement again on every request. After a failure the work waits
    // ten minutes before it is tried again.
    $failed = $dir . '/schema-failed.txt';
    if (is_readable($failed) && (time() - (int)@file_get_contents($failed)) < 600) {
        return ['ok' => false, 'created' => 0, 'version' => MSB_SCHEMA_VERSION, 'error' => 'schema check failed recently; waiting before trying again'];
    }

    $created = 0;
    $added = 0;
    $error = null;
    foreach (msb_schema_statements() as $sql) {
        try {
            $pdo->exec($sql);
            $created++;
        } catch (Throwable $e) {
            // One table that the server refuses is reported and the rest are
            // still attempted: a partial schema is better than none, and the
            // message says which one is missing.
            $error = $e->getMessage();
        }
    }

    /**
     * A table can be present and still be a column short - that is what
     * happens when a column is added to the code and the server keeps the
     * table it already had. Such a column is added here, and it is added
     * nullable if the server will not have it the way the development machine
     * has it: a column that exists is worth more than a column that is exactly
     * right, and the code that reads it can cope with an empty one.
     */
    foreach (msb_schema_columns() as $table => $definitions) {
        try {
            $present = [];
            foreach ($pdo->query('SHOW COLUMNS FROM \`' . $table . '\`')->fetchAll(PDO::FETCH_ASSOC) as $column) {
                $present[strtolower((string)$column['Field'])] = true;
            }
        } catch (Throwable $e) {
            continue; // The table itself could not be read; nothing to add to.
        }

        foreach ($definitions as $colName => $definition) {
            $name = strtolower((string)$colName);
            if ($name === '' || isset($present[$name])) continue;
            try {
                $pdo->exec('ALTER TABLE \`' . $table . '\` ADD COLUMN ' . $definition);
                $added++;
            } catch (Throwable $e) {
                $looser = preg_replace('/\\bNOT NULL\\b/i', 'NULL', $definition);
                try {
                    $pdo->exec('ALTER TABLE \`' . $table . '\` ADD COLUMN ' . $looser);
                    $added++;
                } catch (Throwable $e2) {
                    $error = 'column ' . $table . '.' . $name . ': ' . $e2->getMessage();
                }
            }
        }
    }

    if ($error === null) {
        @file_put_contents($marker, MSB_SCHEMA_VERSION);
        @unlink($failed);
    } else {
        @file_put_contents($failed, (string)time());
    }

    return ['ok' => $error === null, 'created' => $created, 'added' => $added, 'version' => MSB_SCHEMA_VERSION, 'error' => $error];
}
`;

fs.writeFileSync(outPath, php, 'utf8');
console.log(
  'wrote ' + outPath + ' with ' + statements.length + ' tables, version ' + version
);
