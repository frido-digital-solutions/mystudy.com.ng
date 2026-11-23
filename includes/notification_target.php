<?php
/**
 * What a notice is about, read out of the link it carries.
 *
 * The website's links are written for a browser, so they are full of things
 * the phone cannot use. The phone needs the bare numbers: which discussion,
 * which comment, which attempt. Reading them here - once, on the server -
 * means the app does not have to guess at a web address, and a notice about a
 * comment can point at that exact comment.
 *
 * It lives in its own file because two different places need it: the push
 * payload and the notice list.
 *
 * @return array<string,int>
 */
function notification_target_fields(?string $link): array
{
    $link = (string)$link;
    if ($link === '') return [];
    $out = [];

    if (preg_match('/discussion_view\.php\?id=(\d+)/', $link, $m)) {
        $out['discussion_id'] = (int)$m[1];
    }
    if (preg_match('/#comment-(\d+)/', $link, $m)) {
        $out['comment_id'] = (int)$m[1];
    }
    if (preg_match('/quiz_result\.php\?attempt=(\d+)/', $link, $m)) {
        $out['attempt_id'] = (int)$m[1];
    }
    if (preg_match('/quiz_special\.php\?id=(\d+)/', $link, $m)) {
        $out['challenge_id'] = (int)$m[1];
    }
    if (preg_match('/claim_reward\.php\?quiz_id=(\d+)/', $link, $m)) {
        $out['quiz_id'] = (int)$m[1];
    }
    if (preg_match('/quiz_battle\.php\?id=(\d+)/', $link, $m)) {
        $out['battle_id'] = (int)$m[1];
    }
    if (preg_match('/quiz_battle_result\.php\?id=(\d+)/', $link, $m)) {
        $out['battle_id'] = (int)$m[1];
    }
    if (preg_match('/battle_arena\.php(?:#battle-(\d+))?/', $link, $m)) {
        $out['battle_id'] = (int)($m[1] ?? 0);
    }
    if (preg_match('/#view-reply-(\d+)/', $link, $m)) {
        $out['ticket_id'] = (int)$m[1];
    }
    if (preg_match('/material(?:s)?(?:_view)?\.php\?id=(\d+)/', $link, $m)) {
        $out['material_id'] = (int)$m[1];
    }

    return array_filter($out, static fn($v) => (int)$v > 0);
}
