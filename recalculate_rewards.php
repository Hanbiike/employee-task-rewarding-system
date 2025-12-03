<?php
/**
 * Скрипт для пересчета всех вознаграждений с учетом исправленной логики
 * Используется после исправления расчета квартальных и годовых премий
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/KPI.php';
require_once __DIR__ . '/classes/Reward.php';

$db = Database::getInstance();
$reward = new Reward();

// Проверяем, нужно ли выполнить пересчет
$confirm = $_GET['confirm'] ?? null;

if ($confirm !== 'yes') {
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Пересчет вознаграждений - <?php echo APP_NAME; ?></title>
        <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    </head>
    <body>
        <div style="max-width: 800px; margin: 50px auto; padding: 20px;">
            <h1>⚠️ Пересчет вознаграждений</h1>
            
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-body">
                    <h3>Что будет сделано?</h3>
                    <p>Этот скрипт пересчитает все существующие вознаграждения сотрудников и менеджеров с учетом исправленной логики расчета квартальных и годовых премий.</p>
                    
                    <h4>Изменения:</h4>
                    <ul>
                        <li><strong>Месячные премии</strong> - останутся без изменений</li>
                        <li><strong>Квартальные премии</strong> - будут пересчитаны с учетом задач за весь квартал (3 месяца)</li>
                        <li><strong>Годовые премии</strong> - будут пересчитаны с учетом задач за весь год (12 месяцев)</li>
                    </ul>
                    
                    <p style="color: red; font-weight: bold;">⚠️ ВНИМАНИЕ: Это действие перезапишет все существующие данные вознаграждений!</p>
                    
                    <p>Перед запуском рекомендуется сделать резервную копию базы данных.</p>
                </div>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: center;">
                <a href="?confirm=yes" class="btn btn-primary" style="background-color: #e74c3c; padding: 15px 30px; font-size: 16px; text-decoration: none; color: white; border-radius: 4px;">
                    🚀 Начать пересчет
                </a>
                <a href="dashboard/manager.php" class="btn btn-secondary" style="background-color: #95a5a6; padding: 15px 30px; font-size: 16px; text-decoration: none; color: white; border-radius: 4px;">
                    ← Отмена
                </a>
            </div>
            
            <div class="card" style="margin-top: 20px; background-color: #f8f9fa;">
                <div class="card-body">
                    <h4>📖 Подробности</h4>
                    <p>Для получения подробной информации об изменениях, см. файл <code>FIX_REWARDS_CALCULATION.md</code></p>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

echo "<!DOCTYPE html>";
echo "<html lang='ru'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>Пересчет вознаграждений - " . APP_NAME . "</title>";
echo "<link rel='stylesheet' href='" . APP_URL . "/assets/css/style.css'>";
echo "</head>";
echo "<body>";
echo "<div style='max-width: 1200px; margin: 50px auto; padding: 20px;'>";
echo "<h1>Пересчет вознаграждений</h1>";
echo "<p>Начинаем пересчет всех существующих вознаграждений...</p>";

// Получаем все уникальные записи вознаграждений
$rewardsData = $db->fetchAll(
    "SELECT DISTINCT employee_id, period, period_type FROM rewards ORDER BY period, employee_id"
);

$totalProcessed = 0;
$errors = [];

echo "<h2>Вознаграждения сотрудников:</h2>";
echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr><th>ID сотрудника</th><th>Период</th><th>Тип</th><th>Статус</th></tr>";

foreach ($rewardsData as $data) {
    $employeeId = $data['employee_id'];
    $period = $data['period'];
    $periodType = $data['period_type'];
    
    try {
        $reward->calculateAndSave($employeeId, $period, $periodType);
        echo "<tr><td>$employeeId</td><td>$period</td><td>$periodType</td><td style='color: green;'>✓ Пересчитано</td></tr>";
        $totalProcessed++;
    } catch (Exception $e) {
        $error = "Сотрудник $employeeId, период $period ($periodType): " . $e->getMessage();
        $errors[] = $error;
        echo "<tr><td>$employeeId</td><td>$period</td><td>$periodType</td><td style='color: red;'>✗ " . htmlspecialchars($e->getMessage()) . "</td></tr>";
    }
}

echo "</table>";

// Пересчет вознаграждений менеджеров
$managerRewardsData = $db->fetchAll(
    "SELECT DISTINCT manager_id, period, period_type FROM manager_rewards ORDER BY period, manager_id"
);

echo "<h2>Вознаграждения менеджеров:</h2>";
echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr><th>ID менеджера</th><th>Период</th><th>Тип</th><th>Статус</th></tr>";

foreach ($managerRewardsData as $data) {
    $managerId = $data['manager_id'];
    $period = $data['period'];
    $periodType = $data['period_type'];
    
    try {
        $reward->calculateAndSaveManagerReward($managerId, $period, $periodType);
        echo "<tr><td>$managerId</td><td>$period</td><td>$periodType</td><td style='color: green;'>✓ Пересчитано</td></tr>";
        $totalProcessed++;
    } catch (Exception $e) {
        $error = "Менеджер $managerId, период $period ($periodType): " . $e->getMessage();
        $errors[] = $error;
        echo "<tr><td>$managerId</td><td>$period</td><td>$periodType</td><td style='color: red;'>✗ " . htmlspecialchars($e->getMessage()) . "</td></tr>";
    }
}

echo "</table>";

// Итоги
echo "<h2>Итоги пересчета:</h2>";
echo "<p><strong>Всего обработано:</strong> $totalProcessed записей</p>";

if (!empty($errors)) {
    echo "<p style='color: red;'><strong>Ошибки:</strong> " . count($errors) . "</p>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li>" . htmlspecialchars($error) . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color: green;'><strong>Все вознаграждения успешно пересчитаны!</strong></p>";
}

echo "<p><a href='dashboard/manager.php' style='padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 4px; display: inline-block; margin-top: 20px;'>← Вернуться в панель управления</a></p>";
echo "</div>";
echo "</body>";
echo "</html>";
