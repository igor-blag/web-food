<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Генерирует файл Excel «Перечень ресурсов раздела Питание» (общественный контроль).
 * Загружает шаблон oc/findex-template.xlsx, подставляет данные и сохраняет в FILES_DIR.
 *
 * @param  array  $d  Данные из oc_monitoring (результат get_oc_monitoring())
 * @return string Абсолютный путь к сгенерированному файлу
 */
function generate_oc_excel(array $d): string
{
    $templatePath = FILES_DIR . 'oc/findex-template.xlsx';
    $spreadsheet  = IOFactory::load($templatePath);
    $sheet        = $spreadsheet->getActiveSheet();

    // ── Шапка ────────────────────────────────────────────────────────────────
    $sheet->getCell('B1')->setValue($d['school_name']);
    if ($d['report_date']) {
        $dt = new DateTime($d['report_date']);
        $sheet->getCell('D1')->setValue($dt->format('d.m.Y'));
        // Убираем числовой формат даты шаблона, чтобы Excel отобразил строку
        $sheet->getStyle('D1')->getNumberFormat()->setFormatCode('@');
    }

    // ── Раздел 1 ─────────────────────────────────────────────────────────────
    $sheet->getCell('C4')->setValue($d['s1_url']);

    // ── Раздел 2 ─────────────────────────────────────────────────────────────
    $sheet->getCell('C6')->setValue($d['s2_hotline']);
    $sheet->getCell('C7')->setValue($d['s2_chat_url']);
    $sheet->getCell('C8')->setValue($d['s2_forum_url']);

    // ── Раздел 3 — диетические меню ──────────────────────────────────────────
    $diets = [
        1 => ['type' => $d['s3_diet1_type'], 'url' => $d['s3_diet1_url'], 'row_t' => 10, 'row_u' => 11],
        2 => ['type' => $d['s3_diet2_type'], 'url' => $d['s3_diet2_url'], 'row_t' => 12, 'row_u' => 13],
        3 => ['type' => $d['s3_diet3_type'], 'url' => $d['s3_diet3_url'], 'row_t' => 14, 'row_u' => 15],
        4 => ['type' => $d['s3_diet4_type'], 'url' => $d['s3_diet4_url'], 'row_t' => 16, 'row_u' => 17],
    ];
    foreach ($diets as $diet) {
        $sheet->getCell('C' . $diet['row_t'])->setValue($diet['type']);
        $sheet->getCell('C' . $diet['row_u'])->setValue($diet['url']);
    }

    // ── Раздел 4 ─────────────────────────────────────────────────────────────
    $sheet->getCell('C19')->setValue($d['s4_survey_url']);
    $sheet->getCell('C20')->setValue($d['s4_results_url']);

    // ── Раздел 5 ─────────────────────────────────────────────────────────────
    $sheet->getCell('C22')->setValue($d['s5_page_url']);
    $sheet->getCell('C23')->setValue($d['s5_materials_url']);

    // ── Раздел 6 ─────────────────────────────────────────────────────────────
    $sheet->getCell('C25')->setValue($d['s6_acts_url']);
    $sheet->getCell('C26')->setValue($d['s6_photos_url']);

    // ── Раздел 7 — пищевые отходы ────────────────────────────────────────────
    // Сначала снимаем все отметки
    foreach ([28, 29, 30, 31, 32] as $row) {
        $sheet->getCell('C' . $row)->setValue('');
    }
    $wasteRowMap = ['20' => 28, '30' => 29, '40' => 30, '50' => 31, 'none' => 32];
    if (!empty($d['s7_waste_level']) && isset($wasteRowMap[$d['s7_waste_level']])) {
        $sheet->getCell('C' . $wasteRowMap[$d['s7_waste_level']])->setValue('+');
    }

    // ── Сохранение ───────────────────────────────────────────────────────────
    if (!is_dir(FILES_DIR)) {
        mkdir(FILES_DIR, 0755, true);
    }
    $filename = 'findex.xlsx';
    $filepath = FILES_DIR . $filename;
    (new Xlsx($spreadsheet))->save($filepath);

    return $filepath;
}
