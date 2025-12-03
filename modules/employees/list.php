<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Department.php';

if (!User::isAuthenticated()) {
    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

$user = User::getCurrentUser();
$role = User::getCurrentRole();
$userId = User::getCurrentUserId();
$db = Database::getInstance();
$department = new Department();

// Получаем фильтры
$departmentFilter = $_GET['department_id'] ?? null;
$searchQuery = $_GET['search'] ?? '';

// Формируем SQL запрос
$sql = "SELECT e.*, d.name as department_name, 
        CONCAT(m.first_name, ' ', m.last_name) as manager_name
        FROM employees e
        JOIN departments d ON e.department_id = d.id
        LEFT JOIN managers m ON e.manager_id = m.id
        WHERE 1=1";

$params = [];

// Фильтр по отделу
if ($role === 'Manager') {
    // Менеджер видит только сотрудников своего отдела
    $sql .= " AND e.department_id = ?";
    $params[] = $user['department_id'];
} elseif ($departmentFilter) {
    $sql .= " AND e.department_id = ?";
    $params[] = $departmentFilter;
}

// Поиск по имени или email
if ($searchQuery) {
    $sql .= " AND (e.first_name LIKE ? OR e.last_name LIKE ? OR e.email LIKE ?)";
    $searchParam = '%' . $searchQuery . '%';
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$sql .= " ORDER BY e.last_name, e.first_name";

$employees = $db->fetchAll($sql, $params);

// Получаем отделы для фильтра
if ($role === 'CEO') {
    $departments = $department->getAll();
} else {
    $departments = [$db->fetchOne("SELECT * FROM departments WHERE id = ?", [$user['department_id']])];
}

// Статистика
$totalEmployees = count($employees);
$departmentCounts = [];
foreach ($employees as $emp) {
    $deptName = $emp['department_name'];
    $departmentCounts[$deptName] = ($departmentCounts[$deptName] ?? 0) + 1;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Сотрудники - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <style>
        .employee-card {
            background: white;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            transition: all 0.3s;
            display: flex;
            align-items: start;
            gap: 20px;
        }
        .employee-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .employee-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
            font-weight: bold;
            flex-shrink: 0;
        }
        .employee-info {
            flex: 1;
        }
        .employee-name {
            font-size: 20px;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 4px;
        }
        .employee-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin-top: 12px;
        }
        .detail-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-light);
            font-size: 14px;
        }
        .detail-item .icon {
            font-size: 16px;
        }
        .employee-actions {
            display: flex;
            gap: 8px;
            flex-direction: column;
        }
        .search-box {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
        }
        .search-box input {
            flex: 1;
            min-width: 300px;
        }
    </style>
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
                <?php if ($role !== 'Employee'): ?>
                <li><a href="<?php echo APP_URL; ?>/modules/projects/list.php"><span class="icon">📁</span> Проекты</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/tasks/list.php"><span class="icon">✓</span> Задачи</a></li>
                <?php endif; ?>
                <li><a href="list.php" class="active"><span class="icon">👥</span> Сотрудники</a></li>
                <?php if ($role === 'CEO'): ?>
                <li><a href="<?php echo APP_URL; ?>/modules/managers/list.php"><span class="icon">👔</span> Менеджеры</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/departments/list.php"><span class="icon">🏢</span> Отделы</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/kpi/list.php"><span class="icon">📈</span> KPI</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/rewards/list.php"><span class="icon">💰</span> Вознаграждения</a></li>
                <?php elseif ($role === 'Manager'): ?>
                <li><a href="<?php echo APP_URL; ?>/modules/kpi/employees.php"><span class="icon">📈</span> KPI команды</a></li>
                <?php endif; ?>
                <li><a href="<?php echo APP_URL; ?>/auth/logout.php"><span class="icon">🚪</span> Выход</a></li>
            </ul>
        </aside>

        <div class="main-content">
            <div class="topbar">
                <h1>Сотрудники</h1>
                <div class="user-menu">
                    <div class="user-info">
                        <div class="name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                        <div class="role"><?php echo $role; ?></div>
                    </div>
                </div>
            </div>

            <div class="content">
                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">👥</div>
                        <div class="stat-details">
                            <div class="stat-value"><?php echo $totalEmployees; ?></div>
                            <div class="stat-label">Всего сотрудников</div>
                        </div>
                    </div>
                    
                    <?php foreach (array_slice($departmentCounts, 0, 3) as $deptName => $count): ?>
                    <div class="stat-card">
                        <div class="stat-icon">🏢</div>
                        <div class="stat-details">
                            <div class="stat-value"><?php echo $count; ?></div>
                            <div class="stat-label"><?php echo htmlspecialchars($deptName); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Filters -->
                <div class="card">
                    <div class="card-header">
                        <h3>Фильтры и поиск</h3>
                        <?php if ($role === 'CEO'): ?>
                        <a href="<?php echo APP_URL; ?>/modules/export/export.php?type=employees<?php echo $departmentFilter ? '&department_id=' . $departmentFilter : ''; ?>" class="btn btn-primary" style="background: #27ae60;">
                            📥 Экспорт в Excel
                        </a>
                        <a href="create.php" class="btn btn-primary">+ Добавить сотрудника</a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="search-box">
                            <input type="text" 
                                   name="search" 
                                   placeholder="🔍 Поиск по имени или email..." 
                                   value="<?php echo htmlspecialchars($searchQuery); ?>">
                            
                            <?php if ($role === 'CEO'): ?>
                            <select name="department_id">
                                <option value="">Все отделы</option>
                                <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>" 
                                        <?php echo $departmentFilter == $dept['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                            
                            <button type="submit" class="btn btn-primary">Найти</button>
                            <?php if ($searchQuery || $departmentFilter): ?>
                            <a href="list.php" class="btn btn-secondary">Сбросить</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Employee List -->
                <div class="card">
                    <div class="card-header">
                        <h3>Список сотрудников (<?php echo $totalEmployees; ?>)</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($employees)): ?>
                        <p class="text-muted">Сотрудники не найдены</p>
                        <?php else: ?>
                        
                        <?php foreach ($employees as $emp): ?>
                        <div class="employee-card">
                            <div class="employee-avatar">
                                <?php 
                                $initials = mb_substr($emp['first_name'], 0, 1) . mb_substr($emp['last_name'], 0, 1);
                                echo htmlspecialchars($initials);
                                ?>
                            </div>
                            
                            <div class="employee-info">
                                <div class="employee-name">
                                    <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?>
                                </div>
                                
                                <div class="employee-details">
                                    <div class="detail-item">
                                        <span class="icon">📧</span>
                                        <span><?php echo htmlspecialchars($emp['email']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="icon">📱</span>
                                        <span><?php echo htmlspecialchars($emp['phone_number']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="icon">🏢</span>
                                        <span><?php echo htmlspecialchars($emp['department_name']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="icon">👔</span>
                                        <span>Менеджер: <?php echo $emp['manager_name'] ?: 'Не назначен'; ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="icon">📅</span>
                                        <span>Принят: <?php echo date('d.m.Y', strtotime($emp['hire_date'])); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="employee-actions">
                                <a href="view.php?id=<?php echo $emp['id']; ?>" class="btn btn-sm btn-primary">
                                    Профиль
                                </a>
                                <a href="<?php echo APP_URL; ?>/modules/kpi/view.php?employee_id=<?php echo $emp['id']; ?>" 
                                   class="btn btn-sm btn-info">
                                    KPI
                                </a>
                                <?php if ($role === 'CEO'): ?>
                                <a href="edit.php?id=<?php echo $emp['id']; ?>" class="btn btn-sm btn-secondary">
                                    Редактировать
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
