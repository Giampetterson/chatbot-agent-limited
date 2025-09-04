<?php
/**
 * Error Tracking and Notification System for Lightbot
 * Features: Real-time error aggregation, alert thresholds, notification routing
 */

require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/config-loader.php';

class LightbotErrorTracker {
    private static $instance = null;
    private $config;
    private $logger;
    private $errorThresholds = [
        'api_errors' => 10,      // 10 errors per 5 minutes
        'db_errors' => 5,        // 5 DB errors per 5 minutes
        'rate_limit_violations' => 50,  // 50 violations per minute
        'security_events' => 3   // 3 security events per hour
    ];
    
    private function __construct() {
        $this->config = ConfigLoader::getInstance();
        $this->logger = LightbotLogger::getInstance();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new LightbotErrorTracker();
        }
        return self::$instance;
    }
    
    /**
     * Track and potentially alert on errors
     */
    public function trackError($category, $severity, $message, $context = []) {
        $errorId = $this->generateErrorId($category, $message);
        
        // Log the error
        $this->logger->log('ERROR', "Error tracked: {$category} - {$message}", array_merge($context, [
            'category' => 'error_tracking',
            'error_category' => $category,
            'severity' => $severity,
            'error_id' => $errorId
        ]));
        
        // Store error occurrence
        $this->storeErrorOccurrence($errorId, $category, $severity, $message, $context);
        
        // Check if we need to send alerts
        $this->checkAlertThresholds($category, $severity);
        
        return $errorId;
    }
    
    private function generateErrorId($category, $message) {
        // Create a stable ID for similar errors
        return substr(md5($category . '|' . $message), 0, 16);
    }
    
    private function storeErrorOccurrence($errorId, $category, $severity, $message, $context) {
        $errorFile = $this->getErrorStorageFile();
        
        $errorData = [
            'id' => $errorId,
            'category' => $category,
            'severity' => $severity,
            'message' => $message,
            'context' => $context,
            'timestamp' => time(),
            'formatted_time' => date('Y-m-d H:i:s')
        ];
        
        // Append to error file
        file_put_contents($errorFile, json_encode($errorData) . "\n", FILE_APPEND | LOCK_EX);
    }
    
    private function getErrorStorageFile() {
        $logPath = $this->config->get('LOG_PATH', '/var/www/lightbot.rentri360.it/logs');
        return $logPath . '/error_occurrences_' . date('Y-m-d') . '.jsonl';
    }
    
    /**
     * Check if error thresholds are exceeded and send alerts
     */
    private function checkAlertThresholds($category, $severity) {
        $now = time();
        $timeWindows = [
            'api_errors' => 300,      // 5 minutes
            'db_errors' => 300,       // 5 minutes
            'rate_limit_violations' => 60,   // 1 minute
            'security_events' => 3600        // 1 hour
        ];
        
        $window = $timeWindows[$category] ?? 300;
        $threshold = $this->errorThresholds[$category] ?? 5;
        
        $recentErrors = $this->getRecentErrorCount($category, $window);
        
        if ($recentErrors >= $threshold) {
            $this->sendAlert($category, $severity, $recentErrors, $threshold, $window);
        }
    }
    
    private function getRecentErrorCount($category, $timeWindow) {
        $errorFile = $this->getErrorStorageFile();
        
        if (!file_exists($errorFile)) {
            return 0;
        }
        
        $lines = file($errorFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $cutoffTime = time() - $timeWindow;
        $count = 0;
        
        foreach ($lines as $line) {
            $data = json_decode($line, true);
            if ($data && 
                $data['category'] === $category && 
                $data['timestamp'] >= $cutoffTime) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Send alert notifications
     */
    private function sendAlert($category, $severity, $errorCount, $threshold, $timeWindow) {
        $alertId = $this->generateAlertId($category, $severity);
        
        // Check if we already sent this alert recently (avoid spam)
        if ($this->isAlertRecentlySent($alertId, 1800)) { // 30 minutes cooldown
            return;
        }
        
        $message = $this->formatAlertMessage($category, $severity, $errorCount, $threshold, $timeWindow);
        
        // Log the alert
        $this->logger->log('ERROR', "ALERT TRIGGERED: {$category}", [
            'category' => 'alerting',
            'alert_id' => $alertId,
            'error_category' => $category,
            'severity' => $severity,
            'error_count' => $errorCount,
            'threshold' => $threshold,
            'time_window_seconds' => $timeWindow
        ]);
        
        // Send notifications
        $this->sendEmailAlert($message);
        $this->sendSystemNotification($message);
        
        // Record alert sent
        $this->recordAlertSent($alertId);
    }
    
    private function generateAlertId($category, $severity) {
        return md5($category . '|' . $severity . '|' . date('Y-m-d-H'));
    }
    
    private function isAlertRecentlySent($alertId, $cooldownSeconds) {
        $alertFile = $this->getAlertHistoryFile();
        
        if (!file_exists($alertFile)) {
            return false;
        }
        
        $lines = file($alertFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $cutoffTime = time() - $cooldownSeconds;
        
        foreach ($lines as $line) {
            $data = json_decode($line, true);
            if ($data && 
                $data['alert_id'] === $alertId && 
                $data['timestamp'] >= $cutoffTime) {
                return true;
            }
        }
        
        return false;
    }
    
    private function getAlertHistoryFile() {
        $logPath = $this->config->get('LOG_PATH', '/var/www/lightbot.rentri360.it/logs');
        return $logPath . '/alert_history.jsonl';
    }
    
    private function recordAlertSent($alertId) {
        $alertFile = $this->getAlertHistoryFile();
        
        $record = [
            'alert_id' => $alertId,
            'timestamp' => time(),
            'sent_at' => date('Y-m-d H:i:s')
        ];
        
        file_put_contents($alertFile, json_encode($record) . "\n", FILE_APPEND | LOCK_EX);
    }
    
    private function formatAlertMessage($category, $severity, $errorCount, $threshold, $timeWindow) {
        $timeDesc = $this->formatTimeWindow($timeWindow);
        
        return "🚨 LIGHTBOT ALERT 🚨\n\n" .
               "Category: {$category}\n" .
               "Severity: {$severity}\n" .
               "Error Count: {$errorCount} (threshold: {$threshold})\n" .
               "Time Window: {$timeDesc}\n" .
               "Server: " . gethostname() . "\n" .
               "Time: " . date('Y-m-d H:i:s') . "\n\n" .
               "Please investigate immediately.";
    }
    
    private function formatTimeWindow($seconds) {
        if ($seconds >= 3600) {
            return ($seconds / 3600) . ' hour(s)';
        } elseif ($seconds >= 60) {
            return ($seconds / 60) . ' minute(s)';
        } else {
            return $seconds . ' second(s)';
        }
    }
    
    /**
     * Send email alert (if configured)
     */
    private function sendEmailAlert($message) {
        $adminEmail = $this->config->get('ADMIN_EMAIL', '');
        
        if (!$adminEmail) {
            $this->logger->log('WARNING', 'Cannot send email alert: ADMIN_EMAIL not configured', [
                'category' => 'alerting'
            ]);
            return false;
        }
        
        $subject = '🚨 Lightbot Alert - ' . gethostname();
        $headers = 'From: lightbot@' . ($_SERVER['SERVER_NAME'] ?? 'localhost');
        
        $sent = mail($adminEmail, $subject, $message, $headers);
        
        if ($sent) {
            $this->logger->log('INFO', 'Alert email sent successfully', [
                'category' => 'alerting',
                'recipient' => $adminEmail
            ]);
        } else {
            $this->logger->log('ERROR', 'Failed to send alert email', [
                'category' => 'alerting',
                'recipient' => $adminEmail
            ]);
        }
        
        return $sent;
    }
    
    /**
     * Send system notification (syslog)
     */
    private function sendSystemNotification($message) {
        // Write to syslog
        syslog(LOG_CRIT, "LIGHTBOT ALERT: " . str_replace("\n", " | ", $message));
        
        // Also write to dedicated alert file
        $alertFile = $this->config->get('LOG_PATH', '/var/www/lightbot.rentri360.it/logs') . '/critical_alerts.log';
        
        $logEntry = "[" . date('Y-m-d H:i:s') . "] CRITICAL ALERT\n" . $message . "\n\n";
        file_put_contents($alertFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        $this->logger->log('INFO', 'System notification sent', [
            'category' => 'alerting'
        ]);
    }
    
    /**
     * Get error summary for monitoring dashboard
     */
    public function getErrorSummary($hours = 24) {
        $summary = [
            'total_errors' => 0,
            'by_category' => [],
            'by_severity' => ['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0],
            'recent_errors' => [],
            'error_rate' => 0
        ];
        
        $errorFile = $this->getErrorStorageFile();
        
        if (!file_exists($errorFile)) {
            return $summary;
        }
        
        $lines = file($errorFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $cutoffTime = time() - ($hours * 3600);
        
        foreach ($lines as $line) {
            $data = json_decode($line, true);
            if (!$data || $data['timestamp'] < $cutoffTime) {
                continue;
            }
            
            $summary['total_errors']++;
            
            // Count by category
            $category = $data['category'];
            $summary['by_category'][$category] = ($summary['by_category'][$category] ?? 0) + 1;
            
            // Count by severity
            $severity = $data['severity'];
            $summary['by_severity'][$severity] = ($summary['by_severity'][$severity] ?? 0) + 1;
            
            // Recent errors for dashboard
            if (count($summary['recent_errors']) < 10) {
                $summary['recent_errors'][] = [
                    'category' => $category,
                    'severity' => $severity,
                    'message' => substr($data['message'], 0, 100),
                    'time' => $data['formatted_time']
                ];
            }
        }
        
        // Calculate error rate (errors per hour)
        $summary['error_rate'] = round($summary['total_errors'] / $hours, 2);
        
        return $summary;
    }
    
    /**
     * Health check - returns system health status
     */
    public function getHealthStatus() {
        $summary = $this->getErrorSummary(1); // Last hour
        
        $status = 'healthy';
        $issues = [];
        
        // Check error rates
        if ($summary['error_rate'] > 10) {
            $status = 'critical';
            $issues[] = 'High error rate: ' . $summary['error_rate'] . ' errors/hour';
        } elseif ($summary['error_rate'] > 5) {
            $status = 'warning';
            $issues[] = 'Elevated error rate: ' . $summary['error_rate'] . ' errors/hour';
        }
        
        // Check critical errors
        if (($summary['by_severity']['critical'] ?? 0) > 0) {
            $status = 'critical';
            $issues[] = $summary['by_severity']['critical'] . ' critical errors in last hour';
        }
        
        // Check high severity errors
        if (($summary['by_severity']['high'] ?? 0) > 5) {
            if ($status !== 'critical') $status = 'warning';
            $issues[] = $summary['by_severity']['high'] . ' high severity errors in last hour';
        }
        
        return [
            'status' => $status,
            'issues' => $issues,
            'error_summary' => $summary,
            'last_check' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Clean old error data (maintenance)
     */
    public function cleanOldErrors($daysToKeep = 7) {
        $logPath = $this->config->get('LOG_PATH', '/var/www/lightbot.rentri360.it/logs');
        $cutoffDate = date('Y-m-d', strtotime("-{$daysToKeep} days"));
        
        $cleaned = 0;
        
        // Clean error occurrence files
        foreach (glob($logPath . '/error_occurrences_*.jsonl') as $file) {
            if (basename($file) < "error_occurrences_{$cutoffDate}.jsonl") {
                unlink($file);
                $cleaned++;
            }
        }
        
        // Clean alert history (keep more of this)
        $alertFile = $logPath . '/alert_history.jsonl';
        if (file_exists($alertFile)) {
            $this->cleanJsonlFile($alertFile, 30 * 24 * 3600); // 30 days
        }
        
        $this->logger->log('INFO', "Error tracking cleanup completed", [
            'category' => 'maintenance',
            'files_cleaned' => $cleaned,
            'days_kept' => $daysToKeep
        ]);
        
        return $cleaned;
    }
    
    private function cleanJsonlFile($file, $maxAge) {
        if (!file_exists($file)) return;
        
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $cutoffTime = time() - $maxAge;
        $kept = [];
        
        foreach ($lines as $line) {
            $data = json_decode($line, true);
            if ($data && $data['timestamp'] >= $cutoffTime) {
                $kept[] = $line;
            }
        }
        
        file_put_contents($file, implode("\n", $kept) . "\n", LOCK_EX);
    }
}

// Convenience functions
function lightbot_track_error($category, $severity, $message, $context = []) {
    return LightbotErrorTracker::getInstance()->trackError($category, $severity, $message, $context);
}

function lightbot_get_error_summary($hours = 24) {
    return LightbotErrorTracker::getInstance()->getErrorSummary($hours);
}

function lightbot_get_health_status() {
    return LightbotErrorTracker::getInstance()->getHealthStatus();
}

?>