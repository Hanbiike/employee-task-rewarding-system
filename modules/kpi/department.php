<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/KPI.php';
require_once __DIR__ . '/../../classes/Department.php';

if (!User::isAuthenticated() || !User::hasRole('CEO')) {
    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

$user = User::getCurrentUser();
$role = User::getCurrentRole();
$kpi = new KPI();
$department = new Department();
$db = Database::getInstance();

// Получаем список отделов
$departments = $department->getAll();

// Текущий период
$currentPeriod = $_GET['period'] ?? date('Y-m');
$selectedDepartment = $_GET['department_id'] ?? null;

// Получаем KPI сотрудников
if ($selectedDepartment) {
    $employeesKPI = $kpi->getAllEmployeesKPI($currentPeriod, $selectedDepartment);
    $departmentData = $db->fetchOne("SELECT * FROM departments WHERE id = ?", [$selectedDepartment]);
} else {
    $employeesKPI = $kpi->getAllEmployeesKPI($currentPeriod);
    $departmentData = null;
}

// Статистика
$avgKPI = !empty($employeesKPI) ? array_sum(array_column($employeesKPI, 'total_kpi')) / count($employeesKPI) : 0;
$topPerformers = array_slice($employeesKPI, 0, 5);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KPI по отделам - <?php echo APP_NAME; ?></title>
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
                <li><a href="<?php echo APP_URL; ?>/dashboard/ceo.php"><span class="icon">📊</span> Главная</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/projects/list.php"><span class="icon">📁</span> Проекты</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/tasks/list.php"><span class="icon">✓</span> Задачи</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/employees/list.php"><span class="icon">👥</span> Сотрудники</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/managers/list.php"><span class="icon">👔</span> Менеджеры</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/departments/list.php"><span class="icon">🏢</span> Отделы</a></li>
                <li><a href="list.php" class="active"><span class="icon">📈</span> KPI</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/rewards/list.php"><span class="icon">💰</span> Вознаграждения</a></li>
                <li><a href="<?php echo APP_URL; ?>/auth/logout.php"><span class="icon">🚪</span> Выход</a></li>
            </ul>
        </aside>

        <div class="main-content">
            <div class="topbar">
                <h1>KPI по отделам</h1>
                <div class="user-menu">
                    <div class="user-info">
                        <div class="name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <div class="role"><?php echo $role; ?></div>
                    </div>
                </div>
            </div>

            <div class="content">
                <!-- Filters -->
                <div class="card">
                    <div class="card-header">
                        <h3>Фильтры</h3>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="flex gap-2">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="department_id">Отдел</label>
                                <select name="department_id" id="department_id">
                                    <option value="">Все отделы</option>
                                    <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>" 
                                            <?php echo $selectedDepartment == $dept['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                        (<?php echo $dept['employee_count']; ?> сотр.)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="period">Период</label>
                                <input type="month" name="period" id="period" value="<?php echo $currentPeriod; ?>">
                            </div>
                            
                            <div style="align-self: flex-end;">
                                <button type="submit" class="btn btn-primary">Применить</button>
                                <a href="department.php" class="btn btn-secondary">Сбросить</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">👥</div>
                        <div class="stat-details">
                            <div class="stat-value"><?php echo count($employeesKPI); ?></div>
                            <div class="stat-label">Сотрудников</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">📊</div>
                        <div class="stat-details">
                            <div class="stat-value"><?php echo number_format($avgKPI, 2); ?>%</div>
                            <div class="stat-label">Средний KPI</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">🏆</div>
                        <div class="stat-details">
                            <div class="stat-value">
                                <?php 
                                $excellentCount = count(array_filter($employeesKPI, function($e) { 
                                    return $e['total_kpi'] >= 90; 
                                }));
                                echo $excellentCount;
                                ?>
                            </div>
                            <div class="stat-label">Отличники (≥90%)</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">⚠️</div>
                        <div class="stat-details">
                            <div class="stat-value">
                                <?php 
                                $lowCount = count(array_filter($employeesKPI, function($e) { 
                                    return $e['total_kpi'] < 70; 
                                }));
                                echo $lowCount;
                                ?>
                            </div>
                            <div class="stat-label">Требуют внимания (<70%)</div>
                        </div>
                    </div>
                </div>

                <!-- Department Overview -->
                <?php if ($departmentData): ?>
                <div class="card">
                    <div class="card-header">
                        <h3><?php echo htmlspecialchars($departmentData['name']); ?></h3>
                    </div>
                    <div class="card-body">
                        <div class="stats-grid">
                            <div class="stat-item">
                                <div class="stat-label">Средний KPI отдела</div>
                                <div class="stat-value"><?php echo number_format($avgKPI, 2); ?>%</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-label">Сотрудников в отделе</div>
                                <div class="stat-value"><?php echo count($employeesKPI); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Top Performers -->
                <?php if (!empty($topPerformers)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3>🏆 Топ-5 сотрудников</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Сотрудник</th>
                                        <th>Отдел</th>
                                        <th>Общий KPI</th>
                                        <th>Оценка</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topPerformers as $index => $emp): ?>
                                    <tr>
                                        <td>
                                            <?php 
                                            $medal = ['🥇', '🥈', '🥉', '4', '5'];
                                            echo $medal[$index];
                                            ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($emp['department_name']); ?></td>
                                        <td>
                                            <strong style="font-size: 18px; color: var(--success-color);">
                                                <?php echo number_format($emp['total_kpi'], 2); ?>%
                                            </strong>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($emp['total_kpi'] >= 90) {
                                                echo '<span class="badge badge-success">Отлично</span>';
                                            } elseif ($emp['total_kpi'] >= 80) {
                                                echo '<span class="badge badge-info">Хорошо</span>';
                                            } else {
                                                echo '<span class="badge badge-warning">Средне</span>';
                                            }
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

                <!-- All Employees KPI -->
                <div class="card">
                    <div class="card-header">
                        <h3>KPI всех сотрудников за <?php echo date('m.Y', strtotime($currentPeriod)); ?></h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($employeesKPI)): ?>
                        <p class="text-muted">Нет данных за выбранный период</p>
                        <?php else: ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Сотрудник</th>
                                        <th>Отдел</th>
                                        <th>KPI Total</th>
                                        <th>Оценка</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($employeesKPI as $emp): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></strong>
                                            <div style="font-size: 12px; color: var(--text-light);">
                                                <?php echo htmlspecialchars($emp['email']); ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($emp['department_name']); ?></td>
                                        <td>
                                            <strong style="font-size: 16px;">
                                                <?php echo number_format($emp['total_kpi'], 2); ?>%
                                            </strong>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($emp['total_kpi'] >= 90) {
                                                echo '<span class="badge badge-success">Отлично</span>';
                                            } elseif ($emp['total_kpi'] >= 80) {
                                                echo '<span class="badge badge-info">Хорошо</span>';
                                            } elseif ($emp['total_kpi'] >= 70) {
                                                echo '<span class="badge badge-warning">Средне</span>';
                                            } else {
                                                echo '<span class="badge badge-danger">Низко</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <a href="view.php?employee_id=<?php echo $emp['id']; ?>&period=<?php echo $currentPeriod; ?>" 
                                               class="btn btn-sm btn-primary">Детали</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
