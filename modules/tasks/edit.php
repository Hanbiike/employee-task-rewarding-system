<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Task.php';
require_once __DIR__ . '/../../classes/Project.php';

if (!User::isAuthenticated() || !User::hasRole('Manager')) {
    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

$taskId = $_GET['id'] ?? null;
if (!$taskId) {
    header('Location: list.php');
    exit;
}

$user = User::getCurrentUser();
$role = User::getCurrentRole();
$userId = User::getCurrentUserId();
$task = new Task();
$project = new Project();
$db = Database::getInstance();

// Получаем данные задачи
$taskData = $task->getById($taskId);
if (!$taskData) {
    header('Location: list.php');
    exit;
}

// Проверяем права: CEO или создатель задачи
if ($role !== 'CEO' && $taskData['created_by_manager_id'] != $userId) {
    header('Location: view.php?id=' . $taskId);
    exit;
}

// Получаем проекты менеджера
if ($role === 'CEO') {
    $projects = $project->getAll();
} else {
    $projects = $project->getByManager($userId);
}

// Получаем список сотрудников
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
        [$user['department_id'], $userId]
    );
}

// Получаем текущих исполнителей задачи
$currentEmployees = $task->getTaskEmployees($taskId);
$currentEmployeeIds = array_column($currentEmployees, 'id');

$error = null;
$success = null;

// Обработка формы обновления задачи
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_task'])) {
    if (!User::verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Ошибка безопасности. Обновите страницу и попробуйте снова.';
    } else {
        $data = [
            'name' => trim($_POST['name']),
            'description' => trim($_POST['description']),
            'deadline' => $_POST['deadline'] ?: null,
            'status' => $_POST['status'],
            'importance' => $_POST['importance'],
            'end_date' => $_POST['end_date'] ?: null,
            'employee_ids' => $_POST['employee_ids'] ?? []
        ];
        
        if (empty($data['name'])) {
            $error = 'Название задачи обязательно';
        } elseif (empty($data['employee_ids'])) {
            $error = 'Назначьте хотя бы одного исполнителя';
        } elseif (!empty($data['deadline']) && strtotime($data['deadline']) < strtotime(date('Y-m-d', strtotime($taskData['created_at'])))) {
            $error = 'Дедлайн должен быть не раньше даты создания задачи';
        } else {
            try {
                $task->update($taskId, $data);
                $task->assignEmployees($taskId, $data['employee_ids']);
                $success = 'Задача успешно обновлена!';
                
                // Обновляем данные задачи
                $taskData = $task->getById($taskId);
                $currentEmployees = $task->getTaskEmployees($taskId);
                $currentEmployeeIds = array_column($currentEmployees, 'id');
            } catch (Exception $e) {
                $error = 'Ошибка при обновлении задачи: ' . $e->getMessage();
            }
        }
    }
}

