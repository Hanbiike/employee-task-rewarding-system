<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';

if (!User::isAuthenticated() || !User::hasRole('CEO')) {
    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

$user = User::getCurrentUser();
$db = Database::getInstance();

// Общая статистика
$stats = [
    'departments' => $db->fetchOne("SELECT COUNT(*) as count FROM departments"),
    'managers' => $db->fetchOne("SELECT COUNT(*) as count FROM managers"),
    'employees' => $db->fetchOne("SELECT COUNT(*) as count FROM employees"),
    'projects' => $db->fetchOne("SELECT COUNT(*) as count FROM projects"),
    'tasks' => $db->fetchOne("SELECT COUNT(*) as count FROM tasks"),
];

// Статистика по проектам
$projectStats = [
    'not_started' => $db->fetchOne("SELECT COUNT(*) as count FROM projects WHERE status = 'Not Started'"),
    'in_progress' => $db->fetchOne("SELECT COUNT(*) as count FROM projects WHERE status = 'In Progress'"),
    'completed' => $db->fetchOne("SELECT COUNT(*) as count FROM projects WHERE status = 'Completed'"),
    'frozen' => $db->fetchOne("SELECT COUNT(*) as count FROM projects WHERE status = 'Frozen'"),
];

// Статистика по задачам
$taskStats = [
    'not_started' => $db->fetchOne("SELECT COUNT(*) as count FROM tasks WHERE status = 'Not Started'"),
    'in_progress' => $db->fetchOne("SELECT COUNT(*) as count FROM tasks WHERE status = 'In Progress'"),
    'completed' => $db->fetchOne("SELECT COUNT(*) as count FROM tasks WHERE status = 'Completed'"),
    'frozen' => $db->fetchOne("SELECT COUNT(*) as count FROM tasks WHERE status = 'Frozen'"),
];

// Статистика по отделам
$departmentStats = $db->fetchAll("
    SELECT 
        d.name,
        d.id,
        (SELECT COUNT(*) FROM managers WHERE department_id = d.id) as managers_count,
        (SELECT COUNT(*) FROM employees WHERE department_id = d.id) as employees_count,
        (SELECT COUNT(*) FROM project_departments pd WHERE pd.department_id = d.id) as projects_count,
        (SELECT COUNT(*) FROM tasks t 
         JOIN projects p ON t.project_id = p.id 
         JOIN project_departments pd ON p.id = pd.project_id 
         WHERE pd.department_id = d.id) as tasks_count
    FROM departments d
    ORDER BY d.name
");

// Топ менеджеров по количеству проектов
$topManagers = $db->fetchAll("
    SELECT 
        CONCAT(m.first_name, ' ', m.last_name) as name,
        m.id,
        d.name as department_name,
        COUNT(DISTINCT mp.project_id) as projects_count,
        COUNT(DISTINCT t.id) as tasks_count
    FROM managers m
    LEFT JOIN manager_projects mp ON m.id = mp.manager_id
    LEFT JOIN tasks t ON m.id = t.created_by_manager_id
    JOIN departments d ON m.department_id = d.id
    GROUP BY m.id
    ORDER BY projects_count DESC, tasks_count DESC
    LIMIT 10
");

// Проекты с приближающимся дедлайном (следующие 30 дней)
$upcomingDeadlines = $db->fetchAll("
    SELECT p.*, 
           CONCAT(m.first_name, ' ', m.last_name) as created_by,
           DATEDIFF(p.deadline, CURDATE()) as days_left
    FROM projects p
    JOIN managers m ON p.created_by_manager_id = m.id
    WHERE p.deadline IS NOT NULL 
      AND p.status NOT IN ('Completed', 'Canceled')
      AND p.deadline >= CURDATE()
      AND p.deadline <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY p.deadline ASC
    LIMIT 10
");

// Просроченные проекты
$overdueProjects = $db->fetchAll("
    SELECT p.*, 
           CONCAT(m.first_name, ' ', m.last_name) as created_by,
           DATEDIFF(CURDATE(), p.deadline) as days_overdue
    FROM projects p
    JOIN managers m ON p.created_by_manager_id = m.id
    WHERE p.deadline IS NOT NULL 
      AND p.status NOT IN ('Completed', 'Canceled')
      AND p.deadline < CURDATE()
    ORDER BY p.deadline ASC
    LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Аналитика - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <style>
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .progress-bar {
            height: 30px;
            background: #e0e0e0;
            border-radius: 15px;
            overflow: hidden;
            margin: 10px 0;
        }
        .progress-fill {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: bold;
            transition: width 0.3s ease;
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
                <li><a href="<?php echo APP_URL; ?>/modules/projects/list.php"><span class="icon">📁</span> Проекты</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/tasks/list.php"><span class="icon">✓</span> Задачи</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/employees/list.php"><span class="icon">👥</span> Сотрудники</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/managers/list.php"><span class="icon">👔</span> Менеджеры</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/departments/list.php"><span class="icon">🏢</span> Отделы</a></li>
                <li><a href="index.php" class="active"><span class="icon">📈</span> Аналитика</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/kpi/list.php"><span class="icon">🎯</span> KPI</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/rewards/list.php"><span class="icon">💰</span> Вознаграждения</a></li>
                <li><a href="<?php echo APP_URL; ?>/auth/logout.php"><span class="icon">🚪</span> Выход</a></li>
            </ul>
        </aside>

        <div class="main-content">
            <div class="topbar">
                <h1>📊 Аналитика и отчеты</h1>
                <div class="user-menu">
                    <div class="user-info">
                        <div class="name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <div class="role">CEO</div>
                    </div>
                </div>
            </div>

            <div class="content">
                <!-- Общая статистика -->
                <div class="analytics-grid">
                    <div class="stat-card primary">
                        <div class="icon">🏢</div>
                        <div class="value"><?php echo $stats['departments']['count']; ?></div>
                        <div class="label">Отделов</div>
                    </div>
                    <div class="stat-card info">
                        <div class="icon">👔</div>
                        <div class="value"><?php echo $stats['managers']['count']; ?></div>
                        <div class="label">Менеджеров</div>
                    </div>
                    <div class="stat-card success">
                        <div class="icon">👥</div>
                        <div class="value"><?php echo $stats['employees']['count']; ?></div>
                        <div class="label">Сотрудников</div>
                    </div>
                    <div class="stat-card warning">
                        <div class="icon">📁</div>
                        <div class="value"><?php echo $stats['projects']['count']; ?></div>
                        <div class="label">Проектов</div>
                    </div>
                    <div class="stat-card danger">
                        <div class="icon">✓</div>
                        <div class="value"><?php echo $stats['tasks']['count']; ?></div>
                        <div class="label">Задач</div>
                    </div>
                </div>

                <!-- Статистика проектов -->
                <div class="card">
                    <div class="card-header">
                        <h3>📁 Статистика по проектам</h3>
                    </div>
                    <div class="card-body">
                        <div class="progress-bar">
                            <?php 
                            $total = $stats['projects']['count'];
                            if ($total > 0):
                                $notStartedPct = ($projectStats['not_started']['count'] / $total) * 100;
                                $inProgressPct = ($projectStats['in_progress']['count'] / $total) * 100;
                                $completedPct = ($projectStats['completed']['count'] / $total) * 100;
                                $frozenPct = ($projectStats['frozen']['count'] / $total) * 100;
                            ?>
                            <div class="progress-fill" style="width: <?php echo $notStartedPct; ?>%; background: #9e9e9e;">
                                <?php if ($notStartedPct > 5): ?>Не начато: <?php echo $projectStats['not_started']['count']; ?><?php endif; ?>
                            </div>
                            <div class="progress-fill" style="width: <?php echo $inProgressPct; ?>%; background: #2196f3;">
                                <?php if ($inProgressPct > 5): ?>В работе: <?php echo $projectStats['in_progress']['count']; ?><?php endif; ?>
                            </div>
                            <div class="progress-fill" style="width: <?php echo $completedPct; ?>%; background: #4caf50;">
                                <?php if ($completedPct > 5): ?>Завершено: <?php echo $projectStats['completed']['count']; ?><?php endif; ?>
                            </div>
                            <div class="progress-fill" style="width: <?php echo $frozenPct; ?>%; background: #ff9800;">
                                <?php if ($frozenPct > 5): ?>Заморожено: <?php echo $projectStats['frozen']['count']; ?><?php endif; ?>
                            </div>
                            <?php else: ?>
                            <div class="progress-fill" style="width: 100%; background: #e0e0e0; color: #666;">Нет проектов</div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-grid mt-3">
                            <div>
                                <strong>Не начато:</strong> <span class="badge badge-secondary"><?php echo $projectStats['not_started']['count']; ?></span>
                            </div>
                            <div>
                                <strong>В работе:</strong> <span class="badge badge-info"><?php echo $projectStats['in_progress']['count']; ?></span>
                            </div>
                            <div>
                                <strong>Завершено:</strong> <span class="badge badge-success"><?php echo $projectStats['completed']['count']; ?></span>
                            </div>
                            <div>
                                <strong>Заморожено:</strong> <span class="badge badge-warning"><?php echo $projectStats['frozen']['count']; ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Статистика задач -->
                <div class="card">
                    <div class="card-header">
                        <h3>✓ Статистика по задачам</h3>
                    </div>
                    <div class="card-body">
                        <div class="progress-bar">
                            <?php 
                            $total = $stats['tasks']['count'];
                            if ($total > 0):
                                $notStartedPct = ($taskStats['not_started']['count'] / $total) * 100;
                                $inProgressPct = ($taskStats['in_progress']['count'] / $total) * 100;
                                $completedPct = ($taskStats['completed']['count'] / $total) * 100;
                                $frozenPct = ($taskStats['frozen']['count'] / $total) * 100;
                            ?>
                            <div class="progress-fill" style="width: <?php echo $notStartedPct; ?>%; background: #9e9e9e;">
                                <?php if ($notStartedPct > 5): ?>Не начато: <?php echo $taskStats['not_started']['count']; ?><?php endif; ?>
                            </div>
                            <div class="progress-fill" style="width: <?php echo $inProgressPct; ?>%; background: #2196f3;">
                                <?php if ($inProgressPct > 5): ?>В работе: <?php echo $taskStats['in_progress']['count']; ?><?php endif; ?>
                            </div>
                            <div class="progress-fill" style="width: <?php echo $completedPct; ?>%; background: #4caf50;">
                                <?php if ($completedPct > 5): ?>Завершено: <?php echo $taskStats['completed']['count']; ?><?php endif; ?>
                            </div>
                            <div class="progress-fill" style="width: <?php echo $frozenPct; ?>%; background: #ff9800;">
                                <?php if ($frozenPct > 5): ?>Заморожено: <?php echo $taskStats['frozen']['count']; ?><?php endif; ?>
                            </div>
                            <?php else: ?>
                            <div class="progress-fill" style="width: 100%; background: #e0e0e0; color: #666;">Нет задач</div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-grid mt-3">
                            <div>
                                <strong>Не начато:</strong> <span class="badge badge-secondary"><?php echo $taskStats['not_started']['count']; ?></span>
                            </div>
                            <div>
                                <strong>В работе:</strong> <span class="badge badge-info"><?php echo $taskStats['in_progress']['count']; ?></span>
                            </div>
                            <div>
                                <strong>Завершено:</strong> <span class="badge badge-success"><?php echo $taskStats['completed']['count']; ?></span>
                            </div>
                            <div>
                                <strong>Заморожено:</strong> <span class="badge badge-warning"><?php echo $taskStats['frozen']['count']; ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Статистика по отделам -->
                <div class="card">
                    <div class="card-header">
                        <h3>🏢 Статистика по отделам</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Отдел</th>
                                        <th>Менеджеры</th>
                                        <th>Сотрудники</th>
                                        <th>Проекты</th>
                                        <th>Задачи</th>
                                        <th>Всего персонала</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($departmentStats as $dept): ?>
                                    <tr>
                                        <td>
                                            <strong>
                                                <a href="<?php echo APP_URL; ?>/modules/departments/view.php?id=<?php echo $dept['id']; ?>">
                                                    <?php echo htmlspecialchars($dept['name']); ?>
                                                </a>
                                            </strong>
                                        </td>
                                        <td><span class="badge badge-info"><?php echo $dept['managers_count']; ?></span></td>
                                        <td><span class="badge badge-primary"><?php echo $dept['employees_count']; ?></span></td>
                                        <td><span class="badge badge-warning"><?php echo $dept['projects_count']; ?></span></td>
                                        <td><span class="badge badge-secondary"><?php echo $dept['tasks_count']; ?></span></td>
                                        <td><strong><?php echo ($dept['managers_count'] + $dept['employees_count']); ?></strong></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Топ менеджеров -->
                <div class="card">
                    <div class="card-header">
                        <h3>🏆 Топ-10 менеджеров</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Менеджер</th>
                                        <th>Отдел</th>
                                        <th>Проектов</th>
                                        <th>Задач создано</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topManagers as $index => $mgr): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td>
                                            <a href="<?php echo APP_URL; ?>/modules/managers/view.php?id=<?php echo $mgr['id']; ?>">
                                                <?php echo htmlspecialchars($mgr['name']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($mgr['department_name']); ?></td>
                                        <td><span class="badge badge-primary"><?php echo $mgr['projects_count']; ?></span></td>
                                        <td><span class="badge badge-info"><?php echo $mgr['tasks_count']; ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($topManagers)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">Нет данных</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Приближающиеся дедлайны -->
                <?php if (!empty($upcomingDeadlines)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3>⏰ Проекты с приближающимся дедлайном (30 дней)</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Проект</th>
                                        <th>Дедлайн</th>
                                        <th>Осталось дней</th>
                                        <th>Статус</th>
                                        <th>Важность</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upcomingDeadlines as $proj): ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo APP_URL; ?>/modules/projects/view.php?id=<?php echo $proj['id']; ?>">
                                                <?php echo htmlspecialchars($proj['name']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo date('d.m.Y', strtotime($proj['deadline'])); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $proj['days_left'] <= 7 ? 'danger' : 'warning'; ?>">
                                                <?php echo $proj['days_left']; ?> дней
                                            </span>
                                        </td>
                                        <td><span class="badge badge-info"><?php echo $proj['status']; ?></span></td>
                                        <td>
                                            <span class="badge badge-<?php echo $proj['importance'] === 'Critical' ? 'danger' : 'warning'; ?>">
                                                <?php echo $proj['importance']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Просроченные проекты -->
                <?php if (!empty($overdueProjects)): ?>
                <div class="card">
                    <div class="card-header" style="background: #dc3545; color: white;">
                        <h3>⚠️ Просроченные проекты</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Проект</th>
                                        <th>Дедлайн был</th>
                                        <th>Просрочено дней</th>
                                        <th>Статус</th>
                                        <th>Важность</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($overdueProjects as $proj): ?>
                                    <tr style="background: #fff3cd;">
                                        <td>
                                            <a href="<?php echo APP_URL; ?>/modules/projects/view.php?id=<?php echo $proj['id']; ?>">
                                                <?php echo htmlspecialchars($proj['name']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo date('d.m.Y', strtotime($proj['deadline'])); ?></td>
                                        <td>
                                            <span class="badge badge-danger">
                                                +<?php echo $proj['days_overdue']; ?> дней
                                            </span>
                                        </td>
                                        <td><span class="badge badge-warning"><?php echo $proj['status']; ?></span></td>
                                        <td>
                                            <span class="badge badge-<?php echo $proj['importance'] === 'Critical' ? 'danger' : 'warning'; ?>">
                                                <?php echo $proj['importance']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</body>
</html>
