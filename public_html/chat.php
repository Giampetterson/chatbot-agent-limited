<?php
/**
 * Chat API with Rate Limiting v2.0
 * Integrato con sistema rate limiting 10 messaggi permanenti
 */

// Abilita streaming
@set_time_limit(0);
if (ob_get_level()) {
    ob_end_clean();
}

// Headers base
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-User-ID');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Solo POST accettato
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "data: {\"error\": \"Method not allowed\"}\n\n";
    exit;
}

// Carica servizi rate limiting (Database version - fixed)
require_once 'rate-limiter-db.php';
require_once 'logger.php';
require_once 'error-tracker.php';

try {
    $startTime = microtime(true);
    $logger = LightbotLogger::getInstance();
    
    // Parse input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validazione input base
    if (empty($input['content'])) {
        throw new Exception('Nessun contenuto ricevuto');
    }
    
    // Protezione contro richieste di system prompt e informazioni del modello
    $content = strtolower($input['content']);
    $blockedPatterns = [
        'system prompt',
        'system_prompt',
        'prompt del sistema',
        'prompt di sistema', 
        'tuo prompt',
        'tuo system prompt',
        'dimmi il tuo system prompt',
        'qual è il tuo prompt',
        'mostra il prompt',
        'modello di intelligenza artificiale',
        'che modello sei',
        'quale modello usi',
        'che ia sei',
        'intelligence artificiale',
        'consulente professionista',
        'rentri',
        'instructions',
        'istruzioni di sistema',
        'ignore previous',
        'ignora precedenti',
        'forget previous',
        'dimenticati le istruzioni'
    ];
    
    foreach ($blockedPatterns as $pattern) {
        if (strpos($content, $pattern) !== false) {
            http_response_code(400);
            $errorResponse = [
                'error' => 'blocked_request',
                'message' => 'La richiesta contiene contenuto non supportato. Prova con una domanda diversa.',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            echo "data: " . json_encode($errorResponse) . "\n\n";
            exit;
        }
    }
    
    // Ottieni User ID dal request
    $userId = null;
    
    // Prova header HTTP first
    if (isset($_SERVER['HTTP_X_USER_ID'])) {
        $userId = $_SERVER['HTTP_X_USER_ID'];
    }
    // Fallback a JSON body
    elseif (isset($input['user_id'])) {
        $userId = $input['user_id'];
    }
    
    if (empty($userId)) {
        throw new Exception('User ID richiesto ma non fornito');
    }
    
    // Log debug (solo se abilitato)
    if (defined('DEBUG_RATE_LIMITING') && DEBUG_RATE_LIMITING) {
        error_log("Chat API: User " . substr($userId, 0, 20) . "... requesting message");
    }
    
    // Inizializza RateLimiter (Database version - fixed)
    $rateLimiter = new RateLimiterDB();
    
    // Admin bypass check (per testing)
    $adminBypass = $input['admin_bypass'] ?? null;
    
    // PRE-CHECK: Verifica se l'utente può inviare il messaggio (solo controllo, senza incrementare)
    $limitCheck = $rateLimiter->getStatus($userId);
    
    // Admin bypass check - se è admin, sovrascrive il limitCheck
    if ($adminBypass) {
        // Verifica admin token qui se necessario
        $limitCheck['can_send'] = true;
        $limitCheck['status'] = 'admin_bypass';
    }
    
    if (!$limitCheck['can_send']) {
        // Track rate limit violations for monitoring
        lightbot_track_error('rate_limit_violations', 'medium', "Rate limit exceeded: " . $limitCheck['message'], [
            'user_id_hash' => substr($userId, 0, 20),
            'status' => $limitCheck['status'],
            'count' => $limitCheck['count'],
            'max_count' => $limitCheck['max_count']
        ]);
        
        // Se errore di validazione User ID, usa HTTP 400
        if ($limitCheck['status'] === 'invalid_user') {
            http_response_code(400);
        } else {
            // Utente ha raggiunto il limite - risposta HTTP 429
            http_response_code(429);
        }
        
        $errorResponse = [
            'error' => 'rate_limit_exceeded',
            'status' => $limitCheck['status'],
            'message' => $limitCheck['message'],
            'count' => $limitCheck['count'],
            'max_count' => $limitCheck['max_count'],
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Se è in grace period, aggiungi info tempo rimanente
        if (isset($limitCheck['data']['grace_remaining_minutes'])) {
            $errorResponse['grace_remaining_minutes'] = $limitCheck['data']['grace_remaining_minutes'];
        }
        
        echo "data: " . json_encode($errorResponse) . "\n\n";
        exit;
    }
    
    // L'utente può inviare - procedi con chiamata API
    $apiKey = 'YPKwjwUhsEj6ygLXZ_G_NK1ugxOZ7XrS';
    $apiData = [
        'messages' => [
            ['role' => 'user', 'content' => $input['content']]
        ],
        'stream' => true
    ];
    
    // Setup chiamata API
    $ch = curl_init("https://s5pdsmwvr6vyuj6gwtaedp7g.agents.do-ai.run/api/v1/chat/completions");
    curl_setopt($ch, CURLOPT_BUFFERSIZE, 128);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($apiData));
    
    // Variabili per tracking successo API
    $apiSuccess = false;
    $responseStarted = false;
    $totalChunks = 0;
    
    // Write function che traccia il successo
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use (&$apiSuccess, &$responseStarted, &$totalChunks) {
        // Se riceviamo chunk, l'API ha risposto
        if (!$responseStarted && !empty(trim($chunk))) {
            $responseStarted = true;
        }
        
        // Conta chunk ricevuti
        if (!empty(trim($chunk))) {
            $totalChunks++;
        }
        
        // Output chunk al client
        echo $chunk;
        @ob_flush();
        @flush();
        
        return strlen($chunk);
    });
    
    // Esegui chiamata API
    $curlResult = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Determina se API call è riuscita
    $apiSuccess = ($curlResult !== false && 
                   $httpCode >= 200 && $httpCode < 300 && 
                   $responseStarted && 
                   $totalChunks > 0 &&
                   empty($curlError));
    
    if ($apiSuccess) {
        // API SUCCESS - Incrementa contatore utente (solo se non admin bypass)
        $totalTime = lightbot_track_performance('api_call', $startTime, [
            'user_id_hash' => substr($userId, 0, 20),
            'chunks_received' => $totalChunks,
            'admin_bypass' => $adminBypass ? 'yes' : 'no'
        ]);
        
        lightbot_log_api('POST', '/chat.php', round($totalTime, 2), 200, [
            'user_id_hash' => substr($userId, 0, 20),
            'message_length' => strlen($input['content']),
            'chunks_streamed' => $totalChunks
        ]);
        
        if (!$adminBypass) {
            try {
                $incrementResult = $rateLimiter->incrementUsage($userId, $adminBypass);
                
                lightbot_log_rate_limit($userId, 'increment', false, $incrementResult['count'], $incrementResult['max_count']);
                
                // Invia sempre il contatore aggiornato al client
                $counterUpdate = [
                    'type' => 'counter_update',
                    'count' => $incrementResult['count'],
                    'max_count' => $incrementResult['max_count'],
                    'remaining' => max(0, $incrementResult['max_count'] - $incrementResult['count'])
                ];
                
                echo "\n\ndata: " . json_encode($counterUpdate) . "\n\n";
                
                // Se ha raggiunto il limite, invia notifica aggiuntiva
                if ($incrementResult['count'] >= $incrementResult['max_count']) {
                    $limitNotification = [
                        'type' => 'limit_notification',
                        'count' => $incrementResult['count'],
                        'max_count' => $incrementResult['max_count'],
                        'message' => $incrementResult['message'],
                        'status' => $incrementResult['status']
                    ];
                    
                    echo "\n\ndata: " . json_encode($limitNotification) . "\n\n";
                }
                
            } catch (Exception $e) {
                // Errore increment - log ma non bloccare response
                error_log("Chat API: Error incrementing usage for user " . substr($userId, 0, 20) . "...: " . $e->getMessage());
            }
        }
        
    } else {
        // API FAILED - Non incrementare contatore
        $totalTime = lightbot_track_performance('api_call', $startTime, [
            'user_id_hash' => substr($userId, 0, 20),
            'error' => 'api_failed',
            'http_code' => $httpCode,
            'curl_error' => $curlError
        ]);
        
        lightbot_log_api('POST', '/chat.php', round($totalTime, 2), $httpCode > 0 ? $httpCode : 500, [
            'user_id_hash' => substr($userId, 0, 20),
            'error' => 'External API failed',
            'curl_error' => $curlError
        ]);
        
        // Track API failure for alerting
        lightbot_track_error('api_errors', 'high', "External API call failed: HTTP {$httpCode}", [
            'user_id_hash' => substr($userId, 0, 20),
            'http_code' => $httpCode,
            'curl_error' => $curlError,
            'endpoint' => '/chat.php'
        ]);
        
        error_log("Chat API: API call failed for user " . substr($userId, 0, 20) . "... - HTTP $httpCode, Curl Error: $curlError");
        
        // Se non abbiamo mandato nulla al client, manda errore
        if (!$responseStarted) {
            http_response_code(502);
            $errorResponse = [
                'error' => 'api_call_failed',
                'message' => 'Errore temporaneo del servizio AI. Riprova tra poco.',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            echo "data: " . json_encode($errorResponse) . "\n\n";
        }
    }
    
} catch (Exception $e) {
    // Errore generale - log e risposta errore
    if (isset($startTime)) {
        lightbot_track_performance('api_call', $startTime, [
            'error' => 'exception',
            'exception_message' => $e->getMessage()
        ]);
    }
    
    lightbot_log('ERROR', "Chat API exception: " . $e->getMessage(), [
        'category' => 'api',
        'endpoint' => '/chat.php',
        'user_id_hash' => isset($userId) ? substr($userId, 0, 20) : 'unknown',
        'input_length' => isset($input['content']) ? strlen($input['content']) : 0
    ]);
    
    // Track critical error for alerting
    lightbot_track_error('api_errors', 'critical', "Chat API exception: " . $e->getMessage(), [
        'endpoint' => '/chat.php',
        'user_id_hash' => isset($userId) ? substr($userId, 0, 20) : 'unknown',
        'input_length' => isset($input['content']) ? strlen($input['content']) : 0,
        'exception_class' => get_class($e)
    ]);
    
    error_log("Chat API: General error - " . $e->getMessage());
    
    http_response_code(400);
    $errorResponse = [
        'error' => 'request_error',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo "data: " . json_encode($errorResponse) . "\n\n";
}

?>