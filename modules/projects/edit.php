<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Project.php';

if (!User::isAuthenticated() || !User::hasRole('CEO')) {
    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

$projectId = $_GET['id'] ?? null;
if (!$projectId) {
    header('Location: list.php');
    exit;
}

$user = User::getCurrentUser();
$project = new Project();
$db = Database::getInstance();

// Получаем данные проекта
$projectData = $project->getById($projectId);
if (!$projectData) {
    header('Location: list.php');
    exit;
}

// Получаем список отделов и менеджеров
$departments = $db->fetchAll("SELECT * FROM departments ORDER BY name");
$managers = $db->fetchAll("SELECT m.*, d.name as department_name FROM managers m 
                           JOIN departments d ON m.department_id = d.id 
                           ORDER BY m.last_name, m.first_name");

// Получаем текущих менеджеров проекта
$currentManagers = $project->getProjectManagers($projectId);
$currentManagerIds = array_column($currentManagers, 'id');

// Получаем текущие департаменты проекта
$currentDepartments = $project->getProjectDepartments($projectId);
$currentDepartmentIds = array_column($currentDepartments, 'id');

$error = null;
$success = null;

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_project'])) {
    // CSRF проверка
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
            'manager_ids' => $_POST['manager_ids'] ?? [],
            'department_ids' => $_POST['department_ids'] ?? []
        ];
        
        if (empty($data['name'])) {
            $error = 'Название проекта обязательно';
        } elseif (empty($data['department_ids'])) {
            $error = 'Выберите хотя бы один отдел';
        } else {
            try {
                $project->update($projectId, $data);
                $project->assignManagers($projectId, $data['manager_ids']);
                $project->assignDepartments($projectId, $data['department_ids']);
                $success = 'Проект успешно обновлен!';
                
                // Обновляем данные проекта
                $projectData = $project->getById($projectId);
                $currentManagers = $project->getProjectManagers($projectId);
                $currentManagerIds = array_column($currentManagers, 'id');
                $currentDepartments = $project->getProjectDepartments($projectId);
                $currentDepartmentIds = array_column($currentDepartments, 'id');
            } catch (Exception $e) {
                $error = 'Ошибка при обновлении проекта: ' . $e->getMessage();
            }
        }
    }
}

// Обработка удаления
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_project'])) {
    if (!User::verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Ошибка безопасности. Обновите страницу и попробуйте снова.';
    } else {
        try {
            $project->delete($projectId);
            header('Location: list.php?deleted=1');
            exit;
        } catch (Exception $e) {
            $error = 'Ошибка при удалении проекта: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактировать проект - <?php echo APP_NAME; ?></title>
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
                <h1>Редактировать проект</h1>
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
                        <h3><?php echo htmlspecialchars($projectData['name']); ?></h3>
                        <div class="flex gap-2">
                            <a href="view.php?id=<?php echo $projectId; ?>" class="btn btn-secondary btn-sm">Просмотр</a>
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
                                    <label for="name">Название проекта *</label>
                                    <input type="text" id="name" name="name" required 
                                           value="<?php echo htmlspecialchars($projectData['name']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="status">Статус</label>
                                    <select id="status" name="status">
                                        <option value="Not Started" <?php echo $projectData['status'] === 'Not Started' ? 'selected' : ''; ?>>Не начат</option>
                                        <option value="In Progress" <?php echo $projectData['status'] === 'In Progress' ? 'selected' : ''; ?>>В работе</option>
                                        <option value="On Moderation" <?php echo $projectData['status'] === 'On Moderation' ? 'selected' : ''; ?>>На модерации</option>
                                        <option value="Completed" <?php echo $projectData['status'] === 'Completed' ? 'selected' : ''; ?>>Завершен</option>
                                        <option value="Frozen" <?php echo $projectData['status'] === 'Frozen' ? 'selected' : ''; ?>>Заморожен</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="importance">Важность</label>
                                    <select id="importance" name="importance">
                                        <option value="Low" <?php echo $projectData['importance'] === 'Low' ? 'selected' : ''; ?>>Низкая</option>
                                        <option value="Medium" <?php echo $projectData['importance'] === 'Medium' ? 'selected' : ''; ?>>Средняя</option>
                                        <option value="High" <?php echo $projectData['importance'] === 'High' ? 'selected' : ''; ?>>Высокая</option>
                                        <option value="Critical" <?php echo $projectData['importance'] === 'Critical' ? 'selected' : ''; ?>>Критическая</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="deadline">Срок выполнения</label>
                                    <input type="date" id="deadline" name="deadline" 
                                           value="<?php echo $projectData['deadline'] ? date('Y-m-d', strtotime($projectData['deadline'])) : ''; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="end_date">Дата завершения</label>
                                    <input type="date" id="end_date" name="end_date" 
                                           value="<?php echo $projectData['end_date'] ? date('Y-m-d', strtotime($projectData['end_date'])) : ''; ?>">
                                    <small style="color: var(--text-light);">Заполняется при завершении проекта</small>
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
                                                   <?php echo in_array($dept['id'], $currentDepartmentIds) ? 'checked' : ''; ?>>
                                            <span style="vertical-align: middle;"><?php echo htmlspecialchars($dept['name']); ?></span>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <small style="color: var(--text-light);">Выберите один или несколько отделов</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Описание</label>
                                <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($projectData['description']); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Назначить менеджеров</label>
                                <div style="max-height: 200px; overflow-y: auto; overflow-x: hidden; border: 1px solid var(--border-color); border-radius: 8px; padding: 12px;">
                                    <?php foreach ($managers as $mgr): ?>
                                    <div style="margin-bottom: 8px;">
                                        <label style="cursor: pointer; display: block;">
                                            <input type="checkbox" name="manager_ids[]" value="<?php echo $mgr['id']; ?>" 
                                                   style="margin-right: 8px; vertical-align: middle;"
                                                   <?php echo in_array($mgr['id'], $currentManagerIds) ? 'checked' : ''; ?>>
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
                                <button type="submit" name="update_project" class="btn btn-primary">Сохранить изменения</button>
                                <a href="view.php?id=<?php echo $projectId; ?>" class="btn btn-secondary">Отмена</a>
                            </div>
                        </form>
                        
                        <!-- Секция удаления -->
                        <div class="delete-section">
                            <h4 style="color: #dc3545; margin-bottom: 15px;">⚠️ Опасная зона</h4>
                            <div class="delete-warning">
                                <strong>Внимание!</strong> Удаление проекта приведет к удалению всех связанных задач и назначений. 
                                Это действие необратимо.
                            </div>
                            <form method="POST" action="" onsubmit="return confirm('Вы уверены, что хотите удалить этот проект? Все связанные задачи также будут удалены. Это действие необратимо!');">
                                <input type="hidden" name="csrf_token" value="<?php echo User::getCsrfToken(); ?>">
                                <button type="submit" name="delete_project" class="btn btn-danger">Удалить проект</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
