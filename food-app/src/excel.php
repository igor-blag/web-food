<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/db.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

define('CLR_FILL',  'FFFFF2CC'); // жёлто-бежевый для данных
define('BRD_M', Border::BORDER_MEDIUM);
define('BRD_T', Border::BORDER_THIN);
define('BRD_N', Border::BORDER_NONE);

function generate_menu_excel(string $date, string $type = 'sm'): ?string
{
    $cal = get_calendar_day($date, $type);
    if (!$cal || !$cal['template_id']) return null;

    $tpl      = get_template((int)$cal['template_id']);
    $items    = get_template_items((int)$cal['template_id']);
    $deptInfo = get_department($type);
    $school   = get_org_name() ?: ($cal['school'] ?? '-');
    $dept     = $deptInfo ? $deptInfo['dept_name'] : ($cal['dept'] ?? '');
    $dateObj  = new DateTime($date);
    $dayNumber = $tpl ? (int)$tpl['day_number'] : null;

    $sections = [
        'breakfast'  => ['label' => 'Завтрак',   'items' => $items['breakfast']],
        'breakfast2' => ['label' => 'Завтрак 2', 'items' => $items['breakfast2']],
        'lunch'      => ['label' => 'Обед',       'items' => $items['lunch']],
    ];

    if ($tpl && !empty($tpl['is_boarding'])) {
        $sections['afternoon_snack'] = ['label' => 'Полдник', 'items' => $items['afternoon_snack']];
        $sections['dinner']          = ['label' => 'Ужин',    'items' => $items['dinner']];
        $sections['dinner2']         = ['label' => 'Ужин 2',  'items' => $items['dinner2']];
    }

    // Стандартные подразделы каждого приёма пищи — белый фон, заблокированы
    $standardSections = [
        'breakfast'       => ['гор.блюдо', 'гор.напиток', 'хлеб', 'фрукты'],
        'breakfast2'      => ['фрукты'],
        'lunch'           => ['закуска', '1 блюдо', '2 блюдо', 'гарнир', 'напиток', 'хлеб бел.', 'хлеб черн.'],
        'afternoon_snack' => ['булочное', 'напиток'],
        'dinner'          => ['гор.блюдо', 'гарнир', 'напиток', 'хлеб'],
        'dinner2'         => ['кисломол.', 'булочное', 'напиток', 'фрукты'],
    ];

    $spreadsheet = new Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();

    // Название вкладки: "6 день" (номер + день)
    $tabName = $dayNumber ? $dayNumber . ' день' : 'Меню';
    $sheet->setTitle(mb_substr($tabName, 0, 31));
    $sheet->getTabColor()->setARGB('FFFFD966');

    // Скрыть сетку и заголовки строк/столбцов
    $sheet->setShowGridlines(false);
    $sheet->setShowRowColHeaders(false);

    // Ширина колонок (F — без явной ширины, авто)
    foreach (['A'=>12.14,'B'=>11.57,'C'=>8,'D'=>41.57,'E'=>10.14,'G'=>13.43,'H'=>7.71,'I'=>7.86,'J'=>10.43] as $c=>$w) {
        $sheet->getColumnDimension($c)->setWidth($w);
    }

    // ── Строка 1: шапка ─────────────────────────────────────────────────────
    $sheet->setCellValue('A1', 'Школа');
    $sheet->setCellValue('B1', $school);
    $sheet->mergeCells('B1:D1');
    $sheet->setCellValue('E1', 'Отд./корп');
    $sheet->setCellValue('F1', $dept);
    $sheet->setCellValue('I1', 'День');
    $sheet->setCellValue('J1', Date::PHPToExcel($dateObj));
    $sheet->getStyle('J1')->getNumberFormat()->setFormatCode('m/d/yyyy');

    // Разблокировать редактируемые ячейки строки 1
    foreach (['B1:D1', 'F1', 'J1'] as $r) {
        $sheet->getStyle($r)->getProtection()->setLocked(Protection::PROTECTION_UNPROTECTED);
    }

    // Жёлто-бежевый и белый фоны
    $fillYellow = [
        'fillType'   => Fill::FILL_SOLID,
        'startColor' => ['argb' => CLR_FILL],
        'endColor'   => ['argb' => 'FFFFFFFF'],
    ];
    $fillWhite = [
        'fillType'   => Fill::FILL_SOLID,
        'startColor' => ['argb' => 'FFFFFFFF'],
        'endColor'   => ['argb' => 'FFFFFFFF'],
    ];
    foreach (['B1:D1', 'J1'] as $range) {
        $sheet->getStyle($range)->applyFromArray([
            'fill'    => $fillYellow,
            'borders' => ['allBorders' => ['borderStyle' => BRD_T]],
        ]);
    }
    $sheet->getStyle('F1')->applyFromArray([
        'fill'         => $fillYellow,
        'borders'      => ['allBorders' => ['borderStyle' => BRD_T]],
        'numberFormat' => ['formatCode' => '@'],
    ]);

    // ── Строка 2: разделитель ────────────────────────────────────────────────
    $sheet->getRowDimension(2)->setRowHeight(7.5);

    // ── Строка 3: заголовки колонок ──────────────────────────────────────────
    $headers = [
        'A'=>'Прием пищи','B'=>'Раздел','C'=>'№ рец.','D'=>'Блюдо',
        'E'=>'Выход, г','F'=>'Цена','G'=>'Калорийность','H'=>'Белки','I'=>'Жиры','J'=>'Углеводы',
    ];
    foreach ($headers as $col => $val) {
        $sheet->setCellValue($col.'3', $val);
    }
    $sheet->getRowDimension(3)->setRowHeight(15);

    // Заголовки: центр + нижний край; внешняя рамка — medium, внутри — thin
    $sheet->getStyle('A3:J3')->applyFromArray([
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical'   => Alignment::VERTICAL_BOTTOM,
        ],
        'borders' => [
            'allBorders' => ['borderStyle' => BRD_T],
        ],
    ]);
    // Внешняя рамка строки 3 — medium со всех сторон
    foreach (range('A', 'J') as $col) {
        $sheet->getStyle($col.'3')->getBorders()->getTop()->setBorderStyle(BRD_M);
        $sheet->getStyle($col.'3')->getBorders()->getBottom()->setBorderStyle(BRD_M);
    }
    $sheet->getStyle('A3')->getBorders()->getLeft()->setBorderStyle(BRD_M);
    $sheet->getStyle('J3')->getBorders()->getRight()->setBorderStyle(BRD_M);

    // ── Строки данных ────────────────────────────────────────────────────────
    $row = 4;

    foreach ($sections as $sectionKey => $section) {
        $mealRows = $section['items'];
        if (empty($mealRows)) $mealRows = [[]]; // хотя бы пустая строка в секции
        $count = count($mealRows);
        $stdList = array_map('mb_strtolower', $standardSections[$sectionKey] ?? []);

        foreach ($mealRows as $i => $item) {
            $isFirst = ($i === 0);

            // Значения ячеек
            $sheet->setCellValue('A'.$row, $isFirst ? $section['label'] : '');
            $sheet->setCellValue('B'.$row, $item['section']    ?? '');
            $sheet->setCellValue('C'.$row, $item['recipe_num'] ?? '');
            $sheet->setCellValue('D'.$row, $item['dish_name']  ?? '');
            $sheet->setCellValue('E'.$row, isset($item['grams'])   ? (float)$item['grams']   : '');
            $sheet->setCellValue('F'.$row, isset($item['price'])   ? (float)$item['price']   : '');
            $sheet->setCellValue('G'.$row, isset($item['kcal'])    ? (float)$item['kcal']    : '');
            $sheet->setCellValue('H'.$row, isset($item['protein']) ? (float)$item['protein'] : '');
            $sheet->setCellValue('I'.$row, isset($item['fat'])     ? (float)$item['fat']     : '');
            $sheet->setCellValue('J'.$row, isset($item['carbs'])   ? (float)$item['carbs']   : '');

            // Высота строки: только для длинных строк, иначе авто
            if (mb_strlen($item['dish_name'] ?? '') > 40) {
                $sheet->getRowDimension($row)->setRowHeight(28.8);
            }

            // Стандартный подраздел: только колонка B белая (заблокирована), C-J жёлтые (разблокированы)
            $isStandard = in_array(mb_strtolower(trim($item['section'] ?? '')), $stdList);

            // Колонка A: medium левая, medium top только на первой строке секции
            $sheet->getStyle('A'.$row)->applyFromArray([
                'borders'   => [
                    'left' => ['borderStyle' => BRD_M],
                    'top'  => ['borderStyle' => $isFirst ? BRD_M : BRD_N],
                ],
                'alignment' => ['vertical' => Alignment::VERTICAL_BOTTOM],
            ]);

            $topBrd = $isFirst ? BRD_M : BRD_T;

            // Колонки B–J: жёлтый фон, thin внутри, medium top на первой строке и правом краю
            $sheet->getStyle('B'.$row.':J'.$row)->applyFromArray([
                'fill'    => $fillYellow,
                'borders' => [
                    'allBorders' => ['borderStyle' => BRD_T],
                    'top'        => ['borderStyle' => $topBrd],
                ],
                'alignment' => ['vertical' => Alignment::VERTICAL_BOTTOM],
            ]);
            // Колонка B стандартного подраздела — белый фон (визуальная метка «не трогать»)
            if ($isStandard) {
                $sheet->getStyle('B'.$row)->applyFromArray(['fill' => $fillWhite]);
            }
            // Правая граница J — всегда medium
            $sheet->getStyle('J'.$row)->getBorders()->getRight()->setBorderStyle(BRD_M);

            // Разблокировка: B стандартных строк заблокирована, C-J всегда доступны
            if ($isStandard) {
                $sheet->getStyle('C'.$row.':J'.$row)->getProtection()
                    ->setLocked(Protection::PROTECTION_UNPROTECTED);
            } else {
                $sheet->getStyle('B'.$row.':J'.$row)->getProtection()
                    ->setLocked(Protection::PROTECTION_UNPROTECTED);
            }

            // Форматы чисел и перенос — после applyFromArray, чтобы не перезаписывались
            $sheet->getStyle('E'.$row)->getNumberFormat()->setFormatCode('0');
            $sheet->getStyle('F'.$row)->getNumberFormat()->setFormatCode('0.00');
            $sheet->getStyle('G'.$row.':J'.$row)->getNumberFormat()->setFormatCode('0');
            $sheet->getStyle('D'.$row)->getAlignment()->setWrapText(true);

            $row++;
        }

        // Пустая строка-паддинг в конце секции — внутри толстой рамки (bottom закрывает секцию)
        $sheet->getStyle('A'.$row)->applyFromArray([
            'borders' => [
                'left'   => ['borderStyle' => BRD_M],
                'bottom' => ['borderStyle' => BRD_M],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_BOTTOM],
        ]);
        $sheet->getStyle('B'.$row.':J'.$row)->applyFromArray([
            'fill'    => $fillYellow,
            'borders' => [
                'allBorders' => ['borderStyle' => BRD_T],
                'bottom'     => ['borderStyle' => BRD_M],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_BOTTOM],
        ]);
        $sheet->getStyle('J'.$row)->getBorders()->getRight()->setBorderStyle(BRD_M);
        $sheet->getStyle('B'.$row.':J'.$row)->getProtection()->setLocked(Protection::PROTECTION_UNPROTECTED);
        $sheet->getStyle('E'.$row)->getNumberFormat()->setFormatCode('0');
        $sheet->getStyle('F'.$row)->getNumberFormat()->setFormatCode('0.00');
        $sheet->getStyle('G'.$row.':J'.$row)->getNumberFormat()->setFormatCode('0');
        $sheet->getStyle('D'.$row)->getAlignment()->setWrapText(true);
        $row++;
    }

    // ── Защита листа ─────────────────────────────────────────────────────────
    $sheet->getProtection()->setSheet(true);

    // ── Параметры печати ─────────────────────────────────────────────────────
    $sheet->getPageSetup()
        ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
        ->setPaperSize(PageSetup::PAPERSIZE_A4);
    $sheet->getPageMargins()->setLeft(0.25)->setRight(0.25)->setTop(0.75)->setBottom(0.75);

    // ── Сохранение ───────────────────────────────────────────────────────────
    if (!is_dir(FILES_DIR)) mkdir(FILES_DIR, 0755, true);
    $suffix   = $deptInfo ? $deptInfo['file_suffix'] : (($type !== 'main') ? "-{$type}" : '');
    $filepath = FILES_DIR . $dateObj->format('Y-m-d') . $suffix . '.xlsx';
    (new Xlsx($spreadsheet))->save($filepath);
    return $filepath;
}

function cleanup_old_files(): int
{
    $deleted = 0;
    $cutoff  = time() - (DELETE_AFTER_DAYS * 86400);
    foreach (glob(FILES_DIR . '*.xlsx') ?: [] as $file) {
        if (filemtime($file) < $cutoff) { unlink($file); $deleted++; }
    }
    return $deleted;
}
