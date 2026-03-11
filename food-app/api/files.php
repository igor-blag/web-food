<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';
require_auth();

header('Content-Type: application/json; charset=utf-8');

$data   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $data['action'] ?? ($_GET['action'] ?? '');

try {
    if ($action === 'list') {
        $year  = isset($data['year'])  ? (int)$data['year']  : (int)date('Y');
        $month = isset($data['month']) ? (int)$data['month'] : (int)date('n');

        $prefix = sprintf('%04d-%02d-', $year, $month);
        $allFiles = glob(FILES_DIR . $prefix . '*.xlsx') ?: [];

        // Построить карту суффиксов отделений
        $depts = get_enabled_departments();
        $suffixMap = []; // suffix => dept code
        foreach ($depts as $d) {
            if ($d['publish_xlsx']) {
                $suffixMap[$d['file_suffix']] = $d['code'];
            }
        }

        $result = [];

        // Вспомогательная функция для добавления файла в результат
        $addFile = function(string $filepath, string $deptCode) use (&$result) {
            $basename = basename($filepath);
            $mtime = filemtime($filepath);
            $result[$deptCode][] = [
                'name'    => $basename,
                'size_kb' => round(filesize($filepath) / 1024, 1),
                'mtime'   => date('d.m.Y H:i', $mtime),
                'mtime_ts' => $mtime,
                'url'     => FILES_URL . $basename,
            ];
        };

        // Ежедневные файлы меню
        foreach ($allFiles as $filepath) {
            $nameNoExt = basename($filepath, '.xlsx');

            $deptCode = null;
            foreach ($suffixMap as $sfx => $code) {
                if ($sfx !== '' && str_ends_with($nameNoExt, $sfx)) {
                    $deptCode = $code;
                    break;
                }
            }
            if ($deptCode === null && isset($suffixMap[''])) {
                $deptCode = $suffixMap[''];
            }
            if ($deptCode === null) {
                $deptCode = '_other';
            }

            $addFile($filepath, $deptCode);
        }

        // kp и tm файлы — только для sm (начальная школа)
        if (isset($suffixMap['-sm']) || isset($suffixMap['sm'])) {
            $smCode = isset($suffixMap['-sm']) ? $suffixMap['-sm'] : $suffixMap['sm'];
            // kpYYYY.xlsx
            $kpFile = FILES_DIR . "kp{$year}.xlsx";
            if (file_exists($kpFile)) {
                $addFile($kpFile, $smCode);
            }
            // tmYYYY-sm.xlsx
            $tmFile = FILES_DIR . "tm{$year}-sm.xlsx";
            if (file_exists($tmFile)) {
                $addFile($tmFile, $smCode);
            }
        }

        // Сортировать файлы по имени в каждом отделении
        foreach ($result as &$files) {
            usort($files, fn($a, $b) => strcmp($a['name'], $b['name']));
        }
        unset($files);

        echo json_encode(['ok' => true, 'files' => $result]);

    } elseif ($action === 'delete') {
        $filename = $data['filename'] ?? '';

        // Валидация
        if (!preg_match('/^(\d{4}-\d{2}-\d{2}[a-zA-Z0-9_-]*|kp\d{4}|tm\d{4}-[a-z]+)\.xlsx$/', $filename)) {
            echo json_encode(['ok' => false, 'error' => 'Недопустимое имя файла']);
            exit;
        }
        if (str_contains($filename, '..') || str_contains($filename, '/')) {
            echo json_encode(['ok' => false, 'error' => 'Недопустимое имя файла']);
            exit;
        }

        $filepath = FILES_DIR . $filename;
        if (!file_exists($filepath)) {
            echo json_encode(['ok' => false, 'error' => 'Файл не найден']);
            exit;
        }

        unlink($filepath);
        echo json_encode(['ok' => true]);

    } else {
        echo json_encode(['ok' => false, 'error' => 'Неизвестное действие']);
    }
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
