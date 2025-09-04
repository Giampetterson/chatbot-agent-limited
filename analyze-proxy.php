<?php
/**
 * Proxy per l'analisi immagini rifiuti
 * Inoltra le richieste ad analyze.php del sistema RentrIA
 */

// Headers per CORS se necessario
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gestione preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Solo POST ammessi
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo non ammesso']);
    exit();
}

// Verifica che ci sia un file immagine
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Nessuna immagine fornita o errore upload']);
    exit();
}

// Include analyze.php locale (ora autoconsistente)
$_POST['image'] = $_FILES['image'];
include 'analyze.php';
?>