<?php
/**
 * Centralized Logging System for Lightbot
 * Features: Multi-level logging, performance tracking, error aggregation
 */

class LightbotLogger {
    private static $instance = null;
    private $logPath;
    private $enabledLevels = ['ERROR', 'WARNING', 'INFO', 'DEBUG'];
    private $performanceThresholds = [
        'api_call' => 1000, // 1 second
        'db_query' => 100,  // 100ms
        'rate_limit' => 50  // 50ms
    ];
    
    private function __construct() {
        require_once __DIR__ . '/config-loader.php';
        $config = ConfigLoader::getInstance();
        
        $this->logPath = $config->get('LOG_PATH', '/var/www/lightbot.rentri360.it/logs');
        
        // Create log directories if they don't exist
        $this->ensureLogDirectories();
        
        // Set log levels based on environment
        $debugMode = $config->get('DEBUG_MODE', 'false') === 'true';
        if (!$debugMode) {
            $this->enabledLevels = ['ERROR', 'WARNING', 'INFO'];
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new LightbotLogger();
        }
        return self::$instance;
    }
    
    private function ensureLogDirectories() {
        $directories = [
            $this->logPath,
            $this->logPath . '/api',
            $this->logPath . '/database',
            $this->logPath . '/telegram',
            $this->logPath . '/security',
            $this->logPath . '/performance'
        ];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
                chown($dir, 'www-data');
                chgrp($dir, 'www-data');
            }
        }
    }
    
    /**
     * Log general messages with context
     */
    public function log($level, $message, $context = []) {
        if (!in_array($level, $this->enabledLevels)) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? json_encode($context) : '';
        $logEntry = "[{$timestamp}] [{$level}] {$message}";
        
        if ($contextStr) {
            $logEntry .= " | Context: {$contextStr}";
        }
        
        $logEntry .= "\n";
        
        // Write to main log and category-specific log
        file_put_contents($this->logPath . '/lightbot.log', $logEntry, FILE_APPEND | LOCK_EX);
        
        // Write to specific category logs
        $this->writeToSpecificLog($level, $logEntry, $context);
        
        // Send critical errors to system log
        if ($level === 'ERROR') {
            error_log("LIGHTBOT ERROR: {$message}");
        }
    }
    
    private function writeToSpecificLog($level, $logEntry, $context) {
        $category = $context['category'] ?? 'general';
        
        $categoryMap = [
            'api' => 'api',
            'database' => 'database',
            'telegram' => 'telegram',
            'rate_limit' => 'security',
            'authentication' => 'security',
            'performance' => 'performance'
        ];
        
        $logDir = $categoryMap[$category] ?? 'general';
        $logFile = $this->logPath . "/{$logDir}/" . date('Y-m-d') . ".log";
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Performance tracking with automatic warnings
     */
    public function trackPerformance($operation, $startTime, $context = []) {
        $duration = (microtime(true) - $startTime) * 1000; // ms
        $threshold = $this->performanceThresholds[$operation] ?? 500;
        
        $level = $duration > $threshold ? 'WARNING' : 'DEBUG';
        $message = "Performance: {$operation} took {$duration}ms";
        
        $perfContext = array_merge($context, [
            'category' => 'performance',
            'operation' => $operation,
            'duration_ms' => round($duration, 2),
            'threshold_ms' => $threshold,
            'is_slow' => $duration > $threshold
        ]);
        
        $this->log($level, $message, $perfContext);
        
        return $duration;
    }
    
    /**
     * API call logging with request/response tracking
     */
    public function logApiCall($method, $endpoint, $responseTime, $statusCode, $context = []) {
        $level = $statusCode >= 400 ? 'ERROR' : 'INFO';
        $message = "API {$method} {$endpoint} - {$statusCode} ({$responseTime}ms)";
        
        $apiContext = array_merge($context, [
            'category' => 'api',
            'method' => $method,
            'endpoint' => $endpoint,
            'status_code' => $statusCode,
            'response_time_ms' => $responseTime
        ]);
        
        $this->log($level, $message, $apiContext);
    }
    
    /**
     * Database operation logging
     */
    public function logDbOperation($operation, $table, $duration, $rowsAffected = null, $context = []) {
        $level = $duration > 100 ? 'WARNING' : 'DEBUG';
        $message = "DB {$operation} on {$table} - {$duration}ms";
        
        if ($rowsAffected !== null) {
            $message .= " ({$rowsAffected} rows)";
        }
        
        $dbContext = array_merge($context, [
            'category' => 'database',
            'operation' => $operation,
            'table' => $table,
            'duration_ms' => $duration,
            'rows_affected' => $rowsAffected
        ]);
        
        $this->log($level, $message, $dbContext);
    }
    
    /**
     * Rate limiting events
     */
    public function logRateLimit($userId, $action, $isBlocked, $currentCount, $maxCount, $context = []) {
        $level = $isBlocked ? 'WARNING' : 'INFO';
        $userIdShort = substr($userId, 0, 20) . '...';
        $message = "Rate limit {$action}: User {$userIdShort} - {$currentCount}/{$maxCount}";
        
        $rateLimitContext = array_merge($context, [
            'category' => 'rate_limit',
            'user_id_hash' => substr($userId, 0, 20),
            'action' => $action,
            'is_blocked' => $isBlocked,
            'current_count' => $currentCount,
            'max_count' => $maxCount
        ]);
        
        $this->log($level, $message, $rateLimitContext);
    }
    
    /**
     * Security events logging
     */
    public function logSecurityEvent($event, $severity, $details, $context = []) {
        $level = $severity === 'high' ? 'ERROR' : 'WARNING';
        $message = "Security event: {$event} - {$details}";
        
        $secContext = array_merge($context, [
            'category' => 'authentication',
            'event' => $event,
            'severity' => $severity,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
        $this->log($level, $message, $secContext);
    }
    
    /**
     * Get log statistics for monitoring
     */
    public function getLogStats($hours = 24) {
        $stats = [
            'total_entries' => 0,
            'by_level' => ['ERROR' => 0, 'WARNING' => 0, 'INFO' => 0, 'DEBUG' => 0],
            'by_category' => [],
            'recent_errors' => []
        ];
        
        $logFile = $this->logPath . '/lightbot.log';
        if (!file_exists($logFile)) {
            return $stats;
        }
        
        $lines = file($logFile, FILE_IGNORE_NEW_LINES);
        $cutoffTime = time() - ($hours * 3600);
        
        foreach ($lines as $line) {
            if (preg_match('/\[([\d\-\s:]+)\]\s+\[(\w+)\]/', $line, $matches)) {
                $logTime = strtotime($matches[1]);
                $level = $matches[2];
                
                if ($logTime >= $cutoffTime) {
                    $stats['total_entries']++;
                    $stats['by_level'][$level]++;
                    
                    if ($level === 'ERROR' && count($stats['recent_errors']) < 10) {
                        $stats['recent_errors'][] = $line;
                    }
                }
            }
        }
        
        return $stats;
    }
    
    /**
     * Clean old log files (maintenance)
     */
    public function cleanOldLogs($daysToKeep = 30) {
        $cutoffTime = time() - ($daysToKeep * 24 * 3600);
        $cleaned = 0;
        
        $logDirs = ['api', 'database', 'telegram', 'security', 'performance'];
        
        foreach ($logDirs as $dir) {
            $dirPath = $this->logPath . '/' . $dir;
            if (!is_dir($dirPath)) continue;
            
            foreach (glob($dirPath . '/*.log') as $logFile) {
                if (filemtime($logFile) < $cutoffTime) {
                    unlink($logFile);
                    $cleaned++;
                }
            }
        }
        
        $this->log('INFO', "Log cleanup completed", [
            'category' => 'performance',
            'files_cleaned' => $cleaned,
            'days_kept' => $daysToKeep
        ]);
        
        return $cleaned;
    }
}

// Convenience functions
function lightbot_log($level, $message, $context = []) {
    LightbotLogger::getInstance()->log($level, $message, $context);
}

function lightbot_track_performance($operation, $startTime, $context = []) {
    return LightbotLogger::getInstance()->trackPerformance($operation, $startTime, $context);
}

function lightbot_log_api($method, $endpoint, $responseTime, $statusCode, $context = []) {
    LightbotLogger::getInstance()->logApiCall($method, $endpoint, $responseTime, $statusCode, $context);
}

function lightbot_log_db($operation, $table, $duration, $rowsAffected = null, $context = []) {
    LightbotLogger::getInstance()->logDbOperation($operation, $table, $duration, $rowsAffected, $context);
}

function lightbot_log_rate_limit($userId, $action, $isBlocked, $currentCount, $maxCount, $context = []) {
    LightbotLogger::getInstance()->logRateLimit($userId, $action, $isBlocked, $currentCount, $maxCount, $context);
}

function lightbot_log_security($event, $severity, $details, $context = []) {
    LightbotLogger::getInstance()->logSecurityEvent($event, $severity, $details, $context);
}

?>