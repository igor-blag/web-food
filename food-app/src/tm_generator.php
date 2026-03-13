<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/db.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Генерирует типовое примерное меню (tm-файл) для указанного типа школы и года.
 * Формат шапки соответствует образцу с сайта мониторинга питания.
 * Возвращает путь к сохранённому файлу.
 */
function generate_typical_menu_excel(string $school_type, int $year): string
{
    $s = db()->prepare(
        'SELECT * FROM menu_templates WHERE school_type = ? ORDER BY day_number'
    );
    $s->execute([$school_type]);
    $templates = $s->fetchAll();

    $settings = get_kitchen_settings();
    $orgName   = $settings['org_name']            ?? '';
    $position  = $settings['tm_approver_position'] ?? '';
    $name      = $settings['tm_approver_name']     ?? '';
    $approveDate = $settings['tm_approve_date']    ?? null;

    // Количество дней в неделе — из структуры шаблонов: пробуем 5, затем 6, затем 7
    // 10 → 2×5, 12 → 2×6, 14 → 2×7, 15 → 3×5, 20 → 4×5 и т.д.
    $total = count($templates);
    if ($total % 5 === 0)      $daysPerWeek = 5;
    elseif ($total % 6 === 0)  $daysPerWeek = 6;
    elseif ($total % 7 === 0)  $daysPerWeek = 7;
    else                       $daysPerWeek = 5; // нестандартный цикл — считаем по 5

    $approveDay   = null;
    $approveMonth = null;
    $approveYear  = $year;
    if ($approveDate) {
        $dt = date_create($approveDate);
        if ($dt) {
            $approveDay   = (int)date_format($dt, 'j');
            $approveMonth = (int)date_format($dt, 'n');
            $approveYear  = (int)date_format($dt, 'Y');
        }
    }

    // Возрастная категория по типу школы
    $ageCategory = match ($school_type) {
        'sm'   => '7-11 лет',
        'main' => '11-15 лет',
        'ss'   => '15-18 лет',
        default => '',
    };

    $spreadsheet = new Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Типовое меню');
    $sheet->getTabColor()->setARGB('FFFFD966');

    // ── Ширина колонок ───────────────────────────────────────────────────────
    $colWidths = [
        'A' => 6,   // Неделя
        'B' => 6,   // День недели
        'C' => 15,  // Приём пищи
        'D' => 16,  // Раздел меню
        'E' => 36,  // Блюда
        'F' => 9,   // Вес блюда, г
        'G' => 8,   // Белки
        'H' => 8,   // Жиры
        'I' => 8,   // Углеводы
        'J' => 10,  // Калорийность
        'K' => 12,  // № рецептуры
        'L' => 10,  // Цена
    ];
    foreach ($colWidths as $col => $width) {
        $sheet->getColumnDimension($col)->setWidth($width);
    }

    $BRD_M = Border::BORDER_MEDIUM;
    $BRD_T = Border::BORDER_THIN;
    $CLR_GREY = 'FFD9D9D9';

    // ── Строка 1: Школа / Утвердил ──────────────────────────────────────────
    // A1 = "Школа", C1:E1 = название организации, F1 = "Утвердил:", G1 = "должность", H1:K1 = должность
    $sheet->setCellValue('A1', 'Школа');
    $sheet->mergeCells('C1:E1');
    $sheet->setCellValue('C1', $orgName);
    $sheet->setCellValue('F1', 'Утвердил:');
    $sheet->setCellValue('G1', 'должность');
    $sheet->mergeCells('H1:K1');
    $sheet->setCellValue('H1', $position);
    $sheet->getStyle('A1:L1')->applyFromArray([
        'font'      => ['bold' => false, 'size' => 10],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
    ]);
    $sheet->getStyle('C1')->applyFromArray([
        'borders' => ['bottom' => ['borderStyle' => $BRD_T]],
    ]);
    $sheet->getStyle('H1')->applyFromArray([
        'borders' => ['bottom' => ['borderStyle' => $BRD_T]],
    ]);
    $sheet->getStyle('G1')->applyFromArray([
        'font'      => ['color' => ['argb' => 'FF808080'], 'size' => 8],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
    ]);
    $sheet->getRowDimension(1)->setRowHeight(15);

    // ── Строка 2: Заголовок документа / ФИО подписанта ──────────────────────
    // A2 = заголовок, G2 = "фамилия", H2:K2 = ФИО
    $sheet->setCellValue('A2', 'Типовое примерное меню приготавливаемых блюд');
    $sheet->getStyle('A2')->applyFromArray([
        'font'      => ['bold' => true, 'size' => 11],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
    ]);
    $sheet->setCellValue('G2', 'фамилия');
    $sheet->mergeCells('H2:K2');
    $sheet->setCellValue('H2', $name);
    $sheet->getStyle('H2')->applyFromArray([
        'borders' => ['bottom' => ['borderStyle' => $BRD_T]],
    ]);
    $sheet->getStyle('G2')->applyFromArray([
        'font'      => ['color' => ['argb' => 'FF808080'], 'size' => 8],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
    ]);
    $sheet->getRowDimension(2)->setRowHeight(18);

    // ── Строка 3: Возрастная категория / Дата ────────────────────────────────
    // A3 = "Возрастная категория", E3 = значение, G3 = "дата", H3 = день, I3 = месяц, J3 = год
    $sheet->setCellValue('A3', 'Возрастная категория');
    $sheet->setCellValue('E3', $ageCategory);
    $sheet->setCellValue('G3', 'дата');
    if ($approveDay !== null) {
        $sheet->setCellValue('H3', $approveDay);
    }
    if ($approveMonth !== null) {
        $sheet->setCellValue('I3', $approveMonth);
    }
    $sheet->setCellValue('J3', $approveYear);
    $sheet->getStyle('G3')->applyFromArray([
        'font'      => ['color' => ['argb' => 'FF808080'], 'size' => 8],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
    ]);
    $sheet->getRowDimension(3)->setRowHeight(15);

    // ── Строка 4: подписи к полям даты ───────────────────────────────────────
    $sheet->setCellValue('H4', 'день');
    $sheet->setCellValue('I4', 'месяц');
    $sheet->setCellValue('J4', 'год');
    $sheet->getStyle('H4:J4')->applyFromArray([
        'font'      => ['color' => ['argb' => 'FF808080'], 'size' => 8],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
    $sheet->getRowDimension(4)->setRowHeight(10);

    // ── Строка 5: заголовки колонок ──────────────────────────────────────────
    $colHeaders = [
        'A' => 'Неделя',
        'B' => 'День недели',
        'C' => 'Прием пищи',
        'D' => 'Раздел меню',
        'E' => 'Блюда',
        'F' => 'Вес блюда, г',
        'G' => 'Белки',
        'H' => 'Жиры',
        'I' => 'Углеводы',
        'J' => 'Калорийность',
        'K' => '№ рецептуры',
        'L' => 'Цена',
    ];
    foreach ($colHeaders as $col => $val) {
        $sheet->setCellValue($col . '5', $val);
    }
    $sheet->getStyle('A5:L5')->applyFromArray([
        'font'      => ['bold' => true, 'size' => 9],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFBDD7EE']],
        'borders'   => [
            'outline'        => ['borderStyle' => $BRD_M],
            'insideVertical' => ['borderStyle' => $BRD_T],
        ],
    ]);
    $sheet->getRowDimension(5)->setRowHeight(28);

    // ── Данные: дни цикла ────────────────────────────────────────────────────
    $row = 6;

    foreach ($templates as $tpl) {
        $dayNum    = (int)$tpl['day_number'];
        $week      = (int)ceil($dayNum / $daysPerWeek);
        $dayInWeek = (($dayNum - 1) % $daysPerWeek) + 1;
        $items     = get_template_items((int)$tpl['id']);

        // Завтрак и Завтрак 2 объединяем в один блок
        $breakfastItems = array_merge($items['breakfast'], $items['breakfast2']);
        $lunchItems     = $items['lunch'];

        if (empty($breakfastItems)) $breakfastItems = [[]];
        if (empty($lunchItems))     $lunchItems     = [[]];

        // ── Завтрак ──────────────────────────────────────────────────────────
        $bStart = $row;
        $weekRow = $row;

        foreach ($breakfastItems as $i => $item) {
            $isFirst = ($i === 0);
            if ($isFirst) {
                $sheet->setCellValue('A' . $row, $week);
                $sheet->setCellValue('B' . $row, $dayInWeek);
                $sheet->setCellValue('C' . $row, 'Завтрак');
            }
            $sheet->setCellValue('D' . $row, $item['section']    ?? '');
            $sheet->setCellValue('E' . $row, $item['dish_name']  ?? '');
            if (isset($item['grams'])   && $item['grams']   !== null) $sheet->setCellValue('F' . $row, (float)$item['grams']);
            if (isset($item['protein']) && $item['protein'] !== null) $sheet->setCellValue('G' . $row, (float)$item['protein']);
            if (isset($item['fat'])     && $item['fat']     !== null) $sheet->setCellValue('H' . $row, (float)$item['fat']);
            if (isset($item['carbs'])   && $item['carbs']   !== null) $sheet->setCellValue('I' . $row, (float)$item['carbs']);
            if (isset($item['kcal'])    && $item['kcal']    !== null) $sheet->setCellValue('J' . $row, (float)$item['kcal']);
            $sheet->setCellValue('K' . $row, $item['recipe_num'] ?? '');
            if (isset($item['price'])   && $item['price']   !== null) $sheet->setCellValue('L' . $row, (float)$item['price']);

            _tm_row_style($sheet, $row, $isFirst, $BRD_T, $BRD_M);
            $row++;
        }
        $bEnd = $row - 1;

        // Итого по завтраку
        $bTotalRow = $row;
        $sheet->setCellValue('D' . $row, 'итого');
        $sheet->setCellValue('F' . $row, "=SUM(F{$bStart}:F{$bEnd})");
        $sheet->setCellValue('G' . $row, "=SUM(G{$bStart}:G{$bEnd})");
        $sheet->setCellValue('H' . $row, "=SUM(H{$bStart}:H{$bEnd})");
        $sheet->setCellValue('I' . $row, "=SUM(I{$bStart}:I{$bEnd})");
        $sheet->setCellValue('J' . $row, "=SUM(J{$bStart}:J{$bEnd})");
        $sheet->setCellValue('L' . $row, "=SUM(L{$bStart}:L{$bEnd})");
        $sheet->getStyle('A' . $row . ':L' . $row)->applyFromArray([
            'font'    => ['bold' => true, 'italic' => true],
            'borders' => [
                'top'    => ['borderStyle' => $BRD_T],
                'bottom' => ['borderStyle' => $BRD_M],
                'left'   => ['borderStyle' => $BRD_M],
                'right'  => ['borderStyle' => $BRD_M],
            ],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(13);
        $row++;

        // ── Обед ─────────────────────────────────────────────────────────────
        $lStart = $row;
        foreach ($lunchItems as $i => $item) {
            $isFirst = ($i === 0);
            if ($isFirst) {
                $sheet->setCellValue('A' . $row, "=A{$weekRow}");
                $sheet->setCellValue('B' . $row, "=B{$weekRow}");
                $sheet->setCellValue('C' . $row, 'Обед');
            }
            $sheet->setCellValue('D' . $row, $item['section']    ?? '');
            $sheet->setCellValue('E' . $row, $item['dish_name']  ?? '');
            if (isset($item['grams'])   && $item['grams']   !== null) $sheet->setCellValue('F' . $row, (float)$item['grams']);
            if (isset($item['protein']) && $item['protein'] !== null) $sheet->setCellValue('G' . $row, (float)$item['protein']);
            if (isset($item['fat'])     && $item['fat']     !== null) $sheet->setCellValue('H' . $row, (float)$item['fat']);
            if (isset($item['carbs'])   && $item['carbs']   !== null) $sheet->setCellValue('I' . $row, (float)$item['carbs']);
            if (isset($item['kcal'])    && $item['kcal']    !== null) $sheet->setCellValue('J' . $row, (float)$item['kcal']);
            $sheet->setCellValue('K' . $row, $item['recipe_num'] ?? '');
            if (isset($item['price'])   && $item['price']   !== null) $sheet->setCellValue('L' . $row, (float)$item['price']);

            _tm_row_style($sheet, $row, $isFirst, $BRD_T, $BRD_M);
            $row++;
        }
        $lEnd = $row - 1;

        // Итого по обеду
        $lTotalRow = $row;
        $sheet->setCellValue('D' . $row, 'итого');
        $sheet->setCellValue('F' . $row, "=SUM(F{$lStart}:F{$lEnd})");
        $sheet->setCellValue('G' . $row, "=SUM(G{$lStart}:G{$lEnd})");
        $sheet->setCellValue('H' . $row, "=SUM(H{$lStart}:H{$lEnd})");
        $sheet->setCellValue('I' . $row, "=SUM(I{$lStart}:I{$lEnd})");
        $sheet->setCellValue('J' . $row, "=SUM(J{$lStart}:J{$lEnd})");
        $sheet->setCellValue('L' . $row, "=SUM(L{$lStart}:L{$lEnd})");
        $sheet->getStyle('A' . $row . ':L' . $row)->applyFromArray([
            'font'    => ['bold' => true, 'italic' => true],
            'borders' => [
                'top'    => ['borderStyle' => $BRD_T],
                'bottom' => ['borderStyle' => $BRD_M],
                'left'   => ['borderStyle' => $BRD_M],
                'right'  => ['borderStyle' => $BRD_M],
            ],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(13);
        $row++;

        // ── Итого за день ─────────────────────────────────────────────────────
        $sheet->setCellValue('C' . $row, 'Итого за день:');
        $sheet->setCellValue('F' . $row, "=F{$bTotalRow}+F{$lTotalRow}");
        $sheet->setCellValue('G' . $row, "=G{$bTotalRow}+G{$lTotalRow}");
        $sheet->setCellValue('H' . $row, "=H{$bTotalRow}+H{$lTotalRow}");
        $sheet->setCellValue('I' . $row, "=I{$bTotalRow}+I{$lTotalRow}");
        $sheet->setCellValue('J' . $row, "=J{$bTotalRow}+J{$lTotalRow}");
        $sheet->setCellValue('L' . $row, "=L{$bTotalRow}+L{$lTotalRow}");
        $sheet->getStyle('A' . $row . ':L' . $row)->applyFromArray([
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $CLR_GREY]],
            'font'    => ['bold' => true],
            'borders' => [
                'outline'        => ['borderStyle' => $BRD_M],
                'insideVertical' => ['borderStyle' => $BRD_T],
            ],
        ]);
        $sheet->getRowDimension($row)->setRowHeight(14);
        $row++;
    }

    // ── Сохранение ───────────────────────────────────────────────────────────
    if (!is_dir(FILES_DIR)) mkdir(FILES_DIR, 0755, true);
    $filepath = FILES_DIR . "tm{$year}-{$school_type}.xlsx";
    (new Xlsx($spreadsheet))->save($filepath);
    return $filepath;
}

/**
 * Применяет стиль строки данных в tm-файле.
 */
function _tm_row_style($sheet, int $row, bool $isFirst, string $BRD_T, string $BRD_M): void
{
    $sheet->getStyle('A' . $row . ':L' . $row)->applyFromArray([
        'borders' => [
            'top'            => ['borderStyle' => $isFirst ? $BRD_M : $BRD_T],
            'bottom'         => ['borderStyle' => $BRD_T],
            'left'           => ['borderStyle' => $BRD_M],
            'right'          => ['borderStyle' => $BRD_M],
            'insideVertical' => ['borderStyle' => $BRD_T],
        ],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
    ]);
    $sheet->getStyle('E' . $row)->getAlignment()->setWrapText(true);
    $sheet->getStyle('F' . $row . ':L' . $row)->getNumberFormat()->setFormatCode('0.00');
    $sheet->getRowDimension($row)->setRowHeight(13);
}
