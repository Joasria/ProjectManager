<?php
/**
 * Project Path Manager - API Principal
 * @description API REST amb control de versions i optimistic locking. 6 endpoints: health (check BD), list (metadata), get (complet+JSON parsed), create (auto-slug ID), update (transaccions+FOR UPDATE+conflict 409), history (limit configurable). 4 tipus changes: answer_question, update_task_status, toggle_checklist, update_memory (recursiu per subtasques). Device tracking. Transaccions completes amb row locking anti-race conditions. Version increment automàtic. Historial detallat per change. CORS complet.
 * @param string $action Acció a realitzar: health, list, get, create, update, history.
 * @param string $config Configuració de BD a utilitzar (per defecte: project_manager).
 * @param string $project_id ID del projecte (requerit per get, update, history).
 * @param int $limit Límit de resultats per historial (per defecte: 50).
 * @usage project_manager.php?action=health&config=project_manager
 * @usage project_manager.php?action=list&config=project_manager
 * @usage project_manager.php?action=get&config=project_manager&project_id=test-project
 * @usage project_manager.php?action=history&config=project_manager&project_id=test-project&limit=20
 * @example URL: https://www.contratemps.org/claudetools/project_manager.php?action=health&config=project_manager
 * @post Create: {"name": "Projecte", "description": "Desc", "template": "custom", "device": "mcp-client"}
 * @post Update: {"currentVersion": 1, "changes": [{"type": "answer_question", "questionId": "q1", "answer": "Resposta"}], "device": "mcp-client"}
 * @category Project Management
 * @reusable true
 * @note Optimistic locking: retorna 409 Conflict si currentVersion != BD version
 * @note Transaccions amb FOR UPDATE per evitar race conditions
 * @note Auto-genera project_id des del name (lowercase, guions, sanititzat)
 * @note Device tracking a version_history per cada change
 * @note Changes recursiu: busca tasques/subtasques en profunditat
 */

require_once 'pm_config.php';

// Gestionar OPTIONS per CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    exit;
}

// Obtenir ruta i mètode
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Extreure path sense query string
$path = parse_url($requestUri, PHP_URL_PATH);

// Eliminar prefix si existeix
$path = preg_replace('#^.*/project_manager\.php#', '', $path);
$path = preg_replace('#^/api#', '', $path);

// Router
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'health':
        healthCheck();
        break;
    case 'list':
        listProjects();
        break;
    case 'get':
        $projectId = $_GET['project_id'] ?? null;
        if (!$projectId) {
            sendJson(['error' => 'project_id requerit'], 400);
        }
        getProject($projectId);
        break;
    case 'create':
        createProject();
        break;
    case 'update':
        $projectId = $_GET['project_id'] ?? null;
        if (!$projectId) {
            sendJson(['error' => 'project_id requerit'], 400);
        }
        updateProject($projectId);
        break;
    case 'history':
        $projectId = $_GET['project_id'] ?? null;
        if (!$projectId) {
            sendJson(['error' => 'project_id requerit'], 400);
        }
        getProjectHistory($projectId);
        break;
    default:
        sendJson([
            'error' => 'Acció no vàlida',
            'available_actions' => ['health', 'list', 'get', 'create', 'update', 'history'],
            'usage' => 'project_manager.php?action=health&config=project_manager'
        ], 404);
}

// ==================== FUNCIONS ENDPOINTS ====================

function healthCheck() {
    $conn = getDbConnection();
    $dbStatus = $conn ? 'connected' : 'disconnected';
    if ($conn) $conn->close();
    
    sendJson([
        'status' => 'healthy',
        'database' => $dbStatus,
        'config' => $_GET['config'] ?? 'project_manager',
        'timestamp' => date('c')
    ]);
}

function listProjects() {
    $conn = getDbConnection();
    if (!$conn) {
        sendJson(['error' => 'Database connection failed'], 500);
    }
    
    $sql = "SELECT project_id, name, version, last_modified, modified_by 
            FROM projects 
            ORDER BY last_modified DESC";
    
    $result = $conn->query($sql);
    
    $projects = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $projects[] = $row;
        }
    }
    
    $conn->close();
    
    sendJson([
        'success' => true,
        'projects' => $projects
    ]);
}

