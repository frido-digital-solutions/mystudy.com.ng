## Email Plan (Brevo Transactional API) – MyStudy.com.ng

This is the authoritative list of **important emails** we may send. We avoid “spammy” emails and focus on messages users must not miss if they’re offline.

### Priority levels
- **P0 (Must-have)**: Security, payments, rewards, critical moderation
- **P1 (Should-have)**: Challenge/competition outcomes, important engagement
- **P2 (Nice-to-have)**: Digests, marketing (only opt-in)

---

## P0 — Must-have emails

### 1) Email verification
- **Trigger**: new registration, resend verification
- **Purpose**: account security, onboarding

### 2) Password reset
- **Trigger**: user requests reset
- **Purpose**: account recovery

### 3) Payment successful / access granted
- **Trigger**: successful payment (e.g. access code issued)
- **Purpose**: user needs immediate proof + link to proceed

### 4) Reward claim outcomes
- **Trigger**: reward claim submitted → approved / rejected / paid
- **Purpose**: money/data/airtime outcomes are high importance

### 5) Critical moderation actions
- **Trigger**: user content removed/hidden; user suspended/banned
- **Purpose**: transparency + appeal/support path

---

## P1 — Should-have emails

### 6) Challenge ended: results + winners
- **Trigger**: challenge ends
- **Audience**: all participants
- **Special**: Top 3 get a premium winner version with CTA to claim reward

### 7) Corrections available (challenge)
- **Trigger**: corrections unlocked after challenge ends
- **Audience**: participants
- **Note**: can remain in-app only if we want fewer emails; keep optional

### 8) Account security events (optional)
- **Trigger**: password changed, email changed, login from new device
- **Note**: password change confirmation is usually a good P1

---

## P2 — Nice-to-have (opt-in or digest)

### 9) Messaging emails
- **Trigger**: new message when user hasn’t been active for X mins/hours
- **Recommendation**: opt-in only; throttled (no more than 1 per 30–60 mins)

### 10) Community engagement
- **Trigger**: reply on your question, mention
- **Recommendation**: digest (daily/weekly) unless users opt into instant

---

## Implementation rollout (safe)

### Phase 7b.1 (first)
- Verification email (Brevo)
- Access code/payment success email (Brevo)
- Challenge results email (Brevo, includes top 3 winner version)

### Phase 7b.2
- Reward claim approved/rejected/paid emails

### Phase 7b.3
- Password reset email
- Password changed confirmation

### Phase 7b.4 (optional, opt-in)
- Messaging + community digest/instant

