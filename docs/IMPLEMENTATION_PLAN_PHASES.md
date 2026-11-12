# Implementation Plan – Phases (No Breaking Changes)

This document breaks down all requested changes into **7 phases** so each can be tested and shipped without breaking existing behaviour.

---

## Summary

| Phase | Scope | Risk |
|-------|--------|-----|
| **1** | Quiz (normal + challenge) mobile: fixed nav + question grid | Low |
| **2** | Upload material: multiple files, 30MB cap, bulk mode | Medium |
| **3** | Welcome message: Battle Arena, Community Hub, Messaging | Low |
| **4** | Blog: premium look + share (copy link + native share) | Low |
| **5** | Materials card: remove download count | Low |
| **6** | Community forum: mobile filters inline + share + deep link/highlight | Medium |
| **7** | Email plan: document + implement key emails in sub-phases | Medium |

---

## Phase 1: Quiz (Normal + Challenge) Mobile UX

**Goal:** Match Battle quiz behaviour on mobile: fixed Prev/Next bar and compact question navigator (multiple numbers per line).

**Files:** `quiz.php` (and any shared layout used by challenge/special quiz).

**Changes:**

1. **Fixed bottom bar (like Battle)**
   - Reuse Battle pattern: a `.quiz-controls` (or equivalent) wrapper with `position: fixed; bottom: 0; left: 0; right: 0; z-index: 999;` and a solid background.
   - Add `padding-bottom` to the main quiz content (e.g. `~120px`) on viewports where the bar is fixed so content is not hidden behind it.
   - Apply only below a breakpoint (e.g. `max-width: 768px`) so desktop layout stays as is.

2. **Question navigator on mobile**
   - Ensure `#questionNav` stays a flex grid with multiple items per row: e.g. `display: flex; flex-wrap: wrap;` and **fixed-size** items (e.g. `width: 36px; min-width: 36px;` or similar) so ~8–10 fit per line on small screens.
   - Avoid any rule that makes `.nav-item` full width on mobile (e.g. `flex: 1 1 100%` or block display).
   - Optionally, on mobile only, move the question navigator into a horizontal scroll strip above the fixed Prev/Next bar (like Battle’s `.question-map`) so it stays visible without scrolling the main content.

**Testing:** Normal quiz + challenge quiz on a narrow viewport; confirm Prev/Next always visible at bottom and question numbers show several per line.

---

## Phase 2: Upload Material – Multiple Files, 30MB Cap, Bulk Mode

**Goal:** Support multiple file selection; max 30MB total per upload; for multiple files, skip title/description (use filenames only).

**Files:** `upload-material.php`, `models/Material.php` (if create logic is extended).

**Changes:**

1. **Multiple files + 30MB**
   - Change `<input type="file" name="file" ...>` to `name="file[]"` and add `multiple`.
   - In PHP: loop over `$_FILES['file']` (treat as array of files); validate each (type, size); sum sizes and enforce **total ≤ 30MB**; reject entire request if over.
   - Keep existing **per-file** limit (e.g. 10MB) or define a per-file max so no single file can dominate (e.g. 15MB each, 30MB total).

2. **Bulk mode (no title/description)**
   - If `count($_FILES['file']['name']) > 1` (or only one file but we still want consistency): do **not** show or submit title/description fields.
   - Use only: level, secondary_board, university_id, is_university_general (and any existing required fields). For each file, use **original filename** (sanitized) as title; description can be empty or a generic “Bulk upload”.
   - If exactly one file: keep current UI with optional title/description and autofill from filename.

3. **UI**
   - When `multiple`: show a list of selected filenames and total size; show “Total: X MB / 30 MB”; disable or hide title/description section.
   - Optional: drag-and-drop that adds to the file list (still enforcing 30MB total).

**Testing:** Single file (unchanged behaviour); 2–5 files under 30MB; over 30MB rejected; bulk entries created with correct filenames and no manual title/description.

