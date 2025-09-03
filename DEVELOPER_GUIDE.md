# 🛠️ LIGHTBOT RENTRI360.it - Manuale Operativo per Sviluppatori

**Documentazione tecnica completa per sviluppo, manutenzione e estensione del sistema**

---

## 📚 Indice

- [1. Architettura Generale](#1-architettura-generale)
- [2. Setup Ambiente di Sviluppo](#2-setup-ambiente-di-sviluppo)
- [3. Pattern e Convenzioni](#3-pattern-e-convenzioni)
- [4. Sistema Frontend](#4-sistema-frontend)
- [5. Sistema Backend/API](#5-sistema-backendapi)
- [6. Database e Persistenza](#6-database-e-persistenza)
- [7. Sistema di Sicurezza](#7-sistema-di-sicurezza)
- [8. Integrazione Telegram](#8-integrazione-telegram)
- [9. Logging e Monitoring](#9-logging-e-monitoring)
- [10. Testing e Debug](#10-testing-e-debug)
- [11. Guida alle Modifiche](#11-guida-alle-modifiche)
- [12. Troubleshooting](#12-troubleshooting)

---

## 1. Architettura Generale

### 1.1 Overview del Sistema

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   FRONTEND      │    │   BACKEND/API   │    │   EXTERNAL      │
│                 │    │                 │    │                 │
│ • chatbot.php   │◄──►│ • chat.php      │◄──►│ • OpenAI API    │
│ • JavaScript    │    │ • voice-*.php   │    │ • Custom AI API │
│ • CSS/HTML      │    │ • analyze-*.php │    │ • Telegram API  │
│                 │    │                 │    │                 │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       
         └───────┐       ┌───────┘                       
                 ▼       ▼                               
        ┌─────────────────────┐                         
        │     DATABASE        │                         
        │                     │                         
        │ • user_limits       │                         
        │ • MySQL/MariaDB     │                         
        └─────────────────────┘                         
```

### 1.2 Flusso di Richiesta Tipico

1. **User Input** → `chatbot.php` (Frontend)
2. **JavaScript Processing** → `services.js`, `user-fingerprint.js`
3. **API Call** → `chat.php` (Backend)
4. **External API** → Custom AI API / OpenAI
5. **Response Processing** → Streaming SSE
6. **UI Update** → Rendering markdown, action buttons
7. **Post-Processing** → Notes, sharing, RENTRI classification

---

## 2. Setup Ambiente di Sviluppo

### 2.1 Prerequisiti

```bash
# Verifica versioni
php --version          # Richiesto: 8.4+
mysql --version        # Richiesto: 5.7+ / MariaDB 10.3+
nginx -version         # Richiesto: 1.20+
```

### 2.2 Clone e Setup

```bash
# 1. Clone repository
git clone https://github.com/Giampetterson/chatbot-agent-limited.git
cd chatbot-agent-limited

# 2. Setup database
mysql -u root -p << EOF
CREATE DATABASE lightbot_dev;
CREATE USER 'lightbot_dev'@'localhost' IDENTIFIED BY 'dev_password';
GRANT ALL PRIVILEGES ON lightbot_dev.* TO 'lightbot_dev'@'localhost';
FLUSH PRIVILEGES;
EOF

# 3. Crea .env per sviluppo
cp .env.example .env.dev
```

### 2.3 Configurazione .env.dev

```bash
# Development Environment
ENVIRONMENT=development
DEBUG=true

# AI APIs
OPENAI_API_KEY=sk-proj-your_dev_key
AI_API_URL=https://your-dev-api.run/api/v1/chat/completions
AI_API_KEY=your_dev_api_key

# Database Development
DB_HOST=localhost
DB_NAME=lightbot_dev
DB_USER=lightbot_dev
DB_PASSWORD=dev_password

# Telegram Bot (Test)
TELEGRAM_BOT_TOKEN=your_test_bot_token
TELEGRAM_BOT_USERNAME=YourTestBot

# Rate Limiting (Development)
RATE_LIMIT_MAX_MESSAGES=999999
```

### 2.4 Nginx Development Config

```nginx
# /etc/nginx/sites-available/lightbot.dev
server {
    listen 80;
    server_name lightbot.dev www.lightbot.dev;
    
    root /path/to/project/public_html;
    index index.html index.php;
    
    client_max_body_size 10M;
    
    # Development headers
    add_header X-Debug "Development" always;
    
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param ENVIRONMENT development;
        
        # Development settings
        fastcgi_buffering off;
        fastcgi_read_timeout 300s;
    }
    
    # No cache in development
    location ~* \.(js|css|html)$ {
        add_header Cache-Control "no-cache, no-store, must-revalidate";
        expires 0;
    }
}
```

---

## 3. Pattern e Convenzioni

### 3.1 Naming Conventions

#### 3.1.1 File e Directory
```
public_html/
├── {service}-{type}.php     # es: voice-transcription.php, analyze-proxy.php
├── {service}.js             # es: services.js, notes.js
├── {service}-{action}.js    # es: user-fingerprint.js, voice-recorder.js
└── telegram-bot/
    ├── {bot-component}.php  # es: polling-bot.php, bot-functions.php
```

#### 3.1.2 PHP Functions e Classes
```php
// Naming pattern: {entity}_{action} oppure {action}_{entity}
function validateUserFingerprint($userId) { }
function logApiRequest($endpoint, $data) { }
function sendTelegramMessage($chatId, $message) { }

// Classes: PascalCase con suffisso descrittivo
class DatabaseManager { }
class ErrorTracker { }
class RateLimiter { }
```

#### 3.1.3 JavaScript Functions e Variables
```javascript
// Functions: camelCase con verbo + oggetto
function addShareButtons(element, messageId) { }
function extractTextFromElement(element) { }
function compressImageForUpload(file) { }

// Variables: camelCase descrittivo
const messageCounter = 0;
const userFingerprint = new UserFingerprint();
const currentAbortController = null;
```

### 3.2 Error Handling Pattern

#### 3.2.1 Backend PHP
```php
try {
    // Operazione
    $result = performOperation();
    
    // Log successo
    error_log("SUCCESS: Operation completed", 3, "/path/to/success.log");
    
    return $result;
    
} catch (Exception $e) {
    // Log errore con contesto
    $errorContext = [
        'operation' => 'operation_name',
        'user_id' => $userId ?? 'unknown',
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ];
    
    error_log("ERROR: " . json_encode($errorContext), 3, "/path/to/error.log");
    
    // Response standardizzata
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Internal server error',
        'error_id' => uniqid()
    ]);
    exit;
}
```

#### 3.2.2 Frontend JavaScript
```javascript
async function apiCall(endpoint, data) {
    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        return await response.json();
        
    } catch (error) {
        console.error('API Call failed:', {
            endpoint,
            error: error.message,
            timestamp: new Date().toISOString()
        });
        
        // UI feedback
        showErrorMessage('Si è verificato un errore. Riprova più tardi.');
        
        throw error; // Re-throw per handling upstream
    }
}
```

### 3.3 Response Format Standards

#### 3.3.1 API Success Response
```php
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'data' => $resultData,
    'meta' => [
        'timestamp' => time(),
        'request_id' => $requestId,
        'processing_time_ms' => $processingTime
    ]
]);
```

#### 3.3.2 API Error Response
```php
http_response_code($errorCode);
header('Content-Type: application/json');
echo json_encode([
    'success' => false,
    'error' => [
        'code' => $errorCode,
        'message' => $userFriendlyMessage,
        'details' => $debugInfo, // Solo se ENVIRONMENT === 'development'
        'error_id' => $errorId
    ],
    'meta' => [
        'timestamp' => time(),
        'request_id' => $requestId
    ]
]);
```

---

## 4. Sistema Frontend

### 4.1 Struttura File Frontend

```
public_html/
├── chatbot.php              # Entry point principale
├── index.html              # Redirect a chatbot.php
├── user-fingerprint.js     # Sistema identificazione utenti
├── services.js             # Pulsanti azione e condivisione  
├── notes.js                # Sistema appunti e esportazione
├── voice-recorder.js       # Registrazione audio
└── security-headers.php    # Headers sicurezza (incluso in chatbot.php)
```

### 4.2 chatbot.php - Struttura Principale

#### 4.2.1 Sezioni Principali
```php
<?php
// 1. Security headers inclusione
require_once 'security-headers.php';
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <!-- 2. Meta tags e fonts -->
    <!-- 3. Script loading con versioning -->
    <!-- 4. CSS inline (stili principali) -->
</head>
<body>
    <!-- 5. Header e loading indicator -->
    <!-- 6. Chat container principale -->
    <!-- 7. Input form e attachment menu -->
    
    <script>
        // 8. Core JavaScript functions
        // 9. Event listeners setup
        // 10. Initialization code
    </script>
</body>
</html>
```

#### 4.2.2 Script Loading Pattern
```javascript
// Pattern di caricamento sequenziale con fallback
const scripts = [
    'user-fingerprint.js?v=1.0.0&t=' + timestamp,
    'notes.js?v=2.4.4&t=' + timestamp,
    'services.js?v=2.4.4&t=' + timestamp, 
    'voice-recorder.js?v=2.4.4&t=' + timestamp
];

// Caricamento sequenziale per dependency management
scripts.forEach((src, index) => {
    const script = document.createElement('script');
    script.src = src;
    script.onload = function() {
        scriptsLoaded++;
        if (scriptsLoaded === scripts.length) {
            initializeApplication();
        }
    };
    document.head.appendChild(script);
});
```

### 4.3 Gestione Messaggi e UI

#### 4.3.1 Creazione Messaggi
```javascript
// Location: chatbot.php ~line 1900
function createMessage(content, sender) {
    const div = document.createElement('div');
    div.className = sender; // 'user' o 'bot'
    div.setAttribute('data-timestamp', Date.now());
    
    if (sender === 'bot') {
        // Assegna ID univoco per action buttons
        const messageId = 'msg-' + (++messageCounter);
        div.setAttribute('data-message-id', messageId);
        
        // Setup per action buttons
        setTimeout(() => {
            if (window.chatServices) {
                window.chatServices.addShareButtons(div, messageId);
            }
            if (window.addNotesToButton) {
                window.addNotesToButton(div, messageId);
            }
        }, 100);
    }
    
    return div;
}
```

#### 4.3.2 Rendering Markdown
```javascript
// Location: chatbot.php ~line 1997
function renderMarkdown(text) {
    marked.setOptions({
        breaks: false,    // Evita <br> automatici
        gfm: true,       // GitHub Flavored Markdown
        sanitize: false, // Sanitizzazione manuale
        highlight: function(code, lang) {
            // Syntax highlighting se necessario
            return code;
        }
    });
    
    return marked.parse(text);
}
```

### 4.4 Sistema User Fingerprint

#### 4.4.1 Generazione ID Utente
```javascript
// Location: user-fingerprint.js ~line 45
generateFingerprint() {
    try {
        // Raccogli dati browser
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        ctx.textBaseline = 'top';
        ctx.fillText('Fingerprint test', 2, 2);
        const canvasHash = canvas.toDataURL();
        
        const components = [
            navigator.userAgent,
            navigator.language,
            screen.width + 'x' + screen.height,
            new Date().getTimezoneOffset(),
            canvasHash,
            navigator.hardwareConcurrency || 'unknown'
        ];
        
        // Genera hash
        const combined = components.join('|');
        const hash = this.simpleHash(combined);
        const timestamp = Date.now().toString(36);
        
        return `fp_${hash}_${timestamp}`;
        
    } catch (error) {
        // Fallback per mobile/restrizioni
        const random = Math.random().toString(36).substring(2, 15);
        const timestamp = Date.now().toString(36);
        return `fp_emergency_${random}_${timestamp}`;
    }
}
```

### 4.5 Action Buttons System

#### 4.5.1 Pattern Creazione Pulsanti
```javascript
// Location: services.js ~line 39
addCopyButton(messageElement, messageId) {
    // 1. Controllo esistenza
    const existingBtn = messageElement.querySelector('.copy-btn');
    if (existingBtn) return;

    // 2. Creazione elemento
    const copyBtn = document.createElement('button');
    copyBtn.className = 'copy-btn action-btn';
    copyBtn.innerHTML = `
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <!-- SVG path -->
        </svg>
    `;
    
    // 3. Event handler
    const copyHandler = async () => {
        // Logic specifica del pulsante
        await performCopyAction();
        showFeedback(copyBtn);
    };
    
    copyBtn.addEventListener('click', copyHandler);
    
    // 4. Inserimento in DOM
    addButtonToContainer(copyBtn, messageElement);
}
```

---

## 5. Sistema Backend/API

### 5.1 Struttura API Endpoints

```
public_html/
├── chat.php                 # Chat principale con streaming
├── voice-transcription.php  # Trascrizione audio (Whisper)
├── analyze-proxy.php        # Analisi immagini (GPT-4o Vision)
├── analyze-original.php     # Backup analisi immagini
├── database.php            # Database abstraction layer
├── logger.php              # Sistema logging centralizzato
└── rate-limiter-db.php     # Rate limiting (attualmente disabilitato)
```

### 5.2 chat.php - API Principale

#### 5.2.1 Struttura Request/Response
```php
// Location: chat.php ~line 1
<?php
// Headers CORS e sicurezza
header('Access-Control-Allow-Origin: *');
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');

// Validazione input
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "data: " . json_encode(['error' => 'Method not allowed']) . "\n\n";
    exit;
}

// Parse JSON input
$input = json_decode(file_get_contents('php://input'), true);
$userMessage = $input['content'] ?? '';
$userId = $_SERVER['HTTP_X_USER_ID'] ?? '';

// Validazione User ID
if (!preg_match('/^fp_[a-f0-9]{25,}_[a-z0-9]{8,}$/', $userId)) {
    sendErrorResponse('Invalid user ID format');
}

// Rate limiting check
$rateLimiter = new RateLimiter();
if (!$rateLimiter->checkLimit($userId)) {
    sendErrorResponse('Rate limit exceeded');
}

// Chiamata API AI con streaming
streamAIResponse($userMessage, $userId);
?>
```

#### 5.2.2 Streaming Implementation
```php
// Location: chat.php ~line 89
function streamAIResponse($message, $userId) {
    $context = file_get_contents('contesto.txt');
    
    $requestData = [
        'model' => 'gpt-4o',
        'messages' => [
            ['role' => 'system', 'content' => $context],
            ['role' => 'user', 'content' => $message]
        ],
        'stream' => true,
        'temperature' => 0.7
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $_ENV['AI_API_URL'],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($requestData),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $_ENV['AI_API_KEY']
        ],
        CURLOPT_WRITEFUNCTION => 'processStreamChunk'
    ]);
    
    curl_exec($ch);
    curl_close($ch);
}

function processStreamChunk($ch, $data) {
    if (strpos($data, 'data: ') === 0) {
        $jsonData = substr($data, 6);
        
        if (trim($jsonData) === '[DONE]') {
            echo "data: [DONE]\n\n";
            return strlen($data);
        }
        
        $parsed = json_decode($jsonData, true);
        if (isset($parsed['choices'][0]['delta']['content'])) {
            $content = $parsed['choices'][0]['delta']['content'];
            echo "data: " . json_encode(['content' => $content]) . "\n\n";
        }
    }
    
    flush(); // Forza invio immediato
    return strlen($data);
}
```

### 5.3 voice-transcription.php

#### 5.3.1 Upload e Validazione Audio
```php
// Location: voice-transcription.php ~line 15
function processAudioUpload() {
    // Validazione file
    if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
        throwError('No audio file uploaded or upload error', 400);
    }
    
    $audioFile = $_FILES['audio'];
    
    // Validazione tipo MIME
    $allowedTypes = ['audio/webm', 'audio/wav', 'audio/mp3', 'audio/m4a'];
    if (!in_array($audioFile['type'], $allowedTypes)) {
        throwError('Invalid audio format', 400);
    }
    
    // Validazione dimensione (max 25MB per Whisper)
    if ($audioFile['size'] > 25 * 1024 * 1024) {
        throwError('Audio file too large', 413);
    }
    
    return $audioFile;
}
```

#### 5.3.2 Integrazione OpenAI Whisper
```php
// Location: voice-transcription.php ~line 67
function transcribeAudio($audioFile) {
    $curl = curl_init();
    
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://api.openai.com/v1/audio/transcriptions',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'file' => new CURLFile($audioFile['tmp_name'], $audioFile['type'], $audioFile['name']),
            'model' => 'whisper-1',
            'language' => 'it',  // Italiano ottimizzato
            'response_format' => 'json'
        ],
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $_ENV['OPENAI_API_KEY']
        ],
        CURLOPT_RETURNTRANSFER => true
    ]);
    
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    if ($httpCode !== 200) {
        throwError('Transcription failed', $httpCode);
    }
    
    $result = json_decode($response, true);
    return $result['text'] ?? '';
}
```

### 5.4 analyze-proxy.php - Analisi Immagini

#### 5.4.1 Processing Immagini
```php
// Location: analyze-proxy.php ~line 20
function processImageUpload() {
    if (!isset($_FILES['image'])) {
        throwError('No image provided', 400);
    }
    
    $imageFile = $_FILES['image'];
    
    // Validazione formato
    $imageInfo = getimagesize($imageFile['tmp_name']);
    if (!$imageInfo) {
        throwError('Invalid image format', 400);
    }
    
    $allowedTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];
    if (!in_array($imageInfo[2], $allowedTypes)) {
        throwError('Unsupported image type', 400);
    }
    
    // Conversione base64 per OpenAI
    $imageData = file_get_contents($imageFile['tmp_name']);
    $base64Image = base64_encode($imageData);
    $mimeType = image_type_to_mime_type($imageInfo[2]);
    
    return "data:$mimeType;base64,$base64Image";
}
```

#### 5.4.2 GPT-4o Vision Integration
```php
// Location: analyze-proxy.php ~line 89
function analyzeImageWithGPT4($base64Image) {
    $context = file_get_contents('contesto.txt');
    
    $requestData = [
        'model' => 'gpt-4o',
        'messages' => [
            [
                'role' => 'system',
                'content' => $context . "\n\nAnalizza questa immagine di rifiuti e fornisci classificazione dettagliata."
            ],
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => 'Analizza questa immagine di rifiuti'],
                    ['type' => 'image_url', 'image_url' => ['url' => $base64Image]]
                ]
            ]
        ],
        'max_tokens' => 2000,
        'temperature' => 0.3
    ];
    
    $response = callOpenAI($requestData);
    return $response['choices'][0]['message']['content'];
}
```

---

## 6. Database e Persistenza

### 6.1 Database Schema

#### 6.1.1 Tabella user_limits
```sql
CREATE TABLE user_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id_hash VARCHAR(128) UNIQUE NOT NULL,
    count INT DEFAULT 0,
    max_count INT DEFAULT 999999,
    is_blocked BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_user_hash (user_id_hash),
    INDEX idx_created_at (created_at)
);
```

### 6.2 DatabaseManager Class

#### 6.2.1 Connection Management
```php
// Location: database.php ~line 15
class DatabaseManager {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $dbname = $_ENV['DB_NAME'] ?? 'lightbot';
        $username = $_ENV['DB_USER'] ?? 'lightbot_user';
        $password = $_ENV['DB_PASSWORD'] ?? '';
        
