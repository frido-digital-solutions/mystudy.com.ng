<?php
/**
 * One-time domain and stale-data migration.
 *
 * After importing the old database onto the new host, stored content still
 * points at the old domains, and a few site_settings rows still hold placeholder
 * junk from before the relaunch. This fixes both in one run.
 *
 * users.email is deliberately NOT touched by the text rewrite — those are login
 * identifiers, and rewriting them would lock people out of their accounts. The
 * Primary Super Admin card on the Manage Admins page is how the main login moves.
 *
 * Safe to run more than once: every step only writes rows that still look wrong,
 * so a second run reports zero changes.
 */
require_once __DIR__ . '/../config/config.php';
require_super_admin();

$page_title = 'Domain Migration';

$newDomain = SITE_DOMAIN;

// Content columns that can hold a saved URL.
$targets = [
    'posts'         => ['pk' => 'id', 'columns' => ['content']],
    'notifications' => ['pk' => 'id', 'columns' => ['link', 'title']],
];

// Hosts this site has previously run on. Rows may carry any of them.
$oldDomains = ['mystudy.com.ng', 'mystudy.workvista.org'];

// Links saved while the app sat on a test host under some subdirectory. The page
// path after the prefix is still correct, so only the host and prefix are replaced.
$localhostNeedle = 'localhost';

/**
 * Rewrite one stored value onto the new domain.
 *
 * Done in PHP rather than with MySQL REPLACE() because REPLACE() is
 * case-sensitive while LIKE is not, so rows such as "MyStudy.com.ng" would be
 * counted but never rewritten.
 */
function migrate_fix_text(string $val, array $oldDomains, string $newDomain): string {
    $new = str_ireplace($oldDomains, $newDomain, $val);
    // http://localhost/<anything-before-first-slash>/page.php  ->  https://new/page.php
    // Covers every subdirectory the app has ever lived in locally.
    $new = preg_replace('~https?://localhost(?:/[^/?#]+)?/~i', 'https://' . $newDomain . '/', $new);
    // Canonicalise: no www prefix, and https so links do not bounce through a redirect.
    $new = str_ireplace('www.' . $newDomain, $newDomain, $new);
    $new = str_ireplace('http://' . $newDomain, 'https://' . $newDomain, $new);
    return $new;
}

// Settings rows that must be set to a known value instead of text-searched,
// because their current content is a placeholder rather than an old URL.
// An empty target means "this network has no handle — remove the row".
$fixedSettings = [
    'social_facebook'  => 'https://www.facebook.com/share/1CYQ7fR8b1/',
    'social_twitter'   => 'https://x.com/Mystudybase_ng',
    'social_instagram' => 'https://www.instagram.com/mystudybase_ng',
    'social_youtube'   => 'https://youtube.com/@mystudybase_ng',
    'social_tiktok'    => 'https://www.tiktok.com/@mystudybase_ng',
    'social_linkedin'  => '',
];

// The address students see on the contact page and in admin alerts. It cannot be
// guessed by this tool and must not be baked into code that reaches GitHub, so
// the operator types it once here and it is stored in site_settings.
$contactKeys = ['support_email', 'admin_email'];

/**
 * A stored URL is only replaced when it still carries an old host or is blank.
 */
function migrate_is_stale($value, array $oldDomains): bool {
    $value = trim((string)$value);
    if ($value === '' || $value === '#') {
        return true;
    }
    foreach ($oldDomains as $old) {
        if (stripos($value, $old) !== false) {
            return true;
        }
    }
    // Covers the pre-relaunch placeholders (admin@mystudy.com, /contact.php links).
    return stripos($value, 'mystudy.com') !== false
        || stripos($value, 'workvista') !== false
        || stripos($value, 'localhost') !== false;
}

/** Rows in one column that still mention any of the given hosts. */
function migrate_count(PDO $pdo, string $table, string $column, array $needles): int {
    $clause = implode(' OR ', array_fill(0, count($needles), "`$column` LIKE ?"));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE $clause");
    $stmt->execute(array_map(static fn($needle) => '%' . $needle . '%', $needles));
    return (int)$stmt->fetchColumn();
}

