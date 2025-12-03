<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';

if (!User::isAuthenticated() || !User::hasRole('CEO')) {
    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

$deptId = $_GET['id'] ?? null;
if (!$deptId) {
    header('Location: list.php');
    exit;
}

$user = User::getCurrentUser();
$db = Database::getInstance();

$dept = $db->fetchOne("SELECT * FROM departments WHERE id = ?", [$deptId]);
if (!$dept) {
    header('Location: list.php');
    exit;
}

// Получаем менеджеров отдела
$managers = $db->fetchAll("SELECT * FROM managers WHERE department_id = ? ORDER BY last_name, first_name", [$deptId]);

// Получаем сотрудников отдела
$employees = $db->fetchAll("SELECT * FROM employees WHERE department_id = ? ORDER BY last_name, first_name", [$deptId]);

// Получаем проекты отдела
$projects = $db->fetchAll("
    SELECT p.* 
    FROM projects p
    JOIN project_departments pd ON p.id = pd.project_id
    WHERE pd.department_id = ?
    ORDER BY p.created_at DESC
", [$deptId]);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($dept['name']); ?> - <?php echo APP_NAME; ?></title>
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
                <li><a href="<?php echo APP_URL; ?>/modules/projects/list.php"><span class="icon">📁</span> Проекты</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/tasks/list.php"><span class="icon">✓</span> Задачи</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/employees/list.php"><span class="icon">👥</span> Сотрудники</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/managers/list.php"><span class="icon">👔</span> Менеджеры</a></li>
                <li><a href="list.php" class="active"><span class="icon">🏢</span> Отделы</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/kpi/list.php"><span class="icon">📈</span> KPI</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/rewards/list.php"><span class="icon">💰</span> Вознаграждения</a></li>
                <li><a href="<?php echo APP_URL; ?>/auth/logout.php"><span class="icon">🚪</span> Выход</a></li>
            </ul>
        </aside>

        <div class="main-content">
            <div class="topbar">
                <h1><?php echo htmlspecialchars($dept['name']); ?></h1>
                <div class="user-menu">
                    <div class="user-info">
                        <div class="name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <div class="role">CEO</div>
                    </div>
                </div>
            </div>

            <div class="content">
                <!-- Статистика -->
                <div class="stats-grid">
                    <div class="stat-card info">
                        <div class="icon">👔</div>
                        <div class="value"><?php echo count($managers); ?></div>
                        <div class="label">Менеджеры</div>
                    </div>
                    <div class="stat-card primary">
                        <div class="icon">👥</div>
                        <div class="value"><?php echo count($employees); ?></div>
                        <div class="label">Сотрудники</div>
                    </div>
                    <div class="stat-card secondary">
                        <div class="icon">📁</div>
                        <div class="value"><?php echo count($projects); ?></div>
                        <div class="label">Проекты</div>
                    </div>
                </div>

                <!-- Информация об отделе -->
                <div class="card">
                    <div class="card-header">
                        <h3>Информация об отделе</h3>
                        <a href="edit.php?id=<?php echo $deptId; ?>" class="btn btn-warning btn-sm">Редактировать</a>
                    </div>
                    <div class="card-body">
                        <div class="form-grid">
                            <div>
                                <strong>ID:</strong>
                                <p><?php echo $dept['id']; ?></p>
                            </div>
                            <div>
                                <strong>Название:</strong>
                                <p><?php echo htmlspecialchars($dept['name']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Менеджеры -->
                <div class="card">
                    <div class="card-header">
                        <h3>Менеджеры (<?php echo count($managers); ?>)</h3>
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
                                        <th>Должность</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($managers as $mgr): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($mgr['first_name'] . ' ' . $mgr['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($mgr['email']); ?></td>
                                        <td><?php echo htmlspecialchars($mgr['phone_number']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $mgr['position'] === 'CEO' ? 'danger' : 'info'; ?>">
                                                <?php echo $mgr['position']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="<?php echo APP_URL; ?>/modules/managers/view.php?id=<?php echo $mgr['id']; ?>" 
                                               class="btn btn-sm btn-primary">Просмотр</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <p class="text-center" style="color: var(--text-light); padding: 20px;">Нет менеджеров</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Сотрудники -->
                <div class="card">
                    <div class="card-header">
                        <h3>Сотрудники (<?php echo count($employees); ?>)</h3>
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
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($employees as $emp): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($emp['email']); ?></td>
                                        <td><?php echo htmlspecialchars($emp['phone_number']); ?></td>
                                        <td>
                                            <a href="<?php echo APP_URL; ?>/modules/employees/view.php?id=<?php echo $emp['id']; ?>" 
                                               class="btn btn-sm btn-primary">Просмотр</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <p class="text-center" style="color: var(--text-light); padding: 20px;">Нет сотрудников</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Проекты -->
                <div class="card">
                    <div class="card-header">
                        <h3>Проекты (<?php echo count($projects); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($projects)): ?>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Название</th>
                                        <th>Статус</th>
                                        <th>Важность</th>
                                        <th>Срок</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($projects as $proj): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($proj['name']); ?></td>
                                        <td>
                                            <?php 
                                            $statusClass = 'secondary';
                                            if ($proj['status'] === 'Completed') $statusClass = 'success';
                                            elseif ($proj['status'] === 'In Progress') $statusClass = 'info';
                                            ?>
                                            <span class="badge badge-<?php echo $statusClass; ?>">
                                                <?php echo $proj['status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $importanceClass = 'secondary';
                                            if ($proj['importance'] === 'Critical') $importanceClass = 'danger';
                                            elseif ($proj['importance'] === 'High') $importanceClass = 'warning';
                                            ?>
                                            <span class="badge badge-<?php echo $importanceClass; ?>">
                                                <?php echo $proj['importance']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $proj['deadline'] ? date('d.m.Y', strtotime($proj['deadline'])) : '-'; ?></td>
                                        <td>
                                            <a href="<?php echo APP_URL; ?>/modules/projects/view.php?id=<?php echo $proj['id']; ?>" 
                                               class="btn btn-sm btn-primary">Просмотр</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <p class="text-center" style="color: var(--text-light); padding: 20px;">Нет проектов</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="text-center mt-3">
                    <a href="list.php" class="btn btn-secondary">← Назад к списку</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
