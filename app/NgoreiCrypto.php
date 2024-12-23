<?php
namespace app;
use app\tatiye;
use Exception;

class NgoreiCrypto {
    protected $method = 'AES-256-CBC';
    protected $key;

    public function __construct($endpoint) {
        $SECRET_KEY=str_replace("-", "", $endpoint);
        $this->key = hash('sha256', $SECRET_KEY, true);
    }

    protected function validatePayload($payload) {
        if (!is_array($payload)) {
            return false;
        }

        // Memeriksa apakah payload memiliki minimal 1 parameter
        if (empty($payload)) {
            return false;
        }

        // Memastikan semua nilai parameter tidak kosong
        foreach ($payload as $key => $value) {
            if (empty($value) && $value !== '0') {
                return false;
            }
        }

        return true;
    }

    protected function generateSessionToken($userData) {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->method));
        unset($userData['password']);
        
        $encrypted = openssl_encrypt(
            json_encode(array_merge($userData, array('exp' => time() + (60 * 60 * 24 * 7)))),
            $this->method,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );
        return base64_encode($iv . $encrypted);
    }

    protected function decryptToken($encryptedToken) {
        try {
            $encryptedToken = urldecode($encryptedToken);
            $encryptedToken = base64_decode($encryptedToken);
            
            $ivSize = openssl_cipher_iv_length($this->method);
            $iv = substr($encryptedToken, 0, $ivSize);
            $ciphertext = substr($encryptedToken, $ivSize);
            
            $decrypted = openssl_decrypt(
                $ciphertext,
                $this->method,
                $this->key,
                OPENSSL_RAW_DATA,
                $iv
            );
            
            if ($decrypted === false) {
                return ['status' => 'error', 'message' => 'Dekripsi token gagal'];
            }
            
            return json_decode($decrypted, true);
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => 'Token tidak valid'];
        }
    }

    protected function createErrorResponse($message) {
        return [
            'status' => 'error',
            'message' => $message
        ];
    }

    protected function createSuccessResponse($data) {
        unset($data['password']);
        
        return [
            'status' => 'success',
            'token' => $this->generateSessionToken($data)
        ];
    }

    protected function validateCredentials($payload) {
        // Method ini akan di-override oleh class turunan
        return false;
    }

    public function authenticate($request) {
        try {
            if (!isset($request['token'])) {
                return $this->createErrorResponse('Token tidak ditemukan');
            }
            
            $decrypted = $this->decryptToken($request['token']);
            error_log('Decrypted data: ' . print_r($decrypted, true));
            
            if (isset($decrypted['status']) && $decrypted['status'] === 'error') {
                return $decrypted;
            }

            if (!isset($decrypted['payload']) || !$this->validatePayload($decrypted['payload'])) {
                return $this->createErrorResponse('Format data tidak valid');
            }

            $userData = $this->validateCredentials($decrypted['payload']);
            if ($userData) {
                return $this->createSuccessResponse($userData);
            }

            return $this->createErrorResponse('Kredensial tidak valid');
            
        } catch (Exception $e) {
            error_log('Authentication error: ' . $e->getMessage());
            return $this->createErrorResponse('Authentication failed: ' . $e->getMessage());
        }
    }
} 