        try {
            $this->connection = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
```

#### 6.2.2 Rate Limiting Operations
```php
// Location: database.php ~line 67
public function getUserLimits($userIdHash) {
    $stmt = $this->connection->prepare(
        "SELECT count, max_count, is_blocked FROM user_limits WHERE user_id_hash = ?"
    );
    $stmt->execute([$userIdHash]);
    
    $result = $stmt->fetch();
    
    if (!$result) {
        // Crea nuovo utente
        $this->createUser($userIdHash);
        return ['count' => 0, 'max_count' => 999999, 'is_blocked' => false];
    }
    
    return $result;
}

public function incrementUserCount($userIdHash) {
    $stmt = $this->connection->prepare(
        "UPDATE user_limits SET count = count + 1, updated_at = NOW() 
         WHERE user_id_hash = ?"
    );
    $result = $stmt->execute([$userIdHash]);
    
    if (!$result || $stmt->rowCount() === 0) {
        throw new Exception("Failed to increment user count");
    }
    
    return $this->getUserLimits($userIdHash);
}
```

---

## 7. Sistema di Sicurezza

### 7.1 Security Headers

#### 7.1.1 security-headers.php
```php
// Location: security-headers.php
<?php
// CORS Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-User-ID');

// Security Headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Permissions Policy (Safari iOS fix)
header('Permissions-Policy: microphone=(self), camera=(self), geolocation=(self), notifications=(self)');

// Content Security Policy
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://unpkg.com https://cdnjs.cloudflare.com https://fonts.googleapis.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.gstatic.com; font-src 'self' https://fonts.googleapis.com https://fonts.gstatic.com; media-src 'self' blob: data:; connect-src 'self' https://s5pdsmwvr6vyuj6gwtaedp7g.agents.do-ai.run https://api.openai.com; img-src 'self' data: https:;");
?>
```

### 7.2 Input Validation

#### 7.2.1 User ID Validation
```php
// Pattern utilizzato in tutti gli endpoint API
function validateUserId($userId) {
    // Formato: fp_[64char_hash]_[timestamp_base36]
    $pattern = '/^fp_[a-f0-9]{25,}_[a-z0-9]{8,}$/';
    
    if (!preg_match($pattern, $userId)) {
        throw new InvalidArgumentException('Invalid user ID format');
    }
    
    // Validazione timestamp (non troppo vecchio)
    $parts = explode('_', $userId);
    if (count($parts) < 3) {
        throw new InvalidArgumentException('Invalid user ID structure');
    }
    
    $timestamp = base_convert(end($parts), 36, 10);
    $age = time() - ($timestamp / 1000);
    
    if ($age > 86400) { // 24 ore
        throw new InvalidArgumentException('User ID expired');
    }
    
    return true;
}
```

#### 7.2.2 Content Sanitization
```php
function sanitizeUserInput($input, $maxLength = 10000) {
    // Rimuovi caratteri di controllo
    $input = preg_replace('/[\x00-\x1F\x7F]/', '', $input);
    
    // Trim e length limit
    $input = trim($input);
    if (strlen($input) > $maxLength) {
        $input = substr($input, 0, $maxLength);
    }
    
    // Escape HTML se necessario (per logging)
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    
    return $input;
}
```

---

## 8. Integrazione Telegram

### 8.1 Struttura Bot

```
public_html/telegram-bot/
├── polling-bot.php          # Script polling principale
├── bot-functions.php        # Funzioni bot
├── config.php              # Configurazione bot
├── .htaccess               # Sicurezza directory
├── start-bot.sh            # Script avvio
└── stop-bot.sh             # Script stop
```

### 8.2 polling-bot.php

#### 8.2.1 Main Loop
```php
// Location: telegram-bot/polling-bot.php
<?php
require_once 'config.php';
require_once 'bot-functions.php';

$lastUpdateId = 0;

while (true) {
    try {
        // Ottieni updates da Telegram
        $updates = getUpdates($lastUpdateId + 1);
        
        foreach ($updates as $update) {
            processUpdate($update);
            $lastUpdateId = $update['update_id'];
        }
        
        // Sleep per evitare spam API
        sleep(1);
        
    } catch (Exception $e) {
        error_log("Bot error: " . $e->getMessage());
        sleep(5); // Sleep più lungo in caso di errore
    }
}

function processUpdate($update) {
    if (!isset($update['message'])) return;
    
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $text = $message['text'] ?? '';
    
    // Routing comandi
    if (strpos($text, '/') === 0) {
        handleCommand($chatId, $text);
    } else {
        handleMessage($chatId, $text);
    }
}
?>
```

#### 8.2.2 Command Handling
```php
// Location: telegram-bot/bot-functions.php ~line 45
function handleCommand($chatId, $command) {
    $parts = explode(' ', $command, 2);
    $cmd = strtolower($parts[0]);
    $args = $parts[1] ?? '';
    
    switch ($cmd) {
        case '/start':
            $welcome = "🤖 Ciao! Sono RentrIA, l'assistente virtuale specializzato nella gestione rifiuti.\n\n";
            $welcome .= "Puoi:\n";
            $welcome .= "• Farmi domande sulla gestione rifiuti\n";
            $welcome .= "• Chiedere info sui codici CER\n";
            $welcome .= "• Ottenere supporto per RENTRI\n\n";
            $welcome .= "🌐 Chat completa: https://lightbot.rentri360.it/chatbot.php";
            
            sendMessage($chatId, $welcome);
            break;
            
        case '/help':
            $help = "ℹ️ *Comandi disponibili:*\n\n";
            $help .= "/start - Messaggio di benvenuto\n";
            $help .= "/help - Mostra questo messaggio\n";
            $help .= "/gruppo - Link gruppo Telegram\n\n";
            $help .= "*Oppure scrivi direttamente la tua domanda!*";
            
            sendMessage($chatId, $help, 'Markdown');
            break;
            
        case '/gruppo':
            sendMessage($chatId, "👥 Unisciti al gruppo: https://t.me/rentrifacile");
            break;
            
        default:
            sendMessage($chatId, "❓ Comando non riconosciuto. Usa /help per vedere i comandi disponibili.");
    }
}
```

### 8.3 Integrazione con Chat API

```php
// Location: telegram-bot/bot-functions.php ~line 120
function handleMessage($chatId, $text) {
    // Chiama la stessa API utilizzata dal web
    $apiUrl = 'https://lightbot.rentri360.it/chat.php';
    $userId = 'tg_' . $chatId; // Prefix per utenti Telegram
    
    $data = ['content' => $text];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-User-ID: ' . $userId
        ],
        CURLOPT_RETURNTRANSFER => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        // Parse streaming response per Telegram
        $content = parseStreamingResponse($response);
        sendMessage($chatId, $content, 'Markdown');
    } else {
        sendMessage($chatId, "❌ Mi dispiace, si è verificato un errore. Riprova più tardi.");
    }
}
```

---

## 9. Logging e Monitoring

### 9.1 Sistema di Logging

#### 9.1.1 Struttura Log Directory
```
logs/
├── api/
│   └── YYYY-MM-DD.log          # Log API calls
├── performance/
│   └── YYYY-MM-DD.log          # Performance metrics
├── security/
│   └── YYYY-MM-DD.log          # Security events
├── general/
│   └── YYYY-MM-DD.log          # General application logs
├── lightbot.log                # Main application log
└── error_occurrences_YYYY-MM-DD.jsonl  # Structured error tracking
```

#### 9.1.2 Logger Class
```php
// Location: logger.php
class Logger {
    private static $logDir = __DIR__ . '/logs/';
    
