<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Department.php';

if (!User::isAuthenticated() || !User::hasRole('CEO')) {
    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

$employeeId = $_GET['id'] ?? null;
if (!$employeeId) {
    header('Location: list.php');
    exit;
}

$user = User::getCurrentUser();
$role = User::getCurrentRole();
$db = Database::getInstance();
$department = new Department();

// Получаем данные сотрудника
$employee = $db->fetchOne("SELECT * FROM employees WHERE id = ?", [$employeeId]);
if (!$employee) {
    header('Location: list.php');
    exit;
}

$error = null;
$success = null;

// Получаем отделы и менеджеров
$departments = $department->getAll();
$managers = $db->fetchAll(
    "SELECT m.*, d.name as department_name 
     FROM managers m 
     JOIN departments d ON m.department_id = d.id 
     WHERE m.position = 'Manager'
     ORDER BY m.last_name, m.first_name"
);

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_employee'])) {
    if (!User::verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Ошибка безопасности. Обновите страницу.';
    } else {
        $data = [
            'first_name' => trim($_POST['first_name']),
            'last_name' => trim($_POST['last_name']),
            'email' => trim($_POST['email']),
            'phone_number' => trim($_POST['phone_number']),
            'department_id' => $_POST['department_id'],
            'manager_id' => $_POST['manager_id'] ?: null,
            'hire_date' => $_POST['hire_date'],
            'base_salary' => floatval($_POST['base_salary'])
        ];
        
        // Валидация
        if (empty($data['first_name']) || empty($data['last_name'])) {
            $error = 'Имя и фамилия обязательны';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'Некорректный email';
        } elseif ($data['base_salary'] <= 0) {
            $error = 'Укажите корректную базовую зарплату';
        } else {
            // Проверка уникальности email (кроме текущего сотрудника)
            $existing = $db->fetchOne("SELECT id FROM employees WHERE email = ? AND id != ?", [$data['email'], $employeeId]);
            if ($existing) {
                $error = 'Сотрудник с таким email уже существует';
            } else {
                try {
                    $sql = "UPDATE employees SET 
                            first_name = ?, 
                            last_name = ?, 
                            email = ?, 
                            phone_number = ?, 
                            department_id = ?, 
                            manager_id = ?, 
                            hire_date = ?,
                            base_salary = ?
                            WHERE id = ?";
                    
                    $db->update($sql, [
                        $data['first_name'],
                        $data['last_name'],
                        $data['email'],
                        $data['phone_number'],
                        $data['department_id'],
                        $data['manager_id'],
                        $data['hire_date'],
                        $data['base_salary'],
                        $employeeId
                    ]);
                    
                    // Обновление пароля если указан
                    if (!empty($_POST['new_password'])) {
                        if (strlen($_POST['new_password']) < 6) {
                            $error = 'Пароль должен быть не менее 6 символов';
                        } elseif ($_POST['new_password'] !== $_POST['password_confirm']) {
                            $error = 'Пароли не совпадают';
                        } else {
                            $hashedPassword = password_hash($_POST['new_password'], PASSWORD_BCRYPT, ['cost' => 12]);
                            $db->update("UPDATE employees SET password = ? WHERE id = ?", [$hashedPassword, $employeeId]);
                        }
                    }
                    
                    if (!$error) {
                        $success = 'Данные сотрудника успешно обновлены!';
                        // Перезагружаем данные
                        $employee = $db->fetchOne("SELECT * FROM employees WHERE id = ?", [$employeeId]);
                    }
                } catch (Exception $e) {
                    $error = 'Ошибка при обновлении: ' . $e->getMessage();
                }
            }
        }
    }
}

