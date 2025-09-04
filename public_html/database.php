<?php
/**
 * Database Connection and Operations Class
 * Replaces JSON file storage with MySQL database
 * 
 * Created: 2025-08-29
 * Purpose: Scalable database operations for Lightbot system
 */

class DatabaseManager {
    private $pdo;
    private $config;
    private static $instance = null;
    
    private function __construct() {
        // Load configuration
        require_once __DIR__ . '/config-loader.php';
        require_once __DIR__ . '/logger.php';
        $this->config = ConfigLoader::getInstance();
        $this->connect();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new DatabaseManager();
        }
        return self::$instance;
    }
    
    private function connect() {
        $host = $this->config->get('DB_HOST', 'localhost');
        $dbname = $this->config->get('DB_NAME', 'lightbot');
        $username = $this->config->get('DB_USER', 'lightbot_user');
        $password = $this->config->get('DB_PASSWORD', 'df65e00b78b87b10f724d3baaf88e7a9');
        
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'"
        ];
        
        try {
            $this->pdo = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new RuntimeException("Database connection failed");
        }
    }
    
    public function getPdo() {
        return $this->pdo;
    }
    
    /**
     * Get user rate limit data
     */
    public function getUserLimits($userId) {
        $startTime = microtime(true);
        
        $stmt = $this->pdo->prepare("
            SELECT * FROM user_limits 
            WHERE user_id = ? 
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        
        $duration = lightbot_track_performance('db_query', $startTime, [
            'user_id_hash' => substr($userId, 0, 20),
            'found' => $result ? 'yes' : 'no'
        ]);
        
        lightbot_log_db('SELECT', 'user_limits', round($duration, 2), 1, [
            'user_id_hash' => substr($userId, 0, 20)
        ]);
        
        return $result;
    }
    
    /**
     * Create or update user rate limit data
     */
    public function updateUserLimits($userId, $data) {
        $startTime = microtime(true);
        
        // Prepare shortened hash for display
        $userIdHash = substr($userId, 0, 20) . '...';
        
        $stmt = $this->pdo->prepare("
            INSERT INTO user_limits (
                user_id, user_id_hash, count, total_attempts, 
                first_message, last_message, is_blocked, 
                grace_period_start, metadata
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                count = VALUES(count),
                total_attempts = VALUES(total_attempts),
                last_message = VALUES(last_message),
                is_blocked = VALUES(is_blocked),
                grace_period_start = VALUES(grace_period_start),
                metadata = VALUES(metadata)
        ");
        
        $metadata = isset($data['metadata']) ? json_encode($data['metadata']) : null;
        $gracePeriodStart = isset($data['grace_period_start']) ? $data['grace_period_start'] : null;
        
        $result = $stmt->execute([
            $userId,
            $userIdHash,
            intval($data['count'] ?? 0),
            intval($data['total_attempts'] ?? 0),
            $data['first_message'] ?? date('Y-m-d H:i:s'),
            $data['last_message'] ?? date('Y-m-d H:i:s'),
            $data['is_blocked'] ? 1 : 0,
            $gracePeriodStart,
            $metadata
        ]);
        
        $duration = lightbot_track_performance('db_query', $startTime, [
            'user_id_hash' => substr($userId, 0, 20),
            'count' => intval($data['count'] ?? 0),
            'is_blocked' => $data['is_blocked'] ? 'yes' : 'no'
        ]);
        
        lightbot_log_db('INSERT/UPDATE', 'user_limits', round($duration, 2), $stmt->rowCount(), [
            'user_id_hash' => substr($userId, 0, 20)
        ]);
        
        return $result;
    }
    
    /**
     * Get all user limits (for migration and stats)
     */
    public function getAllUserLimits($limit = null) {
        $sql = "SELECT * FROM user_limits ORDER BY last_message DESC";
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }
    
    /**
     * Log activity
     */
    public function logActivity($userId, $action, $component, $data = []) {
        $stmt = $this->pdo->prepare("
            INSERT INTO activity_log (
                user_id, action, component, ip_address, 
                processing_time_ms, error_message, timestamp
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        return $stmt->execute([
            $userId,
            $action,
            $component,
            $data['ip_address'] ?? null,
            $data['processing_time_ms'] ?? null,
            $data['error_message'] ?? null
        ]);
    }
    
    /**
     * Get system statistics
     */
    public function getSystemStats() {
        $stats = [];
        
        // User statistics
        $stmt = $this->pdo->query("
            SELECT 
                COUNT(*) as total_users,
                COUNT(CASE WHEN is_blocked = 1 THEN 1 END) as blocked_users,
                COUNT(CASE WHEN count >= max_count THEN 1 END) as users_at_limit,
                COUNT(CASE WHEN DATE(last_message) = CURDATE() THEN 1 END) as active_today,
                ROUND(AVG(count), 2) as avg_messages_per_user,
                MAX(count) as max_messages_single_user
            FROM user_limits
        ");
        $stats['users'] = $stmt->fetch();
        
        // Activity statistics (last 24 hours)
        $stmt = $this->pdo->query("
            SELECT 
                action,
                COUNT(*) as count
            FROM activity_log 
            WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY action
            ORDER BY count DESC
        ");
        $stats['activity_24h'] = $stmt->fetchAll();
        
        // Database size
        $stmt = $this->pdo->query("
            SELECT 
                table_name,
                ROUND(((data_length + index_length) / 1024 / 1024), 2) as size_mb,
                table_rows
            FROM information_schema.tables 
            WHERE table_schema = DATABASE()
            ORDER BY (data_length + index_length) DESC
        ");
        $stats['tables'] = $stmt->fetchAll();
        
        return $stats;
    }
    
    /**
     * Clean old data (maintenance)
     */
    public function cleanOldData($daysOld = 30) {
        $stmt = $this->pdo->prepare("
            DELETE FROM activity_log 
            WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $deletedRows = $stmt->execute([$daysOld]);
        
        // Optimize tables
        $this->pdo->exec("OPTIMIZE TABLE user_limits, activity_log");
        
        return [
            'deleted_rows' => $stmt->rowCount(),
            'tables_optimized' => true
        ];
    }
    
    /**
     * Backup user data to JSON (for rollback)
     */
    public function backupToJson($filePath) {
        $users = $this->getAllUserLimits();
        $backupData = [];
        
        foreach ($users as $user) {
            $backupData[$user['user_id']] = [
                'count' => $user['count'],
                'first_message' => $user['first_message'],
                'last_message' => $user['last_message'],
                'created' => $user['created'],
                'user_id_hash' => $user['user_id_hash'],
                'is_blocked' => $user['is_blocked'],
                'total_attempts' => $user['total_attempts'],
                'grace_period_start' => $user['grace_period_start']
            ];
        }
        
        $result = file_put_contents($filePath, json_encode($backupData, JSON_PRETTY_PRINT));
        
        return [
            'success' => $result !== false,
            'users_backed_up' => count($backupData),
            'file_size' => $result ? filesize($filePath) : 0
        ];
    }
    
    /**
     * Test database connection and performance
     */
    public function testConnection() {
        $startTime = microtime(true);
        
        try {
            // Test connection
            $stmt = $this->pdo->query("SELECT 1 as test");
            $result = $stmt->fetch();
            
            if ($result['test'] !== 1) {
                throw new Exception("Connection test failed");
            }
            
            // Test user_limits table
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM user_limits");
            $userCount = $stmt->fetch()['count'];
            
            // Test activity_log table
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM activity_log");
            $logCount = $stmt->fetch()['count'];
            
            $endTime = microtime(true);
            $queryTime = round(($endTime - $startTime) * 1000, 2);
            
            return [
                'success' => true,
                'user_count' => $userCount,
                'log_count' => $logCount,
                'query_time_ms' => $queryTime,
                'connection_status' => 'active'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'connection_status' => 'failed'
            ];
        }
    }
}

?>