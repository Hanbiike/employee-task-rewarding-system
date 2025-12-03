<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/KPI.php';

if (!User::isAuthenticated() || !User::hasRole('CEO')) {
    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

$user = User::getCurrentUser();
$role = User::getCurrentRole();
$kpi = new KPI();

$error = null;
$success = null;

// Обработка создания нового индикатора
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_indicator'])) {
    if (!User::verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Ошибка безопасности. Обновите страницу.';
    } else {
        $data = [
            'name' => trim($_POST['name']),
            'description' => trim($_POST['description']),
            'target_value' => floatval($_POST['target_value']),
            'weight' => floatval($_POST['weight']),
            'measurement_unit' => trim($_POST['measurement_unit'])
        ];
        
        if (empty($data['name'])) {
            $error = 'Название индикатора обязательно';
        } elseif ($data['target_value'] <= 0) {
            $error = 'Целевое значение должно быть больше 0';
        } elseif ($data['weight'] < 0 || $data['weight'] > 100) {
            $error = 'Вес должен быть от 0 до 100';
        } else {
            try {
                $kpi->createIndicator($data);
                $success = 'Индикатор KPI успешно создан!';
            } catch (Exception $e) {
                $error = 'Ошибка при создании: ' . $e->getMessage();
            }
        }
    }
}

// Обработка удаления индикатора
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_indicator'])) {
    if (!User::verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Ошибка безопасности';
    } else {
        try {
            $kpi->deleteIndicator($_POST['indicator_id']);
            $success = 'Индикатор удалён';
        } catch (Exception $e) {
            $error = 'Ошибка при удалении: ' . $e->getMessage();
        }
    }
}

// Обработка обновления индикатора
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_indicator'])) {
    if (!User::verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Ошибка безопасности';
    } else {
        $updateData = [
            'name' => trim($_POST['name']),
            'description' => trim($_POST['description']),
            'target_value' => floatval($_POST['target_value']),
            'weight' => floatval($_POST['weight']),
            'measurement_unit' => trim($_POST['measurement_unit'])
        ];
        
        try {
            $kpi->updateIndicator($_POST['indicator_id'], $updateData);
            $success = 'Индикатор обновлён';
        } catch (Exception $e) {
            $error = 'Ошибка при обновлении: ' . $e->getMessage();
        }
    }
}

// Получаем все индикаторы
$indicators = $kpi->getAllIndicators();

