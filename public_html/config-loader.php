<?php
/**
 * Central Configuration Loader
 * Loads and validates environment variables for all components
 * 
 * Security: Single point of configuration management
 * Created: 2025-08-29
 */

class ConfigLoader {
    private static $instance = null;
    private $config = [];
    private $envFile;
    
    private function __construct() {
        $this->envFile = '/var/www/lightbot.rentri360.it/.env';
        $this->loadEnvironment();
        $this->validateConfiguration();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new ConfigLoader();
        }
        return self::$instance;
    }
    
    private function loadEnvironment() {
        if (!file_exists($this->envFile)) {
            $this->fatalError("Environment file not found: {$this->envFile}");
        }
        
        $lines = file($this->envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) continue;
            
            // Skip lines without =
            if (strpos($line, '=') === false) continue;
            
            // Parse key=value
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }
            
            $this->config[$key] = $value;
            
            // Also set as environment variable for compatibility
            putenv("$key=$value");
        }
    }
    
    private function validateConfiguration() {
        $requiredKeys = [
            'ADMIN_SECRET',
            'RATE_LIMIT_SECRET',
            'AI_API_URL',
            'AI_AGENT_ID'
        ];
        
        $missingKeys = [];
        foreach ($requiredKeys as $key) {
            if (empty($this->config[$key])) {
                $missingKeys[] = $key;
            }
        }
        
        if (!empty($missingKeys)) {
            error_log("WARNING: Missing configuration keys: " . implode(', ', $missingKeys));
        }
        
        // Check for pending configurations
        $pendingKeys = [];
        foreach ($this->config as $key => $value) {
            if (strpos($value, 'PENDING') !== false) {
                $pendingKeys[] = $key;
            }
        }
        
        if (!empty($pendingKeys)) {
            error_log("INFO: Pending configuration keys: " . implode(', ', $pendingKeys));
        }
    }
    
    public function get($key, $default = null) {
        return $this->config[$key] ?? $default;
    }
    
    public function getAll() {
        return $this->config;
    }
    
    public function isProduction() {
        return $this->get('DEBUG_MODE', 'false') === 'false';
    }
    
    public function isDevelopment() {
        return !$this->isProduction();
    }
    
    public function getSecureHash($data) {
        return hash_hmac('sha256', $data, $this->get('ADMIN_SECRET', ''));
    }
    
    public function getRateLimitHash($data) {
        return hash_hmac('sha256', $data, $this->get('RATE_LIMIT_SECRET', ''));
    }
    
    private function fatalError($message) {
        error_log("FATAL: $message");
        http_response_code(500);
        die('Configuration error. Please contact administrator.');
    }
    
    // Helper method for checking if service is ready
    public function isServiceReady() {
        $openaiKey = $this->get('OPENAI_API_KEY', '');
        $telegramToken = $this->get('TELEGRAM_BOT_TOKEN', '');
        $aiApiKey = $this->get('AI_API_KEY', '');
        
        return !empty($openaiKey) && 
               $openaiKey !== 'PENDING_REGENERATION_REQUIRED' &&
               !empty($telegramToken) && 
               $telegramToken !== 'PENDING_REGENERATION_REQUIRED' &&
               !empty($aiApiKey) && 
               $aiApiKey !== 'PENDING_CHECK_IF_VALID';
    }
    
    public function getServiceStatus() {
        return [
            'openai' => $this->get('OPENAI_API_KEY') !== 'PENDING_REGENERATION_REQUIRED',
            'telegram' => $this->get('TELEGRAM_BOT_TOKEN') !== 'PENDING_REGENERATION_REQUIRED',
            'ai_agent' => $this->get('AI_API_KEY') !== 'PENDING_CHECK_IF_VALID',
            'ready' => $this->isServiceReady()
        ];
    }
}

// Auto-initialize for backward compatibility
$config = ConfigLoader::getInstance();

?>