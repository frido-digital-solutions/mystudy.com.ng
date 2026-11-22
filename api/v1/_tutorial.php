<?php
/**
 * One video shape for every surface that shows one: the home rail, the quiz
 * result's "watch this next", and the tutorials list itself.
 *
 * YouTube is its own poster, because an admin rarely uploads a thumbnail and a
 * card with no picture is the card that looks broken. hqdefault exists for
 * every real video id, so the fallback can never 404.
 */

if (!function_exists('api_tutorial_payload')) {
    function api_tutorial_payload(array $t, array $courses = []): array
    {
        $yt = trim((string)($t['youtube_id'] ?? ''));
        $stored = trim((string)($t['thumbnail_url'] ?? ''));
        $thumb = $stored !== ''
            ? api_url($stored)
            : ($yt !== '' ? 'https://img.youtube.com/vi/' . rawurlencode($yt) . '/hqdefault.jpg' : null);

        return [
            'id' => (int)$t['id'],
            'title' => (string)($t['title'] ?? ''),
            'description' => mb_strimwidth(strip_tags((string)($t['description'] ?? '')), 0, 160, '…'),
            'youtube_id' => $yt !== '' ? $yt : null,
            'url' => $yt !== ''
                ? 'https://www.youtube.com/watch?v=' . rawurlencode($yt)
                : api_url((string)($t['youtube_url'] ?? '')),
            'thumbnail' => $thumb,
            'level' => $t['level'] ?? null,
            'courses' => array_map(static function ($c) {
                return ['id' => (int)($c['id'] ?? 0), 'name' => (string)($c['name'] ?? ''), 'slug' => (string)($c['slug'] ?? '')];
            }, $courses),
            'created_at' => $t['created_at'] ?? null,
        ];
    }
}
