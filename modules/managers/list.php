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

// Получаем фильтры
$departmentFilter = $_GET['department_id'] ?? null;
$searchQuery = $_GET['search'] ?? '';

// Формируем SQL запрос
$sql = "SELECT m.*, d.name as department_name
        FROM managers m
        JOIN departments d ON m.department_id = d.id
        WHERE 1=1";

$params = [];

// Фильтр по отделу
if ($departmentFilter) {
    $sql .= " AND m.department_id = ?";
    $params[] = $departmentFilter;
}

// Поиск
if ($searchQuery) {
    $sql .= " AND (m.first_name LIKE ? OR m.last_name LIKE ? OR m.email LIKE ?)";
    $searchParam = '%' . $searchQuery . '%';
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$sql .= " ORDER BY m.last_name, m.first_name";

$managers = $db->fetchAll($sql, $params);
$departments = $db->fetchAll("SELECT * FROM departments ORDER BY name");
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Менеджеры - <?php echo APP_NAME; ?></title>
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
                <li><a href="list.php" class="active"><span class="icon">👔</span> Менеджеры</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/departments/list.php"><span class="icon">🏢</span> Отделы</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/kpi/list.php"><span class="icon">📈</span> KPI</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/rewards/list.php"><span class="icon">💰</span> Вознаграждения</a></li>
                <li><a href="<?php echo APP_URL; ?>/auth/logout.php"><span class="icon">🚪</span> Выход</a></li>
            </ul>
        </aside>

        <div class="main-content">
            <div class="topbar">
                <h1>Менеджеры</h1>
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
                        <h3>Список менеджеров (<?php echo count($managers); ?>)</h3>
                        <div class="flex gap-2">
                            <a href="<?php echo APP_URL; ?>/modules/export/export.php?type=managers" class="btn btn-primary" style="background: #27ae60;">
                                📥 Экспорт в Excel
                            </a>
                            <a href="create.php" class="btn btn-primary">+ Добавить менеджера</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Фильтры -->
                        <form method="GET" class="mb-3">
                            <div class="flex gap-2">
                                <input type="text" name="search" placeholder="Поиск по имени или email..." 
                                       value="<?php echo htmlspecialchars($searchQuery); ?>" style="flex: 1;">
                                <select name="department_id" onchange="this.form.submit()">
                                    <option value="">Все отделы</option>
                                    <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>" 
                                            <?php echo $departmentFilter == $dept['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-primary">Поиск</button>
                                <?php if ($searchQuery || $departmentFilter): ?>
                                <a href="list.php" class="btn btn-secondary">Сбросить</a>
                                <?php endif; ?>
                            </div>
                        </form>

                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Имя</th>
                                        <th>Email</th>
                                        <th>Телефон</th>
                                        <th>Отдел</th>
                                        <th>Должность</th>
                                        <th>Дата приёма</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($managers as $mgr): ?>
                                    <tr>
                                        <td><?php echo $mgr['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($mgr['first_name'] . ' ' . $mgr['last_name']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($mgr['email']); ?></td>
                                        <td><?php echo htmlspecialchars($mgr['phone_number']); ?></td>
                                        <td>
                                            <span class="badge badge-secondary">
                                                <?php echo htmlspecialchars($mgr['department_name']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo $mgr['position'] === 'CEO' ? 'danger' : 'info'; ?>">
                                                <?php echo htmlspecialchars($mgr['position']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d.m.Y', strtotime($mgr['hire_date'])); ?></td>
                                        <td>
                                            <a href="view.php?id=<?php echo $mgr['id']; ?>" class="btn btn-sm btn-primary">Просмотр</a>
                                            <a href="edit.php?id=<?php echo $mgr['id']; ?>" class="btn btn-sm btn-warning">Изменить</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($managers)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center">Менеджеры не найдены</td>
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
