<?php
/**
 * User Storage Manager v1.0
 * Sistema di storage permanente per tracking utenti rate limiting
 * NESSUN CLEANUP AUTOMATICO - dopo 10 messaggi l'utente è permanentemente bloccato
 */

require_once 'user-validator.php';

class UserStorage {
    
    private $storageDir;
    private $dataFile;
    private $lockFile;
    private $maxMessageCount;
    private $gracePeroidMinutes;
    
    public function __construct($storageDir = null, $maxMessages = 10, $gracePeriod = 1) {
        $this->storageDir = $storageDir ?: '/var/www/lightbot.rentri360.it/private';
        $this->dataFile = $this->storageDir . '/user_limits.json';
        $this->lockFile = $this->storageDir . '/user_limits.lock';
        $this->maxMessageCount = $maxMessages;
        $this->gracePeroidMinutes = $gracePeriod;
        
        $this->initializeStorage();
    }
    
    /**
     * Inizializza directory e file storage
     */
    private function initializeStorage() {
        // Verifica directory esiste
        if (!is_dir($this->storageDir)) {
            if (!mkdir($this->storageDir, 0750, true)) {
                throw new Exception("Cannot create storage directory: {$this->storageDir}");
            }
        }
        
        // Verifica permessi directory
        if (!is_writable($this->storageDir)) {
            throw new Exception("Storage directory not writable: {$this->storageDir}");
        }
        
        // Crea file dati se non exists
        if (!file_exists($this->dataFile)) {
            $this->writeDataFile([]);
        }
    }
    
    /**
     * Acquisisce lock esclusivo per operazioni atomiche
     */
    private function acquireLock() {
        $lockHandle = fopen($this->lockFile, 'c+');
        if (!$lockHandle) {
            throw new Exception("Cannot create lock file");
        }
        
        $timeout = 5; // 5 secondi timeout
        $acquired = false;
        $start = time();
        
        while (!$acquired && (time() - $start) < $timeout) {
            $acquired = flock($lockHandle, LOCK_EX | LOCK_NB);
            if (!$acquired) {
                usleep(50000); // 50ms wait
            }
        }
        
        if (!$acquired) {
            fclose($lockHandle);
            throw new Exception("Cannot acquire file lock within timeout");
        }
        
        return $lockHandle;
    }
    
    /**
     * Rilascia lock
     */
    private function releaseLock($lockHandle) {
        if ($lockHandle) {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
            @unlink($this->lockFile);
        }
    }
    
    /**
     * Legge file dati con handling sicuro
     */
    private function readDataFile() {
        if (!file_exists($this->dataFile)) {
            return [];
        }
        
        $content = file_get_contents($this->dataFile);
        if ($content === false) {
            throw new Exception("Cannot read data file");
        }
        
        if (empty(trim($content))) {
            return [];
        }
        
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // File corrotto, backup e reset
            $backupFile = $this->dataFile . '.backup.' . date('Y-m-d_H-i-s');
            copy($this->dataFile, $backupFile);
            error_log("UserStorage: Corrupted data file backed up to: $backupFile");
            return [];
        }
        
