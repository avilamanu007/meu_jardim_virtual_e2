<?php
// models/UserModel.php - Lógica de dados para a tabela 'users'

class UserModel {
    private $db;
    private $tableName = 'users';

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Busca um usuário pelo email.
     * COMPATÍVEL com UserController::register() e login()
     */
    public function getUserByEmail($email) {
        $email = $this->sanitizeEmail($email);
        
        try {
            $query = "SELECT id, name, email, password_hash FROM {$this->tableName} WHERE email = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$email]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logError("Erro no getUserByEmail: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cria um novo usuário no banco de dados.
     * COMPATÍVEL com UserController::register()
     */
    public function createUser($name, $email, $passwordHash) {
        $name = $this->sanitizeName($name);
        $email = $this->sanitizeEmail($email);

        try {
            $query = "
                INSERT INTO {$this->tableName} (name, email, password_hash, created_at) 
                VALUES (?, ?, ?, NOW())
            ";

            $stmt = $this->db->prepare($query);
            $success = $stmt->execute([$name, $email, $passwordHash]);
            
            if ($success) {
                return $this->db->lastInsertId(); // Retorna o ID do usuário criado
            }
            return false;
            
        } catch (PDOException $e) {
            $this->logError("Erro no createUser: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Método adicional útil: Buscar usuário por ID
     */
    public function getUserById($id) {
        try {
            $query = "SELECT id, name, email, created_at FROM {$this->tableName} WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$id]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logError("Erro no getUserById: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Método para atualizar dados do usuário
     */
    public function updateUser($id, $name, $email) {
        $name = $this->sanitizeName($name);
        $email = $this->sanitizeEmail($email);

        try {
            $query = "
                UPDATE {$this->tableName} 
                SET name = ?, email = ?, updated_at = NOW() 
                WHERE id = ?
            ";

            $stmt = $this->db->prepare($query);
            return $stmt->execute([$name, $email, $id]);
            
        } catch (PDOException $e) {
            $this->logError("Erro no updateUser: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Método para deletar usuário
     */
    public function deleteUser($id) {
        try {
            $query = "DELETE FROM {$this->tableName} WHERE id = ?";
            $stmt = $this->db->prepare($query);
            return $stmt->execute([$id]);
            
        } catch (PDOException $e) {
            $this->logError("Erro no deleteUser: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Método para listar todos os usuários (com paginação opcional)
     */
    public function getAllUsers($limit = null, $offset = 0) {
        try {
            $query = "SELECT id, name, email, created_at FROM {$this->tableName}";
            
            if ($limit !== null) {
                $query .= " LIMIT ? OFFSET ?";
                $stmt = $this->db->prepare($query);
                $stmt->execute([$limit, $offset]);
            } else {
                $stmt = $this->db->prepare($query);
                $stmt->execute();
            }
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logError("Erro no getAllUsers: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Métodos auxiliares privados para sanitização
     */
    private function sanitizeName($name) {
        return trim($name);
    }

    private function sanitizeEmail($email) {
        return trim($email);
    }

    /**
     * Método auxiliar para logging de erros
     */
    private function logError($message) {
        error_log($message);
    }

    /**
     * Getter para a conexão com o banco (útil para testes)
     */
    public function getDbConnection() {
        return $this->db;
    }
}