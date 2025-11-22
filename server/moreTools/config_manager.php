<?php
/**
 * Gestor de Configuració de Claudetools
 * @description Gestor centralitzat de configuracions BD per claudetools. Llegeix/escriu tools-config.json amb backup automàtic (.bak). Permet llistar configuracions actuals i afegir-ne de noves amb validació de paràmetres. Crea automàticament project_mappings per cada configuració. Paràmetres opcionals: port (3306), charset (utf8mb4), description.
 * @param string $action Acció a realitzar: list, add_config.
 * @param string $config_name Per a 'add_config', el nom de la nova connexió (ex: 'nou_projecte').
 * @param string $host Per a 'add_config', el host de la BD.
 * @param string $username Per a 'add_config', el nom d'usuari de la BD.
 * @param string $password Per a 'add_config', la contrasenya de la BD.
 * @param string $database Per a 'add_config', el nom de la BD.
 * @param string $port Per a 'add_config', el port MySQL (opcional, per defecte: 3306).
 * @param string $charset Per a 'add_config', el charset de connexió (opcional, per defecte: utf8mb4).
 * @param string $description Per a 'add_config', descripció de la configuració (opcional).
 * @usage config_manager.php?action=list (mostra totes les configuracions)
 * @usage config_manager.php?action=add_config&config_name=nou_projecte&host=localhost&username=user&password=pass&database=mydb&description=Nova BD
 * @example URL completa: https://www.contratemps.org/claudetools/config_manager.php?action=list
 * @category System Management
 * @reusable false
 * @note Crea automàticament backup (.bak) abans de modificar tools-config.json
 * @note Afegeix automàticament project_mappings per cada nova configuració
 */

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'list';
$config_file = __DIR__ . '/tools-config.json';

function load_config($file) {
    if (!file_exists($file)) {
        throw new Exception("El fitxer de configuració no existeix.");
    }
    return json_decode(file_get_contents($file), true);
}

function save_config($file, $config_data) {
    // Fer una còpia de seguretat abans de sobreescriure
    copy($file, $file . '.bak');
    
    if (file_put_contents($file, json_encode($config_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        return true;
    }
    return false;
}

try {
    $config = load_config($config_file);
    $response = ['status' => 'success'];

    switch ($action) {
        case 'add_config':
            // Recollir paràmetres
            $config_name = $_GET['config_name'] ?? null;
            $host = $_GET['host'] ?? null;
            $port = $_GET['port'] ?? '3306';
            $username = $_GET['username'] ?? null;
            $password = $_GET['password'] ?? null;
            $database = $_GET['database'] ?? null;
            $charset = $_GET['charset'] ?? 'utf8mb4';
            $description = $_GET['description'] ?? '';

            if (!$config_name || !$host || !$username || !$password || !$database) {
                throw new Exception("Paràmetres insuficients. Es requereix: config_name, host, username, password, database.");
            }

            if (isset($config['database_configs'][$config_name])) {
                throw new Exception("La configuració '$config_name' ja existeix.");
            }

            // Crear la nova configuració
            $new_config = [
                'host' => $host,
                'port' => (int)$port,
                'username' => $username,
                'password' => $password,
                'database' => $database,
                'charset' => $charset,
                'description' => $description
            ];

            // Afegir la nova configuració i el mapeig
            $config['database_configs'][$config_name] = $new_config;
            $config['project_mappings'][$config_name] = $config_name;

            // Guardar els canvis
            if (save_config($config_file, $config)) {
                $response['message'] = "Configuració '$config_name' afegida correctament.";
                $response['new_config'] = $new_config;
                $response['backup_created'] = $config_file . '.bak';
            } else {
                throw new Exception("No s'ha pogut guardar el fitxer de configuració.");
            }
            break;

        case 'list':
        default:
            $response['message'] = "Configuració actual.";
            $response['config_data'] = $config;
            break;
    }

    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>