        return $data;
    }
    
    /**
     * Scrive file dati con permessi sicuri
     */
    private function writeDataFile($data) {
        $jsonData = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($jsonData === false) {
            throw new Exception("Cannot encode data to JSON");
        }
        
        // Scrivi in file temporaneo poi sposta (operazione atomica)
        $tempFile = $this->dataFile . '.tmp.' . uniqid();
        
        if (file_put_contents($tempFile, $jsonData, LOCK_EX) === false) {
            throw new Exception("Cannot write temporary data file");
        }
        
        // Imposta permessi sicuri
        chmod($tempFile, 0600);
        
        // Sposta atomicamente
        if (!rename($tempFile, $this->dataFile)) {
            @unlink($tempFile);
            throw new Exception("Cannot move temporary file to data file");
        }
        
        return true;
    }
    
    /**
     * Ottiene record utente o crea nuovo se non esiste
     */
    public function getUserRecord($userId) {
        // Valida user ID
        if (!UserValidator::validateUserId($userId)) {
            throw new Exception("Invalid user ID format");
        }
        
        $storageHash = UserValidator::hashForStorage($userId);
        $lockHandle = $this->acquireLock();
        
        try {
            $data = $this->readDataFile();
            
            if (!isset($data[$storageHash])) {
                // Nuovo utente - crea record iniziale
                $data[$storageHash] = [
                    'count' => 0,
                    'first_message' => null,
                    'last_message' => null,
                    'created' => date('Y-m-d H:i:s'),
                    'user_id_hash' => substr($userId, 0, 20) . '...', // Solo per debug
                    'is_blocked' => false,
                    'total_attempts' => 0
                ];
                
                $this->writeDataFile($data);
            }
            
            return $data[$storageHash];
            
        } finally {
            $this->releaseLock($lockHandle);
        }
    }
    
    /**
     * Verifica se utente può inviare messaggi
     */
    public function canUserSendMessage($userId) {
        $record = $this->getUserRecord($userId);
        
        // Se già bloccato, sempre false
        if ($record['is_blocked']) {
            return [
                'can_send' => false,
                'reason' => 'permanently_blocked',
                'count' => $record['count'],
                'max_count' => $this->maxMessageCount,
                'message' => 'Hai raggiunto il limite di 5 messaggi, iscriviti a Rentri360.it https://www.rentri360.it'
            ];
        }
        
        // Se ha raggiunto o superato il limite, controlla grace period
        if ($record['count'] >= $this->maxMessageCount) {
            $gracePeriodExpired = true;
            
            if ($record['last_message']) {
                $lastMessageTime = strtotime($record['last_message']);
                $graceExpiry = $lastMessageTime + ($this->gracePeroidMinutes * 60);
                $gracePeriodExpired = time() > $graceExpiry;
            }
            
            if ($gracePeriodExpired) {
                // Grace period scaduto - blocca permanentemente
                $this->blockUserPermanently($userId);
                return [
                    'can_send' => false,
                    'reason' => 'limit_reached',
                    'count' => $record['count'],
                    'max_count' => $this->maxMessageCount,
                    'message' => 'Hai raggiunto il limite di 5 messaggi, iscriviti a Rentri360.it https://www.rentri360.it'
                ];
            } else {
                // Ancora nel grace period - ma NON può inviare nuovi messaggi
                $remainingMinutes = ceil(($graceExpiry - time()) / 60);
                return [
                    'can_send' => false,
                    'reason' => 'grace_period',
                    'count' => $record['count'],
                    'max_count' => $this->maxMessageCount,
                    'grace_remaining_minutes' => $remainingMinutes,
                    'message' => "Hai raggiunto il limite di 5 messaggi, iscriviti a Rentri360.it https://www.rentri360.it"
                ];
            }
        }
        
        // Può inviare messaggi
        return [
            'can_send' => true,
            'reason' => 'within_limit',
            'count' => $record['count'],
            'max_count' => $this->maxMessageCount,
            'remaining' => $this->maxMessageCount - $record['count']
        ];
    }
    
    /**
     * Incrementa counter messaggi per utente
     */
    public function incrementUserMessageCount($userId) {
        $storageHash = UserValidator::hashForStorage($userId);
        $lockHandle = $this->acquireLock();
        
        try {
            $data = $this->readDataFile();
            
            if (!isset($data[$storageHash])) {
                throw new Exception("User record not found for increment");
            }
            
            $now = date('Y-m-d H:i:s');
            
            // Incrementa contatori
            $data[$storageHash]['count']++;
            $data[$storageHash]['total_attempts']++;
            $data[$storageHash]['last_message'] = $now;
            
            // Se è il primo messaggio, imposta first_message
            if ($data[$storageHash]['count'] === 1) {
                $data[$storageHash]['first_message'] = $now;
            }
            
            // Se ha raggiunto il limite esatto, inizia grace period
            if ($data[$storageHash]['count'] === $this->maxMessageCount) {
                $data[$storageHash]['grace_period_start'] = $now;
            }
            
            $this->writeDataFile($data);
            
            return $data[$storageHash];
            
        } finally {
            $this->releaseLock($lockHandle);
        }
    }
    
    /**
     * Blocca utente permanentemente
     */
    public function blockUserPermanently($userId) {
        $storageHash = UserValidator::hashForStorage($userId);
        $lockHandle = $this->acquireLock();
        
        try {
            $data = $this->readDataFile();
            
            if (isset($data[$storageHash])) {
                $data[$storageHash]['is_blocked'] = true;
                $data[$storageHash]['blocked_at'] = date('Y-m-d H:i:s');
                $this->writeDataFile($data);
                
                error_log("UserStorage: User permanently blocked - " . substr($userId, 0, 20));
            }
            
        } finally {
            $this->releaseLock($lockHandle);
        }
    }
    
    /**
     * Statistiche storage per monitoring
     */
    public function getStorageStats() {
        $lockHandle = $this->acquireLock();
        
        try {
            $data = $this->readDataFile();
            
            $stats = [
                'total_users' => count($data),
                'blocked_users' => 0,
                'active_users' => 0,
                'users_at_limit' => 0,
                'total_messages' => 0,
                'file_size' => file_exists($this->dataFile) ? filesize($this->dataFile) : 0,
                'last_modified' => file_exists($this->dataFile) ? date('Y-m-d H:i:s', filemtime($this->dataFile)) : null
            ];
            
            foreach ($data as $record) {
                if ($record['is_blocked']) {
                    $stats['blocked_users']++;
                } else {
                    $stats['active_users']++;
                }
                
                if ($record['count'] >= $this->maxMessageCount) {
                    $stats['users_at_limit']++;
                }
                
                $stats['total_messages'] += $record['count'];
            }
            
            return $stats;
            
        } finally {
            $this->releaseLock($lockHandle);
        }
    }
    
    /**
     * Admin bypass per testing (DEVE essere sicuro)
     */
    public function adminBypass($userId, $adminToken) {
        // Token sicuro basato su data + secret
        $expectedToken = hash_hmac('sha256', 
            date('Y-m-d') . $userId, 
            'rentria_admin_secret_2025_' . $_SERVER['SERVER_NAME']
        );
        
        if (!hash_equals($expectedToken, $adminToken)) {
            error_log("UserStorage: Invalid admin bypass attempt for user " . substr($userId, 0, 20));
            return false;
        }
        
        error_log("UserStorage: Admin bypass used for user " . substr($userId, 0, 20));
        return true;
    }
    
    /**
     * Reset utente per admin (solo con bypass valido)
     */
    public function resetUser($userId, $adminToken) {
        if (!$this->adminBypass($userId, $adminToken)) {
            throw new Exception("Invalid admin token");
        }
        
        $storageHash = UserValidator::hashForStorage($userId);
        $lockHandle = $this->acquireLock();
        
        try {
            $data = $this->readDataFile();
            
            if (isset($data[$storageHash])) {
                // Reset completo mantiene solo info di base
                $data[$storageHash] = [
                    'count' => 0,
                    'first_message' => null,
                    'last_message' => null,
                    'created' => date('Y-m-d H:i:s'),
                    'user_id_hash' => $data[$storageHash]['user_id_hash'],
                    'is_blocked' => false,
                    'total_attempts' => $data[$storageHash]['total_attempts'] ?? 0,
                    'admin_reset' => date('Y-m-d H:i:s'),
                    'admin_reset_count' => ($data[$storageHash]['admin_reset_count'] ?? 0) + 1
                ];
                
                $this->writeDataFile($data);
                error_log("UserStorage: User reset by admin - " . substr($userId, 0, 20));
                return true;
            }
            
            return false;
            
        } finally {
            $this->releaseLock($lockHandle);
        }
    }
}

?>