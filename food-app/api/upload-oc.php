<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../config.php';
require_auth();

header('Content-Type: application/json');

$ALLOWED_MIME = [
    'application/pdf'  => 'pdf',
    'image/jpeg'       => 'jpg',
    'image/png'        => 'jpg',   // PNG → конвертируем в JPEG
    'image/webp'       => 'jpg',
];
const MAX_IMAGE_PX = 2000;
const JPEG_QUALITY  = 82;
const MAX_FILE_MB   = 20;

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $code = $_FILES['file']['error'] ?? -1;
    echo json_encode(['ok' => false, 'error' => 'Ошибка загрузки (код ' . $code . ')']);
    exit;
}

$file = $_FILES['file'];

if ($file['size'] > MAX_FILE_MB * 1024 * 1024) {
    echo json_encode(['ok' => false, 'error' => 'Файл слишком большой (максимум ' . MAX_FILE_MB . ' МБ)']);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($file['tmp_name']);

if (!isset($ALLOWED_MIME[$mime])) {
    echo json_encode(['ok' => false, 'error' => 'Недопустимый тип файла. Разрешены: PDF, JPEG, PNG, WebP']);
    exit;
}

$ocDir = rtrim(FILES_DIR, '/') . '/oc/';
if (!is_dir($ocDir)) {
    mkdir($ocDir, 0755, true);
    file_put_contents($ocDir . '.htaccess', "Options -Indexes\n");
}

$ext      = $ALLOWED_MIME[$mime];
$basename = uniqid('oc_', true) . '.' . $ext;
$dest     = $ocDir . $basename;

if ($mime === 'application/pdf') {
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        echo json_encode(['ok' => false, 'error' => 'Не удалось сохранить файл']);
        exit;
    }
} else {
    if (!save_optimized_image($file['tmp_name'], $dest, $mime)) {
        echo json_encode(['ok' => false, 'error' => 'Не удалось обработать изображение']);
        exit;
    }
}

$url = rtrim(FILES_URL, '/') . '/oc/' . $basename;
echo json_encode(['ok' => true, 'url' => $url, 'name' => $file['name']]);

// ────────────────────────────────────────────────────────────────────────────

function save_optimized_image(string $src, string $dest, string $mime): bool
{
    if (!function_exists('imagecreatefromjpeg')) {
        return (bool)copy($src, $dest);
    }

    $img = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($src),
        'image/png'  => @imagecreatefrompng($src),
        'image/webp' => @imagecreatefromwebp($src),
        default      => false,
    };
    if (!$img) return false;

    // Белый фон для PNG с прозрачностью
    if ($mime === 'image/png') {
        $bg = imagecreatetruecolor(imagesx($img), imagesy($img));
        imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
        imagecopy($bg, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
        imagedestroy($img);
        $img = $bg;
    }

    [$w, $h] = [imagesx($img), imagesy($img)];

    if ($w > MAX_IMAGE_PX || $h > MAX_IMAGE_PX) {
        if ($w >= $h) {
            $nw = MAX_IMAGE_PX;
            $nh = (int)round($h * MAX_IMAGE_PX / $w);
        } else {
            $nh = MAX_IMAGE_PX;
            $nw = (int)round($w * MAX_IMAGE_PX / $h);
        }
        $resized = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($resized, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);
        $img = $resized;
    }

    $ok = imagejpeg($img, $dest, JPEG_QUALITY);
    imagedestroy($img);
    return $ok;
}