// Обработка удаления
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_task'])) {
    if (!User::verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Ошибка безопасности. Обновите страницу и попробуйте снова.';
    } else {
        try {
            $task->delete($taskId);
            header('Location: list.php?deleted=1');
            exit;
        } catch (Exception $e) {
            $error = 'Ошибка при удалении задачи: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактировать задачу - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <style>
        .delete-section {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px solid var(--border-color);
        }
        .delete-warning {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
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
                <li><a href="<?php echo APP_URL; ?>/modules/projects/list.php"><span class="icon">📁</span> Проекты</a></li>
                <li><a href="list.php" class="active"><span class="icon">✓</span> Задачи</a></li>
                <?php if ($role === 'CEO'): ?>
                <li><a href="<?php echo APP_URL; ?>/modules/employees/list.php"><span class="icon">👥</span> Сотрудники</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/managers/list.php"><span class="icon">👔</span> Менеджеры</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/departments/list.php"><span class="icon">🏢</span> Отделы</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/kpi/list.php"><span class="icon">📈</span> KPI</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/rewards/list.php"><span class="icon">💰</span> Вознаграждения</a></li>
                <?php elseif ($role === 'Manager'): ?>
                <li><a href="<?php echo APP_URL; ?>/modules/employees/list.php"><span class="icon">👥</span> Сотрудники</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/kpi/employees.php"><span class="icon">📈</span> KPI команды</a></li>
                <?php endif; ?>
                <li><a href="<?php echo APP_URL; ?>/auth/logout.php"><span class="icon">🚪</span> Выход</a></li>
            </ul>
        </aside>

        <div class="main-content">
            <div class="topbar">
                <h1>Редактировать задачу</h1>
                <div class="user-menu">
                    <div class="user-info">
                        <div class="name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <div class="role"><?php echo $role; ?></div>
                    </div>
                </div>
            </div>

            <div class="content">
                <div class="card">
                    <div class="card-header">
                        <h3><?php echo htmlspecialchars($taskData['name']); ?></h3>
                        <div class="flex gap-2">
                            <a href="view.php?id=<?php echo $taskId; ?>" class="btn btn-secondary btn-sm">Просмотр</a>
                            <a href="list.php" class="btn btn-secondary btn-sm">← Назад</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo User::getCsrfToken(); ?>">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="name">Название задачи *</label>
                                    <input type="text" id="name" name="name" required 
                                           value="<?php echo htmlspecialchars($taskData['name']); ?>"
                                           placeholder="Например: Разработать модуль авторизации">
                                </div>
                                
                                <div class="form-group">
                                    <label>Проект</label>
                                    <input type="text" readonly 
                                           value="<?php echo htmlspecialchars($taskData['project_name']); ?>"
                                           style="background-color: #f5f5f5; cursor: not-allowed;">
                                    <small style="color: var(--text-light);">Проект задачи нельзя изменить</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="status">Статус</label>
                                    <select id="status" name="status">
                                        <option value="Not Started" <?php echo $taskData['status'] === 'Not Started' ? 'selected' : ''; ?>>Не начата</option>
                                        <option value="In Progress" <?php echo $taskData['status'] === 'In Progress' ? 'selected' : ''; ?>>В работе</option>
                                        <option value="On Moderation" <?php echo $taskData['status'] === 'On Moderation' ? 'selected' : ''; ?>>На модерации</option>
                                        <option value="Completed" <?php echo $taskData['status'] === 'Completed' ? 'selected' : ''; ?>>Завершена</option>
                                        <option value="Frozen" <?php echo $taskData['status'] === 'Frozen' ? 'selected' : ''; ?>>Заморожена</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="importance">Важность</label>
                                    <select id="importance" name="importance">
                                        <option value="Low" <?php echo $taskData['importance'] === 'Low' ? 'selected' : ''; ?>>Низкая</option>
                                        <option value="Medium" <?php echo $taskData['importance'] === 'Medium' ? 'selected' : ''; ?>>Средняя</option>
                                        <option value="High" <?php echo $taskData['importance'] === 'High' ? 'selected' : ''; ?>>Высокая</option>
                                        <option value="Critical" <?php echo $taskData['importance'] === 'Critical' ? 'selected' : ''; ?>>Критическая</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="deadline">Срок выполнения</label>
                                    <input type="date" id="deadline" name="deadline" 
                                           value="<?php echo $taskData['deadline'] ? date('Y-m-d', strtotime($taskData['deadline'])) : ''; ?>"
                                           min="<?php echo date('Y-m-d'); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="end_date">Дата завершения</label>
                                    <input type="date" id="end_date" name="end_date" 
                                           value="<?php echo $taskData['end_date'] ? date('Y-m-d', strtotime($taskData['end_date'])) : ''; ?>">
                                    <small style="color: var(--text-light);">Заполняется при завершении задачи</small>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Описание</label>
                                <textarea id="description" name="description" rows="4" 
                                          placeholder="Подробное описание задачи..."><?php echo htmlspecialchars($taskData['description']); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Назначить исполнителей *</label>
                                <div style="max-height: 250px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 8px; padding: 12px;">
                                    <?php if (empty($employees)): ?>
                                        <p style="color: var(--text-light); text-align: center; padding: 20px;">
                                            Нет доступных сотрудников
                                        </p>
                                    <?php else: ?>
                                        <?php foreach ($employees as $emp): ?>
                                        <div style="margin-bottom: 8px;">
                                            <label style="display: flex; align-items: center; cursor: pointer;">
                                                <input type="checkbox" name="employee_ids[]" value="<?php echo $emp['id']; ?>" 
                                                       style="margin-right: 8px;"
                                                       <?php echo in_array($emp['id'], $currentEmployeeIds) ? 'checked' : ''; ?>>
                                                <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?>
                                                <span style="margin-left: 8px; font-size: 12px; color: var(--text-light);">
                                                    (<?php echo htmlspecialchars($emp['department_name']); ?>)
                                                </span>
                                            </label>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <small style="color: var(--text-light);">Выберите хотя бы одного исполнителя</small>
                            </div>
                            
                            <div class="flex gap-2 mt-3">
                                <button type="submit" name="update_task" class="btn btn-primary">Сохранить изменения</button>
                                <a href="view.php?id=<?php echo $taskId; ?>" class="btn btn-secondary">Отмена</a>
                            </div>
                        </form>
                        
                        <!-- Секция удаления -->
                        <div class="delete-section">
                            <h4 style="color: #dc3545; margin-bottom: 15px;">⚠️ Опасная зона</h4>
                            <div class="delete-warning">
                                <strong>Внимание!</strong> Удаление задачи приведет к удалению всех назначений исполнителей. 
                                Это действие необратимо.
                            </div>
                            <form method="POST" action="" onsubmit="return confirm('Вы уверены, что хотите удалить эту задачу? Все назначения исполнителей будут удалены. Это действие необратимо!');">
                                <input type="hidden" name="csrf_token" value="<?php echo User::getCsrfToken(); ?>">
                                <button type="submit" name="delete_task" class="btn btn-danger">Удалить задачу</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Автоматически устанавливаем дату завершения при выборе статуса "Завершена"
        document.getElementById('status').addEventListener('change', function() {
            const endDateInput = document.getElementById('end_date');
            if (this.value === 'Completed' && !endDateInput.value) {
                endDateInput.value = new Date().toISOString().split('T')[0];
            }
        });
        
        // Валидация дедлайна
        document.addEventListener('DOMContentLoaded', function() {
            const deadlineInput = document.getElementById('deadline');
            const form = document.querySelector('form:not([onsubmit])'); // Основная форма, не форма удаления
            const createdAt = '<?php echo date('Y-m-d', strtotime($taskData['created_at'])); ?>';
            
            // Устанавливаем минимальную дату дедлайна равной дате создания задачи
            deadlineInput.setAttribute('min', createdAt);
            
            // Валидация при изменении
            deadlineInput.addEventListener('change', function() {
                if (this.value && this.value < createdAt) {
                    alert('Дедлайн должен быть не раньше даты создания задачи (' + createdAt + ')');
                    this.value = '';
                }
            });
            
            // Валидация при отправке формы
            form.addEventListener('submit', function(e) {
                if (deadlineInput.value && deadlineInput.value < createdAt) {
                    e.preventDefault();
                    alert('Дедлайн должен быть не раньше даты создания задачи (' + createdAt + ')');
                    deadlineInput.focus();
                }
            });
        });
    </script>
</body>
</html>
