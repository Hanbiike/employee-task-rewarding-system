<?php
/**
 * Скрипт инициализации базы данных с тестовыми данными
 * Запустите этот файл один раз для создания начальных данных
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/User.php';

$db = Database::getInstance();

try {
    echo "<h2>Инициализация базы данных...</h2>";
    
    // 1. Создаём отделы
    echo "<p>Создание отделов...</p>";
    $departments = [
        'IT отдел',
        'Отдел продаж'
    ];
    
    $departmentIds = [];
    foreach ($departments as $dept) {
        $existing = $db->fetchOne("SELECT id FROM departments WHERE name = ?", [$dept]);
        if (!$existing) {
            $id = $db->insert("INSERT INTO departments (name) VALUES (?)", [$dept]);
            $departmentIds[$dept] = $id;
            echo "✓ Создан отдел: $dept (ID: $id)<br>";
        } else {
            $departmentIds[$dept] = $existing['id'];
            echo "- Отдел уже существует: $dept (ID: {$existing['id']})<br>";
        }
    }
    
    // 2. Создаём CEO
    echo "<p>Создание CEO...</p>";
    $ceoEmail = 'ceo@aea-system.com';
    $existingCEO = $db->fetchOne("SELECT id FROM managers WHERE email = ?", [$ceoEmail]);
    
    if (!$existingCEO) {
        $ceoPassword = password_hash('ceo123', HASH_ALGO, ['cost' => HASH_COST]);
        $ceoId = $db->insert(
            "INSERT INTO managers (first_name, last_name, email, password, phone_number, department_id, position, hire_date, base_salary) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            ['Асылбек', 'Токтомушев', $ceoEmail, $ceoPassword, '+79001234567', $departmentIds['IT отдел'], 'CEO', '2020-01-01', 300000.00]
        );
        echo "✓ Создан CEO: Асылбек Токтомушев (Email: $ceoEmail, Password: ceo123)<br>";
    } else {
        $ceoId = $existingCEO['id'];
        echo "- CEO уже существует (Email: $ceoEmail)<br>";
    }
    
    // 3. Создаём менеджеров для каждого отдела
    echo "<p>Создание менеджеров...</p>";
    $managers = [
        ['Айгуль', 'Бакиева', 'manager1@aea-system.com', '+79001234568', 'IT отдел', 150000.00],
        ['Нурбек', 'Жумабаев', 'manager2@aea-system.com', '+79001234569', 'Отдел продаж', 140000.00]
    ];
    
    $managerIds = [];
    foreach ($managers as $mgr) {
        $existing = $db->fetchOne("SELECT id FROM managers WHERE email = ?", [$mgr[2]]);
        if (!$existing) {
            $password = password_hash('manager123', HASH_ALGO, ['cost' => HASH_COST]);
            $id = $db->insert(
                "INSERT INTO managers (first_name, last_name, email, password, phone_number, department_id, position, hire_date, base_salary) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$mgr[0], $mgr[1], $mgr[2], $password, $mgr[3], $departmentIds[$mgr[4]], 'Manager', '2020-06-01', $mgr[5]]
            );
            $managerIds[$mgr[2]] = $id;
            echo "✓ Создан менеджер: {$mgr[0]} {$mgr[1]} (Email: {$mgr[2]}, Password: manager123)<br>";
        } else {
            $managerIds[$mgr[2]] = $existing['id'];
            echo "- Менеджер уже существует: {$mgr[2]}<br>";
        }
    }
    
    // 4. Создаём сотрудников (по 2 на каждого менеджера)
    echo "<p>Создание сотрудников...</p>";
    $employees = [
        ['Бекболот', 'Тургунбаев', 'employee1@aea-system.com', '+79001234572', 'IT отдел', 'manager1@aea-system.com', 80000.00],
        ['Гулнара', 'Маматова', 'employee2@aea-system.com', '+79001234573', 'IT отдел', 'manager1@aea-system.com', 75000.00],
        ['Эрлан', 'Касымов', 'employee3@aea-system.com', '+79001234574', 'Отдел продаж', 'manager2@aea-system.com', 70000.00],
        ['Динара', 'Осмонова', 'employee4@aea-system.com', '+79001234575', 'Отдел продаж', 'manager2@aea-system.com', 72000.00]
    ];
    
    $employeeIds = [];
    foreach ($employees as $emp) {
        $existing = $db->fetchOne("SELECT id FROM employees WHERE email = ?", [$emp[2]]);
        if (!$existing) {
            $password = password_hash('employee123', HASH_ALGO, ['cost' => HASH_COST]);
            $id = $db->insert(
                "INSERT INTO employees (first_name, last_name, email, password, phone_number, department_id, hire_date, base_salary, manager_id) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$emp[0], $emp[1], $emp[2], $password, $emp[3], $departmentIds[$emp[4]], '2021-01-15', $emp[6], $managerIds[$emp[5]]]
            );
            $employeeIds[$emp[2]] = $id;
            echo "✓ Создан сотрудник: {$emp[0]} {$emp[1]} (Email: {$emp[2]}, Password: employee123)<br>";
        } else {
            $employeeIds[$emp[2]] = $existing['id'];
            echo "- Сотрудник уже существует: {$emp[2]}<br>";
        }
    }
    
    // 5. Создаём настройки системы KPI
    echo "<p>Создание настроек системы KPI...</p>";
    $kpiSettingsExists = $db->fetchOne("SELECT id FROM kpi_settings LIMIT 1");
    if (!$kpiSettingsExists && isset($ceoId)) {
        $db->insert(
            "INSERT INTO kpi_settings (tasks_weight_percentage, manager_evaluation_percentage, manager_bonus_percentage, updated_by_manager_id) VALUES (?, ?, ?, ?)",
            [50, 50, 100.00, $ceoId]
        );
        echo "✓ Созданы настройки KPI (Задачи: 50%, Оценка менеджера: 50%, Премия менеджера: 100%)<br>";
    } elseif (!$kpiSettingsExists) {
        echo "⚠ Пропущено создание настроек KPI: CEO не найден<br>";
    } else {
        echo "- Настройки KPI уже существуют<br>";
    }
    
    // 6. Создаём веса важности задач
    echo "<p>Создание весов важности задач...</p>";
    $importanceWeights = [
        ['Low', 1],
        ['Medium', 2],
        ['High', 3],
        ['Critical', 5]
    ];
    
    foreach ($importanceWeights as $iw) {
        $existing = $db->fetchOne("SELECT id FROM task_importance_weights WHERE importance = ?", [$iw[0]]);
        if (!$existing) {
            $db->insert(
                "INSERT INTO task_importance_weights (importance, weight) VALUES (?, ?)",
                $iw
            );
            echo "✓ Создан вес важности: {$iw[0]} = {$iw[1]}<br>";
        } else {
            echo "- Вес важности уже существует: {$iw[0]}<br>";
        }
    }
    
    // 7. Создаём базовые KPI показатели (глобальные метрики для оценки менеджера)
    echo "<p>Создание KPI показателей (глобальные метрики)...</p>";
    $kpiIndicators = [
        ['Пунктуальность', 'Соблюдение сроков выполнения задач', 25, 100, '%'],
        ['Качество работы', 'Оценка качества выполненных задач', 30, 100, '%'],
        ['Коммуникация', 'Эффективность взаимодействия в команде', 25, 100, '%'],
        ['Инициативность', 'Предложения по улучшению процессов', 20, 100, '%'],
    ];
    
    $kpiIds = [];
    foreach ($kpiIndicators as $kpi) {
        $existing = $db->fetchOne("SELECT id FROM kpi WHERE name = ?", [$kpi[0]]);
        if (!$existing) {
            $id = $db->insert(
                "INSERT INTO kpi (name, description, weight, target_value, measurement_unit) VALUES (?, ?, ?, ?, ?)",
                $kpi
            );
            $kpiIds[$kpi[0]] = $id;
            echo "✓ Создан KPI: {$kpi[0]} (вес: {$kpi[2]}%)<br>";
        } else {
            $kpiIds[$kpi[0]] = $existing['id'];
            echo "- KPI уже существует: {$kpi[0]}<br>";
        }
    }
    
    // 8. Создаём 10 проектов
    echo "<p>Создание тестовых проектов...</p>";
    
    // Получаем ID менеджеров для создания проектов
    $ceo = $db->fetchOne("SELECT id FROM managers WHERE email = ?", [$ceoEmail]);
    $ceoId = $ceo ? $ceo['id'] : (isset($ceoId) ? $ceoId : null);
    
    if ($ceoId) {
        $testProjects = [
            [
                'name' => 'Разработка новой CRM системы',
                'description' => 'Создание CRM системы для автоматизации работы с клиентами',
                'departments' => ['IT отдел'],
                'status' => 'In Progress',
                'importance' => 'High',
                'deadline' => date('Y-m-d', strtotime('+3 months')),
                'manager' => 'manager1@aea-system.com'
            ],
            [
                'name' => 'Модернизация IT инфраструктуры',
                'description' => 'Обновление серверов и сетевого оборудования',
                'departments' => ['IT отдел'],
                'status' => 'In Progress',
                'importance' => 'Critical',
                'deadline' => date('Y-m-d', strtotime('+2 months')),
                'manager' => 'manager1@aea-system.com'
            ],
            [
                'name' => 'Внедрение системы безопасности',
                'description' => 'Установка и настройка комплексной системы информационной безопасности',
                'departments' => ['IT отдел'],
                'status' => 'Not Started',
                'importance' => 'High',
                'deadline' => date('Y-m-d', strtotime('+4 months')),
                'manager' => 'manager1@aea-system.com'
            ],
            [
                'name' => 'Разработка мобильного приложения',
                'description' => 'Создание мобильного приложения для iOS и Android',
                'departments' => ['IT отдел'],
                'status' => 'In Progress',
                'importance' => 'Medium',
                'deadline' => date('Y-m-d', strtotime('+5 months')),
                'manager' => 'manager1@aea_system.com'
            ],
            [
                'name' => 'Автоматизация отчётности',
                'description' => 'Внедрение системы автоматического формирования отчётов',
                'departments' => ['IT отдел'],
                'status' => 'Completed',
                'importance' => 'Medium',
                'deadline' => date('Y-m-d', strtotime('+1 month')), // Дедлайн в будущем, проект уже завершён
                'manager' => 'manager1@aea-system.com'
            ],
            [
                'name' => 'Увеличение продаж Q1 2026',
                'description' => 'Комплекс мероприятий по увеличению продаж в первом квартале',
                'departments' => ['Отдел продаж'],
                'status' => 'In Progress',
                'importance' => 'High',
                'deadline' => date('Y-m-d', strtotime('+2 months')),
                'manager' => 'manager2@aea-system.com'
            ],
            [
                'name' => 'Расширение клиентской базы',
                'description' => 'Привлечение новых корпоративных клиентов',
                'departments' => ['Отдел продаж'],
                'status' => 'In Progress',
                'importance' => 'Critical',
                'deadline' => date('Y-m-d', strtotime('+3 months')),
                'manager' => 'manager2@aea_system.com'
            ],
            [
                'name' => 'Обучение менеджеров продаж',
                'description' => 'Программа повышения квалификации для отдела продаж',
                'departments' => ['Отдел продаж'],
                'status' => 'Completed',
                'importance' => 'Medium',
                'deadline' => date('Y-m-d', strtotime('+2 weeks')), // Дедлайн в будущем, проект уже завершён
                'manager' => 'manager2@aea_system.com'
            ],
            [
                'name' => 'Запуск новой линейки продуктов',
                'description' => 'Подготовка и запуск продаж новой линейки товаров',
                'departments' => ['Отдел продаж'],
                'status' => 'Not Started',
                'importance' => 'High',
                'deadline' => date('Y-m-d', strtotime('+6 months')),
                'manager' => 'manager2@aea_system.com'
            ],
            [
                'name' => 'Оптимизация процесса продаж',
                'description' => 'Анализ и улучшение процесса работы с клиентами',
                'departments' => ['Отдел продаж'],
                'status' => 'In Progress',
                'importance' => 'Medium',
                'deadline' => date('Y-m-d', strtotime('+4 months')),
                'manager' => 'manager2@aea_system.com'
            ],
        ];
        
        $projectIds = [];
        foreach ($testProjects as $proj) {
            $existing = $db->fetchOne("SELECT id FROM projects WHERE name = ?", [$proj['name']]);
            
            if (!$existing) {
                $firstDeptId = $departmentIds[$proj['departments'][0]];
                $creatorId = isset($managerIds[$proj['manager']]) ? $managerIds[$proj['manager']] : $ceoId;
                
                $projectId = $db->insert(
                    "INSERT INTO projects (name, description, department_id, status, importance, deadline, created_by_manager_id) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [
                        $proj['name'],
                        $proj['description'],
                        $firstDeptId,
                        $proj['status'],
                        $proj['importance'],
                        $proj['deadline'],
                        $creatorId
                    ]
                );
                
                $projectIds[] = $projectId;
                
                // Назначаем департаменты через project_departments
                foreach ($proj['departments'] as $deptName) {
                    if (isset($departmentIds[$deptName])) {
                        $db->insert(
                            "INSERT INTO project_departments (project_id, department_id) VALUES (?, ?)",
                            [$projectId, $departmentIds[$deptName]]
                        );
                    }
                }
                
                // Назначаем менеджера на проект через manager_projects
                if (isset($proj['manager']) && isset($managerIds[$proj['manager']])) {
                    $assignedManagerId = $managerIds[$proj['manager']];
                    $db->insert(
                        "INSERT INTO manager_projects (manager_id, project_id) VALUES (?, ?)",
                        [$assignedManagerId, $projectId]
                    );
                }
                
                echo "✓ Создан проект: {$proj['name']}<br>";
            } else {
                $projectIds[] = $existing['id'];
                
                // Проверяем и добавляем связь менеджера с проектом, если её нет
                if (isset($proj['manager']) && isset($managerIds[$proj['manager']])) {
                    $assignedManagerId = $managerIds[$proj['manager']];
                    $linkExists = $db->fetchOne(
                        "SELECT * FROM manager_projects WHERE manager_id = ? AND project_id = ?",
                        [$assignedManagerId, $existing['id']]
                    );
                    
                    if (!$linkExists) {
                        $db->insert(
                            "INSERT INTO manager_projects (manager_id, project_id) VALUES (?, ?)",
                            [$assignedManagerId, $existing['id']]
                        );
                        echo "- Проект уже существует, добавлена связь с менеджером: {$proj['name']}<br>";
                    } else {
                        echo "- Проект уже существует: {$proj['name']}<br>";
                    }
                } else {
                    echo "- Проект уже существует: {$proj['name']}<br>";
                }
            }
        }
    } else {
        echo "⚠ Пропущено создание проектов: CEO не найден<br>";
    }
    
    // 9. Создаём 100 задач
    echo "<p>Создание задач (100 шт.)...</p>";
    
    if (!empty($projectIds)) {
        $taskTemplates = [
            'Анализ требований', 'Разработка документации', 'Проектирование архитектуры',
            'Написание кода', 'Тестирование функционала', 'Исправление ошибок',
            'Подготовка презентации', 'Согласование с заказчиком', 'Внедрение изменений',
            'Проведение встречи', 'Написание отчёта', 'Обновление базы данных',
            'Настройка окружения', 'Оптимизация производительности', 'Код-ревью',
            'Создание бэкапа', 'Подготовка к релизу', 'Развёртывание на сервере',
            'Мониторинг системы', 'Обучение пользователей'
        ];
        
        $statuses = ['Completed', 'In Progress', 'Not Started', 'On Moderation'];
        $importances = ['Low', 'Medium', 'High', 'Critical'];
        
        $allEmployees = $db->fetchAll("SELECT id FROM employees ORDER BY id");
        
        $taskCount = 0;
        for ($i = 0; $i < 100; $i++) {
            $projectId = $projectIds[array_rand($projectIds)];
            $project = $db->fetchOne("SELECT created_by_manager_id FROM projects WHERE id = ?", [$projectId]);
            
            $taskName = $taskTemplates[$i % count($taskTemplates)] . " #" . ($i + 1);
            $status = $statuses[array_rand($statuses)];
            $importance = $importances[array_rand($importances)];
            
            // Дедлайн всегда в будущем (от 1 до 90 дней)
            $daysOffset = rand(1, 90);
            $deadline = date('Y-m-d', strtotime("+{$daysOffset} days"));
            
            // Для завершённых задач end_date в прошлом, но после created_at (сегодня)
            // Устанавливаем end_date как сегодня или вчера для завершённых задач
            $endDate = ($status === 'Completed') ? date('Y-m-d') : null;
            
            $existing = $db->fetchOne("SELECT id FROM tasks WHERE name = ?", [$taskName]);
            
            if (!$existing) {
                $taskId = $db->insert(
                    "INSERT INTO tasks (name, description, project_id, created_by_manager_id, status, importance, deadline, end_date) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $taskName,
                        "Описание задачи: " . $taskName,
                        $projectId,
                        $project['created_by_manager_id'],
                        $status,
                        $importance,
                        $deadline,
                        $endDate
                    ]
                );
                
                // Назначаем задачу случайному сотруднику
                if (!empty($allEmployees)) {
                    $randomEmployee = $allEmployees[array_rand($allEmployees)];
                    $db->insert(
                        "INSERT INTO employee_tasks (employee_id, task_id) VALUES (?, ?)",
                        [$randomEmployee['id'], $taskId]
                    );
                }
                
                $taskCount++;
            }
        }
        
        echo "✓ Создано задач: {$taskCount}<br>";
    }
    
    // 10. Создаём KPI значения для сотрудников (глобальные метрики от менеджера)
    echo "<p>Создание KPI значений для сотрудников...</p>";
    
    $allEmployeesForKPI = $db->fetchAll("SELECT id FROM employees");
    $allKPIs = $db->fetchAll("SELECT id, target_value FROM kpi");
    
    $kpiCount = 0;
    foreach ($allEmployeesForKPI as $emp) {
        // Создаём значения KPI за последние 3 месяца
        for ($month = 0; $month < 3; $month++) {
            $period = date('Y-m-01', strtotime("-{$month} months"));
            
            foreach ($allKPIs as $kpi) {
                // Генерируем случайное значение (от 70% до 110% от целевого)
                $value = $kpi['target_value'] * (rand(70, 110) / 100);
                
                $existing = $db->fetchOne(
                    "SELECT id FROM kpi_values WHERE employee_id = ? AND kpi_id = ? AND period = ?",
                    [$emp['id'], $kpi['id'], $period]
                );
                
                if (!$existing) {
                    $db->insert(
                        "INSERT INTO kpi_values (employee_id, kpi_id, value, period) VALUES (?, ?, ?, ?)",
                        [$emp['id'], $kpi['id'], $value, $period]
                    );
                    $kpiCount++;
                }
            }
        }
    }
    
    echo "✓ Создано KPI значений: {$kpiCount}<br>";
    
    // 11. Рассчитываем и создаём вознаграждения (месячные, квартальные, годовые)
    echo "<p>Расчёт вознаграждений...</p>";
    
    $rewardCount = 0;
    $periodTypes = ['monthly', 'quarterly', 'yearly'];
    
    foreach ($allEmployeesForKPI as $emp) {
        // Получаем базовую зарплату сотрудника
        $employee = $db->fetchOne("SELECT base_salary FROM employees WHERE id = ?", [$emp['id']]);
        $baseSalary = floatval($employee['base_salary']);
        
        // Создаём вознаграждения за последние 3 месяца
        for ($month = 0; $month < 3; $month++) {
            $period = date('Y-m-01', strtotime("-{$month} months"));
            $currentMonth = (int)date('m', strtotime($period));
            
            // Определяем тип периода
            $periodType = 'monthly'; // По умолчанию месячное
            
            // Квартальные - март (3), июнь (6), сентябрь (9), декабрь (12)
            if (in_array($currentMonth, [3, 6, 9, 12])) {
                $periodType = 'quarterly';
            }
            
            // Годовое - только декабрь (12)
            if ($currentMonth == 12) {
                $periodType = 'yearly';
            }
            
            // Получаем все KPI значения за период
            $kpiValues = $db->fetchAll(
                "SELECT kv.value, k.weight, k.target_value 
                 FROM kpi_values kv 
                 JOIN kpi k ON kv.kpi_id = k.id 
                 WHERE kv.employee_id = ? AND kv.period = ?",
                [$emp['id'], $period]
            );
            
            if (!empty($kpiValues)) {
                $totalKPI = 0;
                $totalWeight = 0;
                
                foreach ($kpiValues as $kv) {
                    $achievement = min(($kv['value'] / $kv['target_value']) * 100, 150);
                    $totalKPI += $achievement * $kv['weight'];
                    $totalWeight += $kv['weight'];
                }
                
                if ($totalWeight > 0) {
                    // Нормализуем KPI если сумма весов не 100
                    if ($totalWeight != 100) {
                        $kpiTotal = ($totalKPI / $totalWeight) * 100;
                    } else {
                        $kpiTotal = $totalKPI;
                    }
                    
                    // Множители для разных типов периодов
                    $multipliers = [
                        'monthly' => 1.0,
                        'quarterly' => 3.0,
                        'yearly' => 12.0
                    ];
                    
                    $multiplier = $multipliers[$periodType];
                    
                    // Рассчитываем бонус: BaseSalary * (KPI/100) * Multiplier
                    $bonusAmount = $baseSalary * ($kpiTotal / 100) * $multiplier;
                    
                    // Общая сумма = базовая зарплата + бонус
                    $totalAmount = $baseSalary + $bonusAmount;
                    
                    $existing = $db->fetchOne(
                        "SELECT id FROM rewards WHERE employee_id = ? AND period = ? AND period_type = ?",
                        [$emp['id'], $period, $periodType]
                    );
                    
                    if (!$existing) {
                        $db->insert(
                            "INSERT INTO rewards (employee_id, period, period_type, base_salary, kpi_total, bonus_amount, total_amount) 
                             VALUES (?, ?, ?, ?, ?, ?, ?)",
                            [$emp['id'], $period, $periodType, $baseSalary, $kpiTotal, $bonusAmount, $totalAmount]
                        );
                        $rewardCount++;
                    }
                }
            }
        }
    }
    
    echo "✓ Создано вознаграждений: {$rewardCount}<br>";
    
    // 11. Создаём вознаграждения для менеджеров
    echo "<p>Создание вознаграждений для менеджеров...</p>";
    $managerRewardCount = 0;
    
    // Получаем всех менеджеров (кроме CEO)
    $managers = $db->fetchAll("SELECT id, department_id, base_salary FROM managers WHERE position = 'Manager'");
    
    foreach ($managers as $manager) {
        // Создаём вознаграждения за последние 3 месяца
        for ($month = 0; $month < 3; $month++) {
            $period = date('Y-m-01', strtotime("-{$month} months"));
            $currentMonth = (int)date('m', strtotime($period));
            
            // Определяем тип периода
            $periodType = 'monthly'; // По умолчанию месячное
            
            // Квартальные - март (3), июнь (6), сентябрь (9), декабрь (12)
            if (in_array($currentMonth, [3, 6, 9, 12])) {
                $periodType = 'quarterly';
            }
            
            // Годовое - только декабрь (12)
            if ($currentMonth == 12) {
                $periodType = 'yearly';
            }
            
            // Подсчитываем количество сотрудников в отделе
            $employeesCount = $db->fetchOne(
                "SELECT COUNT(*) as count FROM employees WHERE department_id = ?",
                [$manager['department_id']]
            )['count'];
            
            $baseSalary = $manager['base_salary'];
            
            // Рассчитываем средний KPI отдела за период
            // Получаем KPI всех сотрудников отдела за период
            $employees = $db->fetchAll(
                "SELECT id FROM employees WHERE department_id = ?",
                [$manager['department_id']]
            );
            
            $totalKpi = 0;
            $kpiCount = 0;
            
            foreach ($employees as $emp) {
                // Получаем KPI сотрудника за период (из rewards)
                $empReward = $db->fetchOne(
                    "SELECT kpi_total FROM rewards WHERE employee_id = ? AND period = ? AND period_type = ?",
                    [$emp['id'], $period, $periodType]
                );
                
                if ($empReward && $empReward['kpi_total'] > 0) {
                    $totalKpi += $empReward['kpi_total'];
                    $kpiCount++;
                }
            }
            
            $avgDepartmentKpi = $kpiCount > 0 ? round($totalKpi / $kpiCount, 2) : 0;
            
            // Рассчитываем премию менеджера: base_salary * (avg_department_kpi / 100)
            $bonusAmount = $baseSalary * ($avgDepartmentKpi / 100);
            
            // Общая сумма
            $totalAmount = $baseSalary + $bonusAmount;
            
            // Проверяем, есть ли уже запись
            $existing = $db->fetchOne(
                "SELECT id FROM manager_rewards 
                 WHERE manager_id = ? AND period = ? AND period_type = ?",
                [$manager['id'], $period, $periodType]
            );
            
            if (!$existing) {
                $db->insert(
                    "INSERT INTO manager_rewards 
                     (manager_id, period, period_type, base_salary, department_id, 
                      employees_count, avg_department_kpi, 
                      bonus_amount, total_amount) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $manager['id'], 
                        $period, 
                        $periodType, 
                        $baseSalary,
                        $manager['department_id'], 
                        $employeesCount, 
                        $avgDepartmentKpi, 
                        $bonusAmount, 
                        $totalAmount
                    ]
                );
                $managerRewardCount++;
            }
        }
    }
    
    echo "✓ Создано вознаграждений для менеджеров: {$managerRewardCount}<br>";
    
    echo "<h3 style='color: green;'>✓ Инициализация завершена успешно!</h3>";
    echo "<h4>Учётные данные для входа:</h4>";
    echo "<ul>";
    echo "<li><strong>CEO:</strong> Email: ceo@aea-system.com, Password: ceo123</li>";
    echo "<li><strong>Manager:</strong> Email: manager1@aea-system.com, Password: manager123</li>";
    echo "<li><strong>Employee:</strong> Email: employee1@aea-system.com, Password: employee123</li>";
    echo "</ul>";
    echo "<p><a href='index.php'>Перейти к авторизации →</a></p>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>Ошибка: " . $e->getMessage() . "</h3>";
}
