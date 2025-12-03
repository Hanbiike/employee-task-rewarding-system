<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';

if (!User::isAuthenticated() || !User::hasRole('CEO')) {
    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

$managerId = $_GET['id'] ?? null;
if (!$managerId) {
    header('Location: list.php');
    exit;
}

$user = User::getCurrentUser();
$db = Database::getInstance();

$manager = $db->fetchOne("SELECT m.*, d.name as department_name FROM managers m JOIN departments d ON m.department_id = d.id WHERE m.id = ?", [$managerId]);
if (!$manager) {
    header('Location: list.php');
    exit;
}

// Получаем проекты менеджера
$projects = $db->fetchAll("
    SELECT p.* 
    FROM projects p
    JOIN manager_projects mp ON p.id = mp.project_id
    WHERE mp.manager_id = ?
    ORDER BY p.created_at DESC
", [$managerId]);

// Получаем задачи менеджера
$tasks = $db->fetchAll("SELECT * FROM tasks WHERE created_by_manager_id = ? ORDER BY created_at DESC", [$managerId]);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($manager['first_name'] . ' ' . $manager['last_name']); ?> - <?php echo APP_NAME; ?></title>
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
                <li><a href="list.php" class="active"><span class="icon">👔</span> Менеджеры</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/departments/list.php"><span class="icon">🏢</span> Отделы</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/kpi/list.php"><span class="icon">📈</span> KPI</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/rewards/list.php"><span class="icon">💰</span> Вознаграждения</a></li>
                <li><a href="<?php echo APP_URL; ?>/auth/logout.php"><span class="icon">🚪</span> Выход</a></li>
            </ul>
        </aside>

        <div class="main-content">
            <div class="topbar">
                <h1><?php echo htmlspecialchars($manager['first_name'] . ' ' . $manager['last_name']); ?></h1>
                <div class="user-menu">
                    <div class="user-info">
                        <div class="name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <div class="role">CEO</div>
                    </div>
                </div>
            </div>

            <div class="content">
                <!-- Статистика -->
                <div class="stats-grid">
                    <div class="stat-card secondary">
                        <div class="icon">📁</div>
                        <div class="value"><?php echo count($projects); ?></div>
                        <div class="label">Проекты</div>
                    </div>
                    <div class="stat-card primary">
                        <div class="icon">✓</div>
                        <div class="value"><?php echo count($tasks); ?></div>
                        <div class="label">Задачи</div>
                    </div>
                </div>

                <!-- Информация о менеджере -->
                <div class="card">
                    <div class="card-header">
                        <h3>Информация</h3>
                        <a href="edit.php?id=<?php echo $managerId; ?>" class="btn btn-warning btn-sm">Редактировать</a>
                    </div>
                    <div class="card-body">
                        <div class="form-grid">
                            <div>
                                <strong>Email:</strong>
                                <p><?php echo htmlspecialchars($manager['email']); ?></p>
                            </div>
                            <div>
                                <strong>Телефон:</strong>
                                <p><?php echo htmlspecialchars($manager['phone_number']); ?></p>
                            </div>
                            <div>
                                <strong>Отдел:</strong>
                                <p><span class="badge badge-secondary"><?php echo htmlspecialchars($manager['department_name']); ?></span></p>
                            </div>
                            <div>
                                <strong>Должность:</strong>
                                <p><span class="badge badge-<?php echo $manager['position'] === 'CEO' ? 'danger' : 'info'; ?>"><?php echo $manager['position']; ?></span></p>
                            </div>
                            <div>
                                <strong>Дата приёма:</strong>
                                <p><?php echo date('d.m.Y', strtotime($manager['hire_date'])); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-3">
                    <a href="list.php" class="btn btn-secondary">← Назад к списку</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
