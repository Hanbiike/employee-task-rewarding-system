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

$departments = $db->fetchAll("SELECT * FROM departments ORDER BY name");

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_manager'])) {
    if (!User::verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Ошибка безопасности. Обновите страницу и попробуйте снова.';
    } else {
        $data = [
            'first_name' => trim($_POST['first_name']),
            'last_name' => trim($_POST['last_name']),
            'email' => trim($_POST['email']),
            'phone_number' => trim($_POST['phone_number']),
            'department_id' => $_POST['department_id'],
            'position' => $_POST['position'],
            'hire_date' => $_POST['hire_date'],
            'base_salary' => floatval($_POST['base_salary']),
            'password' => $_POST['password']
        ];
        
        if (empty($data['first_name']) || empty($data['last_name']) || empty($data['email'])) {
            $error = 'Заполните все обязательные поля';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'Неверный формат email';
        } elseif ($data['base_salary'] <= 0) {
            $error = 'Укажите корректную базовую зарплату';
        } elseif (strlen($data['password']) < 6) {
            $error = 'Пароль должен быть не менее 6 символов';
        } else {
            try {
                $existingEmail = $db->fetchOne("SELECT id FROM managers WHERE email = ?", [$data['email']]);
                if ($existingEmail) {
                    $error = 'Email уже используется';
                } else {
                    $hashedPassword = password_hash($data['password'], HASH_ALGO, ['cost' => HASH_COST]);
                    
                    $id = $db->insert(
                        "INSERT INTO managers (first_name, last_name, email, password, phone_number, department_id, position, hire_date, base_salary) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [
                            $data['first_name'],
                            $data['last_name'],
                            $data['email'],
                            $hashedPassword,
                            $data['phone_number'],
                            $data['department_id'],
                            $data['position'],
                            $data['hire_date'],
                            $data['base_salary']
                        ]
                    );
                    
                    header('Location: view.php?id=' . $id);
                    exit;
                }
            } catch (Exception $e) {
                $error = 'Ошибка при создании менеджера: ' . $e->getMessage();
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
    <title>Создать менеджера - <?php echo APP_NAME; ?></title>
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
                <h1>Создать менеджера</h1>
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
                        <h3>Новый менеджер</h3>
                        <a href="list.php" class="btn btn-secondary btn-sm">← Назад</a>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo User::getCsrfToken(); ?>">
                            
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
                                           value="<?php echo isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : ''; ?>"
                                           placeholder="+79001234567">
                                </div>
                                
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
                                    <label for="position">Должность *</label>
                                    <select id="position" name="position" required>
                                        <option value="Manager" selected>Manager</option>
                                        <option value="CEO">CEO</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="hire_date">Дата приёма *</label>
                                    <input type="date" id="hire_date" name="hire_date" required 
                                           value="<?php echo isset($_POST['hire_date']) ? $_POST['hire_date'] : date('Y-m-d'); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="base_salary">Базовая зарплата *</label>
                                    <input type="number" id="base_salary" name="base_salary" required step="0.01" min="0"
                                           value="<?php echo isset($_POST['base_salary']) ? htmlspecialchars($_POST['base_salary']) : ''; ?>"
                                           placeholder="Например: 150000.00">
                                    <small style="color: var(--text-light);">Укажите зарплату в рублях</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="password">Пароль *</label>
                                    <input type="password" id="password" name="password" required 
                                           placeholder="Минимум 6 символов">
                                    <small style="color: var(--text-light);">Минимум 6 символов</small>
                                </div>
                            </div>
                            
                            <div class="flex gap-2 mt-3">
                                <button type="submit" name="create_manager" class="btn btn-primary">Создать менеджера</button>
                                <a href="list.php" class="btn btn-secondary">Отмена</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
