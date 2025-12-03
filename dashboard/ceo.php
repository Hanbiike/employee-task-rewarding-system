<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Project.php';
require_once __DIR__ . '/../classes/Task.php';
require_once __DIR__ . '/../classes/KPI.php';
require_once __DIR__ . '/../classes/Reward.php';

// Проверяем авторизацию и права доступа
if (!User::isAuthenticated() || !User::hasRole('CEO')) {
    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

$user = User::getCurrentUser();
$project = new Project();
$task = new Task();
$kpi = new KPI();
$reward = new Reward();

// Получаем текущий период (месяц)
$currentPeriod = date('Y-m-01');

// Статистика
$allProjects = $project->getAll();
$kpiData = $kpi->getAllEmployeesKPI($currentPeriod);
$rewardStats = $reward->getStatistics($currentPeriod);

// Подсчёт статистики проектов
$projectStats = [
    'total' => count($allProjects),
    'in_progress' => 0,
    'completed' => 0,
    'not_started' => 0
];

foreach ($allProjects as $proj) {
    if ($proj['status'] === 'In Progress') $projectStats['in_progress']++;
    if ($proj['status'] === 'Completed') $projectStats['completed']++;
    if ($proj['status'] === 'Not Started') $projectStats['not_started']++;
}

// Топ сотрудников
$topEmployees = $kpi->getTopEmployees($currentPeriod, 5);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEO Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><?php echo APP_NAME; ?></h2>
                <p>CEO Dashboard</p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="ceo.php" class="active"><span class="icon">📊</span> Главная</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/projects/list.php"><span class="icon">📁</span> Проекты</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/tasks/list.php"><span class="icon">✓</span> Задачи</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/employees/list.php"><span class="icon">👥</span> Сотрудники</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/managers/list.php"><span class="icon">👔</span> Менеджеры</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/departments/list.php"><span class="icon">🏢</span> Отделы</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/kpi/list.php"><span class="icon">📈</span> KPI</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/kpi/settings.php"><span class="icon">🔧</span> Настройки KPI</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/rewards/list.php"><span class="icon">💰</span> Вознаграждения</a></li>
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
                        <div class="role">CEO</div>
                    </div>
                </div>
            </div>

            <div class="content">
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card primary">
                        <div class="icon">📁</div>
                        <div class="value"><?php echo $projectStats['total']; ?></div>
                        <div class="label">Всего проектов</div>
                    </div>
                    <div class="stat-card success">
                        <div class="icon">✓</div>
                        <div class="value"><?php echo $projectStats['in_progress']; ?></div>
                        <div class="label">В работе</div>
                    </div>
                    <div class="stat-card warning">
                        <div class="icon">👥</div>
                        <div class="value"><?php echo count($kpiData); ?></div>
                        <div class="label">Сотрудников</div>
                    </div>
                    <div class="stat-card danger">
                        <div class="icon">💰</div>
                        <div class="value"><?php echo number_format($rewardStats['total_rewards'] ?? 0, 0, ',', ' '); ?> ₽</div>
                        <div class="label">Вознаграждения</div>
                    </div>
                </div>

                <!-- Recent Projects -->
                <div class="card">
                    <div class="card-header">
                        <h3>Последние проекты</h3>
                        <a href="<?php echo APP_URL; ?>/modules/projects/create.php" class="btn btn-primary btn-sm">+ Создать проект</a>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Название</th>
                                        <th>Отдел</th>
                                        <th>Статус</th>
                                        <th>Важность</th>
                                        <th>Срок</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $recentProjects = array_slice($allProjects, 0, 5);
                                    foreach ($recentProjects as $proj): 
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($proj['name']); ?></td>
                                        <td>
                                            <?php if (!empty($proj['department_names'])): ?>
                                                <?php 
                                                $deptNames = explode(', ', $proj['department_names']);
                                                foreach ($deptNames as $index => $deptName): 
                                                ?>
                                                    <span class="badge badge-secondary" style="margin: 2px;">
                                                        <?php echo htmlspecialchars($deptName); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span style="color: var(--text-light);">-</span>
                                            <?php endif; ?>
                                        </td>
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

                <!-- Top Employees -->
                <div class="card">
                    <div class="card-header">
                        <h3>Топ сотрудников по KPI (<?php echo date('m.Y', strtotime($currentPeriod)); ?>)</h3>
                        <a href="<?php echo APP_URL; ?>/modules/kpi/employees.php" class="btn btn-primary btn-sm">Все KPI</a>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Сотрудник</th>
                                        <th>Отдел</th>
                                        <th>KPI</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $rank = 1;
                                    foreach ($topEmployees as $emp): 
                                    ?>
                                    <tr>
                                        <td><?php echo $rank++; ?></td>
                                        <td><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($emp['department_name']); ?></td>
                                        <td>
                                            <strong><?php echo number_format($emp['total_kpi'], 2); ?></strong>
                                        </td>
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
