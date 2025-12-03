<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Task.php';

if (!User::isAuthenticated()) {
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

$taskData = $task->getById($taskId);
if (!$taskData) {
    header('Location: list.php');
    exit;
}

// Получаем исполнителей задачи
$employees = $task->getTaskEmployees($taskId);

$success = null;
$error = null;

// Обработка обновления статуса
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (User::verifyCsrfToken($_POST['csrf_token'])) {
        $newStatus = $_POST['status'];
        $endDate = ($newStatus === 'Completed') ? date('Y-m-d') : null;
        
        try {
            $task->updateStatus($taskId, $newStatus, $endDate);
            $success = 'Статус задачи обновлён!';
            // Перезагружаем данные
            $taskData = $task->getById($taskId);
        } catch (Exception $e) {
            $error = 'Ошибка при обновлении: ' . $e->getMessage();
        }
    }
}

// Обработка обновления задачи (для менеджера)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_task'])) {
    if (!User::hasRole('Manager')) {
        $error = 'Недостаточно прав';
    } elseif (!User::verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Ошибка безопасности';
    } else {
        $updateData = [
            'name' => trim($_POST['name']),
            'description' => trim($_POST['description']),
            'deadline' => $_POST['deadline'] ?: null,
            'status' => $_POST['status'],
            'importance' => $_POST['importance'],
            'end_date' => $_POST['status'] === 'Completed' ? date('Y-m-d') : null
        ];
        
        try {
            $task->update($taskId, $updateData);
            
            // Обновляем исполнителей
            if (isset($_POST['employee_ids'])) {
                $task->assignEmployees($taskId, $_POST['employee_ids']);
            }
            
            $success = 'Задача успешно обновлена!';
            // Перезагружаем данные
            $taskData = $task->getById($taskId);
            $employees = $task->getTaskEmployees($taskId);
        } catch (Exception $e) {
            $error = 'Ошибка при обновлении: ' . $e->getMessage();
        }
    }
}

// Проверяем, является ли текущий пользователь исполнителем
$isAssignee = false;
if ($role === 'Employee') {
    foreach ($employees as $emp) {
        if ($emp['id'] == $userId) {
            $isAssignee = true;
            break;
        }
    }
}

