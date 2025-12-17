<?php
/**
 * Класс для работы с KPI
 */
class KPI {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Создать новый KPI показатель
     */
    public function createIndicator($data) {
        $sql = "INSERT INTO kpi (name, description, weight, target_value, measurement_unit) 
                VALUES (?, ?, ?, ?, ?)";
        
        return $this->db->insert($sql, [
            $data['name'],
            $data['description'] ?? null,
            $data['weight'],
            $data['target_value'] ?? 100,
            $data['measurement_unit'] ?? '%'
        ]);
    }

    /**
     * Обновить KPI показатель
     */
    public function updateIndicator($kpiId, $data) {
        $sql = "UPDATE kpi SET 
                name = ?, 
                description = ?, 
                weight = ?, 
                target_value = ?, 
                measurement_unit = ?
                WHERE id = ?";
        
        return $this->db->update($sql, [
            $data['name'],
            $data['description'],
            $data['weight'],
            $data['target_value'],
            $data['measurement_unit'],
            $kpiId
        ]);
    }

    /**
     * Получить все KPI показатели
     */
    public function getAllIndicators() {
        return $this->db->fetchAll(
            "SELECT * FROM kpi ORDER BY name"
        );
    }

    /**
     * Получить KPI показатель по ID
     */
    public function getIndicatorById($kpiId) {
        return $this->db->fetchOne(
            "SELECT * FROM kpi WHERE id = ?",
            [$kpiId]
        );
    }

    /**
     * Удалить KPI показатель
     */
    public function deleteIndicator($kpiId) {
        return $this->db->delete("DELETE FROM kpi WHERE id = ?", [$kpiId]);
    }

    /**
     * Установить значение KPI для сотрудника
     */
    public function setEmployeeValue($employeeId, $kpiId, $value, $period) {
        // Нормализуем период к формату YYYY-MM-01
        if (strlen($period) == 7) { // формат YYYY-MM
            $period = $period . '-01';
        }
        
        // Проверяем, есть ли уже запись
        $existing = $this->db->fetchOne(
            "SELECT id FROM kpi_values WHERE employee_id = ? AND kpi_id = ? AND DATE_FORMAT(period, '%Y-%m') = DATE_FORMAT(?, '%Y-%m')",
            [$employeeId, $kpiId, $period]
        );

        if ($existing) {
            // Обновляем существующую
            $sql = "UPDATE kpi_values SET value = ? WHERE id = ?";
            return $this->db->update($sql, [$value, $existing['id']]);
        } else {
            // Создаём новую
            $sql = "INSERT INTO kpi_values (employee_id, kpi_id, value, period) VALUES (?, ?, ?, ?)";
            return $this->db->insert($sql, [$employeeId, $kpiId, $value, $period]);
        }
    }

    /**
     * Получить все значения KPI сотрудника за период
     */
    public function getEmployeeValues($employeeId, $period) {
        return $this->db->fetchAll(
            "SELECT kv.*, k.name as kpi_name, k.description, k.weight, k.target_value, k.measurement_unit
             FROM kpi_values kv
             JOIN kpi k ON kv.kpi_id = k.id
             WHERE kv.employee_id = ? AND DATE_FORMAT(kv.period, '%Y-%m') = ?
             ORDER BY k.weight DESC",
            [$employeeId, $period]
        );
    }

    /**
     * Получить историю KPI сотрудника
     */
    public function getEmployeeHistory($employeeId, $limit = 12) {
        // Текущий период (не показываем будущие периоды)
        $currentPeriod = date('Y-m');
        
        // Получаем все уникальные периоды для сотрудника из kpi_values и задач
        // Форматируем все даты в формат YYYY-MM для единообразия
        $sql = "SELECT DISTINCT period FROM (
                    SELECT DISTINCT DATE_FORMAT(period, '%Y-%m') as period 
                    FROM kpi_values 
                    WHERE employee_id = ?
                    UNION
                    SELECT DISTINCT DATE_FORMAT(deadline, '%Y-%m') as period 
                    FROM tasks t
                    JOIN employee_tasks et ON t.id = et.task_id
                    WHERE et.employee_id = ? AND t.deadline IS NOT NULL
                ) as periods
                WHERE period <= ?
                ORDER BY period DESC
                LIMIT ?";
        
        $periods = $this->db->fetchAll($sql, [$employeeId, $employeeId, $currentPeriod, $limit]);
        
        // Для каждого периода рассчитываем правильный KPI
        $history = [];
        foreach ($periods as $periodRow) {
            $period = $periodRow['period'];
            $totalKpi = $this->calculateTotalKPI($employeeId, $period);
            
            $history[] = [
                'period' => $period,
                'total_kpi' => $totalKpi
            ];
        }
        
