<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Project.php';

if (!User::isAuthenticated()) {
    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

$user = User::getCurrentUser();
$role = User::getCurrentRole();
$project = new Project();

// Получаем проекты в зависимости от роли
if ($role === 'CEO') {
    $projects = $project->getAll();
} elseif ($role === 'Manager') {
    $projects = $project->getByManager(User::getCurrentUserId());
} else {
    header('Location: ' . APP_URL . '/dashboard/employee.php');
    exit;
}

// Фильтрация
$statusFilter = $_GET['status'] ?? '';
$importanceFilter = $_GET['importance'] ?? '';

if ($statusFilter) {
    $projects = array_filter($projects, function($p) use ($statusFilter) {
        return $p['status'] === $statusFilter;
    });
}

if ($importanceFilter) {
    $projects = array_filter($projects, function($p) use ($importanceFilter) {
        return $p['importance'] === $importanceFilter;
    });
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Проекты - <?php echo APP_NAME; ?></title>
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
                <h1>Проекты</h1>
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
                        <h3>Список проектов (<?php echo count($projects); ?>)</h3>
                        <?php if ($role === 'CEO'): ?>
                        <a href="create.php" class="btn btn-primary">+ Создать проект</a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <!-- Фильтры -->
                        <form method="GET" class="mb-3">
                            <div class="flex gap-2 mb-3">
                                <select name="status" onchange="this.form.submit()">
                                    <option value="">Все статусы</option>
                                    <option value="Not Started" <?php echo $statusFilter === 'Not Started' ? 'selected' : ''; ?>>Не начат</option>
                                    <option value="In Progress" <?php echo $statusFilter === 'In Progress' ? 'selected' : ''; ?>>В работе</option>
                                    <option value="Completed" <?php echo $statusFilter === 'Completed' ? 'selected' : ''; ?>>Завершён</option>
                                    <option value="Frozen" <?php echo $statusFilter === 'Frozen' ? 'selected' : ''; ?>>Заморожен</option>
                                    <option value="On Moderation" <?php echo $statusFilter === 'On Moderation' ? 'selected' : ''; ?>>На модерации</option>
                                </select>
                                <select name="importance" onchange="this.form.submit()">
                                    <option value="">Вся важность</option>
                                    <option value="Low" <?php echo $importanceFilter === 'Low' ? 'selected' : ''; ?>>Низкая</option>
                                    <option value="Medium" <?php echo $importanceFilter === 'Medium' ? 'selected' : ''; ?>>Средняя</option>
                                    <option value="High" <?php echo $importanceFilter === 'High' ? 'selected' : ''; ?>>Высокая</option>
                                    <option value="Critical" <?php echo $importanceFilter === 'Critical' ? 'selected' : ''; ?>>Критическая</option>
                                </select>
                                <?php if ($statusFilter || $importanceFilter): ?>
                                <a href="list.php" class="btn btn-secondary">Сбросить</a>
                                <?php endif; ?>
                            </div>
                        </form>

                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Название</th>
                                        <th>Отдел</th>
                                        <th>Статус</th>
                                        <th>Важность</th>
                                        <th>Создан</th>
                                        <th>Срок</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($projects as $proj): ?>
                                    <tr>
                                        <td><?php echo $proj['id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($proj['name']); ?></strong></td>
                                        <td>
                                            <?php if (!empty($proj['department_names'])): ?>
                                                <?php 
                                                $deptNames = explode(', ', $proj['department_names']);
                                                foreach ($deptNames as $index => $deptName): 
                                                ?>
                                                    <span class="badge badge-secondary" style="margin: 2px;">
                                                        <?php echo htmlspecialchars($deptName); ?>
                                                    </span>
                                                    <?php if ($index < count($deptNames) - 1): ?>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span style="color: var(--text-light);">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $statusClass = 'secondary';
                                            if ($proj['status'] === 'Completed') $statusClass = 'success';
                                            elseif ($proj['status'] === 'In Progress') $statusClass = 'info';
                                            elseif ($proj['status'] === 'Frozen') $statusClass = 'warning';
                                            ?>
                                            <span class="badge badge-<?php echo $statusClass; ?>">
                                                <?php echo htmlspecialchars($proj['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $importanceClass = 'secondary';
                                            if ($proj['importance'] === 'Critical') $importanceClass = 'danger';
                                            elseif ($proj['importance'] === 'High') $importanceClass = 'warning';
                                            ?>
                                            <span class="badge badge-<?php echo $importanceClass; ?>">
                                                <?php echo htmlspecialchars($proj['importance']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d.m.Y', strtotime($proj['created_at'])); ?></td>
                                        <td><?php echo $proj['deadline'] ? date('d.m.Y', strtotime($proj['deadline'])) : '-'; ?></td>
                                        <td>
                                            <a href="view.php?id=<?php echo $proj['id']; ?>" class="btn btn-sm btn-primary">Просмотр</a>
                                            <?php if ($role === 'CEO'): ?>
                                            <a href="edit.php?id=<?php echo $proj['id']; ?>" class="btn btn-sm btn-warning">Изменить</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($projects)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center">Проекты не найдены</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
