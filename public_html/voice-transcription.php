<?php
// Gestione trascrizione audio con OpenAI Whisper
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Verifica metodo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non consentito']);
    exit;
}

// Verifica presenza file audio
if (!isset($_FILES['audio'])) {
    http_response_code(400);
    echo json_encode(['error' => 'File audio mancante nella richiesta']);
    exit;
}

// Controlla errori specifici di upload
if ($_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE => 'File troppo grande (limite server PHP)',
        UPLOAD_ERR_FORM_SIZE => 'File troppo grande (limite form)',
        UPLOAD_ERR_PARTIAL => 'Upload parziale, riprova',
        UPLOAD_ERR_NO_FILE => 'Nessun file caricato',
        UPLOAD_ERR_NO_TMP_DIR => 'Cartella temporanea mancante',
        UPLOAD_ERR_CANT_WRITE => 'Errore scrittura disco',
        UPLOAD_ERR_EXTENSION => 'Upload bloccato da estensione'
    ];
    
    $errorMsg = $uploadErrors[$_FILES['audio']['error']] ?? 'Errore upload sconosciuto';
    error_log("Upload error: " . $errorMsg . " (code: " . $_FILES['audio']['error'] . ")");
    
    http_response_code(400);
    echo json_encode(['error' => $errorMsg, 'upload_error_code' => $_FILES['audio']['error']]);
    exit;
}

// Carica configurazione sicura
require_once __DIR__ . '/config-secure.php';

$apiKey = OPENAI_API_KEY;
$apiUrl = 'https://api.openai.com/v1/audio/transcriptions';

// Prepara il file per l'upload
$audioFile = $_FILES['audio']['tmp_name'];
$audioFileName = $_FILES['audio']['name'];
$audioMimeType = $_FILES['audio']['type'];
$audioSize = $_FILES['audio']['size'];

// Validazione dimensione file (max 10MB)
if ($audioSize > MAX_AUDIO_SIZE) {
    http_response_code(413);
    echo json_encode([
        'error' => 'File audio troppo grande',
        'max_size' => MAX_AUDIO_SIZE,
        'received_size' => $audioSize
    ]);
    exit;
}

// Validazione tipo MIME (permissiva per compatibilità browser)
$allowedTypes = ALLOWED_AUDIO_TYPES;
if (!empty($audioMimeType) && !in_array($audioMimeType, $allowedTypes)) {
    error_log("Warning: Unexpected MIME type: $audioMimeType, but continuing...");
}

// Log per debug
error_log("Voice Transcription: Ricevuto file audio: $audioFileName, tipo: $audioMimeType, dimensione: $audioSize bytes");

// Leggi il file di contesto usando path sicuro
$contextFile = CONTEXT_FILE_PATH;
$contextContent = '';
if (file_exists($contextFile)) {
    $contextContent = file_get_contents($contextFile);
    error_log("Context loaded: " . strlen($contextContent) . " characters");
} else {
    error_log("Warning: Context file not found at $contextFile");
}

// Crea richiesta multipart
$cFile = new CURLFile($audioFile, $audioMimeType, $audioFileName);
$postData = [
    'file' => $cFile,
    'model' => 'whisper-1',
    'language' => 'it', // Forza italiano per migliori risultati
    'response_format' => 'json'
];

// Aggiungi il prompt con il contesto se disponibile
if (!empty($contextContent)) {
    $postData['prompt'] = $contextContent;
    error_log("Context added to API request");
}

// Inizializza cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

// Esegui richiesta
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Gestione errori cURL
if ($error) {
    error_log("Errore cURL: $error");
    http_response_code(500);
    echo json_encode(['error' => 'Errore connessione API: ' . $error]);
    exit;
}

// Gestione risposta API
if ($httpCode !== 200) {
    error_log("Errore API OpenAI: HTTP $httpCode - Response: $response");
    http_response_code($httpCode);
    echo json_encode(['error' => 'Errore API trascrizione', 'details' => json_decode($response)]);
    exit;
}

// Decodifica risposta
$result = json_decode($response, true);
if (!isset($result['text'])) {
    error_log("Risposta API inaspettata: $response");
    http_response_code(500);
    echo json_encode(['error' => 'Formato risposta non valido']);
    exit;
}

// Restituisci trascrizione
echo json_encode([
    'success' => true,
    'text' => $result['text'],
    'duration' => $result['duration'] ?? null
]);
?>