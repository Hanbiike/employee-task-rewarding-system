<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Task.php';
require_once __DIR__ . '/../classes/KPI.php';
require_once __DIR__ . '/../classes/Reward.php';

// Проверяем авторизацию
if (!User::isAuthenticated()) {
    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

$user = User::getCurrentUser();
$userId = User::getCurrentUserId();
$task = new Task();
$kpi = new KPI();
$reward = new Reward();

// Получаем задачи сотрудника
$myTasks = $task->getByEmployee($userId);

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

// KPI и вознаграждения
$currentPeriod = date('Y-m-01');
$myKPI = $kpi->calculateTotalKPI($userId, $currentPeriod);
$myReward = $reward->getEmployeeReward($userId, $currentPeriod, 'monthly');
$kpiHistory = $kpi->getEmployeeHistory($userId, 6);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><?php echo APP_NAME; ?></h2>
                <p>Employee Dashboard</p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="employee.php" class="active"><span class="icon">📊</span> Главная</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/tasks/my_tasks.php"><span class="icon">✓</span> Мои задачи</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/kpi/my_kpi.php"><span class="icon">📈</span> Мои KPI</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/rewards/my_rewards.php"><span class="icon">💰</span> Вознаграждения</a></li>
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
                        <div class="role">Сотрудник - <?php echo htmlspecialchars($user['department_name']); ?></div>
                    </div>
                </div>
            </div>

            <div class="content">
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card primary">
                        <div class="icon">✓</div>
                        <div class="value"><?php echo $taskStats['total']; ?></div>
                        <div class="label">Всего задач</div>
                    </div>
                    <div class="stat-card warning">
                        <div class="icon">⏳</div>
                        <div class="value"><?php echo $taskStats['in_progress']; ?></div>
                        <div class="label">В работе</div>
                    </div>
                    <div class="stat-card success">
                        <div class="icon">📈</div>
                        <div class="value"><?php echo number_format($myKPI, 2); ?></div>
                        <div class="label">Мой KPI</div>
                    </div>
                    <div class="stat-card danger">
                        <div class="icon">💰</div>
                        <div class="value"><?php echo ($myReward && isset($myReward['total_amount'])) ? number_format($myReward['total_amount'], 0, ',', ' ') . ' ₽' : '—'; ?></div>
                        <div class="label">Вознаграждение</div>
                    </div>
                </div>

                <!-- My Tasks -->
                <div class="card">
                    <div class="card-header">
                        <h3>Мои задачи</h3>
                        <a href="<?php echo APP_URL; ?>/modules/tasks/my_tasks.php" class="btn btn-primary btn-sm">Все задачи</a>
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
                                        <th>Действия</th>
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
                                        <td>
                                            <a href="<?php echo APP_URL; ?>/modules/tasks/view.php?id=<?php echo $t['id']; ?>" 
                                               class="btn btn-sm btn-primary">Просмотр</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($myTasks)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">Задачи не назначены</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- KPI Information -->
                <div class="card">
                    <div class="card-header">
                        <h3>Мои KPI за <?php echo date('m.Y', strtotime($currentPeriod)); ?></h3>
                        <a href="<?php echo APP_URL; ?>/modules/kpi/my_kpi.php" class="btn btn-primary btn-sm">Подробнее</a>
                    </div>
                    <div class="card-body">
                        <?php 
                        $currentKPIValues = $kpi->getEmployeeValues($userId, $currentPeriod);
                        if (!empty($currentKPIValues)): 
                        ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Показатель</th>
                                        <th>Вес</th>
                                        <th>Цель</th>
                                        <th>Факт</th>
                                        <th>Выполнение</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($currentKPIValues as $kpiValue): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($kpiValue['name']); ?></td>
                                        <td><?php echo $kpiValue['weight']; ?></td>
                                        <td><?php echo $kpiValue['target_value'] ? $kpiValue['target_value'] . ' ' . ($kpiValue['measurement_unit'] ?? $kpiValue['unit'] ?? '%') : '-'; ?></td>
                                        <td><?php echo $kpiValue['value'] . ' ' . ($kpiValue['measurement_unit'] ?? $kpiValue['unit'] ?? '%'); ?></td>
                                        <td>
                                            <?php 
                                            if ($kpiValue['target_value'] > 0) {
                                                $percentage = ($kpiValue['value'] / $kpiValue['target_value']) * 100;
                                                $badgeClass = $percentage >= 90 ? 'success' : ($percentage >= 70 ? 'warning' : 'danger');
                                                echo '<span class="badge badge-' . $badgeClass . '">' . number_format($percentage, 1) . '%</span>';
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <p class="text-center">KPI за текущий период не установлены</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
