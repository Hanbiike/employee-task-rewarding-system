<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/KPI.php';
require_once __DIR__ . '/../../classes/Reward.php';

if (!User::isAuthenticated()) {
    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

$user = User::getCurrentUser();
$userId = User::getCurrentUserId();
$kpi = new KPI();
$reward = new Reward();

// Получаем период
$selectedPeriod = $_GET['period'] ?? date('Y-m-01');

// Получаем KPI данные
$kpiValues = $kpi->getEmployeeValues($userId, $selectedPeriod);
$totalKPI = $kpi->calculateTotalKPI($userId, $selectedPeriod);
$kpiHistory = $kpi->getEmployeeHistory($userId, 12);
$kpiBreakdown = $kpi->getKPIBreakdown($userId, $selectedPeriod);

// Получаем вознаграждение (без указания типа периода, чтобы получить любое)
$myReward = $reward->getEmployeeReward($userId, $selectedPeriod, 'monthly');

// Если вознаграждение не найдено, пытаемся его рассчитать
if (!$myReward && $totalKPI > 0) {
    try {
        // Проверяем базовую зарплату
        $db = Database::getInstance();
        $employeeData = $db->fetchOne("SELECT base_salary FROM employees WHERE id = ?", [$userId]);
        
        if ($employeeData && $employeeData['base_salary'] > 0) {
            $reward->calculateAndSave($userId, $selectedPeriod, 'monthly');
            $myReward = $reward->getEmployeeReward($userId, $selectedPeriod, 'monthly');
        }
    } catch (Exception $e) {
        // Игнорируем ошибки - вознаграждение просто не будет показано
        // Для отладки можно раскомментировать:
        // echo "<!-- Ошибка расчета вознаграждения: " . htmlspecialchars($e->getMessage()) . " -->";
    }
}

// Генерируем список доступных периодов (последние 12 месяцев)
$periods = [];
for ($i = 0; $i < 12; $i++) {
    $date = date('Y-m-01', strtotime("-$i months"));
    $periods[] = $date;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мои KPI - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
</head>
<body>
    <div class="dashboard">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><?php echo APP_NAME; ?></h2>
                <p>Employee Dashboard</p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="<?php echo APP_URL; ?>/dashboard/employee.php"><span class="icon">📊</span> Главная</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/tasks/my_tasks.php"><span class="icon">✓</span> Мои задачи</a></li>
                <li><a href="my_kpi.php" class="active"><span class="icon">📈</span> Мои KPI</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/rewards/my_rewards.php"><span class="icon">💰</span> Вознаграждения</a></li>
                <li><a href="<?php echo APP_URL; ?>/auth/logout.php"><span class="icon">🚪</span> Выход</a></li>
            </ul>
        </aside>

        <div class="main-content">
            <div class="topbar">
                <h1>Мои KPI</h1>
                <div class="user-menu">
                    <div class="user-info">
                        <div class="name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <div class="role">Сотрудник</div>
                    </div>
                </div>
            </div>

            <div class="content">
                <!-- KPI Summary -->
                <div class="stats-grid" style="margin-bottom: 32px;">
                    <div class="stat-card success">
                        <div class="icon">📈</div>
                        <div class="value"><?php echo number_format($totalKPI, 2); ?>%</div>
                        <div class="label">Итоговый KPI</div>
                    </div>
                    <div class="stat-card primary">
                        <div class="icon">📊</div>
                        <div class="value"><?php echo count($kpiValues); ?></div>
                        <div class="label">Показателей</div>
                    </div>
                    <div class="stat-card warning">
                        <div class="icon">📅</div>
                        <div class="value"><?php echo date('m.Y', strtotime($selectedPeriod)); ?></div>
                        <div class="label">Период</div>
                    </div>
                    <div class="stat-card danger">
                        <div class="icon">💰</div>
                        <div class="value"><?php 
                            if ($myReward && isset($myReward['total_amount'])) {
                                echo number_format($myReward['total_amount'], 0, ',', ' ') . ' ₽';
                            } elseif ($totalKPI > 0 && isset($user['base_salary']) && $user['base_salary'] > 0) {
                                // Рассчитываем на лету
                                $calculatedReward = $user['base_salary'] * (1 + $totalKPI / 100);
                                echo number_format($calculatedReward, 0, ',', ' ') . ' ₽';
                            } else {
                                echo '—';
                            }
                        ?></div>
                        <div class="label">Вознаграждение</div>
                    </div>
                </div>

                <!-- Period Selector -->
                <div class="card">
                    <div class="card-header">
                        <h3>Выбор периода</h3>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="flex gap-2">
                            <select name="period" onchange="this.form.submit()">
                                <?php foreach ($periods as $period): ?>
                                <option value="<?php echo $period; ?>" <?php echo $period === $selectedPeriod ? 'selected' : ''; ?>>
                                    <?php echo date('F Y', strtotime($period)); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                </div>

                <!-- Current KPI Values -->
                <?php if ($kpiBreakdown): ?>
                <!-- Детализация KPI -->
                <div class="card" style="margin-bottom: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <div class="card-body">
                        <h3 style="color: white; margin-bottom: 20px;">📊 Ваш KPI за <?php echo date('m.Y', strtotime($selectedPeriod)); ?></h3>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div style="background: rgba(255,255,255,0.1); padding: 20px; border-radius: 12px; backdrop-filter: blur(10px);">
                                <h4 style="color: white; margin-bottom: 15px;">🎯 Выполнение задач</h4>
                                <div style="font-size: 2.5em; font-weight: bold; margin: 10px 0;">
                                    <?php echo $kpiBreakdown['tasks_kpi_percentage']; ?>%
                                </div>
                                <p style="margin: 5px 0; opacity: 0.9;">Вес: <?php echo $kpiBreakdown['tasks_weight']; ?>%</p>
                                <p style="margin: 5px 0; font-size: 1.2em;">
                                    <strong>Вклад: <?php echo $kpiBreakdown['tasks_contribution']; ?></strong>
                                </p>
                                
                                <?php if (!empty($kpiBreakdown['tasks_data'])): ?>
                                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.2);">
                                    <p style="font-size: 0.9em; margin-bottom: 8px;"><strong>По важности задач:</strong></p>
                                    <?php foreach ($kpiBreakdown['tasks_data'] as $td): ?>
                                    <div style="margin: 5px 0; font-size: 0.85em;">
                                        <strong><?php echo $td['importance']; ?></strong> (×<?php echo $td['importance_weight']; ?>): 
                                        <?php echo $td['completed_count']; ?> из <?php echo $td['task_count']; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div style="background: rgba(255,255,255,0.1); padding: 20px; border-radius: 12px; backdrop-filter: blur(10px);">
                                <h4 style="color: white; margin-bottom: 15px;">⭐ Оценка менеджера</h4>
                                <div style="font-size: 2.5em; font-weight: bold; margin: 10px 0;">
                                    <?php echo $kpiBreakdown['manager_evaluation']; ?>
                                </div>
                                <p style="margin: 5px 0; opacity: 0.9;">Вес: <?php echo $kpiBreakdown['manager_weight']; ?>%</p>
                                <p style="margin: 5px 0; font-size: 1.2em;">
                                    <strong>Вклад: <?php echo $kpiBreakdown['manager_contribution']; ?></strong>
                                </p>
                                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.2);">
                                    <p style="font-size: 0.85em; opacity: 0.9;">
                                        Основано на глобальных метриках: пунктуальность, качество работы, 
                                        коммуникация и другие факторы, оцениваемые вашим менеджером.
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div style="margin-top: 25px; padding: 20px; background: rgba(255,255,255,0.95); 
                                    border-radius: 12px; text-align: center; color: #333;">
                            <h2 style="margin: 0; color: #667eea;">
                                Итоговый KPI: 
                                <span style="font-size: 1.4em; color: <?php 
                                    $total = $kpiBreakdown['total_kpi'];
                                    echo $total >= 100 ? '#28a745' : ($total >= 80 ? '#17a2b8' : ($total >= 60 ? '#ffc107' : '#dc3545'));
                                ?>;">
                                    <?php echo $kpiBreakdown['total_kpi']; ?>%
                                </span>
                            </h2>
                            <p style="margin: 10px 0 0 0; color: #6c757d; font-size: 0.9em;">
                                = (<?php echo $kpiBreakdown['tasks_kpi_percentage']; ?>% × <?php echo $kpiBreakdown['tasks_weight']; ?>%) 
                                + (<?php echo $kpiBreakdown['manager_evaluation']; ?>% × <?php echo $kpiBreakdown['manager_weight']; ?>%)
                            </p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3>Детализация глобальных метрик KPI</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($kpiValues)): ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Показатель</th>
                                        <th>Описание</th>
                                        <th>Вес</th>
                                        <th>Целевое значение</th>
                                        <th>Фактическое значение</th>
                                        <th>Выполнение</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($kpiValues as $kpiValue): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($kpiValue['name'] ?? ''); ?></strong></td>
                                        <td><?php echo htmlspecialchars($kpiValue['description'] ?? ''); ?></td>
                                        <td><?php echo $kpiValue['weight']; ?>%</td>
                                        <td><?php echo $kpiValue['target_value'] ? $kpiValue['target_value'] . ' ' . ($kpiValue['measurement_unit'] ?? $kpiValue['unit'] ?? '%') : '-'; ?></td>
                                        <td><strong><?php echo $kpiValue['value'] . ' ' . ($kpiValue['measurement_unit'] ?? $kpiValue['unit'] ?? '%'); ?></strong></td>
                                        <td>
                                            <?php 
                                            if ($kpiValue['target_value'] > 0) {
                                                $percentage = ($kpiValue['value'] / $kpiValue['target_value']) * 100;
                                                $badgeClass = 'secondary';
                                                if ($percentage >= 100) $badgeClass = 'success';
                                                elseif ($percentage >= 90) $badgeClass = 'info';
                                                elseif ($percentage >= 70) $badgeClass = 'warning';
                                                else $badgeClass = 'danger';
                                                
                                                echo '<span class="badge badge-' . $badgeClass . '">' . number_format($percentage, 1) . '%</span>';
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr style="background: var(--light-color); font-weight: bold;">
                                        <td colspan="5" class="text-right">Итоговый KPI:</td>
                                        <td>
                                            <?php 
                                            $totalBadgeClass = 'secondary';
                                            if ($totalKPI >= 100) $totalBadgeClass = 'success';
                                            elseif ($totalKPI >= 80) $totalBadgeClass = 'info';
                                            elseif ($totalKPI >= 60) $totalBadgeClass = 'warning';
                                            else $totalBadgeClass = 'danger';
                                            ?>
                                            <span class="badge badge-<?php echo $totalBadgeClass; ?>">
                                                <?php echo number_format($totalKPI, 2); ?>%
                                            </span>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <?php else: ?>
                        <p class="text-center" style="padding: 40px;">
                            KPI показатели за выбранный период не установлены.<br>
                            Обратитесь к своему менеджеру.
                        </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- KPI History -->
                <div class="card">
                    <div class="card-header">
                        <h3>История KPI</h3>
                    </div>
                    <div class="card-body">
                        <?php 
                        // Генерируем список периодов за последние 12 месяцев
                        $periodKPIs = [];
                        for ($i = 0; $i < 12; $i++) {
                            $period = date('Y-m-01', strtotime("-$i months"));
                            $periodKPIs[$period] = $kpi->calculateTotalKPI($userId, $period);
                        }
                        
                        // Фильтруем периоды с KPI > 0
                        $periodKPIs = array_filter($periodKPIs, function($kpiValue) {
                            return $kpiValue > 0;
                        });
                        
                        if (!empty($periodKPIs)):
                        ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Период</th>
                                        <th>Итоговый KPI</th>
                                        <th>Тенденция</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $prevKPI = null;
                                    foreach ($periodKPIs as $period => $totalKPI): 
                                    ?>
                                    <tr>
                                        <td><?php echo date('m.Y', strtotime($period)); ?></td>
                                        <td>
                                            <strong><?php echo number_format($totalKPI, 2); ?>%</strong>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($prevKPI !== null) {
                                                $diff = $totalKPI - $prevKPI;
                                                if ($diff > 0) {
                                                    echo '<span style="color: var(--secondary-color);">↑ +' . number_format($diff, 2) . '</span>';
                                                } elseif ($diff < 0) {
                                                    echo '<span style="color: var(--danger-color);">↓ ' . number_format($diff, 2) . '</span>';
                                                } else {
                                                    echo '<span style="color: var(--text-light);">→ 0.00</span>';
                                                }
                                            } else {
                                                echo '-';
                                            }
                                            $prevKPI = $totalKPI;
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <p class="text-center" style="padding: 40px;">
                            История KPI отсутствует
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
