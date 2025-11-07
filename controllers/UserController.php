<?php

class UserController {
    private $db;
    private $userModel;
    private $viewRenderer;
    private $authManager;
    private $formValidator;

    public function __construct($db) {
        $this->db = $db;
        $this->userModel = new UserModel($db);
        $this->viewRenderer = new ViewRenderer();
        $this->authManager = new AuthManager();
        $this->formValidator = new UserFormValidator();
    }

    public function login() {
        $loginProcessor = new LoginProcessor(
            $this->userModel, 
            $this->viewRenderer, 
            $this->authManager
        );
        return $loginProcessor->process();
    }

    public function register() {
        $registerProcessor = new RegisterProcessor(
            $this->userModel,
            $this->viewRenderer,
            $this->formValidator
        );
        return $registerProcessor->process();
    }

    public function logout() {
        $logoutProcessor = new LogoutProcessor($this->authManager);
        return $logoutProcessor->process();
    }
}

// CLASSES AUXILIARES PARA PROCESSAMENTO ESPECÍFICO

class LoginProcessor {
    private $userModel;
    private $viewRenderer;
    private $authManager;
    private $sessionHandler;

    public function __construct($userModel, $viewRenderer, $authManager) {
        $this->userModel = $userModel;
        $this->viewRenderer = $viewRenderer;
        $this->authManager = $authManager;
        $this->sessionHandler = new SessionHandler();
    }

    public function process() {
        // Verificar se já está logado
        if ($this->authManager->isLoggedIn()) {
            $this->redirectToDashboard();
        }

        $loginData = new LoginData();
        $messageHandler = new MessageHandler();

        // Processar mensagens de sucesso
        $this->handleSuccessMessages($messageHandler);

        // Processar formulário
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return $this->processLoginForm($loginData, $messageHandler);
        }

        return $this->showLoginForm($loginData, $messageHandler);
    }

    private function handleSuccessMessages($messageHandler) {
        if (isset($_GET['success'])) {
            switch ($_GET['success']) {
                case 'registered':
                    $messageHandler->setSuccess("Cadastro realizado com sucesso! Faça login para continuar.");
                    break;
                case 'logout':
                    $messageHandler->setSuccess("Logout realizado com sucesso!");
                    break;
            }
        }
    }

    private function processLoginForm($loginData, $messageHandler) {
        $loginData->email = trim($_POST['email'] ?? '');
        $loginData->password = $_POST['password'] ?? '';

        $validator = new LoginValidator();
        $validationResult = $validator->validate($loginData);

        if (!$validationResult->isValid()) {
            return $this->showLoginForm($loginData, $messageHandler, $validationResult->getErrors());
        }

        $user = $this->userModel->getUserByEmail($loginData->email);

        if ($user && password_verify($loginData->password, $user['password_hash'])) {
            $this->authManager->login($user['id'], $user['name']);
            $this->redirectToDashboard();
        } else {
            return $this->showLoginForm($loginData, $messageHandler, ["Email ou senha incorretos."]);
        }
    }

    private function showLoginForm($loginData, $messageHandler, $errors = []) {
        $viewData = [
            'errors' => $errors,
            'email' => $loginData->email,
            'successMessage' => $messageHandler->getSuccess(),
            'BASE_URL' => BASE_URL
        ];
        
        $this->viewRenderer->render('login_form', $viewData);
    }

    private function redirectToDashboard() {
        header('Location: ' . BASE_URL . '?route=dashboard&success=login_success');
        exit;
    }
}

class RegisterProcessor {
    private $userModel;
    private $viewRenderer;
    private $formValidator;

    public function __construct($userModel, $viewRenderer, $formValidator) {
        $this->userModel = $userModel;
        $this->viewRenderer = $viewRenderer;
        $this->formValidator = $formValidator;
    }

    public function process() {
        $authManager = new AuthManager();
        
        // Verificar se já está logado
        if ($authManager->isLoggedIn()) {
            $this->redirectToDashboard();
        }

        $registerData = new RegisterData();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return $this->processRegistrationForm($registerData);
        }

