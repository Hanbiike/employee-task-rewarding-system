<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/KPI.php';
require_once __DIR__ . '/../../classes/Task.php';

if (!User::isAuthenticated() || !User::hasRole('CEO')) {
    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

$user = User::getCurrentUser();
$kpi = new KPI();
$task = new Task();

$success = null;
$error = null;

// Получаем текущие настройки
$currentSettings = $kpi->getKPISettings();
$importanceWeights = $task->getImportanceWeights();

// Обработка обновления настроек KPI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_kpi_settings'])) {
    if (!User::verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Ошибка безопасности';
    } else {
        $tasksWeight = intval($_POST['tasks_weight']);
        $managerWeight = intval($_POST['manager_weight']);
        
        try {
            $kpi->updateKPISettings($tasksWeight, $managerWeight, User::getCurrentUserId());
            $success = 'Настройки KPI успешно обновлены!';
            $currentSettings = $kpi->getKPISettings();
        } catch (Exception $e) {
            $error = 'Ошибка при обновлении: ' . $e->getMessage();
        }
    }
}

// Обработка обновления весов важности задач
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_importance_weights'])) {
    if (!User::verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Ошибка безопасности';
    } else {
        $weights = $_POST['weights'] ?? [];
        
        try {
            foreach ($weights as $importance => $weight) {
                $task->updateImportanceWeight($importance, intval($weight));
            }
            $success = 'Веса важности задач успешно обновлены!';
            $importanceWeights = $task->getImportanceWeights();
        } catch (Exception $e) {
            $error = 'Ошибка при обновлении весов: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Настройки KPI - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <style>
        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .weight-slider {
            margin: 20px 0;
        }
        
        .weight-display {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            font-weight: bold;
        }
        
        .slider-container {
            position: relative;
            padding: 20px 0;
        }
        
        input[type="range"] {
            width: 100%;
            height: 8px;
            border-radius: 5px;
            background: #ddd;
            outline: none;
            opacity: 0.7;
            transition: opacity .2s;
        }
        
        input[type="range"]:hover {
            opacity: 1;
        }
        
        input[type="range"]::-webkit-slider-thumb {
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #007bff;
            cursor: pointer;
        }
        
        input[type="range"]::-moz-range-thumb {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #007bff;
            cursor: pointer;
        }
        
        .importance-table {
            width: 100%;
        }
        
        .importance-table td {
            padding: 10px;
        }
        
        .importance-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            color: white;
        }
        
        .importance-badge.Low { background-color: #6c757d; }
        .importance-badge.Medium { background-color: #17a2b8; }
        .importance-badge.High { background-color: #ffc107; color: #000; }
        .importance-badge.Critical { background-color: #dc3545; }
    </style>
</head>
<body>
    <div class="dashboard">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><?php echo APP_NAME; ?></h2>
                <p>CEO Dashboard</p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="<?php echo APP_URL; ?>/dashboard/ceo.php"><span class="icon">📊</span> Главная</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/projects/list.php"><span class="icon">📁</span> Проекты</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/tasks/list.php"><span class="icon">✓</span> Задачи</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/employees/list.php"><span class="icon">👥</span> Сотрудники</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/managers/list.php"><span class="icon">👔</span> Менеджеры</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/departments/list.php"><span class="icon">🏢</span> Отделы</a></li>
                <li><a href="list.php"><span class="icon">⚙️</span> Управление KPI</a></li>
                <li><a href="employees.php"><span class="icon">📈</span> KPI команды</a></li>
                <li><a href="settings.php" class="active"><span class="icon">🔧</span> Настройки KPI</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/rewards/list.php"><span class="icon">💰</span> Вознаграждения</a></li>
                <li><a href="<?php echo APP_URL; ?>/auth/logout.php"><span class="icon">🚪</span> Выход</a></li>
            </ul>
        </aside>

        <div class="main-content">
            <div class="topbar">
                <h1>Настройки системы KPI</h1>
                <div class="user-menu">
                    <div class="user-info">
                        <div class="name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <div class="role">CEO</div>
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

                <div class="settings-grid">
                    <!-- Настройка распределения весов KPI -->
                    <div class="card">
                        <div class="card-header">
                            <h3>Распределение весов в расчете KPI</h3>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">
                                Итоговый KPI = (Выполнение задач × N%) + (Оценка менеджера × (100-N)%)
                            </p>
                            
                            <form method="POST" action="" id="kpiSettingsForm">
                                <input type="hidden" name="csrf_token" value="<?php echo User::getCsrfToken(); ?>">
                                <input type="hidden" name="update_kpi_settings" value="1">
                                
                                <div class="weight-slider">
                                    <label>Процент влияния выполненных задач (N%)</label>
                                    <div class="slider-container">
                                        <input type="range" name="tasks_weight" id="tasksWeightSlider" 
                                               min="0" max="100" value="<?php echo $currentSettings['tasks_weight_percentage']; ?>" 
                                               step="5">
                                    </div>
                                    <div class="weight-display">
                                        <span>Задачи: <span id="tasksWeightValue"><?php echo $currentSettings['tasks_weight_percentage']; ?></span>%</span>
                                        <span>Оценка менеджера: <span id="managerWeightValue"><?php echo $currentSettings['manager_evaluation_percentage']; ?></span>%</span>
                                    </div>
                                    <input type="hidden" name="manager_weight" id="managerWeightInput" value="<?php echo $currentSettings['manager_evaluation_percentage']; ?>">
                                </div>

                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary">Сохранить настройки</button>
                                </div>
                            </form>

                            <div class="info-box" style="margin-top: 20px; padding: 15px; background: #e7f3ff; border-left: 4px solid #007bff;">
                                <h4>Как это работает:</h4>
                                <ul style="margin: 10px 0; padding-left: 20px;">
                                    <li><strong>Выполнение задач</strong>: рассчитывается как (сумма весов выполненных задач) / (сумма весов всех задач)</li>
                                    <li><strong>Оценка менеджера</strong>: глобальные метрики (пунктуальность, качество работы и др.), устанавливаемые менеджером</li>
                                    <li><strong>Премия менеджера</strong>: Базовая зарплата × (Средний KPI отдела / 100)</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Настройка весов важности задач -->
                    <div class="card">
                        <div class="card-header">
                            <h3>Веса важности задач</h3>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">
                                Установите вес для каждого уровня важности задачи. Эти веса используются при расчете процента выполнения задач.
                            </p>
                            
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token" value="<?php echo User::getCsrfToken(); ?>">
                                <input type="hidden" name="update_importance_weights" value="1">
                                
                                <table class="importance-table">
                                    <?php foreach ($importanceWeights as $iw): ?>
                                    <tr>
                                        <td>
                                            <span class="importance-badge <?php echo $iw['importance']; ?>">
                                                <?php echo $iw['importance']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <label>Вес:</label>
                                        </td>
                                        <td>
                                            <input type="number" name="weights[<?php echo $iw['importance']; ?>]" 
                                                   value="<?php echo $iw['weight']; ?>" 
                                                   min="1" max="10" step="1" class="form-input" 
                                                   style="width: 80px;">
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </table>

                                <div class="form-group" style="margin-top: 20px;">
                                    <button type="submit" class="btn btn-primary">Сохранить веса</button>
                                </div>
                            </form>

                            <div class="info-box" style="margin-top: 20px; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107;">
                                <h4>Пример расчета:</h4>
                                <p>Если у сотрудника было 5 задач:</p>
                                <ul style="margin: 10px 0; padding-left: 20px;">
                                    <li>2 Low (вес 1) = 2</li>
                                    <li>2 Medium (вес 2) = 4</li>
                                    <li>1 Critical (вес 5) = 5</li>
                                </ul>
                                <p><strong>Всего вес:</strong> 11</p>
                                <p>Если выполнены все задачи кроме одной Medium:<br>
                                <strong>Выполнено вес:</strong> 9 (2+2+5)<br>
                                <strong>KPI по задачам:</strong> 9/11 × 100 = 81.8%</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Синхронизация слайдера
        const slider = document.getElementById('tasksWeightSlider');
        const tasksWeightDisplay = document.getElementById('tasksWeightValue');
        const managerWeightDisplay = document.getElementById('managerWeightValue');
        const managerWeightInput = document.getElementById('managerWeightInput');

        slider.addEventListener('input', function() {
            const tasksWeight = parseInt(this.value);
            const managerWeight = 100 - tasksWeight;
            
            tasksWeightDisplay.textContent = tasksWeight;
            managerWeightDisplay.textContent = managerWeight;
            managerWeightInput.value = managerWeight;
        });
    </script>
</body>
</html>
