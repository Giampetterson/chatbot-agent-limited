<?php
/**
 * Image Analysis Proxy
 * Processes uploaded images using OpenAI Vision API for waste classification
 * 
 * Created: 2025-08-30
 * Purpose: Analyze images to classify waste objects for proper disposal
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

// Check Content-Length to prevent 413 errors
$maxUploadSize = 10 * 1024 * 1024; // 10MB
$contentLength = intval($_SERVER['CONTENT_LENGTH'] ?? 0);

if ($contentLength > $maxUploadSize) {
    http_response_code(413);
    echo json_encode([
        'error' => 'File troppo grande',
        'message' => 'La dimensione massima consentita è 10MB',
        'received_size' => $contentLength,
        'max_size' => $maxUploadSize
    ]);
    exit;
}

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once 'config-loader.php';

try {
    // Load configuration
    $config = ConfigLoader::getInstance();
    $openaiKey = $config->get('OPENAI_API_KEY', '');
    
    if (empty($openaiKey) || $openaiKey === 'PENDING_REGENERATION_REQUIRED') {
        throw new Exception('OpenAI API key not configured');
    }
    
    // Check if image was uploaded
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No image uploaded or upload error');
    }
    
    $image = $_FILES['image'];
    
    // Validate image
    if (!in_array($image['type'], ['image/jpeg', 'image/png', 'image/webp'])) {
        throw new Exception('Invalid image format. Only JPEG, PNG, and WebP are supported');
    }
    
    // Check file size (max 20MB for OpenAI Vision)
    if ($image['size'] > 20 * 1024 * 1024) {
        throw new Exception('Image too large. Maximum size: 20MB');
    }
    
    // Read and encode image
    $imageData = file_get_contents($image['tmp_name']);
    if ($imageData === false) {
        throw new Exception('Failed to read uploaded image');
    }
    
    $base64Image = base64_encode($imageData);
    $mimeType = $image['type'];
    
    // Prepare OpenAI Vision API request
    $payload = [
        'model' => 'gpt-4o-mini',
        'messages' => [
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Analizza questa immagine e identifica tutti i rifiuti visibili. Per ogni oggetto, fornisci:
1. Nome dell\'oggetto
2. Categoria di rifiuto (plastica, carta, vetro, metallo, organico, indifferenziato)
3. Peso stimato in grammi
4. Note specifiche per lo smaltimento

Rispondi in formato JSON con questa struttura:
{
  "objects": [
    {
      "name": "nome oggetto",
      "category": "categoria",
      "weight_grams": numero,
      "disposal_notes": "note specifiche"
    }
  ],
  "total_objects": numero,
  "total_weight_kg": numero,
  "general_notes": "note generali"
}'
                    ],
                    [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => "data:{$mimeType};base64,{$base64Image}"
                        ]
                    ]
                ]
            ]
        ],
        'max_tokens' => 1000,
        'temperature' => 0.1
    ];
    
    // Make request to OpenAI
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $openaiKey
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        throw new Exception('CURL error: ' . curl_error($ch));
    }
    
    curl_close($ch);
    
    if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        $errorMsg = $errorData['error']['message'] ?? 'OpenAI API error';
        throw new Exception("API error ($httpCode): $errorMsg");
    }
    
    $data = json_decode($response, true);
    
    if (!$data || !isset($data['choices'][0]['message']['content'])) {
        throw new Exception('Invalid API response format');
    }
    
    // Extract JSON from response
    $content = $data['choices'][0]['message']['content'];
    
    // Try to extract JSON from the response
    if (preg_match('/\{.*\}/s', $content, $matches)) {
        $analysisResult = json_decode($matches[0], true);
        
        if ($analysisResult) {
            echo json_encode($analysisResult);
        } else {
            // Fallback: create structured response from text
            echo json_encode([
                'objects' => [],
                'total_objects' => 0,
                'total_weight_kg' => 0,
                'general_notes' => $content
            ]);
        }
    } else {
        // Fallback response
        echo json_encode([
            'objects' => [],
            'total_objects' => 0,
            'total_weight_kg' => 0,
            'general_notes' => $content
        ]);
    }
    
} catch (Exception $e) {
    error_log("Image analysis error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'objects' => [],
        'total_objects' => 0,
        'total_weight_kg' => 0
    ]);
}
?>