<?php
// controllers/UserController.php - Gerencia Autenticação e Sessão

class UserController {
    private $db;
    private $userModel;

    public function __construct($db) {
        $this->db = $db;
        $this->userModel = new UserModel($db);
    }

    private function renderView($viewName, $data = []) {
        extract($data);
        require "views/public/{$viewName}.php";
    }

    public function login() {
        $errors = [];
        $email = $password = '';
        $successMessage = '';

        // Verificar se veio do cadastro com sucesso
        if (isset($_GET['success']) && $_GET['success'] === 'registered') {
            $successMessage = "Cadastro realizado com sucesso! Faça login para continuar.";
        }

        // Verificar se veio do logout
        if (isset($_GET['success']) && $_GET['success'] === 'logout') {
            $successMessage = "Logout realizado com sucesso!";
        }

        // Redirecionar se já estiver logado
        if (isset($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . '?route=dashboard');
            exit;
        }

        // Processar formulário POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($email) || empty($password)) {
                $errors[] = "Todos os campos são obrigatórios.";
            }

            if (empty($errors)) {
                $user = $this->userModel->getUserByEmail($email);

                if ($user && password_verify($password, $user['password_hash'])) {
                    // Login bem-sucedido
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];

                    header('Location: ' . BASE_URL . '?route=dashboard&success=login_success');
                    exit;
                } else {
                    $errors[] = "Email ou senha incorretos.";
                }
            }
        }

        $this->renderView('login_form', [
            'errors' => $errors,
            'email' => $email,
            'successMessage' => $successMessage,
            'BASE_URL' => BASE_URL
        ]);
    }

    public function register() {
        $errors = [];
        $name = $email = '';

        // Redirecionar se já estiver logado
        if (isset($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . '?route=dashboard');
            exit;
        }

        // Processar formulário POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            // Validação
            if (empty($name) || empty($email) || empty($password) || empty($confirmPassword)) {
                $errors[] = "Todos os campos são obrigatórios.";
            }

            if (strlen($name) < 2) {
                $errors[] = "O nome deve ter pelo menos 2 caracteres.";
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "O email fornecido não é válido.";
            }

            if (strlen($password) < 6) {
                $errors[] = "A senha deve ter pelo menos 6 caracteres.";
            }

            if ($password !== $confirmPassword) {
                $errors[] = "A senha e a confirmação de senha não coincidem.";
            }

            // Checar se o email já existe
            if (empty($errors)) {
                $existingUser = $this->userModel->getUserByEmail($email);
                if ($existingUser) {
                    $errors[] = "Este email já está cadastrado.";
                }
            }

            if (empty($errors)) {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $userId = $this->userModel->createUser($name, $email, $passwordHash);

                if ($userId) {
                    // CORREÇÃO: Redireciona para login em vez de fazer login automático
                    header('Location: ' . BASE_URL . '?route=login&success=registered');
                    exit;
                } else {
                    $errors[] = "Erro interno ao cadastrar usuário. Tente novamente.";
                }
            }
        }

        $this->renderView('register_form', [
            'errors' => $errors,
            'name' => $name,
            'email' => $email,
            'BASE_URL' => BASE_URL
        ]);
    }

    public function logout() {
        session_unset();
        session_destroy();
        header('Location: ' . BASE_URL . '?route=login&success=logout');
        exit;
    }
}
