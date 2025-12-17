<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Reward.php';
require_once __DIR__ . '/../../classes/KPI.php';

if (!User::isAuthenticated() || !User::hasRole('Manager')) {
    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

$user = User::getCurrentUser();
$role = User::getCurrentRole();
$reward = new Reward();
$kpi = new KPI();
$db = Database::getInstance();

// Получаем период
$selectedPeriod = $_GET['period'] ?? date('Y-m-01');

// Получаем тип периода
$periodType = $_GET['period_type'] ?? 'monthly';

$success = null;
$error = null;

// Обработка расчёта вознаграждений
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calculate_rewards'])) {
    if (!User::verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Ошибка безопасности';
    } else {
        $period = $_POST['period'];
        $pType = $_POST['period_type'];
        $departmentId = $role === 'CEO' ? null : $user['department_id'];
        
        try {
            if (isset($_POST['employee_id'])) {
                // Рассчитываем для одного сотрудника
                $reward->calculateAndSave($_POST['employee_id'], $period, $pType);
                $success = 'Вознаграждение успешно рассчитано!';
            } elseif ($departmentId) {
                // Рассчитываем для отдела
                $reward->calculateForDepartment($departmentId, $period, $pType);
                $success = 'Вознаграждения успешно рассчитаны для всех сотрудников отдела!';
            } else {
                // CEO - рассчитываем для всех
                $reward->calculateForAll($period, $pType);
                $success = 'Вознаграждения успешно рассчитаны для всех сотрудников компании!';
            }
            $selectedPeriod = $period;
            $periodType = $pType;
        } catch (Exception $e) {
            $error = 'Ошибка при расчёте: ' . $e->getMessage();
        }
    }
}

// Обработка расчёта вознаграждений менеджеров
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calculate_manager_rewards'])) {
    if (!User::verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Ошибка безопасности';
    } else {
        $period = $_POST['period'];
        $pType = $_POST['period_type'];
        
        try {
            $reward->calculateForAllManagers($period, $pType);
            $success = 'Вознаграждения успешно рассчитаны для всех менеджеров!';
            $selectedPeriod = $period;
            $periodType = $pType;
        } catch (Exception $e) {
            $error = 'Ошибка при расчёте: ' . $e->getMessage();
        }
    }
}

// Обработка расчёта вознаграждений за все время для всех (CEO only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calculate_all_time']) && $role === 'CEO') {
    if (!User::verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Ошибка безопасности';
    } else {
        $pType = $_POST['period_type'];
        
        try {
            set_time_limit(300); // Увеличиваем лимит времени выполнения до 5 минут
            $results = $reward->calculateForEveryoneAllTime($pType);
            $success = sprintf(
                'Вознаграждения успешно рассчитаны за все время! Обработано периодов: %d. Сотрудников: %d записей. Менеджеров: %d записей.',
                $results['total_periods'],
                $results['employees_calculated'],
                $results['managers_calculated']
            );
        } catch (Exception $e) {
            $error = 'Ошибка при расчёте: ' . $e->getMessage();
        }
    }
}

// Получаем список сотрудников
if ($role === 'CEO') {
    $employees = $db->fetchAll(
        "SELECT e.*, d.name as department_name 
         FROM employees e 
         JOIN departments d ON e.department_id = d.id 
         ORDER BY e.last_name, e.first_name"
    );
    $departmentId = null;
} else {
    $employees = $db->fetchAll(
        "SELECT e.*, d.name as department_name 
         FROM employees e 
         JOIN departments d ON e.department_id = d.id 
         WHERE e.department_id = ?
         ORDER BY e.last_name, e.first_name",
        [$user['department_id']]
    );
    $departmentId = $user['department_id'];
}

// Получаем данные по вознаграждениям
$rewards = $reward->getAllRewards($selectedPeriod, $departmentId, $periodType);
$stats = $reward->getStatistics($selectedPeriod, $departmentId, $periodType);

// Получаем данные по вознаграждениям менеджеров (только для CEO)
$managerRewards = [];
$managerStats = null;
if ($role === 'CEO') {
    $managerRewards = $reward->getAllManagerRewards($selectedPeriod, $periodType);
    $managerStats = $reward->getManagerStatistics($selectedPeriod, $periodType);
}

