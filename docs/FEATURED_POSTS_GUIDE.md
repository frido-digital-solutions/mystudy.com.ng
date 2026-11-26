# Featured posts: how they are fetched, and how to create a new one

This is the handover document for MyStudy Base's news and featured posts. It is written so
that any IDE, any AI assistant, or any developer can pick it up cold and produce a new,
correct, published post without guessing anything.

Read this file first. Do not change the pipeline described here unless you change it in the
same commit, and update this file in that commit.

---

## 1. What a "featured post" is here

A featured post is a normal article about news that matters to Nigerian students — an
examination announcement, a registration deadline, a result, a policy change — written for
somebody who wants to know **what to do next**. It is not a press release and it is not a copy
of somebody else's article.

Every post:

- has a headline, an excerpt, and a body written in HTML,
- carries **its own picture**, a 1200×630 banner drawn on our own server,
- carries **the date of the news it reports**, not the day the file was typed,
- ends with a **Sources** list pointing at the announcements it was written from,
- links to the relevant part of the site (an exam page, the materials library, the quizzes).

---

## 2. Where everything lives

| Path | What it is |
|---|---|
| `content/exam_news_posts.php` | **The post data.** A PHP file that returns an array of posts. This is the single source of truth for every post. |
| `content/exam_posts_2027.sql` | The same posts as a plain SQL file, for hosts with no terminal. Generated, not hand-edited. |
| `cli/publish_exam_posts.php` | Puts the posts into the database. Idempotent. |
| `cli/make_posts_sql.php` | Regenerates `content/exam_posts_2027.sql` from the data file. |
| `cli/make_news_banners.php` | Draws the 1200×630 banner for every post into `assets/img/news/`. |
| `assets/img/news/` | The generated banners. Committed to the repository. |
| `assets/img/exams/` | The official WAEC, NECO and JAMB marks, used to draw the banners. |
| `assets/fonts/Poppins-*.ttf` | Poppins, the site's typeface, used by the banner generator. |
| `models/Post.php` | The model that reads posts out of the database. |
| `blog.php` | The blog listing page. |
| `post.php` | A single post, plus the `BlogPosting` structured data. |
| `index.php` | The home page, which shows the three newest posts. |

Two database tables are involved:

```
posts       id, title, slug (unique), excerpt, content (HTML),
            author_id, created_at, level, university_id, is_university_general
post_images id, post_id, file_path, caption, sort_order
```

`level` is either `Secondary` or `University`. `is_university_general = 1` means the post is
for every university, not one particular one. `author_id = 13` is the MyStudy Base account that
already owns the existing posts.

---

## 3. How posts are fetched and displayed

**The listing — `blog.php`**
1. Reads `q` (search), `level` (Secondary/University), `university_id`, `page` from the query string.
2. Picks the query: `searchPosts()` if there is a search word, otherwise `listFiltered()`
   (which filters by level and university), otherwise `list()`.
3. `models/Post.php` always orders by `created_at DESC`, so **the newest post is the first one**.
   This is why the date of the news matters: it decides the order.
4. Shows 15 per page.
5. **The card picture is the first `<img>` inside the post body.** `blog.php` pulls it out with a
   regular expression. If the body has no image, it falls back to a random Unsplash picture.
   *This is why the banner must be the first thing in the content.*
6. The card shows the date as `M j, Y` and the excerpt (or the first 140 characters of the body).

**A single post — `post.php`**
1. `getBySlug()` finds the post by the `slug` query parameter.
2. The date is printed as `F j, Y` from `created_at`.
3. The body is printed as-is, so the HTML in the data file is the HTML the reader sees.
4. Related posts come from `getRelatedPosts()` — the same level, excluding this one.
5. The page sends `BlogPosting` structured data: headline, description, picture, author,
   publisher, `datePublished` and `dateModified`.
   - `datePublished` comes from `created_at` — the day the news broke.
   - `dateModified` comes from a small comment at the top of the body:
     `<!-- msb-updated: 2026-11-26 09:00:00 -->`. If the comment is missing, it falls back to
     `created_at`.

**Everywhere else**
- The home page shows the three newest posts (`$postModel->list(3, 0)`).
- `sitemap.xml.php` lists every post as `/post.php?slug=...` with `weekly` change frequency and
  `lastmod` taken from `created_at`. A new post is therefore in the sitemap automatically — there
  is nothing to add by hand.

---

## 4. The shape of a post in the data file

