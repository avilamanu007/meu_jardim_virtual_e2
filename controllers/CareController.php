<?php
class CareController {
    private $db;
    private $careModel;
    private $plantModel;

    public function __construct($db) {
        $this->db = $db;
        $this->careModel = new CareModel($db);
        $this->plantModel = new PlantModel($db);
    }

    private function getUserId() {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            header('Location: ' . BASE_URL . '?route=login');
            exit;
        }
        return $userId;
    }

    private function calculateNextMaintenance($careType, $careDate) {
        $careDate = new DateTime($careDate);
        
        switch ($careType) {
            case 'rega': $careDate->modify('+3 days'); break;
            case 'adubacao': $careDate->modify('+30 days'); break;
            case 'poda': $careDate->modify('+90 days'); break;
            case 'transplante': $careDate->modify('+180 days'); break;
            case 'tratamento': $careDate->modify('+14 days'); break;
            case 'limpeza': $careDate->modify('+15 days'); break;
            default: $careDate->modify('+30 days'); break;
        }
        
        return $careDate->format('Y-m-d');
    }

    public function register() {
    $userId = $this->getUserId();
    $errors = [];
    $successMessage = '';

    // Buscar plantas do usuário
    $plants = $this->plantModel->getAllPlantsByUserId($userId);
    
    // DEBUG: Verificar se há plantas
    error_log("CareController - Plantas encontradas: " . count($plants));

    // Valores padrão para o formulário
    $careData = [
        'plant_id' => $_POST['plant_id'] ?? '',
        'care_type' => $_POST['care_type'] ?? '',
        'care_date' => $_POST['care_date'] ?? date('Y-m-d'),
        'observations' => $_POST['observations'] ?? '',
        'next_maintenance_date' => $_POST['next_maintenance_date'] ?? ''
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // DEBUG
        error_log("CareController - POST recebido: " . print_r($_POST, true));
        
        // Limpar e validar dados
        $careData['plant_id'] = trim($_POST['plant_id'] ?? '');
        $careData['care_type'] = trim($_POST['care_type'] ?? '');
        $careData['care_date'] = trim($_POST['care_date'] ?? '');
        $careData['observations'] = trim($_POST['observations'] ?? '');
        $careData['next_maintenance_date'] = trim($_POST['next_maintenance_date'] ?? '');
        
        // DEBUG
        error_log("CareController - Dados coletados: " . print_r($careData, true));

        // Validações
        if (empty($careData['plant_id'])) {
            $errors[] = "Selecione uma planta.";
        } else {
            
            $userPlants = $this->plantModel->getAllPlantsByUserId($userId);
            $plantExists = false;
            foreach ($userPlants as $plant) {
                if ($plant['id'] == $careData['plant_id']) {
                    $plantExists = true;
                    break;
                }
            }
            
            if (!$plantExists) {
                $errors[] = "Planta não encontrada ou não pertence ao usuário.";
            }
        }

        if (empty($careData['care_type'])) {
            $errors[] = "O tipo de cuidado é obrigatório.";
        }

        if (empty($careData['care_date'])) {
            $errors[] = "A data do cuidado é obrigatória.";
        } else {
            // Verificar se a data não é futura
            $today = new DateTime();
            $careDate = new DateTime($careData['care_date']);
            if ($careDate > $today) {
                $errors[] = "A data do cuidado não pode ser futura.";
            }
        }

        
        if (empty($errors) && empty($careData['next_maintenance_date']) && !empty($careData['care_type']) && !empty($careData['care_date'])) {
            $careData['next_maintenance_date'] = $this->calculateNextMaintenance(
                $careData['care_type'], 
                $careData['care_date']
            );
            error_log("CareController - Próxima manutenção calculada: " . $careData['next_maintenance_date']);
        }

        
        if (empty($errors) && empty($careData['next_maintenance_date'])) {
            $errors[] = "Não foi possível calcular a próxima data de manutenção.";
        }

        if (empty($errors)) {
            // DEBUG antes de salvar
            error_log("CareController - Tentando salvar no banco...");
            error_log("CareController - Dados finais: " . print_r($careData, true));
            
            $success = $this->careModel->createCare(
                $careData['plant_id'],
                $careData['care_type'],
                $careData['care_date'],
                $careData['observations'],
                $careData['next_maintenance_date']
            );

            // DEBUG após salvar
            error_log("CareController - Resultado do save: " . ($success ? 'SUCESSO' : 'FALHA'));

            if ($success) {
                $_SESSION['success_message'] = "Cuidado registrado com sucesso!";
                header('Location: ' . BASE_URL . '?route=dashboard&success=care_registered');
                exit;
            } else {
                $errors[] = "Erro ao registrar o cuidado. Tente novamente.";
                error_log("CareController - ERRO: Não conseguiu salvar no banco");
            }
        } else {
            error_log("CareController - Erros de validação: " . print_r($errors, true));
        }
    }

    // Buscar mensagem de sucesso da sessão
    $successMessage = $_SESSION['success_message'] ?? '';
    unset($_SESSION['success_message']);

    require 'views/protected/care/care_register.php';
}

    public function history($plantId = null) {
        $userId = $this->getUserId();
        
        try {
            // Se plantId foi fornecido, mostrar histórico da planta específica
            if ($plantId) {
                $plant = $this->plantModel->getPlantById($plantId);
                // Verificar se a planta pertence ao usuário
                if (!$plant || $plant['user_id'] != $userId) {
                    $_SESSION['error_message'] = "Planta não encontrada.";
                    header('Location: ' . BASE_URL . '?route=dashboard');
                    exit;
                }
                $cares = $this->careModel->getCaresByPlantId($plantId);
            } else {
                // Mostrar todos os cuidados do usuário
                $plant = null;
                $cares = $this->careModel->getCaresByUserId($userId);
            }
            
            // DEBUG
            error_log("CareController - Histórico - Cuidados encontrados: " . count($cares));
            
            require 'views/protected/care/care_history.php';
            
        } catch (Exception $e) {
            error_log("CareController - Erro no histórico: " . $e->getMessage());
            $errors = ["Erro ao carregar histórico de cuidados."];
            require 'views/protected/care/care_history.php';
        }
    }

    
    public function detail($careId) {
        $userId = $this->getUserId();
        
        // Buscar cuidado com verificação de propriedade
        $care = $this->careModel->getCareById($careId);
        
        if (!$care) {
            $_SESSION['error_message'] = "Cuidado não encontrado.";
            header('Location: ' . BASE_URL . '?route=care/history');
            exit;
        }
        
        // Verificar se a planta do cuidado pertence ao usuário
        $plant = $this->plantModel->getPlantById($care['plant_id']);
        if (!$plant || $plant['user_id'] != $userId) {
            $_SESSION['error_message'] = "Acesso negado.";
            header('Location: ' . BASE_URL . '?route=dashboard');
            exit;
        }
        
        require 'views/protected/care/care_detail.php';
    }

   
    public function pending() {
        $userId = $this->getUserId();
        
        try {
            $pendingCares = $this->careModel->getPendingCaresForUser($userId);
            
            // DEBUG
            error_log("CareController - Pendências encontradas: " . count($pendingCares));
            
            require 'views/protected/care/care_pending.php';
            
        } catch (Exception $e) {
            error_log("CareController - Erro ao carregar pendências: " . $e->getMessage());
            $_SESSION['error_message'] = "Erro ao carregar cuidados pendentes.";
            header('Location: ' . BASE_URL . '?route=dashboard');
            exit;
        }
    }

 
    public function complete() {
        $userId = $this->getUserId();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Método não permitido.";
            header('Location: ' . BASE_URL . '?route=care_pending');
            exit;
        }

        $careId = $_POST['care_id'] ?? null;
        $observations = trim($_POST['observations'] ?? '');

        if (!$careId) {
            $_SESSION['error_message'] = "ID do cuidado não informado.";
            header('Location: ' . BASE_URL . '?route=care_pending');
            exit;
        }

        try {
            // DEBUG
            error_log("CareController - Tentando dar baixa no cuidado: " . $careId);
            
            $success = $this->careModel->completeCare($careId, $userId, $observations);
            
            if ($success) {
                $_SESSION['success_message'] = "Cuidado registrado com sucesso! Próxima manutenção agendada.";
                error_log("CareController - Baixa realizada com sucesso no cuidado: " . $careId);
            } else {
                $_SESSION['error_message'] = "Erro ao registrar o cuidado. Verifique se o cuidado existe e pertence a você.";
                error_log("CareController - ERRO ao dar baixa no cuidado: " . $careId);
            }
            
        } catch (Exception $e) {
            error_log("CareController - Exceção ao dar baixa: " . $e->getMessage());
            $_SESSION['error_message'] = "Erro interno ao processar a solicitação.";
        }

        header('Location: ' . BASE_URL . '?route=care_pending');
        exit;
    }

 
    public function stats() {
        $userId = $this->getUserId();
        
        try {
            $careStats = $this->careModel->getCareStats($userId);
            $upcomingCares = $this->careModel->getUpcomingCares($userId, 7);
            $recentActivities = $this->careModel->getRecentActivities($userId, 10);
            
            require 'views/protected/care/care_stats.php';
            
        } catch (Exception $e) {
            error_log("CareController - Erro ao carregar estatísticas: " . $e->getMessage());
            $_SESSION['error_message'] = "Erro ao carregar estatísticas de cuidados.";
            header('Location: ' . BASE_URL . '?route=dashboard');
            exit;
        }
    }

   
    public function edit($careId) {
        $userId = $this->getUserId();
        $errors = [];
        
        // Buscar cuidado com verificação de propriedade
        $care = $this->careModel->getCareById($careId);
        
        if (!$care) {
            $_SESSION['error_message'] = "Cuidado não encontrado.";
            header('Location: ' . BASE_URL . '?route=care_pending');
            exit;
        }
        
        // Verificar se a planta do cuidado pertence ao usuário
        $plant = $this->plantModel->getPlantById($care['plant_id']);
        if (!$plant || $plant['user_id'] != $userId) {
            $_SESSION['error_message'] = "Acesso negado.";
            header('Location: ' . BASE_URL . '?route=dashboard');
            exit;
        }

        // Buscar plantas do usuário para o formulário
        $plants = $this->plantModel->getAllPlantsByUserId($userId);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $careData = [
                'care_type' => trim($_POST['care_type'] ?? ''),
                'care_date' => trim($_POST['care_date'] ?? ''),
                'observations' => trim($_POST['observations'] ?? ''),
                'next_maintenance_date' => trim($_POST['next_maintenance_date'] ?? '')
            ];

            // Validações
            if (empty($careData['care_type'])) {
                $errors[] = "O tipo de cuidado é obrigatório.";
            }

            if (empty($careData['care_date'])) {
                $errors[] = "A data do cuidado é obrigatória.";
            }

            if (empty($errors)) {
                $success = $this->careModel->updateCare($careId, $careData);
                
                if ($success) {
                    $_SESSION['success_message'] = "Cuidado atualizado com sucesso!";
                    header('Location: ' . BASE_URL . '?route=care_pending');
                    exit;
                } else {
                    $errors[] = "Erro ao atualizar o cuidado. Tente novamente.";
                }
            }
        }

        require 'views/protected/care/care_edit.php';
    }

    
    public function delete($careId) {
        $userId = $this->getUserId();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = "Método não permitido.";
            header('Location: ' . BASE_URL . '?route=care_pending');
            exit;
        }

        // Buscar cuidado com verificação de propriedade
        $care = $this->careModel->getCareById($careId);
        
        if (!$care) {
            $_SESSION['error_message'] = "Cuidado não encontrado.";
            header('Location: ' . BASE_URL . '?route=care_pending');
            exit;
        }
        
        // Verificar se a planta do cuidado pertence ao usuário
        $plant = $this->plantModel->getPlantById($care['plant_id']);
        if (!$plant || $plant['user_id'] != $userId) {
            $_SESSION['error_message'] = "Acesso negado.";
            header('Location: ' . BASE_URL . '?route=dashboard');
            exit;
        }

        try {
            $success = $this->careModel->deleteCare($careId);
            
            if ($success) {
                $_SESSION['success_message'] = "Cuidado excluído com sucesso!";
            } else {
                $_SESSION['error_message'] = "Erro ao excluir o cuidado.";
            }
            
        } catch (Exception $e) {
            error_log("CareController - Erro ao excluir cuidado: " . $e->getMessage());
            $_SESSION['error_message'] = "Erro interno ao excluir o cuidado.";
        }

        header('Location: ' . BASE_URL . '?route=care_pending');
        exit;
    }

    public function calendar() {
    try {
        $userId = $_SESSION['user_id'];
        $careModel = new CareModel($this->db);
        $plantModel = new PlantModel($this->db);
        
        // Buscar dados para o calendário
        $upcomingCares = $careModel->getUpcomingCares($userId, 90);
        $recentCares = $careModel->getCaresByUserId($userId);
        $plants = $plantModel->getAllPlantsByUserId($userId);

        // Preparar eventos para o calendário
        $calendarEvents = [];
        
        
        foreach ($recentCares as $care) {
            $calendarEvents[] = [
                'id' => 'care_' . $care['id'],
                'title' => '✅ ' . $care['care_type'] . ' - ' . $care['plant_name'],
                'start' => $care['care_date'],
                'color' => '#10B981',
                'type' => 'care_done',
                'plant_name' => $care['plant_name'],
                'care_type' => $care['care_type'],
                'observations' => $care['observations'] ?? ''
            ];
        }
        
        
        foreach ($upcomingCares as $care) {
            $nextDate = $care['next_maintenance_date'] ?? null;
            if ($nextDate) {
                $isOverdue = strtotime($nextDate) < strtotime(date('Y-m-d'));
                $calendarEvents[] = [
                    'id' => 'upcoming_' . $care['id'],
                    'title' => '📅 ' . $care['care_type'] . ' - ' . $care['plant_name'],
                    'start' => $nextDate,
                    'color' => $isOverdue ? '#EF4444' : '#3B82F6',
                    'type' => 'care_upcoming',
                    'plant_name' => $care['plant_name'],
                    'care_type' => $care['care_type'],
                    'days_until' => $care['days_until'] ?? 0,
                    'is_overdue' => $isOverdue
                ];
            }
        }
        
        
        foreach ($plants as $plant) {
            // Verificar se a chave existe e tem valor válido
            $wateringDate = $plant['next_watering_date'] ?? null;
            if ($wateringDate && !empty($wateringDate) && $wateringDate !== '0000-00-00') {
                $isOverdue = strtotime($wateringDate) < strtotime(date('Y-m-d'));
                $calendarEvents[] = [
                    'id' => 'water_' . $plant['id'],
                    'title' => '💧 Rega - ' . $plant['name'],
                    'start' => $wateringDate,
                    'color' => $isOverdue ? '#F59E0B' : '#06B6D4',
                    'type' => 'watering',
                    'plant_name' => $plant['name'],
                    'care_type' => 'Regar'
                ];
            }
        }
        
        // Carregar a view do calendário
        $pageTitle = 'Calendário de Cuidados';
        ob_start();
        include 'views/calendar/index.php';
        $content = ob_get_clean();
        
        // Incluir o template principal
        include 'views/includes/header.php';
        echo $content;
        include 'views/includes/footer.php';
        
    } catch (Exception $e) {
        error_log("Erro no calendário: " . $e->getMessage());
        $_SESSION['error_message'] = "Erro ao carregar o calendário.";
        header('Location: ' . BASE_URL . '?route=dashboard');
        exit;
    }
}

    
}