function getProject($projectId) {
    $conn = getDbConnection();
    if (!$conn) {
        sendJson(['error' => 'Database connection failed'], 500);
    }
    
    $stmt = $conn->prepare("SELECT * FROM projects WHERE project_id = ?");
    $stmt->bind_param("s", $projectId);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $project = $result->fetch_assoc();
    
    $stmt->close();
    $conn->close();
    
    if (!$project) {
        sendJson(['error' => 'Project not found'], 404);
    }
    
    // Parsejar JSON data
    $project['data'] = json_decode($project['data'], true);
    
    sendJson([
        'success' => true,
        'project' => $project
    ]);
}

function createProject() {
    $data = getJsonInput();
    
    if (empty($data['name'])) {
        sendJson(['error' => 'Name is required'], 400);
    }
    
    // Generar project_id
    $projectId = strtolower($data['name']);
    $projectId = preg_replace('/[\s_]+/', '-', $projectId);
    $projectId = preg_replace('/[^a-z0-9-]/', '', $projectId);
    
    // Estructura inicial
    $projectData = [
        'name' => $data['name'],
        'description' => $data['description'] ?? '',
        'template' => $data['template'] ?? 'custom',
        'structure' => [
            'phases' => $data['phases'] ?? []
        ],
        'development' => [
            'questions' => [],
            'inProgressTasks' => [],
            'checklists' => []
        ]
    ];
    
    $conn = getDbConnection();
    if (!$conn) {
        sendJson(['error' => 'Database connection failed'], 500);
    }
    
    $device = $data['device'] ?? 'unknown';
    $jsonData = json_encode($projectData, JSON_UNESCAPED_UNICODE);
    
    $stmt = $conn->prepare("INSERT INTO projects (project_id, name, version, modified_by, data) VALUES (?, ?, 1, ?, ?)");
    $stmt->bind_param("ssss", $projectId, $data['name'], $device, $jsonData);
    
    if ($stmt->execute()) {
        // Registrar en historial
        $changeData = json_encode(['name' => $data['name']], JSON_UNESCAPED_UNICODE);
        $stmt2 = $conn->prepare("INSERT INTO version_history (project_id, version, device, change_type, change_data) VALUES (?, 1, ?, 'project_created', ?)");
        $stmt2->bind_param("sss", $projectId, $device, $changeData);
        $stmt2->execute();
        $stmt2->close();
        
        $stmt->close();
        $conn->close();
        
        sendJson([
            'success' => true,
            'projectId' => $projectId,
            'version' => 1
        ]);
    } else {
        $error = $stmt->error;
        $stmt->close();
        $conn->close();
        
        if (strpos($error, 'Duplicate entry') !== false) {
            sendJson(['error' => 'Project already exists'], 400);
        }
        
        sendJson(['error' => $error], 500);
    }
}

function updateProject($projectId) {
    $data = getJsonInput();
    
    if (!isset($data['currentVersion'])) {
        sendJson(['error' => 'currentVersion is required'], 400);
    }
    
    $conn = getDbConnection();
    if (!$conn) {
        sendJson(['error' => 'Database connection failed'], 500);
    }
    
    // Bloquejar fila per actualització
    $conn->begin_transaction();
    
    $stmt = $conn->prepare("SELECT version, data FROM projects WHERE project_id = ? FOR UPDATE");
    $stmt->bind_param("s", $projectId);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $project = $result->fetch_assoc();
    $stmt->close();
    
    if (!$project) {
        $conn->rollback();
        $conn->close();
        sendJson(['error' => 'Project not found'], 404);
    }
    
    // Verificar versió
    if ($data['currentVersion'] != $project['version']) {
        $conn->rollback();
        $conn->close();
        sendJson([
            'conflict' => true,
            'message' => "Versió desactualitzada. Última versió: v{$project['version']}",
            'latestVersion' => $project['version']
        ], 409);
    }
    
    // Aplicar canvis
    $projectData = json_decode($project['data'], true);
    
    foreach ($data['changes'] ?? [] as $change) {
        applyChange($projectData, $change);
    }
    
    // Incrementar versió
    $newVersion = $project['version'] + 1;
    $device = $data['device'] ?? 'unknown';
    $jsonData = json_encode($projectData, JSON_UNESCAPED_UNICODE);
    
    // Actualitzar projecte
    $stmt = $conn->prepare("UPDATE projects SET version = ?, modified_by = ?, data = ? WHERE project_id = ?");
    $stmt->bind_param("isss", $newVersion, $device, $jsonData, $projectId);
    $stmt->execute();
    $stmt->close();
    
    // Registrar canvis en historial
    foreach ($data['changes'] ?? [] as $change) {
        $changeType = $change['type'] ?? 'unknown';
        $changeJson = json_encode($change, JSON_UNESCAPED_UNICODE);
        
        $stmt = $conn->prepare("INSERT INTO version_history (project_id, version, device, change_type, change_data) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sisss", $projectId, $newVersion, $device, $changeType, $changeJson);
        $stmt->execute();
        $stmt->close();
    }
    
    $conn->commit();
    $conn->close();
    
    sendJson([
        'success' => true,
        'newVersion' => $newVersion
    ]);
}

