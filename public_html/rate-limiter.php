<?php
/**
 * RateLimiter Service v1.0
 * Servizio dedicato per controllo limiti messaggi utenti
 * Limite: 10 messaggi permanenti + grace period 1 minuto
 */

require_once 'user-storage.php';
require_once 'user-validator.php';

class RateLimiter {
    
    private $storage;
    private $maxMessages;
    private $gracePeriodMinutes;
    
    // Response codes standardizzati
    const STATUS_ALLOWED = 'allowed';
    const STATUS_LIMIT_REACHED = 'limit_reached';
    const STATUS_GRACE_PERIOD = 'grace_period';
    const STATUS_PERMANENTLY_BLOCKED = 'permanently_blocked';
    const STATUS_INVALID_USER = 'invalid_user';
    const STATUS_ERROR = 'error';
    
    public function __construct($maxMessages = 10, $gracePeriodMinutes = 1) {
        $this->storage = new UserStorage();
        $this->maxMessages = $maxMessages;
        $this->gracePeriodMinutes = $gracePeriodMinutes;
    }
    
    /**
     * Verifica se l'utente può inviare un messaggio
     * 
     * @param string $userId ID univoco dell'utente
     * @param string $adminBypass Token admin per bypass (opzionale)
     * @return array Response strutturata
     */
    public function checkLimit($userId, $adminBypass = null) {
        try {
            // Valida user ID
            if (!UserValidator::validateUserId($userId)) {
                return $this->createResponse(
                    self::STATUS_INVALID_USER,
                    false,
                    'Invalid user ID format',
                    0,
                    $this->maxMessages
                );
            }
            
            // Check admin bypass
            if ($adminBypass && $this->storage->adminBypass($userId, $adminBypass)) {
                return $this->createResponse(
                    self::STATUS_ALLOWED,
                    true,
                    'Admin bypass active',
                    0,
                    $this->maxMessages,
                    null,
                    ['admin_bypass' => true]
                );
            }
            
            // Ottieni stato utente dal storage
            $canSendResult = $this->storage->canUserSendMessage($userId);
            
            // Converte response storage in format standardizzato
            return $this->convertStorageResponse($canSendResult);
            
        } catch (Exception $e) {
            error_log("RateLimiter::checkLimit Error: " . $e->getMessage());
            
            return $this->createResponse(
                self::STATUS_ERROR,
                false,
                'System error: ' . $e->getMessage(),
                0,
                $this->maxMessages
            );
        }
    }
    
    /**
     * Incrementa il contatore di utilizzo per l'utente
     * 
     * @param string $userId ID univoco dell'utente
     * @param string $adminBypass Token admin per bypass (opzionale)
     * @return array Response strutturata
     */
    public function incrementUsage($userId, $adminBypass = null) {
        try {
            // Valida user ID
            if (!UserValidator::validateUserId($userId)) {
                return $this->createResponse(
                    self::STATUS_INVALID_USER,
                    false,
                    'Invalid user ID format',
                    0,
                    $this->maxMessages
                );
            }
            
            // Check admin bypass
            if ($adminBypass && $this->storage->adminBypass($userId, $adminBypass)) {
                return $this->createResponse(
                    self::STATUS_ALLOWED,
                    true,
                    'Admin bypass - increment skipped',
                    0,
                    $this->maxMessages,
                    null,
                    ['admin_bypass' => true, 'increment_skipped' => true]
                );
            }
            
            // Prima verifica se può inviare
            $checkResult = $this->checkLimit($userId);
            
            if (!$checkResult['can_send']) {
                return $checkResult; // Ritorna il motivo del blocco
            }
            
            // Incrementa counter
            $record = $this->storage->incrementUserMessageCount($userId);
            
            // Determina nuovo stato dopo increment
            $newStatus = self::STATUS_ALLOWED;
            $newMessage = 'Message counted successfully';
            
            if ($record['count'] >= $this->maxMessages) {
                $newStatus = self::STATUS_LIMIT_REACHED;
                $newMessage = 'Hai raggiunto il limite di 10 messaggi, iscriviti a Rentri360.it https://www.rentri360.it';
                
                // Se ha appena raggiunto il limite, inizia grace period
                if ($record['count'] === $this->maxMessages) {
                    $newMessage = "Limite raggiunto. Hai {$this->gracePeriodMinutes} minuto/i per completare la conversazione.";
                }
            }
            
            return $this->createResponse(
                $newStatus,
                true, // Increment eseguito con successo
                $newMessage,
                $record['count'],
                $this->maxMessages,
                null,
                [
                    'incremented' => true,
                    'first_message' => $record['first_message'],
                    'last_message' => $record['last_message']
                ]
            );
            
        } catch (Exception $e) {
            error_log("RateLimiter::incrementUsage Error: " . $e->getMessage());
            
            return $this->createResponse(
                self::STATUS_ERROR,
                false,
                'System error during increment: ' . $e->getMessage(),
                0,
                $this->maxMessages
            );
        }
    }
    
