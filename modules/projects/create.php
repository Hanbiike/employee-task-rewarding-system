<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Project.php';

if (!User::isAuthenticated() || !User::hasRole('CEO')) {
    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

$user = User::getCurrentUser();
$project = new Project();
$db = Database::getInstance();

// Получаем список отделов и менеджеров
$departments = $db->fetchAll("SELECT * FROM departments ORDER BY name");
$managers = $db->fetchAll("SELECT m.*, d.name as department_name FROM managers m 
                           JOIN departments d ON m.department_id = d.id 
                           ORDER BY m.last_name, m.first_name");

$error = null;
$success = null;

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_project'])) {
    // CSRF проверка
    if (!User::verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Ошибка безопасности. Обновите страницу и попробуйте снова.';
    } else {
        $data = [
            'name' => trim($_POST['name']),
            'description' => trim($_POST['description']),
            'department_ids' => $_POST['department_ids'] ?? [],
            'deadline' => $_POST['deadline'] ?: null,
            'status' => $_POST['status'],
            'importance' => $_POST['importance'],
            'created_by_manager_id' => User::getCurrentUserId(),
            'manager_ids' => $_POST['manager_ids'] ?? []
        ];
        
        if (empty($data['name'])) {
            $error = 'Название проекта обязательно';
        } elseif (empty($data['department_ids'])) {
            $error = 'Выберите хотя бы один отдел';
        } elseif (!empty($data['deadline']) && strtotime($data['deadline']) < strtotime(date('Y-m-d'))) {
            $error = 'Дедлайн должен быть не раньше сегодняшней даты';
        } else {
            try {
                $projectId = $project->create($data);
                $success = 'Проект успешно создан!';
                header('Location: view.php?id=' . $projectId);
                exit;
            } catch (Exception $e) {
                $error = 'Ошибка при создании проекта: ' . $e->getMessage();
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
    <title>Создать проект - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
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
                <li><a href="list.php" class="active"><span class="icon">📁</span> Проекты</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/tasks/list.php"><span class="icon">✓</span> Задачи</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/employees/list.php"><span class="icon">👥</span> Сотрудники</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/managers/list.php"><span class="icon">👔</span> Менеджеры</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/departments/list.php"><span class="icon">🏢</span> Отделы</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/kpi/list.php"><span class="icon">📈</span> KPI</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/rewards/list.php"><span class="icon">💰</span> Вознаграждения</a></li>
                <li><a href="<?php echo APP_URL; ?>/auth/logout.php"><span class="icon">🚪</span> Выход</a></li>
            </ul>
        </aside>

        <div class="main-content">
            <div class="topbar">
                <h1>Создать проект</h1>
                <div class="user-menu">
                    <div class="user-info">
                        <div class="name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <div class="role">CEO</div>
                    </div>
                </div>
            </div>

            <div class="content">
                <div class="card">
                    <div class="card-header">
                        <h3>Новый проект</h3>
                        <a href="list.php" class="btn btn-secondary btn-sm">← Назад</a>
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
                                    <label for="name">Название проекта *</label>
                                    <input type="text" id="name" name="name" required 
                                           value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="status">Статус</label>
                                    <select id="status" name="status">
                                        <option value="Not Started">Не начат</option>
                                        <option value="In Progress">В работе</option>
                                        <option value="On Moderation">На модерации</option>
                                        <option value="Frozen">Заморожен</option>
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
                                           value="<?php echo isset($_POST['deadline']) ? $_POST['deadline'] : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Отделы проекта *</label>
                                <div style="max-height: 200px; overflow-y: auto; overflow-x: hidden; border: 1px solid var(--border-color); border-radius: 8px; padding: 12px;">
                                    <?php foreach ($departments as $dept): ?>
                                    <div style="margin-bottom: 8px;">
                                        <label style="cursor: pointer; display: block;">
                                            <input type="checkbox" name="department_ids[]" value="<?php echo $dept['id']; ?>" 
                                                   style="margin-right: 8px; vertical-align: middle;"
                                                   <?php echo (isset($_POST['department_ids']) && in_array($dept['id'], $_POST['department_ids'])) ? 'checked' : ''; ?>>
                                            <span style="vertical-align: middle;"><?php echo htmlspecialchars($dept['name']); ?></span>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <small style="color: var(--text-light);">Выберите один или несколько отделов</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Описание</label>
                                <textarea id="description" name="description" rows="4"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Назначить менеджеров</label>
                                <div style="max-height: 200px; overflow-y: auto; overflow-x: hidden; border: 1px solid var(--border-color); border-radius: 8px; padding: 12px;">
                                    <?php foreach ($managers as $mgr): ?>
                                    <div style="margin-bottom: 8px;">
                                        <label style="cursor: pointer; display: block;">
                                            <input type="checkbox" name="manager_ids[]" value="<?php echo $mgr['id']; ?>" 
                                                   style="margin-right: 8px; vertical-align: middle;"
                                                   <?php echo (isset($_POST['manager_ids']) && in_array($mgr['id'], $_POST['manager_ids'])) ? 'checked' : ''; ?>>
                                            <span style="vertical-align: middle;"><?php echo htmlspecialchars($mgr['first_name'] . ' ' . $mgr['last_name']); ?></span>
                                            <span style="margin-left: 8px; font-size: 12px; color: var(--text-light); vertical-align: middle;">
                                                (<?php echo htmlspecialchars($mgr['department_name']); ?>)
                                            </span>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="flex gap-2 mt-3">
                                <button type="submit" name="create_project" class="btn btn-primary">Создать проект</button>
                                <a href="list.php" class="btn btn-secondary">Отмена</a>
                            </div>
                        </form>
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
