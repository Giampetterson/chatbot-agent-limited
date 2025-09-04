<?php
/**
 * Funzioni principali del bot Telegram
 */

require_once 'config.php';

/**
 * Invia un messaggio a una chat con gestione markdown migliorata
 */
function sendMessage($chatId, $text, $parseMode = true) {
    // Prima pulisci il testo base
    $cleanText = cleanMessageForTelegram($text);
    
    // Poi gestisci il markdown se richiesto
    if ($parseMode) {
        $cleanText = convertMarkdownForTelegram($cleanText);
    }
    
    $data = [
        'chat_id' => $chatId,
        'text' => $cleanText,
        'disable_web_page_preview' => true
    ];
    
    if ($parseMode) {
        $data['parse_mode'] = 'Markdown';
    }
    
    // Log del messaggio per debug
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        logMessage("Invio messaggio (lunghezza: " . strlen($cleanText) . "): " . substr($cleanText, 0, 100) . "...", "DEBUG");
    }
    
    return callTelegramAPI('sendMessage', $data);
}

/**
 * Pulisce il messaggio per compatibilità Telegram
 */
function cleanMessageForTelegram($text) {
    // Limita lunghezza massima per Telegram
    if (strlen($text) > MAX_MESSAGE_LENGTH) {
        $text = substr($text, 0, MAX_MESSAGE_LENGTH - 100) . '...\n\n_Messaggio troncato per limiti Telegram_';
    }
    
    // Rimuovi caratteri di controllo ma mantieni i newline
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
    
    // Normalizza newline
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    
    // Rimuovi newline eccessive (max 3 consecutive)
    $text = preg_replace('/\n{4,}/', "\n\n\n", $text);
    
    // Rimuovi spazi trailing dalle righe
    $text = preg_replace('/[ \t]+$/m', '', $text);
    
    return trim($text);
}

/**
 * Converte markdown generico in formato Telegram-compatibile
 */
function convertMarkdownForTelegram($text) {
    // Prima gestisci i pattern markdown mantenendo la formattazione
    
    // Converti titoli markdown in grassetto (## Titolo → *Titolo*)
    $text = preg_replace('/^#{1,6}\s*(.+)$/m', '*$1*', $text);
    
    // Converti grassetto (** o __) in formato Telegram (*text*)
    $text = preg_replace('/\*\*(.+?)\*\*/', '*$1*', $text);
    $text = preg_replace('/__(.+?)__/', '*$1*', $text);
    
    // Converti corsivo semplice (* o _) in formato Telegram (_text_) 
    // Ma evita di convertire asterischi che sono già grassetto
    $text = preg_replace('/(?<!\*)\*([^*\n]+?)\*(?!\*)/', '_$1_', $text);
    $text = preg_replace('/(?<!_)_([^_\n]+?)_(?!_)/', '_$1_', $text);
    
    // Rimuovi link markdown complessi e mantieni solo l'URL
    $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '$2', $text);
    
    // Converti liste puntate semplici
    $text = preg_replace('/^[\*\-\+]\s+(.+)$/m', '• $1', $text);
    $text = preg_replace('/^\d+\.\s+(.+)$/m', '• $1', $text);
    
    // NON fare escape di caratteri normali - Telegram gestisce bene il testo normale
    // Mantieni solo il testo così com'è dopo le conversioni markdown
    
    return $text;
}

/**
 * Converte in testo semplice rimuovendo tutto il markdown
 */
function stripMarkdownForTelegram($text) {
    // Rimuovi tutti i caratteri markdown
    $text = preg_replace('/\*\*(.+?)\*\*/', '$1', $text); // grassetto
    $text = preg_replace('/__(.+?)__/', '$1', $text);     // grassetto alt
    $text = preg_replace('/\*(.+?)\*/', '$1', $text);     // corsivo
    $text = preg_replace('/_(.+?)_/', '$1', $text);       // corsivo alt
    $text = preg_replace('/`(.+?)`/', '$1', $text);       // codice inline
    $text = preg_replace('/```(.+?)```/s', '$1', $text);  // blocchi codice
    $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text); // link
    $text = preg_replace('/^#{1,6}\s*(.+)$/m', '$1', $text); // titoli
    $text = preg_replace('/^[\*\-\+]\s+(.+)$/m', '• $1', $text); // liste
    $text = preg_replace('/^\d+\.\s+(.+)$/m', '• $1', $text); // liste numerate
    
    return $text;
}