// Проверяем сумму весов
$totalWeight = array_sum(array_column($indicators, 'weight'));
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление KPI - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <style>
        .indicator-card {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            transition: all 0.3s;
        }
        .indicator-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .indicator-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 12px;
        }
        .indicator-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 4px;
        }
        .indicator-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-top: 12px;
        }
        .stat-item {
            background: var(--light-color);
            padding: 12px;
            border-radius: 8px;
        }
        .stat-label {
            font-size: 12px;
            color: var(--text-light);
            margin-bottom: 4px;
        }
        .stat-value {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark-color);
        }
        .weight-warning {
            background: #fff3cd;
            border: 2px solid #ffc107;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .weight-success {
            background: #d4edda;
            border: 2px solid #28a745;
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
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
                <li><a href="<?php echo APP_URL; ?>/dashboard/ceo.php"><span class="icon">📊</span> Главная</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/projects/list.php"><span class="icon">📁</span> Проекты</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/tasks/list.php"><span class="icon">✓</span> Задачи</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/employees/list.php"><span class="icon">👥</span> Сотрудники</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/managers/list.php"><span class="icon">👔</span> Менеджеры</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/departments/list.php"><span class="icon">🏢</span> Отделы</a></li>
                <li><a href="list.php" class="active"><span class="icon">📈</span> KPI</a></li>
                <li><a href="employees.php"><span class="icon">👥</span> KPI команды</a></li>
                <li><a href="settings.php"><span class="icon">🔧</span> Настройки KPI</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/rewards/list.php"><span class="icon">💰</span> Вознаграждения</a></li>
                <li><a href="<?php echo APP_URL; ?>/auth/logout.php"><span class="icon">🚪</span> Выход</a></li>
            </ul>
        </aside>

        <div class="main-content">
            <div class="topbar">
                <h1>Управление индикаторами KPI</h1>
                <div class="user-menu">
                    <div class="user-info">
                        <div class="name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <div class="role"><?php echo $role; ?></div>
                    </div>
                </div>
            </div>

            <div class="content">
                <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <!-- Weight Check -->
                <?php if ($totalWeight != 100 && count($indicators) > 0): ?>
                <div class="weight-warning">
                    <strong>⚠️ Внимание!</strong> Сумма весов индикаторов = <strong><?php echo $totalWeight; ?>%</strong>. 
                    Для корректного расчета KPI сумма должна быть равна 100%.
                </div>
                <?php elseif ($totalWeight == 100): ?>
                <div class="weight-success">
                    <strong>✓ Отлично!</strong> Сумма весов индикаторов = 100%. Система готова к расчёту KPI.
                </div>
                <?php endif; ?>

                <!-- Create New Indicator -->
                <div class="card">
                    <div class="card-header">
                        <h3>Создать новый индикатор KPI</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo User::getCsrfToken(); ?>">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="name">Название индикатора *</label>
                                    <input type="text" id="name" name="name" required 
                                           placeholder="Например: Пунктуальность">
                                </div>
                                
                                <div class="form-group">
                                    <label for="measurement_unit">Единица измерения *</label>
                                    <input type="text" id="measurement_unit" name="measurement_unit" required 
                                           placeholder="%, балл, шт.">
                                </div>
                                
                                <div class="form-group">
                                    <label for="target_value">Целевое значение *</label>
                                    <input type="number" id="target_value" name="target_value" 
                                           step="0.01" min="0.01" required value="100">
                                </div>
                                
                                <div class="form-group">
                                    <label for="weight">Вес (%) *</label>
                                    <input type="number" id="weight" name="weight" 
                                           step="1" min="0" max="100" required value="25">
                                    <small>Сумма всех весов должна быть 100%</small>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Описание</label>
                                <textarea id="description" name="description" rows="3" 
                                          placeholder="Как измеряется этот показатель, критерии оценки..."></textarea>
                            </div>
                            
                            <button type="submit" name="create_indicator" class="btn btn-primary">
                                + Создать индикатор
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Indicators List -->
                <div class="card">
                    <div class="card-header">
                        <h3>Индикаторы KPI (<?php echo count($indicators); ?>)</h3>
                        <div>
                            <span class="badge badge-info">Общий вес: <?php echo $totalWeight; ?>%</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($indicators)): ?>
                        <p class="text-muted">Индикаторы KPI не созданы. Создайте первый индикатор выше.</p>
                        <?php else: ?>
                        
                        <?php foreach ($indicators as $ind): ?>
                        <div class="indicator-card">
                            <div class="indicator-header">
                                <div>
                                    <div class="indicator-title"><?php echo htmlspecialchars($ind['name']); ?></div>
                                    <?php if ($ind['description']): ?>
                                    <p style="color: var(--text-light); margin: 4px 0 0 0;">
                                        <?php echo htmlspecialchars($ind['description']); ?>
                                    </p>
                                    <?php endif; ?>
                                </div>
                                <div class="flex gap-2">
                                    <button onclick="editIndicator(<?php echo $ind['id']; ?>)" 
                                            class="btn btn-sm btn-primary">Изменить</button>
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('Удалить этот индикатор? Все связанные данные будут потеряны!')">
                                        <input type="hidden" name="csrf_token" value="<?php echo User::getCsrfToken(); ?>">
                                        <input type="hidden" name="indicator_id" value="<?php echo $ind['id']; ?>">
                                        <button type="submit" name="delete_indicator" class="btn btn-sm btn-danger">
                                            Удалить
                                        </button>
                                    </form>
                                </div>
                            </div>
                            
                            <div class="indicator-stats">
                                <div class="stat-item">
                                    <div class="stat-label">Целевое значение</div>
                                    <div class="stat-value">
                                        <?php echo $ind['target_value'] ?? 100; ?> <?php echo htmlspecialchars($ind['measurement_unit'] ?? $ind['unit'] ?? '%'); ?>
                                    </div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-label">Вес в общей оценке</div>
                                    <div class="stat-value"><?php echo $ind['weight']; ?>%</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-label">Создан</div>
                                    <div class="stat-value" style="font-size: 14px;">
                                        <?php 
                                        if (isset($ind['created_at']) && $ind['created_at']) {
                                            echo date('d.m.Y', strtotime($ind['created_at']));
                                        } else {
                                            echo 'Н/Д';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Редактировать индикатор</h3>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <form method="POST" id="editForm">
                <input type="hidden" name="csrf_token" value="<?php echo User::getCsrfToken(); ?>">
                <input type="hidden" name="indicator_id" id="edit_indicator_id">
                
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="edit_name">Название *</label>
                            <input type="text" id="edit_name" name="name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_measurement_unit">Единица измерения *</label>
                            <input type="text" id="edit_measurement_unit" name="measurement_unit" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_target_value">Целевое значение *</label>
                            <input type="number" id="edit_target_value" name="target_value" 
                                   step="0.01" min="0.01" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_weight">Вес (%) *</label>
                            <input type="number" id="edit_weight" name="weight" 
                                   step="1" min="0" max="100" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_description">Описание</label>
                        <textarea id="edit_description" name="description" rows="3"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" onclick="closeEditModal()" class="btn btn-secondary">Отмена</button>
                    <button type="submit" name="update_indicator" class="btn btn-primary">Сохранить</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const indicators = <?php echo json_encode($indicators); ?>;
        
        function editIndicator(id) {
            const indicator = indicators.find(i => i.id == id);
            if (!indicator) return;
            
            document.getElementById('edit_indicator_id').value = indicator.id;
            document.getElementById('edit_name').value = indicator.name;
            document.getElementById('edit_description').value = indicator.description || '';
            document.getElementById('edit_target_value').value = indicator.target_value || 100;
            document.getElementById('edit_weight').value = indicator.weight;
            document.getElementById('edit_measurement_unit').value = indicator.measurement_unit || indicator.unit || '%';
            
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Close modal on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                closeEditModal();
            }
        }
    </script>
</body>
</html>
