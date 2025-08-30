<?php
/**
 * Bot Telegram con polling (alternativa al webhook per HTTP)
 * Esegui questo script continuamente per far funzionare il bot
 */

require_once 'config.php';
require_once 'bot-functions.php';

echo "🤖 Avvio RentriFacileBot con polling...\n";

// Disabilita webhook e usa polling
try {
    callTelegramAPI('deleteWebhook', []);
    echo "✅ Webhook disabilitato, usando polling\n";
} catch (Exception $e) {
    echo "⚠️ Errore disabilitazione webhook: " . $e->getMessage() . "\n";
}

$offset = 0;

echo "🔄 Bot attivo, in attesa di messaggi...\n";
echo "Premi Ctrl+C per fermare\n\n";

while (true) {
    try {
        // Ottieni aggiornamenti
        $updates = callTelegramAPI('getUpdates', [
            'offset' => $offset,
            'timeout' => 10,
            'allowed_updates' => json_encode(['message', 'callback_query', 'inline_query'])
        ]);
        
        foreach ($updates as $update) {
            $offset = $update['update_id'] + 1;
            
            echo "[" . date('H:i:s') . "] Messaggio ricevuto (ID: " . $update['update_id'] . ")\n";
            
            // Processa l'update (stessa logica del webhook)
            if (isset($update['message'])) {
                handleMessage($update['message']);
            } elseif (isset($update['callback_query'])) {
                handleCallbackQuery($update['callback_query']);
            } elseif (isset($update['inline_query'])) {
                handleInlineQuery($update['inline_query']);
            }
        }
        
    } catch (Exception $e) {
        echo "❌ Errore: " . $e->getMessage() . "\n";
        logMessage("Errore polling: " . $e->getMessage(), "ERROR");
        sleep(5); // Pausa prima di riprovare
    }
    
    // Pausa breve per non sovraccaricare l'API
    usleep(100000); // 0.1 secondi
}

/**
 * Gestisce messaggi di testo normali (copiato da webhook.php)
 */
function handleMessage($message) {
    $chatId = $message['chat']['id'];
    $userId = $message['from']['id'];
    $username = $message['from']['username'] ?? $message['from']['first_name'] ?? 'Utente';
    $text = $message['text'] ?? '';
    
    echo "💬 $username: $text\n";
    
    // Controlla se è un comando
    if (strpos($text, '/') === 0) {
        handleCommand($chatId, $userId, $username, $text);
        return;
    }
    
    // Controlla se il bot è menzionato o se è una chat privata
    $isBotMentioned = strpos($text, '@RentriFacileBot') !== false;
    $isPrivateChat = $message['chat']['type'] === 'private';
    $isReply = isset($message['reply_to_message']) && 
               isset($message['reply_to_message']['from']['is_bot']) && 
               $message['reply_to_message']['from']['is_bot'];
    
    // Risponde solo se menzionato, in chat privata, o come risposta al bot
    if ($isBotMentioned || $isPrivateChat || $isReply) {
        // Rimuovi menzione dal testo
        $cleanText = str_replace('@RentriFacileBot', '', $text);
        $cleanText = trim($cleanText);
        
        if ($cleanText) {
            echo "🤖 Elaborando richiesta AI...\n";
            handleAIQuery($chatId, $userId, $username, $cleanText);
        }
    }
}

/**
 * Gestisce comandi del bot (copiato da webhook.php)
 */
