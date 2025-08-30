<?php
/**
 * Endpoint per ottenere lo stato corrente del contatore messaggi utente
 */

header('Content-Type: application/json');
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
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once 'rate-limiter-db.php';

try {
    // Parse input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Ottieni User ID
    $userId = null;
    if (isset($_SERVER['HTTP_X_USER_ID'])) {
        $userId = $_SERVER['HTTP_X_USER_ID'];
    } elseif (isset($input['user_id'])) {
        $userId = $input['user_id'];
    }
    
    if (empty($userId)) {
        echo json_encode([
            'count' => 0,
            'max_count' => 10,
            'remaining' => 10,
            'status' => 'no_user_id'
        ]);
        exit;
    }
    
    // Inizializza RateLimiter
    $rateLimiter = new RateLimiterDB();
    
    // Ottieni stato corrente (senza incrementare)
    $limitCheck = $rateLimiter->getStatus($userId);
    
    echo json_encode([
        'count' => $limitCheck['count'],
        'max_count' => $limitCheck['max_count'], 
        'remaining' => max(0, $limitCheck['max_count'] - $limitCheck['count']),
        'status' => $limitCheck['status'],
        'can_send' => $limitCheck['can_send']
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'count' => 0,
        'max_count' => 10,
        'remaining' => 10
    ]);
}
?>