    public static function logAPI($endpoint, $method, $statusCode, $responseTime, $context = []) {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => 'INFO',
            'category' => 'api',
            'method' => $method,
            'endpoint' => $endpoint,
            'status_code' => $statusCode,
            'response_time_ms' => round($responseTime, 2),
            'context' => $context
        ];
        
        $message = "[{$logData['timestamp']}] [{$logData['level']}] API {$method} {$endpoint} - {$statusCode} ({$responseTime}ms)";
        
        if (!empty($context)) {
            $message .= " | Context: " . json_encode($context);
        }
        
        self::writeLog('api', $message);
    }
    
    public static function logPerformance($operation, $duration, $context = []) {
        if ($duration > 1000) { // Log solo operazioni > 1s
            $logData = [
                'timestamp' => date('Y-m-d H:i:s'),
                'level' => 'WARNING',
                'category' => 'performance',
                'operation' => $operation,
                'duration_ms' => round($duration, 2),
                'threshold_ms' => 1000,
                'is_slow' => true,
                'context' => $context
            ];
            
            $message = "[{$logData['timestamp']}] [WARNING] Performance: {$operation} took {$duration}ms";
            
            if (!empty($context)) {
                $message .= " | Context: " . json_encode($context);
            }
            
            self::writeLog('performance', $message);
        }
    }
    
    private static function writeLog($category, $message) {
        $date = date('Y-m-d');
        $logFile = self::$logDir . $category . '/' . $date . '.log';
        
        // Assicura che la directory esista
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        error_log($message . PHP_EOL, 3, $logFile);
    }
}
```

### 9.2 Error Tracking

#### 9.2.1 ErrorTracker Class
```php
// Location: error-tracker.php
class ErrorTracker {
    private static $errorFile = __DIR__ . '/logs/error_occurrences_';
    
