<?php
/**
 * Класс для экспорта данных в Excel (CSV формат)
 * 
 * Поддерживает экспорт:
 * - Вознаграждений сотрудников и менеджеров
 * - KPI сотрудников
 * - Списков сотрудников, менеджеров и отделов
 */
class ExcelExport {
    
    /** @var array Словарь типов периодов */
    private static $periodTypes = [
        'monthly' => 'Месячная',
        'quarterly' => 'Квартальная',
        'yearly' => 'Годовая'
    ];
    
    /** @var array Словарь должностей */
    private static $positions = [
        'CEO' => 'Генеральный директор',
        'Manager' => 'Менеджер'
    ];
    
    /**
     * Инициализация зависимостей
     */
    private static function initDependencies(): void {
        require_once __DIR__ . '/Database.php';
    }
    
    /**
     * Инициализация зависимостей для работы с вознаграждениями
     */
    private static function initRewardDependencies(): void {
        self::initDependencies();
        require_once __DIR__ . '/KPI.php';
        require_once __DIR__ . '/Reward.php';
    }
    
    /**
     * Экспорт данных в CSV файл
     * 
     * @param array $data Массив данных для экспорта
     * @param array $headers Заголовки колонок
     * @param string $filename Имя файла для скачивания
     */
    public static function exportToCSV(array $data, array $headers, string $filename): void {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // BOM для корректного отображения UTF-8 в Excel
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
        
        fputcsv($output, $headers, ';');
        
        foreach ($data as $row) {
            fputcsv($output, $row, ';');
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Показать сообщение об отсутствии данных
     * 
     * @param string $message Сообщение
     */
    private static function showNoDataMessage(string $message): void {
        $backUrl = $_SERVER['HTTP_REFERER'] ?? '/';
        die($message . "<br><br><a href='" . htmlspecialchars($backUrl) . "'>← Вернуться назад</a>");
    }
    
    /**
     * Перевод типа периода на русский
     * 
     * @param string|null $periodType Тип периода
     * @return string
     */
    private static function translatePeriodType(?string $periodType): string {
        return self::$periodTypes[$periodType] ?? ($periodType ?? '-');
    }
    
    /**
     * Перевод должности на русский
     * 
     * @param string|null $position Должность
     * @return string
     */
    private static function translatePosition(?string $position): string {
        return self::$positions[$position] ?? ($position ?? '-');
    }
    
    /**
     * Форматирование даты
     * 
     * @param string|null $date Дата
     * @param string $format Формат вывода
     * @return string
     */
    private static function formatDate(?string $date, string $format = 'd.m.Y'): string {
        if (empty($date)) {
            return '-';
        }
        $timestamp = strtotime($date);
        return $timestamp ? date($format, $timestamp) : '-';
    }
    
    /**
     * Форматирование периода (месяц.год)
     * 
     * @param string|null $period Период
     * @return string
     */
    private static function formatPeriod(?string $period): string {
        return self::formatDate($period, 'm.Y');
    }
    
    /**
     * Генерация имени файла
     * 
     * @param string $prefix Префикс файла
     * @param string|null $period Период (опционально)
     * @return string
     */
    private static function generateFilename(string $prefix, ?string $period = null): string {
        $parts = [$prefix];
        
        if ($period) {
            $parts[] = date('Y-m', strtotime($period));
        }
        
        $parts[] = date('YmdHis');
        
        return implode('_', $parts) . '.csv';
    }
    
    /**
     * Экспорт вознаграждений сотрудников
     * 
     * @param string $period Период
     * @param int|null $departmentId ID отдела
     * @param string|null $periodType Тип периода
     */
    public static function exportEmployeeRewards(string $period, ?int $departmentId = null, ?string $periodType = null): void {
        self::initRewardDependencies();
        
        $reward = new Reward();
        $data = $reward->getAllRewards($period, $departmentId, $periodType);
        
        if (empty($data)) {
            $info = [
                "Период: " . htmlspecialchars($period),
                "Тип периода: " . htmlspecialchars($periodType ?? 'все'),
                "Отдел ID: " . htmlspecialchars($departmentId ?? 'все')
            ];
            self::showNoDataMessage(
                "Нет данных для экспорта.<br>" . 
                implode('<br>', $info) . 
                "<br><br>Возможно, вознаграждения еще не рассчитаны для этого периода."
            );
        }
        
        $headers = [
            'ID сотрудника',
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
                self::formatPeriod($item['period']),
                self::translatePeriodType($item['period_type']),
                $item['base_salary'],
                $item['kpi_total'],
                $item['bonus_amount'],
                $item['total_amount']
            ];
        }
        
        self::exportToCSV($rows, $headers, self::generateFilename('employee_rewards', $period));
    }
    
    /**
     * Экспорт вознаграждений менеджеров
     * 
     * @param string $period Период
     * @param string|null $periodType Тип периода
     */
    public static function exportManagerRewards(string $period, ?string $periodType = null): void {
        self::initRewardDependencies();
        
        $reward = new Reward();
        $data = $reward->getAllManagerRewards($period, $periodType);
        
        if (empty($data)) {
            $info = [
                "Период: " . htmlspecialchars($period),
                "Тип периода: " . htmlspecialchars($periodType ?? 'все')
            ];
            self::showNoDataMessage(
                "Нет данных для экспорта.<br>" . 
                implode('<br>', $info) . 
                "<br><br>Возможно, вознаграждения менеджеров еще не рассчитаны для этого периода."
            );
        }
        
        $headers = [
            'ID менеджера',
            'Имя',
            'Фамилия',
            'Email',
            'Отдел',
            'Период',
            'Тип периода',
            'Базовая зарплата',
            'Количество сотрудников',
            'Средний KPI отдела (%)',
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
                self::formatPeriod($item['period']),
                self::translatePeriodType($item['period_type']),
                $item['base_salary'],
                $item['employees_count'],
                $item['avg_department_kpi'],
                $item['bonus_amount'],
                $item['total_amount']
            ];
        }
        
        self::exportToCSV($rows, $headers, self::generateFilename('manager_rewards', $period));
    }
    
