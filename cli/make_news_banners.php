<?php
/**
 * Draws the picture for every exam-season post.
 *
 * There is no stock photograph here on purpose. Each post gets its own
 * banner, drawn from the official examination logo on a colour that belongs to
 * that examination, with the headline set in Poppins - the same typeface the
 * site already uses. Nothing is copied from a news site, and every image
 * belongs to us.
 *
 * Run it from the project root:
 *     php -d extension=gd cli/make_news_banners.php
 */

$root = dirname(__DIR__);
if (!is_dir($root . '/assets/img/news')) {
    mkdir($root . '/assets/img/news', 0775, true);
}

$posts = require $root . '/content/exam_news_posts.php';

$fontBold    = $root . '/assets/fonts/Poppins-Bold.ttf';
$fontRegular = $root . '/assets/fonts/Poppins-Regular.ttf';
$fontSemi    = $root . '/assets/fonts/Poppins-SemiBold.ttf';

foreach ([$fontBold, $fontRegular, $fontSemi] as $font) {
    if (!is_file($font)) {
        fwrite(STDERR, "Missing font: {$font}\n");
        exit(1);
    }
}

/**
 * The colours each examination is drawn with, and the logo that goes on it.
 */
$styles = [
    'waec' => [
        'from' => [13, 33, 92],    // deep navy, the colour of the WAEC crest
        'to'   => [4, 12, 40],
        'mark' => $root . '/assets/img/exams/waec.png',
        'accent' => [253, 224, 71],
    ],
    'neco' => [
        'from' => [4, 120, 87],    // the green of the NECO crest
        'to'   => [4, 47, 46],
        'mark' => $root . '/assets/img/exams/neco.png',
        'accent' => [190, 242, 100],
        // NECO publishes its crest at 43x43, so it is drawn smaller than the
        // others rather than blown up and made to look soft.
        'size' => 105,
    ],
    'jamb' => [
        'from' => [4, 95, 70],     // the green of the JAMB badge
        'to'   => [2, 44, 34],
        'mark' => $root . '/assets/img/exams/jamb.png',
        'accent' => [167, 243, 208],
    ],
    'university' => [
        'from' => [55, 48, 163],   // indigo, the NUC/university colour
        'to'   => [30, 27, 75],
        'mark' => $root . '/assets/uploads/site_logo_1774176868.png',
        'accent' => [199, 210, 254],
    ],
];

/**
 * Breaks a sentence into lines that fit a given width.
 *
 * @return array<int, string>
 */
function wrap_text(string $text, string $font, float $size, int $maxWidth): array {
    $words = preg_split('/\s+/', trim($text));
    $lines = [];
    $line = '';
    foreach ($words as $word) {
        $candidate = $line === '' ? $word : $line . ' ' . $word;
        $box = imagettfbbox($size, 0, $font, $candidate);
        $width = $box[2] - $box[0];
        if ($width > $maxWidth && $line !== '') {
            $lines[] = $line;
            $line = $word;
        } else {
            $line = $candidate;
        }
    }
    if ($line !== '') {
        $lines[] = $line;
    }
    return $lines;
}

/**
 * Draws text with a soft shadow so it stays readable on any background.
 */
function draw_text($image, string $text, int $x, int $y, string $font, float $size, array $rgb, float $alpha = 100.0): void {
    $shadow = imagecolorallocatealpha($image, 0, 0, 0, 78);
    imagettftext($image, $size, 0, $x + 2, $y + 3, $shadow, $font, $text);
    $colour = imagecolorallocatealpha($image, $rgb[0], $rgb[1], $rgb[2], (int)round((100 - $alpha) * 1.27));
    imagettftext($image, $size, 0, $x, $y, $colour, $font, $text);
}

/**
 * A vertical gradient, painted one line at a time.
 */
function paint_gradient($image, int $width, int $height, array $from, array $to): void {
    for ($y = 0; $y < $height; $y++) {
        $t = $y / max(1, $height - 1);
        $r = (int)round($from[0] + ($to[0] - $from[0]) * $t);
        $g = (int)round($from[1] + ($to[1] - $from[1]) * $t);
        $b = (int)round($from[2] + ($to[2] - $from[2]) * $t);
        $colour = imagecolorallocate($image, $r, $g, $b);
        imageline($image, 0, $y, $width, $y, $colour);
    }
}

/**
 * Cuts the empty margin off a transparent picture, so the mark fills its box.
 */
