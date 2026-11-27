# The Study library - how it is built and how to grow it

The Study section is the past question library. It is the part of MyStudy
Base where a student sits down and reads a real examination paper, question
by question, with the options and the answer under each one.

```
study.php                                          the three examinations
study.php?exam=WAEC                                the subjects of that exam
study.php?exam=WAEC&subject=english-language       the years of that subject
study.php?exam=WAEC&subject=english-language&year=2024
                                                    the paper itself
```

## What is in the database

Two tables, created on demand by `includes/study_schema.php`:

| Table | Holds |
|---|---|
| `study_papers` | one real paper: exam, subject, year, paper code, title, source, question count |
| `study_questions` | the questions inside it: the question text, its options, the correct option, the explanation |

They are deliberately separate from `quizzes` / `quiz_questions` /
`quiz_options`. Those are built for timed, scored, prize quizzes - one quiz
is one sitting, with an attempt log. A study library is different: the same
question belongs to a paper a student reads at their own pace, next to the
other papers for that subject and year. Do not try to force one shape onto
the other.

The key of a paper is:

    exam + subject_slug + year + paper_code + source

so the same paper from two different sources is two papers, and the same
paper from the same source is one.

`subject_slug` is the library's own name for a subject. The sources do not
agree with each other - Acadmate says `use-of-english`, SmarTest says
`english-language`, StudentFirst says `english` - so every source name is
mapped onto one canonical slug in `content/study_subjects.php`. If you add a
source, add its names to that file first.

## Where the questions come from

Five harvesters, one per source. They all follow the same rules:

* they are idempotent - a paper already in the library is skipped, so a run
  can be stopped and started again;
* they print one line per paper, so a run can be watched;
* `--refresh` re-harvestes what is already there;
* `--dry-run` parses and prints but writes nothing;
* `--limit=N` does only the first N papers, for a quick test.

| Command | Source | What it brings |
|---|---|---|
| `php cli/harvest_acadmate.php` | Acadmate | 209 JAMB papers, 1978-2025, question + options + answer |
| `php cli/harvest_smartest.php` | SmarTest | 221 papers across JAMB, WAEC and NECO, question + options + answer |
| `php cli/harvest_skolra.php` | Skolra | 416 WAEC and JAMB papers, 1988-2024, question + options + answer + worked explanation |
| `php cli/harvest_studyproper.php` | StudyProper | papers for all three examinations, question + options + answer + worked explanation |
| `php cli/harvest_schoolngr.php` | SchoolNGR | NECO, WAEC and JAMB papers, question + options + answer, each question carrying the year it came from |

Run them in this order - the earlier ones are quicker, so the library is
useful sooner:

```bash
php cli/harvest_acadmate.php
php cli/harvest_smartest.php
php cli/harvest_skolra.php
php cli/harvest_studyproper.php
php cli/harvest_schoolngr.php
```

The StudyProper and SchoolNGR runs are the longest: between them they walk
every examination, every subject and every year. Stop either and start it
again as often as you like - both skip what they have already stored.

Both take `--exam=waec|gce|jamb|neco` to work on one examination at a time.
Run one at a time: a source that is asked for too much at once starts
dropping requests.

### The CA bundle

XAMPP's CA bundle is from 2022 and no longer carries the roots some of
these sites use. Every request to them then fails with "unable to get
local issuer certificate". A current bundle is downloaded to `.scratch/`
and used instead when it is there:

```bash
curl -sL https://curl.se/ca/cacert.pem -o .scratch/cacert.pem
```

`.scratch/` is not in the repository. If a harvest suddenly cannot read a
site, check that this file exists and is newer than 50 KB.

## Publishing the library to the live site

Two routes. Pick one.

**Run the harvesters on the server.** The harvesters only need PHP with cURL
and an outbound connection. After deploying the repository:

```bash
cd ~/mystudy.com.ng
php cli/harvest_acadmate.php
php cli/harvest_smartest.php
php cli/harvest_skolra.php
php cli/harvest_studyproper.php
php cli/harvest_schoolngr.php
```

They write straight into the live database. Nothing else is needed.

**Import the SQL.** On a machine that has already run the harvesters:

```bash
php cli/make_study_sql.php            # content/study_library.sql
php cli/make_study_sql.php --gzip     # content/study_library.sql.gz
```

Then in phpMyAdmin: select the database, click **Import**, choose the file,
click **Go**. The file is import-safe - a row that is already there is
skipped - so running the import twice does nothing the second time.

The export is not kept in the repository: it runs to tens of megabytes.

## Rules that keep the library honest

1. **Only real papers.** Every question carries the examination, subject and
   year it came from. If a source does not say which year a question is
   from, it does not go in the library.
2. **Every question has an answer.** A question with no answer is not a
   past question, it is a practice guess. It does not go in.
3. **Say where it came from.** Each paper keeps its source and its source
   URL, and the paper page shows it.
4. **Do not guess at a date.** A paper is filed under the year the source
   gives it, not the year we happened to find it.
5. **Never invent a question.** The library is real examination material.
   If a source cannot be read, the paper is skipped, not guessed at.
6. **Respect robots.txt.** A source that asks crawlers not to read a path
   is not read. StudentFirst, for example, keeps its answers inside its paid
   app and its API is encrypted - so its questions are not taken, even
   though the pages are readable.

## Adding a new source

1. Find out how the source serves its questions: a page per paper, a
   sitemap of paper URLs, or an API.
2. Check its robots.txt. If the path you need is disallowed, stop.
3. Add the source's subject names to `content/study_subjects.php`.
4. Copy `cli/harvest_skolra.php` as the shape of the new harvester - it is
   the simplest of the four.
5. Run it with `--dry-run` first and read the output. Then `--limit=5` and
   read the questions it actually parsed. Only then run it for real.
6. Check the result in the browser: the paper must show its question, its
   options and its answer, and the year must be right.

## The never-do list

* Do not edit a harvested question by hand in the database. Fix the
  harvester and re-run it with `--refresh`, so the fix survives.
* Do not change the meaning of a question to make it fit a subject.
* Do not file a paper under a year the source did not give.
* Do not put the same paper under two years.
* Do not remove the source link from the paper page.
