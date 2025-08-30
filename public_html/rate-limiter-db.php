<?php
/**
 * RateLimiter Service v2.0 - Database Edition
 * Servizio dedicato per controllo limiti messaggi utenti
 * Database-powered per scalabilità e performance
 * 
 * Created: 2025-08-29
 * Replaces: rate-limiter.php (file-based)
 */

require_once 'database.php';
require_once 'user-validator.php';

class RateLimiterDB {
    
    private $db;
    private $maxMessages;
    private $gracePeriodMinutes;
    
    // Response codes standardizzati
    const STATUS_ALLOWED = 'allowed';
    const STATUS_LIMIT_REACHED = 'limit_reached';
    const STATUS_GRACE_PERIOD = 'grace_period';
    const STATUS_PERMANENTLY_BLOCKED = 'permanently_blocked';
    const STATUS_INVALID_USER = 'invalid_user';
    const STATUS_ERROR = 'error';
    
    public function __construct($maxMessages = null, $gracePeriodMinutes = null) {
        $this->db = DatabaseManager::getInstance();
        
        // Load configuration
        require_once 'config-loader.php';
        $config = ConfigLoader::getInstance();
        
        // Use provided values or load from config
        $this->maxMessages = $maxMessages ?? (int)$config->get('RATE_LIMIT_MAX_MESSAGES', 5);
        $this->gracePeriodMinutes = $gracePeriodMinutes ?? (int)$config->get('RATE_LIMIT_GRACE_PERIOD_MINUTES', 1);
    }
    
    /**
     * Ottieni stato corrente senza incrementare contatore
     * 
     * @param string $userId ID univoco dell'utente
     * @return array Response strutturata
     */
    public function getStatus($userId) {
        $startTime = microtime(true);
        
        try {
            // Valida user ID
            if (!UserValidator::validateUserId($userId)) {
                return $this->createResponse(
                    self::STATUS_INVALID_USER,
                    false,
                    'Invalid user ID format',
                    0,
                    $this->maxMessages,
                    $startTime
                );
            }
            
            // Ottieni dati utente dal database
            $user = $this->db->getUserLimits($userId);
            
            if (!$user) {
                // Nuovo utente - ritorna stato iniziale
                return $this->createResponse(
                    self::STATUS_ALLOWED,
                    true,
                    'New user',
                    0,
                    $this->maxMessages,
                    $startTime
                );
            }
            
            // Usa max_count dal database se presente
            $userMaxMessages = $user['max_count'] ?? $this->maxMessages;
            
            // Determina stato senza modificare
            if ($user['is_blocked']) {
                return $this->createResponse(
                    self::STATUS_PERMANENTLY_BLOCKED,
                    false,
                    'La prova è scaduta, accedi a Rentri360.it e semplifica le tue procedure',
                    $user['count'],
                    $userMaxMessages,
                    $startTime
                );
            }
            
            if ($user['count'] >= $userMaxMessages) {
                return $this->createResponse(
                    self::STATUS_LIMIT_REACHED,
                    false,
                    'Limite raggiunto',
                    $user['count'],
                    $userMaxMessages,
                    $startTime
                );
            }
            
            return $this->createResponse(
                self::STATUS_ALLOWED,
                true,
                'Can send',
                $user['count'],
                $userMaxMessages,
                $startTime
            );
            
        } catch (Exception $e) {
            error_log("RateLimiter getStatus error: " . $e->getMessage());
            return $this->createResponse(
                self::STATUS_ERROR,
                false,
                'Internal system error',
                0,
                $this->maxMessages,
                $startTime
            );
        }
    }
    
