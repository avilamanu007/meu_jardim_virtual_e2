<?php

/**
 * Lógica de dados para a tabela 'plants' - Versão Orientada a Objetos
 */
class PlantModel {
    private $db;
    private $tableName = 'plants';

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Cria uma nova planta no banco de dados.
     */
    public function createPlant(int $userId, string $name, string $species, string $acquisition_date, string $location): bool {
        try {
            $query = "INSERT INTO {$this->tableName} (user_id, name, species, acquisition_date, location) 
                     VALUES (:user_id, :name, :species, :acquisition_date, :location)";
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                ':user_id' => $userId,
                ':name' => $this->sanitizeString($name),
                ':species' => $this->sanitizeString($species),
                ':acquisition_date' => $acquisition_date,
                ':location' => $this->sanitizeString($location)
            ]);
            
            $this->logInfo("Planta criada: " . ($result ? 'SUCESSO' : 'FALHA'));
            return $result;
            
        } catch (PDOException $e) {
            $this->logError("Erro ao criar planta: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Busca todas as plantas de um usuário específico.
     * Esta função é usada para a tela de listagem/Dashboard.
     */
    public function getAllPlantsByUserId(int $userId): array {
        try {
            $query = "SELECT id, name, species, acquisition_date, location 
                     FROM {$this->tableName} 
                     WHERE user_id = :user_id 
                     ORDER BY name ASC";
                     
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            $plants = $stmt->fetchAll();
            
            $this->logInfo("Plantas encontradas para usuário $userId: " . count($plants));
            return $plants;
            
        } catch (PDOException $e) {
            $this->logError("Erro ao buscar plantas: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Busca uma planta específica pelo ID.
     */
    public function getPlantById(int $id): ?array {
        try {
            $query = "SELECT id, user_id, name, species, acquisition_date, location 
                     FROM {$this->tableName} 
                     WHERE id = :id";
                     
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $plant = $stmt->fetch();
            
            $this->logInfo("Planta encontrada ID $id: " . ($plant ? 'SIM' : 'NÃO'));
            return $plant ? $plant : null;
            
        } catch (PDOException $e) {
            $this->logError("Erro ao buscar planta ID $id: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Busca planta por ID verificando propriedade do usuário
     * Usado para validar se a planta pertence ao usuário
     */
    public function getPlantByIdAndUserId(int $plantId, int $userId): ?array {
        try {
            $query = "SELECT id, user_id, name, species, acquisition_date, location 
                     FROM {$this->tableName} 
                     WHERE id = :plant_id AND user_id = :user_id";
                     
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':plant_id' => $plantId,
                ':user_id' => $userId
            ]);
            $plant = $stmt->fetch();
            
            $this->logInfo("Validação propriedade - Planta $plantId do usuário $userId: " . ($plant ? 'PERTENCE' : 'NÃO PERTENCE'));
            return $plant ? $plant : null;
            
        } catch (PDOException $e) {
            $this->logError("Erro ao validar propriedade da planta: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Atualiza os dados de uma planta.
     */
    public function updatePlant($plantId, $name, $species, $acquisitionDate, $location): bool {
        try {
            $query = "UPDATE {$this->tableName} 
                     SET name = ?, species = ?, acquisition_date = ?, location = ?
                     WHERE id = ?";
                     
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                $this->sanitizeString($name),
                $this->sanitizeString($species),
                $acquisitionDate,
                $this->sanitizeString($location),
                $plantId
            ]);
            
            $this->logInfo("Planta atualizada ID $plantId: " . ($result ? 'SUCESSO' : 'FALHA'));
            return $result;
            
        } catch (PDOException $e) {
            $this->logError("Erro ao atualizar planta: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Exclui uma planta pelo ID.
     */
    public function deletePlant(int $id): bool {
        try {
            $query = "DELETE FROM {$this->tableName} WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $result = $stmt->execute();
            
            $this->logInfo("Planta excluída ID $id: " . ($result ? 'SUCESSO' : 'FALHA'));
            return $result;
            
        } catch (PDOException $e) {
            $this->logError("Erro ao excluir planta: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Conta quantas plantas o usuário possui
     */
    public function countPlantsByUserId(int $userId): int {
        try {
            $query = "SELECT COUNT(*) as total FROM {$this->tableName} WHERE user_id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            $result = $stmt->fetch();
            
            return $result['total'] ?? 0;
            
        } catch (PDOException $e) {
            $this->logError("Erro ao contar plantas: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Busca plantas que precisam de cuidados (com base na última manutenção)
     */
    public function getPlantsNeedingCare(int $userId): array {
        try {
            $query = "SELECT p.*, 
                             MAX(c.next_maintenance_date) as last_maintenance_date
                      FROM {$this->tableName} p
                      LEFT JOIN cares c ON p.id = c.plant_id
                      WHERE p.user_id = :user_id
                      GROUP BY p.id
                      HAVING last_maintenance_date IS NULL OR last_maintenance_date <= CURDATE()
                      ORDER BY last_maintenance_date ASC";
                      
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            $this->logError("Erro ao buscar plantas precisando de cuidado: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca plantas com cuidados pendentes
     */
    public function getPlantsWithPendingCare(int $userId): array {
        try {
            $query = "SELECT DISTINCT p.*, 
                             MIN(c.next_maintenance_date) as next_care_date,
                             DATEDIFF(MIN(c.next_maintenance_date), CURDATE()) as days_remaining
                      FROM {$this->tableName} p
                      INNER JOIN cares c ON p.id = c.plant_id
                      WHERE p.user_id = :user_id 
                      AND c.next_maintenance_date IS NOT NULL
                      AND c.next_maintenance_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                      GROUP BY p.id
                      HAVING days_remaining <= 7
                      ORDER BY next_care_date ASC";
                      
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            $plants = $stmt->fetchAll();
            
            // Classificar por urgência
            foreach ($plants as &$plant) {
                $plant['priority'] = $this->determineCarePriority($plant['days_remaining']);
            }
            
            $this->logInfo("Plantas com cuidados pendentes encontradas: " . count($plants));
            return $plants;
            
        } catch (PDOException $e) {
            $this->logError("Erro ao buscar plantas com cuidados pendentes: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtém estatísticas do jardim
     */
    public function getGardenStats(int $userId): array {
        try {
            $stats = [];
            
            // Total de plantas
            $stats['total_plants'] = $this->countPlantsByUserId($userId);
            
            // Plantas com cuidados pendentes
            $pendingPlants = $this->getPlantsWithPendingCare($userId);
            $stats['pending_care'] = count($pendingPlants);
            
            // Plantas por localização
            $stats['plants_by_location'] = $this->getPlantsByLocationStats($userId);
            
            // Espécies mais comuns
            $stats['top_species'] = $this->getTopSpecies($userId);
            
            $this->logInfo("Estatísticas carregadas: " . json_encode($stats));
            return $stats;
            
        } catch (PDOException $e) {
            $this->logError("Erro ao buscar estatísticas: " . $e->getMessage());
            return $this->getDefaultStats();
        }
    }

    /**
     * Busca plantas sem cuidados
     */
    public function getPlantsWithoutCare(int $userId): array {
        try {
            $query = "SELECT p.* 
                      FROM {$this->tableName} p
                      LEFT JOIN cares c ON p.id = c.plant_id
                      WHERE p.user_id = :user_id 
                      AND c.id IS NULL
                      ORDER BY p.name ASC";
                      
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            $plants = $stmt->fetchAll();
            $this->logInfo("Plantas sem cuidados: " . count($plants));
            return $plants;
            
        } catch (PDOException $e) {
            $this->logError("Erro ao buscar plantas sem cuidados: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca plantas por localização
     */
    public function getPlantsByLocation(int $userId, string $location): array {
        try {
            $query = "SELECT id, name, species, acquisition_date, location 
                     FROM {$this->tableName} 
                     WHERE user_id = :user_id AND location LIKE :location
                     ORDER BY name ASC";
                     
            $stmt = $this->db->prepare($query);
            $searchLocation = '%' . $this->sanitizeString($location) . '%';
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':location', $searchLocation);
            $stmt->execute();
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            $this->logError("Erro ao buscar plantas por localização: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Conta plantas saudáveis
     */
    public function getHealthyPlantsCount(int $userId): int {
        try {
            $query = "SELECT COUNT(DISTINCT p.id) as count
                      FROM {$this->tableName} p
                      LEFT JOIN cares c ON p.id = c.plant_id
                      WHERE p.user_id = :user_id
                      AND (c.care_date >= DATE_SUB(CURDATE(), INTERVAL 15 DAY) OR c.id IS NULL)";
                      
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            $result = $stmt->fetch();
            
            return $result['count'] ?? 0;
            
        } catch (PDOException $e) {
            $this->logError("Erro ao contar plantas saudáveis: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Conta total de localizações
     */
    public function getTotalLocations(int $userId): int {
        try {
            $query = "SELECT COUNT(DISTINCT location) as count 
                     FROM {$this->tableName} 
                     WHERE user_id = :user_id 
                     AND location IS NOT NULL 
                     AND location != ''";
                     
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            $result = $stmt->fetch();
            
            return $result['count'] ?? 0;
            
        } catch (PDOException $e) {
            $this->logError("Erro ao contar localizações: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Busca plantas recentemente adicionadas
     */
    public function getRecentlyAddedPlants(int $userId, int $limit = 5): array {
        try {
            $query = "SELECT id, name, species, location, acquisition_date,
                             DATE_FORMAT(created_at, '%d/%m/%Y') as added_date
                      FROM {$this->tableName} 
                      WHERE user_id = :user_id 
                      ORDER BY created_at DESC 
                      LIMIT :limit";
                      
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $plants = $stmt->fetchAll();
            
            // Adiciona ícone para cada planta
            foreach ($plants as &$plant) {
                $plant['icon'] = $this->getPlantIcon($plant['species']);
            }
            
            return $plants;
            
        } catch (PDOException $e) {
            $this->logError("Erro ao buscar plantas recentes: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtém estatísticas resumidas para a home
     */
    public function getHomeSummaryStats(int $userId): array {
        try {
            return [
                'total_plants' => $this->countPlantsByUserId($userId),
                'pending_care' => $this->getPendingCareCount($userId),
                'healthy_plants' => $this->getHealthyPlantsCount($userId),
                'locations' => $this->getTotalLocations($userId)
            ];
            
        } catch (PDOException $e) {
            $this->logError("Erro ao buscar estatísticas da home: " . $e->getMessage());
            return $this->getDefaultHomeStats();
        }
    }

    /**
     * Conta cuidados pendentes
     */
    public function getPendingCareCount(int $userId): int {
        try {
            $query = "SELECT COUNT(DISTINCT p.id) as count
                      FROM {$this->tableName} p
                      INNER JOIN cares c ON p.id = c.plant_id
                      WHERE p.user_id = :user_id 
                      AND c.next_maintenance_date IS NOT NULL
                      AND c.next_maintenance_date <= CURDATE()";
                      
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            $result = $stmt->fetch();
            
            return $result['count'] ?? 0;
            
        } catch (PDOException $e) {
            $this->logError("Erro ao contar cuidados pendentes: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtém notificações das plantas
     */
    public function getPlantNotifications(int $userId): array {
        try {
            $notifications = [];
            
            // Plantas sem cuidados
            $notifications = array_merge($notifications, $this->getNoCareNotifications($userId));
            
            // Plantas com cuidados atrasados
            $notifications = array_merge($notifications, $this->getOverdueCareNotifications($userId));
            
            // Plantas adicionadas recentemente
            $notifications = array_merge($notifications, $this->getRecentPlantsNotifications($userId));
            
            return $notifications;
            
        } catch (PDOException $e) {
            $this->logError("Erro ao buscar notificações: " . $e->getMessage());
            return [];
        }
    }

    // ===== MÉTODOS PRIVADOS AUXILIARES =====

    /**
     * Determina prioridade do cuidado baseado em dias restantes
     */
    private function determineCarePriority(int $daysRemaining): string {
        if ($daysRemaining < 0) {
            return 'high'; // Atrasado
        } elseif ($daysRemaining == 0) {
            return 'medium'; // Para hoje
        } else {
            return 'low'; // Próximos dias
        }
    }

    /**
     * Obtém estatísticas de plantas por localização
     */
    private function getPlantsByLocationStats(int $userId): array {
        $query = "SELECT location, COUNT(*) as count 
                 FROM {$this->tableName} 
                 WHERE user_id = :user_id 
                 GROUP BY location 
                 ORDER BY count DESC";
                 
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Obtém espécies mais comuns
     */
    private function getTopSpecies(int $userId): array {
        $query = "SELECT species, COUNT(*) as count 
                 FROM {$this->tableName} 
                 WHERE user_id = :user_id 
                 GROUP BY species 
                 ORDER BY count DESC 
                 LIMIT 5";
                 
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Retorna estatísticas padrão em caso de erro
     */
    private function getDefaultStats(): array {
        return [
            'total_plants' => 0,
            'pending_care' => 0,
            'plants_by_location' => [],
            'top_species' => []
        ];
    }

    /**
     * Retorna estatísticas padrão para home em caso de erro
     */
    private function getDefaultHomeStats(): array {
        return [
            'total_plants' => 0,
            'pending_care' => 0,
            'healthy_plants' => 0,
            'locations' => 0
        ];
    }

    /**
     * Gera notificações para plantas sem cuidados
     */
    private function getNoCareNotifications(int $userId): array {
        $plantsWithoutCare = $this->getPlantsWithoutCare($userId);
        if (empty($plantsWithoutCare)) {
            return [];
        }

        return [[
            'type' => 'warning',
            'title' => 'Plantas sem cuidados',
            'message' => count($plantsWithoutCare) . ' planta(s) nunca receberam cuidados',
            'time' => 'Atenção'
        ]];
    }

    /**
     * Gera notificações para cuidados atrasados
     */
    private function getOverdueCareNotifications(int $userId): array {
        $overduePlants = $this->getPlantsWithPendingCare($userId);
        $overdueCount = 0;
        
        foreach ($overduePlants as $plant) {
            if ($plant['priority'] === 'high') {
                $overdueCount++;
            }
        }

        if ($overdueCount === 0) {
            return [];
        }

        return [[
            'type' => 'urgent',
            'title' => 'Cuidados atrasados',
            'message' => $overdueCount . ' planta(s) com cuidados em atraso',
            'time' => 'Urgente'
        ]];
    }

    /**
     * Gera notificações para plantas recentes
     */
    private function getRecentPlantsNotifications(int $userId): array {
        $recentPlants = $this->getRecentlyAddedPlants($userId, 3);
        if (empty($recentPlants)) {
            return [];
        }

        $plantNames = array_slice(array_column($recentPlants, 'name'), 0, 2);
        $notificationMsg = 'Novas plantas: ' . implode(', ', $plantNames);
        
        if (count($recentPlants) > 2) {
            $notificationMsg .= ' e mais ' . (count($recentPlants) - 2);
        }

        return [[
            'type' => 'info',
            'title' => 'Plantas recentes',
            'message' => $notificationMsg,
            'time' => 'Recente'
        ]];
    }

    /**
     * Sanitiza strings
     */
    private function sanitizeString(string $value): string {
        return trim($value);
    }

    /**
     * Log de informações
     */
    private function logInfo(string $message): void {
        error_log("PlantModel - " . $message);
    }

    /**
     * Log de erros
     */
    private function logError(string $message): void {
        error_log("PlantModel - " . $message);
    }

    /**
     * Obtém ícone da planta baseado na espécie
     */
    private function getPlantIcon(string $species): string {
        $species = strtolower($species);
        
        if (strpos($species, 'suculent') !== false || strpos($species, 'cact') !== false) {
            return '🌵';
        } elseif (strpos($species, 'orquíd') !== false || strpos($species, 'orchild') !== false) {
            return '🌸';
        } elseif (strpos($species, 'samambai') !== false || strpos($species, 'fern') !== false) {
            return '🌿';
        } elseif (strpos($species, 'rosa') !== false || strpos($species, 'rose') !== false) {
            return '🌹';
        } elseif (strpos($species, 'comest') !== false || strpos($species, 'hortali') !== false) {
            return '🍅';
        } else {
            return '🌱';
        }
    }

    /**
     * Getter para a conexão com o banco (útil para testes)
     */
    public function getDbConnection(): PDO {
        return $this->db;
    }

    /**
     * Getter para o nome da tabela (útil para testes)
     */
    public function getTableName(): string {
        return $this->tableName;
    }
}