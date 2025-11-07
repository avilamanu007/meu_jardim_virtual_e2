<?php

class CareModel {
    private $db;
    private $careTypeConverter;
    private $careDateCalculator;
    private $careFormatter;
    private $careIconMapper;

    public function __construct($db) {
        $this->db = $db;
        $this->careTypeConverter = new CareTypeConverter();
        $this->careDateCalculator = new CareDateCalculator();
        $this->careFormatter = new CareFormatter();
        $this->careIconMapper = new CareIconMapper();
    }

    public function createCare($plantId, $careType, $careDate, $observations, $nextMaintenanceDate) {
        try {
            $careLogger = new CareLogger();
            $careLogger->logCreateCareStart($plantId, $careType, $careDate, $observations, $nextMaintenanceDate);
            
            $convertedCareType = $this->careTypeConverter->convert($careType);
            $careLogger->logTypeConversion($careType, $convertedCareType);
            
            $careInserter = new CareInserter($this->db);
            $success = $careInserter->insert(
                $plantId, 
                $convertedCareType, 
                $careDate, 
                $observations, 
                $nextMaintenanceDate
            );
            
            $careLogger->logCreateCareEnd($success);
            return $success;
            
        } catch (PDOException $e) {
            error_log("Erro ao registrar cuidado: " . $e->getMessage());
            return false;
        }
    }

    public function getCaresByPlantId($plantId) {
        try {
            $careFinder = new CareFinder($this->db);
            return $careFinder->findByPlantId($plantId);
        } catch (PDOException $e) {
            error_log("Erro ao buscar cuidados: " . $e->getMessage());
            return [];
        }
    }

    public function getCaresByUserId($userId) {
        try {
            $careFinder = new CareFinder($this->db);
            return $careFinder->findByUserId($userId);
        } catch (PDOException $e) {
            error_log("Erro ao buscar cuidados do usuário: " . $e->getMessage());
            return [];
        }
    }

    public function completeCare($careId, $userId, $observations = '') {
        try {
            $careCompleter = new CareCompleter($this->db, $this->careDateCalculator);
            return $careCompleter->complete($careId, $userId, $observations);
        } catch (PDOException $e) {
            error_log("CareModel - Erro ao dar baixa no cuidado: " . $e->getMessage());
            return false;
        }
    }

    public function getPendingCaresForUser($userId) {
        try {
            $pendingCareFinder = new PendingCareFinder($this->db, $this->careIconMapper);
            return $pendingCareFinder->findForUser($userId);
        } catch (PDOException $e) {
            error_log("CareModel - Erro ao buscar cuidados pendentes: " . $e->getMessage());
            return [];
        }
    }

    public function getPendingCareCount($userId): int {
        try {
            $careCounter = new CareCounter($this->db);
            return $careCounter->countPending($userId);
        } catch (PDOException $e) {
            error_log("CareModel - Erro ao contar cuidados pendentes: " . $e->getMessage());
            return 0;
        }
    }

    public function getPendingCaresWithDetails($userId): array {
        try {
            $pendingCareFinder = new PendingCareFinder($this->db, $this->careIconMapper);
            return $pendingCareFinder->findWithDetails($userId);
        } catch (PDOException $e) {
            error_log("CareModel - Erro ao buscar cuidados pendentes: " . $e->getMessage());
            return [];
        }
    }

    public function getRecentActivities($userId, $limit = 10): array {
        try {
            $activityFinder = new ActivityFinder($this->db, $this->careFormatter, $this->careIconMapper);
            return $activityFinder->findRecent($userId, $limit);
        } catch (PDOException $e) {
            error_log("CareModel - Erro ao buscar atividades recentes: " . $e->getMessage());
            return [];
        }
    }

    public function getCareStats($userId): array {
        try {
            $statsCalculator = new CareStatsCalculator($this->db);
            return $statsCalculator->calculate($userId);
        } catch (PDOException $e) {
            error_log("CareModel - Erro ao buscar estatísticas: " . $e->getMessage());
            return [
                'total_cares' => 0,
                'plants_cared' => 0,
                'cares_last_week' => 0,
                'avg_cares_per_day' => 0,
                'type_distribution' => []
            ];
        }
    }