Every entry in the array in `content/exam_news_posts.php` looks like this:

```php
[
    'slug'        => 'neco-2027-ssce-registration-open-exam-moved-to-april',
    'title'       => 'NECO 2027 SSCE: Registration Is Open and the Whole Examination Has Moved',
    'excerpt'     => 'One or two sentences. Shown on the card and used as the meta description.',
    'level'       => 'Secondary',            // or 'University'
    'university_general' => true,            // only for University posts: true = for everybody
    'date'        => '2026-11-06 09:00:00',  // the day the news broke
    'date_label'  => '6 November 2026',      // the same date, written out
    'banner'      => [
        'file'   => 'neco-2027-ssce.png',    // the picture in assets/img/news/
        'exam'   => 'neco',                  // which mark and colour to draw it with
        'kicker' => 'NECO 2027',             // the small line above the headline
    ],
    'sources'     => [
        ['What the source is', 'https://the.link'],
    ],
    'content'     => <<<'HTML'
        <p>The article, in plain HTML.</p>
        HTML,
],
```

Rules for the fields:

- **`slug`** — lowercase, hyphenated, unique, and it never changes once published, because it is
  the URL. If you change it you break every link to the post.
- **`title`** — under about 90 characters so it fits on one card. This is also the `<title>` tag.
- **`excerpt`** — one or two sentences, no HTML. It is the Google snippet.
- **`date`** — the day the news broke, taken from the source. This is the honest, professional
  choice: a newspaper printed the day after an announcement is dated the day after. Never date a
  post before the information existed.
- **`banner.exam`** — one of `waec`, `neco`, `jamb`, `university`.
- **`banner.kicker`** — two or three words, in capitals on the picture.
- **`sources`** — every source is a real page you opened and read. Not a search result page, not
  a social media aggregator.
- **`content`** — plain HTML. `<h3>` for sections, `<ul>`/`<ol>` for lists, `<a>` for links.
  Use the `&pound;` style entity the existing posts use for naira amounts.

---

## 5. The pictures

**Every post gets its own 1200×630 banner, drawn by us, from the official examination mark on a
white card, in that examination's colour, with the headline set in Poppins.** No stock photograph,
nothing lifted from a news site, so there is no copyright problem and the pictures match the site.

| Style | Colours | Mark |
|---|---|---|
| `waec` | deep navy `#0D215C` → `#040C28`, yellow accent | `assets/img/exams/waec.png` |
| `neco` | green `#047857` → `#042F2E`, lime accent | `assets/img/exams/neco.png` |
| `jamb` | deep green `#045F46` → `#022C22`, mint accent | `assets/img/exams/jamb.png` |
| `university` | indigo `#3730A3` → `#1E1B4B`, pale blue accent | the MyStudy Base logo |

The marks themselves came from the official sources: JAMB from `jamb.gov.ng`, WAEC from Wikimedia
Commons, NECO from `neco.gov.ng` (NECO publishes its crest at 43×43, so it is drawn smaller
rather than blown up and made to look soft).

To draw them:

```
cd /c/Users/USER/Downloads/mystudybase.com.ng-main
/c/xampp/php/php.exe -d extension=gd -d extension_dir=/c/xampp/php/ext cli/make_news_banners.php
```

The `gd` extension must be loaded explicitly on the command line, or the script dies with
"Call to undefined function imagecreatefrompng()". The script reads the data file, so it draws a
banner for every post in it — add a post first, then run it.

If you add a new examination style, add it to the `$styles` array in `cli/make_news_banners.php`,
put its mark in `assets/img/exams/`, and use its key as the post's `banner.exam`.

---

## 6. Publishing

**Local, against the local database:**

```
/c/xampp/php/php.exe cli/publish_exam_posts.php            # add anything missing
/c/xampp/php/php.exe cli/publish_exam_posts.php --dry-run  # show what it would do
/c/xampp/php/php.exe cli/publish_exam_posts.php --force    # also refresh posts that already exist
/c/xampp/php/php.exe cli/make_posts_sql.php                # regenerate the SQL file
```

The script is **idempotent**: a post whose slug already exists is left alone, so a second run
changes nothing and a post edited by hand in the admin panel is never overwritten. `--force`
refreshes those posts, and is the only way to change an existing post's date or body.

**On the live server**, after deploying, either:

```
cd ~/mystudy.com.ng && php cli/publish_exam_posts.php
```

