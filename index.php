<?php
// index.php - O Controlador Frontal (Front Controller)

// 1. Configuração e Inicialização da Sessão
session_start();
require 'config.php'; // Carrega as constantes de configuração (BASE_URL, DB_USER, etc.)

// 2. Conexão com o Banco de Dados (Direta e Funcional)
try {
    // Usa as constantes de config.php para conectar com PDO
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE              => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES     => false,
    ];
    
    $db = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
    // Se a conexão falhar, exibe um erro amigável e para a execução.
    die("<div style='padding: 20px; background-color: #fdd; border: 1px solid #f00; font-family: sans-serif;'>
        <h1>Erro de Conexão com o Banco de Dados</h1>
        <p>Não foi possível conectar ao banco de dados <b>" . DB_NAME . "</b>.</p>
        <p>Verifique o arquivo <code>config.php</code> e se o serviço MySQL do XAMPP está ativo.</p>
        <p>Detalhes do Erro: " . htmlspecialchars($e->getMessage()) . "</p>
    </div>");
}

// 3. Carregamento de Modelos e Controladores
require 'models/UserModel.php';
require 'models/PlantModel.php';
require 'models/CareModel.php'; // Model de cuidados
require 'controllers/UserController.php'; 
require 'controllers/PlantController.php';
require 'controllers/CareController.php'; // Controller de cuidados
require 'controllers/HomeController.php'; // Controller da Home

// --- Lógica de Roteamento ---

// 4. CORREÇÃO: Captura da rota de forma mais amigável
$route = $_GET['route'] ?? '';

// Se não veio pela query string, tenta capturar pela URL
if (empty($route)) {
    $request_uri = $_SERVER['REQUEST_URI'];
    
    // Remove a base URL se existir
    $base_path = parse_url(BASE_URL, PHP_URL_PATH);
    if ($base_path && strpos($request_uri, $base_path) === 0) {
        $request_uri = substr($request_uri, strlen($base_path));
    }
    
    // Remove a query string e barras extras
    $request_uri = parse_url($request_uri, PHP_URL_PATH);
    $route = trim($request_uri, '/');
    
    // Remove 'index.php' se estiver na URL
    if (strpos($route, 'index.php') === 0) {
        $route = substr($route, strlen('index.php'));
        $route = trim($route, '/');
    }
}

// Roteamento Padrão: Se a rota estiver vazia, define 'home' ou 'login'.
if (isset($_SESSION['user_id']) && empty($route)) {
    $route = 'home'; // MUDANÇA: Agora a rota padrão é 'home'
} elseif (!isset($_SESSION['user_id']) && empty($route)) {
    $route = 'login';
}

