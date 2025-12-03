<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/KPI.php';

if (!User::isAuthenticated()) {
    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

$user = User::getCurrentUser();
$role = User::getCurrentRole();
$userId = User::getCurrentUserId();
$kpi = new KPI();
$db = Database::getInstance();

$employeeId = $_GET['employee_id'] ?? $userId;
$period = $_GET['period'] ?? date('Y-m');

// Проверка прав доступа
if ($role === 'Employee' && $employeeId != $userId) {
    header('Location: my_kpi.php');
    exit;
}

// Получаем данные сотрудника
$employee = $db->fetchOne(
    "SELECT e.*, d.name as department_name, 
     CONCAT(m.first_name, ' ', m.last_name) as manager_name
     FROM employees e
     JOIN departments d ON e.department_id = d.id
     LEFT JOIN managers m ON e.manager_id = m.id
     WHERE e.id = ?",
    [$employeeId]
);

if (!$employee) {
    header('Location: ' . ($role === 'Employee' ? 'my_kpi.php' : 'employees.php'));
    exit;
}

// Получаем KPI данные
$kpiValues = $kpi->getEmployeeValues($employeeId, $period);
$totalKPI = $kpi->calculateTotalKPI($employeeId, $period);

// История за последние 12 месяцев
$history = $kpi->getEmployeeHistory($employeeId, 12);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KPI <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?> - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
</head>
<body>
    <div class="dashboard">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><?php echo APP_NAME; ?></h2>
                <p><?php echo $role; ?> Dashboard</p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="<?php echo APP_URL; ?>/dashboard/<?php echo strtolower($role); ?>.php"><span class="icon">📊</span> Главная</a></li>
                <?php if ($role === 'Employee'): ?>
                <li><a href="<?php echo APP_URL; ?>/modules/tasks/my_tasks.php"><span class="icon">✓</span> Мои задачи</a></li>
                <li><a href="my_kpi.php" class="active"><span class="icon">📈</span> Мои KPI</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/rewards/my_rewards.php"><span class="icon">💰</span> Вознаграждения</a></li>
                <?php else: ?>
                <li><a href="<?php echo APP_URL; ?>/modules/projects/list.php"><span class="icon">📁</span> Проекты</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/tasks/list.php"><span class="icon">✓</span> Задачи</a></li>
                <?php if ($role === 'CEO'): ?>
                <li><a href="<?php echo APP_URL; ?>/modules/employees/list.php"><span class="icon">👥</span> Сотрудники</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/managers/list.php"><span class="icon">👔</span> Менеджеры</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/departments/list.php"><span class="icon">🏢</span> Отделы</a></li>
                <li><a href="list.php"><span class="icon">⚙️</span> Управление KPI</a></li>
                <li><a href="employees.php" class="active"><span class="icon">📈</span> KPI команды</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/rewards/list.php"><span class="icon">💰</span> Вознаграждения</a></li>
                <?php elseif ($role === 'Manager'): ?>
                <li><a href="<?php echo APP_URL; ?>/modules/employees/list.php"><span class="icon">👥</span> Сотрудники</a></li>
                <li><a href="employees.php" class="active"><span class="icon">📈</span> KPI команды</a></li>
                <?php endif; ?>
                <?php endif; ?>
                <li><a href="<?php echo APP_URL; ?>/auth/logout.php"><span class="icon">🚪</span> Выход</a></li>
            </ul>
        </aside>

        <div class="main-content">
            <div class="topbar">
                <h1>KPI сотрудника</h1>
                <div class="user-menu">
                    <div class="user-info">
                        <div class="name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <div class="role"><?php echo $role; ?></div>
                    </div>
                </div>
            </div>

            <div class="content">
                <!-- Employee Info -->
                <div class="card">
                    <div class="card-header">
                        <h3>Информация о сотруднике</h3>
                        <a href="<?php echo $role === 'Employee' ? 'my_kpi.php' : 'employees.php'; ?>" 
                           class="btn btn-secondary btn-sm">← Назад</a>
                    </div>
                    <div class="card-body">
                        <div class="form-grid">
                            <div>
                                <strong>ФИО:</strong>
                                <p><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></p>
                            </div>
                            <div>
                                <strong>Email:</strong>
                                <p><?php echo htmlspecialchars($employee['email']); ?></p>
                            </div>
                            <div>
                                <strong>Отдел:</strong>
                                <p><?php echo htmlspecialchars($employee['department_name']); ?></p>
                            </div>
                            <div>
                                <strong>Менеджер:</strong>
                                <p><?php echo $employee['manager_name'] ?: 'Не назначен'; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Period Selector -->
                <div class="card">
                    <div class="card-body">
                        <form method="GET" class="flex gap-2 items-center">
                            <input type="hidden" name="employee_id" value="<?php echo $employeeId; ?>">
                            <label for="period">Выберите период:</label>
                            <input type="month" name="period" id="period" value="<?php echo $period; ?>">
                            <button type="submit" class="btn btn-primary">Показать</button>
                        </form>
                    </div>
                </div>

                <!-- Total KPI -->
                <div class="card">
                    <div class="card-header">
                        <h3>Общий KPI за <?php echo date('m.Y', strtotime($period)); ?></h3>
                    </div>
                    <div class="card-body">
                        <div style="text-align: center; padding: 40px;">
                            <div style="font-size: 64px; font-weight: bold; 
                                        color: <?php 
                                        if ($totalKPI >= 90) echo 'var(--success-color)';
                                        elseif ($totalKPI >= 80) echo 'var(--info-color)';
                                        elseif ($totalKPI >= 70) echo 'var(--warning-color)';
                                        else echo 'var(--danger-color)';
                                        ?>;">
                                <?php echo number_format($totalKPI, 2); ?>%
                            </div>
                            <div style="font-size: 20px; color: var(--text-light); margin-top: 8px;">
                                <?php 
                                if ($totalKPI >= 90) echo '🏆 Отличный результат!';
                                elseif ($totalKPI >= 80) echo '👍 Хорошая работа!';
                                elseif ($totalKPI >= 70) echo '📊 Средний показатель';
                                else echo '⚠️ Требуется улучшение';
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- KPI Indicators -->
                <div class="card">
                    <div class="card-header">
                        <h3>Показатели KPI</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($kpiValues)): ?>
                        <p class="text-muted">KPI за этот период не установлены</p>
                        <?php else: ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Индикатор</th>
                                        <th>Цель</th>
                                        <th>Достигнуто</th>
                                        <th>Выполнение</th>
                                        <th>Вес</th>
                                        <th>Вклад в KPI</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($kpiValues as $kpiVal): ?>
                                    <?php 
                                    $achievement = ($kpiVal['value'] / $kpiVal['target_value']) * 100;
                                    $achievement = min($achievement, 150); // Макс 150%
                                    $contribution = ($achievement / 100) * $kpiVal['weight'];
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($kpiVal['kpi_name']); ?></strong>
                                            <?php if ($kpiVal['description']): ?>
                                            <div style="font-size: 12px; color: var(--text-light);">
                                                <?php echo htmlspecialchars($kpiVal['description']); ?>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $kpiVal['target_value']; ?> <?php echo htmlspecialchars($kpiVal['measurement_unit']); ?></td>
                                        <td>
                                            <strong><?php echo $kpiVal['value']; ?></strong> <?php echo htmlspecialchars($kpiVal['measurement_unit']); ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <div style="flex: 1; background: var(--light-color); height: 20px; border-radius: 10px; overflow: hidden;">
                                                    <?php
                                                    $barColor = 'var(--danger-color)';
                                                    if ($achievement >= 100) $barColor = 'var(--success-color)';
                                                    elseif ($achievement >= 80) $barColor = 'var(--info-color)';
                                                    elseif ($achievement >= 60) $barColor = 'var(--warning-color)';
                                                    ?>
                                                    <div style="width: <?php echo min($achievement, 100); ?>%; height: 100%; background: <?php echo $barColor; ?>;"></div>
                                                </div>
                                                <span style="min-width: 60px; text-align: right; font-weight: 600;">
                                                    <?php echo number_format($achievement, 1); ?>%
                                                </span>
                                            </div>
                                        </td>
                                        <td><?php echo $kpiVal['weight']; ?>%</td>
                                        <td>
                                            <strong style="color: var(--success-color);">
                                                <?php echo number_format($contribution, 2); ?>%
                                            </strong>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- History -->
                <?php if (!empty($history)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3>История KPI (последние 12 месяцев)</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Период</th>
                                        <th>KPI Total</th>
                                        <th>Оценка</th>
                                        <th>Динамика</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $prevKPI = null;
                                    foreach ($history as $h): 
                                    ?>
                                    <tr>
                                        <td><?php echo date('m.Y', strtotime($h['period'] . '-01')); ?></td>
                                        <td>
                                            <strong style="font-size: 16px;">
                                                <?php echo number_format($h['total_kpi'], 2); ?>%
                                            </strong>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($h['total_kpi'] >= 90) {
                                                echo '<span class="badge badge-success">Отлично</span>';
                                            } elseif ($h['total_kpi'] >= 80) {
                                                echo '<span class="badge badge-info">Хорошо</span>';
                                            } elseif ($h['total_kpi'] >= 70) {
                                                echo '<span class="badge badge-warning">Средне</span>';
                                            } else {
                                                echo '<span class="badge badge-danger">Низко</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($prevKPI !== null) {
                                                $diff = $h['total_kpi'] - $prevKPI;
                                                if ($diff > 0) {
                                                    echo '<span style="color: var(--success-color);">↑ +' . number_format($diff, 2) . '%</span>';
                                                } elseif ($diff < 0) {
                                                    echo '<span style="color: var(--danger-color);">↓ ' . number_format($diff, 2) . '%</span>';
                                                } else {
                                                    echo '<span style="color: var(--text-light);">→ 0%</span>';
                                                }
                                            } else {
                                                echo '-';
                                            }
                                            $prevKPI = $h['total_kpi'];
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
