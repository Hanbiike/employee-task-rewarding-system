<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Project.php';
require_once __DIR__ . '/../../classes/Task.php';

if (!User::isAuthenticated()) {
    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

$projectId = $_GET['id'] ?? null;
if (!$projectId) {
    header('Location: list.php');
    exit;
}

$user = User::getCurrentUser();
$role = User::getCurrentRole();
$project = new Project();
$task = new Task();

$projectData = $project->getById($projectId);
if (!$projectData) {
    header('Location: list.php');
    exit;
}

$managers = $project->getProjectManagers($projectId);
$tasks = $task->getByProject($projectId);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($projectData['name']); ?> - <?php echo APP_NAME; ?></title>
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
                <li><a href="list.php" class="active"><span class="icon">📁</span> Проекты</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/tasks/list.php"><span class="icon">✓</span> Задачи</a></li>
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
                <h1><?php echo htmlspecialchars($projectData['name']); ?></h1>
                <div class="user-menu">
                    <div class="user-info">
                        <div class="name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <div class="role"><?php echo $role; ?></div>
                    </div>
                </div>
            </div>

            <div class="content">
                <!-- Project Info -->
                <div class="card">
                    <div class="card-header">
                        <h3>Информация о проекте</h3>
                        <div class="flex gap-2">
                            <?php if ($role === 'CEO'): ?>
                            <a href="edit.php?id=<?php echo $projectId; ?>" class="btn btn-warning btn-sm">Редактировать</a>
                            <?php endif; ?>
                            <?php if ($role === 'Manager'): ?>
                            <a href="<?php echo APP_URL; ?>/modules/tasks/create.php?project_id=<?php echo $projectId; ?>" 
                               class="btn btn-primary btn-sm">+ Создать задачу</a>
                            <?php endif; ?>
                            <a href="list.php" class="btn btn-secondary btn-sm">← Назад</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="form-grid">
                            <div>
                                <strong>Отделы:</strong>
                                <p>
                                    <?php if (!empty($projectData['departments'])): ?>
                                        <?php foreach ($projectData['departments'] as $index => $dept): ?>
                                            <span class="badge badge-secondary" style="margin-right: 5px; margin-bottom: 5px;">
                                                <?php echo htmlspecialchars($dept['name']); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span style="color: var(--text-light);">Не указаны</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div>
                                <strong>Статус:</strong>
                                <p>
                                    <?php 
                                    $statusClass = 'secondary';
                                    if ($projectData['status'] === 'Completed') $statusClass = 'success';
                                    elseif ($projectData['status'] === 'In Progress') $statusClass = 'info';
                                    elseif ($projectData['status'] === 'Frozen') $statusClass = 'warning';
                                    ?>
                                    <span class="badge badge-<?php echo $statusClass; ?>">
                                        <?php echo htmlspecialchars($projectData['status']); ?>
                                    </span>
                                </p>
                            </div>
                            <div>
                                <strong>Важность:</strong>
                                <p>
                                    <?php 
                                    $importanceClass = 'secondary';
                                    if ($projectData['importance'] === 'Critical') $importanceClass = 'danger';
                                    elseif ($projectData['importance'] === 'High') $importanceClass = 'warning';
                                    ?>
                                    <span class="badge badge-<?php echo $importanceClass; ?>">
                                        <?php echo htmlspecialchars($projectData['importance']); ?>
                                    </span>
                                </p>
                            </div>
                            <div>
                                <strong>Создал:</strong>
                                <p><?php echo htmlspecialchars($projectData['created_by']); ?></p>
                            </div>
                            <div>
                                <strong>Дата создания:</strong>
                                <p><?php echo date('d.m.Y', strtotime($projectData['created_at'])); ?></p>
                            </div>
                            <div>
                                <strong>Срок:</strong>
                                <p><?php echo $projectData['deadline'] ? date('d.m.Y', strtotime($projectData['deadline'])) : 'Не указан'; ?></p>
                            </div>
                        </div>
                        
                        <?php if ($projectData['description']): ?>
                        <div class="mt-3">
                            <strong>Описание:</strong>
                            <p><?php echo nl2br(htmlspecialchars($projectData['description'])); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Managers -->
                <div class="card">
                    <div class="card-header">
                        <h3>Ответственные менеджеры (<?php echo count($managers); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($managers)): ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Имя</th>
                                        <th>Email</th>
                                        <th>Телефон</th>
                                        <th>Отдел</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($managers as $mgr): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($mgr['first_name'] . ' ' . $mgr['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($mgr['email']); ?></td>
                                        <td><?php echo htmlspecialchars($mgr['phone_number']); ?></td>
                                        <td><?php echo htmlspecialchars($mgr['department_name']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <p>Менеджеры не назначены</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tasks -->
                <div class="card">
                    <div class="card-header">
                        <h3>Задачи проекта (<?php echo count($tasks); ?>)</h3>
                        <?php if ($role === 'Manager'): ?>
                        <a href="<?php echo APP_URL; ?>/modules/tasks/create.php?project_id=<?php echo $projectId; ?>" 
                           class="btn btn-primary btn-sm">+ Создать задачу</a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($tasks)): ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Название</th>
                                        <th>Статус</th>
                                        <th>Важность</th>
                                        <th>Создана</th>
                                        <th>Срок</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tasks as $t): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($t['name']); ?></td>
                                        <td>
                                            <?php 
                                            $statusClass = 'secondary';
                                            if ($t['status'] === 'Completed') $statusClass = 'success';
                                            elseif ($t['status'] === 'In Progress') $statusClass = 'info';
                                            ?>
                                            <span class="badge badge-<?php echo $statusClass; ?>">
                                                <?php echo htmlspecialchars($t['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $importanceClass = 'secondary';
                                            if ($t['importance'] === 'Critical') $importanceClass = 'danger';
                                            elseif ($t['importance'] === 'High') $importanceClass = 'warning';
                                            ?>
                                            <span class="badge badge-<?php echo $importanceClass; ?>">
                                                <?php echo htmlspecialchars($t['importance']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d.m.Y', strtotime($t['created_at'])); ?></td>
                                        <td><?php echo $t['deadline'] ? date('d.m.Y', strtotime($t['deadline'])) : '-'; ?></td>
                                        <td>
                                            <a href="<?php echo APP_URL; ?>/modules/tasks/view.php?id=<?php echo $t['id']; ?>" 
                                               class="btn btn-sm btn-primary">Просмотр</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <p>Задачи ещё не созданы</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
