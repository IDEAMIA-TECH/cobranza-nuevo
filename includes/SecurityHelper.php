<?php
class SecurityHelper {
    // Tiempo de expiración de sesión en segundos (30 minutos)
    const SESSION_EXPIRE_TIME = 1800;
    
    // Intentos máximos de login antes de bloqueo
    const MAX_LOGIN_ATTEMPTS = 5;
    
    // Tiempo de bloqueo en segundos (15 minutos)
    const LOCKOUT_TIME = 900;
    
    public static function validateSession() {
        if (!isset($_SESSION['last_activity'])) {
            return false;
        }
        
        if (time() - $_SESSION['last_activity'] > self::SESSION_EXPIRE_TIME) {
            session_destroy();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    public static function checkLoginAttempts($email, $db) {
        $query = "SELECT attempts, last_attempt 
                  FROM login_attempts 
                  WHERE email = :email";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return true;
        }
        
        // Si ha pasado el tiempo de bloqueo, reiniciar intentos
        if (time() - strtotime($result['last_attempt']) > self::LOCKOUT_TIME) {
            $query = "UPDATE login_attempts 
                      SET attempts = 0, 
                          last_attempt = NOW() 
                      WHERE email = :email";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":email", $email);
            $stmt->execute();
            return true;
        }
        
        return $result['attempts'] < self::MAX_LOGIN_ATTEMPTS;
    }
    
    public static function updateLoginAttempts($email, $success, $db) {
        $query = "SELECT id FROM login_attempts WHERE email = :email";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();
        
        // Convertir el booleano a entero
        $attempts = $success ? 0 : 1;
        
        if ($stmt->rowCount() === 0) {
            $query = "INSERT INTO login_attempts (email, attempts, last_attempt) 
                      VALUES (:email, :attempts, NOW())";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":email", $email);
            $stmt->bindParam(":attempts", $attempts);
            $stmt->execute();
        } else {
            if ($success) {
                $query = "UPDATE login_attempts 
                          SET attempts = 0, 
                              last_attempt = NOW() 
                          WHERE email = :email";
            } else {
                $query = "UPDATE login_attempts 
                          SET attempts = attempts + 1, 
                              last_attempt = NOW() 
                          WHERE email = :email";
            }
            $stmt = $db->prepare($query);
            $stmt->bindParam(":email", $email);
            $stmt->execute();
        }
    }
    
    public static function validateCSRF() {
        if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
    }
    
    public static function sanitizeInput($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }
    
    public static function validatePassword($password) {
        // Mínimo 8 caracteres
        if (strlen($password) < 8) {
            return false;
        }
        
        // Debe contener al menos una letra mayúscula
        if (!preg_match('/[A-Z]/', $password)) {
            return false;
        }
        
        // Debe contener al menos una letra minúscula
        if (!preg_match('/[a-z]/', $password)) {
            return false;
        }
        
        // Debe contener al menos un número
        if (!preg_match('/[0-9]/', $password)) {
            return false;
        }
        
        // Debe contener al menos un carácter especial
        if (!preg_match('/[!@#$%^&*()\-_=+{};:,<.>]/', $password)) {
            return false;
        }
        
        return true;
    }
    
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function getCSRFTokenField() {
        return '<input type="hidden" name="csrf_token" value="' . self::generateCSRFToken() . '">';
    }
} 