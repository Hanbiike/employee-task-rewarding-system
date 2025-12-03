<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/KPI.php';

if (!User::isAuthenticated() || !User::hasRole('Manager')) {
    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

$user = User::getCurrentUser();
$role = User::getCurrentRole();
$kpi = new KPI();
$db = Database::getInstance();

// Получаем текущий период
$selectedPeriod = $_GET['period'] ?? date('Y-m-01');
$selectedEmployee = $_GET['employee_id'] ?? null;

// Получаем список сотрудников отдела
if ($role === 'CEO') {
    $employees = $db->fetchAll(
        "SELECT e.*, d.name as department_name 
         FROM employees e 
         JOIN departments d ON e.department_id = d.id 
         ORDER BY e.last_name, e.first_name"
    );
} else {
    $employees = $db->fetchAll(
        "SELECT e.*, d.name as department_name 
         FROM employees e 
         JOIN departments d ON e.department_id = d.id 
         WHERE e.department_id = ? OR e.manager_id = ?
         ORDER BY e.last_name, e.first_name",
        [$user['department_id'], User::getCurrentUserId()]
    );
}

// Получаем все KPI показатели
$kpiIndicators = $kpi->getAllIndicators();

$success = null;
$error = null;

// Обработка установки значений KPI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_kpi'])) {
    if (!User::verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Ошибка безопасности';
    } else {
        $employeeId = $_POST['employee_id'];
        $period = $_POST['period'];
        $kpiValues = $_POST['kpi_values'] ?? [];
        
        try {
            foreach ($kpiValues as $kpiId => $value) {
                if ($value !== '') {
                    $kpi->setEmployeeValue($employeeId, $kpiId, floatval($value), $period);
                }
            }
            $success = 'KPI успешно сохранены!';
            $selectedEmployee = $employeeId;
            $selectedPeriod = $period;
        } catch (Exception $e) {
            $error = 'Ошибка при сохранении: ' . $e->getMessage();
        }
    }
}

// Получаем KPI для выбранного сотрудника
$employeeKPIValues = [];
$kpiBreakdown = null;
if ($selectedEmployee) {
    $currentValues = $kpi->getEmployeeValues($selectedEmployee, $selectedPeriod);
    foreach ($currentValues as $val) {
        $employeeKPIValues[$val['kpi_id']] = $val['value'];
    }
    
    // Получаем детализацию KPI
    $kpiBreakdown = $kpi->getKPIBreakdown($selectedEmployee, $selectedPeriod);
}

// Получаем статистику по команде
$teamKPI = $kpi->getAllEmployeesKPI($selectedPeriod, $role === 'CEO' ? null : $user['department_id']);

