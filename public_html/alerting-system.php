<?php
/**
 * Comprehensive Alerting System for Lightbot
 * Features: Multiple channels, smart throttling, escalation paths
 */

require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/config-loader.php';
require_once __DIR__ . '/error-tracker.php';
require_once __DIR__ . '/metrics-collector.php';

class LightbotAlertingSystem {
    private static $instance = null;
    private $config;
    private $logger;
    
    // Alert severity levels
    const SEVERITY_LOW = 'low';
    const SEVERITY_MEDIUM = 'medium';
    const SEVERITY_HIGH = 'high';
    const SEVERITY_CRITICAL = 'critical';
    
    // Alert channels
    const CHANNEL_EMAIL = 'email';
    const CHANNEL_SYSLOG = 'syslog';
    const CHANNEL_FILE = 'file';
    const CHANNEL_WEBHOOK = 'webhook';
    
    private $alertRules = [
        // System resource alerts
        'high_memory_usage' => [
            'threshold' => 85,
            'severity' => self::SEVERITY_HIGH,
            'cooldown' => 1800, // 30 minutes
            'channels' => [self::CHANNEL_EMAIL, self::CHANNEL_SYSLOG]
        ],
        'critical_memory_usage' => [
            'threshold' => 95,
            'severity' => self::SEVERITY_CRITICAL,
            'cooldown' => 300, // 5 minutes
            'channels' => [self::CHANNEL_EMAIL, self::CHANNEL_SYSLOG, self::CHANNEL_FILE]
        ],
        'high_disk_usage' => [
            'threshold' => 80,
            'severity' => self::SEVERITY_HIGH,
            'cooldown' => 3600, // 1 hour
            'channels' => [self::CHANNEL_EMAIL, self::CHANNEL_SYSLOG]
        ],
        'critical_disk_usage' => [
            'threshold' => 90,
            'severity' => self::SEVERITY_CRITICAL,
            'cooldown' => 600, // 10 minutes
            'channels' => [self::CHANNEL_EMAIL, self::CHANNEL_SYSLOG, self::CHANNEL_FILE]
        ],
        
        // Application performance alerts
        'slow_database' => [
            'threshold' => 1000, // 1 second
            'severity' => self::SEVERITY_MEDIUM,
            'cooldown' => 900, // 15 minutes
            'channels' => [self::CHANNEL_SYSLOG, self::CHANNEL_FILE]
        ],
        'database_down' => [
            'threshold' => -1, // Error condition
            'severity' => self::SEVERITY_CRITICAL,
            'cooldown' => 300, // 5 minutes
            'channels' => [self::CHANNEL_EMAIL, self::CHANNEL_SYSLOG, self::CHANNEL_FILE]
        ],
        
        // Business/error rate alerts
        'high_error_rate' => [
            'threshold' => 10,
            'severity' => self::SEVERITY_HIGH,
            'cooldown' => 900, // 15 minutes
            'channels' => [self::CHANNEL_EMAIL, self::CHANNEL_SYSLOG]
        ],
        'critical_error_rate' => [
            'threshold' => 50,
            'severity' => self::SEVERITY_CRITICAL,
            'cooldown' => 300, // 5 minutes
            'channels' => [self::CHANNEL_EMAIL, self::CHANNEL_SYSLOG, self::CHANNEL_FILE]
        ]
    ];
    
