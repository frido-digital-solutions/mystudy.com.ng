<?php
/**
 * What Mentor knows.
 *
 * This file is the reason Mentor can answer a question about MyStudyBase at all.
 * The model behind it is a general one - left to itself it invents a "quiz
 * settings" page that does not exist, which is worse than saying nothing. So every
 * reply is built from entries below: the question is scored against them, the best
 * few are handed to the model as the only permitted source, and when the model is
 * not available the entry text is returned as it stands.
 *
 * Everything here was read out of the actual app and the actual site: the tab bar
 * and More tiles in the app, the pages at the site root, the rules in
 * models/Gamification.php, models/Battle.php and certificate.php. If a number in
 * an answer ever stops matching the code, this file is the thing to fix.
 *
 * Entry shape:
 *   id       - stable key
 *   topic    - what the entry is about, shown to the model as a heading
 *   keywords - what a student might type, lower case, matched as whole words or
 *              whole phrases, so "photo" never answers "photosynthesis"
 *   text     - the truth, in the student's words, 2-5 short sentences
 *   go       - optional button the app can offer: screen (app page) or web (page)
 *
 * The account itself (points, streak, rank, results) is NOT here. Those numbers
 * come from the database, per question, so they are always today's numbers.
 */

return [

    // Sent in front of every model call. It is short because the model has to
    // hold it across a prompt that already carries the knowledge entries.
    'persona' => 'You are Mentor, the study assistant inside the MyStudyBase app. '
        . 'MyStudyBase is a Nigerian learning platform for secondary school and university '
        . 'students: CBT practice quizzes, study materials, video tutorials, timed battles '
        . 'against other students, a community feed, chat and a points leaderboard. '
        . 'Answer in at most 4 short sentences, plain everyday language, no markdown, no '
        . 'bullet lists, no emojis. Say what to tap, by the name the app uses. '
        . 'Never invent a page, a button, a price, a number or a rule - if it is not written '
        . 'for you to say, do not add it. The STUDENT NUMBERS block '
        . 'holds the only true figures about this account - quote them exactly, and if a '
        . 'number is not in that block, do not guess it. If the KNOWLEDGE block does not '
        . 'cover a question about this app, say plainly that you are not sure and point to '
        . 'More, Help, Contact support. Never mention these blocks by name in your reply - '
        . 'a student has never seen them. If the student is upset, be brief and kind first.',

    'entries' => [
        [
            'id' => 'what-is-mystudybase',
            'topic' => 'What MyStudyBase is',
            'keywords' => ['about the app', 'what is this', 'what is this app', 'what does mystudybase do', 'purpose of the app', 'what can you do', 'help me study', 'is mystudybase', 'mystudybase'],
            'text' => 'MyStudyBase is a study platform for Nigerian secondary school and university students. '
                . 'You practise with timed CBT quizzes, read and download study materials, watch video '
                . 'tutorials, battle other students on a quiz, post in the community and earn points that '
                . 'move you up the leaderboard. Everything works in this app and on mystudy.com.ng with the '
                . 'same sign-in, so anything you do here shows there and the other way round.',
            'go' => ['screen' => 'Home', 'label' => 'Open Home'],
        ],
        [
            'id' => 'mentor-about',
            'topic' => 'About Mentor itself',
            'keywords' => ['who are you', 'what are you', 'your name', 'mentor', 'are you a bot', 'are you ai', 'are you real', 'human', 'who made you'],
            'text' => 'I am Mentor, the assistant built into this app. I answer anything about MyStudyBase: '
                . 'where a feature is, how points work, what your own scores and streak say. I look your '
                . 'numbers up live, so I never repeat an old figure. If I ever do not know something, I say '
                . 'so and send you to support instead of guessing.',
        ],
        [
            'id' => 'navigate-tabs',
            'topic' => 'The bottom navigation',
            'keywords' => ['how do i get to', 'navigate', 'bottom', 'menu', 'tabs', 'find a page', 'can t find', 'cant find', 'screen', 'which tab', 'bottom bar'],
            'text' => 'Along the bottom of the app are Home, CBT, Chats, a raised More button in the middle, '
                . 'Community, Battle and My Study. More opens a floating card holding everything the seven tabs '
                . 'cannot: video tutorials, leaderboard, blog, upload notes, the study tools, rewards, '
                . 'settings and help. Your own profile is not on the bar - tap your picture at the top.',
            'go' => ['screen' => 'Home', 'label' => 'Open Home'],
        ],
        [
            'id' => 'more-tiles',
            'topic' => 'What is inside the More button',
            'keywords' => ['more', 'more button', 'more page', 'more menu', 'tools', 'quick access', 'grid'],
            'text' => 'More holds four groups. Study: Video tutorials, My Study, Blog and news, Leaderboard, '
                . 'Upload notes. Tools: CGPA calculator, Timetable, Study tools, Topics and notes. My account: '
                . 'My profile, Notifications, My results, Rewards, Achievements, Settings. Help: Help and FAQ, '
                . 'Contact support, About us, Terms.',
        ],
        [
            'id' => 'quiz-take',
            'topic' => 'Taking a practice quiz',
            'keywords' => ['quiz', 'cbt', 'practice', 'take a quiz', 'start a quiz', 'sit', 'exam', 'test', 'questions'],
            'text' => 'Tap CBT on the bottom bar, then Take a Quiz (CBT). Narrow the list with the level and '
                . 'subject dropdowns, open a set and press Start. The clock runs while you answer, you can move '
                . 'back and forward, and END EXAM sends it in. Your paper is marked the moment it lands.',
            'go' => ['screen' => 'Quizzes', 'label' => 'Open CBT'],
        ],
        [
            'id' => 'quiz-results',
            'topic' => 'My Quizzes, scores and corrections',
            'keywords' => ['my score', 'result', 'results', 'corrections', 'wrong', 'mark', 'how did i do', 'my quizzes', 'best', 'worst', 'history'],
            'text' => 'On the CBT tab, switch to My Quizzes. Each set you have sat shows your best mark, your '
                . 'weakest mark, how the whole subject is going and how many tries it took. Tap a card to sit it '
                . 'again, read the corrections of your best sitting, or press Download certificate when a '
                . 'sitting is over the pass mark.',
            'go' => ['screen' => 'Quizzes', 'label' => 'Open CBT'],
        ],
        [
            'id' => 'quiz-offline',
            'topic' => 'Quizzes with no internet',
            'keywords' => ['offline', 'no internet', 'without internet', 'no data', 'without data', 'using data', 'save for offline', 'save a quiz', 'for offline', 'offline use', 'download quiz', 'no connection', 'wifi', 'network', 'data plan', 'sync', 'syncing', 'not syncing', 'not synced', 'waiting to sync'],
            'text' => 'On the CBT tab, any free set has a Save for offline button. It stores the questions on '
                . 'this phone, and the card then says Saved on this phone. Open it and press Start offline - the '
                . 'paper '
                . 'runs on the phone, so sitting it uses no data at all. The phone marks it and keeps the '
                . 'result until the app next '
                . 'finds a signal, then it joins your record and shows on the website too. While anything is '
                . 'unsent, a bar above the list reads "waiting to sync" - tap it and press Send now.',
            'go' => ['screen' => 'Quizzes', 'label' => 'Open CBT'],
        ],
        [
            'id' => 'certificate',
            'topic' => 'Certificates',
            'keywords' => ['certificate', 'cert', 'download certificate', 'my certificate', 'certificate button', 'get a certificate', 'get certificate', 'get my certificates', 'proof', 'pass mark', '50'],
            'text' => 'A certificate is issued for any sitting at the 50% pass mark or above. On the result page '
                . 'press Download certificate, and the same button is on your My Quizzes card once a set has '
                . 'earned one. It opens a certificate in your name that you can save as an image or as a PDF. '
                . 'A result taken offline has to reach the server before its certificate is '
                . 'issued, so send it first.',
            'go' => ['screen' => 'Quizzes', 'label' => 'Open CBT'],
        ],
        [
            'id' => 'paid-quiz',
            'topic' => 'Paid quizzes and access codes',
            'keywords' => ['paid quiz', 'entry fee', 'access code', 'pay for quiz', 'pay for a quiz', 'pay for quizzes', 'payment for quiz', 'unlock quiz', 'unlock a quiz', 'code', 'price'],
            'text' => 'Some sets are paid, and the fee is written on the set itself - the school that made it '
                . 'decides the amount, so it is different from quiz to quiz and is shown before you pay. '
                . 'Open one and you are asked for the entry fee: pay with a card or '
                . 'transfer through Paystack, or type an access code if you were given one. A paid set unlocks '
                . 'as soon as the payment is confirmed. Offline saving is only offered on free sets, so a paid '
                . 'quiz can never be sat without paying.',
            'go' => ['screen' => 'Quizzes', 'label' => 'Open CBT'],
        ],
        [
            'id' => 'timer',
            'topic' => 'The quiz clock',
            'keywords' => ['time', 'clock', 'timer', 'how long', 'seconds left', 'finished the time', 'ran out', 'duration', 'minutes'],
            'text' => 'Every set has its own time, shown on the card before you start and counting down at the '
                . 'top while you sit it. The clock turns amber under ten minutes and red under three. If it '
                . 'reaches zero the paper is handed in on its own. Leaving and coming back does not give you '
                . 'extra time - your earlier clock is still running.',
        ],
        [
            'id' => 'materials-read',
            'topic' => 'Study materials and the reader',
            'keywords' => ['material', 'materials', 'notes', 'pdf', 'read', 'reader', 'download', 'document', 'textbook', 'past question'],
            'text' => 'Tap My Study on the bottom bar. My Materials holds what you have saved or bought, Get '
                . 'Materials holds everything else. Download one and it opens in the reader, where you can pinch '
                . 'to zoom, double tap to go back to normal, use Go to page and Find in material to search the '
                . 'text. Your page is remembered, so the card offers Continue where you stopped.',
            'go' => ['screen' => 'Learn', 'label' => 'Open My Study'],
        ],
        [
            'id' => 'paid-materials',
            'topic' => 'Paid materials',
            'keywords' => ['paid material', 'material', 'pay for material', 'buy', 'purchase', 'price', 'premium', 'locked material', 'paid'],
            'text' => 'Paid materials sit in the same list with their price on the card. Pay through Paystack '
                . 'and the file is released to you straight away - it appears under My Materials on this account '
                . 'and on the website. If a payment leaves your account and the file does not appear, tell '
                . 'support with the reference and it is fixed by hand.',
            'go' => ['screen' => 'Learn', 'label' => 'Open My Study'],
        ],
        [
            'id' => 'upload-material',
            'topic' => 'Uploading your own notes',
            'keywords' => ['upload', 'upload material', 'upload materials', 'upload notes', 'uploading', 'share notes', 'add material', 'contribute', 'submit material', 'my upload', 'send my notes', 'post my notes'],
            'text' => 'More, then Upload notes under Study. Pick the file, set the subject, level and title, '
                . 'and send it. An admin reviews new uploads before they reach the list, so a contribution shows '
                . 'up once it is approved rather than immediately.',
            'go' => ['screen' => 'UploadMaterial', 'label' => 'Upload notes'],
        ],
        [
            'id' => 'videos',
            'topic' => 'Video tutorials',
            'keywords' => ['video', 'videos', 'tutorial', 'youtube', 'watch', 'lecture'],
            'text' => 'Video tutorials are under More, or on the Home page rail. Each card carries its own '
                . 'thumbnail and title; open one and it plays inside the app in full screen.',
            'go' => ['screen' => 'VideoTutorials', 'label' => 'Open videos'],
        ],
        [
            'id' => 'battle-how',
            'topic' => 'Battles against another student',
            'keywords' => ['battle', 'challenge', 'fight', 'versus', 'arena', 'hub', '1v1', 'against', 'opponent', 'stake'],
            'text' => 'Tap Battle on the bottom bar. The Arena is where live matches and open invitations sit; '
                . 'the Hub is where you post one for anyone to take or send a direct one to a friend. Both sides '
                . 'answer the same paper and the higher score wins. Results, your wins and losses and your rating '
                . 'all show on the same tab.',
            'go' => ['screen' => 'Battles', 'label' => 'Open Battle'],
        ],
        [
            'id' => 'battle-elo',
            'topic' => 'Battle rating',
            'keywords' => ['elo', 'rating', 'rank up', 'rank down', 'rating dropped', 'points in battle'],
            'text' => 'Battle rating starts at 1000 and moves with each result, so beating someone rated above '
                . 'you gains more than beating someone below. It is separate from your study points: the '
                . 'leaderboard counts study points, the battle board counts rating and wins.',
        ],
        [
            'id' => 'challenge-prize',
            'topic' => 'Challenges with a prize pool',
            'keywords' => ['prize', 'prize pool', 'money', 'reward challenge', 'winner', 'cash', 'entry', 'timed challenge'],
            'text' => 'A challenge with a prize pool is a bigger event: you pay the entry fee, everyone answers '
                . 'the same set, and the top positions share the pool. When it closes, the winners list is '
                . 'published and the prize goes to the claim page.',
            'go' => ['screen' => 'Battles', 'label' => 'Open Battle'],
        ],
        [
            'id' => 'claim-reward',
            'topic' => 'Claiming a prize',
            'keywords' => ['claim', 'claim reward', 'claim my prize', 'claim prize', 'claim my reward', 'prize claim', 'bank details', 'withdraw', 'get my money', 'paid yet', 'when will i get'],
            'text' => 'Open the claim page from the winner card, or More then Rewards. You give your bank name, '
                . 'account number, account name and phone, and an admin sends the money. The claim then reads '
                . 'pending, processing or paid. If it is rejected the reason is shown on the claim so you can fix '
                . 'the details and send it again.',
            'go' => ['screen' => 'RewardClaim', 'label' => 'Claim a prize'],
        ],
        [
            'id' => 'points-how',
            'topic' => 'How points are earned',
            'keywords' => ['point', 'points', 'xp', 'score points', 'earn', 'how do i get points', 'increase points', 'add points'],
            'text' => 'Finishing a quiz gives 10 points, plus up to 10 more for the mark you got, plus 20 for a '
                . 'perfect paper, plus 15 more when it is a challenge. Battles pay the pot to the winner. Nothing '
                . 'else adds points, so the way up the board is to keep finishing papers well.',
            'go' => ['screen' => 'Leaderboard', 'label' => 'Open Leaderboard'],
        ],
        [
            'id' => 'levels',
            'topic' => 'Levels and titles',
            'keywords' => ['level', 'levels', 'what level', 'next level', 'title', 'rank title', 'matriculant', 'scholar', 'professor'],
            'text' => 'There are 30 levels, from 0 points to 50,000: level 2 at 100, level 5 at 1,000, level 10 '
                . 'at 4,500, level 20 at 19,000, level 30 at 50,000. Each level carries a title that depends on '
                . 'whether you signed up as a secondary or a university student, so a university account reads '
                . 'Matriculant, Freshman, Sophomore and up, and a secondary account reads JSS and SS names.',
            'go' => ['screen' => 'Achievements', 'label' => 'Open Achievements'],
        ],
        [
            'id' => 'streak',
            'topic' => 'Study streak',
            'keywords' => ['streak', 'daily', 'consecutive', 'lost my streak', 'keep my streak', 'burned'],
            'text' => 'Your streak counts days in a row on which you finished a quiz. One a day keeps it '
                . 'growing; miss a whole day and it starts again from one, though your best run is kept '
                . 'forever as the longest streak. At 3, 7, 14 and 30 days it awards badges.',
            'go' => ['screen' => 'Quizzes', 'label' => 'Open CBT'],
        ],
        [
            'id' => 'badges',
            'topic' => 'Badges and achievements',
            'keywords' => ['badge', 'badges', 'achievement', 'achievement', 'trophy', 'award', 'medal', 'unlock badge'],
            'text' => 'Badges come from what you do, not from time: your first quiz, streaks of 3, 7, 14 and 30 '
                . 'days, a perfect score, ten, fifty and a hundred quizzes, reaching level 5 and level 10, '
                . 'finishing in the top three of a challenge, winning one, and the early bird for joining '
                . 'early. Achievements under More shows the ones you have and what is still to come.',
            'go' => ['screen' => 'Achievements', 'label' => 'Open Achievements'],
        ],
        [
            'id' => 'leaderboard',
            'topic' => 'The leaderboard',
            'keywords' => ['leaderboard', 'rank', 'standing', 'top student', 'first place', 'board', 'who is top', 'number one'],
            'text' => 'The board is ordered by points, and the Home page shows the top ten. Open the full '
                . 'Leaderboard under More to see the study points board, the battle board and a board for each '
                . 'individual quiz, with your own position on it. A student who hides their level in privacy '
                . 'settings shows on the board without their details.',
            'go' => ['screen' => 'Leaderboard', 'label' => 'Open Leaderboard'],
        ],
        [
            'id' => 'community',
            'topic' => 'The community feed',
            'keywords' => ['community', 'post', 'feed', 'comment', 'reply', 'upvote', 'like', 'discuss', 'group'],
            'text' => 'The Community tab is the feed. Write a post with New post, and people answer it as '
                . 'threaded comments you can reply under. Upvotes push a good answer up. Tap a post to read the '
                . 'whole thread. You can report anyone else s post or comment, but not your own.',
            'go' => ['screen' => 'Community', 'label' => 'Open Community'],
        ],
        [
            'id' => 'chats',
            'topic' => 'Private chat',
            'keywords' => ['chat', 'message', 'dm', 'inbox', 'reply to', 'typing', 'seen', 'online', 'last seen', 'read receipt'],
            'text' => 'The Chats tab is your thread list; open one to talk. You can reply to a particular '
                . 'message, and the other person sees a typing indicator and whether they have read it. Names '
                . 'show Online or last seen from real activity. Find New friends is where you start a thread '
                . 'with someone.',
            'go' => ['screen' => 'Chats', 'label' => 'Open Chats'],
        ],
        [
            'id' => 'profile',
            'topic' => 'Your profile',
            'keywords' => ['profile', 'my picture', 'avatar', 'photo', 'change name', 'bio', 'school', 'edit profile'],
            'text' => 'Your profile is the picture at the top of the app, not on the bottom bar. There you set '
                . 'your name, a profile picture, your type of account and what other people can see. Tapping '
                . 'anyone else s name opens their public profile, where you can message or challenge them.',
            'go' => ['screen' => 'Profile', 'label' => 'Open my profile'],
        ],
        [
            'id' => 'settings',
            'topic' => 'Settings and dark mode',
            'keywords' => ['setting', 'settings', 'dark mode', 'night', 'theme', 'appearance', 'light mode', 'how do i open settings'],
            'text' => 'Settings opens from your profile: tap your picture at the top, then the Settings card. It '
                . 'has three doors. Profile holds your name, email and picture, your school details and the '
                . 'Light or Dark theme. Security holds the password change. Privacy holds what other people can '
                . 'see. One Save at the bottom stores everything you edited.',
            'go' => ['screen' => 'Settings', 'label' => 'Open Settings'],
        ],
        [
            'id' => 'privacy',
            'topic' => 'Who can see what',
            'keywords' => ['private', 'privacy', 'hide', 'visible', 'who can see', 'block', 'public profile'],
            'text' => 'The switches are in Settings, behind the Privacy door, and they apply to the website and '
                . 'this app at the same time. They cover your school details, your XP and rank, your community '
                . 'posts, your badges and certificates, your battle record, what you study most, your quiz '
                . 'history, whether strangers may message you, your Online dot and your level on Find Friends. '
                . 'Anything switched off shows other people a locked card instead of a number.',
            'go' => ['screen' => 'Settings', 'label' => 'Open Settings'],
        ],
        [
            'id' => 'notifications',
            'topic' => 'Notifications',
            'keywords' => ['notification', 'notifications', 'bell', 'alert', 'push', 'did not get', 'news'],
            'text' => 'The bell at the top opens Notifications, and a red count on it is anything unread. '
                . 'Notifications arrive for replies to your work, battle results, rewards, and reminders. On the '
                . 'phone they also push, as long as you are signed in and you allowed this app to send you '
                . 'notifications in the phone s own settings.',
            'go' => ['screen' => 'Notifications', 'label' => 'Open Notifications'],
        ],
        [
            'id' => 'sign-up',
            'topic' => 'Creating an account',
            'keywords' => ['sign up', 'register', 'create account', 'new account', 'join', 'signup'],
            'text' => 'Press Register and give your name, a working email and a password, then choose secondary '
                . 'school or university - that decides which quizzes, materials and level titles you get. A '
                . 'verification email follows; open it and confirm, and sign in with the same details on the '
                . 'app and the website.',
            'go' => ['web' => 'register.php', 'label' => 'Open the register page'],
        ],
        [
            'id' => 'sign-in',
            'topic' => 'Signing in and passwords',
            'keywords' => ['login', 'log in', 'sign in', 'password', 'forgot', 'cannot access', 'can t log', 'wrong password', 'locked out', 'reset'],
            'text' => 'Sign in with your email and password. If it refuses, the message says exactly why: wrong '
                . 'password, unknown email, or an account that is not verified yet. Forgot password on that '
                . 'screen emails a reset link. The same sign-in works on mystudy.com.ng and in this app.',
            'go' => ['screen' => 'Settings', 'label' => 'Open Settings'],
        ],
        [
            'id' => 'password-change',
            'topic' => 'Changing your password',
            'keywords' => ['password', 'change password', 'change my password', 'new password', 'current password', 'reset password', 'update password', 'forgot password'],
            'text' => 'Signed in: tap your picture at the top to open your profile, then the Settings card, then '
                . 'the Security door. It asks for your current password, the new one and a confirmation - fill '
                . 'all three together and press Save changes, and the new password works on the app and on '
                . 'mystudy.com.ng. Leave them blank and the password stays as it is. Locked out instead: Forgot '
                . 'password on the sign-in page emails a reset link.',
            'go' => ['screen' => 'Settings', 'label' => 'Open Settings'],
        ],
        [
            'id' => 'session-ended',
            'topic' => 'Session ended / sign in again',
            'keywords' => ['session ended', 'sign in again', 'logged out', 'keep signing me out', 'token'],
            'text' => 'Your sign-in lasts ninety days, after which the app asks you once more. It also appears '
                . 'if the password was changed or an admin ended the session. Signing in again is all it takes, '
                . 'and nothing you saved is lost.',
        ],
        [
            'id' => 'payments',
            'topic' => 'Payments and failed transactions',
            'keywords' => ['paystack', 'payment', 'paid but', 'failed', 'transaction', 'receipt', 'debit', 'charged', 'refund', 'card', 'did not come', 'did not receive', 'not received', 'has not appeared', 'did not unlock', 'money deducted', 'debited but'],
            'text' => 'Payments go through Paystack by card, bank transfer or USSD. If money left your account '
                . 'and the item did not unlock, do not pay again straight away: send support the reference from '
                . 'your bank or email and it is matched and released. Every payment is recorded against your '
                . 'account, so nothing is lost.',
            'go' => ['screen' => 'Support', 'label' => 'Contact support'],
        ],
        [
            'id' => 'tools',
            'topic' => 'Study tools, CGPA and timetable',
            'keywords' => ['cgpa', 'gpa', 'calculator', 'timetable', 'schedule', 'tool', 'tools', 'convert', 'topic'],
            'text' => 'More, Tools holds the extras: a CGPA calculator that keeps your entries, a timetable for '
                . 'your week, general study tools, and topics with notes. All of it is built into the app, so '
                . 'nothing leaves it and your sign-in carries over.',
            'go' => ['screen' => 'StudyTools', 'label' => 'CGPA calculator'],
        ],
        [
            'id' => 'blogs',
            'topic' => 'Blog and news',
            'keywords' => ['blog', 'news', 'article', 'articles', 'read about'],
            'text' => 'Blog and news is under More, or the rail on the Home page. The newest articles come first, '
                . 'and a post with a picture uses that picture as its card. Tap View All Blogs for the full list.',
            'go' => ['screen' => 'Blogs', 'label' => 'Open Blogs'],
        ],
        [
            'id' => 'app-vs-website',
            'topic' => 'The app and the website',
            'keywords' => ['website', 'site', 'browser', 'mystudy', 'difference between the app and the website', 'same account', 'computer', 'phone'],
            'text' => 'They are the same account on purpose. Points, results, materials and messages are one set '
                . 'of records, so work done on mystudy.com.ng appears in the app and the other way round. The '
                . 'website holds the pages the app has not rebuilt, which open inside the app when you tap them.',
        ],
        [
            'id' => 'data-lost',
            'topic' => 'Work that has not synced',
            'keywords' => ['lost', 'missing', 'not saved', 'gone', 'where is my result', 'does not show', 'doesn t show', 'not on website', 'delete the app', 'uninstall', 'new phone', 'another phone', 'unsent', 'waiting to send'],
            'text' => 'Anything done offline lives only on this phone until it syncs, so it will not appear on '
                . 'the website or another device yet. The bar above the quiz list counts the results still '
                . 'waiting; tap it and Send now to push them. Once sent, the mark joins your record and the '
                . 'website shows it. Uninstalling the app before that deletes the unsent copy.',
            'go' => ['screen' => 'Quizzes', 'label' => 'Open CBT'],
        ],
        [
            'id' => 'support',
            'topic' => 'Contacting support',
            'keywords' => ['support', 'contact', 'help me', 'email', 'complaint', 'report a problem', 'problem', 'someone to talk'],
            'text' => 'More, Help, Contact support reaches the team, and support@mystudy.com.ng works too. Say '
                . 'what you expected, what happened and which part of the app, and a screenshot if you have one - '
                . 'that is what turns a vague report into a fix.',
            'go' => ['screen' => 'Support', 'label' => 'Contact support'],
        ],
        [
            'id' => 'deactivate',
            'topic' => 'Closing or deleting the account',
            'keywords' => ['delete account', 'delete my account', 'delete the account', 'deactivate', 'close account', 'close my account', 'cancel my account', 'erase my account', 'remove my account', 'unsubscribe', 'stop using the app forever'],
            'text' => 'Deactivating is on the website under Settings, and it hides your profile while keeping '
                . 'your records, so you can come back. Say so in a support message if you want the account and '
                . 'its data removed outright.',
            'go' => ['screen' => 'Settings', 'label' => 'Open Settings'],
        ],
        [
            'id' => 'offline-app',
            'topic' => 'The app with no connection',
            'keywords' => ['app not loading', 'not loading', 'app is not loading', 'blank', 'stuck', 'spinner', 'loading forever', 'not working', 'not responding', 'error', 'something went wrong'],
            'text' => 'With no signal, the app still shows your saved quizzes and the profile it last read, so '
                . 'you are not locked out. If a page keeps failing, check the connection, then sign out and in '
                . 'again - that refreshes the session. If it is still wrong, tell support what you tapped first.',
            'go' => ['screen' => 'Help', 'label' => 'Read the FAQ'],
        ],
    ],
];
