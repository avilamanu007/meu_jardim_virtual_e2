<?php

class NotificationModel {
    private $db;
    private $tableName = 'cares';
    private $plantsTable = 'plants';

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Obtém notificações do usuário baseadas em cuidados pendentes
     */
    public function getUserNotifications($userId) {
        try {
            $notifications = $this->fetchPendingCareNotifications($userId);
            $formattedNotifications = $this->formatNotifications($notifications);
            
            return $formattedNotifications;

        } catch (PDOException $e) {
            $this->logError("Erro ao buscar notificações: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca notificações de cuidados pendentes do banco de dados
     */
    private function fetchPendingCareNotifications($userId): array {
        $sql = "SELECT 
                'urgent' as type,
                CONCAT('Cuidado pendente: ', p.name) as title,
                CONCAT('Próximo cuidado: ', c.care_type, ' - ', DATE_FORMAT(c.next_care_date, '%d/%m/%Y')) as message,
                TIMESTAMPDIFF(HOUR, NOW(), c.next_care_date) as hours_remaining
            FROM {$this->tableName} c
            JOIN {$this->plantsTable} p ON c.plant_id = p.id
            WHERE p.user_id = ? AND c.next_care_date <= DATE_ADD(NOW(), INTERVAL 2 DAY)
            ORDER BY c.next_care_date ASC
            LIMIT 5";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        
        return $stmt->fetchAll();
    }

    /**
     * Formata as notificações com base no tempo restante
     */
    private function formatNotifications(array $notifications): array {
        $formattedNotifications = [];

        foreach ($notifications as $notification) {
            $formattedNotifications[] = $this->formatSingleNotification($notification);
        }

        return $formattedNotifications;
    }

    /**
     * Formata uma única notificação
     */
    private function formatSingleNotification(array $notification): array {
        $hours = $notification['hours_remaining'];
        
        return [
            'type' => $this->determineNotificationType($hours),
            'title' => $notification['title'],
            'message' => $notification['message'],
            'time' => $this->determineTimeLabel($hours)
        ];
    }

    /**
     * Determina o tipo da notificação baseado nas horas restantes
     */
    private function determineNotificationType(int $hoursRemaining): string {
        if ($hoursRemaining <= 24) {
            return 'urgent';
        } elseif ($hoursRemaining <= 48) {
            return 'warning';
        } else {
            return 'info';
        }
    }

    /**
     * Determina o rótulo de tempo baseado nas horas restantes
     */
    private function determineTimeLabel(int $hoursRemaining): string {
        if ($hoursRemaining <= 24) {
            return 'Urgente!';
        } elseif ($hoursRemaining <= 48) {
            return 'Em breve';
        } else {
            return 'Próximos dias';
        }
    }

    /**
     * Registra erros no log
     */
    private function logError(string $message): void {
        error_log("NotificationModel - " . $message);
    }

    /**
     * Registra informações no log
     */
    private function logInfo(string $message): void {
        error_log("NotificationModel - " . $message);
    }

    /**
     * Getter para a conexão com o banco (útil para testes)
     */
    public function getDbConnection() {
        return $this->db;
    }

    /**
     * Getter para o nome da tabela de cuidados
     */
    public function getCaresTableName(): string {
        return $this->tableName;
    }

    /**
     * Getter para o nome da tabela de plantas
     */
    public function getPlantsTableName(): string {
        return $this->plantsTable;
    }

    /**
     * Método adicional: Busca notificações urgentes apenas
     */
    public function getUrgentNotifications($userId): array {
        try {
            $allNotifications = $this->getUserNotifications($userId);
            return $this->filterNotificationsByType($allNotifications, 'urgent');
            
        } catch (PDOException $e) {
            $this->logError("Erro ao buscar notificações urgentes: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Método adicional: Filtra notificações por tipo
     */
    private function filterNotificationsByType(array $notifications, string $type): array {
        return array_filter($notifications, function($notification) use ($type) {
            return $notification['type'] === $type;
        });
    }

    /**
     * Método adicional: Conta total de notificações
     */
    public function getNotificationCount($userId): int {
        try {
            $notifications = $this->getUserNotifications($userId);
            return count($notifications);
            
        } catch (PDOException $e) {
            $this->logError("Erro ao contar notificações: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Método adicional: Busca notificações com limite personalizado
     */
    public function getUserNotificationsWithLimit($userId, int $limit): array {
        try {
            $sql = "SELECT 
                    'urgent' as type,
                    CONCAT('Cuidado pendente: ', p.name) as title,
                    CONCAT('Próximo cuidado: ', c.care_type, ' - ', DATE_FORMAT(c.next_care_date, '%d/%m/%Y')) as message,
                    TIMESTAMPDIFF(HOUR, NOW(), c.next_care_date) as hours_remaining
                FROM {$this->tableName} c
                JOIN {$this->plantsTable} p ON c.plant_id = p.id
                WHERE p.user_id = ? AND c.next_care_date <= DATE_ADD(NOW(), INTERVAL 2 DAY)
                ORDER BY c.next_care_date ASC
                LIMIT ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId, $limit]);
            $notifications = $stmt->fetchAll();

            return $this->formatNotifications($notifications);

        } catch (PDOException $e) {
            $this->logError("Erro ao buscar notificações com limite: " . $e->getMessage());
            return [];
        }
    }
}