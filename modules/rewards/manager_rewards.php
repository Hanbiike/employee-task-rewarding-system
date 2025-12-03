<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/KPI.php';
require_once __DIR__ . '/../../classes/Reward.php';

if (!User::isAuthenticated() || !User::hasRole('Manager')) {
    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

$user = User::getCurrentUser();
$userId = User::getCurrentUserId();
$reward = new Reward();

// Получаем тип периода
$periodType = $_GET['period_type'] ?? 'monthly';

// Получаем историю вознаграждений менеджера
$rewardHistory = $reward->getManagerHistory($userId, 12, $periodType);

// Получаем текущий период
$currentPeriod = date('Y-m-01');
$currentReward = $reward->getManagerReward($userId, $currentPeriod, $periodType);

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
                <p>Manager Dashboard</p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="<?php echo APP_URL; ?>/dashboard/manager.php"><span class="icon">📊</span> Главная</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/employees/list.php"><span class="icon">👥</span> Сотрудники</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/projects/list.php"><span class="icon">📋</span> Проекты</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/tasks/list.php"><span class="icon">✓</span> Задачи</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/kpi/employees.php"><span class="icon">📈</span> KPI сотрудников</a></li>
                <li><a href="manager_rewards.php" class="active"><span class="icon">💰</span> Мои вознаграждения</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/rewards/list.php"><span class="icon">💵</span> Вознаграждения отдела</a></li>
                <li><a href="<?php echo APP_URL; ?>/auth/logout.php"><span class="icon">🚪</span> Выход</a></li>
            </ul>
        </aside>

        <div class="main-content">
            <div class="topbar">
                <h1>Мои вознаграждения</h1>
                <div class="user-menu">
                    <div class="user-info">
                        <div class="name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <div class="role">Менеджер</div>
                    </div>
                </div>
            </div>

            <div class="content">
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

                <!-- Info Block -->
                <div class="card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; margin-bottom: 20px;">
                    <div class="card-body">
                        <h3 style="color: white; margin-bottom: 10px;">ℹ️ Как рассчитывается вознаграждение менеджера</h3>
                        <p style="font-size: 16px; line-height: 1.6; margin: 0;">
                            Ваше вознаграждение = <strong>Базовая зарплата</strong> + <strong>Премия</strong><br>
                            <strong>Премия</strong> = (100 / Количество сотрудников в отделе) × Общая сумма премий сотрудников<br>
                            <em>Например: если в отделе 5 человек, вы получаете 20% от общей суммы премий сотрудников</em>
                        </p>
                    </div>
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
                                Отдел: <strong><?php echo htmlspecialchars($currentReward['department_name']); ?></strong>
                            </div>
                            <div class="stats-grid" style="max-width: 900px; margin: 0 auto; grid-template-columns: repeat(4, 1fr);">
                                <div style="background: var(--light-color); padding: 20px; border-radius: 8px;">
                                    <div style="font-size: 14px; color: var(--text-light); margin-bottom: 8px;">
                                        Базовая зарплата
                                    </div>
                                    <div style="font-size: 24px; font-weight: bold; color: var(--text-color);">
                                        <?php echo number_format($currentReward['base_salary'], 0, ',', ' '); ?> ₽
                                    </div>
                                </div>
                                <div style="background: var(--light-color); padding: 20px; border-radius: 8px;">
                                    <div style="font-size: 14px; color: var(--text-light); margin-bottom: 8px;">
                                        Сотрудников в отделе
                                    </div>
                                    <div style="font-size: 24px; font-weight: bold; color: var(--info-color);">
                                        <?php echo $currentReward['employees_count']; ?>
                                    </div>
                                </div>
                                <div style="background: var(--light-color); padding: 20px; border-radius: 8px;">
                                    <div style="font-size: 14px; color: var(--text-light); margin-bottom: 8px;">
                                        Процент от премий
                                    </div>
                                    <div style="font-size: 24px; font-weight: bold; color: var(--warning-color);">
                                        <?php echo number_format($currentReward['bonus_percentage'], 2); ?>%
                                    </div>
                                </div>
                                <div style="background: var(--light-color); padding: 20px; border-radius: 8px;">
                                    <div style="font-size: 14px; color: var(--text-light); margin-bottom: 8px;">
                                        Ваша премия
                                    </div>
                                    <div style="font-size: 24px; font-weight: bold; color: var(--success-color);">
                                        <?php echo number_format($currentReward['bonus_amount'], 0, ',', ' '); ?> ₽
                                    </div>
                                </div>
                            </div>
                            <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                                <p style="margin: 0; font-size: 16px; color: var(--text-light);">
                                    <strong>Общая сумма премий сотрудников:</strong> 
                                    <?php echo number_format($currentReward['total_employee_bonuses'], 2, ',', ' '); ?> ₽
                                </p>
                                <p style="margin: 10px 0 0 0; font-size: 14px; color: var(--text-light);">
                                    <em>Расчет: <?php echo number_format($currentReward['total_employee_bonuses'], 2, ',', ' '); ?> × 
                                    <?php echo number_format($currentReward['bonus_percentage'], 2); ?>% = 
                                    <?php echo number_format($currentReward['bonus_amount'], 2, ',', ' '); ?> ₽</em>
                                </p>
                            </div>
                        </div>
                        <?php else: ?>
                        <p class="text-center" style="padding: 60px;">
                            Вознаграждение за текущий период ещё не рассчитано.
                        </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- History -->
                <?php if (!empty($rewardHistory)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3>История вознаграждений</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Период</th>
                                        <th>Отдел</th>
                                        <th>Сотрудников</th>
                                        <th>Премии сотрудников</th>
                                        <th>Процент</th>
                                        <th>Базовая зарплата</th>
                                        <th>Премия</th>
                                        <th>Итого</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rewardHistory as $hist): ?>
                                    <tr>
                                        <td><?php echo date('m.Y', strtotime($hist['period'])); ?></td>
                                        <td><?php echo htmlspecialchars($hist['department_name']); ?></td>
                                        <td><?php echo $hist['employees_count']; ?></td>
                                        <td><?php echo number_format($hist['total_employee_bonuses'], 0, ',', ' '); ?> ₽</td>
                                        <td><?php echo number_format($hist['bonus_percentage'], 2); ?>%</td>
                                        <td><?php echo number_format($hist['base_salary'], 0, ',', ' '); ?> ₽</td>
                                        <td>
                                            <strong style="color: var(--success-color);">
                                                <?php echo number_format($hist['bonus_amount'], 0, ',', ' '); ?> ₽
                                            </strong>
                                        </td>
                                        <td>
                                            <strong style="color: var(--primary-color);">
                                                <?php echo number_format($hist['total_amount'], 0, ',', ' '); ?> ₽
                                            </strong>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr style="background: var(--light-color); font-weight: bold;">
                                        <td colspan="5">Всего за период:</td>
                                        <td>
                                            <?php 
                                            $totalBaseSalary = array_sum(array_column($rewardHistory, 'base_salary'));
                                            echo number_format($totalBaseSalary, 0, ',', ' '); 
                                            ?> ₽
                                        </td>
                                        <td>
                                            <?php 
                                            $totalBonus = array_sum(array_column($rewardHistory, 'bonus_amount'));
                                            echo number_format($totalBonus, 0, ',', ' '); 
                                            ?> ₽
                                        </td>
                                        <td>
                                            <?php 
                                            $totalReward = array_sum(array_column($rewardHistory, 'total_amount'));
                                            echo number_format($totalReward, 0, ',', ' '); 
                                            ?> ₽
                                        </td>
                                    </tr>
                                </tfoot>
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