    public static function trackError($category, $message, $context = []) {
        $errorId = uniqid();
        $timestamp = time();
        
        $errorData = [
            'error_id' => $errorId,
            'timestamp' => $timestamp,
            'datetime' => date('Y-m-d H:i:s', $timestamp),
            'category' => $category,
            'message' => $message,
            'severity' => self::determineSeverity($category),
            'context' => $context,
            'server' => $_SERVER['SERVER_NAME'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        // Log in formato JSON Lines per analisi
        $logFile = self::$errorFile . date('Y-m-d') . '.jsonl';
        $jsonLine = json_encode($errorData) . PHP_EOL;
        error_log($jsonLine, 3, $logFile);
        
        // Log anche nel main log per visibilità immediata
        $mainMessage = "[ERROR] Error tracked: {$category} - {$message}";
        if (!empty($context)) {
            $mainMessage .= " | Context: " . json_encode($context);
        }
        
        error_log($mainMessage, 3, __DIR__ . '/logs/lightbot.log');
        
        return $errorId;
    }
    
    private static function determineSeverity($category) {
        $severityMap = [
            'database_error' => 'critical',
            'api_failure' => 'high',
            'rate_limit_violations' => 'medium',
            'validation_error' => 'low',
            'user_error' => 'low'
        ];
        
        return $severityMap[$category] ?? 'medium';
    }
}
```

### 9.3 Performance Monitoring

#### 9.3.1 Request Timing
```php
// Pattern utilizzato in tutti gli endpoint API
$startTime = microtime(true);

// ... elaborazione richiesta ...

$endTime = microtime(true);
$processingTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

// Log performance
Logger::logPerformance('api_call', $processingTime, [
    'user_id_hash' => substr($userId, 0, 25) . '...',
    'chunks_received' => $chunkCount ?? 0,
    'admin_bypass' => $isAdminBypass ? 'yes' : 'no'
]);

// Log API call
Logger::logAPI($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD'], 200, $processingTime, [
    'user_id_hash' => substr($userId, 0, 25) . '...',
    'message_length' => strlen($userMessage),
    'chunks_streamed' => $chunkCount ?? 0
]);
```

---

## 10. Testing e Debug

### 10.1 API Testing

#### 10.1.1 Chat API Test
```bash
#!/bin/bash
# test-chat-api.sh

DOMAIN="https://lightbot.rentri360.it"
USER_ID="fp_test_$(date +%s)_$(openssl rand -hex 16)"

echo "Testing Chat API with User ID: $USER_ID"

curl -X POST "$DOMAIN/chat.php" \
  -H "Content-Type: application/json" \
  -H "X-User-ID: $USER_ID" \
  -d '{
    "content": "Ciao, come funziona la gestione dei rifiuti pericolosi?"
  }' \
  --verbose
