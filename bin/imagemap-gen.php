<?php

ini_set('date.timezone', 'Asia/Tokyo');

if (PHP_SAPI !== 'cli') {
    echo sprintf('Warning: %s should be invoked via the CLI version of PHP, not the %s SAPI'.PHP_EOL, $argv[0], PHP_SAPI);
    exit(1);
}

require_once '../vendor/autoload.php';

use CHH\Optparse;

$destination = sys_get_temp_dir() . DIRECTORY_SEPARATOR . basename($argv[0]);

$parser = new Optparse\Parser();

function usage() {
    global $parser;
    fwrite(STDERR, "{$parser->usage()}\n");
    exit(1);
}

$parser->setExamples([
    sprintf("%s ./foo.jpeg", $argv[0]),
]);
$parser->addFlag('help', [ 'alias' => '-h' ], 'usage');
$parser->addArgument('file', [ 'required' => true ]);

try {
    $parser->parse();
} catch (\Exception $e) {
    usage();
}

$file_path = $parser['file'];

$path = pathinfo($file_path);

$destination = $destination . DIRECTORY_SEPARATOR . $path['basename'];


if (!file_exists($destination) && !mkdir($destination, 0700, true)) {
    die(sprintf("[error]: %s: %s\n", error_get_last()['message'], $destination));
}

foreach ([ 1040, 700, 460, 300, 240 ] as $size) {
    $resized_path = resize_image($file_path, $size, $path['extension'], false, $destination);

    echo '[generate] ', $resized_path, PHP_EOL;
}

function resize_image($source, $toWidth, $ext = 'jpg', $aspect_ratio = false, $destination_path = '')
{
    if (!function_exists('ImageCreateFromJPEG'))
        throw new \Exception('Require install GD.');

    list($source_width, $source_height) = getimagesize($source);

    // 元画像の比率を計算し、高さを設定
    $proportion = $source_width / $source_height;
    $toHeight = $toWidth / $proportion;

    if ($proportion < 1) {
        $toHeight = $toWidth;
    }
    if ($aspect_ratio) {
        $toHeight = ($toWidth / $source_width) * $source_height;
    }

    $original_image = null;
    $ext = strtolower($ext);
    if ($ext == 'jpg' || $ext == 'jpeg') {
        $original_image = ImageCreateFromJPEG($source);
    } elseif ($ext == 'png') {
        $original_image = ImageCreateFromPNG($source);
    } else {
        throw new \Exception('Unsupported file extension.');
    }
    if (!$original_image)
        throw new \Exception(sprintf('Failed ImageCreateFromXXX: %s', $source));

    $new_image = ImageCreateTrueColor($toWidth, $toHeight);

    ImageCopyResampled(
        $new_image, $original_image, 0, 0, 0, 0,
        $toWidth, $toHeight, $source_width, $source_height
    );

    $resized_path = sprintf('%s/%s', $destination_path, $toWidth);
    if ($ext == 'jpg' || $ext == 'jpeg') {
        ImageJpeg($new_image, $resized_path, 100);
    } elseif ($ext == 'png') {
        ImagePng($new_image, $resized_path);
    } else {

    }

    imagedestroy($original_image);
    imagedestroy($new_image);

    return $resized_path;
}