// Генерируем периоды
$periods = [];
for ($i = 0; $i < 12; $i++) {
    $date = date('Y-m-01', strtotime("-$i months"));
    $periods[] = $date;
}

// Типы периодов
$periodTypes = [
    'monthly' => 'Месячные',
    'quarterly' => 'Квартальные',
    'yearly' => 'Годовые'
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вознаграждения - <?php echo APP_NAME; ?></title>
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
                <li><a href="<?php echo APP_URL; ?>/modules/projects/list.php"><span class="icon">📁</span> Проекты</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/tasks/list.php"><span class="icon">✓</span> Задачи</a></li>
                <?php if ($role === 'CEO'): ?>
                <li><a href="<?php echo APP_URL; ?>/modules/employees/list.php"><span class="icon">👥</span> Сотрудники</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/managers/list.php"><span class="icon">👔</span> Менеджеры</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/departments/list.php"><span class="icon">🏢</span> Отделы</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/kpi/list.php"><span class="icon">📈</span> KPI</a></li>
                <li><a href="list.php" class="active"><span class="icon">💰</span> Вознаграждения</a></li>
                <?php elseif ($role === 'Manager'): ?>
                <li><a href="<?php echo APP_URL; ?>/modules/employees/list.php"><span class="icon">👥</span> Сотрудники</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/kpi/employees.php"><span class="icon">📈</span> KPI команды</a></li>
                <?php endif; ?>
                <li><a href="<?php echo APP_URL; ?>/auth/logout.php"><span class="icon">🚪</span> Выход</a></li>
            </ul>
        </aside>

        <div class="main-content">
            <div class="topbar">
                <h1>Вознаграждения</h1>
                <div class="user-menu">
                    <div class="user-info">
                        <div class="name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <div class="role"><?php echo $role; ?></div>
                    </div>
                </div>
            </div>

            <div class="content">
                <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>

                <!-- Statistics -->
                <?php if ($stats && $stats['total_employees'] > 0): ?>
                <div class="stats-grid">
                    <div class="stat-card primary">
                        <div class="icon">👥</div>
                        <div class="value"><?php echo $stats['total_employees']; ?></div>
                        <div class="label">Сотрудников</div>
                    </div>
                    <div class="stat-card success">
                        <div class="icon">💰</div>
                        <div class="value"><?php echo number_format($stats['total_rewards'], 0, ',', ' '); ?> ₽</div>
                        <div class="label">Всего вознаграждений</div>
                    </div>
                    <div class="stat-card warning">
                        <div class="icon">📊</div>
                        <div class="value"><?php echo number_format($stats['avg_reward'], 0, ',', ' '); ?> ₽</div>
                        <div class="label">Среднее вознаграждение</div>
                    </div>
                    <div class="stat-card danger">
                        <div class="icon">📈</div>
                        <div class="value"><?php echo number_format($stats['avg_kpi'], 2); ?></div>
                        <div class="label">Средний KPI</div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Calculate Form -->
                <div class="card">
                    <div class="card-header">
                        <h3>Рассчитать вознаграждения</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo User::getCsrfToken(); ?>">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="period">Период *</label>
                                    <select id="period" name="period" required>
                                        <?php foreach ($periods as $p): ?>
                                        <option value="<?php echo $p; ?>" <?php echo $p === $selectedPeriod ? 'selected' : ''; ?>>
                                            <?php echo date('F Y', strtotime($p)); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="period_type">Тип периода *</label>
                                    <select id="period_type" name="period_type" required>
                                        <?php foreach ($periodTypes as $key => $label): ?>
                                        <option value="<?php echo $key; ?>" <?php echo $key === $periodType ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="flex gap-2 mt-3">
                                <button type="submit" name="calculate_rewards" class="btn btn-primary">
                                    Рассчитать для всех
                                </button>
                                <small style="align-self: center; color: var(--text-light);">
                                    Вознаграждения будут рассчитаны на основе индивидуальных зарплат и KPI показателей
                                </small>
                            </div>
                        </form>
                        
                        <?php if ($role === 'CEO'): ?>
                        <hr style="margin: 30px 0; border: none; border-top: 1px solid var(--border-color);">
                        
                        <form method="POST" action="" onsubmit="return confirm('Это может занять несколько минут. Рассчитать вознаграждения за ВСЕ периоды для ВСЕХ сотрудников и менеджеров?');">
                            <input type="hidden" name="csrf_token" value="<?php echo User::getCsrfToken(); ?>">
                            
                            <div class="form-group" style="max-width: 300px;">
                                <label for="period_type_all">Тип периода для полного расчета *</label>
                                <select id="period_type_all" name="period_type" required>
                                    <?php foreach ($periodTypes as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" <?php echo $key === 'monthly' ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <button type="submit" name="calculate_all_time" class="btn btn-danger">
                                🔄 Рассчитать за ВСЕ ВРЕМЯ для ВСЕХ
                            </button>
                            <small style="display: block; margin-top: 10px; color: var(--text-light);">
                                ⚠️ Внимание: Этот процесс рассчитает вознаграждения за все месяцы с момента найма первого сотрудника до текущего месяца для всех сотрудников и менеджеров. Может занять несколько минут.
                            </small>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Rewards List -->
                <div class="card">
                    <div class="card-header">
                        <h3>Вознаграждения за <?php echo date('m.Y', strtotime($selectedPeriod)); ?> (<?php echo $periodTypes[$periodType]; ?>)</h3>
                        <a href="<?php echo APP_URL; ?>/modules/export/export.php?type=employee_rewards&period=<?php echo urlencode($selectedPeriod); ?>&period_type=<?php echo urlencode($periodType); ?><?php echo $departmentId ? '&department_id=' . $departmentId : ''; ?>" class="btn btn-primary" style="background: #27ae60;">
                            📥 Экспорт в Excel
                        </a>
                        <form method="GET" style="display: inline-flex; gap: 10px;">
                            <select name="period" onchange="this.form.submit()">
                                <?php foreach ($periods as $p): ?>
                                <option value="<?php echo $p; ?>" <?php echo $p === $selectedPeriod ? 'selected' : ''; ?>>
                                    <?php echo date('F Y', strtotime($p)); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="period_type" onchange="this.form.submit()">
                                <?php foreach ($periodTypes as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo $key === $periodType ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($rewards)): ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Сотрудник</th>
                                        <th>Отдел</th>
                                        <th>Базовая зарплата</th>
                                        <th>KPI</th>
                                        <th>Бонус</th>
                                        <th>Итого к выплате</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $rank = 1;
                                    foreach ($rewards as $r): 
                                    ?>
                                    <tr>
                                        <td><?php echo $rank++; ?></td>
                                        <td><?php echo htmlspecialchars($r['first_name'] . ' ' . $r['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($r['department_name']); ?></td>
                                        <td><?php echo number_format($r['base_salary'], 2, ',', ' '); ?> ₽</td>
                                        <td>
                                            <?php 
                                            $kpiClass = 'secondary';
                                            if ($r['kpi_total'] >= 100) $kpiClass = 'success';
                                            elseif ($r['kpi_total'] >= 80) $kpiClass = 'info';
                                            elseif ($r['kpi_total'] >= 60) $kpiClass = 'warning';
                                            else $kpiClass = 'danger';
                                            ?>
                                            <span class="badge badge-<?php echo $kpiClass; ?>">
                                                <?php echo number_format($r['kpi_total'], 2); ?>%
                                            </span>
                                        </td>
                                        <td style="color: var(--secondary-color);">
                                            +<?php echo number_format($r['bonus_amount'], 2, ',', ' '); ?> ₽
                                        </td>
                                        <td>
                                            <strong style="color: var(--primary-color); font-size: 1.1em;">
                                                <?php echo number_format($r['total_amount'], 2, ',', ' '); ?> ₽
                                            </strong>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <p class="text-center" style="padding: 40px;">
                            Вознаграждения за выбранный период ещё не рассчитаны.<br>
                            <small>Сначала установите KPI показатели для сотрудников, затем рассчитайте вознаграждения.</small>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Manager Rewards (Only for CEO) -->
                <?php if ($role === 'CEO'): ?>
                <div class="card" style="margin-top: 30px;">
                    <div class="card-header">
                        <h3>Вознаграждения менеджеров за <?php echo date('m.Y', strtotime($selectedPeriod)); ?></h3>
                        <div class="flex gap-2">
                            <a href="<?php echo APP_URL; ?>/modules/export/export.php?type=manager_rewards&period=<?php echo urlencode($selectedPeriod); ?>&period_type=<?php echo urlencode($periodType); ?>" class="btn btn-primary" style="background: #27ae60;">
                                📥 Экспорт в Excel
                            </a>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo User::getCsrfToken(); ?>">
                                <input type="hidden" name="period" value="<?php echo $selectedPeriod; ?>">
                                <input type="hidden" name="period_type" value="<?php echo $periodType; ?>">
                                <button type="submit" name="calculate_manager_rewards" class="btn btn-primary btn-sm">
                                    🔄 Рассчитать для менеджеров
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($managerStats && $managerStats['total_managers'] > 0): ?>
                        <div class="stats-grid" style="margin-bottom: 30px;">
                            <div class="stat-card">
                                <div class="icon">👔</div>
                                <div class="value"><?php echo $managerStats['total_managers']; ?></div>
                                <div class="label">Менеджеров</div>
                            </div>
                            <div class="stat-card success">
                                <div class="icon">💰</div>
                                <div class="value"><?php echo number_format($managerStats['total_bonuses'], 0, ',', ' '); ?> ₽</div>
                                <div class="label">Общая сумма премий</div>
                            </div>
                            <div class="stat-card info">
                                <div class="icon">📊</div>
                                <div class="value"><?php echo number_format($managerStats['avg_bonus'], 0, ',', ' '); ?> ₽</div>
                                <div class="label">Средняя премия</div>
                            </div>
                            <div class="stat-card warning">
                                <div class="icon">💵</div>
                                <div class="value"><?php echo number_format($managerStats['total_rewards'], 0, ',', ' '); ?> ₽</div>
                                <div class="label">Всего к выплате</div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($managerRewards)): ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Менеджер</th>
                                        <th>Отдел</th>
                                        <th>Сотрудников</th>
                                        <th>Средний KPI</th>
                                        <th>Базовая зарплата</th>
                                        <th>Премия</th>
                                        <th>Итого</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $rank = 1;
                                    foreach ($managerRewards as $mr): 
                                    ?>
                                    <tr>
                                        <td><?php echo $rank++; ?></td>
                                        <td><?php echo htmlspecialchars($mr['first_name'] . ' ' . $mr['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($mr['department_name']); ?></td>
                                        <td><?php echo $mr['employees_count']; ?></td>
                                        <td>
                                            <span class="badge badge-info">
                                                <?php echo number_format($mr['avg_department_kpi'], 2); ?>%
                                            </span>
                                        </td>
                                        <td><?php echo number_format($mr['base_salary'], 0, ',', ' '); ?> ₽</td>
                                        <td>
                                            <strong style="color: var(--success-color);">
                                                <?php echo number_format($mr['bonus_amount'], 0, ',', ' '); ?> ₽
                                            </strong>
                                        </td>
                                        <td>
                                            <strong style="color: var(--primary-color);">
                                                <?php echo number_format($mr['total_amount'], 0, ',', ' '); ?> ₽
                                            </strong>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr style="background: var(--light-color); font-weight: bold;">
                                        <td colspan="5">Всего:</td>
                                        <td>
                                            <?php 
                                            $totalBase = array_sum(array_column($managerRewards, 'base_salary'));
                                            echo number_format($totalBase, 0, ',', ' '); 
                                            ?> ₽
                                        </td>
                                        <td>
                                            <?php 
                                            $totalBonus = array_sum(array_column($managerRewards, 'bonus_amount'));
                                            echo number_format($totalBonus, 0, ',', ' '); 
                                            ?> ₽
                                        </td>
                                        <td>
                                            <?php 
                                            $totalReward = array_sum(array_column($managerRewards, 'total_amount'));
                                            echo number_format($totalReward, 0, ',', ' '); 
                                            ?> ₽
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <?php else: ?>
                        <p class="text-center" style="padding: 40px; color: var(--text-light);">
                            Вознаграждения для менеджеров не рассчитаны.<br>
                            <small>Сначала рассчитайте вознаграждения сотрудников, затем нажмите кнопку выше.</small>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

