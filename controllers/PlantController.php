<?php
/**
 * Lógica para gerenciar plantas.
 */
class PlantController {
    private $db;
    private $plantModel;

    public function __construct($db) {
        $this->db = $db;
        $this->plantModel = new PlantModel($db);
    }

    /**
     * Verifica o ID do usuário na sessão e redireciona para o login se não estiver logado.
     */
    private function getUserId() {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            header('Location: ' . BASE_URL . '?route=login');
            exit;
        }
        return $userId;
    }

    // -------------------------------------------------------------------------
    // Rota: ?route=dashboard (ou padrão) - Tela de Listagem Principal
    // -------------------------------------------------------------------------

    /**
     * Exibe a lista de plantas do usuário (Dashboard).
     */
    public function index() {
        $userId = $this->getUserId();

        // Busca de Plantas
        $plants = $this->plantModel->getAllPlantsByUserId($userId);
        $searchQuery = trim($_GET['search'] ?? '');
        
        // Filtro em PHP
        if (!empty($searchQuery)) {
            $searchLower = strtolower($searchQuery);
            $plants = array_filter($plants, function($plant) use ($searchLower) {
                return stripos($plant['name'], $searchLower) !== false ||
                       stripos($plant['species'], $searchLower) !== false ||
                       stripos($plant['location'], $searchLower) !== false;
            });
        }
        
        //  Buscar notificações e estatísticas
        $pendingNotifications = $this->plantModel->getPlantsWithPendingCare($userId);
        $totalNotifications = count($pendingNotifications);
        $gardenStats = $this->plantModel->getGardenStats($userId);
        $plantsWithoutCare = $this->plantModel->getPlantsWithoutCare($userId);
        
        error_log("PlantController - Dashboard carregado: " . count($plants) . " plantas, " . $totalNotifications . " notificações");
        
        // Carrega a view de listagem
        require 'views/protected/dashboard.php';
    }
    
    // -------------------------------------------------------------------------
    // Rota: ?route=plant/register (Cadastro de Planta)
    // -------------------------------------------------------------------------

    /**
     * Exibe e processa o formulário de Cadastro de Planta.
     */
    public function register() {
        $userId = $this->getUserId();
        $errors = [];
        $plantData = [
            'name' => '', 
            'species' => '', 
            'acquisition_date' => date('Y-m-d'), 
            'location' => ''
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Coleta e limpeza dos dados
            $plantData['name'] = trim($_POST['name'] ?? '');
            $plantData['species'] = trim($_POST['species'] ?? '');
            $plantData['acquisition_date'] = $_POST['acquisition_date'] ?? '';
            $plantData['location'] = trim($_POST['location'] ?? '');

            // Validação
            if (empty($plantData['name'])) { 
                $errors[] = "O nome da planta é obrigatório."; 
            }
            if (empty($plantData['species'])) { 
                $errors[] = "A espécie é obrigatória."; 
            }
            if (empty($plantData['acquisition_date'])) { 
                $errors[] = "A data de aquisição é obrigatória."; 
            }
            
            // Validar se a data não é futura
            if (!empty($plantData['acquisition_date'])) {
                $today = new DateTime();
                $acquisitionDate = new DateTime($plantData['acquisition_date']);
                if ($acquisitionDate > $today) {
                    $errors[] = "A data de aquisição não pode ser futura.";
                }
            }
            
            if (empty($errors)) {
                $success = $this->plantModel->createPlant(
                    $userId, 
                    $plantData['name'], 
                    $plantData['species'], 
                    $plantData['acquisition_date'], 
                    $plantData['location']
                );
                
                if ($success) {
                    $_SESSION['success_message'] = "Planta cadastrada com sucesso!";
                    header('Location: ' . BASE_URL . '?route=dashboard');
                    exit;
                } else {
                    $errors[] = "Erro ao cadastrar a planta. Tente novamente.";
                }
            }
        }
        
        // Carrega a view correta de planta
        require 'views/protected/plants/plant_form.php';
    }

    // -------------------------------------------------------------------------
    // Ações de CRUD
    // -------------------------------------------------------------------------

    /**
     * Visualizar Detalhes da Planta (?route=plant/detail&id=X)
     */
    public function show() {
        $userId = $this->getUserId();
        $plantId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

        if (!$plantId) {
            $_SESSION['error_message'] = "ID da planta inválido.";
            header('Location: ' . BASE_URL . '?route=dashboard');
            exit;
        }

        $plant = $this->plantModel->getPlantById($plantId);

        if (!$plant || $plant['user_id'] != $userId) {
            $_SESSION['error_message'] = "Planta não encontrada.";
            header('Location: ' . BASE_URL . '?route=dashboard');
            exit;
        }
        
        // Buscar cuidados da planta
        $careModel = new CareModel($this->db);
        $cares = $careModel->getCaresByPlantId($plantId);
        
        error_log("PlantController - Detalhes da planta $plantId: " . count($cares) . " cuidados encontrados");
        
        // Carrega a view de detalhes atualizada
        require 'views/protected/plants/details.php';
    }
    
    /**
     * Exibir Formulário de Edição e Processar Atualização (?route=plant/edit&id=X)
     */
    public function edit() {
        $userId = $this->getUserId();
        $plantId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        $errors = [];
        $plantData = [];

        if (!$plantId) {
            $_SESSION['error_message'] = "ID da planta inválido.";
            header('Location: ' . BASE_URL . '?route=dashboard');
            exit;
        }

        $currentPlant = $this->plantModel->getPlantById($plantId);

        if (!$currentPlant || $currentPlant['user_id'] != $userId) {
            $_SESSION['error_message'] = "Planta não encontrada.";
            header('Location: ' . BASE_URL . '?route=dashboard');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Processar atualização
            $plantData['name'] = trim($_POST['name'] ?? '');
            $plantData['species'] = trim($_POST['species'] ?? '');
            $plantData['acquisition_date'] = $_POST['acquisition_date'] ?? '';
            $plantData['location'] = trim($_POST['location'] ?? '');

            // Validação
            if (empty($plantData['name'])) { 
                $errors[] = "O nome da planta é obrigatório."; 
            }
            if (empty($plantData['species'])) { 
                $errors[] = "A espécie é obrigatória."; 
            }
            if (empty($plantData['acquisition_date'])) { 
                $errors[] = "A data de aquisição é obrigatória."; 
            }
            
            // Validar se a data não é futura
            if (!empty($plantData['acquisition_date'])) {
                $today = new DateTime();
                $acquisitionDate = new DateTime($plantData['acquisition_date']);
                if ($acquisitionDate > $today) {
                    $errors[] = "A data de aquisição não pode ser futura.";
                }
            }
            
            if (empty($errors)) {
                $success = $this->plantModel->updatePlant(
                    $plantId, 
                    $plantData['name'], 
                    $plantData['species'], 
                    $plantData['acquisition_date'], 
                    $plantData['location']
                );
                
                if ($success) {
                    $_SESSION['success_message'] = "Planta atualizada com sucesso!";
                    header('Location: ' . BASE_URL . '?route=plant/detail&id=' . $plantId);
                    exit;
                } else {
                    $errors[] = "Erro ao atualizar a planta. Tente novamente.";
                }
            }
        } else {
            // Carrega os dados existentes para o formulário GET
            $plantData = $currentPlant;
        }
        
        // Carrega a view correta
        require 'views/protected/plants/plant_edit.php';
    }

    /**
     * Processar Exclusão da Planta (?route=plant/delete&id=X)
     */
    public function delete() {
        $userId = $this->getUserId();
        $plantId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

        if (!$plantId) {
            $_SESSION['error_message'] = "ID da planta inválido.";
            header('Location: ' . BASE_URL . '?route=dashboard');
            exit;
        }

        $plant = $this->plantModel->getPlantById($plantId);

        if (!$plant || $plant['user_id'] != $userId) {
            $_SESSION['error_message'] = "Planta não encontrada.";
            header('Location: ' . BASE_URL . '?route=dashboard');
            exit;
        }

        if ($this->plantModel->deletePlant($plantId)) {
            $_SESSION['success_message'] = "Planta excluída com sucesso!";
        } else {
            $_SESSION['error_message'] = "Erro ao excluir a planta. Tente novamente.";
        }
        
        header('Location: ' . BASE_URL . '?route=dashboard');
        exit;
    }

    /**
     * Método create() como alias para register()
     * Para compatibilidade com o roteamento
     */
    public function create() {
        $this->register();
    }

    // -------------------------------------------------------------------------
    //  MÉTODOS PARA FUNCIONALIDADES AVANÇADAS
    // -------------------------------------------------------------------------

    /**
     *  Exibe estatísticas detalhadas do jardim
     */
    public function stats() {
        $userId = $this->getUserId();
        
        $gardenStats = $this->plantModel->getGardenStats($userId);
        $plantsWithoutCare = $this->plantModel->getPlantsWithoutCare($userId);
        $pendingNotifications = $this->plantModel->getPlantsWithPendingCare($userId);
        
        error_log("PlantController - Estatísticas carregadas: " . $gardenStats['total_plants'] . " plantas totais");
        
        require 'views/protected/plants/garden_stats.php';
    }

    /**
     *  Busca plantas por localização específica
     */
    public function byLocation() {
        $userId = $this->getUserId();
        $location = trim($_GET['location'] ?? '');
        
        if (empty($location)) {
            $_SESSION['error_message'] = "Localização não especificada.";
            header('Location: ' . BASE_URL . '?route=dashboard');
            exit;
        }
        
        $plants = $this->plantModel->getPlantsByLocation($userId, $location);
        
        error_log("PlantController - Plantas por localização '$location': " . count($plants));
        
        require 'views/protected/plants/plants_by_location.php';
    }

    /**
     *  Exibe plantas que precisam de atenção (sem cuidados)
     */
    public function needsAttention() {
        $userId = $this->getUserId();
        
        $plantsWithoutCare = $this->plantModel->getPlantsWithoutCare($userId);
        $pendingNotifications = $this->plantModel->getPlantsWithPendingCare($userId);
        
        error_log("PlantController - Plantas que precisam de atenção: " . count($plantsWithoutCare) . " sem cuidados");
        
        require 'views/protected/plants/needs_attention.php';
    }

    /**
     *  API para buscar notificações (para AJAX)
     */
    public function apiNotifications() {
    $userId = $this->getUserId();
    
    $pendingNotifications = $this->plantModel->getPlantsWithPendingCare($userId);
    $totalNotifications = count($pendingNotifications);
    
    // Retorna JSON para requisições AJAX
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'total' => $totalNotifications,
        'notifications' => $pendingNotifications,
        'high_priority' => count(array_filter($pendingNotifications, function($n) {
            return $n['priority'] === 'high';
        })),
        'medium_priority' => count(array_filter($pendingNotifications, function($n) {
            return $n['priority'] === 'medium';
        })),
        'low_priority' => count(array_filter($pendingNotifications, function($n) {
            return $n['priority'] === 'low';
        }))
    ]);
    exit;
}


    /**
     * Limpar/arquivar notificações
     */
    public function clearNotifications() {
        $userId = $this->getUserId();
        $plantId = filter_input(INPUT_GET, 'plant_id', FILTER_VALIDATE_INT);
        
        
        $_SESSION['success_message'] = "Notificações atualizadas.";
        header('Location: ' . BASE_URL . '?route=dashboard');
        exit;
    }
}