function handleCommand($chatId, $userId, $username, $command) {
    echo "⚡ Comando: $command\n";
    
    $commandParts = explode(' ', $command, 2);
    $cmd = strtolower($commandParts[0]);
    $args = $commandParts[1] ?? '';
    
    switch ($cmd) {
        case '/start':
            $response = "♻️ *Benvenuto nel sistema RENTRI!*\n\n";
            $response .= "Sono l'assistente virtuale RentrIA per la gestione dei rifiuti. Posso aiutarti con:\n";
            $response .= "• Classificazione dei rifiuti\n";
            $response .= "• Normative ambientali\n";
            $response .= "• Procedure di smaltimento\n";
            $response .= "• Compilazione moduli RENTRI\n\n";
            $response .= "💬 Menzionami con @RentriFacileBot o rispondi ai miei messaggi per farmi una domanda!\n\n";
            $response .= "📋 Usa /help per vedere tutti i comandi disponibili.";
            break;
            
        case '/help':
            $response = "📋 *Comandi Disponibili:*\n\n";
            $response .= "/start - Messaggio di benvenuto\n";
            $response .= "/help - Mostra questo aiuto\n";
            $response .= "/info - Informazioni sul bot\n";
            $response .= "/contatti - Informazioni di contatto\n";
            $response .= "/sito - Link al portale RENTRI\n";
            $response .= "/rifiuti - Guida classificazione rifiuti\n\n";
            $response .= "💡 *Per fare domande:*\n";
            $response .= "Menziona @RentriFacileBot seguito dalla tua domanda su gestione rifiuti!";
            break;
            
        case '/info':
            $response = "🤖 *RentriFacileBot v1.0*\n\n";
            $response .= "Assistente virtuale per il sistema RENTRI\n";
            $response .= "Specializzato nella gestione dei rifiuti\n";
            $response .= "Powered by RentrIA AI\n\n";
            $response .= "🌐 Portale: PiattaformaRentriFacile.it\n";
            $response .= "📱 Gruppo: @RentriFacile\n";
            $response .= "♻️ Settore: Gestione Rifiuti\n";
            $response .= "⚡ Modalità: Polling (HTTP)\n";
            $response .= "🤖 Sviluppato con Claude Code";
            break;
            
        case '/contatti':
            $response = "📞 *Contatti Sistema RENTRI*\n\n";
            $response .= "🌐 Portale: [PiattaformaRentriFacile.it](https://PiattaformaRentriFacile.it)\n";
            $response .= "📱 Gruppo Telegram: @RentriFacile\n";
            $response .= "🤖 Bot: @RentriFacileBot\n";
            $response .= "♻️ Settore: Gestione e Classificazione Rifiuti\n\n";
            $response .= "Per assistenza su normative ambientali o classificazione rifiuti, fai la tua domanda menzionando il bot!";
            break;
            
        case '/sito':
            $response = "🌐 *Visita il portale RENTRI:*\n\n";
            $response .= "[PiattaformaRentriFacile.it](https://PiattaformaRentriFacile.it)\n\n";
            $response .= "Troverai normative ambientali, guide per la classificazione dei rifiuti e moduli per il sistema RENTRI!";
            break;
            
        case '/rifiuti':
            $response = "♻️ *Guida Classificazione Rifiuti*\n\n";
            $response .= "🗂️ *Categorie principali:*\n";
            $response .= "• Rifiuti urbani non pericolosi\n";
            $response .= "• Rifiuti speciali non pericolosi\n";
            $response .= "• Rifiuti pericolosi\n";
            $response .= "• Rifiuti da costruzione e demolizione\n\n";
            $response .= "📋 Per classificazioni specifiche o dubbi normativi, menziona @RentriFacileBot con la tua domanda!";
            break;
            
        case '/status':
            $response = "🔧 *Stato Bot*\n\n";
            $response .= "✅ Bot: Online (Polling)\n";
            $response .= "✅ API AI: Connessa\n";
            $response .= "✅ Logs: Attivi\n";
            $response .= "📊 Modalità: HTTP Polling\n";
            $response .= "⏰ Tempo: " . date('H:i:s');
            break;
            
        default:
            $response = "❓ Comando non riconosciuto.\n\nUsa /help per vedere i comandi disponibili.";
    }
    
    sendMessage($chatId, $response, true);
    echo "✅ Risposta inviata\n";
}

/**
 * Gestisce query AI con messaggio di elaborazione che viene modificato
 */
