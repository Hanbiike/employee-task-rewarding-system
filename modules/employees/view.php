<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/KPI.php';
require_once __DIR__ . '/../../classes/Reward.php';
require_once __DIR__ . '/../../classes/Task.php';

if (!User::isAuthenticated()) {
    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

$employeeId = $_GET['id'] ?? null;
if (!$employeeId) {
    header('Location: list.php');
    exit;
}

$user = User::getCurrentUser();
$role = User::getCurrentRole();
$db = Database::getInstance();
$kpi = new KPI();
$reward = new Reward();
$task = new Task();

// Получаем данные сотрудника
$employee = $db->fetchOne(
    "SELECT e.*, d.name as department_name, 
     CONCAT(m.first_name, ' ', m.last_name) as manager_name,
     m.email as manager_email
     FROM employees e
     JOIN departments d ON e.department_id = d.id
     LEFT JOIN managers m ON e.manager_id = m.id
     WHERE e.id = ?",
    [$employeeId]
);

if (!$employee) {
    header('Location: list.php');
    exit;
}

// Проверка прав доступа
if ($role === 'Manager' && $employee['department_id'] != $user['department_id']) {
    header('Location: list.php');
    exit;
}

// Получаем статистику
$currentPeriod = date('Y-m');
$totalKPI = $kpi->calculateTotalKPI($employeeId, $currentPeriod);
$kpiHistory = $kpi->getEmployeeHistory($employeeId, 6);
$currentReward = $reward->getEmployeeReward($employeeId, $currentPeriod);
$rewardHistory = $reward->getEmployeeHistory($employeeId, 6);

// Получаем задачи
$tasks = $task->getByEmployee($employeeId);
$tasksCompleted = count(array_filter($tasks, fn($t) => $t['status'] === 'Completed'));
$tasksInProgress = count(array_filter($tasks, fn($t) => $t['status'] === 'In Progress'));
$tasksNotStarted = count(array_filter($tasks, fn($t) => $t['status'] === 'Not Started'));
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?> - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <style>
        .profile-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 40px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 30px;
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: white;
            color: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: bold;
            flex-shrink: 0;
        }
        .profile-info h2 {
            margin: 0 0 8px 0;
            font-size: 32px;
        }
        .profile-meta {
            display: flex;
            gap: 24px;
            margin-top: 12px;
        }
        .profile-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
    </style>
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
                <?php if ($role !== 'Employee'): ?>
                <li><a href="<?php echo APP_URL; ?>/modules/projects/list.php"><span class="icon">📁</span> Проекты</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/tasks/list.php"><span class="icon">✓</span> Задачи</a></li>
                <?php endif; ?>
                <li><a href="list.php" class="active"><span class="icon">👥</span> Сотрудники</a></li>
                <?php if ($role === 'CEO'): ?>
                <li><a href="<?php echo APP_URL; ?>/modules/managers/list.php"><span class="icon">👔</span> Менеджеры</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/departments/list.php"><span class="icon">🏢</span> Отделы</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/kpi/list.php"><span class="icon">📈</span> KPI</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/rewards/list.php"><span class="icon">💰</span> Вознаграждения</a></li>
                <?php elseif ($role === 'Manager'): ?>
                <li><a href="<?php echo APP_URL; ?>/modules/kpi/employees.php"><span class="icon">📈</span> KPI команды</a></li>
                <?php endif; ?>
                <li><a href="<?php echo APP_URL; ?>/auth/logout.php"><span class="icon">🚪</span> Выход</a></li>
            </ul>
        </aside>

        <div class="main-content">
            <div class="topbar">
                <h1>Профиль сотрудника</h1>
                <div class="user-menu">
                    <div class="user-info">
                        <div class="name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <div class="role"><?php echo $role; ?></div>
                    </div>
                </div>
            </div>

            <div class="content">
                <a href="list.php" class="btn btn-secondary mb-3">← Назад к списку</a>

                <!-- Profile Header -->
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php 
                        $initials = mb_substr($employee['first_name'], 0, 1) . mb_substr($employee['last_name'], 0, 1);
                        echo htmlspecialchars($initials);
                        ?>
                    </div>
                    <div class="profile-info">
                        <h2><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></h2>
                        <div class="profile-meta">
                            <div class="profile-meta-item">
                                <span>🏢</span>
                                <span><?php echo htmlspecialchars($employee['department_name']); ?></span>
                            </div>
                            <div class="profile-meta-item">
                                <span>📅</span>
                                <span>С <?php echo date('d.m.Y', strtotime($employee['hire_date'])); ?></span>
                            </div>
                            <?php if ($employee['manager_name']): ?>
                            <div class="profile-meta-item">
                                <span>👔</span>
                                <span>Менеджер: <?php echo htmlspecialchars($employee['manager_name']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">📈</div>
                        <div class="stat-details">
                            <div class="stat-value"><?php echo number_format($totalKPI, 2); ?>%</div>
                            <div class="stat-label">KPI (текущий месяц)</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">💰</div>
                        <div class="stat-details">
                            <div class="stat-value">
                                <?php echo $currentReward ? number_format($currentReward['total_reward'], 0, ',', ' ') . ' ₽' : 'Н/Д'; ?>
                            </div>
                            <div class="stat-label">Вознаграждение за месяц</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">✓</div>
                        <div class="stat-details">
                            <div class="stat-value"><?php echo $tasksCompleted; ?> / <?php echo count($tasks); ?></div>
                            <div class="stat-label">Завершённые задачи</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">⏳</div>
                        <div class="stat-details">
                            <div class="stat-value"><?php echo $tasksInProgress; ?></div>
                            <div class="stat-label">Задач в работе</div>
                        </div>
                    </div>
                </div>

                <!-- Contact Info -->
                <div class="card">
                    <div class="card-header">
                        <h3>Контактная информация</h3>
                        <?php if ($role === 'CEO'): ?>
                        <a href="edit.php?id=<?php echo $employeeId; ?>" class="btn btn-primary btn-sm">
                            Редактировать
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="form-grid">
                            <div>
                                <strong>Email:</strong>
                                <p><a href="mailto:<?php echo htmlspecialchars($employee['email']); ?>">
                                    <?php echo htmlspecialchars($employee['email']); ?>
                                </a></p>
                            </div>
                            <div>
                                <strong>Телефон:</strong>
                                <p><a href="tel:<?php echo htmlspecialchars($employee['phone_number']); ?>">
                                    <?php echo htmlspecialchars($employee['phone_number']); ?>
                                </a></p>
                            </div>
                            <div>
                                <strong>Отдел:</strong>
                                <p><?php echo htmlspecialchars($employee['department_name']); ?></p>
                            </div>
                            <div>
                                <strong>Дата найма:</strong>
                                <p><?php echo date('d.m.Y', strtotime($employee['hire_date'])); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- KPI History -->
                <?php if (!empty($kpiHistory)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3>История KPI (последние 6 месяцев)</h3>
                        <a href="<?php echo APP_URL; ?>/modules/kpi/view.php?employee_id=<?php echo $employeeId; ?>" 
                           class="btn btn-primary btn-sm">
                            Полная информация
                        </a>
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
                                    foreach ($kpiHistory as $h): 
                                    ?>
                                    <tr>
                                        <td><?php echo date('m.Y', strtotime($h['period'] . '-01')); ?></td>
                                        <td><strong><?php echo number_format($h['total_kpi'], 2); ?>%</strong></td>
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

                <!-- Recent Tasks -->
                <div class="card">
                    <div class="card-header">
                        <h3>Последние задачи</h3>
                        <div class="flex gap-2">
                            <span class="badge badge-success"><?php echo $tasksCompleted; ?> завершено</span>
                            <span class="badge badge-info"><?php echo $tasksInProgress; ?> в работе</span>
                            <span class="badge badge-secondary"><?php echo $tasksNotStarted; ?> не начато</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($tasks)): ?>
                        <p class="text-muted">Задачи не назначены</p>
                        <?php else: ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Задача</th>
                                        <th>Проект</th>
                                        <th>Статус</th>
                                        <th>Важность</th>
                                        <th>Срок</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($tasks, 0, 10) as $t): ?>
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
                                        <td>
                                            <?php 
                                            if ($t['deadline']) {
                                                echo date('d.m.Y', strtotime($t['deadline']));
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <a href="<?php echo APP_URL; ?>/modules/tasks/view.php?id=<?php echo $t['id']; ?>" 
                                               class="btn btn-sm btn-primary">
                                                Просмотр
                                            </a>
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