```

#### 10.1.2 Voice Transcription Test
```bash
#!/bin/bash
# test-voice-api.sh

DOMAIN="https://lightbot.rentri360.it"
USER_ID="fp_test_$(date +%s)_$(openssl rand -hex 16)"

echo "Testing Voice Transcription API"

curl -X POST "$DOMAIN/voice-transcription.php" \
  -H "X-User-ID: $USER_ID" \
  -F "audio=@test-audio.webm" \
  --verbose
```

#### 10.1.3 Image Analysis Test
```bash
#!/bin/bash
# test-image-api.sh

DOMAIN="https://lightbot.rentri360.it"
USER_ID="fp_test_$(date +%s)_$(openssl rand -hex 16)"

echo "Testing Image Analysis API"

curl -X POST "$DOMAIN/analyze-proxy.php" \
  -H "X-User-ID: $USER_ID" \
  -F "image=@test-image.jpg" \
  --verbose
```

### 10.2 Debug Configuration

#### 10.2.1 Development Error Reporting
```php
// In .env.dev
ENVIRONMENT=development
DEBUG=true

// In ogni endpoint PHP, aggiungere:
if ($_ENV['ENVIRONMENT'] === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    
    // Headers debug
    header('X-Debug-Mode: enabled');
    header('X-Debug-Time: ' . date('Y-m-d H:i:s'));
}
```

#### 10.2.2 Frontend Debug
```javascript
// In chatbot.php, quando ENVIRONMENT = development
const DEBUG_MODE = true; // Leggere da header o config