// Обработка удаления
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_employee'])) {
    if (!User::verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Ошибка безопасности';
    } else {
        try {
            // Проверяем, есть ли у сотрудника задачи
            $taskCount = $db->fetchOne("SELECT COUNT(*) as count FROM employee_tasks WHERE employee_id = ?", [$employeeId]);
            
            if ($taskCount['count'] > 0) {
                $error = 'Невозможно удалить сотрудника с активными задачами. Сначала переназначьте задачи.';
            } else {
                // Удаляем связанные данные
                $db->delete("DELETE FROM kpi_values WHERE employee_id = ?", [$employeeId]);
                $db->delete("DELETE FROM rewards WHERE employee_id = ?", [$employeeId]);
                $db->delete("DELETE FROM employees WHERE id = ?", [$employeeId]);
                
                header('Location: list.php?deleted=1');
                exit;
            }
        } catch (Exception $e) {
            $error = 'Ошибка при удалении: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактировать сотрудника - <?php echo APP_NAME; ?></title>
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
                <li><a href="<?php echo APP_URL; ?>/dashboard/ceo.php"><span class="icon">📊</span> Главная</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/projects/list.php"><span class="icon">📁</span> Проекты</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/tasks/list.php"><span class="icon">✓</span> Задачи</a></li>
                <li><a href="list.php" class="active"><span class="icon">👥</span> Сотрудники</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/managers/list.php"><span class="icon">👔</span> Менеджеры</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/departments/list.php"><span class="icon">🏢</span> Отделы</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/kpi/list.php"><span class="icon">📈</span> KPI</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/rewards/list.php"><span class="icon">💰</span> Вознаграждения</a></li>
                <li><a href="<?php echo APP_URL; ?>/auth/logout.php"><span class="icon">🚪</span> Выход</a></li>
            </ul>
        </aside>

        <div class="main-content">
            <div class="topbar">
                <h1>Редактировать сотрудника</h1>
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
                        <h3><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></h3>
                        <div class="flex gap-2">
                            <a href="view.php?id=<?php echo $employeeId; ?>" class="btn btn-secondary btn-sm">Профиль</a>
                            <a href="list.php" class="btn btn-secondary btn-sm">← К списку</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo User::getCsrfToken(); ?>">
                            
                            <h4>Персональная информация</h4>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="first_name">Имя *</label>
                                    <input type="text" id="first_name" name="first_name" required 
                                           value="<?php echo htmlspecialchars($employee['first_name']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="last_name">Фамилия *</label>
                                    <input type="text" id="last_name" name="last_name" required 
                                           value="<?php echo htmlspecialchars($employee['last_name']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="email">Email *</label>
                                    <input type="email" id="email" name="email" required 
                                           value="<?php echo htmlspecialchars($employee['email']); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="phone_number">Телефон *</label>
                                    <input type="tel" id="phone_number" name="phone_number" required 
                                           value="<?php echo htmlspecialchars($employee['phone_number']); ?>">
                                </div>
                            </div>
                            
                            <h4 class="mt-3">Рабочая информация</h4>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="department_id">Отдел *</label>
                                    <select id="department_id" name="department_id" required>
                                        <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['id']; ?>"
                                                <?php echo $employee['department_id'] == $dept['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="manager_id">Менеджер</label>
                                    <select id="manager_id" name="manager_id">
                                        <option value="">Не назначен</option>
                                        <?php foreach ($managers as $manager): ?>
                                        <option value="<?php echo $manager['id']; ?>"
                                                <?php echo $employee['manager_id'] == $manager['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($manager['first_name'] . ' ' . $manager['last_name']); ?>
                                            (<?php echo htmlspecialchars($manager['department_name']); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="hire_date">Дата найма *</label>
                                    <input type="date" id="hire_date" name="hire_date" required 
                                           value="<?php echo $employee['hire_date']; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="base_salary">Базовая зарплата *</label>
                                    <input type="number" id="base_salary" name="base_salary" required step="0.01" min="0"
                                           value="<?php echo htmlspecialchars($employee['base_salary']); ?>"
                                           placeholder="Например: 75000.00">
                                    <small style="color: var(--text-light);">Укажите зарплату в рублях</small>
                                </div>
                            </div>
                            
                            <h4 class="mt-3">Изменить пароль (опционально)</h4>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="new_password">Новый пароль</label>
                                    <input type="password" id="new_password" name="new_password" minlength="6"
                                           placeholder="Оставьте пустым, чтобы не менять">
                                    <small>Минимум 6 символов</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="password_confirm">Подтверждение пароля</label>
                                    <input type="password" id="password_confirm" name="password_confirm" minlength="6">
                                </div>
                            </div>
                            
                            <div class="flex gap-2 mt-3">
                                <button type="submit" name="update_employee" class="btn btn-primary">
                                    ✓ Сохранить изменения
                                </button>
                                <a href="view.php?id=<?php echo $employeeId; ?>" class="btn btn-secondary">Отмена</a>
                            </div>
                        </form>
                        
                        <!-- Delete Section -->
                        <div style="margin-top: 40px; padding-top: 40px; border-top: 2px solid var(--border-color);">
                            <h4 style="color: var(--danger-color);">Опасная зона</h4>
                            <p>Удаление сотрудника приведет к удалению всех его данных KPI и вознаграждений.</p>
                            <form method="POST" onsubmit="return confirm('Вы уверены? Это действие необратимо!');">
                                <input type="hidden" name="csrf_token" value="<?php echo User::getCsrfToken(); ?>">
                                <button type="submit" name="delete_employee" class="btn btn-danger">
                                    🗑️ Удалить сотрудника
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Проверка совпадения паролей
        document.querySelector('form').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirm = document.getElementById('password_confirm').value;
            
            if (newPassword && newPassword !== confirm) {
                e.preventDefault();
                alert('Пароли не совпадают!');
                return false;
            }
        });
    </script>
</body>
</html>
