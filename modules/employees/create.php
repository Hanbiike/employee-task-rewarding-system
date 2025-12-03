<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/Department.php';

if (!User::isAuthenticated() || !User::hasRole('CEO')) {
    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

$user = User::getCurrentUser();
$role = User::getCurrentRole();
$db = Database::getInstance();
$department = new Department();

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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_employee'])) {
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
            'base_salary' => floatval($_POST['base_salary']),
            'password' => password_hash($_POST['password'], PASSWORD_BCRYPT, ['cost' => 12])
        ];
        
        // Валидация
        if (empty($data['first_name']) || empty($data['last_name'])) {
            $error = 'Имя и фамилия обязательны';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'Некорректный email';
        } elseif ($data['base_salary'] <= 0) {
            $error = 'Укажите корректную базовую зарплату';
        } elseif (empty($data['password']) || strlen($_POST['password']) < 6) {
            $error = 'Пароль должен быть не менее 6 символов';
        } else {
            // Проверка уникальности email
            $existing = $db->fetchOne("SELECT id FROM employees WHERE email = ?", [$data['email']]);
            if ($existing) {
                $error = 'Сотрудник с таким email уже существует';
            } else {
                try {
                    $sql = "INSERT INTO employees (first_name, last_name, email, password, phone_number, department_id, manager_id, hire_date, base_salary) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $employeeId = $db->insert($sql, [
                        $data['first_name'],
                        $data['last_name'],
                        $data['email'],
                        $data['password'],
                        $data['phone_number'],
                        $data['department_id'],
                        $data['manager_id'],
                        $data['hire_date'],
                        $data['base_salary']
                    ]);
                    
                    $success = 'Сотрудник успешно добавлен!';
                    header('Location: view.php?id=' . $employeeId);
                    exit;
                } catch (Exception $e) {
                    $error = 'Ошибка при создании: ' . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Добавить сотрудника - <?php echo APP_NAME; ?></title>
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
                <h1>Добавить сотрудника</h1>
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
                        <h3>Новый сотрудник</h3>
                        <a href="list.php" class="btn btn-secondary btn-sm">← Назад к списку</a>
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
                                           value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="last_name">Фамилия *</label>
                                    <input type="text" id="last_name" name="last_name" required 
                                           value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="email">Email *</label>
                                    <input type="email" id="email" name="email" required 
                                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="phone_number">Телефон *</label>
                                    <input type="tel" id="phone_number" name="phone_number" required 
                                           placeholder="+7 (999) 123-45-67"
                                           value="<?php echo isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : ''; ?>">
                                </div>
                            </div>
                            
                            <h4 class="mt-3">Рабочая информация</h4>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="department_id">Отдел *</label>
                                    <select id="department_id" name="department_id" required>
                                        <option value="">Выберите отдел</option>
                                        <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['id']; ?>"
                                                <?php echo (isset($_POST['department_id']) && $_POST['department_id'] == $dept['id']) ? 'selected' : ''; ?>>
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
                                                <?php echo (isset($_POST['manager_id']) && $_POST['manager_id'] == $manager['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($manager['first_name'] . ' ' . $manager['last_name']); ?>
                                            (<?php echo htmlspecialchars($manager['department_name']); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="hire_date">Дата найма *</label>
                                    <input type="date" id="hire_date" name="hire_date" required 
                                           value="<?php echo isset($_POST['hire_date']) ? $_POST['hire_date'] : date('Y-m-d'); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="base_salary">Базовая зарплата *</label>
                                    <input type="number" id="base_salary" name="base_salary" required step="0.01" min="0"
                                           value="<?php echo isset($_POST['base_salary']) ? htmlspecialchars($_POST['base_salary']) : ''; ?>"
                                           placeholder="Например: 75000.00">
                                    <small style="color: var(--text-light);">Укажите зарплату в рублях</small>
                                </div>
                            </div>
                            
                            <h4 class="mt-3">Доступ к системе</h4>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="password">Пароль *</label>
                                    <input type="password" id="password" name="password" required minlength="6"
                                           placeholder="Минимум 6 символов">
                                    <small>Сотрудник сможет сменить пароль после первого входа</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="password_confirm">Подтверждение пароля *</label>
                                    <input type="password" id="password_confirm" name="password_confirm" required minlength="6">
                                </div>
                            </div>
                            
                            <div class="flex gap-2 mt-3">
                                <button type="submit" name="create_employee" class="btn btn-primary">
                                    ✓ Добавить сотрудника
                                </button>
                                <a href="list.php" class="btn btn-secondary">Отмена</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Проверка совпадения паролей
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('password_confirm').value;
            
            if (password !== confirm) {
                e.preventDefault();
                alert('Пароли не совпадают!');
                return false;
            }
        });
    </script>
</body>
</html>
