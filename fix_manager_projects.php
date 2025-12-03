<?php
/**
 * Скрипт для исправления связей менеджеров с проектами
 * Запустите этот файл один раз, чтобы добавить отсутствующие связи в таблицу manager_projects
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Database.php';

$db = Database::getInstance();

try {
    echo "<h2>Исправление связей менеджеров с проектами...</h2>";
    
    // Получаем все проекты
    $projects = $db->fetchAll("SELECT id, created_by_manager_id, name FROM projects ORDER BY id");
    
    $fixed = 0;
    $skipped = 0;
    
    foreach ($projects as $project) {
        // Проверяем, есть ли уже связь создателя проекта с этим проектом
        $exists = $db->fetchOne(
            "SELECT * FROM manager_projects WHERE manager_id = ? AND project_id = ?",
            [$project['created_by_manager_id'], $project['id']]
        );
        
        if (!$exists) {
            // Добавляем связь
            $db->insert(
                "INSERT INTO manager_projects (manager_id, project_id) VALUES (?, ?)",
                [$project['created_by_manager_id'], $project['id']]
            );
            echo "✓ Добавлена связь для проекта: {$project['name']} (ID: {$project['id']})<br>";
            $fixed++;
        } else {
            echo "- Связь уже существует для проекта: {$project['name']} (ID: {$project['id']})<br>";
            $skipped++;
        }
    }
    
    echo "<br><p><strong>Готово!</strong></p>";
    echo "<p>Исправлено: {$fixed}</p>";
    echo "<p>Пропущено (уже существовали): {$skipped}</p>";
    echo "<p>Всего проектов: " . count($projects) . "</p>";
    
    echo "<br><p><a href='dashboard/manager.php'>← Вернуться в дэшборд менеджера</a></p>";
    echo "<p><a href='modules/projects/list.php'>← Перейти к списку проектов</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Ошибка: " . $e->getMessage() . "</p>";
}
?>