function getProjectHistory($projectId) {
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
    
    $conn = getDbConnection();
    if (!$conn) {
        sendJson(['error' => 'Database connection failed'], 500);
    }
    
    $stmt = $conn->prepare("SELECT version, timestamp, device, change_type, change_data 
                            FROM version_history 
                            WHERE project_id = ? 
                            ORDER BY version DESC 
                            LIMIT ?");
    $stmt->bind_param("si", $projectId, $limit);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $history = [];
    
    while ($row = $result->fetch_assoc()) {
        if ($row['change_data']) {
            $row['change_data'] = json_decode($row['change_data'], true);
        }
        $history[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    
    sendJson([
        'success' => true,
        'history' => $history
    ]);
}

// ==================== FUNCIONS AUXILIARS ====================

function applyChange(&$data, $change) {
    $changeType = $change['type'] ?? null;
    
    switch ($changeType) {
        case 'answer_question':
            $questionId = $change['questionId'] ?? null;
            $answer = $change['answer'] ?? null;
            
            foreach ($data['development']['questions'] ?? [] as &$q) {
                if ($q['id'] === $questionId) {
                    $q['answer'] = $answer;
                    break;
                }
            }
            break;
            
        case 'update_task_status':
            $taskId = $change['taskId'] ?? null;
            $status = $change['status'] ?? null;
            updateTaskStatusRecursive($data['structure'] ?? [], $taskId, $status);
            break;
            
        case 'toggle_checklist':
            $taskId = $change['taskId'] ?? null;
            $itemId = $change['itemId'] ?? null;
            toggleChecklistRecursive($data['structure'] ?? [], $taskId, $itemId);
            break;
            
        case 'update_memory':
            $taskId = $change['taskId'] ?? null;
            $memory = $change['memory'] ?? null;
            updateMemoryRecursive($data['structure'] ?? [], $taskId, $memory);
            break;
    }
}

function updateTaskStatusRecursive(&$structure, $taskId, $status) {
    foreach ($structure['phases'] ?? [] as &$phase) {
        foreach ($phase['tasks'] ?? [] as &$task) {
            if ($task['id'] === $taskId) {
                $task['status'] = $status;
                return true;
            }
            if (isset($task['subtasks'])) {
                if (updateTaskStatusRecursive(['phases' => [['tasks' => &$task['subtasks']]]], $taskId, $status)) {
                    return true;
                }
            }
        }
    }
    return false;
}

function toggleChecklistRecursive(&$structure, $taskId, $itemId) {
    foreach ($structure['phases'] ?? [] as &$phase) {
        foreach ($phase['tasks'] ?? [] as &$task) {
            if ($task['id'] === $taskId) {
                foreach ($task['checklist'] ?? [] as &$item) {
                    if ($item['id'] === $itemId) {
                        $item['checked'] = !($item['checked'] ?? false);
                        return true;
                    }
                }
            }
            if (isset($task['subtasks'])) {
                if (toggleChecklistRecursive(['phases' => [['tasks' => &$task['subtasks']]]], $taskId, $itemId)) {
                    return true;
                }
            }
        }
    }
    return false;
}

function updateMemoryRecursive(&$structure, $taskId, $memory) {
    foreach ($structure['phases'] ?? [] as &$phase) {
        foreach ($phase['tasks'] ?? [] as &$task) {
            if ($task['id'] === $taskId) {
                $task['memory'] = $memory;
                return true;
            }
            if (isset($task['subtasks'])) {
                if (updateMemoryRecursive(['phases' => [['tasks' => &$task['subtasks']]]], $taskId, $memory)) {
                    return true;
                }
            }
        }
    }
    return false;
}
