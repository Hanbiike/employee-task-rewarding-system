<?php
/**
 * Класс для экспорта данных в Excel (CSV формат)
 */
class ExcelExport {
    
    /**
     * Экспорт данных в CSV файл
     * @param array $data Массив данных для экспорта
     * @param array $headers Заголовки колонок
     * @param string $filename Имя файла для скачивания
     */
    public static function exportToCSV($data, $headers, $filename) {
        // Устанавливаем заголовки для скачивания файла
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Открываем поток вывода
        $output = fopen('php://output', 'w');
        
        // Добавляем BOM для корректного отображения UTF-8 в Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Записываем заголовки
        fputcsv($output, $headers, ';');
        
        // Записываем данные
        foreach ($data as $row) {
            fputcsv($output, $row, ';');
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Экспорт вознаграждений сотрудников
     */
    public static function exportEmployeeRewards($period, $departmentId = null, $periodType = null) {
        require_once __DIR__ . '/Database.php';
        require_once __DIR__ . '/KPI.php';
        require_once __DIR__ . '/Reward.php';
        $reward = new Reward();
        
        $data = $reward->getAllRewards($period, $departmentId, $periodType);
        
        // Если данных нет, выводим сообщение
        if (empty($data)) {
            die("Нет данных для экспорта. Проверьте:<br>- Период: " . htmlspecialchars($period) . "<br>- Тип периода: " . htmlspecialchars($periodType) . "<br>- Отдел ID: " . htmlspecialchars($departmentId ?? 'все') . "<br><br>Возможно, вознаграждения еще не рассчитаны для этого периода.<br><a href='" . $_SERVER['HTTP_REFERER'] . "'>← Вернуться назад</a>");
        }
        
        $headers = [
            'ID',
            'Имя',
            'Фамилия',
            'Email',
            'Отдел',
            'Период',
            'Тип периода',
            'Базовая зарплата',
            'KPI (%)',
            'Премия',
            'Итого'
        ];
        
        $rows = [];
        foreach ($data as $item) {
            $rows[] = [
                $item['employee_id'],
                $item['first_name'],
                $item['last_name'],
                $item['email'],
                $item['department_name'],
                date('m.Y', strtotime($item['period'])),
                self::translatePeriodType($item['period_type']),
                number_format($item['base_salary'], 2, ',', ''),
                number_format($item['kpi_total'], 2, ',', ''),
                number_format($item['bonus_amount'], 2, ',', ''),
                number_format($item['total_amount'], 2, ',', '')
            ];
        }
        
        $filename = 'employee_rewards_' . date('Y-m', strtotime($period)) . '_' . date('YmdHis') . '.csv';
        self::exportToCSV($rows, $headers, $filename);
    }
    
    /**
     * Экспорт вознаграждений менеджеров
     */
    public static function exportManagerRewards($period, $periodType = null) {
        require_once __DIR__ . '/Database.php';
        require_once __DIR__ . '/KPI.php';
        require_once __DIR__ . '/Reward.php';
        $reward = new Reward();
        
        $data = $reward->getAllManagerRewards($period, $periodType);
        
        $headers = [
            'ID',
            'Имя',
            'Фамилия',
            'Email',
            'Отдел',
            'Период',
            'Тип периода',
            'Базовая зарплата',
            'Сотрудников',
            'Премии сотрудников',
            'Процент (%)',
            'Премия',
            'Итого'
        ];
        
        $rows = [];
        foreach ($data as $item) {
            $rows[] = [
                $item['manager_id'],
                $item['first_name'],
                $item['last_name'],
                $item['email'],
                $item['department_name'],
                date('m.Y', strtotime($item['period'])),
                self::translatePeriodType($item['period_type']),
                number_format($item['base_salary'], 2, ',', ''),
                $item['employees_count'],
                number_format($item['total_employee_bonuses'], 2, ',', ''),
                number_format($item['bonus_percentage'], 2, ',', ''),
                number_format($item['bonus_amount'], 2, ',', ''),
                number_format($item['total_amount'], 2, ',', '')
            ];
        }
        
        $filename = 'manager_rewards_' . date('Y-m', strtotime($period)) . '_' . date('YmdHis') . '.csv';
        self::exportToCSV($rows, $headers, $filename);
    }
    
    /**
     * Экспорт KPI сотрудников
     */
    public static function exportEmployeeKPI($period, $departmentId = null) {
        require_once __DIR__ . '/Database.php';
        require_once __DIR__ . '/KPI.php';
        $kpi = new KPI();
        
        $data = $kpi->getAllEmployeesKPI($period, $departmentId);
        
        $headers = [
            'ID',
            'Имя',
            'Фамилия',
            'Email',
            'Отдел',
            'Период',
            'KPI (%)'
        ];
        
        $rows = [];
        foreach ($data as $item) {
            $rows[] = [
                $item['id'],
                $item['first_name'],
                $item['last_name'],
                $item['email'],
                $item['department_name'],
                $period,
                number_format($item['total_kpi'], 2, ',', '')
            ];
        }
        
        $filename = 'employee_kpi_' . $period . '_' . date('YmdHis') . '.csv';
        self::exportToCSV($rows, $headers, $filename);
    }
    
    /**
     * Экспорт списка сотрудников
     */
    public static function exportEmployees($departmentId = null) {
        require_once __DIR__ . '/Database.php';
        $db = Database::getInstance();
        
        $sql = "SELECT e.*, d.name as department_name, 
                CONCAT(m.first_name, ' ', m.last_name) as manager_name
                FROM employees e
                JOIN departments d ON e.department_id = d.id
                LEFT JOIN managers m ON e.manager_id = m.id";
        
        $params = [];
        if ($departmentId) {
            $sql .= " WHERE e.department_id = ?";
            $params[] = $departmentId;
        }
        
        $sql .= " ORDER BY e.last_name, e.first_name";
        
        $data = $db->fetchAll($sql, $params);
        
        $headers = [
            'ID',
            'Имя',
            'Фамилия',
            'Email',
            'Телефон',
            'Отдел',
            'Менеджер',
            'Дата найма',
            'Базовая зарплата'
        ];
        
        $rows = [];
        foreach ($data as $item) {
            $rows[] = [
                $item['id'],
                $item['first_name'],
                $item['last_name'],
                $item['email'],
                $item['phone_number'],
                $item['department_name'],
                $item['manager_name'] ?? '-',
                date('d.m.Y', strtotime($item['hire_date'])),
                number_format($item['base_salary'], 2, ',', '')
            ];
        }
        
        $filename = 'employees_' . date('YmdHis') . '.csv';
        self::exportToCSV($rows, $headers, $filename);
    }
    
    /**
     * Экспорт списка менеджеров
     */
    public static function exportManagers() {
        require_once __DIR__ . '/Database.php';
        $db = Database::getInstance();
        
        $sql = "SELECT m.*, d.name as department_name
                FROM managers m
                JOIN departments d ON m.department_id = d.id
                ORDER BY m.last_name, m.first_name";
        
        $data = $db->fetchAll($sql);
        
        $headers = [
            'ID',
            'Имя',
            'Фамилия',
            'Email',
            'Телефон',
            'Отдел',
            'Должность',
            'Дата найма',
            'Базовая зарплата'
        ];
        
        $rows = [];
        foreach ($data as $item) {
            $rows[] = [
                $item['id'],
                $item['first_name'],
                $item['last_name'],
                $item['email'],
                $item['phone_number'],
                $item['department_name'],
                $item['position'] === 'CEO' ? 'Генеральный директор' : 'Менеджер',
                date('d.m.Y', strtotime($item['hire_date'])),
                number_format($item['base_salary'], 2, ',', '')
            ];
        }
        
        $filename = 'managers_' . date('YmdHis') . '.csv';
        self::exportToCSV($rows, $headers, $filename);
    }
    
    /**
     * Экспорт отделов
     */
    public static function exportDepartments() {
        require_once __DIR__ . '/Database.php';
        $db = Database::getInstance();
        
        $data = $db->fetchAll("
            SELECT d.*,
                   (SELECT COUNT(*) FROM managers WHERE department_id = d.id) as manager_count,
                   (SELECT COUNT(*) FROM employees WHERE department_id = d.id) as employee_count
            FROM departments d
            ORDER BY d.name
        ");
        
        $headers = [
            'ID',
            'Название',
            'Количество сотрудников',
            'Количество менеджеров'
        ];
        
        $rows = [];
        foreach ($data as $item) {
            $rows[] = [
                $item['id'],
                $item['name'],
                $item['employee_count'],
                $item['manager_count']
            ];
        }
        
        $filename = 'departments_' . date('YmdHis') . '.csv';
        self::exportToCSV($rows, $headers, $filename);
    }
    
    /**
     * Перевод типа периода на русский
     */
    private static function translatePeriodType($periodType) {
        $types = [
            'monthly' => 'Месячная',
            'quarterly' => 'Квартальная',
            'yearly' => 'Годовая'
        ];
        return $types[$periodType] ?? $periodType;
    }
}
