<?php
/**
 * File Manager per Project Path Manager
 * @description Sistema CRUD complet per gestió d'arxius de projectes. Emmagatzema base64 a BD (LONGTEXT, ~4GB teòric, recomanable <16MB). Funcions: upload (POST JSON), list (metadata sense contingut), download (base64), delete, info. Auto-crea taula project_files amb FOREIGN KEY CASCADE i INDEX. Valida existència projecte abans upload. CORS complet (OPTIONS). Metadata: name, size, ext, description, uploaded_at, uploaded_by (default: mcp-client). UTF8MB4 InnoDB.
 * @param string $action Acció a realitzar: upload, list, download, delete, info.
 * @param string $config Configuració de BD a utilitzar (per defecte: project_manager).
 * @param string $project_id ID del projecte (requerit per list).
 * @param int $file_id ID de l'arxiu (requerit per download, delete, info).
 * @usage file_manager.php?action=list&config=project_manager&project_id=test-project
 * @usage file_manager.php?action=info&file_id=1
 * @usage file_manager.php?action=delete&file_id=1
 * @upload POST JSON: {"project_id": "test", "file_name": "doc.txt", "file_content": "base64data", "file_size": 1024, "file_ext": "txt", "description": "Document", "uploaded_by": "user"}
 * @limits Arxius emmagatzemats com LONGTEXT base64, límit teòric ~4GB però recomanable <16MB per performance
 * @category File Management
 * @reusable true
 * @note Auto-crea taula project_files si no existeix
 * @note FOREIGN KEY CASCADE: elimina arxius si s'elimina projecte
 * @note INDEX sobre project_id per optimitzar consultes
 * @note uploaded_by per defecte: 'mcp-client' si no s'especifica
 */

$_GET['config'] = $_GET['config'] ?? 'project_manager';
require_once 'pm_config.php';

// Gestionar OPTIONS per CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    exit;
}

// Router
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'upload':
        uploadFile();
        break;
    case 'list':
        listFiles();
        break;
    case 'download':
        downloadFile();
        break;
    case 'delete':
        deleteFile();
        break;
    case 'info':
        getFileInfo();
        break;
    default:
        sendJson([
            'error' => 'Acció no vàlida',
            'available_actions' => ['upload', 'list', 'download', 'delete', 'info']
        ], 404);
}

// ==================== FUNCIONS ENDPOINTS ====================