    /**
     * Verifica se l'utente può inviare un messaggio
     * 
     * @param string $userId ID univoco dell'utente
     * @param string $adminBypass Token admin per bypass (opzionale)
     * @return array Response strutturata
     */
    public function checkLimit($userId, $adminBypass = null) {
        $startTime = microtime(true);
        
        try {
            // Valida user ID
            if (!UserValidator::validateUserId($userId)) {
                return $this->createResponse(
                    self::STATUS_INVALID_USER,
                    false,
                    'Invalid user ID format',
                    0,
                    $this->maxMessages,
                    $startTime
                );
            }
            
            // Admin bypass check
            if ($this->checkAdminBypass($userId, $adminBypass)) {
                $this->logActivity($userId, 'admin_bypass', 'rate_limiter');
                return $this->createResponse(
                    self::STATUS_ALLOWED,
                    true,
                    'Admin bypass granted',
                    0,
                    $this->maxMessages,
                    $startTime
                );
            }
            
            // Ottieni dati utente dal database
            $user = $this->db->getUserLimits($userId);
            
            if (!$user) {
                // Nuovo utente - crea record
                $userData = [
                    'count' => 1,
                    'total_attempts' => 1,
                    'first_message' => date('Y-m-d H:i:s'),
                    'last_message' => date('Y-m-d H:i:s'),
                    'is_blocked' => false
                ];
                
                $this->db->updateUserLimits($userId, $userData);
                $this->logActivity($userId, 'first_message', 'rate_limiter');
                
                return $this->createResponse(
                    self::STATUS_ALLOWED,
                    true,
                    'First message allowed',
                    1,
                    $this->maxMessages,
                    $startTime
                );
            }
            
            // Utente esistente - verifica stato
            // Usa max_count dal database se presente
            $userMaxMessages = $user['max_count'] ?? $this->maxMessages;
            
            if ($user['is_blocked']) {
                $this->logActivity($userId, 'blocked_attempt', 'rate_limiter');
                return $this->createResponse(
                    self::STATUS_PERMANENTLY_BLOCKED,
                    false,
                    'La prova è scaduta, accedi a Rentri360.it e semplifica le tue procedure',
                    $user['count'],
                    $userMaxMessages,
                    $startTime
                );
            }
            
            // Verifica grace period
            if ($user['grace_period_start']) {
                $graceStart = new DateTime($user['grace_period_start']);
                $now = new DateTime();
                $minutesPassed = ($now->getTimestamp() - $graceStart->getTimestamp()) / 60;
                
                if ($minutesPassed < $this->gracePeriodMinutes) {
                    $remainingMinutes = $this->gracePeriodMinutes - $minutesPassed;
                    return $this->createResponse(
                        self::STATUS_GRACE_PERIOD,
                        false,
                        "Grace period active. Wait " . ceil($remainingMinutes) . " more minute(s)",
                        $user['count'],
                        $userMaxMessages,
                        $startTime,
                        ['grace_remaining_minutes' => ceil($remainingMinutes)]
                    );
                } else {
                    // Grace period scaduto - blocca permanentemente
                    $this->blockUser($userId);
                    $this->logActivity($userId, 'permanently_blocked', 'rate_limiter');
                    
                    return $this->createResponse(
                        self::STATUS_PERMANENTLY_BLOCKED,
                        false,
                        'La prova è scaduta, accedi a Rentri360.it e semplifica le tue procedure.',
                        $user['count'],
                        $userMaxMessages,
                        $startTime
                    );
                }
            }
            
            // Controlla se ha raggiunto il limite
            if ($user['count'] >= $userMaxMessages) {
                // Avvia grace period
                $userData = [
                    'count' => $user['count'] + 1,
                    'total_attempts' => $user['total_attempts'] + 1,
                    'last_message' => date('Y-m-d H:i:s'),
                    'grace_period_start' => date('Y-m-d H:i:s'),
                    'is_blocked' => false
                ];
                
                $this->db->updateUserLimits($userId, $userData);
                $this->logActivity($userId, 'grace_period_started', 'rate_limiter');
                
                return $this->createResponse(
                    self::STATUS_LIMIT_REACHED,
                    false,
                    'Hai raggiunto il limite di 5 messaggi, iscriviti a Rentri360.it https://www.rentri360.it',
                    $userData['count'],
                    $this->maxMessages,
                    $startTime
                );
            }
            
            // Messaggio consentito - incrementa counter
            $userData = [
                'count' => $user['count'] + 1,
                'total_attempts' => $user['total_attempts'] + 1,
                'last_message' => date('Y-m-d H:i:s'),
                'is_blocked' => false
            ];
            
            $this->db->updateUserLimits($userId, $userData);
            $this->logActivity($userId, 'message_allowed', 'rate_limiter');
            
            return $this->createResponse(
                self::STATUS_ALLOWED,
                true,
                'Message allowed',
                $userData['count'],
                $this->maxMessages,
                $startTime
            );
            
        } catch (Exception $e) {
            error_log("RateLimiter error: " . $e->getMessage());
            $this->logActivity($userId, 'system_error', 'rate_limiter', [
                'error_message' => $e->getMessage()
            ]);
            
            return $this->createResponse(
                self::STATUS_ERROR,
                false,
                'Internal system error',
                0,
                $this->maxMessages,
                $startTime
            );
        }
    }
    