    public function getUpcomingCares($userId, $daysAhead = 7): array {
        try {
            $upcomingCareFinder = new UpcomingCareFinder($this->db, $this->careIconMapper);
            return $upcomingCareFinder->find($userId, $daysAhead);
        } catch (PDOException $e) {
            error_log("CareModel - Erro ao buscar próximos cuidados: " . $e->getMessage());
            return [];
        }
    }

    public function getCareNotifications($userId): array {
        try {
            $notificationGenerator = new CareNotificationGenerator($this, $this->careIconMapper);
            return $notificationGenerator->generate($userId);
        } catch (PDOException $e) {
            error_log("CareModel - Erro ao gerar notificações: " . $e->getMessage());
            return [];
        }
    }

    public function getCareById($careId) {
        try {
            $careFinder = new CareFinder($this->db);
            return $careFinder->findById($careId);
        } catch (PDOException $e) {
            error_log("CareModel - Erro ao buscar cuidado: " . $e->getMessage());
            return null;
        }
    }

    public function updateCare($careId, $careData) {
        try {
            $careUpdater = new CareUpdater($this->db);
            return $careUpdater->update($careId, $careData);
        } catch (PDOException $e) {
            error_log("CareModel - Erro ao atualizar cuidado: " . $e->getMessage());
            return false;
        }
    }

    public function deleteCare($careId) {
        try {
            $careDeleter = new CareDeleter($this->db);
            return $careDeleter->delete($careId);
        } catch (PDOException $e) {
            error_log("CareModel - Erro ao excluir cuidado: " . $e->getMessage());
            return false;
        }
    }
}

// CLASSES AUXILIARES - IMPLEMENTAÇÃO COMPLETA

class CareTypeConverter {
    private $conversionMap;

    public function __construct() {
        $this->conversionMap = [
            'rega' => 'Regar',
            'adubacao' => 'Adubar', 
            'poda' => 'Podar',
            'transplante' => 'Mudar Vaso',
            'tratamento' => 'Limpar Folhas',
            'limpeza' => 'Limpar Folhas'
        ];
    }

    public function convert($controllerType) {
        error_log("CareTypeConverter - Convertendo tipo: '$controllerType'");
        $converted = $this->conversionMap[strtolower($controllerType)] ?? 'Regar';
        error_log("CareTypeConverter - Resultado da conversão: '$converted'");
        return $converted;
    }
}

class CareDateCalculator {
    private $intervals;

    public function __construct() {
        $this->intervals = [
            'Regar' => '+3 days',
            'Adubar' => '+30 days',
            'Podar' => '+90 days',
            'Mudar Vaso' => '+365 days',
            'Limpar Folhas' => '+7 days'
        ];
    }

    public function calculateNextDate($careType, $baseDate) {
        $interval = $this->intervals[$careType] ?? '+7 days';
        return date('Y-m-d', strtotime($baseDate . ' ' . $interval));
    }
}

class CareIconMapper {
    private $iconMap;

    public function __construct() {
        $this->iconMap = [
            'Regar' => '💧',
            'Adubar' => '🌱',
            'Podar' => '✂️',
            'Mudar Vaso' => '🪴',
            'Limpar Folhas' => '🍃'
        ];
    }

    public function getIcon($careType) {
        return $this->iconMap[$careType] ?? '🌿';
    }
}

class CareFormatter {
    public function formatActivityDescription($activity) {
        $plantName = htmlspecialchars($activity['plant_name']);
        $careType = $activity['care_type'];
        
        $descriptions = [
            'Regar' => "Regou {$plantName}",
            'Adubar' => "Adubou {$plantName}",
            'Podar' => "Podou {$plantName}",
            'Mudar Vaso' => "Mudou {$plantName} de vaso",
            'Limpar Folhas' => "Limpeza em {$plantName}"
        ];
        
        return $descriptions[$careType] ?? "Cuidado em {$plantName}";
    }