$canEdit = User::hasRole('Manager');
$canUpdateStatus = $isAssignee || $canEdit;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($taskData['name']); ?> - <?php echo APP_NAME; ?></title>
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
                <?php if ($role === 'Employee'): ?>
                <li><a href="my_tasks.php" class="active"><span class="icon">✓</span> Мои задачи</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/kpi/my_kpi.php"><span class="icon">📈</span> Мои KPI</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/rewards/my_rewards.php"><span class="icon">💰</span> Мои вознаграждения</a></li>
                <?php else: ?>
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
                <?php endif; ?>
                <li><a href="<?php echo APP_URL; ?>/auth/logout.php"><span class="icon">🚪</span> Выход</a></li>
            </ul>
        </aside>

        <div class="main-content">
            <div class="topbar">
                <h1><?php echo htmlspecialchars($taskData['name']); ?></h1>
                <div class="user-menu">
                    <div class="user-info">
                        <div class="name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <div class="role"><?php echo $role; ?></div>
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

                <!-- Task Info -->
                <div class="card">
                    <div class="card-header">
                        <h3>Информация о задаче</h3>
                        <div class="flex gap-2">
                            <?php if ($canEdit): ?>
                            <a href="edit.php?id=<?php echo $taskId; ?>" class="btn btn-warning btn-sm">Редактировать</a>
                            <?php endif; ?>
                            <a href="<?php echo $role === 'Employee' ? 'my_tasks.php' : 'list.php'; ?>" 
                               class="btn btn-secondary btn-sm">← Назад</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="form-grid">
                            <div>
                                <strong>Проект:</strong>
                                <p><a href="<?php echo APP_URL; ?>/modules/projects/view.php?id=<?php echo $taskData['project_id']; ?>">
                                    <?php echo htmlspecialchars($taskData['project_name']); ?>
                                </a></p>
                            </div>
                            <div>
                                <strong>Создал:</strong>
                                <p><?php echo htmlspecialchars($taskData['created_by']); ?></p>
                            </div>
                            <div>
                                <strong>Статус:</strong>
                                <p>
                                    <?php 
                                    $statusClass = 'secondary';
                                    if ($taskData['status'] === 'Completed') $statusClass = 'success';
                                    elseif ($taskData['status'] === 'In Progress') $statusClass = 'info';
                                    ?>
                                    <span class="badge badge-<?php echo $statusClass; ?>">
                                        <?php echo htmlspecialchars($taskData['status']); ?>
                                    </span>
                                </p>
                            </div>
                            <div>
                                <strong>Важность:</strong>
                                <p>
                                    <?php 
                                    $importanceClass = 'secondary';
                                    if ($taskData['importance'] === 'Critical') $importanceClass = 'danger';
                                    elseif ($taskData['importance'] === 'High') $importanceClass = 'warning';
                                    ?>
                                    <span class="badge badge-<?php echo $importanceClass; ?>">
                                        <?php echo htmlspecialchars($taskData['importance']); ?>
                                    </span>
                                </p>
                            </div>
                            <div>
                                <strong>Дата создания:</strong>
                                <p><?php echo date('d.m.Y H:i', strtotime($taskData['created_at'])); ?></p>
                            </div>
                            <div>
                                <strong>Срок выполнения:</strong>
                                <p>
                                    <?php 
                                    if ($taskData['deadline']) {
                                        $deadline = strtotime($taskData['deadline']);
                                        $today = strtotime(date('Y-m-d'));
                                        $color = 'inherit';
                                        if ($deadline < $today && $taskData['status'] !== 'Completed') {
                                            $color = 'var(--danger-color)';
                                            echo '<span style="color: ' . $color . '; font-weight: bold;">⚠️ ' . date('d.m.Y', $deadline) . ' (просрочено)</span>';
                                        } elseif ($deadline - $today <= 3 * 24 * 3600 && $taskData['status'] !== 'Completed') {
                                            $color = 'var(--warning-color)';
                                            echo '<span style="color: ' . $color . '; font-weight: bold;">⏰ ' . date('d.m.Y', $deadline) . ' (скоро)</span>';
                                        } else {
                                            echo date('d.m.Y', $deadline);
                                        }
                                    } else {
                                        echo 'Не указан';
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                        
                        <?php if ($taskData['description']): ?>
                        <div class="mt-3">
                            <strong>Описание:</strong>
                            <div style="background: var(--light-color); padding: 16px; border-radius: 8px; margin-top: 8px;">
                                <?php echo nl2br(htmlspecialchars($taskData['description'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($taskData['end_date']): ?>
                        <div class="mt-3">
                            <strong>Дата завершения:</strong>
                            <p><?php echo date('d.m.Y', strtotime($taskData['end_date'])); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Status Update for Employee/Manager -->
                <?php if ($canUpdateStatus && $taskData['status'] !== 'Completed'): ?>
                <div class="card">
                    <div class="card-header">
                        <h3>Изменить статус</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="flex gap-2 items-center">
                            <input type="hidden" name="csrf_token" value="<?php echo User::getCsrfToken(); ?>">
                            
                            <select name="status" style="width: auto;">
                                <option value="Not Started" <?php echo $taskData['status'] === 'Not Started' ? 'selected' : ''; ?>>Не начата</option>
                                <option value="In Progress" <?php echo $taskData['status'] === 'In Progress' ? 'selected' : ''; ?>>В работе</option>
                                <option value="On Moderation" <?php echo $taskData['status'] === 'On Moderation' ? 'selected' : ''; ?>>На модерации</option>
                                <option value="Completed" <?php echo $taskData['status'] === 'Completed' ? 'selected' : ''; ?>>Завершена</option>
                                <?php if ($canEdit): ?>
                                <option value="Frozen" <?php echo $taskData['status'] === 'Frozen' ? 'selected' : ''; ?>>Заморожена</option>
                                <option value="Canceled" <?php echo $taskData['status'] === 'Canceled' ? 'selected' : ''; ?>>Отменена</option>
                                <?php endif; ?>
                            </select>
                            
                            <button type="submit" name="update_status" class="btn btn-primary">
                                Обновить статус
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Assigned Employees -->
                <div class="card">
                    <div class="card-header">
                        <h3>Исполнители (<?php echo count($employees); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($employees)): ?>
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
                                    <?php foreach ($employees as $emp): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($emp['email']); ?></td>
                                        <td><?php echo htmlspecialchars($emp['phone_number']); ?></td>
                                        <td><?php echo htmlspecialchars($emp['department_name']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <p>Исполнители не назначены</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
