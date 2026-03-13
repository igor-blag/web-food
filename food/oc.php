<?php
/**
 * food/oc.php — публичная страница «Общественный контроль питания».
 */
$adminRoot = __DIR__ . '/../food-app';
require_once $adminRoot . '/config.php';
require_once $adminRoot . '/src/db.php';

$oc           = get_oc_monitoring();
$enabledDepts = get_enabled_departments();
$orgName      = get_org_name();

$wasteLabels = [
    '20'   => '20 % и менее',
    '30'   => '30 %',
    '40'   => '40 %',
    '50'   => '50 % и более',
    'none' => 'Не ведётся',
];

// Возвращает true если хотя бы одно значение в массиве непустое
function any(...$vals): bool {
    foreach ($vals as $v) {
        if (is_string($v) && trim($v) !== '') return true;
    }
    return false;
}

// Определяет тип ресурса по URL
function res_type(string $url): string {
    if (preg_match('/\.pdf$/i', $url)) return 'pdf';
    if (preg_match('/\.(jpe?g|png|webp|gif)$/i', $url)) return 'image';
    return 'link';
}

// Рендерит ссылку/превью/кнопку для ресурса
function render_resource(string $url, string $label, bool $download = false): void {
    if ($url === '') return;
    $type = res_type($url);
    $esc  = htmlspecialchars($url);
    if ($type === 'image') {
        echo '<a href="' . $esc . '" target="_blank" class="res-photo">';
        echo '<img src="' . $esc . '" alt="' . htmlspecialchars($label) . '"></a>';
    } elseif ($type === 'pdf') {
        $dl = $download ? ' download' : ' target="_blank"';
        echo '<a href="' . $esc . '" class="res-btn"' . $dl . '>';
        echo '&#128196; ' . htmlspecialchars($label) . '</a>';
    } else {
        echo '<a href="' . $esc . '" target="_blank" class="res-link">';
        echo htmlspecialchars($label) . '</a>';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Общественный контроль питания<?= $orgName ? ' | ' . htmlspecialchars($orgName) : '' ?></title>
    <link rel="stylesheet" href="menu/assets/style.css">
    <style>
        .oc-list {
            list-style: none;
            padding: 0;
        }
        .oc-section {
            border: 1px solid var(--border-light);
            border-left: 4px solid var(--orange);
            background: #fff;
            margin-bottom: 16px;
            padding: 16px 20px;
        }
        .oc-section-title {
            font-size: 0.95rem;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: var(--black);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .oc-section-num {
            flex-shrink: 0;
            background: var(--orange);
            color: #fff;
            font-size: 0.78rem;
            font-weight: bold;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            line-height: 24px;
            text-align: center;
        }
        .oc-row {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        .oc-row:last-child { margin-bottom: 0; }
        .oc-row-label {
            flex: 0 0 220px;
            color: var(--muted);
            font-size: 0.82rem;
            padding-top: 2px;
        }
        .oc-row-value { flex: 1; min-width: 0; }
        /* Ресурсы */
        .res-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: transparent;
            border: 2px solid var(--orange);
            color: var(--orange);
            padding: 5px 14px;
            border-radius: var(--radius);
            font-family: Georgia, serif;
            font-size: 0.82rem;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            transition: background .12s, color .12s;
        }
        .res-btn:hover { background: var(--orange); color: #fff; }
        .res-link {
            color: var(--orange);
            text-decoration: none;
            font-size: 0.88rem;
            word-break: break-all;
        }
        .res-link:hover { text-decoration: underline; }
        .res-photo img {
            display: block;
            max-width: 300px;
            max-height: 200px;
            border: 1px solid var(--border-light);
            border-radius: var(--radius);
            margin-top: 4px;
            transition: opacity .12s;
        }
        .res-photo img:hover { opacity: 0.85; }
        /* Диеты */
        .diet-table {
            border-collapse: collapse;
            font-size: 0.88rem;
            width: 100%;
        }
        .diet-table td {
            border: 1px solid var(--border-light);
            padding: 5px 10px;
            vertical-align: middle;
        }
        .diet-table td:first-child { color: var(--muted); width: 40%; }
        /* Пищевые отходы */
        .waste-badge {
            display: inline-block;
            background: var(--bg-panel);
            border: 1px solid var(--border-light);
            border-radius: var(--radius);
            padding: 4px 14px;
            font-size: 0.9rem;
        }
        /* Контакты горячей линии */
        .hotline-val {
            font-size: 0.95rem;
            font-weight: bold;
        }
        /* Дата документа */
        .doc-meta {
            font-size: 0.82rem;
            color: var(--muted);
            margin-bottom: 20px;
        }
        @media (max-width: 600px) {
            .oc-row { flex-direction: column; gap: 4px; }
            .oc-row-label { flex: none; }
            .res-photo img { max-width: 100%; }
        }
    </style>
</head>
<body>
<header>
    <div class="logo"><?= htmlspecialchars($orgName ?: 'Организация питания') ?></div>
    <nav>
        <a href="index.php">Календарь</a>
        <div class="nav-dropdown" tabindex="0">
            <a href="menu/menu.php">Типовое меню</a>
            <div class="nav-dropdown-menu">
                <?php foreach ($enabledDepts as $dept): ?>
                <a href="menu/menu.php?type=<?= htmlspecialchars($dept['code']) ?>">
                    <?= htmlspecialchars($dept['label']) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <a href="oc.php" class="active">Общ. контроль</a>
    </nav>
</header>

<div class="container">
    <div class="page-title">Общественный контроль питания</div>

    <?php if ($oc['school_name'] || $oc['report_date']): ?>
    <div class="doc-meta">
        <?php if ($oc['school_name']): ?>
            <?= htmlspecialchars($oc['school_name']) ?>
        <?php endif; ?>
        <?php if ($oc['report_date']): ?>
            &nbsp;—&nbsp; <?= date('d.m.Y', strtotime($oc['report_date'])) ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Раздел 1 -->
    <?php if (any($oc['s1_url'])): ?>
    <div class="oc-section">
        <div class="oc-section-title">
            <span class="oc-section-num">1</span>
            Положение и приказ о создании комиссии по контролю качества питания
        </div>
        <?php render_resource($oc['s1_url'], 'Открыть документ', true) ?>
    </div>
    <?php endif; ?>

    <!-- Раздел 2 -->
    <?php if (any($oc['s2_hotline'], $oc['s2_chat_url'], $oc['s2_forum_url'])): ?>
    <div class="oc-section">
        <div class="oc-section-title">
            <span class="oc-section-num">2</span>
            Формы интерактивного взаимодействия с родителями
        </div>
        <?php if ($oc['s2_hotline']): ?>
        <div class="oc-row">
            <div class="oc-row-label">«Горячая линия»</div>
            <div class="oc-row-value hotline-val"><?= htmlspecialchars($oc['s2_hotline']) ?></div>
        </div>
        <?php endif; ?>
        <?php if ($oc['s2_chat_url']): ?>
        <div class="oc-row">
            <div class="oc-row-label">Чат</div>
            <div class="oc-row-value"><?php render_resource($oc['s2_chat_url'], $oc['s2_chat_url']) ?></div>
        </div>
        <?php endif; ?>
        <?php if ($oc['s2_forum_url']): ?>
        <div class="oc-row">
            <div class="oc-row-label">Форум / обратная связь</div>
            <div class="oc-row-value"><?php render_resource($oc['s2_forum_url'], $oc['s2_forum_url']) ?></div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Раздел 3 -->
    <?php
    $diets = [];
    for ($i = 1; $i <= 4; $i++) {
        $t = $oc["s3_diet{$i}_type"];
        $u = $oc["s3_diet{$i}_url"];
        if ($t !== '' || $u !== '') $diets[] = ['type' => $t, 'url' => $u];
    }
    if ($diets):
    ?>
    <div class="oc-section">
        <div class="oc-section-title">
            <span class="oc-section-num">3</span>
            Лечебные / диетические меню
        </div>
        <table class="diet-table">
            <?php foreach ($diets as $d): ?>
            <tr>
                <td><?= htmlspecialchars($d['type'] ?: '—') ?></td>
                <td>
                    <?php if ($d['url']): ?>
                        <?php render_resource($d['url'], 'Открыть меню', true) ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>

    <!-- Раздел 4 -->
    <?php if (any($oc['s4_survey_url'], $oc['s4_results_url'])): ?>
    <div class="oc-section">
        <div class="oc-section-title">
            <span class="oc-section-num">4</span>
            Анкетирование обучающихся и родителей
        </div>
        <?php if ($oc['s4_survey_url']): ?>
        <div class="oc-row">
            <div class="oc-row-label">Форма анкеты</div>
            <div class="oc-row-value"><?php render_resource($oc['s4_survey_url'], 'Перейти к анкете') ?></div>
        </div>
        <?php endif; ?>
        <?php if ($oc['s4_results_url']): ?>
        <div class="oc-row">
            <div class="oc-row-label">Результаты анкетирования</div>
            <div class="oc-row-value"><?php render_resource($oc['s4_results_url'], 'Открыть результаты', true) ?></div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Раздел 5 -->
    <?php if (any($oc['s5_page_url'], $oc['s5_materials_url'])): ?>
    <div class="oc-section">
        <div class="oc-section-title">
            <span class="oc-section-num">5</span>
            Информация о здоровом питании для родителей
        </div>
        <?php if ($oc['s5_page_url']): ?>
        <div class="oc-row">
            <div class="oc-row-label">Страница на сайте</div>
            <div class="oc-row-value"><?php render_resource($oc['s5_page_url'], 'Открыть страницу') ?></div>
        </div>
        <?php endif; ?>
        <?php if ($oc['s5_materials_url']): ?>
        <div class="oc-row">
            <div class="oc-row-label">Информационные материалы</div>
            <div class="oc-row-value"><?php render_resource($oc['s5_materials_url'], 'Открыть материалы', true) ?></div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Раздел 6 -->
    <?php if (any($oc['s6_acts_url'], $oc['s6_photos_url'])): ?>
    <div class="oc-section">
        <div class="oc-section-title">
            <span class="oc-section-num">6</span>
            Результаты контрольных мероприятий с участием родителей
        </div>
        <?php if ($oc['s6_acts_url']): ?>
        <div class="oc-row">
            <div class="oc-row-label">Акты / протоколы проверок</div>
            <div class="oc-row-value"><?php render_resource($oc['s6_acts_url'], 'Скачать акт', true) ?></div>
        </div>
        <?php endif; ?>
        <?php if ($oc['s6_photos_url']): ?>
        <div class="oc-row">
            <div class="oc-row-label">Фото членов комиссии</div>
            <div class="oc-row-value"><?php render_resource($oc['s6_photos_url'], 'Фото') ?></div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Раздел 7 -->
    <?php if ($oc['s7_waste_level'] && $oc['s7_waste_level'] !== 'none'): ?>
    <div class="oc-section">
        <div class="oc-section-title">
            <span class="oc-section-num">7</span>
            Оценка пищевых отходов
        </div>
        <span class="waste-badge"><?= htmlspecialchars($wasteLabels[$oc['s7_waste_level']] ?? '—') ?></span>
    </div>
    <?php endif; ?>

    <?php if (!any(
        $oc['s1_url'], $oc['s2_hotline'], $oc['s2_chat_url'], $oc['s2_forum_url'],
        $oc['s3_diet1_type'], $oc['s3_diet1_url'], $oc['s4_survey_url'], $oc['s4_results_url'],
        $oc['s5_page_url'], $oc['s5_materials_url'], $oc['s6_acts_url'], $oc['s6_photos_url']
    )): ?>
    <div class="notice">Сведения об общественном контроле питания пока не заполнены.</div>
    <?php endif; ?>
</div>
<footer>
    <a href="https://github.com/igor-blag/web-food" target="_blank" rel="noopener">github.com/igor-blag/web-food</a>
</footer>
</body>
</html>