// 5. Executa o Controlador e a Ação (TODO O RESTO DO CÓDIGO PERMANECE IGUAL)
switch ($route) {
    // --- Rotas de Autenticação (UserController) ---
    case 'login':
        (new UserController($db))->login();
        break;
    case 'register':
        (new UserController($db))->register();
        break;
    case 'logout':
        (new UserController($db))->logout();
        break;

    // --- ROTA: HOME (HomeController) ---
    case 'home':
        // Rota protegida: Requer que o usuário esteja logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . '?route=login');
            exit;
        }
        (new HomeController($db))->index();
        break;

    // --- Rotas de Gestão de Plantas (PlantController) ---
    case 'dashboard':
        // Rota protegida: Requer que o usuário esteja logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . '?route=login');
            exit;
        }
        (new PlantController($db))->index(); // Listagem de plantas (Dashboard)
        break;
        
    case 'plant_register':
        // Rota protegida: Requer que o usuário esteja logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . '?route=login');
            exit;
        }
        $controller = new PlantController($db);
        
        // CORREÇÃO: Lógica simplificada e padronizada
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Se for POST, processa o cadastro
            if (method_exists($controller, 'store')) {
                $controller->store();
            } elseif (method_exists($controller, 'create')) {
                $controller->create(); // Fallback para create se store não existir
            } else {
                $_SESSION['error_message'] = "Método para processar cadastro não encontrado.";
                header('Location: ' . BASE_URL . '?route=dashboard');
                exit;
            }
        } else {
            // Se for GET, mostra o formulário
            if (method_exists($controller, 'create')) {
                $controller->create();
            } elseif (method_exists($controller, 'register')) {
                $controller->register(); // Fallback para register
            } else {
                $_SESSION['error_message'] = "Método para mostrar formulário não encontrado.";
                header('Location: ' . BASE_URL . '?route=dashboard');
                exit;
            }
        }
        break;
        
    case 'plant_details':
        // Rota protegida: Requer que o usuário esteja logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . '?route=login');
            exit;
        }
        // Valida se o ID da planta foi fornecido
        $plantId = $_GET['id'] ?? null;
        if (!$plantId) {
            $_SESSION['error_message'] = "ID da planta não informado.";
            header('Location: ' . BASE_URL . '?route=dashboard');
            exit;
        }
        (new PlantController($db))->show($plantId);
        break;
        
    case 'plant_edit':
        // Rota protegida: Requer que o usuário esteja logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . '?route=login');
            exit;
        }
        // Valida se o ID da planta foi fornecido
        $plantId = $_GET['id'] ?? null;
        if (!$plantId) {
            $_SESSION['error_message'] = "ID da planta não informado.";
            header('Location: ' . BASE_URL . '?route=dashboard');
            exit;
        }
        $controller = new PlantController($db);
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Se for POST, processa a edição
            if (method_exists($controller, 'update')) {
                $controller->update($plantId);
            } else {
                $_SESSION['error_message'] = "Método para atualizar planta não encontrado.";
                header('Location: ' . BASE_URL . '?route=plant_edit&id=' . $plantId);
                exit;
            }
        } else {
            // Se for GET, mostra o formulário de edição
            if (method_exists($controller, 'edit')) {
                $controller->edit($plantId);
            } else {
                $_SESSION['error_message'] = "Método para editar planta não encontrado.";
                header('Location: ' . BASE_URL . '?route=dashboard');
                exit;
            }
        }
        break;
        
    case 'plant_delete':
        // Rota protegida: Requer que o usuário esteja logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . '?route=login');
            exit;
        }
        // Valida se o ID da planta foi fornecido
        $plantId = $_POST['plant_id'] ?? $_GET['id'] ?? null;
        if (!$plantId) {
            $_SESSION['error_message'] = "ID da planta não informado.";
            header('Location: ' . BASE_URL . '?route=dashboard');
            exit;
        }
        (new PlantController($db))->delete($plantId);
        break;

    // --- Rotas: Gestão de Cuidados (CareController) ---
    case 'care_register':
    case 'care_history':
    case 'care_pending':
    case 'care_complete':
    case 'care_stats':
    case 'care_edit':
    case 'care_delete':
    case 'calendar':
        // Rotas protegidas: Requerem que o usuário esteja logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . '?route=login');
            exit;
        }
        $controller = new CareController($db);
        if ($route === 'care_register') {
            $controller->register(); // Formulário de registro de cuidado
        } elseif ($route === 'care_history') {
            $controller->history(); // Histórico de cuidados de uma planta
        } elseif ($route === 'care_pending') {
            $controller->pending(); // Lista de cuidados pendentes
        } elseif ($route === 'care_complete') {
            $controller->complete(); // Dar baixa em cuidado
        } elseif ($route === 'care_stats') {
            $controller->stats(); // Estatísticas de cuidados
        } elseif ($route === 'care_edit') {
            // Editar cuidado - precisa do ID
            $careId = $_GET['id'] ?? null;
            if (!$careId) {
                $_SESSION['error_message'] = "ID do cuidado não informado.";
                header('Location: ' . BASE_URL . '?route=care_pending');
                exit;
            }
            $controller->edit($careId);
        } elseif ($route === 'care_delete') {
            // Excluir cuidado - precisa do ID
            $careId = $_POST['care_id'] ?? $_GET['id'] ?? null;
            if (!$careId) {
                $_SESSION['error_message'] = "ID do cuidado não informado.";
                header('Location: ' . BASE_URL . '?route=care_pending');
                exit;
            }
            $controller->delete($careId);
        } elseif ($route === 'calendar') {
            $controller->calendar(); // Calendário de cuidados
        }
        break;
        
    default:
        // Se a rota não for reconhecida, redireciona para HOME (se logado) ou login
        $target_route = isset($_SESSION['user_id']) ? 'home' : 'login';
        header('Location: ' . BASE_URL . '?route=' . $target_route);
        exit;
}