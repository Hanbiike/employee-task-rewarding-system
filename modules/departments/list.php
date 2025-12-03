<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';

if (!User::isAuthenticated() || !User::hasRole('CEO')) {
    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

$user = User::getCurrentUser();
$db = Database::getInstance();

// Получаем все отделы со статистикой
$departments = $db->fetchAll("
    SELECT d.*,
           (SELECT COUNT(*) FROM managers WHERE department_id = d.id) as managers_count,
           (SELECT COUNT(*) FROM employees WHERE department_id = d.id) as employees_count,
           (SELECT COUNT(*) FROM project_departments pd 
            JOIN projects p ON pd.project_id = p.id 
            WHERE pd.department_id = d.id) as projects_count
    FROM departments d
    ORDER BY d.name
");

$success = $_GET['success'] ?? null;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Отделы - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
</head>
<body>
    <div class="dashboard">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><?php echo APP_NAME; ?></h2>
                <p>CEO Dashboard</p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="<?php echo APP_URL; ?>/dashboard/ceo.php"><span class="icon">📊</span> Главная</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/projects/list.php"><span class="icon">📁</span> Проекты</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/tasks/list.php"><span class="icon">✓</span> Задачи</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/employees/list.php"><span class="icon">👥</span> Сотрудники</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/managers/list.php"><span class="icon">👔</span> Менеджеры</a></li>
                <li><a href="list.php" class="active"><span class="icon">🏢</span> Отделы</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/kpi/list.php"><span class="icon">📈</span> KPI</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/rewards/list.php"><span class="icon">💰</span> Вознаграждения</a></li>
                <li><a href="<?php echo APP_URL; ?>/auth/logout.php"><span class="icon">🚪</span> Выход</a></li>
            </ul>
        </aside>

        <div class="main-content">
            <div class="topbar">
                <h1>Отделы</h1>
                <div class="user-menu">
                    <div class="user-info">
                        <div class="name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <div class="role">CEO</div>
                    </div>
                </div>
            </div>

            <div class="content">
                <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php 
                    if ($success === 'created') echo 'Отдел успешно создан!';
                    elseif ($success === 'updated') echo 'Отдел успешно обновлён!';
                    elseif ($success === 'deleted') echo 'Отдел успешно удалён!';
                    ?>
                </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3>Список отделов (<?php echo count($departments); ?>)</h3>
                        <div class="flex gap-2">
                            <a href="<?php echo APP_URL; ?>/modules/export/export.php?type=departments" class="btn btn-primary" style="background: #27ae60;">
                                📥 Экспорт в Excel
                            </a>
                            <a href="create.php" class="btn btn-primary">+ Создать отдел</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Название</th>
                                        <th>Менеджеры</th>
                                        <th>Сотрудники</th>
                                        <th>Проекты</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($departments as $dept): ?>
                                    <tr>
                                        <td><?php echo $dept['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($dept['name']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge badge-info">
                                                <?php echo $dept['managers_count']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-primary">
                                                <?php echo $dept['employees_count']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-secondary">
                                                <?php echo $dept['projects_count']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="view.php?id=<?php echo $dept['id']; ?>" class="btn btn-sm btn-primary">Просмотр</a>
                                            <a href="edit.php?id=<?php echo $dept['id']; ?>" class="btn btn-sm btn-warning">Изменить</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($departments)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">Отделы не найдены</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
