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
$reward = new Reward();

// Получаем тип периода
$periodType = $_GET['period_type'] ?? 'monthly';

// Получаем текущий период
$currentPeriod = date('Y-m-01');
$errorMessage = null;

// Автоматически рассчитываем вознаграждение для текущего периода, если оно еще не рассчитано
$currentReward = $reward->getEmployeeReward($userId, $currentPeriod, $periodType);
if (!$currentReward) {
    try {
        $reward->calculateAndSave($userId, $currentPeriod, $periodType);
        $currentReward = $reward->getEmployeeReward($userId, $currentPeriod, $periodType);
    } catch (Exception $e) {
        // Сохраняем сообщение об ошибке
        $errorMessage = $e->getMessage();
    }
}

// Получаем историю вознаграждений и автоматически рассчитываем для последних 12 месяцев
$rewardHistory = $reward->getEmployeeHistory($userId, 12, $periodType);

// Если история пустая, попробуем рассчитать за последние месяцы
if (empty($rewardHistory)) {
    for ($i = 0; $i < 12; $i++) {
        $period = date('Y-m-01', strtotime("-$i months"));
        try {
            $existing = $reward->getEmployeeReward($userId, $period, $periodType);
            if (!$existing) {
                $reward->calculateAndSave($userId, $period, $periodType);
            }
        } catch (Exception $e) {
            // Пропускаем периоды с ошибками
            continue;
        }
    }
    // Повторно получаем историю
    $rewardHistory = $reward->getEmployeeHistory($userId, 12, $periodType);
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
    <title>Мои вознаграждения - <?php echo APP_NAME; ?></title>
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
                <li><a href="<?php echo APP_URL; ?>/modules/kpi/my_kpi.php"><span class="icon">📈</span> Мои KPI</a></li>
                <li><a href="my_rewards.php" class="active"><span class="icon">💰</span> Вознаграждения</a></li>
                <li><a href="<?php echo APP_URL; ?>/auth/logout.php"><span class="icon">🚪</span> Выход</a></li>
            </ul>
        </aside>

        <div class="main-content">
            <div class="topbar">
                <h1>Мои вознаграждения</h1>
                <div class="user-menu">
                    <div class="user-info">
                        <div class="name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <div class="role">Сотрудник</div>
                    </div>
                </div>
            </div>

            <div class="content">
                <?php if (isset($errorMessage)): ?>
                <div class="alert alert-warning" style="margin-bottom: 20px; padding: 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; color: #856404;">
                    <strong>Внимание:</strong> <?php echo htmlspecialchars($errorMessage); ?>
                </div>
                <?php endif; ?>
                
                <!-- Информация о базовой зарплате -->
                <div class="card" style="margin-bottom: 20px;">
                    <div class="card-body" style="padding: 15px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong>Базовая зарплата:</strong> 
                                <?php echo number_format($user['base_salary'] ?? 0, 2, ',', ' '); ?> ₽
                            </div>
                            <div>
                                <strong>Отдел:</strong> 
                                <?php 
                                $dept = Database::getInstance()->fetchOne(
                                    "SELECT d.name FROM departments d JOIN employees e ON e.department_id = d.id WHERE e.id = ?", 
                                    [$userId]
                                );
                                echo htmlspecialchars($dept['name'] ?? 'Не назначен');
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Period Type Selector -->
                <div style="margin-bottom: 20px;">
                    <form method="GET" style="display: inline-flex; gap: 10px; align-items: center;">
                        <label for="period_type" style="font-weight: bold;">Тип вознаграждения:</label>
                        <select name="period_type" id="period_type" onchange="this.form.submit()" 
                                style="padding: 8px 12px; border-radius: 4px; border: 1px solid var(--border-color);">
                            <?php foreach ($periodTypes as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo $key === $periodType ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>

                <!-- Current Reward -->
                <div class="card">
                    <div class="card-header">
                        <h3><?php echo $periodTypes[$periodType]; ?> вознаграждение за <?php echo date('m.Y', strtotime($currentPeriod)); ?></h3>
                    </div>
                    <div class="card-body">
                        <?php if ($currentReward): ?>
                        <div style="text-align: center; padding: 40px;">
                            <div style="font-size: 48px; font-weight: bold; color: var(--primary-color); margin-bottom: 16px;">
                                <?php echo number_format($currentReward['total_amount'], 2, ',', ' '); ?> ₽
                            </div>
                            <div style="font-size: 18px; color: var(--text-light); margin-bottom: 32px;">
                                На основе KPI: <strong><?php echo number_format($currentReward['kpi_total'], 2); ?></strong>
                            </div>
                            <div class="stats-grid" style="max-width: 800px; margin: 0 auto; grid-template-columns: repeat(3, 1fr);">
                                <div style="background: var(--light-color); padding: 20px; border-radius: 8px;">
                                    <div style="font-size: 14px; color: var(--text-light); margin-bottom: 8px;">
                                        Базовая зарплата
                                    </div>
                                    <div style="font-size: 24px; font-weight: bold;">
                                        <?php echo number_format($currentReward['base_salary'], 2, ',', ' '); ?> ₽
                                    </div>
                                </div>
                                <div style="background: var(--light-color); padding: 20px; border-radius: 8px;">
                                    <div style="font-size: 14px; color: var(--text-light); margin-bottom: 8px;">
                                        Бонус за KPI
                                    </div>
                                    <div style="font-size: 24px; font-weight: bold; color: var(--secondary-color);">
                                        +<?php echo number_format($currentReward['bonus_amount'], 2, ',', ' '); ?> ₽
                                    </div>
                                </div>
                                <div style="background: var(--light-color); padding: 20px; border-radius: 8px;">
                                    <div style="font-size: 14px; color: var(--text-light); margin-bottom: 8px;">
                                        Процент прибавки
                                    </div>
                                    <div style="font-size: 24px; font-weight: bold; color: var(--info-color);">
                                        <?php echo number_format(($currentReward['bonus_amount'] / $currentReward['base_salary']) * 100, 1); ?>%
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div style="padding: 40px; text-align: center;">
                            <p style="font-size: 18px; margin-bottom: 20px;">
                                <?php echo $periodTypes[$periodType]; ?> вознаграждение за текущий период ещё не рассчитано.
                            </p>
                            <?php if ($errorMessage): ?>
                            <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 15px; margin: 20px auto; max-width: 600px; text-align: left;">
                                <strong>Причина:</strong><br>
                                <?php echo htmlspecialchars($errorMessage); ?>
                            </div>
                            <?php endif; ?>
                            <small style="color: var(--text-light);">
                                Вознаграждения рассчитываются автоматически при наличии:<br>
                                • Установленной базовой зарплаты<br>
                                • KPI показателей за период
                            </small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Reward History -->
                <div class="card">
                    <div class="card-header">
                        <h3>История вознаграждений (<?php echo $periodTypes[$periodType]; ?>)</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($rewardHistory)): ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Период</th>
                                        <th>Базовая зарплата</th>
                                        <th>KPI</th>
                                        <th>Бонус</th>
                                        <th>Итого</th>
                                        <th>Динамика</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $prevAmount = null;
                                    foreach ($rewardHistory as $r): 
                                    ?>
                                    <tr>
                                        <td><?php echo date('F Y', strtotime($r['period'])); ?></td>
                                        <td><?php echo number_format($r['base_salary'], 2, ',', ' '); ?> ₽</td>
                                        <td>
                                            <span class="badge badge-<?php 
                                                echo $r['kpi_total'] >= 1.0 ? 'success' : 
                                                    ($r['kpi_total'] >= 0.8 ? 'info' : 
                                                    ($r['kpi_total'] >= 0.6 ? 'warning' : 'danger')); 
                                            ?>">
                                                <?php echo number_format($r['kpi_total'], 2); ?>
                                            </span>
                                        </td>
                                        <td style="color: var(--secondary-color);">
                                            +<?php echo number_format($r['bonus_amount'], 2, ',', ' '); ?> ₽
                                        </td>
                                        <td>
                                            <strong><?php echo number_format($r['total_amount'], 2, ',', ' '); ?> ₽</strong>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($prevAmount !== null) {
                                                $diff = $r['total_amount'] - $prevAmount;
                                                if ($diff > 0) {
                                                    echo '<span style="color: var(--secondary-color);">↑ +' . number_format($diff, 2, ',', ' ') . ' ₽</span>';
                                                } elseif ($diff < 0) {
                                                    echo '<span style="color: var(--danger-color);">↓ ' . number_format($diff, 2, ',', ' ') . ' ₽</span>';
                                                } else {
                                                    echo '<span style="color: var(--text-light);">→ 0.00 ₽</span>';
                                                }
                                            } else {
                                                echo '-';
                                            }
                                            $prevAmount = $r['total_amount'];
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr style="background: var(--light-color); font-weight: bold;">
                                        <td>Среднее:</td>
                                        <td>
                                            <?php 
                                            $avgBaseSalary = array_sum(array_column($rewardHistory, 'base_salary')) / count($rewardHistory);
                                            echo number_format($avgBaseSalary, 2, ',', ' ') . ' ₽';
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $avgKPI = array_sum(array_column($rewardHistory, 'kpi_total')) / count($rewardHistory);
                                            echo number_format($avgKPI, 2);
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $avgBonus = array_sum(array_column($rewardHistory, 'bonus_amount')) / count($rewardHistory);
                                            echo number_format($avgBonus, 2, ',', ' ') . ' ₽';
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $avgReward = array_sum(array_column($rewardHistory, 'total_amount')) / count($rewardHistory);
                                            echo number_format($avgReward, 2, ',', ' ') . ' ₽';
                                            ?>
                                        </td>
                                        <td>-</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <?php else: ?>
                        <p class="text-center" style="padding: 40px;">
                            История вознаграждений отсутствует
                        </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Info -->
                <div class="card">
                    <div class="card-header">
                        <h3>ℹ️ Как рассчитывается вознаграждение?</h3>
                    </div>
                    <div class="card-body">
                        <p style="margin-bottom: 16px;">
                            Вознаграждение рассчитывается по следующей формуле:
                        </p>
                        <div style="background: var(--light-color); padding: 20px; border-radius: 8px; font-family: monospace; margin-bottom: 16px;">
                            <strong>Бонус = Базовая_зарплата × KPI_total × Множитель_периода</strong><br>
                            <strong>Итого = Базовая_зарплата + Бонус</strong>
                        </div>
                        <p style="margin-bottom: 8px;"><strong>Множители периодов:</strong></p>
                        <ul style="margin-left: 20px; margin-bottom: 16px;">
                            <li><strong>Месячные</strong> - множитель 1.0</li>
                            <li><strong>Квартальные</strong> - множитель 3.0</li>
                            <li><strong>Годовые</strong> - множитель 12.0</li>
                        </ul>
                        <p style="margin-bottom: 8px;">Где:</p>
                        <ul style="margin-left: 20px;">
                            <li><strong>Базовая_зарплата</strong> - ваша индивидуальная базовая зарплата</li>
                            <li><strong>KPI_total</strong> - итоговый KPI, рассчитанный на основе ваших показателей</li>
                        </ul>
                        <p style="margin-top: 16px;">
                            <strong>Пример (месячное вознаграждение):</strong><br>
                            Базовая зарплата: 50,000 ₽, KPI = 0.85<br>
                            Бонус = 50,000 × 0.85 × 1.0 = 42,500 ₽<br>
                            Итого = 50,000 + 42,500 = <strong style="color: var(--primary-color);">92,500 ₽</strong>
                        </p>
                        <p style="margin-top: 16px;">
                            <strong>Пример (квартальное вознаграждение):</strong><br>
                            Базовая зарплата: 50,000 ₽, KPI = 0.85<br>
                            Бонус = 50,000 × 0.85 × 3.0 = 127,500 ₽<br>
                            Итого = 50,000 + 127,500 = <strong style="color: var(--primary-color);">177,500 ₽</strong>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