        return $this->showRegistrationForm($registerData);
    }

    private function processRegistrationForm($registerData) {
        $this->populateRegisterDataFromPost($registerData);
        
        $validationResult = $this->formValidator->validate($registerData);

        if (!$validationResult->isValid()) {
            return $this->showRegistrationForm($registerData, $validationResult->getErrors());
        }

        // Verificar email único
        if ($this->userModel->getUserByEmail($registerData->email)) {
            return $this->showRegistrationForm($registerData, ["Este email já está cadastrado."]);
        }

        if ($this->createUser($registerData)) {
            $this->redirectToLogin();
        } else {
            return $this->showRegistrationForm($registerData, ["Erro interno ao cadastrar usuário. Tente novamente."]);
        }
    }

    private function populateRegisterDataFromPost($registerData) {
        $registerData->name = trim($_POST['name'] ?? '');
        $registerData->email = trim($_POST['email'] ?? '');
        $registerData->password = $_POST['password'] ?? '';
        $registerData->confirmPassword = $_POST['confirm_password'] ?? '';
    }

    private function createUser($registerData) {
        $passwordHash = password_hash($registerData->password, PASSWORD_DEFAULT);
        return $this->userModel->createUser($registerData->name, $registerData->email, $passwordHash);
    }

    private function showRegistrationForm($registerData, $errors = []) {
        $viewData = [
            'errors' => $errors,
            'name' => $registerData->name,
            'email' => $registerData->email,
            'BASE_URL' => BASE_URL
        ];
        
        $this->viewRenderer->render('register_form', $viewData);
    }

    private function redirectToDashboard() {
        header('Location: ' . BASE_URL . '?route=dashboard');
        exit;
    }

    private function redirectToLogin() {
        header('Location: ' . BASE_URL . '?route=login&success=registered');
        exit;
    }
}

class LogoutProcessor {
    private $authManager;

    public function __construct($authManager) {
        $this->authManager = $authManager;
    }

    public function process() {
        $this->authManager->logout();
        $this->redirectToLogin();
    }

    private function redirectToLogin() {
        header('Location: ' . BASE_URL . '?route=login&success=logout');
        exit;
    }
}

// CLASSES DE SUPORTE

class ViewRenderer {
    public function render($viewName, $data = []) {
        extract($data);
        require "views/public/{$viewName}.php";
    }
}

class AuthManager {
    private $sessionHandler;

    public function __construct() {
        $this->sessionHandler = new SessionHandler();
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public function login($userId, $userName) {
        $this->sessionHandler->set('user_id', $userId);
        $this->sessionHandler->set('user_name', $userName);
    }

    public function logout() {
        $this->sessionHandler->clear();
        session_destroy();
    }

    public function getCurrentUserId() {
        return $this->sessionHandler->get('user_id');
    }

    public function getCurrentUserName() {
        return $this->sessionHandler->get('user_name');
    }
}

class SessionHandle {
    public function set($key, $value) {
        $_SESSION[$key] = $value;
    }

    public function get($key) {
        return $_SESSION[$key] ?? null;
    }

    public function clear() {
        session_unset();
    }
}

class MessageHandler {
    private $successMessage;

    public function setSuccess($message) {
        $this->successMessage = $message;
    }

    public function getSuccess() {
        return $this->successMessage;
    }

    public function hasSuccess() {
        return !empty($this->successMessage);
    }
}

// CLASSES DE DADOS (Data Transfer Objects)

class LoginData {
    public $email = '';
    public $password = '';
}

class RegisterData {
    public $name = '';
    public $email = '';
    public $password = '';
    public $confirmPassword = '';
}

// CLASSES DE VALIDAÇÃO

class LoginValidator {
    public function validate(LoginData $data) {
        $errors = [];

        if (empty($data->email) || empty($data->password)) {
            $errors[] = "Todos os campos são obrigatórios.";
        }

        return new ValidationResult($errors);
    }
}

class UserFormValidator {
    public function validate(RegisterData $data) {
        $errors = [];

        if (empty($data->name) || empty($data->email) || empty($data->password) || empty($data->confirmPassword)) {
            $errors[] = "Todos os campos são obrigatórios.";
        }

        if (strlen($data->name) < 2) {
            $errors[] = "O nome deve ter pelo menos 2 caracteres.";
        }

        if (!filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "O email fornecido não é válido.";
        }

        if (strlen($data->password) < 6) {
            $errors[] = "A senha deve ter pelo menos 6 caracteres.";
        }

        if ($data->password !== $data->confirmPassword) {
            $errors[] = "A senha e a confirmação de senha não coincidem.";
        }

        return new ValidationResult($errors);
    }
}

class ValidationResult {
    private $errors;

    public function __construct(array $errors = []) {
        $this->errors = $errors;
    }

    public function isValid() {
        return empty($this->errors);
    }

    public function getErrors() {
        return $this->errors;
    }

    public function getFirstError() {
        return $this->errors[0] ?? '';
    }
}