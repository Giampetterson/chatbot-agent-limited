<?php
/**
 * Image waste categorization + weight estimation (GPT-4o-mini)
 * PHP 8.1+, ext-curl, ext-json
 */

const OPENAI_API_KEY = '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';
const OPENAI_API_URL = 'https://api.openai.com/v1/chat/completions';
const OPENAI_MODEL   = 'gpt-4o';

header('Content-Type: application/json; charset=utf-8');

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

/** =========================
 *  0) TABELLA PESI (grammi)
 *  =========================
 *  Heuristics iniziali: personalizza in base ai tuoi casi reali.
 *  Struttura: [product_type][material]['small'|'medium'|'large'] = grammi
 *  + fallback per solo material.
 */
$UNIT_WEIGHTS = [
    'bottiglia' => [
        'plastica' => ['small'=>12, 'medium'=>24, 'large'=>30],   // PET ~330-500ml, 1L, 1.5L
        'vetro'    => ['small'=>200,'medium'=>400,'large'=>500],  // 330ml, 500-700ml, 750ml+
    ],
    'lattina' => [
        'metallo'  => ['small'=>14, 'medium'=>16, 'large'=>19],   // 330ml, 500ml, >500ml
    ],
    'scatola' => [
        'cartone'  => ['small'=>80, 'medium'=>180,'large'=>400],  // snack, pizza box, grande
    ],
    'brick' => [
        'composito'=> ['small'=>24, 'medium'=>35, 'large'=>50],   // Tetra Pak ~0.5L, 1L, >1L
    ],
    'bicchiere' => [
        'plastica' => ['small'=>3,  'medium'=>6,  'large'=>9],
        'vetro'    => ['small'=>150,'medium'=>250,'large'=>350],
    ],
    'tappo' => [
        'plastica' => ['small'=>2,  'medium'=>3,  'large'=>4],
        'metallo'  => ['small'=>3,  'medium'=>5,  'large'=>7],
    ],
    'sacchetto' => [
        'plastica' => ['small'=>5,  'medium'=>8,  'large'=>12],
    ],
    'batteria' => [
        'e-waste'  => ['small'=>12, 'medium'=>24, 'large'=>45],   // AAA, AA, 9V
    ],
    'vaschetta' => [
        'plastica' => ['small'=>8,  'medium'=>15, 'large'=>25],
    ],
    // generici
    'carta' => [
        'carta'    => ['small'=>5,  'medium'=>20, 'large'=>50],
    ],
];

// Fallback se manca la combinazione precisa (media generica per materiale)
$FALLBACK_BY_MATERIAL = [
    'plastica'=> 8, 'vetro'=> 400, 'cartone'=>150, 'carta'=>30, 'metallo'=>16,
    'organico'=>200, 'legno'=>300, 'tessile'=>150, 'e-waste'=>50, 'composito'=>30, 'altro'=>50
];

// Normalizzazione semplici sinonimi (opzionale)
function norm($s) {
    $s = mb_strtolower(trim($s));
    // qualche alias utile
    $aliases = [
        'bottles' => 'bottiglia', 'bottle'=>'bottiglia',
        'can'=>'lattina', 'cans'=>'lattina',
        'cup'=>'bicchiere', 'paper cup'=>'bicchiere',
        'cap'=>'tappo', 'bag'=>'sacchetto', 'tray'=>'vaschetta',
        'tetra pak'=>'brick', 'milk carton'=>'brick',
        'battery'=>'batteria', 'box'=>'scatola',
    ];
    return $aliases[$s] ?? $s;
}

/** Risolve il peso unitario in grammi seguendo l'ordine:
 *  1) product_type + material + size_class
 *  2) product_type + qualsiasi material (se definito)
 *  3) solo material (fallback generico)
 *  4) default 20g
 */
function resolve_unit_weight(string $productType, string $material, ?string $sizeClass,
                             array $WEIGHTS, array $FALLBACK_MAT): int {
    $pt = norm($productType);
    $mat = norm($material);
    $sz  = $sizeClass ?: 'medium';

    if (isset($WEIGHTS[$pt][$mat][$sz])) {
        return (int)$WEIGHTS[$pt][$mat][$sz];
    }
    if (isset($WEIGHTS[$pt])) {
        // prova a prendere una media del product_type se esiste
        $candidates = [];
        foreach ($WEIGHTS[$pt] as $m => $sizes) {
            if (isset($sizes[$sz])) $candidates[] = $sizes[$sz];
        }
        if ($candidates) return (int) round(array_sum($candidates)/count($candidates));
    }
    if (isset($FALLBACK_MAT[$mat])) return (int)$FALLBACK_MAT[$mat];

    return 20; // ultima spiaggia
}