    /**
     * Экспорт KPI сотрудников
     * 
     * @param string $period Период
     * @param int|null $departmentId ID отдела
     */
    public static function exportEmployeeKPI(string $period, ?int $departmentId = null): void {
        self::initDependencies();
        require_once __DIR__ . '/KPI.php';
        
        $kpi = new KPI();
        $data = $kpi->getAllEmployeesKPI($period, $departmentId);
        
        if (empty($data)) {
            $info = [
                "Период: " . htmlspecialchars($period),
                "Отдел ID: " . htmlspecialchars($departmentId ?? 'все')
            ];
            self::showNoDataMessage(
                "Нет данных для экспорта.<br>" . 
                implode('<br>', $info) . 
                "<br><br>Возможно, KPI еще не рассчитаны для этого периода."
            );
        }
        
        $headers = [
            'ID сотрудника',
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
                $item['total_kpi']
            ];
        }
        
        self::exportToCSV($rows, $headers, self::generateFilename('employee_kpi', $period));
    }
    
    /**
     * Экспорт списка сотрудников
     * 
     * @param int|null $departmentId ID отдела
     */
    public static function exportEmployees(?int $departmentId = null): void {
        self::initDependencies();
        $db = Database::getInstance();
        
        $sql = "SELECT e.*, 
                       d.name as department_name, 
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
        
        if (empty($data)) {
            self::showNoDataMessage("Нет сотрудников для экспорта.");
        }
        
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
                $item['phone_number'] ?? '-',
                $item['department_name'],
                $item['manager_name'] ?? '-',
                self::formatDate($item['hire_date']),
                $item['base_salary']
            ];
        }
        
        self::exportToCSV($rows, $headers, self::generateFilename('employees'));
    }
    
    /**
     * Экспорт списка менеджеров
     */
    public static function exportManagers(): void {
        self::initDependencies();
        $db = Database::getInstance();
        
        $sql = "SELECT m.*, d.name as department_name
                FROM managers m
                JOIN departments d ON m.department_id = d.id
                ORDER BY m.last_name, m.first_name";
        
        $data = $db->fetchAll($sql);
        
        if (empty($data)) {
            self::showNoDataMessage("Нет менеджеров для экспорта.");
        }
        
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
                $item['phone_number'] ?? '-',
                $item['department_name'],
                self::translatePosition($item['position']),
                self::formatDate($item['hire_date']),
                $item['base_salary']
            ];
        }
        
        self::exportToCSV($rows, $headers, self::generateFilename('managers'));
    }
    
    /**
     * Экспорт отделов
     */
    public static function exportDepartments(): void {
        self::initDependencies();
        $db = Database::getInstance();
        
        $sql = "SELECT d.*,
                       (SELECT COUNT(*) FROM managers WHERE department_id = d.id) as manager_count,
                       (SELECT COUNT(*) FROM employees WHERE department_id = d.id) as employee_count
                FROM departments d
                ORDER BY d.name";
        
        $data = $db->fetchAll($sql);
        
        if (empty($data)) {
            self::showNoDataMessage("Нет отделов для экспорта.");
        }
        
        $headers = [
            'ID',
            'Название отдела',
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
        
        self::exportToCSV($rows, $headers, self::generateFilename('departments'));
    }
}
