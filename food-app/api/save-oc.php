<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/oc_generator.php';
require_auth();

header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    echo json_encode(['ok' => false, 'error' => 'Неверный запрос']);
    exit;
}

$allowed_waste = ['20', '30', '40', '50', 'none'];

$d = [
    'school_name'      => trim($body['school_name']      ?? ''),
    'report_date'      => trim($body['report_date']       ?? ''),
    's1_url'           => trim($body['s1_url']            ?? ''),
    's2_hotline'       => trim($body['s2_hotline']        ?? ''),
    's2_chat_url'      => trim($body['s2_chat_url']       ?? ''),
    's2_forum_url'     => trim($body['s2_forum_url']      ?? ''),
    's3_diet1_type'    => trim($body['s3_diet1_type']     ?? ''),
    's3_diet1_url'     => trim($body['s3_diet1_url']      ?? ''),
    's3_diet2_type'    => trim($body['s3_diet2_type']     ?? ''),
    's3_diet2_url'     => trim($body['s3_diet2_url']      ?? ''),
    's3_diet3_type'    => trim($body['s3_diet3_type']     ?? ''),
    's3_diet3_url'     => trim($body['s3_diet3_url']      ?? ''),
    's3_diet4_type'    => trim($body['s3_diet4_type']     ?? ''),
    's3_diet4_url'     => trim($body['s3_diet4_url']      ?? ''),
    's4_survey_url'    => trim($body['s4_survey_url']     ?? ''),
    's4_results_url'   => trim($body['s4_results_url']    ?? ''),
    's5_page_url'      => trim($body['s5_page_url']       ?? ''),
    's5_materials_url' => trim($body['s5_materials_url']  ?? ''),
    's6_acts_url'      => trim($body['s6_acts_url']       ?? ''),
    's6_photos_url'    => trim($body['s6_photos_url']     ?? ''),
    's7_waste_level'   => in_array($body['s7_waste_level'] ?? '', $allowed_waste)
                            ? $body['s7_waste_level']
                            : 'none',
];

// Валидация даты
if ($d['report_date'] && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['report_date'])) {
    $d['report_date'] = '';
}

try {
    save_oc_monitoring($d);
    generate_oc_excel($d);
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
