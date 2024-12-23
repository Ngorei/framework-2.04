<?php
namespace app;

class NgoreiValidator {
    private array $errors = [];
    
    public function validate(array $data, array $rules): bool {
        foreach ($rules as $field => $rule) {
            if (!isset($data[$field])) {
                $this->errors[$field] = "Field {$field} tidak ditemukan";
                continue;
            }
            $value = $data[$field];
            foreach ($rule as $validation => $param) {
                switch ($validation) {
                    case 'required':
                        if (empty($value)) {
                            $this->errors[$field] = "Field {$field} harus diisi";
                        }
                        break;
                        
                    case 'min':
                        if (strlen($value) < $param) {
                            $this->errors[$field] = "Field {$field} minimal {$param} karakter";
                        }
                        break;
                        
                    case 'max':
                        if (strlen($value) > $param) {
                            $this->errors[$field] = "Field {$field} maksimal {$param} karakter";
                        }
                        break;
                        
                    case 'email':
                        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $this->errors[$field] = "Format email tidak valid";
                        }
                        break;
                }
            }
        }
        
        return empty($this->errors);
    }
    
    public function getErrors(): array {
        return $this->errors;
    }
} 