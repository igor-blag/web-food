<header>
    <span class="logo">&#x2756; Меню школы</span>
    <nav>
        <a href="calendar.php">Календарь</a>
        <a href="templates.php">Шаблоны</a>
        <a href="bulk-import.php">Импорт</a>
        <a href="settings.php">Настройки</a>
        <span class="user"><?= htmlspecialchars(current_user() ?? '') ?></span>
        <a href="logout.php">Выйти</a>
    </nav>
</header>
