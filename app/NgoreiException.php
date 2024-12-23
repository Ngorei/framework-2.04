<?php
namespace app;

class NgoreiException extends \Exception
{
    private $errorCode;
    private $errorContext;
    
    public function __construct(string $message, int $code = 0, array $context = [])
    {
        $this->errorCode = $code;
        $this->errorContext = $context;
        parent::__construct($message, $code);
    }
    
    public function getErrorContext(): array
    {
        return $this->errorContext;
    }
    
    public function getDetailedMessage(): string
    {
        return sprintf(
            "Error [%d]: %s\nContext: %s",
            $this->errorCode,
            $this->getMessage(),
            json_encode($this->errorContext)
        );
    }
} 