---

## Phase 3: Welcome Message – Battle Arena, Community Hub, Messaging

**Goal:** First-time welcome modal/content includes Battle Arena, Community Hub, and Messaging.

**Files:** `dashboard.php` (welcome modal HTML and any copy).

**Changes:**

- Add 1–2 short sections (or bullets) to the welcome modal:
  - **Battle Arena:** Compete in live head-to-head quizzes and climb the leaderboard.
  - **Community Hub:** Ask questions, share knowledge, and discuss with other students.
  - **Messaging:** Chat with other users privately from the messages icon.
- Keep existing features (Practice Quizzes, Special Challenges & Rewards, Study Materials) and style consistent.

**Testing:** Trigger first-time login (or re-show modal in dev); confirm new copy appears and layout still works on mobile/desktop.

---

## Phase 4: Blog – Premium Look + Share on Cards

**Goal:** More premium presentation of blog cards; each card has a share icon with “Copy link” and native share (where available).

**Files:** `blog.php`, possibly `assets/css/design-system.css` or blog-specific styles.

**Changes:**

1. **Premium look**
   - Refine card styling: spacing, typography, subtle shadow/hover, maybe a small accent (e.g. category colour bar or icon) so the grid feels more polished.
   - No change to data or URLs; only CSS and possibly minor markup (e.g. wrappers for share icon).

2. **Share on each card**
   - Add a share icon/button (e.g. share icon) on each post card.
   - On click:
     - **Copy link:** build URL (e.g. `SITE_URL . '/post.php?slug=' . $slug`), copy to clipboard, show brief toast “Link copied”.
     - **Native share:** if `navigator.share` exists, call it with `title`, `text` (e.g. excerpt), `url`; fallback to copy link only if unsupported or user cancels.
   - Optional: dropdown with “Copy link” and “Share…” (opens native share when available).

**Testing:** Desktop and mobile; copy link works; on supported devices, native share opens and pre-fills title/url.

---

## Phase 5: Materials Card – Remove Download Count

**Goal:** Remove the download count from material cards.

**Files:** `materials.php`, and any other template that shows material cards (e.g. `ajax/materials.php` if it returns HTML for cards).

**Changes:**

- Remove the line(s) that output download count (e.g. `<span>… download_count …</span>`).
- Keep “Download” button and any other meta (category, level, date, etc.).
- Do **not** change the `download_count` column or increment logic; only stop displaying it.

**Testing:** Materials listing and filters still work; download button still works; no download number shown.

---

## Phase 6: Community Forum – Mobile Filters, Share, Deep Link + Highlight

**Goal:** Mobile filter toggles inline (compact); share on questions and answers; links to specific question or answer with clear highlight.

**Files:** `discussions.php`, `discussion_view.php`, and related CSS/JS.

**Changes:**

1. **Mobile filters**
   - On mobile, make filter tabs/chips **inline**: e.g. `flex-wrap: wrap` with small gaps, or a horizontal scroll row so “All Levels”, “University”, “Secondary”, “Score”, “Newest”, “Unanswered” (and subject if any) sit on 1–2 lines instead of stacking vertically.
   - Use smaller padding/font so more fit; keep active state clear.

2. **Share on questions and answers**
   - **List (discussions.php):** Each question card gets a small share icon; link = `discussion_view.php?id={id}` (and optionally `#answer-{id}` for answers if we add anchors).
   - **Thread (discussion_view.php):** Share icon on the main question and on each answer block.  
   - **URLs:**
     - Question: `.../discussion_view.php?id=123`.
     - Answer: `.../discussion_view.php?id=123#answer-456` (add `id="answer-456"` on the answer container, where 456 = comment id).

3. **Deep link and highlight**
   - When the page loads with a hash (e.g. `#answer-456`), use JS to:
     - `document.getElementById('answer-456')` and scroll into view (`scrollIntoView({ behavior: 'smooth', block: 'center' })`).
     - Add a temporary “highlight” class (e.g. box-shadow + background) and remove it after 2–3 seconds so the linked answer is clearly indicated.