// Генерируем периоды
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
    <title>KPI команды - <?php echo APP_NAME; ?></title>
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
                <li><a href="list.php"><span class="icon">⚙️</span> Управление KPI</a></li>
                <li><a href="employees.php" class="active"><span class="icon">📈</span> KPI команды</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/rewards/list.php"><span class="icon">💰</span> Вознаграждения</a></li>
                <?php elseif ($role === 'Manager'): ?>
                <li><a href="<?php echo APP_URL; ?>/modules/employees/list.php"><span class="icon">👥</span> Сотрудники</a></li>
                <li><a href="employees.php" class="active"><span class="icon">📈</span> KPI команды</a></li>
                <?php endif; ?>
                <li><a href="<?php echo APP_URL; ?>/auth/logout.php"><span class="icon">🚪</span> Выход</a></li>
            </ul>
        </aside>

        <div class="main-content">
            <div class="topbar">
                <h1>KPI команды</h1>
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

                <!-- KPI Overview -->
                <div class="card">
                    <div class="card-header">
                        <h3>Обзор KPI команды за <?php echo date('m.Y', strtotime($selectedPeriod)); ?></h3>
                        <a href="<?php echo APP_URL; ?>/modules/export/export.php?type=employee_kpi&period=<?php echo urlencode($selectedPeriod); ?><?php echo $role === 'CEO' ? '' : '&department_id=' . $user['department_id']; ?>" class="btn btn-primary" style="background: #27ae60;">
                            📥 Экспорт в Excel
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Сотрудник</th>
                                        <th>Отдел</th>
                                        <th>Итоговый KPI</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teamKPI as $emp): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($emp['department_name']); ?></td>
                                        <td>
                                            <?php 
                                            $kpiClass = 'secondary';
                                            if ($emp['total_kpi'] >= 100) $kpiClass = 'success';
                                            elseif ($emp['total_kpi'] >= 80) $kpiClass = 'info';
                                            elseif ($emp['total_kpi'] >= 60) $kpiClass = 'warning';
                                            else $kpiClass = 'danger';
                                            ?>
                                            <span class="badge badge-<?php echo $kpiClass; ?>">
                                                <?php echo number_format($emp['total_kpi'], 2); ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <a href="?employee_id=<?php echo $emp['id']; ?>&period=<?php echo $selectedPeriod; ?>#edit-kpi" 
                                               class="btn btn-sm btn-primary">Установить KPI</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($teamKPI)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center">Нет сотрудников для отображения</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Edit KPI Form -->
                <div class="card" id="edit-kpi">
                    <div class="card-header">
                        <h3>Установка значений KPI</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo User::getCsrfToken(); ?>">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="employee_id">Сотрудник *</label>
                                    <select id="employee_id" name="employee_id" required 
                                            onchange="window.location.href='?employee_id='+this.value+'&period=<?php echo $selectedPeriod; ?>#edit-kpi'">
                                        <option value="">Выберите сотрудника</option>
                                        <?php foreach ($employees as $emp): ?>
                                        <option value="<?php echo $emp['id']; ?>" 
                                                <?php echo $selectedEmployee == $emp['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?>
                                            (<?php echo htmlspecialchars($emp['department_name']); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="period">Период *</label>
                                    <select id="period" name="period" required
                                            onchange="window.location.href='?employee_id=<?php echo $selectedEmployee; ?>&period='+this.value+'#edit-kpi'">
                                        <?php foreach ($periods as $p): ?>
                                        <option value="<?php echo $p; ?>" <?php echo $p === $selectedPeriod ? 'selected' : ''; ?>>
                                            <?php echo date('F Y', strtotime($p)); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <?php if ($selectedEmployee && !empty($kpiIndicators)): ?>
                            
                            <!-- Детализация KPI -->
                            <?php if ($kpiBreakdown): ?>
                            <div class="card" style="margin-bottom: 20px; background: #f8f9fa; border-left: 4px solid #007bff;">
                                <div class="card-body">
                                    <h4>📊 Детализация KPI сотрудника</h4>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
                                        <div>
                                            <h5>Выполнение задач</h5>
                                            <div style="background: white; padding: 15px; border-radius: 8px;">
                                                <p><strong>Процент выполнения:</strong> <?php echo $kpiBreakdown['tasks_kpi_percentage']; ?>%</p>
                                                <p><strong>Вес в общем KPI:</strong> <?php echo $kpiBreakdown['tasks_weight']; ?>%</p>
                                                <p><strong>Вклад в итоговый KPI:</strong> 
                                                    <span class="badge badge-info"><?php echo $kpiBreakdown['tasks_contribution']; ?></span>
                                                </p>
                                                
                                                <?php if (!empty($kpiBreakdown['tasks_data'])): ?>
                                                <hr>
                                                <p style="margin-bottom: 5px;"><strong>Детали по задачам:</strong></p>
                                                <?php foreach ($kpiBreakdown['tasks_data'] as $td): ?>
                                                <div style="margin: 5px 0; padding: 5px; background: #f8f9fa; border-radius: 4px;">
                                                    <span class="badge badge-<?php 
                                                        echo $td['importance'] == 'Low' ? 'secondary' : 
                                                            ($td['importance'] == 'Medium' ? 'info' : 
                                                            ($td['importance'] == 'High' ? 'warning' : 'danger')); 
                                                    ?>"><?php echo $td['importance']; ?></span>
                                                    (вес: <?php echo $td['importance_weight']; ?>):
                                                    <?php echo $td['completed_count']; ?>/<?php echo $td['task_count']; ?> задач 
                                                    (<?php echo $td['completed_weight_sum']; ?>/<?php echo $td['total_weight_sum']; ?> весов)
                                                </div>
                                                <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div>
                                            <h5>Оценка менеджера</h5>
                                            <div style="background: white; padding: 15px; border-radius: 8px;">
                                                <p><strong>Текущая оценка:</strong> <?php echo $kpiBreakdown['manager_evaluation']; ?></p>
                                                <p><strong>Вес в общем KPI:</strong> <?php echo $kpiBreakdown['manager_weight']; ?>%</p>
                                                <p><strong>Вклад в итоговый KPI:</strong> 
                                                    <span class="badge badge-success"><?php echo $kpiBreakdown['manager_contribution']; ?></span>
                                                </p>
                                                <hr>
                                                <p style="font-size: 0.9em; color: #6c757d;">
                                                    Оценка основана на глобальных метриках (пунктуальность, качество работы и др.), 
                                                    которые вы устанавливаете ниже.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div style="margin-top: 20px; padding: 15px; background: white; border-radius: 8px; text-align: center;">
                                        <h3 style="margin: 0;">
                                            Итоговый KPI: 
                                            <span class="badge badge-<?php 
                                                $total = $kpiBreakdown['total_kpi'];
                                                echo $total >= 1.0 ? 'success' : ($total >= 0.8 ? 'info' : ($total >= 0.6 ? 'warning' : 'danger'));
                                            ?>" style="font-size: 1.5em;">
                                                <?php echo $kpiBreakdown['total_kpi']; ?>
                                            </span>
                                        </h3>
                                        <p style="margin: 5px 0 0 0; color: #6c757d;">
                                            = (<?php echo $kpiBreakdown['tasks_kpi_percentage']; ?>% × <?php echo $kpiBreakdown['tasks_weight']; ?>%) 
                                            + (<?php echo $kpiBreakdown['manager_evaluation']; ?> × <?php echo $kpiBreakdown['manager_weight']; ?>%)
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mt-3">
                                <h4>Значения KPI показателей (глобальные метрики для оценки менеджера):</h4>
                                <div class="table-container mt-2">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Показатель</th>
                                                <th>Описание</th>
                                                <th>Вес</th>
                                                <th>Целевое значение</th>
                                                <th>Фактическое значение *</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($kpiIndicators as $indicator): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($indicator['name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($indicator['description']); ?></td>
                                                <td><?php echo $indicator['weight']; ?>%</td>
                                                <td><?php echo $indicator['target_value'] . ' ' . ($indicator['measurement_unit'] ?? $indicator['unit'] ?? '%'); ?></td>
                                                <td>
                                                    <input type="number" 
                                                           name="kpi_values[<?php echo $indicator['id']; ?>]" 
                                                           step="0.01" 
                                                           min="0"
                                                           placeholder="Введите значение"
                                                           value="<?php echo $employeeKPIValues[$indicator['id']] ?? ''; ?>"
                                                           style="width: 200px;">
                                                    <?php echo ($indicator['measurement_unit'] ?? $indicator['unit'] ?? '%'); ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="mt-3">
                                    <button type="submit" name="save_kpi" class="btn btn-primary">Сохранить KPI</button>
                                </div>
                            </div>
                            <?php elseif ($selectedEmployee): ?>
                            <div class="alert alert-warning mt-3">
                                KPI показатели не настроены. Обратитесь к CEO для создания показателей.
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