        return $history;
    }

    /**
     * Рассчитать итоговый KPI сотрудника за период
     * Теперь учитывает:
     * - N% - процент выполненных задач с учетом их весов
     * - (100-N)% - оценка менеджера по глобальным метрикам
     * Возвращает значение в процентах (0-100+)
     */
    public function calculateTotalKPI($employeeId, $period, $periodType = 'monthly') {
        // Нормализуем период к формату YYYY-MM
        if (strlen($period) > 7) {
            $period = substr($period, 0, 7); // берем только YYYY-MM
        }
        
        // Получаем настройки KPI
        $settings = $this->getKPISettings();
        $tasksWeight = $settings['tasks_weight_percentage'] / 100; // N%
        $managerWeight = $settings['manager_evaluation_percentage'] / 100; // (100-N)%
        
        // Получаем KPI на основе задач (уже в процентах) с учетом типа периода
        require_once __DIR__ . '/Task.php';
        $taskClass = new Task();
        $tasksKPI = $taskClass->calculateTasksKPIPercentage($employeeId, $period, $periodType);
        
        // Получаем оценку менеджера (уже в процентах)
        $managerKPI = $this->calculateManagerEvaluation($employeeId, $period);
        
        // Комбинированный KPI в процентах
        $totalKPI = ($tasksKPI * $tasksWeight) + ($managerKPI * $managerWeight);
        
        return round($totalKPI, 2);
    }

    /**
     * Рассчитать оценку менеджера (старая логика KPI)
     */
    private function calculateManagerEvaluation($employeeId, $period) {
        $values = $this->getEmployeeValues($employeeId, $period);
        
        if (empty($values)) {
            return 0;
        }

        $weightedSum = 0;
        $totalWeight = 0;

        foreach ($values as $value) {
            if ($value['target_value'] > 0) {
                // Рассчитываем процент достижения цели (0-150%)
                $achievement = ($value['value'] / $value['target_value']) * 100;
                $achievement = min($achievement, 150); // Максимум 150% от цели
                
                // Добавляем взвешенное значение
                $weightedSum += $achievement * ($value['weight'] / 100);
            } else {
                // Если нет целевого значения, используем само значение как процент
                $weightedSum += $value['value'] * ($value['weight'] / 100);
            }
            $totalWeight += $value['weight'];
        }

        // Нормализуем если сумма весов не равна 100
        if ($totalWeight > 0 && $totalWeight != 100) {
            $weightedSum = ($weightedSum / $totalWeight) * 100;
        }

        return round($weightedSum, 2);
    }

    /**
     * Получить настройки системы KPI
     */
    public function getKPISettings() {
        $settings = $this->db->fetchOne(
            "SELECT * FROM kpi_settings ORDER BY id DESC LIMIT 1"
        );
        
        if (!$settings) {
            // Возвращаем значения по умолчанию
            return [
                'tasks_weight_percentage' => 50,
                'manager_evaluation_percentage' => 50,
                'manager_bonus_percentage' => 100.00
            ];
        }
        
        return $settings;
    }

    /**
     * Обновить настройки KPI (только для CEO)
     */
    public function updateKPISettings($tasksWeight, $managerWeight, $updatedByManagerId, $managerBonusPercentage = null) {
        // Проверяем, что сумма равна 100
        if ($tasksWeight + $managerWeight != 100) {
            throw new Exception('Сумма процентов должна быть равна 100');
        }
        
        if ($managerBonusPercentage !== null) {
            $sql = "UPDATE kpi_settings SET 
                    tasks_weight_percentage = ?, 
                    manager_evaluation_percentage = ?,
                    manager_bonus_percentage = ?,
                    updated_by_manager_id = ?
                    WHERE id = 1";
            return $this->db->update($sql, [$tasksWeight, $managerWeight, $managerBonusPercentage, $updatedByManagerId]);
        } else {
            $sql = "UPDATE kpi_settings SET 
                    tasks_weight_percentage = ?, 
                    manager_evaluation_percentage = ?,
                    updated_by_manager_id = ?
                    WHERE id = 1";
            return $this->db->update($sql, [$tasksWeight, $managerWeight, $updatedByManagerId]);
        }
    }

    /**
     * Получить детализацию KPI сотрудника
     */
    public function getKPIBreakdown($employeeId, $period, $periodType = 'monthly') {
        $settings = $this->getKPISettings();
        
        require_once __DIR__ . '/Task.php';
        $taskClass = new Task();
        
        $tasksKPI = $taskClass->calculateTasksKPIPercentage($employeeId, $period, $periodType);
        $managerKPI = $this->calculateManagerEvaluation($employeeId, $period);
        
        $tasksWeight = $settings['tasks_weight_percentage'] / 100;
        $managerWeight = $settings['manager_evaluation_percentage'] / 100;
        
        $tasksContribution = $tasksKPI * $tasksWeight;
        $managerContribution = $managerKPI * $managerWeight;
        
        return [
            'tasks_kpi_percentage' => $tasksKPI,
            'manager_evaluation' => $managerKPI,
            'tasks_weight' => $settings['tasks_weight_percentage'],
            'manager_weight' => $settings['manager_evaluation_percentage'],
            'tasks_contribution' => round($tasksContribution, 2),
            'manager_contribution' => round($managerContribution, 2),
            'total_kpi' => round($tasksContribution + $managerContribution, 2),
            'tasks_data' => $taskClass->getTasksKPIData($employeeId, $period, $periodType)
        ];
    }


    /**
     * Получить KPI всех сотрудников за период
     */
    public function getAllEmployeesKPI($period, $departmentId = null) {
        $sql = "SELECT DISTINCT 
                    e.id, 
                    e.first_name, 
                    e.last_name, 
                    e.email,
                    d.name as department_name
                FROM employees e
                JOIN departments d ON e.department_id = d.id
                LEFT JOIN kpi_values kv ON e.id = kv.employee_id AND kv.period = ?";
        
        $params = [$period];
        
        if ($departmentId) {
            $sql .= " WHERE e.department_id = ?";
            $params[] = $departmentId;
        }
        
        $sql .= " ORDER BY e.last_name, e.first_name";
        
        $employees = $this->db->fetchAll($sql, $params);
        
        // Для каждого сотрудника рассчитываем KPI
        foreach ($employees as &$employee) {
            $employee['total_kpi'] = $this->calculateTotalKPI($employee['id'], $period);
        }
        
        return $employees;
    }

    /**
     * Получить средний KPI по отделу за период
     * @param int $departmentId ID отдела
     * @param string $period Период в формате YYYY-MM
     * @param string $periodType Тип периода: monthly, quarterly, yearly
     * @return float Средний KPI в процентах
     */
    public function getDepartmentAverageKPI($departmentId, $period, $periodType = 'monthly') {
        // Получаем список сотрудников отдела
        $employees = $this->db->fetchAll(
            "SELECT id FROM employees WHERE department_id = ?",
            [$departmentId]
        );
        
        if (empty($employees)) {
            return 0;
        }

        $total = 0;
        $count = 0;

        // Определяем периоды для расчёта в зависимости от типа
        $periods = $this->getPeriodsForType($period, $periodType);

        foreach ($employees as $employee) {
            $employeeKpiSum = 0;
            $employeePeriodCount = 0;
            
            foreach ($periods as $p) {
                $kpi = $this->calculateTotalKPI($employee['id'], $p, 'monthly');
                if ($kpi > 0) {
                    $employeeKpiSum += $kpi;
                    $employeePeriodCount++;
                }
            }
            
            if ($employeePeriodCount > 0) {
                $total += ($employeeKpiSum / $employeePeriodCount);
                $count++;
            }
        }

        return $count > 0 ? round($total / $count, 2) : 0;
    }

    /**
     * Получить список периодов для расчёта в зависимости от типа
     * @param string $period Базовый период в формате YYYY-MM
     * @param string $periodType Тип: monthly, quarterly, yearly
     * @return array Список периодов в формате YYYY-MM
     */
    private function getPeriodsForType($period, $periodType) {
        $periods = [];
        $date = new DateTime($period . '-01');
        
        switch ($periodType) {
            case 'yearly':
                // Все 12 месяцев года
                $year = $date->format('Y');
                for ($m = 1; $m <= 12; $m++) {
                    $periods[] = sprintf('%s-%02d', $year, $m);
                }
                break;
                
            case 'quarterly':
                // 3 месяца квартала
                $month = (int)$date->format('m');
                $quarter = ceil($month / 3);
                $startMonth = ($quarter - 1) * 3 + 1;
                $year = $date->format('Y');
                for ($m = $startMonth; $m < $startMonth + 3; $m++) {
                    $periods[] = sprintf('%s-%02d', $year, $m);
                }
                break;
                
            case 'monthly':
            default:
                // Только текущий месяц
                $periods[] = $date->format('Y-m');
                break;
        }
        
        return $periods;
    }

    /**
     * Получить топ сотрудников по KPI
     */
    public function getTopEmployees($period, $limit = 10, $departmentId = null) {
        $employees = $this->getAllEmployeesKPI($period, $departmentId);
        
        // Сортируем по KPI
        usort($employees, function($a, $b) {
            return $b['total_kpi'] <=> $a['total_kpi'];
        });

        return array_slice($employees, 0, $limit);
    }
}
