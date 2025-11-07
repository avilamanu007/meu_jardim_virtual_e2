<?php
// controllers/AuthController.php

class AuthController {
    private PDO $db;
    private UserModel $userModel;
    private AuthService $authService;
    private Validator $validator;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->userModel = new UserModel($db);
        $this->authService = new AuthService($this->userModel);
        $this->validator = new Validator();
    }

    // -------------------------------------------------------------------------
    // Rota: ?route=register
    // -------------------------------------------------------------------------

    /**
     * Exibe e processa o formulário de Cadastro de Usuário.
     */
    public function register(): void {
        if ($this->authService->isUserLoggedIn()) {
            $this->redirect('plant_register');
        }

        $formData = new RegisterFormData();
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $formData->hydrateFromPost($_POST);
            $validationResult = $this->validator->validateRegistration($formData, $this->userModel);

            if ($validationResult->isValid()) {
                $user = $this->authService->registerUser($formData);
                
                if ($user) {
                    $this->authService->loginUser($user->getId());
                    $this->redirect('plant_register');
                } else {
                    $errors['db'] = "Erro ao cadastrar usuário. Verifique sua conexão com o banco de dados.";
                }
            } else {
                $errors = $validationResult->getErrors();
            }
        }

        $this->renderView('views/public/register_form.php', [
            'data' => $formData,
            'errors' => $errors
        ]);
    }

    // -------------------------------------------------------------------------
    // Rota: ?route=login
    // -------------------------------------------------------------------------

    /**
     * Exibe e processa o formulário de Login.
     */
    public function login(): void {
        if ($this->authService->isUserLoggedIn()) {
            $this->redirect('plant_register');
        }

        $formData = new LoginFormData();
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $formData->hydrateFromPost($_POST);
            $validationResult = $this->validator->validateLogin($formData);

            if ($validationResult->isValid()) {
                $user = $this->authService->authenticateUser($formData->getEmail(), $formData->getPassword());
                
                if ($user) {
                    $this->authService->loginUser($user->getId());
                    $this->redirect('plant_register');
                } else {
                    $errors['password'] = "Email ou senha incorretos.";
                }
            } else {
                $errors = $validationResult->getErrors();
            }
        }

        $this->renderView('views/public/login_form.php', [
            'data' => $formData,
            'errors' => $errors
        ]);
    }

    // -------------------------------------------------------------------------
    // Rota: ?route=logout
    // -------------------------------------------------------------------------
    
    /**
     * Destrói a sessão e redireciona para a tela de login.
     */
    public function logout(): void {
        $this->authService->logout();
        $this->redirect('login');
    }

    // -------------------------------------------------------------------------
    // Métodos auxiliares
    // -------------------------------------------------------------------------

    private function redirect(string $route): void {
        header('Location: ' . BASE_URL . '?route=' . $route);
        exit;
    }

    private function renderView(string $viewPath, array $data = []): void {
        extract($data);
        require $viewPath;
    }
}

// -----------------------------------------------------------------------------
// Classes de Dados (Data Transfer Objects)
// -----------------------------------------------------------------------------

class RegisterFormData {
    private string $name;
    private string $email;
    private string $password;
    private string $passwordConfirm;

    public function hydrateFromPost(array $postData): void {
        $this->name = trim(filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS) ?? '');
        $this->email = trim($postData['email'] ?? '');
        $this->password = trim($postData['password'] ?? '');
        $this->passwordConfirm = trim($postData['password_confirm'] ?? '');
    }

    // Getters
    public function getName(): string { return $this->name; }
    public function getEmail(): string { return $this->email; }
    public function getPassword(): string { return $this->password; }
    public function getPasswordConfirm(): string { return $this->passwordConfirm; }
}

class LoginFormData {
    private string $email;
    private string $password;

    public function hydrateFromPost(array $postData): void {
        $this->email = trim($postData['email'] ?? '');
        $this->password = trim($postData['password'] ?? '');
    }

    // Getters
    public function getEmail(): string { return $this->email; }
    public function getPassword(): string { return $this->password; }
}

// -----------------------------------------------------------------------------
// Serviço de Autenticação
// -----------------------------------------------------------------------------

class AuthService {
    private UserModel $userModel;

    public function __construct(UserModel $userModel) {
        $this->userModel = $userModel;
    }

    public function isUserLoggedIn(): bool {
        return isset($_SESSION['user_id']);
    }

    public function registerUser(RegisterFormData $formData): ?User {
        return $this->userModel->createUser(
            $formData->getName(),
            $formData->getEmail(),
            $formData->getPassword()
        );
    }

    public function authenticateUser(string $email, string $password): ?User {
        $user = $this->userModel->findByEmail($email);
        
        if ($user && password_verify($password, $user->getPasswordHash())) {
            return $user;
        }
        
        return null;
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
// Validador
// -----------------------------------------------------------------------------

class Validator {
    public function validateRegistration(RegisterFormData $data, UserModel $userModel): ValidationResult {
        $errors = [];

        if (empty($data->getName())) {
            $errors['name'] = "O nome é obrigatório.";
        }

        if (!filter_var($data->getEmail(), FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Email inválido.";
        } elseif ($userModel->findByEmail($data->getEmail())) {
            $errors['email'] = "Este email já está cadastrado.";
        }

        if (strlen($data->getPassword()) < 6) {
            $errors['password'] = "A senha deve ter no mínimo 6 caracteres.";
        }

        if ($data->getPassword() !== $data->getPasswordConfirm()) {
            $errors['password_confirm'] = "A confirmação de senha não confere.";
        }

        return new ValidationResult($errors);
    }

    public function validateLogin(LoginFormData $data): ValidationResult {
        $errors = [];

        if (!filter_var($data->getEmail(), FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Email inválido.";
        }

        return new ValidationResult($errors);
    }
}

// -----------------------------------------------------------------------------
// Resultado de Validação
// -----------------------------------------------------------------------------

class ValidationResult {
    private array $errors;

    public function __construct(array $errors = []) {
        $this->errors = $errors;
    }

    public function isValid(): bool {
        return empty($this->errors);
    }

    public function getErrors(): array {
        return $this->errors;
    }
}

// -----------------------------------------------------------------------------
// Atualização do UserModel (exemplo)
// -----------------------------------------------------------------------------

class User {
    private int $id;
    private string $name;
    private string $email;
    private string $passwordHash;

    public function __construct(int $id, string $name, string $email, string $passwordHash) {
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
        $this->passwordHash = $passwordHash;
    }

    // Getters
    public function getId(): int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function getEmail(): string { return $this->email; }
    public function getPasswordHash(): string { return $this->passwordHash; }
}

// No UserModel, os métodos devem retornar objetos User em vez de arrays