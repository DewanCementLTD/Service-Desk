<?php

/**
 * Raster application icons, generated from the same geometry as public/favicon.svg.
 *
 * Browsers and Android/iOS home screens need real PNG/ICO files; the SVG alone
 * covers only modern desktop tabs. Run this once, or after a brand change:
 *
 *     php scripts/generate-icons.php
 *
 * Output: public/icon-192.png, icon-512.png, apple-touch-icon.png, favicon.ico
 */

const BRAND_FROM = [0x63, 0x66, 0xf1];   // brand-500
const BRAND_TO   = [0x43, 0x38, 0xca];   // brand-700

/** Rounded square with a diagonal brand gradient and a white check mark. */
function renderIcon(int $size): GdImage
{
    // Drawn at 4x and downsampled, which is cheaper than hand-rolling antialiasing.
    $scale  = 4;
    $canvas = $size * $scale;

    $image = imagecreatetruecolor($canvas, $canvas);
    imagealphablending($image, true);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));

    $radius = (int) round($canvas * 14 / 64);

    // Gradient painted row by row, masked to the rounded square.
    for ($y = 0; $y < $canvas; $y++) {
        for ($x = 0; $x < $canvas; $x++) {
            if (! insideRoundedSquare($x, $y, $canvas, $radius)) {
                continue;
            }

            $t = ($x + $y) / (2 * $canvas);
            $colour = imagecolorallocate(
                $image,
                (int) round(BRAND_FROM[0] + ($t * (BRAND_TO[0] - BRAND_FROM[0]))),
                (int) round(BRAND_FROM[1] + ($t * (BRAND_TO[1] - BRAND_FROM[1]))),
                (int) round(BRAND_FROM[2] + ($t * (BRAND_TO[2] - BRAND_FROM[2])))
            );
            imagesetpixel($image, $x, $y, $colour);
        }
    }

    // The check: same three points as the SVG path, scaled to the canvas. Each
    // segment is stamped as overlapping discs, which gives the round caps and
    // round join the SVG has — imageline's thick stroke is square-ended.
    $unit   = $canvas / 64;
    $white  = imagecolorallocate($image, 255, 255, 255);
    $stroke = (int) round(7 * $unit);
    $points = [[18, 33.5], [27.5, 43], [46, 22]];

    for ($i = 0; $i < count($points) - 1; $i++) {
        [$x1, $y1] = [$points[$i][0] * $unit, $points[$i][1] * $unit];
        [$x2, $y2] = [$points[$i + 1][0] * $unit, $points[$i + 1][1] * $unit];

        $steps = (int) ceil(hypot($x2 - $x1, $y2 - $y1));

        for ($step = 0; $step <= $steps; $step++) {
            $t = $steps === 0 ? 0 : $step / $steps;
            imagefilledellipse(
                $image,
                (int) round($x1 + ($x2 - $x1) * $t),
                (int) round($y1 + ($y2 - $y1) * $t),
                $stroke,
                $stroke,
                $white
            );
        }
    }

    $out = imagecreatetruecolor($size, $size);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
    imagealphablending($out, true);
    imagecopyresampled($out, $image, 0, 0, 0, 0, $size, $size, $canvas, $canvas);
    imagedestroy($image);

    return $out;
}

function insideRoundedSquare(int $x, int $y, int $size, int $radius): bool
{
    $corners = [
        [$radius, $radius],
        [$size - $radius, $radius],
        [$radius, $size - $radius],
        [$size - $radius, $size - $radius],
    ];

    foreach ($corners as $i => [$cx, $cy]) {
        $outsideX = $i % 2 === 0 ? $x < $cx : $x > $cx;
        $outsideY = $i < 2 ? $y < $cy : $y > $cy;

        if ($outsideX && $outsideY) {
            return (($x - $cx) ** 2 + ($y - $cy) ** 2) <= $radius ** 2;
        }
    }

    return true;
}

/** Minimal multi-size .ico writer — the format is a header plus embedded PNGs. */
function writeIco(string $path, array $sizes): void
{
    $images = [];

    foreach ($sizes as $size) {
        $image = renderIcon($size);
        ob_start();
        imagepng($image);
        $images[$size] = ob_get_clean();
        imagedestroy($image);
    }

    $count  = count($images);
    $offset = 6 + (16 * $count);
    $header = pack('vvv', 0, 1, $count);
    $body   = '';

    foreach ($images as $size => $png) {
        $header .= pack(
            'CCCCvvVV',
            $size >= 256 ? 0 : $size,
            $size >= 256 ? 0 : $size,
            0, 0, 1, 32,
            strlen($png),
            $offset
        );
        $offset += strlen($png);
        $body   .= $png;
    }

    file_put_contents($path, $header . $body);
}

$public = dirname(__DIR__) . '/public';

foreach ([192 => 'icon-192.png', 512 => 'icon-512.png', 180 => 'apple-touch-icon.png'] as $size => $name) {
    $image = renderIcon($size);
    imagepng($image, "{$public}/{$name}");
    imagedestroy($image);
    echo "wrote {$name}\n";
}

writeIco("{$public}/favicon.ico", [16, 32, 48]);
echo "wrote favicon.ico\n";