    /**
     * Verifica se l'utente è completamente bloccato
     * 
     * @param string $userId ID univoco dell'utente
     * @return array Response strutturata
     */
    public function isBlocked($userId) {
        try {
            // Valida user ID
            if (!UserValidator::validateUserId($userId)) {
                return $this->createResponse(
                    self::STATUS_INVALID_USER,
                    false,
                    'Invalid user ID format',
                    0,
                    $this->maxMessages
                );
            }
            
            // Ottieni record utente
            $record = $this->storage->getUserRecord($userId);
            
            // Check se bloccato permanentemente
            if ($record['is_blocked']) {
                return $this->createResponse(
                    self::STATUS_PERMANENTLY_BLOCKED,
                    false,
                    'Hai raggiunto il limite di 10 messaggi, iscriviti a Rentri360.it https://www.rentri360.it',
                    $record['count'],
                    $this->maxMessages,
                    null,
                    [
                        'blocked_at' => $record['blocked_at'] ?? null,
                        'is_permanently_blocked' => true
                    ]
                );
            }
            
            // Check limite messaggi con grace period
            $canSendResult = $this->storage->canUserSendMessage($userId);
            
            if (!$canSendResult['can_send']) {
                return $this->convertStorageResponse($canSendResult);
            }
            
            // Non bloccato
            return $this->createResponse(
                self::STATUS_ALLOWED,
                true,
                'User not blocked',
                $record['count'],
                $this->maxMessages
            );
            
        } catch (Exception $e) {
            error_log("RateLimiter::isBlocked Error: " . $e->getMessage());
            
            return $this->createResponse(
                self::STATUS_ERROR,
                false,
                'System error: ' . $e->getMessage(),
                0,
                $this->maxMessages
            );
        }
    }
    
    /**
     * Ottiene statistiche complete per l'utente
     * 
     * @param string $userId ID univoco dell'utente
     * @return array Response con statistiche dettagliate
     */
    public function getUserStats($userId) {
        try {
            if (!UserValidator::validateUserId($userId)) {
                return $this->createResponse(
                    self::STATUS_INVALID_USER,
                    false,
                    'Invalid user ID format',
                    0,
                    $this->maxMessages
                );
            }
            
            $record = $this->storage->getUserRecord($userId);
            $canSend = $this->storage->canUserSendMessage($userId);
            
            return $this->createResponse(
                $canSend['can_send'] ? self::STATUS_ALLOWED : self::STATUS_PERMANENTLY_BLOCKED,
                $canSend['can_send'],
                $canSend['message'] ?? 'User statistics',
                $record['count'],
                $this->maxMessages,
                null,
                [
                    'user_record' => $record,
                    'can_send_details' => $canSend,
                    'remaining_messages' => max(0, $this->maxMessages - $record['count']),
                    'usage_percentage' => round(($record['count'] / $this->maxMessages) * 100, 1)
                ]
            );
            
        } catch (Exception $e) {
            error_log("RateLimiter::getUserStats Error: " . $e->getMessage());
            
            return $this->createResponse(
                self::STATUS_ERROR,
                false,
                'System error: ' . $e->getMessage(),
                0,
                $this->maxMessages
            );
        }
    }
    
    /**
     * Ottiene statistiche globali del sistema
     * 
     * @return array Statistiche sistema
     */
    public function getSystemStats() {
        try {
            $storageStats = $this->storage->getStorageStats();
            
            return [
                'success' => true,
                'system_stats' => $storageStats,
                'configuration' => [
                    'max_messages' => $this->maxMessages,
                    'grace_period_minutes' => $this->gracePeriodMinutes
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            error_log("RateLimiter::getSystemStats Error: " . $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    /**
     * Converte response dal storage in formato standardizzato
     */
    private function convertStorageResponse($storageResult) {
        $status = self::STATUS_ALLOWED;
        $message = $storageResult['message'] ?? 'Message allowed';
        
        switch ($storageResult['reason']) {
            case 'within_limit':
                $status = self::STATUS_ALLOWED;
                break;
                
            case 'grace_period':
                $status = self::STATUS_GRACE_PERIOD;
                break;
                
            case 'limit_reached':
            case 'permanently_blocked':
                $status = self::STATUS_PERMANENTLY_BLOCKED;
                break;
                
            default:
                $status = self::STATUS_ERROR;
        }
        
        $extras = [];
        if (isset($storageResult['grace_remaining_minutes'])) {
            $extras['grace_remaining_minutes'] = $storageResult['grace_remaining_minutes'];
        }
        if (isset($storageResult['remaining'])) {
            $extras['remaining_messages'] = $storageResult['remaining'];
        }
        
        return $this->createResponse(
            $status,
            $storageResult['can_send'],
            $message,
            $storageResult['count'],
            $storageResult['max_count'],
            null,
            $extras
        );
    }
    
    /**
     * Crea response strutturata standardizzata
     */
    private function createResponse($status, $canSend, $message, $count, $maxCount, $error = null, $extras = []) {
        $response = [
            'status' => $status,
            'can_send' => $canSend,
            'message' => $message,
            'count' => $count,
            'max_count' => $maxCount,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if ($error) {
            $response['error'] = $error;
        }
        
        // Aggiungi extra data
        if (!empty($extras)) {
            $response['data'] = $extras;
        }
        
        return $response;
    }
    
    /**
     * Reset utente (solo con admin bypass)
     */
    public function resetUser($userId, $adminToken) {
        try {
            $result = $this->storage->resetUser($userId, $adminToken);
            
            if ($result) {
                return $this->createResponse(
                    self::STATUS_ALLOWED,
                    true,
                    'User reset successfully',
                    0,
                    $this->maxMessages,
                    null,
                    ['admin_reset' => true]
                );
            } else {
                return $this->createResponse(
                    self::STATUS_ERROR,
                    false,
                    'Reset failed - invalid admin token or user not found',
                    0,
                    $this->maxMessages
                );
            }
            
        } catch (Exception $e) {
            return $this->createResponse(
                self::STATUS_ERROR,
                false,
                'Reset error: ' . $e->getMessage(),
                0,
                $this->maxMessages
            );
        }
    }
}

?>