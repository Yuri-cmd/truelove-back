<?php

namespace App\Services;

class EncryptionService
{
    private $key;
    private $cipher = 'AES-256-CBC';
    
    public function __construct()
    {
        $this->key = config('app.encryption_key');
        
        if (empty($this->key)) {
            throw new \Exception('Encryption key not configured');
        }
        
        // Genera una clave consistente usando SHA-256
        $this->key = hash('sha256', $this->key, true);
    }
    
    public function encrypt($data)
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->cipher));
        
        $encrypted = openssl_encrypt(
            $data,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        // Combina IV y datos cifrados
        return base64_encode($iv . $encrypted);
    }
    
    public function decrypt($encryptedData)
    {
        $decoded = base64_decode($encryptedData);
        
        // Extraer IV y texto cifrado
        $ivLength = openssl_cipher_iv_length($this->cipher);
        $iv = substr($decoded, 0, $ivLength);
        $ciphertext = substr($decoded, $ivLength);
        
        $decrypted = openssl_decrypt(
            $ciphertext,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        if ($decrypted === false) {
            throw new \Exception('Decryption failed');
        }
        
        return $decrypted;
    }
}

