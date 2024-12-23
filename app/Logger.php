<?php
namespace app;
class Logger {
    private $logDir;
    private $maxSize = 5242880; // 5MB
    private $maxFiles = 5;
    
    public function __construct() {
        $this->logDir = APP . '/logs';
        $this->ensureLogDirectory();
    }
    
    private function ensureLogDirectory() {
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
    }
    
    public function log($message, $level = 'ERROR') {
        $logFile = $this->getLogFile();
        
        // Rotasi log jika terlalu besar
        if (file_exists($logFile) && filesize($logFile) > $this->maxSize) {
            $this->rotate();
        }
        
        $logMessage = $this->formatMessage($message, $level);
        return file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    private function formatMessage($message, $level) {
        return sprintf(
            "[%s][%s][%s] %s\n",
            date('Y-m-d H:i:s'),
            $level,
            $_SERVER['REMOTE_ADDR'] ?? 'CLI',
            $message
        );
    }
    
    private function getLogFile() {
        return $this->logDir . '/error-' . date('Y-m-d') . '.log';
    }
    
    private function rotate() {
        for ($i = $this->maxFiles; $i > 0; $i--) {
            $old = $this->getLogFile() . '.' . ($i - 1);
            $new = $this->getLogFile() . '.' . $i;
            
            if (file_exists($old)) {
                rename($old, $new);
            }
        }
        
        rename($this->getLogFile(), $this->getLogFile() . '.1');
    }
} 