**Testing:** Filters compact on mobile; share from list and from thread; open link with `#answer-xxx` and confirm scroll + highlight.

---

## Phase 7: Email Plan – Document + Implement Key Emails

**Goal:** One place that lists every email we might send; then implement only the most important ones in small, safe steps.

### 7a – Email inventory (document only)

Create a single reference (e.g. `docs/EMAIL_PLAN.md`) that lists:

**Already sent (or partially) in codebase:**

- Verification email (signup)
- Password reset
- Challenge ended – notification + email to participants (top 3 get “claim reward” link; others get leaderboard link) – see `Notification.php`

**To add or clarify:**

1. **Challenge / competition**
   - When a challenge ends: email all participants with rank and score; top 3 (with prize) get prominent “You won – claim reward” CTA and link to `claim_reward.php?quiz_id=...`.

2. **Content moderation / safety**
   - When a user’s content is **reported:** email the user that their content is under review, what was reported (e.g. “Your post in Community Forum”), and any next steps (e.g. “We may remove it if it violates guidelines”).

3. **Account / security**
   - Password change confirmation.
   - Email change: confirm old and new address.
   - Login from new device (optional; can be Phase 7 later).

4. **Rewards / payments**
   - When a reward is **approved** or **paid:** email confirming claim and (if applicable) payment method/details.
   - When a reward claim is **rejected:** email with reason and link to support or rules.

5. **Community / engagement**
   - When someone **replies** to the user’s discussion question or mentions them (if we add @mentions): optional in-app only first; email digest or instant can be a later phase.
   - When a **message** is received: optional; many apps do in-app only; if we add email, make it opt-in and low frequency.

6. **System / admin**
   - Critical platform announcements (maintenance, policy change) – usually a separate “broadcast” tool; can be listed in the plan as “future”.

The document should state for each:
- **Trigger** (event that sends the email)
- **Recipient** (e.g. “participants”, “top 3”, “content author”)
- **Main content** (e.g. “rank, score, claim link”)
- **Link** (e.g. claim_reward.php, discussion_view.php)
- **Priority** (P0 = must have, P1 = should have, P2 = nice to have)

### 7b – Implement in sub-phases (no breaking changes)

- **7b.1**  
  - Harden existing “challenge ended” email (ensure it always sends for top 3 with claim link; fix any bugs).
  - Add or clarify “content reported” email when a report is submitted (trigger in report flow; one email per report to the content owner).

- **7b.2**  
  - Reward approved/paid and reward rejected emails (when admin or system updates claim status).

- **7b.3**  
  - Password change confirmation; optional: email change confirmation.

- **7b.4**  
  - Optional: reply/mention or new-message emails (only after product decision and opt-in).

Each sub-phase: implement, test with real or test accounts, then deploy. Use a single mail helper (e.g. a small `Mail` or `EmailService` class) and plain-text or simple HTML so we don’t break existing `mail()` or future SMTP.

---

## Order and Dependencies

- **Phases 1–6** can be done in any order; they touch different areas (quiz, upload, dashboard copy, blog, materials list, forum).
- **Phase 7** should start with **7a** (document) so the team agrees on the list; then **7b** can run in parallel with finishing 1–6 or after.

---

## How Many Phases to “Fix All Perfect, No Mistake”

- **7 main phases** cover all 8 request items (item 8 is Phase 7 – email plan + implementation).
- Phase 7 is split into **7a** (plan) and **7b.1–7b.4** (implementation sub-phases) so email work is small and reversible.
- Total: **7 phases** for the main work; **4 sub-phases** inside Phase 7 for email rollout.

If you want to minimise risk further, Phase 1 (quiz) and Phase 5 (materials count) can be done first as quick wins; then Phase 3 (welcome), then 2, 4, 6, and 7.
