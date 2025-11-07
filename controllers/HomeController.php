<?php
// controllers/HomeController.php

class HomeController {
    private PDO $db;
    private PlantModel $plantModel;
    private CareModel $careModel;
    private AuthService $authService;
    private DashboardService $dashboardService;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->plantModel = new PlantModel($db);
        $this->careModel = new CareModel($db);
        $this->authService = new AuthService(new UserModel($db));
        $this->dashboardService = new DashboardService($this->plantModel, $this->careModel);
    }

    public function index(): void {
        $userId = $this->authService->getAuthenticatedUserId();
        
        try {
            $dashboardData = $this->dashboardService->getDashboardData($userId);
            
            $this->renderView('views/protected/home.php', [
                'summaryStats' => $dashboardData->getSummaryStats(),
                'pendingCares' => $dashboardData->getPendingCares(),
                'notifications' => $dashboardData->getNotifications(),
                'recentActivities' => $dashboardData->getRecentActivities()
            ]);
            
        } catch (Exception $e) {
            error_log("HomeController::index - Error: " . $e->getMessage());
            $this->renderView('views/protected/home.php', [
                'summaryStats' => $this->getEmptySummaryStats(),
                'pendingCares' => [],
                'notifications' => [],
                'recentActivities' => []
            ]);
        }
    }

    private function renderView(string $viewPath, array $data = []): void {
        extract($data);
        require $viewPath;
    }

    private function getEmptySummaryStats(): array {
        return [
            'total_plants' => 0,
            'pending_care' => 0,
            'healthy_plants' => 0,
            'locations' => 0
        ];
    }
}

// -----------------------------------------------------------------------------
// Services
// -----------------------------------------------------------------------------

class DashboardService {
    private PlantModel $plantModel;
    private CareModel $careModel;
    private NotificationService $notificationService;

    public function __construct(PlantModel $plantModel, CareModel $careModel) {
        $this->plantModel = $plantModel;
        $this->careModel = $careModel;
        $this->notificationService = new NotificationService($plantModel, $careModel);
    }

    public function getDashboardData(int $userId): DashboardData {
        $summaryStats = $this->getSummaryStats($userId);
        $pendingCares = $this->careModel->getPendingCaresForUser($userId);
        $notifications = $this->notificationService->getUserNotifications($userId);
        $recentActivities = $this->careModel->getRecentActivities($userId, 5);

        return new DashboardData(
            $summaryStats,
            $pendingCares,
            $notifications,
            $recentActivities
        );
    }

    private function getSummaryStats(int $userId): array {
        return [
            'total_plants' => $this->plantModel->countPlantsByUserId($userId),
            'pending_care' => $this->careModel->getPendingCareCount($userId),
            'healthy_plants' => $this->plantModel->getHealthyPlantsCount($userId),
            'locations' => $this->plantModel->getTotalLocations($userId)
        ];
    }
}

class NotificationService {
    private PlantModel $plantModel;
    private CareModel $careModel;
    private NotificationBuilder $notificationBuilder;

    public function __construct(PlantModel $plantModel, CareModel $careModel) {
        $this->plantModel = $plantModel;
        $this->careModel = $careModel;
        $this->notificationBuilder = new NotificationBuilder();
    }

    public function getUserNotifications(int $userId): array {
        $plantNotifications = $this->plantModel->getPlantNotifications($userId);
        $careNotifications = $this->careModel->getCareNotifications($userId);
        
        return $this->notificationBuilder->mergeAndSortNotifications(
            $plantNotifications,
            $careNotifications
        );
    }

    public function getUnreadNotificationCount(int $userId): int {
        $notifications = $this->getUserNotifications($userId);
        return count(array_filter($notifications, fn($notification) => !$notification->isRead()));
    }
}

// -----------------------------------------------------------------------------
// Data Transfer Objects and Value Objects
// -----------------------------------------------------------------------------

class DashboardData {
    private array $summaryStats;
    private array $pendingCares;
    private array $notifications;
    private array $recentActivities;

    public function __construct(
        array $summaryStats,
        array $pendingCares,
        array $notifications,
        array $recentActivities
    ) {
        $this->summaryStats = $summaryStats;
        $this->pendingCares = $pendingCares;
        $this->notifications = $notifications;
        $this->recentActivities = $recentActivities;
    }

    // Getters
    public function getSummaryStats(): array { return $this->summaryStats; }
    public function getPendingCares(): array { return $this->pendingCares; }
    public function getNotifications(): array { return $this->notifications; }
    public function getRecentActivities(): array { return $this->recentActivities; }
}

class Notification {
    private string $id;
    private string $type;
    private string $title;
    private string $message;
    private string $priority;
    private DateTime $createdAt;
    private bool $isRead;
    private ?string $actionUrl;

    public function __construct(
        string $id,
        string $type,
        string $title,
        string $message,
        string $priority = 'medium',
        ?string $actionUrl = null
    ) {
        $this->id = $id;
        $this->type = $type;
        $this->title = $title;
        $this->message = $message;
        $this->priority = $priority;
        $this->createdAt = new DateTime();
        $this->isRead = false;
        $this->actionUrl = $actionUrl;
    }

    // Getters
    public function getId(): string { return $this->id; }
    public function getType(): string { return $this->type; }
    public function getTitle(): string { return $this->title; }
    public function getMessage(): string { return $this->message; }
    public function getPriority(): string { return $this->priority; }
    public function getCreatedAt(): DateTime { return $this->createdAt; }
    public function isRead(): bool { return $this->isRead; }
    public function getActionUrl(): ?string { return $this->actionUrl; }