try {
    // 1) Input: URL o file
    $imageUrl = $_POST['image_url'] ?? null;
    $imageB64 = null;

    if (isset($_FILES['image']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
        $bytes = file_get_contents($_FILES['image']['tmp_name']);
        if ($bytes === false) throw new RuntimeException('Impossibile leggere il file caricato');
        
        // Ottimizza immagine per OpenAI (ridimensiona e converti a JPEG)
        $image = imagecreatefromstring($bytes);
        if ($image === false) throw new RuntimeException('Formato immagine non valido');
        
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Ridimensiona per OpenAI (max 1024px)
        $maxDimension = 1024;
        $ratio = min($maxDimension / $width, $maxDimension / $height, 1);
        
        if ($ratio < 1) {
            $newWidth = intval($width * $ratio);
            $newHeight = intval($height * $ratio);
            
            $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
            $white = imagecolorallocate($resizedImage, 255, 255, 255);
            imagefill($resizedImage, 0, 0, $white);
            imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resizedImage;
        }
        
        // Converti a JPEG con qualità 85
        ob_start();
        imagejpeg($image, null, 85);
        $optimizedBytes = ob_get_contents();
        ob_end_clean();
        imagedestroy($image);
        
        $imageB64 = 'data:image/jpeg;base64,' . base64_encode($optimizedBytes);
    } elseif (!$imageUrl) {
        throw new InvalidArgumentException('Fornisci image_url oppure carica file image.');
    }

    // 2) JSON Schema: aggiungiamo size_class
    $schema = [
        "name" => "waste_detection_result",
        "schema" => [
            "type" => "object",
            "additionalProperties" => false,
            "properties" => [
                "items" => [
                    "type" => "array",
                    "items" => [
                        "type" => "object",
                        "additionalProperties" => false,
                        "properties" => [
                            "product_type" => [
                                "type" => "string",
                                "description" => "Tipo di prodotto (es. bottiglia, lattina, scatola, batteria, tappo, sacchetto, bicchiere, cartone, brick, vaschetta, etc.)"
                            ],
                            "material" => [
                                "type" => "string",
                                "description" => "Materiale principale",
                                "enum" => [
                                    "plastica","vetro","carta","cartone","metallo",
                                    "organico","legno","tessile","e-waste","composito","altro"
                                ]
                            ],
                            "size_class" => [
                                "type" => "string",
                                "description" => "Stima dimensione",
                                "enum" => ["small","medium","large"]
                            ],
                            "quantity" => [
                                "type" => "integer",
                                "minimum" => 0,
                                "description" => "Numero di oggetti visibili"
                            ]
                        ],
                        "required" => ["product_type","material","quantity"]
                    ]
                ],
                "notes" => [
                    "type" => "string",
                    "description" => "Osservazioni utili o incertezze (opzionale)"
                ]
            ],
            "required" => ["items"]
        ],
        "strict" => false
    ];

    // 3) Prompt esteso e complesso con tecniche avanzate
    $system = "Sei un sistema avanzato di riconoscimento rifiuti con expertise italiana.";
    $userText = <<<TXT
### Ruolo e Contesto (Role Prompting + Priming)
Agisci come un esperto del settore rifiuti in Italia.  
Il tuo compito è analizzare immagini di rifiuti, riconoscere e categorizzare gli oggetti visibili con la massima precisione, applicando logiche prudenziali quando necessario.  
Il tuo obiettivo finale è fornire un output in JSON che descriva:  
- Oggetti riconosciuti e classificati  
- Peso singolare stimato  
- Peso totale per ogni gruppo omogeneo  
- Peso totale complessivo  

---

### Linee Guida Generali (Zero-Shot + Few-Shot Priming)
1. Identifica gli oggetti tipici dei rifiuti (bottiglia, lattina, scatola, brick, bicchiere, sacchetto, tappo, vaschetta, batteria, ecc.).  
2. Per ogni gruppo omogeneo, fornisci:  
   - **product_type**: singolare (es. "bottiglia")  
   - **material**: scegli tra {plastica, vetro, carta, cartone, metallo, organico, legno, tessile, e-waste, composito, altro}  
   - **quantity**: numero oggetti visibili; se parziali → stima prudente  
   - **size_class**: small | medium | large (usa le regole sotto)  
   - **weight_single_estimated_kg**: peso medio stimato per oggetto  
   - **weight_total_group_kg**: quantità × peso singolo  
   - **notes**: spiega dubbi, parzialità o motivazioni  

---

### Linee Guida Dimensionali (Least-to-Most + Tree of Thought)
- **Bottiglie** → small ≤0.5L | medium ~1L | large ≥1.5L  
- **Lattine** → small ~330ml | medium ~500ml | large >500ml  
- **Scatole** → small (snack/piccola) | medium (pizza box standard) | large (molto grande)  
- **Se incerto → sempre "medium"**  

---

### Calcoli Richiesti (Chain of Thought integrato)
1. Stima peso singolare per ogni oggetto identificato.  
2. Calcola peso totale di ogni gruppo omogeneo.  
3. Calcola peso complessivo globale (somma di tutti i gruppi).  
*(Ragiona passo passo internamente, ma mostra solo il risultato finale in JSON.)*  

---

### Autocoerenza e Gestione Errori
- Restituisci **SOLO JSON valido**.  
- Se non riesci a identificare un oggetto → material="altro" + spiegazione in notes.  
- Se non riesci a calcolare pesi precisi → fornisci la stima più prudente possibile e aggiungi una nota di scuse ("ancora in allenamento").  
- Non aggiungere testo esterno al JSON.

Restituisci SOLO JSON conforme allo schema.
TXT;

    // 4) Contenuto multimodale (testo + immagine)
    $imageContent = $imageUrl
        ? ["type" => "image_url", "image_url" => ["url" => $imageUrl]]
        : ["type" => "image_url", "image_url" => ["url" => $imageB64]];

    $payload = [
        "model" => OPENAI_MODEL,
        "response_format" => [
            "type" => "json_schema",
            "json_schema" => $schema
        ],
        "messages" => [
            ["role" => "system", "content" => $system],
            [
                "role" => "user",
                "content" => [
                    ["type" => "text", "text" => $userText],
                    $imageContent
                ]
            ]
        ],
        "max_tokens" => 500
    ];

    // 5) Chiamata API
    $ch = curl_init(OPENAI_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . OPENAI_API_KEY,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($payload)
    ]);
    $raw = curl_exec($ch);
    if ($raw === false) throw new RuntimeException('Errore cURL: ' . curl_error($ch));
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http < 200 || $http >= 300) {
        http_response_code($http);
        // Debug completo per capire l'errore
        $payloadSize = strlen(json_encode($payload));
        $errorDetails = json_decode($raw, true);
        $errorMsg = $errorDetails['error']['message'] ?? 'Unknown error';
        $logMsg = "OpenAI HTTP $http - Payload: {$payloadSize} chars - Error: $errorMsg";
        error_log($logMsg);
        
        echo json_encode([
            "error" => "OpenAI HTTP $http", 
            "message" => $errorMsg,
            "details" => $errorDetails ?: $raw
        ], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        exit;
    }

    $resp = json_decode($raw, true);
    $content = $resp['choices'][0]['message']['content'] ?? null;
    if (!$content) {
        echo json_encode(["error" => "Risposta inattesa", "raw" => $resp], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        exit;
    }

    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(["warning" => "JSON non decodificabile, vedi content_raw", "content_raw" => $content], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 6) Annotazione pesi per ogni item + aggregazioni
    $totalsByMaterialQty = [];
    $totalsByMaterialMass = [];
    $grandTotalMassG = 0;

    foreach ($data['items'] as &$item) {
        $pt  = $item['product_type'] ?? 'sconosciuto';
        $mat = $item['material'] ?? 'altro';
        $sz  = $item['size_class'] ?? 'medium';
        $qty = (int)($item['quantity'] ?? 0);

        $unit = resolve_unit_weight($pt, $mat, $sz, $UNIT_WEIGHTS, $FALLBACK_BY_MATERIAL);
        $itemWeight = $unit * $qty;

        $item['unit_weight_g'] = $unit;
        $item['estimated_weight_g'] = $itemWeight;

        // aggregazioni
        $totalsByMaterialQty[$mat]  = ($totalsByMaterialQty[$mat]  ?? 0) + $qty;
        $totalsByMaterialMass[$mat] = ($totalsByMaterialMass[$mat] ?? 0) + $itemWeight;
        $grandTotalMassG += $itemWeight;
    }
    unset($item);

    // Aggiungi riepiloghi
    $data['totals_by_material'] = [];
    foreach ($totalsByMaterialQty as $m => $q) {
        $data['totals_by_material'][] = [
            "material" => $m,
            "count"    => $q,
            "mass_g"   => (int)$totalsByMaterialMass[$m]
        ];
    }
    $data['grand_total_mass_g'] = (int)$grandTotalMassG;

    echo json_encode($data, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(["error" => $e->getMessage()], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
}
?>