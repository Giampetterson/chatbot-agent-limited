<?php
/**
 * User ID Validator - Server Side
 * Validazione e sanitizzazione degli ID utente dal fingerprinting
 */

class UserValidator {
    
    /**
     * Valida formato user ID generato dal fingerprinting
     */
    public static function validateUserId($userId) {
        // Check basic format
        if (!is_string($userId) || empty($userId)) {
            return false;
        }
        
        // Accept two formats:
        // 1. New format: fp_[64char hash]_[timestamp base36] (length 70-200)
        // 2. Legacy format: [64char hash] (length 64)
        
        $userId = trim($userId);
        
        // Legacy format: Pure SHA-256 hash (64 hex chars)
        if (strlen($userId) === 64 && preg_match('/^[a-f0-9]{64}$/', $userId)) {
            return true; // Accept legacy format from existing users
        }
        
        // Emergency format: fp_emergency_[random]_[timestamp]
        if (strpos($userId, 'fp_emergency_') === 0) {
            // Accept emergency IDs from mobile/restricted browsers
            if (preg_match('/^fp_emergency_[a-z0-9]+_[a-z0-9]+$/', $userId)) {
                return true;
            }
        }
        
        // New format: fp_[hash]_[timestamp]
        if (strlen($userId) >= 70 && strlen($userId) <= 200) {
            // Pattern: fp_[64char hash]_[timestamp base36]
            if (preg_match('/^fp_[a-f0-9]{64}_[a-z0-9]+$/', $userId)) {
                // Validate timestamp
                $parts = explode('_', $userId);
                if (count($parts) === 3) {
                    // Il timestamp dal JS è già in millisecondi
                    $timestamp = intval(base_convert($parts[2], 36, 10));
                    $now = time() * 1000; // Converti PHP time in millisecondi
                    $oneWeekAgo = $now - (7 * 24 * 60 * 60 * 1000);
                    $oneHourFuture = $now + (60 * 60 * 1000);
                    
                    // Accetta timestamp negli ultimi 7 giorni o fino a 1 ora nel futuro
                    if ($timestamp >= $oneWeekAgo && $timestamp <= $oneHourFuture) {
                        return true;
                    }
                    
                    // Log per debug (se timestamp non valido)
                    if (defined('DEBUG_USER_VALIDATION') && DEBUG_USER_VALIDATION) {
                        error_log("UserValidator: Timestamp validation failed - timestamp: $timestamp, now: $now, oneWeekAgo: $oneWeekAgo");
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Sanitizza user ID per uso sicuro
     */
    public static function sanitizeUserId($userId) {
        if (!self::validateUserId($userId)) {
            return null;
        }
        
        // Remove any potential dangerous characters (ma mantieni lettere per emergency IDs)
        $sanitized = preg_replace('/[^a-zA-Z0-9_]/', '', $userId);
        
        return $sanitized;
    }
    
    /**
     * Estrae informazioni dall'user ID
     */
    public static function parseUserId($userId) {
        if (!self::validateUserId($userId)) {
            return null;
        }
        
        $parts = explode('_', $userId);
        $hash = $parts[1];
        $timestamp = base_convert($parts[2], 36, 10);
        
        return [
            'hash' => $hash,
            'timestamp' => $timestamp,
            'created' => date('Y-m-d H:i:s', intval($timestamp / 1000)),
            'age_hours' => (time() * 1000 - $timestamp) / (60 * 60 * 1000)
        ];
    }
    
    /**
     * Genera hash sicuro per storage interno
     */
    public static function hashForStorage($userId) {
        if (!self::validateUserId($userId)) {
            return null;
        }
        
        // Usa hash SHA-256 con salt per storage interno
        $salt = 'rentria_rate_limit_v1_' . date('Y-m-d');
        return hash('sha256', $userId . $salt);
    }
    
    /**
     * Valida e pulisce input da HTTP request
     */
    public static function validateFromRequest($input) {
        // Handle JSON input
        if (is_string($input)) {
            $decoded = json_decode($input, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($decoded['user_id'])) {
                $input = $decoded['user_id'];
            }
        }
        
        // Handle array input
        if (is_array($input) && isset($input['user_id'])) {
            $input = $input['user_id'];
        }
        
        if (!is_string($input)) {
            return null;
        }
        
        // Trim whitespace
        $input = trim($input);
        
        // Validate and sanitize
        return self::sanitizeUserId($input);
    }
    
    /**
     * Verifica se l'user ID è troppo vecchio (per eventuali cleanup futuri)
     */
    public static function isExpired($userId, $maxAgeHours = 168) { // 7 giorni default
        $info = self::parseUserId($userId);
        if (!$info) {
            return true; // Consider invalid IDs as expired
        }
        
        return $info['age_hours'] > $maxAgeHours;
    }
    
    /**
     * Rate limiting per richieste di validazione (anti-spam)
     */
    public static function checkValidationRate($clientIp) {
        $rateLimitFile = sys_get_temp_dir() . '/validation_rate_' . md5($clientIp);
        
        if (file_exists($rateLimitFile)) {
            $data = json_decode(file_get_contents($rateLimitFile), true);
            
            // Max 60 validation requests per minute
            if ($data['count'] > 60 && (time() - $data['start']) < 60) {
                return false;
            }
            
            // Reset counter if minute passed
            if ((time() - $data['start']) >= 60) {
                $data = ['count' => 0, 'start' => time()];
            }
        } else {
            $data = ['count' => 0, 'start' => time()];
        }
        
        $data['count']++;
        file_put_contents($rateLimitFile, json_encode($data), LOCK_EX);
        
        return true;
    }
    
    /**
     * Log per debugging e monitoring
     */
    public static function logValidation($userId, $isValid, $clientIp = null) {
        if (!$clientIp) {
            $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }
        
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $userId ? substr($userId, 0, 20) . '...' : 'null',
            'is_valid' => $isValid,
            'client_ip' => $clientIp,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        // Log to file (only in debug mode)
        if (defined('DEBUG_USER_VALIDATION') && DEBUG_USER_VALIDATION) {
            error_log("UserValidator: " . json_encode($logEntry));
        }
        
        return $logEntry;
    }
}

// Utility function per uso rapido
function validate_user_id($userId) {
    return UserValidator::validateUserId($userId);
}

function sanitize_user_id($userId) {
    return UserValidator::sanitizeUserId($userId);
}

?>