if (DEBUG_MODE) {
    // Console logging esteso
    const originalLog = console.log;
    console.log = function(...args) {
        originalLog('[LIGHTBOT DEBUG]', new Date().toISOString(), ...args);
    };
    
    // Error boundary per JavaScript
    window.addEventListener('error', function(event) {
        console.error('Global error caught:', {
            message: event.message,
            filename: event.filename,
            lineno: event.lineno,
            colno: event.colno,
            error: event.error
        });
    });
    
    // Performance monitoring
    const perfMonitor = {
        startTime: performance.now(),
        markEvent: function(name) {
            console.log(`Performance: ${name} at ${performance.now() - this.startTime}ms`);
        }
    };
    
    window.perfMonitor = perfMonitor;
}
```

### 10.3 Log Analysis

#### 10.3.1 Error Pattern Detection
```bash
#!/bin/bash
# analyze-errors.sh

LOG_DIR="/var/www/lightbot.rentri360.it/logs"
TODAY=$(date +%Y-%m-%d)

echo "=== Error Analysis for $TODAY ==="

# Count errors by category
echo "Errors by category:"
if [ -f "$LOG_DIR/error_occurrences_$TODAY.jsonl" ]; then
    cat "$LOG_DIR/error_occurrences_$TODAY.jsonl" | \
    jq -r '.category' | \
    sort | uniq -c | sort -nr
fi

# Performance issues
echo -e "\n=== Slow API calls (>5s) ==="
if [ -f "$LOG_DIR/performance/$TODAY.log" ]; then
    grep "took [5-9][0-9][0-9][0-9]\|took [1-9][0-9][0-9][0-9][0-9]" "$LOG_DIR/performance/$TODAY.log" | \
    head -10
fi

# Rate limit violations
echo -e "\n=== Rate Limit Issues ==="
grep -i "rate limit" "$LOG_DIR/lightbot.log" | tail -5
```

---

## 11. Guida alle Modifiche

### 11.1 Aggiungere Nuovo Endpoint API

#### 11.1.1 Creazione File
```php
// public_html/new-api-endpoint.php
<?php
require_once 'security-headers.php';
require_once 'database.php';
require_once 'logger.php';

// 1. Validazione metodo HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// 2. Validazione User ID
$userId = $_SERVER['HTTP_X_USER_ID'] ?? '';
if (!preg_match('/^fp_[a-f0-9]{25,}_[a-z0-9]{8,}$/', $userId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid user ID format']);
    exit;
}

// 3. Parse input
$input = json_decode(file_get_contents('php://input'), true);

// 4. Rate limiting (se necessario)
// $rateLimiter = new RateLimiter();
// if (!$rateLimiter->checkLimit($userId)) { ... }

// 5. Business logic
try {
    $startTime = microtime(true);
    
    $result = performNewAPIOperation($input, $userId);
    
    $endTime = microtime(true);
    $processingTime = ($endTime - $startTime) * 1000;
    
    // Log success
    Logger::logAPI('/new-api-endpoint.php', 'POST', 200, $processingTime, [
        'user_id_hash' => substr($userId, 0, 25) . '...',
        'operation' => 'new_operation'
    ]);
    
    echo json_encode(['success' => true, 'data' => $result]);
    
} catch (Exception $e) {
    $errorId = ErrorTracker::trackError('api_failure', $e->getMessage(), [
        'endpoint' => '/new-api-endpoint.php',
        'user_id_hash' => substr($userId, 0, 25) . '...'
    ]);
    
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Internal server error',
        'error_id' => $errorId
    ]);
}
?>
```

### 11.2 Aggiungere Nuovo Pulsante di Azione

#### 11.2.1 Modifica services.js
```javascript
// Location: services.js ~line XXX (dopo gli altri pulsanti)

