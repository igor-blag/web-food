<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Парсит xlsx-файл меню и возвращает массив блюд, готовый для save_template_items().
 * Структура файла: строка 3 — заголовки, строки 4+ — данные.
 * Колонка A — тип приёма пищи (только первая строка секции).
 * Колонка D — название блюда (пустая = разделитель секций).
 */
function parse_menu_xlsx(string $filepath): array
{
    $spreadsheet = IOFactory::load($filepath);
    $sheet       = $spreadsheet->getActiveSheet();

    // Маппинг русских названий секций → meal_type
    $mealMap = [
        'завтрак'   => 'breakfast',
        'завтрак 2' => 'breakfast2',
        'завтрак2'  => 'breakfast2',
        'обед'      => 'lunch',
        'полдник'   => 'afternoon_snack',
        'ужин'      => 'dinner',
        'ужин 2'    => 'dinner2',
        'ужин2'     => 'dinner2',
    ];

    $items       = [];
    $currentMeal = null;
    $sortOrder   = 0;
    $maxRow      = $sheet->getHighestDataRow();

    for ($row = 4; $row <= $maxRow; $row++) {
        $colA = trim((string)$sheet->getCell('A' . $row)->getValue());
        $colD = trim((string)$sheet->getCell('D' . $row)->getValue());

        // Обновляем текущую секцию если в A есть текст
        if ($colA !== '') {
            $key = mb_strtolower($colA);
            if (isset($mealMap[$key])) {
                $currentMeal = $mealMap[$key];
                $sortOrder   = 0;
            }
        }

        // Пропускаем строки где нет ни раздела, ни блюда
        $colB = trim((string)$sheet->getCell('B' . $row)->getValue());
        if ($currentMeal === null || ($colD === '' && $colB === '')) {
            continue;
        }

        $val = function(string $col) use ($sheet, $row): ?float {
            $v = trim((string)$sheet->getCell($col . $row)->getValue());
            return ($v !== '' && is_numeric($v)) ? (float)$v : null;
        };

        $items[] = [
            'meal_type'  => $currentMeal,
            'section'    => trim((string)$sheet->getCell('B' . $row)->getValue()),
            'recipe_num' => trim((string)$sheet->getCell('C' . $row)->getValue()),
            'dish_name'  => $colD,
            'grams'      => $val('E'),
            'price'      => $val('F'),
            'kcal'       => $val('G'),
            'protein'    => $val('H'),
            'fat'        => $val('I'),
            'carbs'      => $val('J'),
            'sort_order' => $sortOrder++,
        ];
    }

    return $items;
}
