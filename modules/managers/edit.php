<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';

if (!User::isAuthenticated() || !User::hasRole('CEO')) {
    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

$managerId = $_GET['id'] ?? null;
if (!$managerId) {
    header('Location: list.php');
    exit;
}

$user = User::getCurrentUser();
$db = Database::getInstance();

$manager = $db->fetchOne("SELECT * FROM managers WHERE id = ?", [$managerId]);
if (!$manager) {
    header('Location: list.php');
    exit;
}

$departments = $db->fetchAll("SELECT * FROM departments ORDER BY name");
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_manager'])) {
    if (!User::verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Ошибка безопасности.';
    } else {
        $baseSalary = floatval($_POST['base_salary']);
        if ($baseSalary <= 0) {
            $error = 'Укажите корректную базовую зарплату';
        }
        
        if (!$error) {
            $sql = "UPDATE managers SET first_name = ?, last_name = ?, email = ?, phone_number = ?, department_id = ?, position = ?, hire_date = ?, base_salary = ? WHERE id = ?";
            $params = [
                trim($_POST['first_name']),
                trim($_POST['last_name']),
                trim($_POST['email']),
                trim($_POST['phone_number']),
                $_POST['department_id'],
                $_POST['position'],
                $_POST['hire_date'],
                $baseSalary,
                $managerId
            ];
        }
        
        try {
            if (!$error) {
                $db->update($sql, $params);
                
                // Обновление пароля (если указан)
                if (!empty($_POST['password'])) {
                    $hashedPassword = password_hash($_POST['password'], HASH_ALGO, ['cost' => HASH_COST]);
                    $db->update("UPDATE managers SET password = ? WHERE id = ?", [$hashedPassword, $managerId]);
                }
                
                $success = 'Менеджер успешно обновлён!';
                $manager = $db->fetchOne("SELECT * FROM managers WHERE id = ?", [$managerId]);
            }
        } catch (Exception $e) {
            $error = 'Ошибка: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактировать менеджера - <?php echo APP_NAME; ?></title>
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
                <h1>Редактировать менеджера</h1>
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
                        <h3><?php echo htmlspecialchars($manager['first_name'] . ' ' . $manager['last_name']); ?></h3>
                        <a href="list.php" class="btn btn-secondary btn-sm">← Назад</a>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo User::getCsrfToken(); ?>">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="first_name">Имя *</label>
                                    <input type="text" id="first_name" name="first_name" required value="<?php echo htmlspecialchars($manager['first_name']); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="last_name">Фамилия *</label>
                                    <input type="text" id="last_name" name="last_name" required value="<?php echo htmlspecialchars($manager['last_name']); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="email">Email *</label>
                                    <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($manager['email']); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="phone_number">Телефон *</label>
                                    <input type="tel" id="phone_number" name="phone_number" required value="<?php echo htmlspecialchars($manager['phone_number']); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="department_id">Отдел *</label>
                                    <select id="department_id" name="department_id" required>
                                        <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['id']; ?>" <?php echo $manager['department_id'] == $dept['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="position">Должность *</label>
                                    <select id="position" name="position" required>
                                        <option value="Manager" <?php echo $manager['position'] === 'Manager' ? 'selected' : ''; ?>>Manager</option>
                                        <option value="CEO" <?php echo $manager['position'] === 'CEO' ? 'selected' : ''; ?>>CEO</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="hire_date">Дата приёма *</label>
                                    <input type="date" id="hire_date" name="hire_date" required value="<?php echo $manager['hire_date']; ?>">
                                </div>
                                <div class="form-group">
                                    <label for="base_salary">Базовая зарплата *</label>
                                    <input type="number" id="base_salary" name="base_salary" required step="0.01" min="0"
                                           value="<?php echo htmlspecialchars($manager['base_salary']); ?>"
                                           placeholder="Например: 150000.00">
                                    <small style="color: var(--text-light);">Укажите зарплату в рублях</small>
                                </div>
                                <div class="form-group">
                                    <label for="password">Новый пароль</label>
                                    <input type="password" id="password" name="password" placeholder="Оставьте пустым, чтобы не менять">
                                    <small style="color: var(--text-light);">Минимум 6 символов</small>
                                </div>
                            </div>
                            <div class="flex gap-2 mt-3">
                                <button type="submit" name="update_manager" class="btn btn-primary">Сохранить изменения</button>
                                <a href="view.php?id=<?php echo $managerId; ?>" class="btn btn-secondary">Отмена</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