or import `content/exam_posts_2027.sql` through phpMyAdmin (select the database, then Import).
Both routes produce exactly the same posts. The SQL file is also idempotent — it skips a post
whose slug already exists and updates the date of one that does.

**Deploying the code** is a separate step and always goes through cPanel:
cPanel → Git Version Control → Update from Remote → Deploy HEAD Commit. The deploy script is
`.cpanel.yml`, and it copies named folders. If you add a new top-level folder that the server
needs — `content/` is one — add it to the `/bin/cp -Rf` line in `.cpanel.yml`, or it will not
reach the server.

---

## 7. The rules for the writing

These are the rules the existing posts follow, and they are the reason the posts can be trusted.

1. **Only real news, from real sources.** Every post is written from announcements or reporting
   you opened and read. At least one source should be the body's own authority — WAEC's account,
   `neco.gov.ng`, `jamb.gov.ng`, the NUC — not only a news outlet.
2. **Never copy.** Write it again in your own words. A student reading two articles about the
   same announcement should not recognise the same sentences in both.
3. **Give the dates and the numbers exactly.** Registration windows, fees, penalties, counts. If
   two sources disagree, say so in the post rather than picking one silently.
4. **Date the post to the day the news broke**, and state plainly in the footer when the text was
   last reviewed. Both dates are true; neither pretends to be the other.
5. **Tell the student what to do.** Every post ends with something the reader can act on this
   week.
6. **Link into the site.** An exam post links to `/waec.php`, `/neco.php`, `/jamb.php` or the
   materials page. The site is where they practise, so the article should send them there.
7. **Say when something is not confirmed.** "JAMB has not announced a date" is a useful sentence;
   a guessed date presented as fact is not.
8. **No invented facts, no invented quotes.** If a number is not in a source you read, it does not
   go in the post.

---

## 8. Recipe: a new post, from nothing

1. **Research.** Search for the news, then open the real pages. Collect the dates, the fees, the
   requirements, and the links. Note where sources disagree.
2. **Write the post** in the data file, following the shape in section 4. Keep the title under
   about 90 characters and the excerpt to one or two sentences.
3. **Draw the picture.** Add the banner entry, then run `cli/make_news_banners.php`.
4. **Publish locally.** Run `cli/publish_exam_posts.php`, then open `http://localhost/mystudybase.com.ng-main/blog.php`
   and the post itself. Check: the card picture, the date, the excerpt, the sources, the links.
5. **Regenerate the SQL.** Run `cli/make_posts_sql.php`.
6. **Commit.** `git add` the data file, the SQL file, the new banner, and any changed script.
7. **Push**, then deploy in cPanel, then publish on the server with the script or the SQL file.

---

## 9. The checklist before you call a post done

- [ ] Every fact in it is in a source you opened.
- [ ] Every source is listed at the bottom of the post and the link works.
- [ ] The title is under about 90 characters and reads like a headline, not a sentence.
- [ ] The excerpt is one or two sentences and would make a student click.
- [ ] The date is the day the news broke, and the footer says when the text was reviewed.
- [ ] The banner exists, is 1200×630, and has the official mark on a white card.
- [ ] The banner is the **first** image in the body — that is what the blog card uses.
- [ ] The post links into the site (exam page, materials, quizzes).
- [ ] The body is plain HTML with `<h3>` sections, and it renders on a phone.
- [ ] `php -l` passes on every PHP file you touched.
- [ ] The post shows correctly on `/blog.php`, on `/post.php?slug=...`, and in the sitemap.

---

## 10. Never do these

- Never push to GitHub without the owner's approval.
- Never delete or re-upload the live hosting folder — deploy through `.cpanel.yml` only.
- Never put secrets in either repository.
- Never write to a real user's row.
- Never date a post earlier than the information existed.
- Never state an unannounced date as if it were announced.
- Never reuse a photograph from a news site.

---

## 11. A note on keeping it fresh

Google keeps the pages that change. A site that publishes a real post every week, on the days
the news happens, keeps more pages in the index than one that publishes twelve posts in one
afternoon. So the next batch should not be twelve posts at once: publish each one within a day
or two of the announcement it covers, then update the same post when the next deadline is
announced.

When an announcement changes — a new date, a fee revision, a result release — **update the
existing post** rather than writing a second one about the same thing. Change its `date` and
`date_label` in the data file, run `cli/make_news_banners.php` if the headline changed, then
publish with `--force`.
