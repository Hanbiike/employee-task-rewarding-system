<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Project.php';
require_once __DIR__ . '/../classes/Task.php';
require_once __DIR__ . '/../classes/KPI.php';

// Проверяем авторизацию и права доступа
if (!User::isAuthenticated() || !User::hasRole('Manager')) {
    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

$user = User::getCurrentUser();
$userId = User::getCurrentUserId();
$project = new Project();
$task = new Task();
$kpi = new KPI();

// Получаем проекты менеджера
$myProjects = $project->getByManager($userId);
$myTasks = $task->getByManager($userId);
$departmentProjects = $project->getByDepartment($user['department_id']);

// Статистика
$taskStats = [
    'total' => count($myTasks),
    'in_progress' => 0,
    'completed' => 0,
    'not_started' => 0
];

foreach ($myTasks as $t) {
    if ($t['status'] === 'In Progress') $taskStats['in_progress']++;
    if ($t['status'] === 'Completed') $taskStats['completed']++;
    if ($t['status'] === 'Not Started') $taskStats['not_started']++;
}

$currentPeriod = date('Y-m-01');
$departmentEmployees = $kpi->getAllEmployeesKPI($currentPeriod, $user['department_id']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><?php echo APP_NAME; ?></h2>
                <p>Manager Dashboard</p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="manager.php" class="active"><span class="icon">📊</span> Главная</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/projects/list.php"><span class="icon">📁</span> Проекты</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/tasks/list.php"><span class="icon">✓</span> Задачи</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/kpi/employees.php"><span class="icon">📈</span> KPI команды</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/employees/list.php"><span class="icon">👥</span> Сотрудники</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/rewards/manager_rewards.php"><span class="icon">💰</span> Мои вознаграждения</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/rewards/list.php"><span class="icon">💵</span> Вознаграждения отдела</a></li>
                <li><a href="<?php echo APP_URL; ?>/auth/logout.php"><span class="icon">🚪</span> Выход</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <div class="main-content">
            <div class="topbar">
                <h1>Dashboard</h1>
                <div class="user-menu">
                    <div class="user-info">
                        <div class="name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <div class="role">Manager - <?php echo htmlspecialchars($user['department_name']); ?></div>
                    </div>
                </div>
            </div>

            <div class="content">
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card primary">
                        <div class="icon">📁</div>
                        <div class="value"><?php echo count($myProjects); ?></div>
                        <div class="label">Мои проекты</div>
                    </div>
                    <div class="stat-card success">
                        <div class="icon">✓</div>
                        <div class="value"><?php echo $taskStats['total']; ?></div>
                        <div class="label">Всего задач</div>
                    </div>
                    <div class="stat-card warning">
                        <div class="icon">⏳</div>
                        <div class="value"><?php echo $taskStats['in_progress']; ?></div>
                        <div class="label">В работе</div>
                    </div>
                    <div class="stat-card danger">
                        <div class="icon">👥</div>
                        <div class="value"><?php echo count($departmentEmployees); ?></div>
                        <div class="label">Сотрудников</div>
                    </div>
                </div>

                <!-- My Projects -->
                <div class="card">
                    <div class="card-header">
                        <h3>Мои проекты</h3>
                        <a href="<?php echo APP_URL; ?>/modules/tasks/create.php" class="btn btn-primary btn-sm">+ Создать задачу</a>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Название</th>
                                        <th>Статус</th>
                                        <th>Важность</th>
                                        <th>Срок</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $recentProjects = array_slice($myProjects, 0, 5);
                                    foreach ($recentProjects as $proj): 
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($proj['name']); ?></td>
                                        <td>
                                            <?php 
                                            $statusClass = 'secondary';
                                            if ($proj['status'] === 'Completed') $statusClass = 'success';
                                            elseif ($proj['status'] === 'In Progress') $statusClass = 'info';
                                            elseif ($proj['status'] === 'Frozen') $statusClass = 'warning';
                                            ?>
                                            <span class="badge badge-<?php echo $statusClass; ?>">
                                                <?php echo htmlspecialchars($proj['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $importanceClass = 'secondary';
                                            if ($proj['importance'] === 'Critical') $importanceClass = 'danger';
                                            elseif ($proj['importance'] === 'High') $importanceClass = 'warning';
                                            ?>
                                            <span class="badge badge-<?php echo $importanceClass; ?>">
                                                <?php echo htmlspecialchars($proj['importance']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $proj['deadline'] ? date('d.m.Y', strtotime($proj['deadline'])) : '-'; ?></td>
                                        <td>
                                            <a href="<?php echo APP_URL; ?>/modules/projects/view.php?id=<?php echo $proj['id']; ?>" 
                                               class="btn btn-sm btn-primary">Просмотр</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent Tasks -->
                <div class="card">
                    <div class="card-header">
                        <h3>Последние задачи</h3>
                        <a href="<?php echo APP_URL; ?>/modules/tasks/list.php" class="btn btn-primary btn-sm">Все задачи</a>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Название</th>
                                        <th>Проект</th>
                                        <th>Статус</th>
                                        <th>Важность</th>
                                        <th>Срок</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $recentTasks = array_slice($myTasks, 0, 5);
                                    foreach ($recentTasks as $t): 
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($t['name']); ?></td>
                                        <td><?php echo htmlspecialchars($t['project_name']); ?></td>
                                        <td>
                                            <?php 
                                            $statusClass = 'secondary';
                                            if ($t['status'] === 'Completed') $statusClass = 'success';
                                            elseif ($t['status'] === 'In Progress') $statusClass = 'info';
                                            ?>
                                            <span class="badge badge-<?php echo $statusClass; ?>">
                                                <?php echo htmlspecialchars($t['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $importanceClass = 'secondary';
                                            if ($t['importance'] === 'Critical') $importanceClass = 'danger';
                                            elseif ($t['importance'] === 'High') $importanceClass = 'warning';
                                            ?>
                                            <span class="badge badge-<?php echo $importanceClass; ?>">
                                                <?php echo htmlspecialchars($t['importance']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $t['deadline'] ? date('d.m.Y', strtotime($t['deadline'])) : '-'; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
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
