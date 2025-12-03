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

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_department'])) {
    if (!User::verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Ошибка безопасности. Обновите страницу и попробуйте снова.';
    } else {
        $name = trim($_POST['name']);
        
        if (empty($name)) {
            $error = 'Название отдела обязательно';
        } else {
            try {
                $existing = $db->fetchOne("SELECT id FROM departments WHERE name = ? AND id != ?", [$name, $deptId]);
                if ($existing) {
                    $error = 'Отдел с таким названием уже существует';
                } else {
                    $db->update("UPDATE departments SET name = ? WHERE id = ?", [$name, $deptId]);
                    $success = 'Отдел успешно обновлён!';
                    $dept = $db->fetchOne("SELECT * FROM departments WHERE id = ?", [$deptId]);
                }
            } catch (Exception $e) {
                $error = 'Ошибка при обновлении отдела: ' . $e->getMessage();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_department'])) {
    if (!User::verifyCsrfToken($_POST['csrf_token'])) {
        $error = 'Ошибка безопасности. Обновите страницу и попробуйте снова.';
    } else {
        try {
            // Проверяем, есть ли связанные записи
            $hasManagers = $db->fetchOne("SELECT COUNT(*) as cnt FROM managers WHERE department_id = ?", [$deptId]);
            $hasEmployees = $db->fetchOne("SELECT COUNT(*) as cnt FROM employees WHERE department_id = ?", [$deptId]);
            
            if ($hasManagers['cnt'] > 0 || $hasEmployees['cnt'] > 0) {
                $error = 'Невозможно удалить отдел: в нём есть менеджеры или сотрудники';
            } else {
                $db->delete("DELETE FROM project_departments WHERE department_id = ?", [$deptId]);
                $db->delete("DELETE FROM departments WHERE id = ?", [$deptId]);
                header('Location: list.php?success=deleted');
                exit;
            }
        } catch (Exception $e) {
            $error = 'Ошибка при удалении отдела: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактировать отдел - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <style>
        .delete-section {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px solid var(--border-color);
        }
        .delete-warning {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
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
                <li><a href="list.php" class="active"><span class="icon">🏢</span> Отделы</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/kpi/list.php"><span class="icon">📈</span> KPI</a></li>
                <li><a href="<?php echo APP_URL; ?>/modules/rewards/list.php"><span class="icon">💰</span> Вознаграждения</a></li>
                <li><a href="<?php echo APP_URL; ?>/auth/logout.php"><span class="icon">🚪</span> Выход</a></li>
            </ul>
        </aside>

        <div class="main-content">
            <div class="topbar">
                <h1>Редактировать отдел</h1>
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
                        <h3><?php echo htmlspecialchars($dept['name']); ?></h3>
                        <a href="list.php" class="btn btn-secondary btn-sm">← Назад</a>
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
                            
                            <div class="form-group">
                                <label for="name">Название отдела *</label>
                                <input type="text" id="name" name="name" required 
                                       value="<?php echo htmlspecialchars($dept['name']); ?>">
                            </div>
                            
                            <div class="flex gap-2 mt-3">
                                <button type="submit" name="update_department" class="btn btn-primary">Сохранить изменения</button>
                                <a href="list.php" class="btn btn-secondary">Отмена</a>
                            </div>
                        </form>
                        
                        <!-- Секция удаления -->
                        <div class="delete-section">
                            <h4 style="color: #dc3545; margin-bottom: 15px;">⚠️ Опасная зона</h4>
                            <div class="delete-warning">
                                <strong>Внимание!</strong> Удаление отдела возможно только если в нём нет менеджеров и сотрудников.
                            </div>
                            <form method="POST" action="" onsubmit="return confirm('Вы уверены, что хотите удалить этот отдел? Это действие необратимо!');">
                                <input type="hidden" name="csrf_token" value="<?php echo User::getCsrfToken(); ?>">
                                <button type="submit" name="delete_department" class="btn btn-danger">Удалить отдел</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