    public function formatTimeAgo($hoursAgo) {
        if ($hoursAgo < 1) {
            return 'Agora mesmo';
        } elseif ($hoursAgo < 24) {
            return "Há {$hoursAgo} hora" . ($hoursAgo > 1 ? 's' : '');
        } else {
            $days = floor($hoursAgo / 24);
            return "Há {$days} dia" . ($days > 1 ? 's' : '');
        }
    }

    public function getDaysText($days) {
        if ($days < 0) {
            return abs($days) . ' dia(s) atrasado(s)';
        } elseif ($days == 0) {
            return 'Para hoje';
        } else {
            return "Em $days dia(s)";
        }
    }
}

class CareLogger {
    public function logCreateCareStart($plantId, $careType, $careDate, $observations, $nextMaintenanceDate) {
        error_log("=== CAREMODEL - CREATE CARE ===");
        error_log("Dados recebidos:");
        error_log("  plantId: $plantId");
        error_log("  careType: $careType");
        error_log("  careDate: $careDate");
        error_log("  observations: $observations");
        error_log("  nextMaintenanceDate: $nextMaintenanceDate");
    }

    public function logTypeConversion($originalType, $convertedType) {
        error_log("Tipo convertido: $originalType -> $convertedType");
    }

    public function logCreateCareEnd($success) {
        error_log("CareModel - Executou: " . ($success ? 'SIM' : 'NÃO'));
        error_log("=== CAREMODEL - FIM CREATE CARE ===");
    }
}

class CareInserter {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function insert($plantId, $careType, $careDate, $observations, $nextMaintenanceDate) {
        $stmt = $this->db->prepare("
            INSERT INTO cares 
            (plant_id, care_type, care_date, observations, next_maintenance_date) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $success = $stmt->execute([
            $plantId, 
            $careType, 
            $careDate, 
            $observations, 
            $nextMaintenanceDate
        ]);
        
        if (!$success) {
            $errorInfo = $stmt->errorInfo();
            error_log("CareInserter - Erro SQL: " . print_r($errorInfo, true));
            
            if (isset($errorInfo[1]) && $errorInfo[1] == 1265) {
                return $this->retryWithoutConversion($plantId, $careType, $careDate, $observations, $nextMaintenanceDate);
            }
        }
        
        return $success;
    }

