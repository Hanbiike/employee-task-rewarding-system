<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Task.php';

if (!User::isAuthenticated() || !User::hasRole('Manager')) {
    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

$user = User::getCurrentUser();
$role = User::getCurrentRole();
$userId = User::getCurrentUserId();
$task = new Task();

// Получаем задачи
if ($role === 'CEO') {
    // CEO видит все задачи
    $tasks = $task->getByManager($userId);
} else {
    // Менеджер видит только свои задачи
    $tasks = $task->getByManager($userId);
}

// Фильтрация
$statusFilter = $_GET['status'] ?? '';
$importanceFilter = $_GET['importance'] ?? '';

if ($statusFilter) {
    $tasks = array_filter($tasks, function($t) use ($statusFilter) {
        return $t['status'] === $statusFilter;
    });
}

if ($importanceFilter) {
    $tasks = array_filter($tasks, function($t) use ($importanceFilter) {
        return $t['importance'] === $importanceFilter;
    });
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Задачи - <?php echo APP_NAME; ?></title>
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
                <h1>Задачи</h1>
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
                        <h3>Список задач (<?php echo count($tasks); ?>)</h3>
                        <a href="create.php" class="btn btn-primary">+ Создать задачу</a>
                    </div>
                    <div class="card-body">
                        <!-- Фильтры -->
                        <form method="GET" class="mb-3">
                            <div class="flex gap-2 mb-3">
                                <select name="status" onchange="this.form.submit()">
                                    <option value="">Все статусы</option>
                                    <option value="Not Started" <?php echo $statusFilter === 'Not Started' ? 'selected' : ''; ?>>Не начата</option>
                                    <option value="In Progress" <?php echo $statusFilter === 'In Progress' ? 'selected' : ''; ?>>В работе</option>
                                    <option value="Completed" <?php echo $statusFilter === 'Completed' ? 'selected' : ''; ?>>Завершена</option>
                                    <option value="On Moderation" <?php echo $statusFilter === 'On Moderation' ? 'selected' : ''; ?>>На модерации</option>
                                    <option value="Frozen" <?php echo $statusFilter === 'Frozen' ? 'selected' : ''; ?>>Заморожена</option>
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
                                        <th>Проект</th>
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
                                        <td><?php echo $t['id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($t['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($t['project_name']); ?></td>
                                        <td>
                                            <?php 
                                            $statusClass = 'secondary';
                                            if ($t['status'] === 'Completed') $statusClass = 'success';
                                            elseif ($t['status'] === 'In Progress') $statusClass = 'info';
                                            elseif ($t['status'] === 'Frozen') $statusClass = 'warning';
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
                                        <td>
                                            <?php 
                                            if ($t['deadline']) {
                                                $deadline = strtotime($t['deadline']);
                                                $today = strtotime(date('Y-m-d'));
                                                $color = 'inherit';
                                                if ($deadline < $today && $t['status'] !== 'Completed') {
                                                    $color = 'var(--danger-color)';
                                                } elseif ($deadline - $today <= 3 * 24 * 3600) {
                                                    $color = 'var(--warning-color)';
                                                }
                                                echo '<span style="color: ' . $color . ';">' . date('d.m.Y', $deadline) . '</span>';
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <a href="view.php?id=<?php echo $t['id']; ?>" class="btn btn-sm btn-primary">Просмотр</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($tasks)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center">Задачи не найдены</td>
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
