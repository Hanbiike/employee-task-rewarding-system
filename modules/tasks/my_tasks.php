<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Task.php';

if (!User::isAuthenticated()) {
    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

$user = User::getCurrentUser();
$userId = User::getCurrentUserId();
$task = new Task();

// Получаем задачи сотрудника
$myTasks = $task->getByEmployee($userId);

// Обработка изменения статуса
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (User::verifyCsrfToken($_POST['csrf_token'])) {
        $taskId = $_POST['task_id'];
        $newStatus = $_POST['status'];
        $endDate = ($newStatus === 'Completed') ? date('Y-m-d') : null;
        
        try {
            $task->updateStatus($taskId, $newStatus, $endDate);
            $success = 'Статус задачи обновлён!';
            // Перезагружаем данные
            $myTasks = $task->getByEmployee($userId);
        } catch (Exception $e) {
            $error = 'Ошибка при обновлении: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мои задачи - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <style>
        .task-row {
            transition: background-color 0.3s;
        }
        .task-row:hover {
            background-color: var(--light-color);
        }
        .quick-status {
            display: inline-flex;
            gap: 4px;
        }
        .quick-status form {
            display: inline;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><?php echo APP_NAME; ?></h2>
                <p>Employee Dashboard</p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="<?php echo APP_URL; ?>/dashboard/employee.php"><span class="icon">📊</span> Главная</a></li>
                <li><a href="my_tasks.php" class="active"><span class="icon">✓</span> Мои задачи</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/kpi/my_kpi.php"><span class="icon">📈</span> Мои KPI</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/rewards/my_rewards.php"><span class="icon">💰</span> Вознаграждения</a></li>
                <li><a href="<?php echo APP_URL; ?>/auth/logout.php"><span class="icon">🚪</span> Выход</a></li>
            </ul>
        </aside>

        <div class="main-content">
            <div class="topbar">
                <h1>Мои задачи</h1>
                <div class="user-menu">
                    <div class="user-info">
                        <div class="name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <div class="role">Сотрудник</div>
                    </div>
                </div>
            </div>

            <div class="content">
                <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <h3>Список задач (<?php echo count($myTasks); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($myTasks)): ?>
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
                                    <?php foreach ($myTasks as $t): ?>
                                    <tr class="task-row">
                                        <td><?php echo $t['id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($t['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($t['project_name']); ?></td>
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
                                            <div class="quick-status">
                                                <a href="view.php?id=<?php echo $t['id']; ?>" class="btn btn-sm btn-primary">Просмотр</a>
                                                
                                                <?php if ($t['status'] !== 'Completed'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo User::getCsrfToken(); ?>">
                                                    <input type="hidden" name="task_id" value="<?php echo $t['id']; ?>">
                                                    <?php if ($t['status'] === 'Not Started'): ?>
                                                    <input type="hidden" name="status" value="In Progress">
                                                    <button type="submit" name="update_status" class="btn btn-sm btn-info">
                                                        Начать
                                                    </button>
                                                    <?php elseif ($t['status'] === 'In Progress'): ?>
                                                    <input type="hidden" name="status" value="Completed">
                                                    <button type="submit" name="update_status" class="btn btn-sm btn-success">
                                                        Завершить
                                                    </button>
                                                    <?php endif; ?>
                                                </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <p class="text-center" style="padding: 40px;">
                            У вас пока нет назначенных задач
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