function uploadFile() {
    $data = getJsonInput();
    
    if (empty($data['project_id']) || empty($data['file_name']) || empty($data['file_content'])) {
        sendJson(['error' => 'Falten dades obligatòries'], 400);
    }
    
    $conn = getDbConnection();
    if (!$conn) {
        sendJson(['error' => 'Database connection failed'], 500);
    }
    
    // Crear taula d'arxius si no existeix
    createFilesTableIfNotExists($conn);
    
    // Verificar que el projecte existeix
    $stmt = $conn->prepare("SELECT project_id FROM projects WHERE project_id = ?");
    $stmt->bind_param("s", $data['project_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        sendJson(['error' => 'Projecte no trobat'], 404);
    }
    $stmt->close();
    
    // Insertar arxiu
    $stmt = $conn->prepare("
        INSERT INTO project_files 
        (project_id, file_name, file_content, file_size, file_ext, description, uploaded_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $uploaded_by = $data['uploaded_by'] ?? 'mcp-client';
    $stmt->bind_param(
        "sssssss",
        $data['project_id'],
        $data['file_name'],
        $data['file_content'],
        $data['file_size'],
        $data['file_ext'],
        $data['description'],
        $uploaded_by
    );
    
    if ($stmt->execute()) {
        $file_id = $conn->insert_id();
        $stmt->close();
        $conn->close();
        
        sendJson([
            'success' => true,
            'file_id' => $file_id,
            'message' => 'Arxiu pujat correctament'
        ]);
    } else {
        $error = $stmt->error;
        $stmt->close();
        $conn->close();
        sendJson(['error' => $error], 500);
    }
}

function listFiles() {
    $project_id = $_GET['project_id'] ?? null;
    
    if (!$project_id) {
        sendJson(['error' => 'project_id requerit'], 400);
    }
    
    $conn = getDbConnection();
    if (!$conn) {
        sendJson(['error' => 'Database connection failed'], 500);
    }
    
    createFilesTableIfNotExists($conn);
    
    $stmt = $conn->prepare("
        SELECT id, file_name, file_size, file_ext, description, uploaded_at, uploaded_by
        FROM project_files
        WHERE project_id = ?
        ORDER BY uploaded_at DESC
    ");
    $stmt->bind_param("s", $project_id);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $files = [];
    
    while ($row = $result->fetch_assoc()) {
        $files[] = [
            'id' => $row['id'],
            'file_name' => $row['file_name'],
            'file_size' => $row['file_size'],
            'file_ext' => $row['file_ext'],
            'description' => $row['description'],
            'uploaded_at' => $row['uploaded_at'],
            'uploaded_by' => $row['uploaded_by']
        ];
    }
    
    $stmt->close();
    $conn->close();
    
    sendJson([
        'success' => true,
        'files' => $files
    ]);
}

function downloadFile() {
    $file_id = $_GET['file_id'] ?? null;
    
    if (!$file_id) {
        sendJson(['error' => 'file_id requerit'], 400);
    }
    
    $conn = getDbConnection();
    if (!$conn) {
        sendJson(['error' => 'Database connection failed'], 500);
    }
    
    $stmt = $conn->prepare("
        SELECT file_name, file_content, file_ext
        FROM project_files
        WHERE id = ?
    ");
    $stmt->bind_param("i", $file_id);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $file = $result->fetch_assoc();
    
    $stmt->close();
    $conn->close();
    
    if (!$file) {
        sendJson(['error' => 'Arxiu no trobat'], 404);
    }
    
    sendJson([
        'success' => true,
        'file_name' => $file['file_name'],
        'file_content' => $file['file_content'],
        'file_ext' => $file['file_ext']
    ]);
}

function deleteFile() {
    $file_id = $_GET['file_id'] ?? null;
    
    if (!$file_id) {
        sendJson(['error' => 'file_id requerit'], 400);
    }
    
    $conn = getDbConnection();
    if (!$conn) {
        sendJson(['error' => 'Database connection failed'], 500);
    }
    
    $stmt = $conn->prepare("DELETE FROM project_files WHERE id = ?");
    $stmt->bind_param("i", $file_id);
    
    if ($stmt->execute()) {
        $affected = $stmt->affected_rows;
        $stmt->close();
        $conn->close();
        
        if ($affected > 0) {
            sendJson([
                'success' => true,
                'message' => 'Arxiu eliminat'
            ]);
        } else {
            sendJson(['error' => 'Arxiu no trobat'], 404);
        }
    } else {
        $error = $stmt->error;
        $stmt->close();
        $conn->close();
        sendJson(['error' => $error], 500);
    }
}

function getFileInfo() {
    $file_id = $_GET['file_id'] ?? null;
    
    if (!$file_id) {
        sendJson(['error' => 'file_id requerit'], 400);
    }
    
    $conn = getDbConnection();
    if (!$conn) {
        sendJson(['error' => 'Database connection failed'], 500);
    }
    
    $stmt = $conn->prepare("
        SELECT id, project_id, file_name, file_size, file_ext, description, uploaded_at, uploaded_by
        FROM project_files
        WHERE id = ?
    ");
    $stmt->bind_param("i", $file_id);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $file = $result->fetch_assoc();
    
    $stmt->close();
    $conn->close();
    
    if (!$file) {
        sendJson(['error' => 'Arxiu no trobat'], 404);
    }
    
    sendJson([
        'success' => true,
        'file' => $file
    ]);
}

// ==================== FUNCIONS AUXILIARS ====================

function createFilesTableIfNotExists($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS project_files (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id VARCHAR(255) NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_content LONGTEXT NOT NULL,
        file_size INT NOT NULL,
        file_ext VARCHAR(50),
        description TEXT,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        uploaded_by VARCHAR(100),
        FOREIGN KEY (project_id) REFERENCES projects(project_id) ON DELETE CASCADE,
        INDEX idx_project_files (project_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->query($sql);
}
