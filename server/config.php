<?php
/**
 * Biblioteca de Configuració i Utilitats de Connexió
 * @description Biblioteca configuració base amb suport .env.php. Define constants BD des de getenv() amb fallbacks (localhost/root/project_manager). Include DEBUG flag opcional. Exporta 3 helpers: getDbConnection() (MySQLi utf8mb4), sendJson() (CORS), getJsonInput(). Error_log condicional per DEBUG. Timezone: Europe/Madrid. Fitxer d'inclusió genèric per altres eines.
 * @category General
 * @reusable false
 * @usage require_once 'config.php'; (fitxer d'inclusió, no executable directament)
 * @functions getDbConnection() - Retorna connexió MySQLi amb charset utf8mb4
 * @functions sendJson($data, $statusCode) - Envia resposta JSON amb headers CORS
 * @functions getJsonInput() - Llegeix i decodifica input JSON del POST
 * @note Suport .env.php per variables d'entorn
 * @note Fallbacks: localhost, root, project_manager
 * @note DEBUG flag condicional per error_log
 */

// Carregar variables d'entorn si existeix .env.php
if (file_exists(__DIR__ . '/.env.php')) {
    require_once __DIR__ . '/.env.php';
}

// Configuració MySQL
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'project_manager');

// Configuració general
define('DEBUG', getenv('DEBUG') ?: false);

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
        if (DEBUG) {
            error_log("Database connection error: " . $e->getMessage());
        }
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
