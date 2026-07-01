<?php
/**
 * Shared Password Encryption Service
 */

class EncryptionService {
    private $encryptionKey;
    private $cipher = "AES-256-CBC";
    
    public function __construct() {
        $this->encryptionKey = $_ENV['ENCRYPTION_KEY'] ?? '';
        
        if (empty($this->encryptionKey)) {
            throw new Exception('Encryption key not configured');
        }
        
        $this->encryptionKey = hash('sha256', $this->encryptionKey, true);
    }
    
    public function encrypt($password) {
        $ivLength = openssl_cipher_iv_length($this->cipher);
        $iv = openssl_random_pseudo_bytes($ivLength);
        
        $encrypted = openssl_encrypt(
            $password,
            $this->cipher,
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        if ($encrypted === false) {
            throw new Exception('Encryption failed');
        }
        
        return base64_encode($iv . $encrypted);
    }
    
    public function decrypt($encryptedPassword) {
        $data = base64_decode($encryptedPassword);
        
        if ($data === false) {
            throw new Exception('Invalid encrypted data');
        }
        
        $ivLength = openssl_cipher_iv_length($this->cipher);
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);
        
        $decrypted = openssl_decrypt(
            $encrypted,
            $this->cipher,
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        if ($decrypted === false) {
            throw new Exception('Decryption failed');
        }
        
        return $decrypted;
    }
    
    public function validatePasswordStrength($password) {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long";
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }
        
        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?\/\\|`~]/', $password)) {
            $errors[] = "Password must contain at least one special character";
        }
        
        if ($password === '12345678') {
            $errors[] = "Default password cannot be used";
        }
        
        if (empty($errors)) {
            return ['valid' => true, 'message' => 'Password is strong'];
        } else {
            return ['valid' => false, 'message' => implode('. ', $errors)];
        }
    }
}
?>