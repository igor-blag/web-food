<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/db.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

/**
 * Генерирует kp{year}-{type}.xlsx — годовая сетка календаря питания.
 *
 * Структура:
 *   Строка 1: шапка (школа, заголовок, год)
 *   Строка 2: пустая
 *   Строка 3: A="Месяц", B=1 .. AF=31
 *   Строки 4–13: 10 учебных месяцев (янв–июн, сен–дек)
 *                A=название, B–AF=day_number или пусто
 */
function generate_kp_excel(int $year, string $type = 'sm'): string
{
    $calData = get_calendar_year($year, $type);

    // 10 учебных месяцев
    $schoolMonths = [1,2,3,4,5,6,9,10,11,12];
    $monthNamesRu = [
        1=>'Январь', 2=>'Февраль', 3=>'Март', 4=>'Апрель',
        5=>'Май', 6=>'Июнь', 9=>'Сентябрь', 10=>'Октябрь',
        11=>'Ноябрь', 12=>'Декабрь',
    ];

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Кп ' . $year);

    // ── Ширины колонок ───────────────────────────────────────────────────────
    $sheet->getColumnDimension('A')->setWidth(9);
    for ($col = 2; $col <= 32; $col++) { // B..AF (колонки 2..32)
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
        $sheet->getColumnDimension($colLetter)->setWidth(3.5);
    }

    // ── Строка 1: шапка ──────────────────────────────────────────────────────
    $sheet->setCellValue('A1', 'Школа');
    // L1: заголовок
    $sheet->setCellValue('L1', 'Календарь питания');
    $sheet->getStyle('L1')->getFont()->setBold(true)->setSize(14);
    // AC1: метка года
    $sheet->setCellValue('AC1', 'Год');
    // AD1: значение года (жёлтый фон)
    $sheet->setCellValue('AD1', $year);
    $sheet->mergeCells('AD1:AE1');
    $sheet->getStyle('AD1')->applyFromArray([
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFF2CC']],
        'numberFormat' => ['formatCode' => '0'],
    ]);

    // ── Строка 2: пустая ─────────────────────────────────────────────────────
    $sheet->getRowDimension(2)->setRowHeight(6);

    // ── Строка 3: заголовки дней (1–31) ──────────────────────────────────────
    $sheet->setCellValue('A3', 'Месяц');
    $sheet->getStyle('A3')->getFont()->setBold(true);
    for ($d = 1; $d <= 31; $d++) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($d + 1); // B=col2
        $sheet->setCellValue($colLetter . '3', $d);
        $sheet->getStyle($colLetter . '3')->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'font'      => ['bold' => true],
        ]);
    }

    // ── Строки 4–13: месяцы ──────────────────────────────────────────────────
    $row = 4;
    foreach ($schoolMonths as $month) {
        $sheet->setCellValue('A' . $row, $monthNamesRu[$month]);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true);

        // Количество дней в месяце
        $daysInMonth = (int)(new DateTime(sprintf('%04d-%02d-01', $year, $month)))->format('t');

        for ($d = 1; $d <= 31; $d++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($d + 1);
            $cellRef   = $colLetter . $row;

            if ($d > $daysInMonth) {
                // Несуществующая дата — серая заливка
                $sheet->getStyle($cellRef)->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD9D9D9']],
                ]);
                continue;
            }

            $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $wday    = (int)(new DateTime($dateStr))->format('N'); // 1=пн, 7=вс

            if ($wday >= 6) {
                // Выходной — светло-серая заливка
                $sheet->getStyle($cellRef)->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF2F2F2']],
                ]);
                continue;
            }

            // Учебный день
            if (isset($calData[$dateStr]) && $calData[$dateStr]['template_id'] !== null) {
                $dayNum = (int)$calData[$dateStr]['day_number'];
                $sheet->setCellValue($cellRef, $dayNum);
                $sheet->getStyle($cellRef)->applyFromArray([
                    'fill'         => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFF2CC']],
                    'numberFormat' => ['formatCode' => '0'],
                    'alignment'    => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
            }
            // иначе — пусто (рабочий день без шаблона), без заливки
        }

        $row++;
    }

    // ── Сохранение ───────────────────────────────────────────────────────────
    if (!is_dir(FILES_DIR)) mkdir(FILES_DIR, 0755, true);
    $filepath = FILES_DIR . "kp{$year}.xlsx";
    (new Xlsx($spreadsheet))->save($filepath);
    return $filepath;
}