function migrate_setting(?PDO $pdo, string $key): ?string {
    if (!$pdo) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT setting_value FROM site_settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $val = $stmt->fetchColumn();
    return $val === false ? null : (string)$val;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'rewrite') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash_message('error', 'Invalid form token. Please try again.');
        redirect(SITE_URL . '/admin/migrate_domain.php');
    }

    // The contact address cannot be guessed, so refuse to run until it is given.
    $contactEmail = strtolower(trim((string)($_POST['contact_email'] ?? '')));
    $contactNeeded = false;
    foreach ($contactKeys as $key) {
        if (migrate_is_stale(migrate_setting($pdo, $key), $oldDomains)) {
            $contactNeeded = true;
        }
    }
    if ($contactNeeded && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
        set_flash_message('error', 'Type the email address your students should reach you on — it has to be an inbox that actually receives mail.');
        redirect(SITE_URL . '/admin/migrate_domain.php');
    }

    try {
        $pdo->beginTransaction();
        $changes = [];

        // 1. Text rewrite inside saved content: one pass per column, matching any
        //    of the old hosts at once. Column names come from $targets above,
        //    never from the request, so interpolating them is safe.
        $needles = array_merge($oldDomains, [$localhostNeedle]);
        $bind = array_map(static fn($needle) => '%' . $needle . '%', $needles);
        foreach ($targets as $table => $spec) {
            foreach ($spec['columns'] as $column) {
                $clause = implode(' OR ', array_fill(0, count($needles), "`$column` LIKE ?"));
                $sel = $pdo->prepare("SELECT `id` AS pk, `$column` AS val FROM `$table` WHERE $clause");
                $sel->execute($bind);
                $upd = $pdo->prepare("UPDATE `$table` SET `$column` = ? WHERE `id` = ?");
                $n = 0;
                foreach ($sel->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $val = (string)$row['val'];
                    $new = migrate_fix_text($val, $oldDomains, $newDomain);
                    if ($new !== $val) {
                        $upd->execute([$new, $row['pk']]);
                        $n++;
                    }
                }
                if ($n) {
                    $changes["$table.$column"] = $n;
                }
            }
        }

        // 2. Settings that are placeholders rather than old URLs.
        $selOne = $pdo->prepare('SELECT setting_key FROM site_settings WHERE setting_key = ?');
        $insSet = $pdo->prepare('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)');
        $updSet = $pdo->prepare('UPDATE site_settings SET setting_value = ? WHERE setting_key = ?');
        foreach ($fixedSettings as $key => $want) {
            $current = migrate_setting($pdo, $key);
            if ($current === null && $want === '') {
                continue; // already removed — nothing to do, keeps a re-run at zero
            }
            if (!migrate_is_stale($current, $oldDomains)) {
                continue; // already carries a real value; leave the admin's edit alone
            }
            if ($want === '') {
                // No handle exists for this network: remove the row so the icon hides.
                $pdo->prepare('DELETE FROM site_settings WHERE setting_key = ?')->execute([$key]);
                $changes["site_settings.$key"] = 1;
                continue;
            }
            $selOne->execute([$key]);
            if ($selOne->fetchColumn()) {
                $updSet->execute([$want, $key]);
            } else {
                $insSet->execute([$key, $want]);
            }
            $changes["site_settings.$key"] = 1;
        }

        // 2b. The public contact address, taken from the form above.
        if ($contactEmail !== '') {
            foreach ($contactKeys as $key) {
                $current = migrate_setting($pdo, $key);
                if (!migrate_is_stale($current, $oldDomains)) {
                    continue;
                }
                $selOne->execute([$key]);
                if ($selOne->fetchColumn()) {
                    $updSet->execute([$contactEmail, $key]);
                } else {
                    $insSet->execute([$key, $contactEmail]);
                }
                $changes["site_settings.$key"] = 1;
            }
        }

        // 3. Any remaining settings row that merely still names an old host.
        $n = 0;
        $selRows = $pdo->query('SELECT setting_key, setting_value FROM site_settings');
        $updRow = $pdo->prepare('UPDATE site_settings SET setting_value = ? WHERE setting_key = ?');
        foreach ($selRows->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (array_key_exists($row['setting_key'], $fixedSettings)) {
                continue; // already handled above
            }
            $val = (string)$row['setting_value'];
            $new = migrate_fix_text($val, $oldDomains, $newDomain);
            if ($new !== $val) {
                $updRow->execute([$new, $row['setting_key']]);
                $n++;
            }
        }
        if ($n) {
            $changes['site_settings.other'] = $n;
        }

        $pdo->commit();
        $totalChanges = array_sum($changes);
        log_admin_activity($pdo, 'Domain Migration', "Rewrote to $newDomain: " . json_encode($changes));
        set_flash_message('success', $totalChanges
            ? 'Migration complete — ' . $totalChanges . ' value(s) updated.'
            : 'Nothing left to migrate. The data already points at ' . $newDomain . '.');
        redirect(SITE_URL . '/admin/migrate_domain.php');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = 'Migration failed and was rolled back: ' . $e->getMessage();
    }
}

// ---- What is still wrong right now (shown before and after the run) ----
$needles = array_merge($oldDomains, [$localhostNeedle]);
$pending = [];
$total = 0;
foreach ($targets as $table => $spec) {
    foreach ($spec['columns'] as $column) {
        $sum = migrate_count($pdo, $table, $column, $needles);
        if ($sum) {
            $pending["$table.$column"] = $sum;
            $total += $sum;
        }
    }
}

