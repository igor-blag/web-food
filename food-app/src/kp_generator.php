<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/db.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/**
 * Генерирует kp{year}.xlsx — годовая сетка календаря питания.
 *
 * Если файл уже существует — обновляются только значения ячеек дней (строки 4–13).
 * Если файла нет — создаётся новый с полной структурой и форматированием.
 *
 * Структура:
 *   Строка 1: шапка (B1=организация, L1=заголовок, AC1=«Год», AD1=год)
 *   Строка 2: пустая
 *   Строка 3: A="Месяц", B=1 .. AF=31
 *   Строки 4–13: 10 учебных месяцев (янв–июн, сен–дек)
 *                A=название, B–AF=day_number или пусто
 */
function generate_kp_excel(int $year, string $type = 'sm'): string
{
    $calData   = get_calendar_year($year, $type);
    $filepath  = FILES_DIR . "kp{$year}.xlsx";

    if (!is_dir(FILES_DIR)) {
        mkdir(FILES_DIR, 0755, true);
    }

    if (file_exists($filepath)) {
        // ── Файл существует: загружаем и обновляем только данные дней ──────
        $spreadsheet = IOFactory::load($filepath);
        $sheet       = $spreadsheet->getActiveSheet();
        _kp_update_day_cells($sheet, $year, $calData);
    } else {
        // ── Файл не существует: создаём с нуля ──────────────────────────────
        $spreadsheet = _kp_create($year, $calData);
        $sheet       = $spreadsheet->getActiveSheet();
    }

    (new Xlsx($spreadsheet))->save($filepath);
    return $filepath;
}

/**
 * Создаёт новый Spreadsheet с полной структурой и форматированием.
 */
function _kp_create(int $year, array $calData): Spreadsheet
{
    $monthNamesRu = [
        1=>'январь', 2=>'февраль', 3=>'март',  4=>'апрель',
        5=>'май',    6=>'июнь',    9=>'сентябрь', 10=>'октябрь',
        11=>'ноябрь', 12=>'декабрь',
    ];
    $schoolMonths = [1,2,3,4,5,6,9,10,11,12];

    $spreadsheet = new Spreadsheet();
    $spreadsheet->getDefaultStyle()->getFont()->setSize(10);
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Кп ' . $year);

    // ── Ширины колонок ───────────────────────────────────────────────────────
    $sheet->getColumnDimension('A')->setWidth(7.857);
    for ($col = 2; $col <= 32; $col++) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
        $sheet->getColumnDimension($colLetter)->setWidth(4.286);
    }

    // ── Высота строки 1 ───────────────────────────────────────────────────────
    $sheet->getRowDimension(1)->setRowHeight(18.75);

    // ── Строка 1: шапка ──────────────────────────────────────────────────────
    $sheet->setCellValue('A1', 'Школа');
    // B1:J1 — название организации
    $sheet->mergeCells('B1:J1');
    $sheet->setCellValue('B1', get_org_name());
    $sheet->getStyle('B1:J1')->applyFromArray([
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFF2CC']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        'borders'   => ['outline' => ['borderStyle' => Border::BORDER_THIN]],
    ]);
    // L1: заголовок
    $sheet->setCellValue('L1', 'Календарь питания');
    $sheet->getStyle('L1')->getFont()->setBold(true)->setSize(14);
    // AC1: метка года
    $sheet->setCellValue('AC1', 'Год');
    // AD1:AE1 — значение года
    $sheet->mergeCells('AD1:AE1');
    $sheet->setCellValue('AD1', $year);
    $sheet->getStyle('AD1:AE1')->applyFromArray([
        'fill'         => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFF2CC']],
        'alignment'    => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders'      => ['outline' => ['borderStyle' => Border::BORDER_THIN]],
        'numberFormat' => ['formatCode' => '0'],
    ]);

    // ── Строка 3: заголовки дней (1–31) ──────────────────────────────────────
    $sheet->setCellValue('A3', 'Месяц');
    $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    for ($d = 1; $d <= 31; $d++) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($d + 1);
        $sheet->setCellValue($colLetter . '3', $d);
        $sheet->getStyle($colLetter . '3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    // ── Строки 4–13: месяцы ──────────────────────────────────────────────────
    $row = 4;
    foreach ($schoolMonths as $month) {
        $sheet->setCellValue('A' . $row, $monthNamesRu[$month]);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        _kp_fill_month_row($sheet, $row, $year, $month, $calData);
        $row++;
    }

    // ── Границы: строки 3–13 полностью, строки 1–2 только B1 и AD1 ──────────
    $sheet->getStyle('A3:AF13')->applyFromArray([
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    ]);

    // ── Настройки страницы ────────────────────────────────────────────────────
    $sheet->getPageSetup()
        ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
        ->setPaperSize(PageSetup::PAPERSIZE_A4)
        ->setFitToWidth(1)
        ->setFitToHeight(1);

    return $spreadsheet;
}

/**
 * Записывает значения дней одного месяца в строку (без изменения форматирования).
 */
function _kp_fill_month_row($sheet, int $row, int $year, int $month, array $calData): void
{
    $daysInMonth = (int)(new DateTime(sprintf('%04d-%02d-01', $year, $month)))->format('t');

    for ($d = 1; $d <= 31; $d++) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($d + 1);
        $cellRef   = $colLetter . $row;

        if ($d > $daysInMonth) {
            // Несуществующая дата — пусто
            $sheet->setCellValue($cellRef, null);
            continue;
        }

        $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
        $wday    = (int)(new DateTime($dateStr))->format('N'); // 1=пн, 7=вс

        if ($wday >= 6) {
            // Выходной — пусто
            $sheet->setCellValue($cellRef, null);
            continue;
        }

        // Учебный день
        if (isset($calData[$dateStr]) && $calData[$dateStr]['template_id'] !== null) {
            $dayNum = (int)$calData[$dateStr]['day_number'];
            $sheet->setCellValue($cellRef, $dayNum);
            $sheet->getStyle($cellRef)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        } else {
            $sheet->setCellValue($cellRef, null);
        }
    }
}

/**
 * Обновляет только значения ячеек дней в уже существующем файле (строки 4–13).
 */
function _kp_update_day_cells($sheet, int $year, array $calData): void
{
    $schoolMonths = [1,2,3,4,5,6,9,10,11,12];
    $row = 4;
    foreach ($schoolMonths as $month) {
        _kp_fill_month_row($sheet, $row, $year, $month, $calData);
        $row++;
    }
}