    /**
     * Blocca un utente permanentemente
     */
    private function blockUser($userId) {
        $user = $this->db->getUserLimits($userId);
        if ($user) {
            $userData = [
                'count' => $user['count'],
                'total_attempts' => $user['total_attempts'],
                'last_message' => date('Y-m-d H:i:s'),
                'is_blocked' => true,
                'grace_period_start' => $user['grace_period_start']
            ];
            
            $this->db->updateUserLimits($userId, $userData);
        }
    }
    
    /**
     * Reset utente (solo per admin)
     */
    public function resetUser($userId, $adminToken = null) {
        try {
            if (!$this->checkAdminBypass($userId, $adminToken)) {
                return [
                    'success' => false,
                    'message' => 'Unauthorized: Invalid admin token'
                ];
            }
            
            $userData = [
                'count' => 0,
                'total_attempts' => 0,
                'last_message' => date('Y-m-d H:i:s'),
                'is_blocked' => false,
                'grace_period_start' => null
            ];
            
            $this->db->updateUserLimits($userId, $userData);
            $this->logActivity($userId, 'admin_reset', 'rate_limiter');
            
            return [
                'success' => true,
                'message' => 'User reset successfully'
            ];
            
        } catch (Exception $e) {
            error_log("Reset user error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Reset failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Ottieni statistiche sistema
     */
    public function getSystemStats() {
        try {
            $stats = $this->db->getSystemStats();
            $stats['rate_limiter'] = [
                'max_messages' => $this->maxMessages,
                'grace_period_minutes' => $this->gracePeriodMinutes,
                'version' => '2.0-database'
            ];
            
            return $stats;
        } catch (Exception $e) {
            error_log("Get stats error: " . $e->getMessage());
            return [
                'error' => 'Could not retrieve stats: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Check admin bypass
     */
    private function checkAdminBypass($userId, $adminToken) {
        if (empty($adminToken) || !is_string($adminToken)) {
            return $adminToken === true; // Se è true, è admin bypass diretto
        }
        
        // Load configuration for admin secret
        require_once 'config-loader.php';
        $config = ConfigLoader::getInstance();
        $adminSecret = $config->get('ADMIN_SECRET', '');
        
        if (empty($adminSecret)) {
            return false;
        }
        
        $expectedToken = hash_hmac('sha256', date('Y-m-d') . $userId, $adminSecret);
        return hash_equals($expectedToken, $adminToken);
    }
    
    /**
     * Incrementa il contatore di utilizzo per un utente
     * 
     * @param string $userId ID univoco dell'utente
     * @param string $adminBypass Token admin per bypass (opzionale)
     * @return array Response strutturata
     */
    public function incrementUsage($userId, $adminBypass = null) {
        $startTime = microtime(true);
        
        try {
            // Valida user ID
            if (!UserValidator::validateUserId($userId)) {
                return $this->createResponse(
                    self::STATUS_INVALID_USER,
                    false,
                    'Invalid user ID format',
                    0,
                    $this->maxMessages,
                    $startTime
                );
            }
            
            // Admin bypass check
            if ($this->checkAdminBypass($userId, $adminBypass)) {
                $this->logActivity($userId, 'admin_bypass', 'rate_limiter');
                return $this->createResponse(
                    self::STATUS_ALLOWED,
                    true,
                    'Admin bypass granted',
                    0,
                    $this->maxMessages,
                    $startTime
                );
            }
            
            // Ottieni dati utente dal database
            $user = $this->db->getUserLimits($userId);
            
            if (!$user) {
                // Nuovo utente - crea record con contatore a 1
                $userData = [
                    'count' => 1,
                    'total_attempts' => 1,
                    'first_message' => date('Y-m-d H:i:s'),
                    'last_message' => date('Y-m-d H:i:s'),
                    'is_blocked' => 0
                ];
                
                $this->db->updateUserLimits($userId, $userData);
                $this->logActivity($userId, 'first_message', 'rate_limiter');
                
                return $this->createResponse(
                    self::STATUS_ALLOWED,
                    true,
                    'First message allowed',
                    1,
                    $this->maxMessages,
                    $startTime
                );
            }
            
            // Controlla il limite PRIMA di incrementare
            if ($user['count'] >= $this->maxMessages) {
                // Aggiorna solo total_attempts senza incrementare count
                $userData = [
                    'total_attempts' => $user['total_attempts'] + 1,
                    'last_message' => date('Y-m-d H:i:s'),
                    'is_blocked' => 1
                ];
                
                $this->db->updateUserLimits($userId, $userData);
                $this->logActivity($userId, 'rate_limit_exceeded', 'rate_limiter', ['count' => $user['count']]);
                
                return $this->createResponse(
                    self::STATUS_LIMIT_REACHED,
                    false,
                    'Rate limit exceeded',
                    $user['count'],
                    $this->maxMessages,
                    $startTime
                );
            }
            
            // Incrementa il contatore solo se sotto il limite
            $newCount = $user['count'] + 1;
            $userData = [
                'count' => $newCount,
                'total_attempts' => $user['total_attempts'] + 1,
                'last_message' => date('Y-m-d H:i:s'),
                'is_blocked' => ($newCount >= $this->maxMessages) ? 1 : 0
            ];
            
            $this->db->updateUserLimits($userId, $userData);
            $this->logActivity($userId, 'message_sent', 'rate_limiter', ['count' => $newCount]);
            
            return $this->createResponse(
                self::STATUS_ALLOWED,
                true,
                'Message allowed',
                $newCount,
                $this->maxMessages,
                $startTime
            );
            
        } catch (Exception $e) {
            error_log("RateLimiter incrementUsage error: " . $e->getMessage());
            return $this->createResponse(
                'error',
                false,
                'Internal system error',
                0,
                $this->maxMessages,
                $startTime
            );
        }
    }
    
    /**
     * Log activity nel database
     */
    private function logActivity($userId, $action, $component, $data = []) {
        try {
            $this->db->logActivity($userId, $action, $component, $data);
        } catch (Exception $e) {
            error_log("Activity logging failed: " . $e->getMessage());
            // Non lanciare eccezione per evitare di bloccare il flusso principale
        }
    }
    
    /**
     * Crea response strutturata
     */
    private function createResponse($status, $canSend, $message, $count, $maxCount, $startTime, $extraData = []) {
        $processingTime = round((microtime(true) - $startTime) * 1000, 2);
        
        $response = [
            'status' => $status,
            'can_send' => $canSend,
            'message' => $message,
            'count' => $count,
            'max_count' => $maxCount,
            'timestamp' => date('Y-m-d H:i:s'),
            'processing_time_ms' => $processingTime
        ];
        
        if (!empty($extraData)) {
            $response['data'] = $extraData;
        }
        
        return $response;
    }
}

?>