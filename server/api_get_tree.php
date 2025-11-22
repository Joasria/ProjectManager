<?php
/**
 * API: Get Tree - Retorna entrades d'un projecte ordenades jeràrquicament
 * 
 * Paràmetres GET:
 * - project_id: ID del projecte (obligatori)
 * 
 * Retorna JSON amb array d'entrades ordenades amb depth i full_path calculats
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Carregar configuració
require_once __DIR__ . '/pm_config.php';

// Validar paràmetre
$project_id = $_GET['project_id'] ?? null;

if (!$project_id) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Paràmetre project_id obligatori',
        'usage' => 'api_get_tree.php?project_id=001'
    ]);
    exit;
}

// Sanititzar project_id (només números)
if (!preg_match('/^\d{3}$/', $project_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'project_id ha de ser un número de 3 dígits (ex: 001)']);
    exit;
}

try {
    $conn = getDbConnection();
    $table = "project_" . $project_id;
    
    // Verificar que la taula existeix
    $check = $conn->query("SHOW TABLES LIKE '$table'");
    if ($check->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => "Projecte $project_id no existeix"]);
        exit;
    }
    
    // 1. Llegir totes les entrades
    $result = $conn->query("SELECT * FROM $table");
    $entries = [];
    while ($row = $result->fetch_assoc()) {
        $entries[] = $row;
    }
    
    // 2. Crear mapa parent_id => fills
    $children_map = ['ROOT' => []];
    foreach ($entries as $entry) {
        $pid = $entry['parent_id'] ?? 'ROOT';
        if (!isset($children_map[$pid])) {
            $children_map[$pid] = [];
        }
        $children_map[$pid][] = $entry;
    }
    
    // 3. Ordenar germans per local_path (ordenació natural: 1, 2, 10, a, b)
    foreach ($children_map as &$siblings) {
        usort($siblings, function($a, $b) {
            return strnatcasecmp($a['local_path'], $b['local_path']);
        });
    }
    
    // 4. Construir array ordenat recursivament
    $ordered = [];
    buildFlatTree($children_map, 'ROOT', $ordered, 0, '');
    
    // Retornar resultat
    sendJson([
        'success' => true,
        'project_id' => $project_id,
        'total_entries' => count($ordered),
        'entries' => $ordered
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    sendJson(['error' => 'Error intern: ' . $e->getMessage()]);
}

/**
 * Construeix arbre aplanat amb depth i full_path calculats
 */
function buildFlatTree($children_map, $parent_key, &$result, $depth, $parent_path) {
    if (!isset($children_map[$parent_key])) return;
    
    foreach ($children_map[$parent_key] as $entry) {
        // Calcular full_path dinàmicament
        $full_path = $parent_path 
            ? $parent_path . '.' . $entry['local_path']
            : $entry['local_path'];
        
        // Afegir camps calculats
        $entry['depth'] = $depth;
        $entry['full_path'] = $full_path;
        
        // Decodificar context_data si és JSON vàlid
        if ($entry['context_data']) {
            $decoded = json_decode($entry['context_data'], true);
            $entry['context_data'] = $decoded ?: $entry['context_data'];
        }
        
        $result[] = $entry;
        
        // Recursiu pels fills
        buildFlatTree($children_map, $entry['id'], $result, $depth + 1, $full_path);
    }
}
