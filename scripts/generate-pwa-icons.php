<?php

$outDir = __DIR__.'/../public/icons';
if (! is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}

function drawIcon(int $size, string $path, float $scale = 1.0): void
{
    $img = imagecreatetruecolor($size, $size);
    imagesavealpha($img, true);
    imageantialias($img, true);

    $bg = imagecolorallocate($img, 0x0f, 0x17, 0x2a);
    imagefilledrectangle($img, 0, 0, $size, $size, $bg);

    $white = imagecolorallocate($img, 0xff, 0xff, 0xff);
    $accent = imagecolorallocate($img, 0x38, 0xbd, 0xf8);

    $cx = $size / 2.0;
    $cy = $size / 2.0;
    $w = $size * $scale;
    $h = $size * $scale;
    $left = $cx - $w / 2.0;
    $top = $cy - $h / 2.0;

    // Roof
    $roof = [
        (int) round($left + 0.16 * $w), (int) round($top + 0.50 * $h),
        (int) round($left + 0.50 * $w), (int) round($top + 0.24 * $h),
        (int) round($left + 0.84 * $w), (int) round($top + 0.50 * $h),
    ];
    imagefilledpolygon($img, $roof, $white);

    // Body
    imagefilledrectangle(
        $img,
        (int) round($left + 0.26 * $w), (int) round($top + 0.50 * $h),
        (int) round($left + 0.74 * $w), (int) round($top + 0.78 * $h),
        $white
    );

    // Door
    imagefilledrectangle(
        $img,
        (int) round($left + 0.44 * $w), (int) round($top + 0.60 * $h),
        (int) round($left + 0.56 * $w), (int) round($top + 0.78 * $h),
        $bg
    );

    // Accent underline (heartbeat / family)
    $barH = max(2, (int) round($size * 0.035));
    imagefilledrectangle(
        $img,
        (int) round($left + 0.30 * $w), (int) round($top + 0.80 * $h),
        (int) round($left + 0.70 * $w), (int) round($top + 0.80 * $h) + $barH,
        $accent
    );

    imagepng($img, $path);
    imagedestroy($img);
}

drawIcon(192, $outDir.'/icon-192.png');
drawIcon(512, $outDir.'/icon-512.png');
drawIcon(512, $outDir.'/icon-maskable-512.png', 0.72);
drawIcon(180, $outDir.'/apple-touch-icon.png');

echo "Icons written to {$outDir}\n";
