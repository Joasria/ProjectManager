<?php
/**
 * Configuració Especialitzada per Project Manager
 * @description Biblioteca configuració especialitzada per Project Manager. Carrega config BD des de tools-config.json amb fallback localhost. Define constants globals (DB_HOST/USER/PASS/NAME). Exporta 3 helpers: getDbConnection() (MySQLi utf8mb4), sendJson() (CORS automàtic), getJsonInput() (POST decode). Configurable via GET param 'config' (default: project_manager). Timezone: Europe/Madrid.
 * @param string $config Nom de la configuració BD a carregar (per defecte: project_manager)
 * @category General
 * @reusable false
 * @usage require_once 'pm_config.php'; (fitxer d'inclusió amb configuració dinàmica)
 * @usage pm_config.php?config=project_manager (carrega configuració específica)
 * @functions loadConfig($configName) - Carrega configuració BD des de tools-config.json
 * @functions getDbConnection() - Retorna connexió MySQLi amb charset utf8mb4
 * @functions sendJson($data, $statusCode) - Envia resposta JSON amb headers CORS automàtics
 * @functions getJsonInput() - Llegeix i decodifica input JSON del POST
 * @note Fallback a localhost si tools-config.json no existeix
 * @note Define constants globals: DB_HOST, DB_USER, DB_PASS, DB_NAME
 */

// Carregar configuració del sistema claudetools
$CONFIG_FILE = __DIR__ . '/tools-config.json';

function loadConfig($configName = 'project_manager') {
    global $CONFIG_FILE;
    
    if (!file_exists($CONFIG_FILE)) {
        return [
            'host' => 'localhost',
            'user' => 'root',
            'password' => '',
            'database' => 'db_project_manager'
        ];
    }
    
    $configs = json_decode(file_get_contents($CONFIG_FILE), true);
    
    // Llegir de database_configs
    if (isset($configs['database_configs'][$configName])) {
        $dbConfig = $configs['database_configs'][$configName];
        return [
            'host' => $dbConfig['host'],
            'user' => $dbConfig['username'],
            'password' => $dbConfig['password'],
            'database' => $dbConfig['database']
        ];
    }
    
    return null;
}

// Obtenir configuració
$configName = $_GET['config'] ?? 'project_manager';
$dbConfig = loadConfig($configName);

if (!$dbConfig) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => "Configuració '$configName' no trobada",
        'hint' => "Afegeix-la amb config_manager.php"
    ]);
    exit;
}

// Configuració de la base de dades
define('DB_HOST', $dbConfig['host']);
define('DB_USER', $dbConfig['user']);
define('DB_PASS', $dbConfig['password']);
define('DB_NAME', $dbConfig['database']);

// Timezone
date_default_timezone_set('Europe/Madrid');

/**
 * Obté connexió amb MySQL
 */
function getDbConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        $conn->set_charset("utf8mb4");
        return $conn;
        
    } catch (Exception $e) {
        error_log("Database connection error: " . $e->getMessage());
        return null;
    }
}

/**
 * Envia resposta JSON
 */
function sendJson($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Obté dades POST en format JSON
 */
function getJsonInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true);
}
