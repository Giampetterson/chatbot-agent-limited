<?php
/**
 * System Metrics Collection for Lightbot
 * Features: Performance metrics, resource usage, business metrics
 */

require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/config-loader.php';
require_once __DIR__ . '/database.php';

class LightbotMetricsCollector {
    private static $instance = null;
    private $config;
    private $logger;
    private $db;
    
    private function __construct() {
        $this->config = ConfigLoader::getInstance();
        $this->logger = LightbotLogger::getInstance();
        $this->db = DatabaseManager::getInstance();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new LightbotMetricsCollector();
        }
        return self::$instance;
    }
    
    /**
     * Collect all system metrics
     */
    public function collectAllMetrics() {
        $startTime = microtime(true);
        
        $metrics = [
            'timestamp' => time(),
            'formatted_time' => date('Y-m-d H:i:s'),
            'system' => $this->collectSystemMetrics(),
            'application' => $this->collectApplicationMetrics(),
            'business' => $this->collectBusinessMetrics(),
            'performance' => $this->collectPerformanceMetrics(),
            'health' => $this->collectHealthMetrics()
        ];
        
        // Store metrics
        $this->storeMetrics($metrics);
        
        // Track collection performance
        $collectionTime = lightbot_track_performance('metrics_collection', $startTime);
        
        $this->logger->log('DEBUG', 'Metrics collection completed', [
            'category' => 'performance',
            'collection_time_ms' => round($collectionTime, 2),
            'metrics_count' => $this->countMetrics($metrics)
        ]);
        
        return $metrics;
    }
    
    /**
     * Collect system resource metrics
     */
    private function collectSystemMetrics() {
        $metrics = [];
        
        // CPU usage (load average)
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $metrics['cpu_load_1min'] = $load[0];
            $metrics['cpu_load_5min'] = $load[1];
            $metrics['cpu_load_15min'] = $load[2];
        }
        
        // Memory usage
        $metrics['memory_usage'] = $this->getMemoryUsage();
        
        // Disk usage
        $metrics['disk_usage'] = $this->getDiskUsage();
        
        // Process count
        $metrics['process_count'] = $this->getProcessCount();
        
        // Network connections
        $metrics['network_connections'] = $this->getNetworkConnections();
        
        return $metrics;
    }
    
    /**
     * Collect application-specific metrics
     */
    private function collectApplicationMetrics() {
        $metrics = [];
        
        // PHP metrics
        $metrics['php_version'] = PHP_VERSION;
        $metrics['php_memory_limit'] = ini_get('memory_limit');
        $metrics['php_max_execution_time'] = ini_get('max_execution_time');
        $metrics['php_memory_usage'] = memory_get_usage(true);
        $metrics['php_peak_memory'] = memory_get_peak_usage(true);
        
        // File system metrics
        $logPath = $this->config->get('LOG_PATH', '/var/www/lightbot.rentri360.it/logs');
        $metrics['log_directory_size'] = $this->getDirectorySize($logPath);
        $metrics['log_files_count'] = $this->countFiles($logPath, '*.log');
        
        // Database metrics (if available)
        try {
            $dbStats = $this->db->getSystemStats();
            $metrics['database'] = [
                'total_users' => $dbStats['users']['total_users'],
                'blocked_users' => $dbStats['users']['blocked_users'],
                'users_at_limit' => $dbStats['users']['users_at_limit'],
                'active_today' => $dbStats['users']['active_today'],
                'avg_messages_per_user' => $dbStats['users']['avg_messages_per_user']
            ];
        } catch (Exception $e) {
            $metrics['database'] = ['error' => 'Database unavailable'];
        }
        
        return $metrics;
    }
    
    /**
     * Collect business/usage metrics
     */
    private function collectBusinessMetrics() {
        $metrics = [];
        
        try {
            // API usage metrics from logs
            $apiMetrics = $this->getApiUsageMetrics();
            $metrics['api'] = $apiMetrics;
            
            // Rate limiting metrics
            $rateLimitMetrics = $this->getRateLimitMetrics();
            $metrics['rate_limiting'] = $rateLimitMetrics;
            
            // Error metrics
            $errorTracker = require_once __DIR__ . '/error-tracker.php';
            $errorSummary = lightbot_get_error_summary(24);
            $metrics['errors'] = [
                'total_errors_24h' => $errorSummary['total_errors'],
                'error_rate_per_hour' => $errorSummary['error_rate'],
                'by_category' => $errorSummary['by_category'],
                'by_severity' => $errorSummary['by_severity']
            ];
            
        } catch (Exception $e) {
            $metrics['collection_error'] = $e->getMessage();
        }
        
        return $metrics;
    }
    
    /**
     * Collect performance metrics
     */
    private function collectPerformanceMetrics() {
        $metrics = [];
        
        // Test database performance
        $dbStart = microtime(true);
        try {
            $testResult = $this->db->testConnection();
            $dbTime = (microtime(true) - $dbStart) * 1000;
            
            $metrics['database_response_time_ms'] = round($dbTime, 2);
            $metrics['database_status'] = $testResult['success'] ? 'healthy' : 'error';
        } catch (Exception $e) {
            $metrics['database_response_time_ms'] = -1;
            $metrics['database_status'] = 'error';
        }
        
        // File system performance
        $fsStart = microtime(true);
        $testFile = sys_get_temp_dir() . '/lightbot_fs_test_' . time();
        file_put_contents($testFile, 'test');
        $fsTime = (microtime(true) - $fsStart) * 1000;
        unlink($testFile);
        
        $metrics['filesystem_write_time_ms'] = round($fsTime, 2);
        
        // Log file sizes (potential performance impact)
        $logPath = $this->config->get('LOG_PATH', '/var/www/lightbot.rentri360.it/logs');
        $metrics['largest_log_file_mb'] = $this->getLargestLogFileSize($logPath);
        
        return $metrics;
    }
    
    /**
     * Collect health check metrics
     */
    private function collectHealthMetrics() {
        // Get health status from error tracker
        $health = lightbot_get_health_status();
        
        return [
            'overall_status' => $health['status'],
            'issues_count' => count($health['issues']),
            'issues' => $health['issues']
        ];
    }
    
    /**
     * Helper methods for system metrics
     */
    private function getMemoryUsage() {
        $meminfo = @file_get_contents('/proc/meminfo');
        if (!$meminfo) return null;
        
        preg_match('/MemTotal:\s+(\d+)\s+kB/', $meminfo, $total);
        preg_match('/MemAvailable:\s+(\d+)\s+kB/', $meminfo, $available);
        
        if (!$total || !$available) return null;
        
        $totalKb = $total[1];
        $availableKb = $available[1];
        $usedKb = $totalKb - $availableKb;
        
        return [
            'total_mb' => round($totalKb / 1024, 2),
            'used_mb' => round($usedKb / 1024, 2),
            'available_mb' => round($availableKb / 1024, 2),
            'usage_percent' => round(($usedKb / $totalKb) * 100, 2)
        ];
    }
    
    private function getDiskUsage() {
        $rootPath = '/';
        $totalBytes = disk_total_space($rootPath);
        $freeBytes = disk_free_space($rootPath);
        $usedBytes = $totalBytes - $freeBytes;
        
        return [
            'total_gb' => round($totalBytes / (1024**3), 2),
            'used_gb' => round($usedBytes / (1024**3), 2),
            'free_gb' => round($freeBytes / (1024**3), 2),
            'usage_percent' => round(($usedBytes / $totalBytes) * 100, 2)
        ];
    }
    
    private function getProcessCount() {
        $output = shell_exec('ps aux | wc -l');
        return intval(trim($output)) - 1; // Subtract header line
    }
    
    private function getNetworkConnections() {
        $output = shell_exec('ss -tuln | wc -l');
        return intval(trim($output)) - 1; // Subtract header line
    }
    
    private function getDirectorySize($path) {
        if (!is_dir($path)) return 0;
        
        $size = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        
        return round($size / (1024 * 1024), 2); // MB
    }
    
    private function countFiles($path, $pattern) {
        if (!is_dir($path)) return 0;
        return count(glob($path . '/' . $pattern));
    }
    
    private function getLargestLogFileSize($logPath) {
        $maxSize = 0;
        
        if (!is_dir($logPath)) return 0;
        
        foreach (glob($logPath . '/**/*.log') as $file) {
            $size = filesize($file);
            if ($size > $maxSize) {
                $maxSize = $size;
            }
        }
        
        return round($maxSize / (1024 * 1024), 2); // MB
    }
    
    /**
     * Business metrics helpers
     */
    private function getApiUsageMetrics() {
        $logFile = $this->config->get('LOG_PATH') . '/api/' . date('Y-m-d') . '.log';
        
        if (!file_exists($logFile)) {
            return ['total_requests' => 0, 'by_endpoint' => [], 'avg_response_time' => 0];
        }
        
        $lines = file($logFile, FILE_IGNORE_NEW_LINES);
        $metrics = ['total_requests' => 0, 'by_endpoint' => [], 'response_times' => []];
        
        foreach ($lines as $line) {
            if (preg_match('/API (\w+) ([^\s]+) - (\d+) \(([0-9.]+)ms\)/', $line, $matches)) {
                $method = $matches[1];
                $endpoint = $matches[2];
                $statusCode = $matches[3];
                $responseTime = floatval($matches[4]);
                
                $metrics['total_requests']++;
                $key = "$method $endpoint";
                $metrics['by_endpoint'][$key] = ($metrics['by_endpoint'][$key] ?? 0) + 1;
                $metrics['response_times'][] = $responseTime;
            }
        }
        
        $metrics['avg_response_time'] = !empty($metrics['response_times']) 
            ? round(array_sum($metrics['response_times']) / count($metrics['response_times']), 2) 
            : 0;
            
        unset($metrics['response_times']); // Remove raw data to save space
        
        return $metrics;
    }
    
    private function getRateLimitMetrics() {
        $logFile = $this->config->get('LOG_PATH') . '/security/' . date('Y-m-d') . '.log';
        
        if (!file_exists($logFile)) {
            return ['total_checks' => 0, 'blocked_attempts' => 0, 'block_rate' => 0];
        }
        
        $lines = file($logFile, FILE_IGNORE_NEW_LINES);
        $totalChecks = 0;
        $blockedAttempts = 0;
        
        foreach ($lines as $line) {
            if (strpos($line, 'Rate limit') !== false) {
                $totalChecks++;
                if (strpos($line, 'is_blocked":true') !== false || strpos($line, 'exceeded') !== false) {
                    $blockedAttempts++;
                }
            }
        }
        
        return [
            'total_checks' => $totalChecks,
            'blocked_attempts' => $blockedAttempts,
            'block_rate' => $totalChecks > 0 ? round(($blockedAttempts / $totalChecks) * 100, 2) : 0
        ];
    }
    
    /**
     * Store metrics data
     */
    private function storeMetrics($metrics) {
        $metricsFile = $this->config->get('LOG_PATH') . '/metrics_' . date('Y-m-d') . '.jsonl';
        
        $entry = json_encode($metrics, JSON_UNESCAPED_SLASHES) . "\n";
        file_put_contents($metricsFile, $entry, FILE_APPEND | LOCK_EX);
    }
    
    private function countMetrics($metrics, $prefix = '') {
        $count = 0;
        
        foreach ($metrics as $key => $value) {
            if (is_array($value)) {
                $count += $this->countMetrics($value, $prefix . $key . '.');
            } else {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Get historical metrics data
     */
    public function getHistoricalMetrics($days = 7) {
        $data = [];
        
        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $file = $this->config->get('LOG_PATH') . "/metrics_{$date}.jsonl";
            
            if (file_exists($file)) {
                $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $metric = json_decode($line, true);
                    if ($metric) {
                        $data[] = $metric;
                    }
                }
            }
        }
        
        // Sort by timestamp
        usort($data, function($a, $b) {
            return $a['timestamp'] - $b['timestamp'];
        });
        
        return $data;
    }
    
    /**
     * Generate metrics summary for dashboard
     */
    public function getMetricsSummary() {
        $currentMetrics = $this->collectAllMetrics();
        $historicalData = $this->getHistoricalMetrics(1); // Last 24 hours
        
        $summary = [
            'current' => $currentMetrics,
            'trends' => $this->calculateTrends($historicalData),
            'alerts' => $this->checkMetricAlerts($currentMetrics)
        ];
        
        return $summary;
    }
    
    private function calculateTrends($historicalData) {
        if (count($historicalData) < 2) {
            return ['insufficient_data' => true];
        }
        
        $latest = end($historicalData);
        $previous = $historicalData[count($historicalData) - 2];
        
        $trends = [];
        
        // Memory usage trend
        if (isset($latest['system']['memory_usage']['usage_percent']) && 
            isset($previous['system']['memory_usage']['usage_percent'])) {
            $trends['memory_usage'] = $latest['system']['memory_usage']['usage_percent'] - 
                                     $previous['system']['memory_usage']['usage_percent'];
        }
        
        // Error rate trend
        if (isset($latest['business']['errors']['error_rate_per_hour']) &&
            isset($previous['business']['errors']['error_rate_per_hour'])) {
            $trends['error_rate'] = $latest['business']['errors']['error_rate_per_hour'] - 
                                   $previous['business']['errors']['error_rate_per_hour'];
        }
        
        // API requests trend
        if (isset($latest['business']['api']['total_requests']) &&
            isset($previous['business']['api']['total_requests'])) {
            $trends['api_requests'] = $latest['business']['api']['total_requests'] - 
                                     $previous['business']['api']['total_requests'];
        }
        
        return $trends;
    }
    
    private function checkMetricAlerts($metrics) {
        $alerts = [];
        
        // High memory usage
        if (isset($metrics['system']['memory_usage']['usage_percent']) &&
            $metrics['system']['memory_usage']['usage_percent'] > 85) {
            $alerts[] = [
                'type' => 'high_memory_usage',
                'severity' => 'warning',
                'message' => 'Memory usage is high: ' . $metrics['system']['memory_usage']['usage_percent'] . '%'
            ];
        }
        
        // High disk usage
        if (isset($metrics['system']['disk_usage']['usage_percent']) &&
            $metrics['system']['disk_usage']['usage_percent'] > 90) {
            $alerts[] = [
                'type' => 'high_disk_usage',
                'severity' => 'critical',
                'message' => 'Disk usage is critical: ' . $metrics['system']['disk_usage']['usage_percent'] . '%'
            ];
        }
        
        // High error rate
        if (isset($metrics['business']['errors']['error_rate_per_hour']) &&
            $metrics['business']['errors']['error_rate_per_hour'] > 10) {
            $alerts[] = [
                'type' => 'high_error_rate',
                'severity' => 'warning',
                'message' => 'Error rate is elevated: ' . $metrics['business']['errors']['error_rate_per_hour'] . ' errors/hour'
            ];
        }
        
        // Unhealthy status
        if (isset($metrics['health']['overall_status']) &&
            $metrics['health']['overall_status'] === 'critical') {
            $alerts[] = [
                'type' => 'system_unhealthy',
                'severity' => 'critical',
                'message' => 'System health is critical with ' . $metrics['health']['issues_count'] . ' issues'
            ];
        }
        
        return $alerts;
    }
    
    /**
     * Cleanup old metrics data
     */
    public function cleanOldMetrics($daysToKeep = 30) {
        $logPath = $this->config->get('LOG_PATH');
        $cutoffDate = date('Y-m-d', strtotime("-{$daysToKeep} days"));
        
        $cleaned = 0;
        
        foreach (glob($logPath . '/metrics_*.jsonl') as $file) {
            if (basename($file) < "metrics_{$cutoffDate}.jsonl") {
                unlink($file);
                $cleaned++;
            }
        }
        
        $this->logger->log('INFO', "Metrics cleanup completed", [
            'category' => 'maintenance',
            'files_cleaned' => $cleaned,
            'days_kept' => $daysToKeep
        ]);
        
        return $cleaned;
    }
}

// Convenience functions
function lightbot_collect_metrics() {
    return LightbotMetricsCollector::getInstance()->collectAllMetrics();
}

function lightbot_get_metrics_summary() {
    return LightbotMetricsCollector::getInstance()->getMetricsSummary();
}

function lightbot_get_historical_metrics($days = 7) {
    return LightbotMetricsCollector::getInstance()->getHistoricalMetrics($days);
}

?>