function trim_transparent($image) {
    $w = imagesx($image);
    $h = imagesy($image);
    $minX = $w; $minY = $h; $maxX = -1; $maxY = -1;
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            $c = imagecolorat($image, $x, $y);
            $alpha = ($c >> 24) & 0x7F;
            if ($alpha < 120) {
                if ($x < $minX) { $minX = $x; }
                if ($x > $maxX) { $maxX = $x; }
                if ($y < $minY) { $minY = $y; }
                if ($y > $maxY) { $maxY = $y; }
            }
        }
    }
    if ($maxX < 0) {
        return $image;
    }
    $cw = $maxX - $minX + 1;
    $ch = $maxY - $minY + 1;
    $out = imagecreatetruecolor($cw, $ch);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagecopy($out, $image, 0, 0, $minX, $minY, $cw, $ch);
    imagedestroy($image);
    return $out;
}

/**
 * A white rounded card for the logo to sit on, so every banner has the same
 * shape and no mark looks pasted on.
 */
function paint_card($image, int $cx, int $cy, int $size, int $radius): void {
    $white = imagecolorallocatealpha($image, 255, 255, 255, 2);
    $x1 = $cx - (int)($size / 2);
    $y1 = $cy - (int)($size / 2);
    $x2 = $x1 + $size;
    $y2 = $y1 + $size;
    imagefilledrectangle($image, $x1 + $radius, $y1, $x2 - $radius, $y2, $white);
    imagefilledrectangle($image, $x1, $y1 + $radius, $x2, $y2 - $radius, $white);
    imagefilledellipse($image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $white);
    imagefilledellipse($image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $white);
    imagefilledellipse($image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $white);
    imagefilledellipse($image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $white);
}

$made = 0;

foreach ($posts as $post) {
    $banner = $post['banner'];
    $style  = $styles[$banner['exam']] ?? $styles['university'];

    $width  = 1200;
    $height = 630;
    $image  = imagecreatetruecolor($width, $height);
    imagealphablending($image, true);
    imagesavealpha($image, true);

    paint_gradient($image, $width, $height, $style['from'], $style['to']);

    // A soft light behind the logo, so the mark is not lost in the colour.
    $glow = imagecolorallocatealpha($image, 255, 255, 255, 112);
    imagefilledellipse($image, 965, 300, 420, 420, $glow);

    // The examination logo on a white card, so every banner has the same shape.
    if (is_file($style['mark'])) {
        paint_card($image, 965, 300, 250, 30);
        $mark = trim_transparent(imagecreatefrompng($style['mark']));
        $mw = imagesx($mark);
        $mh = imagesy($mark);
        $box = (int)($style['size'] ?? 165);
        if ($mw >= $mh) {
            $nw = $box;
            $nh = (int)round($mh * $box / $mw);
        } else {
            $nh = $box;
            $nw = (int)round($mw * $box / $mh);
        }
        $scaled = imagecreatetruecolor($nw, $nh);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        imagecopyresampled($scaled, $mark, 0, 0, 0, 0, $nw, $nh, $mw, $mh);
        imagecopy($image, $scaled, 965 - (int)($nw / 2), 300 - (int)($nh / 2), 0, 0, $nw, $nh);
        imagedestroy($mark);
        imagedestroy($scaled);
    }

    // The kicker, the headline, and the wordmark.
    draw_text($image, strtoupper($banner['kicker']), 70, 112, $fontSemi, 25, $style['accent']);

    $lines = wrap_text($post['title'], $fontBold, 43, 690);
    $y = 196;
    foreach (array_slice($lines, 0, 4) as $line) {
        draw_text($image, $line, 70, $y, $fontBold, 43, [255, 255, 255]);
        $y += 59;
    }

    $rule = imagecolorallocatealpha($image, 255, 255, 255, 60);
    imagefilledrectangle($image, 70, 540, 300, 544, $rule);

    draw_text($image, 'MyStudy Base', 70, 592, $fontSemi, 26, [255, 255, 255], 82);

    $tag = 'Exam season update';
    $box = imagettfbbox(24, 0, $fontRegular, $tag);
    $tagWidth = $box[2] - $box[0];
    draw_text($image, $tag, 1130 - $tagWidth, 592, $fontRegular, 24, [255, 255, 255], 55);

    $path = $root . '/assets/img/news/' . $banner['file'];
    imagepng($image, $path, 8);
    imagedestroy($image);

    printf("made %s\n", $banner['file']);
    $made++;
}

printf("%d banners written to assets/img/news\n", $made);
