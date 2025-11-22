<?php
/**
 * Gestor de Migracions de Base de Dades
 * @description Sistema de migracions BD amb control de versions. Aplica fitxers .sql de migrations/ ordenadament amb nomenclatura 001_nom.sql. Usa transaccions completes: rollback automàtic si error en qualsevol migració. Tracking via taula schema_migrations (version, applied_at) auto-creada. Accions: status (mostra aplicades/pendents), migrate (executa pendents). PDO amb prepared statements. Skip SQL buit. Error detallat amb nom fitxer. Execució única garantida per migració.
 * @param string $action Acció a realitzar: migrate (aplica migracions pendents), status (mostra l'estat).
 * @param string $config Nom de la configuració de BD a utilitzar (ex: 'fitxar', 'etera', 'project_manager').
 * @usage migrate.php?action=status&config=fitxar (mostra estat migracions)
 * @usage migrate.php?action=migrate&config=fitxar (aplica migracions pendents)
 * @structure Les migracions han de seguir el format: 001_descripcio.sql, 002_altra_migracio.sql, etc.
 * @structure Cada migració és un fitxer SQL que s'executa una sola vegada i es registra a schema_migrations
 * @security Utilitza transaccions completes: BEGIN al començament, COMMIT si tot OK, ROLLBACK si error
 * @category Database Management
 * @reusable true
 * @note Directori migracions: migrations/ al mateix nivell que migrate.php
 * @note Auto-crea taula schema_migrations (version, applied_at) si no existeix
 * @note Ordenació alfabètica garantida amb SORT_STRING
 * @note Skip fitxers SQL buits sense error
 */

// ===== FUNCIONS DE CONFIGURACIÓ DE BD (COPIADES DE TABLE_EDITOR) =====
function loadToolsConfig() {
    $config_file = __DIR__ . '/tools-config.json';
    if (file_exists($config_file)) {
        return json_decode(file_get_contents($config_file), true);
    }
    return null;
}

function getDatabaseConfig($config_name) {
    $default_configs = [
        'etera' => [
            'host' => 'db5018192408.hosting-data.io',
            'port' => 3306,
            'username' => 'dbu2894409',
            'password' => 'Menua9Euros1981@men[951753]',
            'database' => 'dbs14420026',
            'charset' => 'utf8'
        ]
    ];
    
    $tools_config = loadToolsConfig();
    
    if ($tools_config && isset($tools_config['database_configs'][$config_name])) {
        return $tools_config['database_configs'][$config_name];
    }
    
    return $default_configs[$config_name] ?? null;
}

// ===== LÒGICA PRINCIPAL DE MIGRACIONS =====

function main() {
    $action = $_GET['action'] ?? 'status';
    $config_name = $_GET['config'] ?? null;

    if (!$config_name) {
        throw new Exception("Cal especificar una configuració de base de dades amb el paràmetre 'config'.");
    }

    $db_config = getDatabaseConfig($config_name);
    if (!$db_config) {
        throw new Exception("La configuració de BD '$config_name' no s'ha trobat.");
    }

    $dsn = "mysql:host={$db_config['host']};port={$db_config['port']};dbname={$db_config['database']};charset={$db_config['charset']}";
    $pdo = new PDO($dsn, $db_config['username'], $db_config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $migrations_dir = __DIR__ . '/migrations';
    $migrations_table = 'schema_migrations';

    ensure_migrations_table_exists($pdo, $migrations_table);

    $disk_migrations = get_migrations_from_disk($migrations_dir);
    $db_migrations = get_run_migrations($pdo, $migrations_table);
    $pending_migrations = array_diff($disk_migrations, $db_migrations);

    $response = [];

    switch ($action) {
        case 'status':
            $response['message'] = "Estat de les migracions per a la configuració '$config_name'.";
            $response['applied_migrations'] = $db_migrations;
            $response['pending_migrations'] = array_values($pending_migrations);
            break;

        case 'migrate':
            if (empty($pending_migrations)) {
                $response['message'] = 'La base de dades ja està actualitzada.';
                break;
            }
            
            $executed = [];
            $migration_file = null; // Inicialitzar per a l'abast del catch
            $pdo->beginTransaction();
            try {
                foreach ($pending_migrations as $migration_file) {
                    $sql = file_get_contents($migrations_dir . '/' . $migration_file);
                    if (empty(trim($sql))) continue;
                    $pdo->exec($sql);
                    $stmt = $pdo->prepare("INSERT INTO $migrations_table (version) VALUES (?)");
                    $stmt->execute([$migration_file]);
                    $executed[] = $migration_file;
                }
                $pdo->commit();
                $response['message'] = 'Migracions aplicades correctament.';
                $response['applied_now'] = $executed;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_message = $migration_file 
                    ? "Error aplicant la migració '$migration_file': " . $e->getMessage()
                    : "Error general durant la migració: " . $e->getMessage();
                throw new Exception($error_message);
            }
            break;

        default:
            throw new Exception("Acció no vàlida. Accions disponibles: migrate, status");
    }
    return $response;
}

function ensure_migrations_table_exists($pdo, $table_name) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `$table_name` (
        `version` VARCHAR(255) NOT NULL PRIMARY KEY,
        `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}

function get_migrations_from_disk($dir) {
    if (!is_dir($dir)) return [];
    $files = scandir($dir);
    $migrations = array_filter($files, function($file) {
        return pathinfo($file, PATHINFO_EXTENSION) === 'sql';
    });
    sort($migrations, SORT_STRING);
    return $migrations;
}

function get_run_migrations($pdo, $table_name) {
    $stmt = $pdo->query("SELECT version FROM `$table_name` ORDER BY version ASC");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Punt d'entrada de l'script
try {
    $response_data = main();
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success'] + $response_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>