    private function __construct() {
        $this->config = ConfigLoader::getInstance();
        $this->logger = LightbotLogger::getInstance();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new LightbotAlertingSystem();
        }
        return self::$instance;
    }
    
    /**
     * Check all alerting rules against current metrics
     */
    public function checkAlerts() {
        $startTime = microtime(true);
        
        // Collect current metrics
        $metrics = lightbot_collect_metrics();
        
        $alertsTriggered = 0;
        $alertsSkipped = 0;
        
        // Check system resource alerts
        $this->checkSystemResourceAlerts($metrics, $alertsTriggered, $alertsSkipped);
        
        // Check performance alerts
        $this->checkPerformanceAlerts($metrics, $alertsTriggered, $alertsSkipped);
        
        // Check business/error alerts
        $this->checkBusinessAlerts($metrics, $alertsTriggered, $alertsSkipped);
        
        // Check custom health alerts
        $this->checkHealthAlerts($metrics, $alertsTriggered, $alertsSkipped);
        
        $checkTime = lightbot_track_performance('alert_check', $startTime);
        
        $this->logger->log('DEBUG', 'Alert check completed', [
            'category' => 'alerting',
            'alerts_triggered' => $alertsTriggered,
            'alerts_skipped' => $alertsSkipped,
            'check_time_ms' => round($checkTime, 2)
        ]);
        
        return [
            'alerts_triggered' => $alertsTriggered,
            'alerts_skipped' => $alertsSkipped,
            'check_time_ms' => round($checkTime, 2)
        ];
    }
    
    private function checkSystemResourceAlerts($metrics, &$alertsTriggered, &$alertsSkipped) {
        // Memory usage alerts
        if (isset($metrics['system']['memory_usage']['usage_percent'])) {
            $memUsage = $metrics['system']['memory_usage']['usage_percent'];
            
            if ($memUsage >= $this->alertRules['critical_memory_usage']['threshold']) {
                if ($this->shouldTriggerAlert('critical_memory_usage')) {
                    $this->triggerAlert('critical_memory_usage', "Critical memory usage: {$memUsage}%", [
                        'current_usage' => $memUsage,
                        'threshold' => $this->alertRules['critical_memory_usage']['threshold'],
                        'available_mb' => $metrics['system']['memory_usage']['available_mb']
                    ]);
                    $alertsTriggered++;
                } else {
                    $alertsSkipped++;
                }
            } elseif ($memUsage >= $this->alertRules['high_memory_usage']['threshold']) {
                if ($this->shouldTriggerAlert('high_memory_usage')) {
                    $this->triggerAlert('high_memory_usage', "High memory usage: {$memUsage}%", [
                        'current_usage' => $memUsage,
                        'threshold' => $this->alertRules['high_memory_usage']['threshold']
                    ]);
                    $alertsTriggered++;
                } else {
                    $alertsSkipped++;
                }
            }
        }
        
        // Disk usage alerts
        if (isset($metrics['system']['disk_usage']['usage_percent'])) {
            $diskUsage = $metrics['system']['disk_usage']['usage_percent'];
            
            if ($diskUsage >= $this->alertRules['critical_disk_usage']['threshold']) {
                if ($this->shouldTriggerAlert('critical_disk_usage')) {
                    $this->triggerAlert('critical_disk_usage', "Critical disk usage: {$diskUsage}%", [
                        'current_usage' => $diskUsage,
                        'threshold' => $this->alertRules['critical_disk_usage']['threshold'],
                        'free_gb' => $metrics['system']['disk_usage']['free_gb']
                    ]);
                    $alertsTriggered++;
                } else {
                    $alertsSkipped++;
                }
            } elseif ($diskUsage >= $this->alertRules['high_disk_usage']['threshold']) {
                if ($this->shouldTriggerAlert('high_disk_usage')) {
                    $this->triggerAlert('high_disk_usage', "High disk usage: {$diskUsage}%", [
                        'current_usage' => $diskUsage,
                        'threshold' => $this->alertRules['high_disk_usage']['threshold']
                    ]);
                    $alertsTriggered++;
                } else {
                    $alertsSkipped++;
                }
            }
        }
    }
    
    private function checkPerformanceAlerts($metrics, &$alertsTriggered, &$alertsSkipped) {
        // Database performance alerts
        if (isset($metrics['performance']['database_response_time_ms'])) {
            $dbTime = $metrics['performance']['database_response_time_ms'];
            
            if ($dbTime === -1) { // Database error
                if ($this->shouldTriggerAlert('database_down')) {
                    $this->triggerAlert('database_down', "Database is not responding", [
                        'status' => $metrics['performance']['database_status'] ?? 'error'
                    ]);
                    $alertsTriggered++;
                } else {
                    $alertsSkipped++;
                }
            } elseif ($dbTime >= $this->alertRules['slow_database']['threshold']) {
                if ($this->shouldTriggerAlert('slow_database')) {
                    $this->triggerAlert('slow_database', "Database response time is slow: {$dbTime}ms", [
                        'response_time_ms' => $dbTime,
                        'threshold' => $this->alertRules['slow_database']['threshold']
                    ]);
                    $alertsTriggered++;
                } else {
                    $alertsSkipped++;
                }
            }
        }
    }
    
    private function checkBusinessAlerts($metrics, &$alertsTriggered, &$alertsSkipped) {
        // Error rate alerts
        if (isset($metrics['business']['errors']['error_rate_per_hour'])) {
            $errorRate = $metrics['business']['errors']['error_rate_per_hour'];
            
            if ($errorRate >= $this->alertRules['critical_error_rate']['threshold']) {
                if ($this->shouldTriggerAlert('critical_error_rate')) {
                    $this->triggerAlert('critical_error_rate', "Critical error rate: {$errorRate} errors/hour", [
                        'error_rate' => $errorRate,
                        'threshold' => $this->alertRules['critical_error_rate']['threshold'],
                        'total_errors_24h' => $metrics['business']['errors']['total_errors_24h']
                    ]);
                    $alertsTriggered++;
                } else {
                    $alertsSkipped++;
                }
            } elseif ($errorRate >= $this->alertRules['high_error_rate']['threshold']) {
                if ($this->shouldTriggerAlert('high_error_rate')) {
                    $this->triggerAlert('high_error_rate', "High error rate: {$errorRate} errors/hour", [
                        'error_rate' => $errorRate,
                        'threshold' => $this->alertRules['high_error_rate']['threshold']
                    ]);
                    $alertsTriggered++;
                } else {
                    $alertsSkipped++;
                }
            }
        }
    }
    
    private function checkHealthAlerts($metrics, &$alertsTriggered, &$alertsSkipped) {
        // Overall health status
        if (isset($metrics['health']['overall_status'])) {
            $status = $metrics['health']['overall_status'];
            
            if ($status === 'critical') {
                if ($this->shouldTriggerAlert('system_health_critical')) {
                    $issues = implode(', ', $metrics['health']['issues'] ?? []);
                    $this->triggerAlert('system_health_critical', "System health is critical: {$issues}", [
                        'status' => $status,
                        'issues_count' => $metrics['health']['issues_count'] ?? 0,
                        'issues' => $metrics['health']['issues'] ?? []
                    ]);
                    $alertsTriggered++;
                } else {
                    $alertsSkipped++;
                }
            }
        }
    }
    
    /**
     * Check if an alert should be triggered (respects cooldown periods)
     */
    private function shouldTriggerAlert($alertType) {
        $alertHistoryFile = $this->getAlertHistoryFile();
        
        if (!file_exists($alertHistoryFile)) {
            return true; // No history, allow alert
        }
        
        $lines = file($alertHistoryFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $cooldown = $this->alertRules[$alertType]['cooldown'] ?? 900; // Default 15 minutes
        $cutoffTime = time() - $cooldown;
        
        foreach (array_reverse($lines) as $line) { // Check newest first
            $data = json_decode($line, true);
            if ($data && 
                $data['alert_type'] === $alertType && 
                $data['timestamp'] >= $cutoffTime) {
                return false; // Still in cooldown period
            }
        }
        
        return true;
    }
    
    /**
     * Trigger an alert through configured channels
     */
    private function triggerAlert($alertType, $message, $context = []) {
        $severity = $this->alertRules[$alertType]['severity'] ?? self::SEVERITY_MEDIUM;
        $channels = $this->alertRules[$alertType]['channels'] ?? [self::CHANNEL_SYSLOG];
        
        $alertData = [
            'alert_type' => $alertType,
            'severity' => $severity,
            'message' => $message,
            'context' => $context,
            'timestamp' => time(),
            'formatted_time' => date('Y-m-d H:i:s'),
            'hostname' => gethostname(),
            'channels' => $channels
        ];
        
        // Log the alert trigger
        $this->logger->log('ERROR', "ALERT TRIGGERED [{$alertType}]: {$message}", array_merge($context, [
            'category' => 'alerting',
            'alert_type' => $alertType,
            'severity' => $severity
        ]));
        
        // Send through each configured channel
        foreach ($channels as $channel) {
            $this->sendAlertToChannel($channel, $alertData);
        }
        
        // Record alert in history
        $this->recordAlert($alertData);
    }
    
    /**
     * Send alert to specific channel
     */
    private function sendAlertToChannel($channel, $alertData) {
        switch ($channel) {
            case self::CHANNEL_EMAIL:
                $this->sendEmailAlert($alertData);
                break;
                
            case self::CHANNEL_SYSLOG:
                $this->sendSyslogAlert($alertData);
                break;
                
            case self::CHANNEL_FILE:
                $this->sendFileAlert($alertData);
                break;
                
            case self::CHANNEL_WEBHOOK:
                $this->sendWebhookAlert($alertData);
                break;
                
            default:
                $this->logger->log('WARNING', "Unknown alert channel: {$channel}", [
                    'category' => 'alerting',
                    'alert_type' => $alertData['alert_type']
                ]);
        }
    }
    
    private function sendEmailAlert($alertData) {
        $adminEmail = $this->config->get('ADMIN_EMAIL', '');
        
        if (!$adminEmail) {
            $this->logger->log('WARNING', 'Cannot send email alert: ADMIN_EMAIL not configured', [
                'category' => 'alerting',
                'alert_type' => $alertData['alert_type']
            ]);
            return false;
        }
        
        $subject = "🚨 Lightbot Alert [{$alertData['severity']}] - {$alertData['alert_type']}";
        
        $body = $this->formatAlertEmail($alertData);
        
        $headers = [
            'From: lightbot@' . ($_SERVER['SERVER_NAME'] ?? 'localhost'),
            'Content-Type: text/plain; charset=utf-8',
            'X-Priority: ' . $this->getEmailPriority($alertData['severity'])
        ];
        
        $sent = mail($adminEmail, $subject, $body, implode("\r\n", $headers));
        
        $this->logger->log($sent ? 'INFO' : 'ERROR', 
            $sent ? 'Email alert sent successfully' : 'Failed to send email alert', [
            'category' => 'alerting',
            'alert_type' => $alertData['alert_type'],
            'recipient' => $adminEmail
        ]);
        
        return $sent;
    }
    
    private function sendSyslogAlert($alertData) {
        $priority = $this->getSyslogPriority($alertData['severity']);
        $message = "LIGHTBOT ALERT [{$alertData['alert_type']}]: {$alertData['message']}";
        
        syslog($priority, $message);
        
        $this->logger->log('DEBUG', 'Syslog alert sent', [
            'category' => 'alerting',
            'alert_type' => $alertData['alert_type']
        ]);
        
        return true;
    }
    
    private function sendFileAlert($alertData) {
        $alertFile = $this->config->get('LOG_PATH', '/var/www/lightbot.rentri360.it/logs') . '/critical_alerts.log';
        
        $logEntry = "[{$alertData['formatted_time']}] CRITICAL ALERT [{$alertData['alert_type']}]\n";
        $logEntry .= "Severity: {$alertData['severity']}\n";
        $logEntry .= "Message: {$alertData['message']}\n";
        $logEntry .= "Host: {$alertData['hostname']}\n";
        
        if (!empty($alertData['context'])) {
            $logEntry .= "Context: " . json_encode($alertData['context'], JSON_PRETTY_PRINT) . "\n";
        }
        
        $logEntry .= str_repeat('-', 80) . "\n\n";
        
        $written = file_put_contents($alertFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        $this->logger->log($written !== false ? 'DEBUG' : 'ERROR',
            $written !== false ? 'File alert written' : 'Failed to write file alert', [
            'category' => 'alerting',
            'alert_type' => $alertData['alert_type'],
            'file' => $alertFile
        ]);
        
        return $written !== false;
    }
    
    private function sendWebhookAlert($alertData) {
        $webhookUrl = $this->config->get('ALERT_WEBHOOK_URL', '');
        
        if (!$webhookUrl) {
            $this->logger->log('WARNING', 'Cannot send webhook alert: ALERT_WEBHOOK_URL not configured', [
                'category' => 'alerting',
                'alert_type' => $alertData['alert_type']
            ]);
            return false;
        }
        
        $payload = json_encode($alertData);
        
        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        $success = ($response !== false && $httpCode >= 200 && $httpCode < 300);
        
        $this->logger->log($success ? 'DEBUG' : 'ERROR',
            $success ? 'Webhook alert sent' : 'Webhook alert failed', [
            'category' => 'alerting',
            'alert_type' => $alertData['alert_type'],
            'http_code' => $httpCode,
            'curl_error' => $error
        ]);
        
        return $success;
    }
    
    private function formatAlertEmail($alertData) {
        $body = "🚨 LIGHTBOT MONITORING ALERT 🚨\n\n";
        $body .= "Alert Type: {$alertData['alert_type']}\n";
        $body .= "Severity: {$alertData['severity']}\n";
        $body .= "Message: {$alertData['message']}\n";
        $body .= "Time: {$alertData['formatted_time']}\n";
        $body .= "Host: {$alertData['hostname']}\n\n";
        
        if (!empty($alertData['context'])) {
            $body .= "Additional Details:\n";
            foreach ($alertData['context'] as $key => $value) {
                $body .= "  {$key}: " . (is_array($value) ? json_encode($value) : $value) . "\n";
            }
            $body .= "\n";
        }
        
        $body .= "Please investigate immediately.\n\n";
        $body .= "Generated by Lightbot Monitoring System\n";
        $body .= "https://" . ($_SERVER['SERVER_NAME'] ?? 'localhost') . "\n";
        
        return $body;
    }
    
    private function getEmailPriority($severity) {
        switch ($severity) {
            case self::SEVERITY_CRITICAL: return '1'; // High priority
            case self::SEVERITY_HIGH: return '2';
            case self::SEVERITY_MEDIUM: return '3'; // Normal priority
            case self::SEVERITY_LOW: return '4';
            default: return '3';
        }
    }
    
    private function getSyslogPriority($severity) {
        switch ($severity) {
            case self::SEVERITY_CRITICAL: return LOG_CRIT;
            case self::SEVERITY_HIGH: return LOG_ERR;
            case self::SEVERITY_MEDIUM: return LOG_WARNING;
            case self::SEVERITY_LOW: return LOG_NOTICE;
            default: return LOG_WARNING;
        }
    }
    
    private function recordAlert($alertData) {
        $alertHistoryFile = $this->getAlertHistoryFile();
        
        $record = [
            'alert_type' => $alertData['alert_type'],
            'severity' => $alertData['severity'],
            'timestamp' => $alertData['timestamp'],
            'sent_at' => $alertData['formatted_time']
        ];
        
        file_put_contents($alertHistoryFile, json_encode($record) . "\n", FILE_APPEND | LOCK_EX);
    }
    
    private function getAlertHistoryFile() {
        $logPath = $this->config->get('LOG_PATH', '/var/www/lightbot.rentri360.it/logs');
        return $logPath . '/alert_history.jsonl';
    }
    
    /**
     * Get alert statistics and history
     */
    public function getAlertStats($hours = 24) {
        $alertHistoryFile = $this->getAlertHistoryFile();
        
        $stats = [
            'total_alerts' => 0,
            'by_severity' => [],
            'by_type' => [],
            'recent_alerts' => []
        ];
        
        if (!file_exists($alertHistoryFile)) {
            return $stats;
        }
        
        $lines = file($alertHistoryFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $cutoffTime = time() - ($hours * 3600);
        
        foreach ($lines as $line) {
            $data = json_decode($line, true);
            if (!$data || $data['timestamp'] < $cutoffTime) {
                continue;
            }
            
            $stats['total_alerts']++;
            
            $severity = $data['severity'];
            $stats['by_severity'][$severity] = ($stats['by_severity'][$severity] ?? 0) + 1;
            
            $type = $data['alert_type'];
            $stats['by_type'][$type] = ($stats['by_type'][$type] ?? 0) + 1;
            
            if (count($stats['recent_alerts']) < 20) {
                $stats['recent_alerts'][] = [
                    'type' => $type,
                    'severity' => $severity,
                    'time' => $data['sent_at']
                ];
            }
        }
        
        return $stats;
    }
    
    /**
     * Test all alert channels
     */
    public function testAlerts() {
        $testAlert = [
            'alert_type' => 'test_alert',
            'severity' => self::SEVERITY_LOW,
            'message' => 'This is a test alert from Lightbot monitoring system',
            'context' => ['test' => true, 'timestamp' => time()],
            'timestamp' => time(),
            'formatted_time' => date('Y-m-d H:i:s'),
            'hostname' => gethostname(),
            'channels' => [self::CHANNEL_EMAIL, self::CHANNEL_SYSLOG, self::CHANNEL_FILE]
        ];
        
        $results = [];
        
        foreach ($testAlert['channels'] as $channel) {
            $results[$channel] = $this->sendAlertToChannel($channel, $testAlert);
        }
        
        $this->logger->log('INFO', 'Alert system test completed', [
            'category' => 'alerting',
            'results' => $results
        ]);
        
        return $results;
    }
}

// Convenience functions
function lightbot_check_alerts() {
    return LightbotAlertingSystem::getInstance()->checkAlerts();
}

function lightbot_get_alert_stats($hours = 24) {
    return LightbotAlertingSystem::getInstance()->getAlertStats($hours);
}

function lightbot_test_alerts() {
    return LightbotAlertingSystem::getInstance()->testAlerts();
}

?>