addNewActionButton(messageElement, messageId) {
    // 1. Controllo esistenza
    const existingBtn = messageElement.querySelector('.new-action-btn');
    if (existingBtn) return;

    // 2. Creazione button
    const newBtn = document.createElement('button');
    newBtn.className = 'new-action-btn action-btn';
    newBtn.innerHTML = `
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <!-- Il tuo SVG path qui -->
            <path d="M12 2l3 3-3 3"/>
        </svg>
    `;
    
    // 3. Event handler
    const actionHandler = async () => {
        try {
            // La tua logica qui
            const result = await performNewAction(messageElement);
            showSuccessFeedback(newBtn, result);
        } catch (error) {
            console.error('New action failed:', error);
            showErrorFeedback(newBtn);
        }
    };

    newBtn.addEventListener('click', actionHandler);
    
    // 4. Storage handler per cleanup
    this.newActionHandlers.set(messageId, actionHandler);
    
    // 5. Aggiunta al DOM
    let wrapper = messageElement.parentElement;
    if (!wrapper || !wrapper.classList.contains('msg-wrapper')) {
        wrapper = document.createElement('div');
        wrapper.className = 'msg-wrapper';
        messageElement.parentNode.insertBefore(wrapper, messageElement);
        wrapper.appendChild(messageElement);
    }
    
    let btnContainer = wrapper.querySelector('.action-btn-container');
    if (!btnContainer) {
        btnContainer = document.createElement('div');
        btnContainer.className = 'action-btn-container';
        wrapper.appendChild(btnContainer);
    }
    
    btnContainer.appendChild(newBtn);
}

// 6. Aggiungere chiamata nel metodo principale addShareButtons()
addShareButtons(messageElement, messageId) {
    console.log('addShareButtons chiamato per messaggio:', messageId);
    
    this.addCopyButton(messageElement, messageId);
    this.addWhatsAppButton(messageElement, messageId);
    this.addTelegramButton(messageElement, messageId);
    this.addRentriButton(messageElement, messageId);
    this.addNewActionButton(messageElement, messageId); // <-- Aggiungere qui
}
```

### 11.3 Modificare Stili UI

#### 11.3.1 CSS in chatbot.php
```css
/* Location: chatbot.php ~line 800-1200 (nella sezione <style>) */

/* Nuovo stile per pulsante azione */
.new-action-btn {
  background: #f0f8ff;           /* Colore background specifico */
  border: 1px solid #4a90e2;    /* Border color */
  color: #2c5282;                /* Icon color */
}

.new-action-btn:hover {
  background: #e1f0ff;
  border-color: #2c5282;
}

.new-action-btn:active {
  transform: scale(0.95);
}

.new-action-btn.success {
  background: #d4edda;
  color: #155724;
  border-color: #c3e6cb;
}

/* Media query per responsiveness */
@media (max-width: 768px) {
  .new-action-btn {
    min-width: 45px;
    padding: 8px;
  }
  
  .new-action-btn svg {
    width: 28px;
    height: 28px;
  }
}
```

### 11.4 Aggiungere Nuovo Comando Telegram

#### 11.4.1 Modifica bot-functions.php
```php
// Location: telegram-bot/bot-functions.php ~line 45 (nella funzione handleCommand)

switch ($cmd) {
    // ... altri comandi esistenti ...
    
    case '/newcommand':
        handleNewCommand($chatId, $args);
        break;
        
    // ... default case ...
}

// Aggiungere nuova funzione handler
function handleNewCommand($chatId, $args) {
    // Validazione args se necessario
    if (empty($args)) {
        sendMessage($chatId, "❌ Parametri mancanti. Uso: /newcommand <parametro>");
        return;
    }
    
    try {
        // La tua logica qui
        $result = performNewCommandLogic($args);
        
        $response = "✅ Comando eseguito con successo!\n\n";
        $response .= "Risultato: " . $result;
        
        sendMessage($chatId, $response, 'Markdown');
        
    } catch (Exception $e) {
        error_log("New command error: " . $e->getMessage());
        sendMessage($chatId, "❌ Si è verificato un errore nell'esecuzione del comando.");
    }
}

function performNewCommandLogic($args) {
    // Implementa la tua logica qui
    // Puoi chiamare API esterne, accedere al database, ecc.
    
    return "Comando eseguito con parametro: " . $args;
}
```

### 11.5 Modificare Configurazione Nginx

#### 11.5.1 Aggiungere Location Block
```nginx
# Location: /etc/nginx/sites-enabled/lightbot.rentri360.it

server {
    # ... configurazione esistente ...
    
    # Nuovo location per API specifica
    location ~ ^/api/v2/ {
        # Rate limiting specifico
        limit_req zone=api burst=10 nodelay;
        
        # Headers speciali
        add_header X-API-Version "2.0" always;
        
        # PHP processing
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        
        # Timeout esteso per operazioni lunghe
        fastcgi_read_timeout 600s;
    }
    
    # Location per file statici specifici
    location ~* ^/assets/.*\.(jpg|jpeg|png|gif|ico|css|js)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        add_header Vary "Accept-Encoding";
    }
}
```

---

## 12. Troubleshooting

### 12.1 Problemi Comuni e Soluzioni

#### 12.1.1 HTTP 413 - Request Entity Too Large
**Sintomo**: Upload file fallisce con errore 413

**Diagnosi**:
```bash
# Check Nginx config
nginx -T | grep client_max_body_size

# Check PHP config
php -i | grep -E "upload_max_filesize|post_max_size"