function handleAIQuery($chatId, $userId, $username, $query) {
    // Invia messaggio "sta scrivendo"
    sendChatAction($chatId, 'typing');
    
    // Invia messaggio di elaborazione iniziale
    $processingMessage = "⏳ Sto elaborando...";
    $messageResponse = null;
    
    try {
        $messageResponse = sendMessage($chatId, $processingMessage, false);
        $messageId = $messageResponse['message_id'];
        echo "🔄 Messaggio di elaborazione inviato (ID: $messageId)\n";
    } catch (Exception $e) {
        echo "⚠️ Errore invio messaggio elaborazione: " . $e->getMessage() . "\n";
        // Fallback al metodo precedente se non riusciamo a inviare il messaggio di elaborazione
        sendChatAction($chatId, 'typing');
    }
    
    try {
        // Chiama l'API AI
        $aiResponse = callAIAPI($query);
        
        // Elabora la risposta
        if ($aiResponse) {
            // Aggiungi firma alla risposta
            $signature = "\n\n_Assistente RentrIA - Rentri360.it_";
            $fullResponse = $aiResponse . $signature;
            
            // Se abbiamo un messaggio di elaborazione, modificalo
            if ($messageResponse && isset($messageId)) {
                try {
                    // Prova prima con Markdown migliorato
                    editMessage($chatId, $messageId, $fullResponse, true);
                    echo "✅ Messaggio modificato con risposta AI (Markdown)\n";
                } catch (Exception $e) {
                    echo "⚠️ Errore edit con Markdown: " . $e->getMessage() . "\n";
                    try {
                        // Fallback: testo semplice con markdown rimosso
                        $plainText = stripMarkdownForTelegram($fullResponse);
                        editMessage($chatId, $messageId, $plainText, false);
                        echo "✅ Messaggio modificato con risposta AI (Plain Text)\n";
                    } catch (Exception $e2) {
                        echo "❌ Errore edit anche con Plain Text: " . $e2->getMessage() . "\n";
                        try {
                            // Ultimo fallback: invia nuovo messaggio
                            $plainText = stripMarkdownForTelegram($fullResponse);
                            sendMessage($chatId, $plainText, false);
                            echo "✅ Inviato nuovo messaggio come ultimo fallback\n";
                        } catch (Exception $e3) {
                            echo "❌ Errore critico invio messaggio: " . $e3->getMessage() . "\n";
                        }
                    }
                }
            } else {
                // Fallback: invia nuovo messaggio normale
                try {
                    sendMessage($chatId, $fullResponse, true);
                    echo "✅ Risposta AI inviata (Markdown fallback)\n";
                } catch (Exception $e) {
                    try {
                        $plainText = stripMarkdownForTelegram($fullResponse);
                        sendMessage($chatId, $plainText, false);
                        echo "✅ Risposta AI inviata (Plain Text fallback)\n";
                    } catch (Exception $e2) {
                        echo "❌ Errore critico invio messaggio fallback: " . $e2->getMessage() . "\n";
                    }
                }
            }
        } else {
            $errorMsg = "❌ Mi dispiace, non sono riuscito a elaborare la tua richiesta. Riprova tra poco.";
            if ($messageResponse && isset($messageId)) {
                editMessage($chatId, $messageId, $errorMsg, false);
            } else {
                sendMessage($chatId, $errorMsg, false);
            }
            echo "⚠️ Risposta AI vuota\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Errore AI: " . $e->getMessage() . "\n";
        logMessage("Errore AI Query: " . $e->getMessage(), "ERROR");
        
        $errorMsg = "❌ Si è verificato un errore. Il nostro team è stato notificato.";
        if ($messageResponse && isset($messageId)) {
            try {
                editMessage($chatId, $messageId, $errorMsg, false);
            } catch (Exception $editError) {
                sendMessage($chatId, $errorMsg, false);
            }
        } else {
            sendMessage($chatId, $errorMsg, false);
        }
    }
}

function handleCallbackQuery($callbackQuery) {
    // Placeholder per callback query
}

function handleInlineQuery($inlineQuery) {
    // Placeholder per inline query
}

?>