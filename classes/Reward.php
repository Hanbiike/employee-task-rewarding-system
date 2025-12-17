<?php
/**
 * Класс для работы с вознаграждениями
 */
class Reward {
    private $db;
    private $kpi;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->kpi = new KPI();
    }

    /**
     * Получить базовую зарплату сотрудника
     */
    private function getEmployeeBaseSalary($employeeId) {
        $employee = $this->db->fetchOne(
            "SELECT base_salary FROM employees WHERE id = ?",
            [$employeeId]
        );
        return $employee ? floatval($employee['base_salary']) : 0;
    }

    /**
     * Определить тип периода
     */
    private function determinePeriodType($period) {
        $date = new DateTime($period);
        $month = (int)$date->format('m');
        
        // Годовой - декабрь
        if ($month == 12) {
            return 'yearly';
        }
        // Квартальный - март, июнь, сентябрь, декабрь
        if (in_array($month, [3, 6, 9, 12])) {
            return 'quarterly';
        }
        // Месячный - все остальные
        return 'monthly';
    }

    /**
     * Рассчитать и сохранить вознаграждение
     */
    public function calculateAndSave($employeeId, $period, $periodType = null) {
        // Нормализуем период к формату YYYY-MM-01
        if (strlen($period) == 7) { // формат YYYY-MM
            $period = $period . '-01';
        }
        
        // Получаем базовую зарплату сотрудника
        $baseSalary = $this->getEmployeeBaseSalary($employeeId);
        
        if ($baseSalary <= 0) {
            throw new Exception("Базовая зарплата сотрудника не установлена");
        }

        // Определяем тип периода, если не указан
        if (!$periodType) {
            $periodType = $this->determinePeriodType($period);
        }

        // Получаем итоговый KPI (в процентах) с учетом типа периода
        $kpiTotal = $this->kpi->calculateTotalKPI($employeeId, $period, $periodType);
        
        // Рассчитываем бонус в зависимости от типа периода и KPI
        // Формула: Bonus = BaseSalary * (KPI_total/100)
        // Для месячной премии - умножаем на 1
        // Для квартальной - НЕ умножаем на 3 (это одна премия за квартал)
        // Для годовой - НЕ умножаем на 12 (это одна премия за год)
        $bonusAmount = $baseSalary * ($kpiTotal / 100);
        
        // Общая сумма = базовая зарплата + бонус
        $totalAmount = $baseSalary + $bonusAmount;
        
        // Проверяем, есть ли уже запись
        $existing = $this->db->fetchOne(
            "SELECT id FROM rewards WHERE employee_id = ? AND DATE_FORMAT(period, '%Y-%m') = DATE_FORMAT(?, '%Y-%m') AND period_type = ?",
            [$employeeId, $period, $periodType]
        );

        if ($existing) {
            // Обновляем
            $sql = "UPDATE rewards SET base_salary = ?, kpi_total = ?, bonus_amount = ?, total_amount = ? WHERE id = ?";
            $this->db->update($sql, [$baseSalary, $kpiTotal, $bonusAmount, $totalAmount, $existing['id']]);
            return $existing['id'];
        } else {
            // Создаём новую
            $sql = "INSERT INTO rewards (employee_id, period, period_type, base_salary, kpi_total, bonus_amount, total_amount) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            return $this->db->insert($sql, [$employeeId, $period, $periodType, $baseSalary, $kpiTotal, $bonusAmount, $totalAmount]);
        }
    }

    /**
     * Рассчитать вознаграждения для всех сотрудников отдела
     */
    public function calculateForDepartment($departmentId, $period, $periodType = null) {
        $employees = $this->db->fetchAll(
            "SELECT id FROM employees WHERE department_id = ?",
            [$departmentId]
        );

        $results = [];
        foreach ($employees as $employee) {
            try {
                $results[] = $this->calculateAndSave($employee['id'], $period, $periodType);
            } catch (Exception $e) {
                // Продолжаем расчёт для остальных сотрудников
                continue;
            }
        }

        return $results;
    }

    /**
     * Рассчитать вознаграждения для всех сотрудников
     */
    public function calculateForAll($period, $periodType = null) {
        $employees = $this->db->fetchAll("SELECT id FROM employees");

        $results = [];
        foreach ($employees as $employee) {
            try {
                $results[] = $this->calculateAndSave($employee['id'], $period, $periodType);
            } catch (Exception $e) {
                // Продолжаем расчёт для остальных сотрудников
                continue;
            }
        }

        return $results;
    }

    /**
     * Получить вознаграждение сотрудника за период
     */
    public function getEmployeeReward($employeeId, $period, $periodType = null) {
        $sql = "SELECT r.*, 
                    e.first_name, 
                    e.last_name, 
                    e.email,
                    e.base_salary as employee_base_salary,
                    d.name as department_name
             FROM rewards r
             JOIN employees e ON r.employee_id = e.id
             JOIN departments d ON e.department_id = d.id
             WHERE r.employee_id = ? AND r.period = ?";
        
        $params = [$employeeId, $period];
        
        if ($periodType) {
            $sql .= " AND r.period_type = ?";
            $params[] = $periodType;
        }
        
        return $this->db->fetchOne($sql, $params);
    }

    /**
     * Получить историю вознаграждений сотрудника
     */
    public function getEmployeeHistory($employeeId, $limit = 12, $periodType = null) {
        $sql = "SELECT * FROM rewards 
             WHERE employee_id = ?";
        
        $params = [$employeeId];
        
        if ($periodType) {
            $sql .= " AND period_type = ?";
            $params[] = $periodType;
        }
        
        $sql .= " ORDER BY period DESC LIMIT ?";
        $params[] = $limit;
        
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Получить все вознаграждения за период
     */
    public function getAllRewards($period, $departmentId = null, $periodType = null) {
        // Нормализуем период к формату YYYY-MM-01
        if (strlen($period) == 7) { // формат YYYY-MM
            $period = $period . '-01';
        }
        
        $sql = "SELECT r.*, 
                    e.first_name, 
                    e.last_name, 
                    e.email,
                    e.base_salary as employee_base_salary,
                    d.name as department_name
                FROM rewards r
                JOIN employees e ON r.employee_id = e.id
                JOIN departments d ON e.department_id = d.id
                WHERE DATE_FORMAT(r.period, '%Y-%m') = DATE_FORMAT(?, '%Y-%m')";
        
        $params = [$period];
        
        if ($periodType) {
            $sql .= " AND r.period_type = ?";
            $params[] = $periodType;
        }
        
        if ($departmentId) {
            $sql .= " AND e.department_id = ?";
            $params[] = $departmentId;
        }
        
        $sql .= " ORDER BY r.total_amount DESC";
        
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Получить статистику по вознаграждениям
     */
    public function getStatistics($period, $departmentId = null, $periodType = null) {
        $sql = "SELECT 
                    COUNT(*) as total_employees,
                    SUM(total_amount) as total_rewards,
                    AVG(total_amount) as avg_reward,
                    MAX(total_amount) as max_reward,
                    MIN(total_amount) as min_reward,
                    SUM(bonus_amount) as total_bonuses,
                    AVG(bonus_amount) as avg_bonus,
                    AVG(kpi_total) as avg_kpi
                FROM rewards r
                JOIN employees e ON r.employee_id = e.id
                WHERE r.period = ?";
        
        $params = [$period];
        
        if ($periodType) {
            $sql .= " AND r.period_type = ?";
            $params[] = $periodType;
        }
        
        if ($departmentId) {
            $sql .= " AND e.department_id = ?";
            $params[] = $departmentId;
        }
        
        return $this->db->fetchOne($sql, $params);
    }

    /**
     * Получить топ сотрудников по вознаграждениям
     */
    public function getTopRewards($period, $limit = 10, $departmentId = null, $periodType = null) {
        $sql = "SELECT r.*, 
                    e.first_name, 
                    e.last_name,
                    e.base_salary as employee_base_salary,
                    d.name as department_name
                FROM rewards r
                JOIN employees e ON r.employee_id = e.id
                JOIN departments d ON e.department_id = d.id
                WHERE r.period = ?";
        
        $params = [$period];
        
        if ($periodType) {
            $sql .= " AND r.period_type = ?";
            $params[] = $periodType;
        }
        
        if ($departmentId) {
            $sql .= " AND e.department_id = ?";
            $params[] = $departmentId;
        }
        
        $sql .= " ORDER BY r.total_amount DESC LIMIT ?";
        $params[] = $limit;
        
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Удалить вознаграждение
     */
    public function delete($rewardId) {
        return $this->db->delete("DELETE FROM rewards WHERE id = ?", [$rewardId]);
    }

    /**
     * Экспортировать данные по вознаграждениям (для Excel/CSV)
     */
    public function exportData($period, $departmentId = null, $periodType = null) {
        return $this->getAllRewards($period, $departmentId, $periodType);
    }

    /**
     * Получить список доступных периодов с вознаграждениями
     */
    public function getAvailablePeriods($employeeId = null, $periodType = null) {
        $sql = "SELECT DISTINCT period, period_type FROM rewards WHERE 1=1";
        $params = [];
        
        if ($employeeId) {
            $sql .= " AND employee_id = ?";
            $params[] = $employeeId;
        }
        
        if ($periodType) {
            $sql .= " AND period_type = ?";
            $params[] = $periodType;
        }
        
        $sql .= " ORDER BY period DESC";
        
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Получить базовую зарплату менеджера
     */
    private function getManagerBaseSalary($managerId) {
        $manager = $this->db->fetchOne(
            "SELECT base_salary FROM managers WHERE id = ?",
            [$managerId]
        );
        return $manager ? floatval($manager['base_salary']) : 0;
    }

    /**
     * Рассчитать и сохранить вознаграждение менеджера
     * Новая формула: Премия = Базовая зарплата × (Средний KPI отдела / 100)
     */
    public function calculateAndSaveManagerReward($managerId, $period, $periodType = null) {
        // Получаем базовую зарплату менеджера
        $baseSalary = $this->getManagerBaseSalary($managerId);
        
        if ($baseSalary <= 0) {
            throw new Exception("Базовая зарплата менеджера не установлена");
        }

        // Определяем тип периода, если не указан
        if (!$periodType) {
            $periodType = $this->determinePeriodType($period);
        }

        // Получаем отдел менеджера
        $manager = $this->db->fetchOne(
            "SELECT department_id FROM managers WHERE id = ?",
            [$managerId]
        );
        
        if (!$manager) {
            throw new Exception("Менеджер не найден");
        }
        
        $departmentId = $manager['department_id'];

        // Получаем количество сотрудников в отделе
        $employeesCount = $this->db->fetchOne(
            "SELECT COUNT(*) as count FROM employees WHERE department_id = ?",
            [$departmentId]
        )['count'];

        // Получаем средний KPI отдела за период
        $avgDepartmentKpi = $this->kpi->getDepartmentAverageKPI($departmentId, $period, $periodType);

        // Рассчитываем премию менеджера: Базовая зарплата × (Средний KPI / 100)
        $bonusAmount = $baseSalary * ($avgDepartmentKpi / 100);

        // Общая сумма = базовая зарплата + премия
        $totalAmount = $baseSalary + $bonusAmount;

        // Проверяем, есть ли уже запись
        $existing = $this->db->fetchOne(
            "SELECT id FROM manager_rewards 
             WHERE manager_id = ? AND period = ? AND period_type = ?",
            [$managerId, $period, $periodType]
        );

        if ($existing) {
            // Обновляем
            $sql = "UPDATE manager_rewards SET 
                    base_salary = ?, 
                    department_id = ?,
                    employees_count = ?, 
                    avg_department_kpi = ?, 
                    bonus_amount = ?, 
                    total_amount = ? 
                    WHERE id = ?";
            $this->db->update($sql, [
                $baseSalary, 
                $departmentId,
                $employeesCount, 
                $avgDepartmentKpi, 
                $bonusAmount, 
                $totalAmount, 
                $existing['id']
            ]);
            return $existing['id'];
        } else {
            // Создаём новую
            $sql = "INSERT INTO manager_rewards 
                    (manager_id, period, period_type, base_salary, department_id, 
                     employees_count, avg_department_kpi, 
                     bonus_amount, total_amount) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            return $this->db->insert($sql, [
                $managerId, 
                $period, 
                $periodType, 
                $baseSalary, 
                $departmentId,
                $employeesCount, 
                $avgDepartmentKpi, 
                $bonusAmount, 
                $totalAmount
            ]);
        }
    }

    /**
     * Рассчитать вознаграждения для всех менеджеров
     */
    public function calculateForAllManagers($period, $periodType = null) {
        $managers = $this->db->fetchAll(
            "SELECT id FROM managers WHERE position = 'Manager'"
        );

        $results = [];
        foreach ($managers as $manager) {
            try {
                $results[] = $this->calculateAndSaveManagerReward($manager['id'], $period, $periodType);
            } catch (Exception $e) {
                // Продолжаем расчёт для остальных менеджеров
                continue;
            }
        }

        return $results;
    }

    /**
     * Получить вознаграждение менеджера за период
     */
    public function getManagerReward($managerId, $period, $periodType = null) {
        $sql = "SELECT mr.*, 
                    m.first_name, 
                    m.last_name, 
                    m.email,
                    m.base_salary as manager_base_salary,
                    d.name as department_name
             FROM manager_rewards mr
             JOIN managers m ON mr.manager_id = m.id
             JOIN departments d ON mr.department_id = d.id
             WHERE mr.manager_id = ? AND mr.period = ?";
        
        $params = [$managerId, $period];
        
        if ($periodType) {
            $sql .= " AND mr.period_type = ?";
            $params[] = $periodType;
        }
        
        return $this->db->fetchOne($sql, $params);
    }

    /**
     * Получить историю вознаграждений менеджера
     */
    public function getManagerHistory($managerId, $limit = 12, $periodType = null) {
        $sql = "SELECT mr.*, d.name as department_name 
                FROM manager_rewards mr
                JOIN departments d ON mr.department_id = d.id
                WHERE mr.manager_id = ?";
        
        $params = [$managerId];
        
        if ($periodType) {
            $sql .= " AND mr.period_type = ?";
            $params[] = $periodType;
        }
        
        $sql .= " ORDER BY mr.period DESC LIMIT ?";
        $params[] = $limit;
        
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Получить все вознаграждения менеджеров за период
     */
    public function getAllManagerRewards($period, $periodType = null) {
        $sql = "SELECT mr.*, 
                    m.first_name, 
                    m.last_name, 
                    m.email,
                    m.base_salary as manager_base_salary,
                    d.name as department_name
                FROM manager_rewards mr
                JOIN managers m ON mr.manager_id = m.id
                JOIN departments d ON mr.department_id = d.id
                WHERE mr.period = ?";
        
        $params = [$period];
        
        if ($periodType) {
            $sql .= " AND mr.period_type = ?";
            $params[] = $periodType;
        }
        
        $sql .= " ORDER BY mr.total_amount DESC";
        
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Получить статистику по вознаграждениям менеджеров
     */
    public function getManagerStatistics($period, $periodType = null) {
        $sql = "SELECT 
                    COUNT(*) as total_managers,
                    SUM(total_amount) as total_rewards,
                    AVG(total_amount) as avg_reward,
                    MAX(total_amount) as max_reward,
                    MIN(total_amount) as min_reward,
                    SUM(bonus_amount) as total_bonuses,
                    AVG(bonus_amount) as avg_bonus,
                    AVG(avg_department_kpi) as avg_kpi
                FROM manager_rewards
                WHERE period = ?";
        
        $params = [$period];
        
        if ($periodType) {
            $sql .= " AND period_type = ?";
            $params[] = $periodType;
        }
        
        return $this->db->fetchOne($sql, $params);
    }

    /**
     * Получить все уникальные периоды из hire_date сотрудников до текущей даты
     */
    private function getAllPeriods() {
        // Получаем самую раннюю дату найма
        $earliestHire = $this->db->fetchOne(
            "SELECT MIN(hire_date) as earliest FROM (
                SELECT hire_date FROM employees
                UNION
                SELECT hire_date FROM managers
            ) as all_hires"
        );
        
        if (!$earliestHire || !$earliestHire['earliest']) {
            return [];
        }
        
        $periods = [];
        $startDate = new DateTime($earliestHire['earliest']);
        $startDate->modify('first day of this month');
        $endDate = new DateTime();
        $endDate->modify('first day of this month');
        
        while ($startDate <= $endDate) {
            $periods[] = $startDate->format('Y-m-01');
            $startDate->modify('+1 month');
        }
        
        return $periods;
    }

    /**
     * Рассчитать вознаграждения для всех сотрудников за все периоды
     */
    public function calculateForAllEmployeesAllTime($periodType = 'monthly') {
        $periods = $this->getAllPeriods();
        $results = [];
        
        foreach ($periods as $period) {
            try {
                $periodResults = $this->calculateForAll($period, $periodType);
                $results[$period] = count($periodResults);
            } catch (Exception $e) {
                $results[$period] = 0;
                continue;
            }
        }
        
        return $results;
    }

    /**
     * Рассчитать вознаграждения для всех менеджеров за все периоды
     */
    public function calculateForAllManagersAllTime($periodType = 'monthly') {
        $periods = $this->getAllPeriods();
        $results = [];
        
        foreach ($periods as $period) {
            try {
                $periodResults = $this->calculateForAllManagers($period, $periodType);
                $results[$period] = count($periodResults);
            } catch (Exception $e) {
                $results[$period] = 0;
                continue;
            }
        }
        
        return $results;
    }

    /**
     * Рассчитать вознаграждения для всех (сотрудники + менеджеры) за все периоды
     */
    public function calculateForEveryoneAllTime($periodType = 'monthly') {
        $periods = $this->getAllPeriods();
        $results = [
            'periods' => [],
            'total_periods' => count($periods),
            'employees_calculated' => 0,
            'managers_calculated' => 0
        ];
        
        foreach ($periods as $period) {
            $periodData = [
                'period' => $period,
                'employees' => 0,
                'managers' => 0
            ];
            
            // Расчёт для сотрудников
            try {
                $empResults = $this->calculateForAll($period, $periodType);
                $periodData['employees'] = count($empResults);
                $results['employees_calculated'] += count($empResults);
            } catch (Exception $e) {
                // Продолжаем
            }
            
            // Расчёт для менеджеров
            try {
                $mgrResults = $this->calculateForAllManagers($period, $periodType);
                $periodData['managers'] = count($mgrResults);
                $results['managers_calculated'] += count($mgrResults);
            } catch (Exception $e) {
                // Продолжаем
            }
            
            $results['periods'][] = $periodData;
        }
        
        return $results;
    }
}

