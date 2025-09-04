<?php
/**
 * Lightbot Monitoring Dashboard
 * Real-time system monitoring with metrics visualization
 */

// Set content type to HTML
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-cache');

require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/error-tracker.php';
require_once __DIR__ . '/metrics-collector.php';
require_once __DIR__ . '/alerting-system.php';
require_once __DIR__ . '/config-loader.php';

// Handle API requests for dashboard data
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_GET['api']) {
            case 'metrics':
                echo json_encode(lightbot_get_metrics_summary());
                break;
                
            case 'errors':
                echo json_encode(lightbot_get_error_summary(24));
                break;
                
            case 'alerts':
                echo json_encode(lightbot_get_alert_stats(24));
                break;
                
            case 'health':
                echo json_encode(lightbot_get_health_status());
                break;
                
            case 'historical':
                $days = intval($_GET['days'] ?? 7);
                echo json_encode(lightbot_get_historical_metrics($days));
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['error' => 'Unknown API endpoint']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    
    exit;
}

// Generate dashboard HTML
$config = ConfigLoader::getInstance();
$currentMetrics = lightbot_get_metrics_summary();
$health = lightbot_get_health_status();
$errorSummary = lightbot_get_error_summary(24);
$alertStats = lightbot_get_alert_stats(24);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lightbot Monitoring Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            line-height: 1.6;
        }
        
        .dashboard {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            border-radius: 10px;
            border-left: 4px solid #0ea5e9;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            background: linear-gradient(45deg, #0ea5e9, #06b6d4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .header .timestamp {
            color: #94a3b8;
            font-size: 0.9rem;
        }
        
        .status-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .status-card {
            padding: 20px;
            background: #1e293b;
            border-radius: 8px;
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        
        .status-card:hover {
            transform: translateY(-2px);
        }
        
        .status-card.healthy {
            border-left-color: #10b981;
        }
        
        .status-card.warning {
            border-left-color: #f59e0b;
        }
        
        .status-card.critical {
            border-left-color: #ef4444;
        }
        
        .status-card h3 {
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #94a3b8;
            margin-bottom: 8px;
        }
        
        .status-card .value {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .status-card .label {
            font-size: 0.8rem;
            color: #64748b;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card {
            background: #1e293b;
            border-radius: 10px;
            padding: 20px;
            border: 1px solid #334155;
        }
        
        .card h2 {
            font-size: 1.2rem;
            margin-bottom: 15px;
            color: #f8fafc;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .card .icon {
            width: 20px;
            height: 20px;
            border-radius: 3px;
            display: inline-block;
        }
        
        .icon.system { background: #3b82f6; }
        .icon.errors { background: #ef4444; }
        .icon.alerts { background: #f59e0b; }
        .icon.performance { background: #10b981; }
        
        .metric-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #334155;
        }
        
        .metric-row:last-child {
            border-bottom: none;
        }
        
        .metric-label {
            color: #94a3b8;
            font-size: 0.9rem;
        }
        
        .metric-value {
            font-weight: 600;
            color: #f8fafc;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #334155;
            border-radius: 4px;
            overflow: hidden;
            margin: 5px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981 0%, #f59e0b 70%, #ef4444 90%);
            transition: width 0.3s ease;
        }
        
        .alert-item {
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-item.critical {
            background: rgba(239, 68, 68, 0.1);
            border-left: 3px solid #ef4444;
        }
        
        .alert-item.high {
            background: rgba(245, 158, 11, 0.1);
            border-left: 3px solid #f59e0b;
        }
        
        .alert-item.medium {
            background: rgba(59, 130, 246, 0.1);
            border-left: 3px solid #3b82f6;
        }
        
        .alert-item.low {
            background: rgba(16, 185, 129, 0.1);
            border-left: 3px solid #10b981;
        }
        
        .error-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin: 15px 0;
        }
        
        .error-stat {
            text-align: center;
            padding: 15px;
            background: #0f172a;
            border-radius: 5px;
        }
        
        .error-stat .count {
            font-size: 1.5rem;
            font-weight: 600;
            color: #ef4444;
        }
        
        .error-stat .label {
            font-size: 0.8rem;
            color: #64748b;
            text-transform: uppercase;
        }
        
        .refresh-button {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #0ea5e9;
            color: white;
            border: none;
            border-radius: 50px;
            padding: 15px 20px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .refresh-button:hover {
            background: #0284c7;
            transform: scale(1.05);
        }
        
        .auto-refresh {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #1e293b;
            padding: 10px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            color: #94a3b8;
        }
        
        .auto-refresh.active {
            color: #10b981;
        }
        
        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
            }
            
            .status-bar {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .header h1 {
                font-size: 1.8rem;
            }
        }
        
        .loading {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 2px solid #334155;
            border-top: 2px solid #0ea5e9;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 5px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <div class="header">
            <h1>🤖 Lightbot Monitoring</h1>
            <div class="timestamp" id="lastUpdate">
                Last updated: <?php echo date('Y-m-d H:i:s'); ?>
            </div>
        </div>
        
        <div class="auto-refresh" id="autoRefresh">
            <span id="refreshStatus">Auto-refresh: ON</span>
            <span class="loading" id="loadingSpinner" style="display: none;"></span>
        </div>
        
        <!-- System Status Overview -->
        <div class="status-bar" id="statusBar">
            <div class="status-card <?php echo $health['status'] === 'critical' ? 'critical' : ($health['status'] === 'warning' ? 'warning' : 'healthy'); ?>">
                <h3>System Health</h3>
                <div class="value"><?php echo ucfirst($health['status']); ?></div>
                <div class="label"><?php echo count($health['issues']); ?> issues detected</div>
            </div>
            
            <div class="status-card">
                <h3>Memory Usage</h3>
                <div class="value"><?php echo number_format($currentMetrics['current']['system']['memory_usage']['usage_percent'] ?? 0, 1); ?>%</div>
                <div class="label"><?php echo number_format(($currentMetrics['current']['system']['memory_usage']['used_mb'] ?? 0) / 1024, 1); ?> GB used</div>
            </div>
            
            <div class="status-card">
                <h3>Disk Usage</h3>
                <div class="value"><?php echo number_format($currentMetrics['current']['system']['disk_usage']['usage_percent'] ?? 0, 1); ?>%</div>
                <div class="label"><?php echo number_format($currentMetrics['current']['system']['disk_usage']['free_gb'] ?? 0, 1); ?> GB free</div>
            </div>
            
            <div class="status-card">
                <h3>Error Rate</h3>
                <div class="value"><?php echo number_format($errorSummary['error_rate'], 1); ?></div>
                <div class="label">errors per hour</div>
            </div>
        </div>
        
        <!-- Detailed Metrics Grid -->
        <div class="grid">
            <!-- System Resources -->
            <div class="card">
                <h2><span class="icon system"></span> System Resources</h2>
                
                <div class="metric-row">
                    <span class="metric-label">Memory Usage</span>
                    <span class="metric-value"><?php echo number_format($currentMetrics['current']['system']['memory_usage']['usage_percent'] ?? 0, 1); ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $currentMetrics['current']['system']['memory_usage']['usage_percent'] ?? 0; ?>%"></div>
                </div>
                
                <div class="metric-row">
                    <span class="metric-label">Disk Usage</span>
                    <span class="metric-value"><?php echo number_format($currentMetrics['current']['system']['disk_usage']['usage_percent'] ?? 0, 1); ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $currentMetrics['current']['system']['disk_usage']['usage_percent'] ?? 0; ?>%"></div>
                </div>
                
                <div class="metric-row">
                    <span class="metric-label">CPU Load (1min)</span>
                    <span class="metric-value"><?php echo number_format($currentMetrics['current']['system']['cpu_load_1min'] ?? 0, 2); ?></span>
                </div>
                
                <div class="metric-row">
                    <span class="metric-label">Processes</span>
                    <span class="metric-value"><?php echo number_format($currentMetrics['current']['system']['process_count'] ?? 0); ?></span>
                </div>
            </div>
            
            <!-- Performance Metrics -->
            <div class="card">
                <h2><span class="icon performance"></span> Performance</h2>
                
                <div class="metric-row">
                    <span class="metric-label">Database Response</span>
                    <span class="metric-value"><?php echo number_format($currentMetrics['current']['performance']['database_response_time_ms'] ?? 0, 1); ?> ms</span>
                </div>
                
                <div class="metric-row">
                    <span class="metric-label">Database Status</span>
                    <span class="metric-value"><?php echo ucfirst($currentMetrics['current']['performance']['database_status'] ?? 'unknown'); ?></span>
                </div>
                
                <div class="metric-row">
                    <span class="metric-label">File System Write</span>
                    <span class="metric-value"><?php echo number_format($currentMetrics['current']['performance']['filesystem_write_time_ms'] ?? 0, 1); ?> ms</span>
                </div>
                
                <div class="metric-row">
                    <span class="metric-label">Log Files Size</span>
                    <span class="metric-value"><?php echo number_format($currentMetrics['current']['application']['log_directory_size'] ?? 0, 1); ?> MB</span>
                </div>
            </div>
            
            <!-- Error Summary -->
            <div class="card">
                <h2><span class="icon errors"></span> Error Summary (24h)</h2>
                
                <div class="error-summary">
                    <div class="error-stat">
                        <div class="count"><?php echo $errorSummary['total_errors']; ?></div>
                        <div class="label">Total</div>
                    </div>
                    <div class="error-stat">
                        <div class="count"><?php echo $errorSummary['by_severity']['critical'] ?? 0; ?></div>
                        <div class="label">Critical</div>
                    </div>
                    <div class="error-stat">
                        <div class="count"><?php echo $errorSummary['by_severity']['high'] ?? 0; ?></div>
                        <div class="label">High</div>
                    </div>
                    <div class="error-stat">
                        <div class="count"><?php echo number_format($errorSummary['error_rate'], 1); ?></div>
                        <div class="label">Per Hour</div>
                    </div>
                </div>
                
                <?php if (!empty($errorSummary['recent_errors'])): ?>
                <h3 style="margin: 15px 0 10px; font-size: 1rem;">Recent Errors</h3>
                <?php foreach (array_slice($errorSummary['recent_errors'], 0, 5) as $error): ?>
                <div class="alert-item <?php echo $error['severity']; ?>">
                    <strong><?php echo ucfirst($error['category']); ?>:</strong>
                    <?php echo htmlspecialchars(substr($error['message'], 0, 60)); ?>...
                    <span style="margin-left: auto; font-size: 0.8rem; color: #64748b;">
                        <?php echo $error['time']; ?>
                    </span>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <p style="color: #10b981; margin-top: 15px;">✅ No recent errors</p>
                <?php endif; ?>
            </div>
            
            <!-- Alerts Summary -->
            <div class="card">
                <h2><span class="icon alerts"></span> Alerts (24h)</h2>
                
                <div class="metric-row">
                    <span class="metric-label">Total Alerts</span>
                    <span class="metric-value"><?php echo $alertStats['total_alerts']; ?></span>
                </div>
                
                <?php if (!empty($alertStats['by_severity'])): ?>
                <?php foreach ($alertStats['by_severity'] as $severity => $count): ?>
                <div class="metric-row">
                    <span class="metric-label"><?php echo ucfirst($severity); ?> Severity</span>
                    <span class="metric-value"><?php echo $count; ?></span>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
                
                <?php if (!empty($alertStats['recent_alerts'])): ?>
                <h3 style="margin: 15px 0 10px; font-size: 1rem;">Recent Alerts</h3>
                <?php foreach (array_slice($alertStats['recent_alerts'], 0, 5) as $alert): ?>
                <div class="alert-item <?php echo $alert['severity']; ?>">
                    <strong><?php echo str_replace('_', ' ', ucfirst($alert['type'])); ?></strong>
                    <span style="margin-left: auto; font-size: 0.8rem; color: #64748b;">
                        <?php echo $alert['time']; ?>
                    </span>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <p style="color: #10b981; margin-top: 15px;">✅ No recent alerts</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- System Health Issues -->
        <?php if (!empty($health['issues'])): ?>
        <div class="card" style="margin-bottom: 20px;">
            <h2>🚨 Active Issues</h2>
            <?php foreach ($health['issues'] as $issue): ?>
            <div class="alert-item critical">
                <?php echo htmlspecialchars($issue); ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <button class="refresh-button" onclick="refreshDashboard()">
        🔄 Refresh
    </button>
    
    <script>
        let autoRefreshEnabled = true;
        let refreshInterval;
        
        function refreshDashboard() {
            const loadingSpinner = document.getElementById('loadingSpinner');
            loadingSpinner.style.display = 'inline-block';
            
            // Reload the page to get fresh data
            window.location.reload();
        }
        
        function toggleAutoRefresh() {
            const autoRefreshElement = document.getElementById('autoRefresh');
            const statusElement = document.getElementById('refreshStatus');
            
            autoRefreshEnabled = !autoRefreshEnabled;
            
            if (autoRefreshEnabled) {
                statusElement.textContent = 'Auto-refresh: ON';
                autoRefreshElement.classList.add('active');
                startAutoRefresh();
            } else {
                statusElement.textContent = 'Auto-refresh: OFF';
                autoRefreshElement.classList.remove('active');
                clearInterval(refreshInterval);
            }
        }
        
        function startAutoRefresh() {
            refreshInterval = setInterval(() => {
                if (autoRefreshEnabled) {
                    refreshDashboard();
                }
            }, 30000); // Refresh every 30 seconds
        }
        
        // Initialize auto-refresh
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('autoRefresh').addEventListener('click', toggleAutoRefresh);
            startAutoRefresh();
            
            // Update timestamp
            const now = new Date();
            document.getElementById('lastUpdate').textContent = 
                'Last updated: ' + now.toLocaleString();
        });
        
        // Handle visibility change to pause/resume refresh when tab is not active
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                clearInterval(refreshInterval);
            } else if (autoRefreshEnabled) {
                startAutoRefresh();
            }
        });
    </script>
</body>
</html>