    private function retryWithoutConversion($plantId, $careType, $careDate, $observations, $nextMaintenanceDate) {
        error_log("CareInserter - Tentando inserir sem conversão...");
        $stmt = $this->db->prepare("
            INSERT INTO cares 
            (plant_id, care_type, care_date, observations, next_maintenance_date) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $success = $stmt->execute([
            $plantId, 
            $careType, 
            $careDate, 
            $observations, 
            $nextMaintenanceDate
        ]);
        
        error_log("CareInserter - Segunda tentativa: " . ($success ? 'SUCESSO' : 'FALHA'));
        return $success;
    }
}

class CareFinder {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function findByPlantId($plantId) {
        $stmt = $this->db->prepare("
            SELECT * FROM cares 
            WHERE plant_id = ? 
            ORDER BY care_date DESC
        ");
        $stmt->execute([$plantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByUserId($userId) {
        $stmt = $this->db->prepare("
            SELECT c.*, p.name as plant_name 
            FROM cares c 
            INNER JOIN plants p ON c.plant_id = p.id 
            WHERE p.user_id = ? 
            ORDER BY c.care_date DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById($careId) {
        $stmt = $this->db->prepare("SELECT * FROM cares WHERE id = ?");
        $stmt->execute([$careId]);
        return $stmt->fetch();
    }
}

class CareCompleter {
    private $db;
    private $dateCalculator;

    public function __construct($db, $dateCalculator) {
        $this->db = $db;
        $this->dateCalculator = $dateCalculator;
    }

    public function complete($careId, $userId, $observations = '') {
        $care = $this->findUserCare($careId, $userId);
        
        if (!$care) {
            error_log("CareCompleter - Cuidado não encontrado ou não pertence ao usuário");
            return false;
        }
        
        return $this->updateCareCompletion($careId, $care, $observations);
    }

    private function findUserCare($careId, $userId) {
        $stmt = $this->db->prepare("
            SELECT c.*, p.name as plant_name 
            FROM cares c 
            INNER JOIN plants p ON c.plant_id = p.id 
            WHERE c.id = ? AND p.user_id = ?
        ");
        $stmt->execute([$careId, $userId]);
        return $stmt->fetch();
    }

    private function updateCareCompletion($careId, $care, $observations) {
        $today = date('Y-m-d');
        $nextDate = $this->dateCalculator->calculateNextDate($care['care_type'], $today);
        
        $stmt = $this->db->prepare("
            UPDATE cares 
            SET care_date = ?, 
                observations = CONCAT(IFNULL(observations, ''), ?),
                next_maintenance_date = ?
            WHERE id = ?
        ");
        
        $observationText = $observations ? "\n\nBaixa realizada em " . date('d/m/Y') . ": " . $observations : "\n\nBaixa realizada em " . date('d/m/Y');
        
        $success = $stmt->execute([
            $today,
            $observationText,
            $nextDate,
            $careId
        ]);
        
        error_log("CareCompleter - Baixa realizada no cuidado $careId: " . ($success ? 'SUCESSO' : 'FALHA'));
        return $success;
    }
}

class PendingCareFinder {
    private $db;
    private $iconMapper;
    private $formatter;

    public function __construct($db, $iconMapper) {
        $this->db = $db;
        $this->iconMapper = $iconMapper;
        $this->formatter = new CareFormatter();
    }

    public function findForUser($userId) {
        $stmt = $this->db->prepare("
            SELECT 
                c.id as care_id,
                c.care_type,
                c.next_maintenance_date,
                c.observations,
                DATEDIFF(c.next_maintenance_date, CURDATE()) as days_overdue,
                p.id as plant_id,
                p.name as plant_name,
                p.species,
                p.location,
                CASE 
                    WHEN c.next_maintenance_date < CURDATE() THEN 'Atrasado'
                    WHEN c.next_maintenance_date = CURDATE() THEN 'Para hoje'
                    ELSE 'Próximos dias'
                END as status
            FROM cares c
            INNER JOIN plants p ON c.plant_id = p.id
            WHERE p.user_id = ? 
            AND c.next_maintenance_date IS NOT NULL
            AND c.next_maintenance_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            ORDER BY 
                CASE 
                    WHEN c.next_maintenance_date < CURDATE() THEN 1
                    WHEN c.next_maintenance_date = CURDATE() THEN 2
                    ELSE 3
                END,
                c.next_maintenance_date ASC
        ");
        $stmt->execute([$userId]);
        
        $cares = $stmt->fetchAll();
        
        foreach ($cares as &$care) {
            $this->enrichCareData($care);
        }
        
        return $cares;
    }

    public function findWithDetails($userId) {
        $stmt = $this->db->prepare("
            SELECT 
                c.id as care_id,
                c.care_type,
                c.next_maintenance_date,
                DATEDIFF(c.next_maintenance_date, CURDATE()) as days_overdue,
                p.id as plant_id,
                p.name as plant_name,
                p.location
            FROM cares c
            INNER JOIN plants p ON c.plant_id = p.id
            WHERE p.user_id = ? 
            AND c.next_maintenance_date IS NOT NULL
            AND c.next_maintenance_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
            ORDER BY c.next_maintenance_date ASC
        ");
        $stmt->execute([$userId]);
        $cares = $stmt->fetchAll();
        
        foreach ($cares as &$care) {
            $this->enrichCareWithPriority($care);
        }
        
        return $cares;
    }

    private function enrichCareData(&$care) {
        $care['icon'] = $this->iconMapper->getIcon($care['care_type']);
        $care['formatted_date'] = date('d/m/Y', strtotime($care['next_maintenance_date']));
        $care['days_text'] = $this->formatter->getDaysText($care['days_overdue']);
        
        if ($care['days_overdue'] < 0) {
            $care['priority'] = 'high';
            $care['badge_color'] = 'bg-red-100 text-red-800';
        } elseif ($care['days_overdue'] == 0) {
            $care['priority'] = 'medium';
            $care['badge_color'] = 'bg-orange-100 text-orange-800';
        } else {
            $care['priority'] = 'low';
            $care['badge_color'] = 'bg-blue-100 text-blue-800';
        }
    }

    private function enrichCareWithPriority(&$care) {
        if ($care['days_overdue'] < 0) {
            $care['priority'] = 'high';
            $care['status'] = 'Atrasado';
        } elseif ($care['days_overdue'] == 0) {
            $care['priority'] = 'medium';
            $care['status'] = 'Para hoje';
        } else {
            $care['priority'] = 'low';
            $care['status'] = 'Próximos dias';
        }
        
        $care['icon'] = $this->iconMapper->getIcon($care['care_type']);
    }
}

class CareCounter {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function countPending($userId): int {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM cares c
            INNER JOIN plants p ON c.plant_id = p.id
            WHERE p.user_id = ? 
            AND c.next_maintenance_date IS NOT NULL
            AND c.next_maintenance_date <= CURDATE()
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        
        return $result['count'] ?? 0;
    }
}

class ActivityFinder {
    private $db;
    private $formatter;
    private $iconMapper;

    public function __construct($db, $formatter, $iconMapper) {
        $this->db = $db;
        $this->formatter = $formatter;
        $this->iconMapper = $iconMapper;
    }

    public function findRecent($userId, $limit = 10): array {
        $stmt = $this->db->prepare("
            SELECT 
                c.id,
                c.care_type,
                c.care_date,
                c.observations,
                c.next_maintenance_date,
                p.id as plant_id,
                p.name as plant_name,
                p.species,
                DATE_FORMAT(c.care_date, '%d/%m/%Y às %H:%i') as formatted_date,
                TIMESTAMPDIFF(HOUR, c.care_date, NOW()) as hours_ago
            FROM cares c
            INNER JOIN plants p ON c.plant_id = p.id
            WHERE p.user_id = ? 
            ORDER BY c.care_date DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $userId);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $activities = $stmt->fetchAll();
        
        foreach ($activities as &$activity) {
            $activity['description'] = $this->formatter->formatActivityDescription($activity);
            $activity['time'] = $this->formatter->formatTimeAgo($activity['hours_ago']);
            $activity['icon'] = $this->iconMapper->getIcon($activity['care_type']);
        }
        
        return $activities;
    }
}

class CareStatsCalculator {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function calculate($userId): array {
        // Cuidados realizados nos últimos 30 dias
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_cares,
                COUNT(DISTINCT c.plant_id) as plants_cared,
                SUM(CASE WHEN c.care_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as cares_last_week,
                AVG(CASE WHEN c.care_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as avg_cares_per_day
            FROM cares c
            INNER JOIN plants p ON c.plant_id = p.id
            WHERE p.user_id = ? 
            AND c.care_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$userId]);
        $stats = $stmt->fetch();
        
        // Distribuição por tipo de cuidado
        $stmt = $this->db->prepare("
            SELECT 
                care_type,
                COUNT(*) as count
            FROM cares c
            INNER JOIN plants p ON c.plant_id = p.id
            WHERE p.user_id = ? 
            AND c.care_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY care_type
            ORDER BY count DESC
        ");
        $stmt->execute([$userId]);
        $typeDistribution = $stmt->fetchAll();
        
        return [
            'total_cares' => $stats['total_cares'] ?? 0,
            'plants_cared' => $stats['plants_cared'] ?? 0,
            'cares_last_week' => $stats['cares_last_week'] ?? 0,
            'avg_cares_per_day' => round($stats['avg_cares_per_day'] ?? 0, 1),
            'type_distribution' => $typeDistribution
        ];
    }
}

class UpcomingCareFinder {
    private $db;
    private $iconMapper;

    public function __construct($db, $iconMapper) {
        $this->db = $db;
        $this->iconMapper = $iconMapper;
    }

    public function find($userId, $daysAhead = 7): array {
        $stmt = $this->db->prepare("
            SELECT 
                c.id,
                c.care_type,
                c.next_maintenance_date,
                DATEDIFF(c.next_maintenance_date, CURDATE()) as days_until,
                p.id as plant_id,
                p.name as plant_name,
                p.location
            FROM cares c
            INNER JOIN plants p ON c.plant_id = p.id
            WHERE p.user_id = ? 
            AND c.next_maintenance_date IS NOT NULL
            AND c.next_maintenance_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
            ORDER BY c.next_maintenance_date ASC
        ");
        $stmt->bindValue(1, $userId);
        $stmt->bindValue(2, $daysAhead, PDO::PARAM_INT);
        $stmt->execute();
        
        $upcomingCares = $stmt->fetchAll();
        
        foreach ($upcomingCares as &$care) {
            $care['formatted_date'] = date('d/m/Y', strtotime($care['next_maintenance_date']));
            $care['icon'] = $this->iconMapper->getIcon($care['care_type']);
            
            if ($care['days_until'] == 0) {
                $care['timeline'] = 'Hoje';
            } elseif ($care['days_until'] == 1) {
                $care['timeline'] = 'Amanhã';
            } else {
                $care['timeline'] = "Em {$care['days_until']} dias";
            }
        }
        
        return $upcomingCares;
    }
}

class CareNotificationGenerator {
    private $careModel;
    private $iconMapper;

    public function __construct($careModel, $iconMapper) {
        $this->careModel = $careModel;
        $this->iconMapper = $iconMapper;
    }

    public function generate($userId): array {
        $notifications = [];
        
        // Cuidados atrasados
        $overdueCares = $this->careModel->getPendingCaresWithDetails($userId);
        $highPriorityCares = array_filter($overdueCares, function($care) {
            return $care['priority'] === 'high';
        });
        
        if (!empty($highPriorityCares)) {
            $plantNames = array_slice(array_column($highPriorityCares, 'plant_name'), 0, 2);
            $message = count($highPriorityCares) . ' cuidado(s) atrasado(s)';
            if (!empty($plantNames)) {
                $message .= ' em: ' . implode(', ', $plantNames);
            }
            
            $notifications[] = [
                'type' => 'urgent',
                'title' => 'Cuidados Atrasados',
                'message' => $message,
                'time' => 'Urgente'
            ];
        }
        
        // Próximos cuidados (para hoje)
        $todayCares = array_filter($overdueCares, function($care) {
            return $care['priority'] === 'medium';
        });
        
        if (!empty($todayCares)) {
            $plantNames = array_slice(array_column($todayCares, 'plant_name'), 0, 2);
            $message = count($todayCares) . ' cuidado(s) para hoje';
            if (!empty($plantNames)) {
                $message .= ' em: ' . implode(', ', $plantNames);
            }
            
            $notifications[] = [
                'type' => 'warning',
                'title' => 'Cuidados para Hoje',
                'message' => $message,
                'time' => 'Hoje'
            ];
        }
        
        // Atividade recente
        $recentActivities = $this->careModel->getRecentActivities($userId, 1);
        if (!empty($recentActivities)) {
            $latestActivity = $recentActivities[0];
            $notifications[] = [
                'type' => 'info',
                'title' => 'Última Atividade',
                'message' => $latestActivity['description'],
                'time' => $latestActivity['time']
            ];
        }
        
        return $notifications;
    }
}

class CareUpdater {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function update($careId, $careData) {
        $stmt = $this->db->prepare("
            UPDATE cares 
            SET care_type = ?, care_date = ?, observations = ?, next_maintenance_date = ?
            WHERE id = ?
        ");
        return $stmt->execute([
            $careData['care_type'],
            $careData['care_date'],
            $careData['observations'],
            $careData['next_maintenance_date'],
            $careId
        ]);
    }
}

class CareDeleter {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function delete($careId) {
        $stmt = $this->db->prepare("DELETE FROM cares WHERE id = ?");
        return $stmt->execute([$careId]);
    }
}