/**
 * Modifica un messaggio esistente con gestione markdown migliorata
 */
function editMessage($chatId, $messageId, $text, $parseMode = true) {
    // Prima pulisci il testo base
    $cleanText = cleanMessageForTelegram($text);
    
    // Poi gestisci il markdown se richiesto
    if ($parseMode) {
        $cleanText = convertMarkdownForTelegram($cleanText);
    }
    
    $data = [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $cleanText,
        'disable_web_page_preview' => true
    ];
    
    if ($parseMode) {
        $data['parse_mode'] = 'Markdown';
    }
    
    // Log del messaggio per debug
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        logMessage("Edit messaggio ID $messageId (lunghezza: " . strlen($cleanText) . "): " . substr($cleanText, 0, 100) . "...", "DEBUG");
    }
    
    return callTelegramAPI('editMessageText', $data);
}

/**
 * Invia azione di chat (typing, upload_photo, etc.)
 */
function sendChatAction($chatId, $action) {
    $data = [
        'chat_id' => $chatId,
        'action' => $action
    ];
    
    return callTelegramAPI('sendChatAction', $data);
}

/**
 * Risponde a una callback query
 */
function answerCallbackQuery($queryId, $text = '', $showAlert = false) {
    $data = [
        'callback_query_id' => $queryId,
        'text' => $text,
        'show_alert' => $showAlert
    ];
    
    return callTelegramAPI('answerCallbackQuery', $data);
}

/**
 * Risponde a una inline query
 */
function answerInlineQuery($queryId, $results) {
    $data = [
        'inline_query_id' => $queryId,
        'results' => json_encode($results)
    ];
    
    return callTelegramAPI('answerInlineQuery', $data);
}

/**
 * Chiama l'API di Telegram
 */
function callTelegramAPI($method, $data = []) {
    $url = TELEGRAM_API_URL . '/' . $method;
    
    // Usa cURL per migliore gestione degli errori
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($result === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception("Errore cURL: $error");
    }
    
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("HTTP Error $httpCode per metodo $method");
    }
    
    $response = json_decode($result, true);
    
    if (!$response || !$response['ok']) {
        $errorMsg = $response['description'] ?? 'Errore sconosciuto';
        throw new Exception("Errore API Telegram: $errorMsg");
    }
    
    return $response['result'];
}

/**
 * Chiama l'API AI (stessa logica del chatbot web)
 */
function callAIAPI($userMessage) {
    $data = [
        'messages' => [
            ['role' => 'user', 'content' => $userMessage]
        ],
        'stream' => true
    ];
    
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        logMessage("Chiamata AI per: " . substr($userMessage, 0, 50) . "...", "DEBUG");
    }
    
    $ch = curl_init(AI_API_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . AI_API_KEY
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, REQUEST_TIMEOUT);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        logMessage("Risposta AI - HTTP: $httpCode, Size: " . strlen($response) . ", cURL Error: " . ($curlError ?: 'none'), "DEBUG");
        if ($response && strlen($response) < 500) {
            logMessage("Risposta AI raw: " . $response, "DEBUG");
        }
    }
    
    if ($response === false) {
        throw new Exception('Errore cURL API AI: ' . $curlError);
    }
    
    if ($httpCode !== 200) {
        throw new Exception('Errore chiamata API AI: HTTP ' . $httpCode);
    }
    
    if (empty($response)) {
        throw new Exception('Risposta AI vuota');
    }
    
    // Processa risposta streaming (simile al chatbot web)
    return parseStreamingResponse($response);
}

/**
 * Processa risposta streaming dall'AI
 */