# Check logs
tail -f /var/log/nginx/lightbot.rentri360.it_error.log
```

**Soluzione**:
```nginx
# /etc/nginx/sites-enabled/lightbot.rentri360.it
server {
    client_max_body_size 10M;  # Aumentare valore
}
```

```ini
# /etc/php/8.4/fpm/php.ini
upload_max_filesize = 10M
post_max_size = 10M
```

#### 12.1.2 Streaming Chat Non Funziona
**Sintomo**: Chat non mostra risposta in tempo reale

**Diagnosi**:
```javascript
// Debug in console browser
fetch('/chat.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ content: 'test' })
})
.then(response => {
    console.log('Response headers:', response.headers);
    console.log('Content-Type:', response.headers.get('content-type'));
});
```

**Possibili Cause**:
1. FastCGI buffering abilitato
2. Headers Content-Type non corretti
3. PHP output buffering abilitato

**Soluzione**:
```nginx
# Nginx config
fastcgi_buffering off;
proxy_buffering off;
```

```php
// PHP header
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
ob_end_flush(); // Disable output buffering
```

#### 12.1.3 User Fingerprint Non Generato
**Sintomo**: Errore "Invalid user ID format" in tutte le API calls

**Diagnosi**:
```javascript
// Debug in console
console.log('UserFingerprint available:', typeof window.UserFingerprint);
console.log('userFingerprint instance:', window.userFingerprint);

if (window.userFingerprint) {
    console.log('Generated ID:', window.userFingerprint.getUserId());
}
```

**Possibili Cause**:
1. JavaScript non caricato correttamente
2. Blocco CSP su script execution
3. Browser troppo restrittivo (iOS Safari)

**Soluzione**:
```javascript
// Fallback robusto in user-fingerprint.js
generateFingerprint() {
    try {
        // Metodo principale
        return this.generateAdvancedFingerprint();
    } catch (error) {
        console.warn('Advanced fingerprinting failed, using fallback');
        // Fallback semplice
        const random = Math.random().toString(36).substring(2, 15);
        const timestamp = Date.now().toString(36);
        return `fp_emergency_${random}_${timestamp}`;
    }
}
```

#### 12.1.4 Database Connection Failed
**Sintomo**: API returns "Database connection failed"

**Diagnosi**:
```bash
# Check MySQL service
systemctl status mysql

# Test connection
mysql -u lightbot_user -p lightbot

# Check PHP MySQL extension
php -m | grep -i mysql
```

**Soluzione**:
```bash
# Restart MySQL
systemctl restart mysql

# Check user privileges
mysql -u root -p
> SHOW GRANTS FOR 'lightbot_user'@'localhost';
> FLUSH PRIVILEGES;
```

### 12.2 Performance Issues

#### 12.2.1 API Calls Lente (>5s)
**Diagnosi**:
```bash
# Check performance logs
grep "took [5-9][0-9][0-9][0-9]" /var/www/lightbot.rentri360.it/logs/performance/$(date +%Y-%m-%d).log

# Monitor real-time
tail -f /var/www/lightbot.rentri360.it/logs/performance/$(date +%Y-%m-%d).log
```

**Possibili Cause**:
1. External API timeout
2. Database lock
3. PHP memory limit

**Soluzioni**:
```php
// Aumenta timeout per API externe
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

// Check memory usage
ini_set('memory_limit', '512M');

// Database optimization
// Aggiungi indici se necessario
```

#### 12.2.2 High Memory Usage
**Diagnosi**:
```bash
# Check PHP-FPM processes
ps aux | grep php-fpm | awk '{print $6}' | sort -n

# Monitor memory
watch -n 1 'free -m'
```

**Soluzione**:
```ini
# /etc/php/8.4/fpm/pool.d/www.conf
pm.max_children = 10
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 6
pm.max_requests = 500
```

### 12.3 Debugging Tools e Comandi

#### 12.3.1 Log Analysis Commands
```bash
# Real-time error monitoring
tail -f /var/www/lightbot.rentri360.it/logs/lightbot.log | grep ERROR

# API call statistics
grep "API POST" /var/www/lightbot.rentri360.it/logs/api/$(date +%Y-%m-%d).log | \
awk '{print $8}' | sed 's/(//' | sed 's/ms)//' | \
awk '{sum+=$1; n++} END {print "Avg response time: " sum/n "ms, Total calls: " n}'

# User activity analysis
grep "User ID from header" /var/log/nginx/lightbot.rentri360.it_error.log | \
sed 's/.*fp_//' | cut -d' ' -f1 | sort | uniq -c | sort -nr | head -10
```

#### 12.3.2 Quick Status Checks
```bash
#!/bin/bash
# status-check.sh

echo "=== System Status Check ==="
echo "Date: $(date)"
echo ""

echo "1. Services Status:"
systemctl is-active nginx && echo "✓ Nginx running" || echo "✗ Nginx down"
systemctl is-active php8.4-fpm && echo "✓ PHP-FPM running" || echo "✗ PHP-FPM down"
systemctl is-active mysql && echo "✓ MySQL running" || echo "✗ MySQL down"

echo ""
echo "2. Disk Usage:"
df -h /var/www/lightbot.rentri360.it | tail -1

echo ""
echo "3. Recent Errors (last 10):"
tail -10 /var/www/lightbot.rentri360.it/logs/lightbot.log | grep ERROR

echo ""
echo "4. Current API Load:"
curl -s -o /dev/null -w "Chat API: %{http_code} (%{time_total}s)\n" \
  https://lightbot.rentri360.it/chatbot.php

echo ""
echo "5. Database Connection:"
mysql -u lightbot_user -p$(grep DB_PASSWORD /var/www/lightbot.rentri360.it/.env | cut -d'=' -f2) \
  lightbot -e "SELECT COUNT(*) as total_users FROM user_limits;" 2>/dev/null && \
  echo "✓ Database accessible" || echo "✗ Database connection failed"
```

---

## 📝 Conclusione

Questa documentazione copre tutti gli aspetti tecnici necessari per sviluppare, mantenere ed estendere il sistema LIGHTBOT RENTRI360.it. 

**Key Takeaways per Sviluppatori**:

1. **Segui sempre i pattern esistenti** per naming, error handling e logging
2. **Testa ogni modifica** con gli script forniti nella sezione testing
3. **Monitora i log** dopo deployment per identificare issues
4. **Mantieni la sicurezza** validando sempre input e rate limiting
5. **Documenta le modifiche** aggiornando questo documento

Per domande specifiche o chiarimenti, consulta i file di codice referenziati con i numeri di riga indicati.

---

*🤖 Documentazione generata con [Claude Code](https://claude.ai/code)*