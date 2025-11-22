<?php
/**
 * Gestor Genèric d'Estructures de Carpetes
 * @description Gestor d'estructures de carpetes via JSON POST. Crea directoris recursivament amb permisos 0755, genera README.md automàtic per carpetes amb 'description', i verifica existència. Skip carpetes existents sense sobreescriure. Gestió d'errors individual per carpeta (continua amb errors parcials). Base path personalitzable (GET), estructura en POST JSON amb format: {folder: {description, subdirs: {...}}}.
 * @param string $action Acció a realitzar: create (crea carpetes), verify (verifica existència), list (mostra estructura rebuda).
 * @param string $base_path Ruta base on es crearà o verificarà l'estructura (opcional, per defecte és l'arrel del document).
 * @usage folder_manager.php?action=create (amb POST JSON)
 * @usage folder_manager.php?action=verify&base_path=/path/custom (amb POST JSON)
 * @usage POST JSON format: {"structure_data": {"carpeta1": {"description": "Descripció", "subdirs": {"subcarpeta1": {}}}, "carpeta2": {}}}
 * @example POST amb README: {"structure_data": {"src": {"description": "Codi font", "subdirs": {"components": {"description": "Components React"}}}}}
 * @category System Management
 * @reusable true
 * @note Crea README.md automàtic si 'description' està present
 * @note Carpetes creades amb permisos 0755
 * @note Skip carpetes existents (no sobreescriu)
 * @note Gestió errors individual: continua creant altres carpetes si n'hi ha errors
 */

// Funció principal per processar la petició
function main() {
    // Determinar l'acció a partir de GET, per mantenir la URL simple
    $action = $_GET['action'] ?? 'list';
    $base_path = $_GET['base_path'] ?? $_SERVER['DOCUMENT_ROOT'];

    // Les dades de l'estructura sempre es llegeixen del cos de la petició POST
    $input_data = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Error de sintaxi al JSON rebut: " . json_last_error_msg());
    }
    $folder_structure = $input_data['structure_data'] ?? null;
    if (!$folder_structure) {
        throw new Exception("No s'han trobat les dades 'structure_data' a la petició POST.");
    }

    $response = [];
    
    switch ($action) {
        case 'list':
            $response['folder_structure'] = $folder_structure;
            $response['message'] = "Definició de l'estructura de carpetes rebuda correctament.";
            break;
            
        case 'create':
            $result = createFolderStructure($folder_structure, $base_path);
            $response['created_folders'] = $result['created'];
            $response['errors'] = $result['errors'];
            $response['message'] = count($result['created']) . ' carpetes creades correctament.';
            if (!empty($result['errors'])) {
                $response['warning'] = 'Alguns errors durant la creació. Revisa el camp errors.';
            }
            break;
            
        case 'verify':
            $result = verifyFolderStructure($folder_structure, $base_path);
            $response['existing_folders'] = $result['existing'];
            $response['missing_folders'] = $result['missing'];
            $response['message'] = count($result['existing']) . ' carpetes existents, ' . count($result['missing']) . ' falten.';
            break;
            
        default:
            throw new Exception("Acció no vàlida. Accions disponibles: list, create, verify");
    }
    return $response;
}

// Crear estructura de carpetes recursivament
function createFolderStructure($structure, $base_path = '') {
    $created = [];
    $errors = [];
    foreach ($structure as $folder_name => $folder_info) {
        $full_path = rtrim($base_path, '/') . '/' . $folder_name;
        try {
            if (!file_exists($full_path)) {
                mkdir($full_path, 0755, true);
                $created[] = $full_path;
            }
            if (isset($folder_info['description'])) {
                $readme_path = $full_path . '/README.md';
                if (!file_exists($readme_path)) {
                    file_put_contents($readme_path, "# " . ucfirst($folder_name) . "\n\n" . $folder_info['description'] . "\n");
                }
            }
            if (isset($folder_info['subdirs']) && !empty($folder_info['subdirs'])) {
                $result = createFolderStructure($folder_info['subdirs'], $full_path);
                $created = array_merge($created, $result['created']);
                $errors = array_merge($errors, $result['errors']);
            }
        } catch (Exception $e) {
            $errors[$full_path] = $e->getMessage();
        }
    }
    return ['created' => $created, 'errors' => $errors];
}

// Verificar estructura existent
function verifyFolderStructure($structure, $base_path = '') {
    $existing = [];
    $missing = [];
    foreach ($structure as $folder_name => $folder_info) {
        $full_path = rtrim($base_path, '/') . '/' . $folder_name;
        if (file_exists($full_path) && is_dir($full_path)) {
            $existing[] = $full_path;
        } else {
            $missing[] = $full_path;
        }
        if (isset($folder_info['subdirs']) && !empty($folder_info['subdirs'])) {
            $result = verifyFolderStructure($folder_info['subdirs'], $full_path);
            $existing = array_merge($existing, $result['existing']);
            $missing = array_merge($missing, $result['missing']);
        }
    }
    return ['existing' => $existing, 'missing' => $missing];
}

// Punt d'entrada de l'script
try {
    $response_data = main();
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success'] + $response_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>
