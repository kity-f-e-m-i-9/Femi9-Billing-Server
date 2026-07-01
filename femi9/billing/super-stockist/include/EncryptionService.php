<?php
/**
 * Password Encryption Service
 * Uses AES-256-CBC encryption for reversible password storage
 */

class EncryptionService {
    private $encryptionKey;
    private $cipher = "AES-256-CBC";
    
    public function __construct() {
        // Load encryption key from .env
        $this->encryptionKey = $_ENV['ENCRYPTION_KEY'] ?? '';
        
        if (empty($this->encryptionKey)) {
            throw new Exception('Encryption key not configured in .env');
        }
        
        // Key must be 32 bytes for AES-256
        $this->encryptionKey = hash('sha256', $this->encryptionKey, true);
    }
    
    /**
     * Encrypt password
     * 
     * @param string $password Plain text password
     * @return string Encrypted password (base64 encoded)
     */
    public function encrypt($password) {
        // Generate random IV (Initialization Vector)
        $ivLength = openssl_cipher_iv_length($this->cipher);
        $iv = openssl_random_pseudo_bytes($ivLength);
        
        // Encrypt the password
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
        
        // Combine IV and encrypted data, then base64 encode
        // Format: base64(iv + encrypted_data)
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * Decrypt password
     * 
     * @param string $encryptedPassword Encrypted password (base64 encoded)
     * @return string Plain text password
     */
    public function decrypt($encryptedPassword) {
        // Decode base64
        $data = base64_decode($encryptedPassword);
        
        if ($data === false) {
            throw new Exception('Invalid encrypted data');
        }
        
        // Extract IV and encrypted data
        $ivLength = openssl_cipher_iv_length($this->cipher);
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);
        
        // Decrypt
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
    
    /**
     * Validate password strength
     * - Minimum 8 characters
     * - At least one uppercase letter
     * - At least one lowercase letter
     * - At least one number
     * - At least one special character
     * 
     * @param string $password Password to validate
     * @return array ['valid' => true/false, 'message' => 'error message']
     */
    public function validatePasswordStrength($password) {
        $errors = [];
        
        // Check minimum length
        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long";
        }
        
        // Check for uppercase letter
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }
        
        // Check for lowercase letter
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }
        
        // Check for number
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }
        
        // Check for special character
        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};:\'",.<>?\/\\|`~]/', $password)) {
            $errors[] = "Password must contain at least one special character (!@#$%^&* etc.)";
        }
        
        // Check if password is default password
        if ($password === '12345678') {
            $errors[] = "Default password cannot be used";
        }
        
        if (empty($errors)) {
            return [
                'valid' => true,
                'message' => 'Password is strong'
            ];
        } else {
            return [
                'valid' => false,
                'message' => implode('. ', $errors)
            ];
        }
    }
}
?>