function parseStreamingResponse($response) {
    $lines = explode("\n", $response);
    $fullResponse = '';
    $validChunks = 0;
    $totalLines = count($lines);
    
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        logMessage("Parsing streaming response: $totalLines righe totali", "DEBUG");
    }
    
    foreach ($lines as $lineNum => $line) {
        $line = trim($line);
        if (strpos($line, 'data:') === 0) {
            $jsonStr = trim(substr($line, 5));
            if ($jsonStr && $jsonStr !== '[DONE]') {
                $data = json_decode($jsonStr, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    if (isset($data['choices'][0]['delta']['content'])) {
                        $content = $data['choices'][0]['delta']['content'];
                        $fullResponse .= $content;
                        $validChunks++;
                        
                        if (defined('DEBUG_MODE') && DEBUG_MODE && $validChunks <= 3) {
                            logMessage("Chunk $validChunks: " . substr($content, 0, 30) . "...", "DEBUG");
                        }
                    }
                } else {
                    if (defined('DEBUG_MODE') && DEBUG_MODE) {
                        logMessage("JSON Error linea $lineNum: " . json_last_error_msg() . " - JSON: " . substr($jsonStr, 0, 100), "DEBUG");
                    }
                }
            }
        }
    }
    
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        logMessage("Parsing completato: $validChunks chunk validi, lunghezza finale: " . strlen($fullResponse), "DEBUG");
    }
    
    // Pulisci la risposta
    $fullResponse = trim($fullResponse);
    
    if (empty($fullResponse)) {
        throw new Exception("Nessun contenuto valido nella risposta streaming (chunk validi: $validChunks/$totalLines)");
    }
    
    // Limita lunghezza per Telegram
    if (strlen($fullResponse) > MAX_MESSAGE_LENGTH) {
        $fullResponse = substr($fullResponse, 0, MAX_MESSAGE_LENGTH - 100) . '...\n\n_Risposta troncata per limiti Telegram_';
    }
    
    return $fullResponse;
}

/**
 * Controlla se un utente è amministratore
 */
function isAdmin($userId) {
    return in_array($userId, BOT_ADMINS);
}

/**
 * Ottiene statistiche del bot
 */
function getStats() {
    // Implementare con database se necessario
    $stats = "📊 *Statistiche Bot*\n\n";
    $stats .= "⏰ Online da: " . date('d/m/Y H:i') . "\n";
    $stats .= "🤖 Versione: 1.0\n";
    $stats .= "💾 Memoria: " . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB\n";
    $stats .= "🔄 Uptime: " . getUptime() . "\n\n";
    $stats .= "_Statistiche dettagliate in sviluppo_";
    
    return $stats;
}

/**
 * Ottiene stato del sistema
 */
function getSystemStatus() {
    $status = "🔧 *Stato Sistema*\n\n";
    $status .= "✅ Bot: Online\n";
    $status .= "✅ Webhook: Attivo\n";
    $status .= "✅ API AI: " . checkAIStatus() . "\n";
    $status .= "✅ Logs: Attivi\n\n";
    $status .= "📋 Config:\n";
    $status .= "• Debug: " . (DEBUG_MODE ? 'ON' : 'OFF') . "\n";
    $status .= "• Timeout: " . REQUEST_TIMEOUT . "s\n";
    $status .= "• Rate Limit: " . RATE_LIMIT_SECONDS . "s\n";
    
    return $status;
}

/**
 * Controlla stato API AI
 */
function checkAIStatus() {
    try {
        // Test ping all'API
        $context = stream_context_create([
            'http' => [
                'method' => 'HEAD',
                'timeout' => 5
            ]
        ]);
        
        $result = @file_get_contents(AI_API_URL, false, $context);
        return $result !== false ? 'Online' : 'Offline';
    } catch (Exception $e) {
        return 'Offline';
    }
}

/**
 * Gestisce broadcast agli utenti
 */
function handleBroadcast($message) {
    // Implementare con database degli utenti
    return "📢 *Broadcast*\n\nFunzione in sviluppo.\nSarà disponibile con il database utenti.";
}

/**
 * Calcola uptime del server
 */
function getUptime() {
    if (file_exists('/proc/uptime')) {
        $uptime = file_get_contents('/proc/uptime');
        $uptime = explode(' ', $uptime)[0];
        $days = floor($uptime / 86400);
        $hours = floor(($uptime % 86400) / 3600);
        $minutes = floor(($uptime % 3600) / 60);
        return "{$days}d {$hours}h {$minutes}m";
    }
    return "N/A";
}

/**
 * Log personalizzato
 */
function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message\n";
    file_put_contents(LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Inizializzazione bot
 */
function initBot() {
    // Crea directory logs se non esiste
    $logDir = dirname(LOG_FILE);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // Log di avvio
    logMessage("Bot inizializzato", "INFO");
}

// Inizializza il bot
initBot();

?>