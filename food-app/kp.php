<?php
require_once __DIR__ . '/src/auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/kp_generator.php';
require_auth();

require_once __DIR__ . '/src/db.php';
$enabledDepts = get_enabled_departments();
$validTypes   = array_column($enabledDepts, 'code');
$year = (int)($_GET['year'] ?? date('Y'));
$type = in_array($_GET['type'] ?? '', $validTypes) ? $_GET['type'] : ($validTypes[0] ?? 'sm');

$year = max(2020, min(2035, $year));

$path = generate_kp_excel($year, $type);

$filename = basename($path);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: no-cache');
readfile($path);
exit;
