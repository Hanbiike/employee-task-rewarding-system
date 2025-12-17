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

$user = User::getCurrentUser();
$role = User::getCurrentRole();
$userId = User::getCurrentUserId();
$task = new Task();
$project = new Project();
$db = Database::getInstance();

// Получаем ID проекта из URL (если есть)
$preselectedProjectId = $_GET['project_id'] ?? null;

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

$error = null;
$success = null;

// Обработка формы создания задачи
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_task'])) {
    if (!User::verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Ошибка безопасности. Обновите страницу и попробуйте снова.';
    } else {
        $data = [
            'name' => trim($_POST['name']),
            'description' => trim($_POST['description']),
            'project_id' => $_POST['project_id'],
            'deadline' => $_POST['deadline'] ?: null,
            'status' => $_POST['status'],
            'importance' => $_POST['importance'],
            'created_by_manager_id' => $userId,
            'employee_ids' => $_POST['employee_ids'] ?? []
        ];
        
        if (empty($data['name'])) {
            $error = 'Название задачи обязательно';
        } elseif (empty($data['project_id'])) {
            $error = 'Выберите проект';
        } elseif (empty($data['employee_ids'])) {
            $error = 'Назначьте хотя бы одного исполнителя';
        } elseif (!empty($data['deadline']) && strtotime($data['deadline']) < strtotime(date('Y-m-d'))) {
            $error = 'Дедлайн должен быть не раньше сегодняшней даты';
        } else {
            try {
                $taskId = $task->create($data);
                $success = 'Задача успешно создана!';
                header('Location: view.php?id=' . $taskId);
                exit;
            } catch (Exception $e) {
                $error = 'Ошибка при создании задачи: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Создать задачу - <?php echo APP_NAME; ?></title>
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
                <h1>Создать задачу</h1>
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
                        <h3>Новая задача</h3>
                        <a href="list.php" class="btn btn-secondary btn-sm">← Назад</a>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>
                        
                        <?php if (empty($projects)): ?>
                        <div class="alert alert-warning">
                            У вас нет доступных проектов. Сначала создайте проект или попросите CEO назначить вас на проект.
                        </div>
                        <?php else: ?>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo User::getCsrfToken(); ?>">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="name">Название задачи *</label>
                                    <input type="text" id="name" name="name" required 
                                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                                           placeholder="Например: Разработать модуль авторизации">
                                </div>
                                
                                <div class="form-group">
                                    <label for="project_id">Проект *</label>
                                    <select id="project_id" name="project_id" required>
                                        <option value="">Выберите проект</option>
                                        <?php foreach ($projects as $proj): ?>
                                        <option value="<?php echo $proj['id']; ?>"
                                                <?php echo (($preselectedProjectId && $preselectedProjectId == $proj['id']) || (isset($_POST['project_id']) && $_POST['project_id'] == $proj['id'])) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($proj['name']); ?>
                                            <?php if (!empty($proj['department_names'])): ?>
                                                (<?php echo htmlspecialchars($proj['department_names']); ?>)
                                            <?php endif; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="status">Статус</label>
                                    <select id="status" name="status">
                                        <option value="Not Started" selected>Не начата</option>
                                        <option value="In Progress">В работе</option>
                                        <option value="On Moderation">На модерации</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="importance">Важность</label>
                                    <select id="importance" name="importance">
                                        <option value="Low">Низкая</option>
                                        <option value="Medium" selected>Средняя</option>
                                        <option value="High">Высокая</option>
                                        <option value="Critical">Критическая</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="deadline">Срок выполнения</label>
                                    <input type="date" id="deadline" name="deadline" 
                                           value="<?php echo isset($_POST['deadline']) ? $_POST['deadline'] : ''; ?>"
                                           min="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Описание задачи</label>
                                <textarea id="description" name="description" rows="4" 
                                          placeholder="Подробное описание задачи, требования, критерии выполнения..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Назначить исполнителей *</label>
                                <?php if (!empty($employees)): ?>
                                <div style="max-height: 300px; overflow-y: auto; border: 2px solid var(--border-color); border-radius: 8px; padding: 16px; background: var(--light-color);">
                                    <?php foreach ($employees as $emp): ?>
                                    <div style="margin-bottom: 12px;">
                                        <label style="display: flex; align-items: center; cursor: pointer; padding: 8px; border-radius: 6px; transition: background 0.2s;"
                                               onmouseover="this.style.background='white'" 
                                               onmouseout="this.style.background='transparent'">
                                            <input type="checkbox" name="employee_ids[]" value="<?php echo $emp['id']; ?>" 
                                                   style="margin-right: 12px; width: 18px; height: 18px; cursor: pointer;"
                                                   <?php echo (isset($_POST['employee_ids']) && in_array($emp['id'], $_POST['employee_ids'])) ? 'checked' : ''; ?>>
                                            <div>
                                                <div style="font-weight: 500; color: var(--dark-color);">
                                                    <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?>
                                                </div>
                                                <div style="font-size: 12px; color: var(--text-light);">
                                                    <?php echo htmlspecialchars($emp['department_name']); ?> • <?php echo htmlspecialchars($emp['email']); ?>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-warning">
                                    В вашем отделе нет сотрудников
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($employees)): ?>
                            <div class="flex gap-2 mt-3">
                                <button type="submit" name="create_task" class="btn btn-primary">
                                    ✓ Создать задачу
                                </button>
                                <a href="list.php" class="btn btn-secondary">Отмена</a>
                            </div>
                            <?php endif; ?>
                        </form>
                        
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const deadlineInput = document.getElementById('deadline');
        const form = document.querySelector('form');
        const today = new Date().toISOString().split('T')[0];
        
        // Устанавливаем минимальную дату дедлайна
        deadlineInput.setAttribute('min', today);
        
        // Валидация при изменении
        deadlineInput.addEventListener('change', function() {
            if (this.value && this.value < today) {
                alert('Дедлайн должен быть не раньше сегодняшней даты');
                this.value = today;
            }
        });
        
        // Валидация при отправке формы
        form.addEventListener('submit', function(e) {
            if (deadlineInput.value && deadlineInput.value < today) {
                e.preventDefault();
                alert('Дедлайн должен быть не раньше сегодняшней даты');
                deadlineInput.focus();
            }
        });
    });
    </script>
</body>
</html>