$settingsPlan = [];
foreach ($fixedSettings as $key => $want) {
    $current = migrate_setting($pdo, $key);
    if ($current === null && $want === '') {
        continue;
    }
    if (migrate_is_stale($current, $oldDomains)) {
        $settingsPlan[$key] = ['from' => $current, 'to' => $want];
        $total++;
    }
}

// Suggest a usable contact address only if a stored one is not already valid.
$contactDefault = (string)($_POST['contact_email'] ?? '');
if ($contactDefault === '') {
    foreach ($contactKeys as $key) {
        $current = (string)migrate_setting($pdo, $key);
        if (!migrate_is_stale($current, $oldDomains) && filter_var($current, FILTER_VALIDATE_EMAIL)) {
            $contactDefault = $current;
            break;
        }
    }
}
foreach ($contactKeys as $key) {
    $current = migrate_setting($pdo, $key);
    if (migrate_is_stale($current, $oldDomains)) {
        $settingsPlan[$key] = ['from' => $current, 'to' => $contactDefault];
        $total++;
    }
}

include __DIR__ . '/includes/header.php';
?>
<div class="container-fluid">
  <div class="row">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
      <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h4 mb-0">Domain Migration</h1>
      </div>

      <?php echo display_flash_message(); ?>

      <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <div class="card mb-3">
        <div class="card-header bg-white"><strong>What this does</strong></div>
        <div class="card-body">
          <p>Content saved while the site ran on an old address still points at it. This replaces it with <strong><?php echo htmlspecialchars($newDomain); ?></strong> inside:</p>
          <ul class="mb-2">
            <li>Blog post bodies (inline images)</li>
            <li>Notification links and titles</li>
            <li>Site settings — including the placeholder social links and admin email left over from before the relaunch</li>
          </ul>
          <p class="mb-0 text-muted small">
            Old addresses handled in one run: <?php echo htmlspecialchars(implode(', ', $oldDomains)); ?>, plus any link still saved
            as <code>http://localhost/&hellip;</code> from a test install.
            Login email addresses are never changed, so nobody loses access. Running this twice is harmless, and any value you have
            already edited by hand is left alone.
          </p>
        </div>
      </div>

      <div class="card">
        <div class="card-header bg-white"><strong>Still needs fixing</strong></div>
        <div class="card-body">
          <?php if ($total === 0): ?>
            <div class="alert alert-success mb-0">Nothing left to migrate. All content already points at <strong><?php echo htmlspecialchars($newDomain); ?></strong>.</div>
          <?php else: ?>
            <table class="table table-sm mb-3">
              <thead><tr><th>Location</th><th class="text-end">Rows</th></tr></thead>
              <tbody>
                <?php foreach ($pending as $where => $n): ?>
                  <tr><td><code><?php echo htmlspecialchars($where); ?></code></td><td class="text-end"><?php echo $n; ?></td></tr>
                <?php endforeach; ?>
                <?php foreach ($settingsPlan as $key => $plan): ?>
                  <tr>
                    <td><code>site_settings.<?php echo htmlspecialchars($key); ?></code></td>
                    <td class="text-end" style="max-width:420px;">
                      <div class="text-muted small text-truncate"><?php echo $plan['from'] === null ? '(missing)' : htmlspecialchars($plan['from']); ?></div>
                      &darr;
                      <div class="fw-bold small text-truncate"><?php
                          if ($plan['to'] === '') {
                              echo in_array($key, $contactKeys, true) ? '(type it below — required)' : '(remove — no handle configured)';
                          } else {
                              echo htmlspecialchars($plan['to']);
                          }
                      ?></div>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <tr class="fw-bold"><td>Total</td><td class="text-end"><?php echo $total; ?></td></tr>
              </tbody>
            </table>

            <form method="post">
              <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
              <input type="hidden" name="action" value="rewrite">
              <?php if (array_intersect($contactKeys, array_keys($settingsPlan))): ?>
                <div class="row g-2 mb-3">
                  <div class="col-md-6">
                    <label class="form-label" for="contact_email">Public contact email <span class="text-danger">*</span></label>
                    <input type="email" id="contact_email" name="contact_email" class="form-control" required
                           value="<?php echo htmlspecialchars($contactDefault); ?>"
                           placeholder="An inbox you actually read, e.g. your Gmail for now">
                    <div class="form-text">
                      The <code>@mystudybase.ng</code> mailboxes do not exist yet, so use an inbox that receives mail today.
                      You can change it later on the Settings page once you buy real mailboxes.
                    </div>
                  </div>
                </div>
              <?php endif; ?>
              <button type="submit" class="btn btn-primary">
                <i class="fas fa-play me-1"></i> Run migration
              </button>
              <span class="text-muted small ms-2">Changes happen inside one transaction; a failure rolls everything back.</span>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </main>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
