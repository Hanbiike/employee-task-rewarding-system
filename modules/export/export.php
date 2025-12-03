<?php
/**
 * Обработчик экспорта данных в Excel
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';
require_once __DIR__ . '/../../classes/ExcelExport.php';

// Проверяем авторизацию
if (!User::isAuthenticated()) {
    header('Location: ' . APP_URL . '/auth/login.php');
    exit;
}

// Получаем тип экспорта
$type = $_GET['type'] ?? null;
$period = $_GET['period'] ?? date('Y-m-01');
$departmentId = $_GET['department_id'] ?? null;
$periodType = $_GET['period_type'] ?? null;

try {
    switch ($type) {
        case 'employee_rewards':
            if (!User::hasRole(['CEO', 'Manager'])) {
                throw new Exception('Доступ запрещен');
            }
            ExcelExport::exportEmployeeRewards($period, $departmentId, $periodType);
            break;
            
        case 'manager_rewards':
            if (!User::hasRole('CEO')) {
                throw new Exception('Доступ запрещен');
            }
            ExcelExport::exportManagerRewards($period, $periodType);
            break;
            
        case 'employee_kpi':
            if (!User::hasRole(['CEO', 'Manager'])) {
                throw new Exception('Доступ запрещен');
            }
            ExcelExport::exportEmployeeKPI($period, $departmentId);
            break;
            
        case 'employees':
            if (!User::hasRole(['CEO', 'Manager'])) {
                throw new Exception('Доступ запрещен');
            }
            ExcelExport::exportEmployees($departmentId);
            break;
            
        case 'managers':
            if (!User::hasRole('CEO')) {
                throw new Exception('Доступ запрещен');
            }
            ExcelExport::exportManagers();
            break;
            
        case 'departments':
            if (!User::hasRole('CEO')) {
                throw new Exception('Доступ запрещен');
            }
            ExcelExport::exportDepartments();
            break;
            
        default:
            throw new Exception('Неизвестный тип экспорта');
    }
} catch (Exception $e) {
    die('Ошибка экспорта: ' . htmlspecialchars($e->getMessage()));
}