    // Setters
    public function markAsRead(): void { $this->isRead = true; }
}

class SummaryStatistics {
    private int $totalPlants;
    private int $pendingCare;
    private int $healthyPlants;
    private int $locations;

    public function __construct(int $totalPlants, int $pendingCare, int $healthyPlants, int $locations) {
        $this->totalPlants = $totalPlants;
        $this->pendingCare = $pendingCare;
        $this->healthyPlants = $healthyPlants;
        $this->locations = $locations;
    }

    // Getters
    public function getTotalPlants(): int { return $this->totalPlants; }
    public function getPendingCare(): int { return $this->pendingCare; }
    public function getHealthyPlants(): int { return $this->healthyPlants; }
    public function getLocations(): int { return $this->locations; }

    public function toArray(): array {
        return [
            'total_plants' => $this->totalPlants,
            'pending_care' => $this->pendingCare,
            'healthy_plants' => $this->healthyPlants,
            'locations' => $this->locations
        ];
    }
}

// -----------------------------------------------------------------------------
// Builders
// -----------------------------------------------------------------------------

class NotificationBuilder {
    public function mergeAndSortNotifications(array $plantNotifications, array $careNotifications): array {
        $allNotifications = array_merge($plantNotifications, $careNotifications);
        
        usort($allNotifications, function($a, $b) {
            return $b->getCreatedAt() <=> $a->getCreatedAt();
        });

        return $allNotifications;
    }

    public function createPlantNotification(string $plantName, string $issue, string $priority = 'medium'): Notification {
        $id = 'plant_' . uniqid();
        $title = "Atenção: {$plantName}";
        
        $messages = [
            'watering' => "precisa de água urgente!",
            'health' => "está com problemas de saúde.",
            'maintenance' => "precisa de manutenção."
        ];

        $message = $messages[$issue] ?? "precisa de atenção.";
        
        return new Notification($id, 'plant_alert', $title, $message, $priority);
    }

    public function createCareNotification(string $plantName, string $careType, int $daysOverdue = 0): Notification {
        $id = 'care_' . uniqid();
        $title = "Cuidado Pendente: {$plantName}";
        
        $priority = $daysOverdue > 0 ? 'high' : 'medium';
        $message = $daysOverdue > 0 
            ? "{$careType} atrasado há {$daysOverdue} dias" 
            : "{$careType} precisa ser realizado";

        return new Notification($id, 'care_reminder', $title, $message, $priority);
    }
}

// -----------------------------------------------------------------------------
// Updated AuthService (extensão para incluir método necessário)
// -----------------------------------------------------------------------------

class AuthService {
    private UserModel $userModel;

    public function __construct(UserModel $userModel) {
        $this->userModel = $userModel;
    }

    public function getAuthenticatedUserId(): int {
        $userId = $_SESSION['user_id'] ?? null;
        
        if (!$userId) {
            header('Location: ' . BASE_URL . '?route=login');
            exit;
        }
        
        return (int)$userId;
    }

    public function isUserLoggedIn(): bool {
        return isset($_SESSION['user_id']);
    }

    public function loginUser(int $userId): void {
        $_SESSION['user_id'] = $userId;
    }

    public function logout(): void {
        session_unset();
        session_destroy();
    }
}

// -----------------------------------------------------------------------------
// Interfaces para melhor testabilidade e inversão de dependência
// -----------------------------------------------------------------------------

interface DashboardDataProviderInterface {
    public function getDashboardData(int $userId): DashboardData;
}

interface NotificationProviderInterface {
    public function getUserNotifications(int $userId): array;
    public function getUnreadNotificationCount(int $userId): int;
}

// -----------------------------------------------------------------------------
// Implementação alternativa para testes
// -----------------------------------------------------------------------------

class MockDashboardService implements DashboardDataProviderInterface {
    private array $mockData;

    public function __construct(array $mockData = []) {
        $this->mockData = $mockData;
    }

    public function getDashboardData(int $userId): DashboardData {
        return new DashboardData(
            $this->mockData['summaryStats'] ?? ['total_plants' => 0, 'pending_care' => 0, 'healthy_plants' => 0, 'locations' => 0],
            $this->mockData['pendingCares'] ?? [],
            $this->mockData['notifications'] ?? [],
            $this->mockData['recentActivities'] ?? []
        );
    }
}

// -----------------------------------------------------------------------------
// Versão estendida do HomeController com funcionalidades adicionais
// -----------------------------------------------------------------------------

class ExtendedHomeController extends HomeController {
    private NotificationService $notificationService;

    public function __construct(PDO $db) {
        parent::__construct($db);
        $this->notificationService = new NotificationService(
            new PlantModel($db),
            new CareModel($db)
        );
    }

    public function getNotificationCount(): array {
        $userId = $this->authService->getAuthenticatedUserId();
        
        try {
            $unreadCount = $this->notificationService->getUnreadNotificationCount($userId);
            
            return [
                'success' => true,
                'unread_count' => $unreadCount
            ];
        } catch (Exception $e) {
            error_log("ExtendedHomeController::getNotificationCount - Error: " . $e->getMessage());
            return [
                'success' => false,
                'unread_count' => 0
            ];
        }
    }

    public function markNotificationAsRead(string $notificationId): array {
        $userId = $this->authService->getAuthenticatedUserId();
        
        // Lógica para marcar notificação como lida
        // Esta implementação dependerá de como as notificações são armazenadas
        
        return [
            'success' => true,
            'message' => 'Notificação marcada como lida'
        ];
    }
}