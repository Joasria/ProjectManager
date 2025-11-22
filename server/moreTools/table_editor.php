<?php
/**
 * Editor de Taules de Base de Dades Avançat
 * @description Editor avançat d'estructures BD amb 7 accions: list (totes+predefinides), create (SQL custom/predefinit), describe (estructura+CREATE TABLE), count (registres), drop (confirm=yes obligatori), alter (modifica+mostra actualitzada), execute_sql (arbitrari, SELECT retorna dades). Taules predefinides: Etera (4 taules amb FULLTEXT/JSON/ENUM), CTPonts (reserves). PDO prepared statements. URL decode per SQL custom. Metadata completa: access_info, affected_rows, descriptions. Fallback config 'etera'.
 * @param string $action Acció a realitzar: list, create, describe, count, drop, alter, execute_sql.
 * @param string $config Nom de la configuració de BD a utilitzar (ex: 'fitxar', 'etera', 'ctponts').
 * @param string $table Nom de la taula sobre la qual actuar.
 * @param string $sql Comanda SQL personalitzada i codificada per URL (per a accions com create, alter, execute_sql).
 * @param string $confirm Valor 'yes' requerit per a accions destructives com 'drop'.
 * @usage table_editor.php?action=list&config=fitxar (llista totes les taules)
 * @usage table_editor.php?action=describe&config=fitxar&table=usuaris (mostra estructura)
 * @usage table_editor.php?action=count&config=fitxar&table=usuaris (compte registres)
 * @usage table_editor.php?action=drop&config=fitxar&table=test&confirm=yes (elimina taula)
 * @usage table_editor.php?action=execute_sql&config=fitxar&sql=SELECT%20*%20FROM%20usuaris (executa SQL)
 * @warning Eina potent que pot modificar/eliminar dades! Usar amb precaució en producció
 * @warning Les accions destructives (drop) requereixen confirm=yes
 * @category Database Management
 * @reusable true
 * @note Taules predefinides Etera: etera_literal, etera_context, etera_coneixements, etera_memoria
 * @note Taules predefinides CTPonts: lloguer_pistes
 * @note execute_sql amb SELECT retorna resultats en query_results
 * @note alter mostra estructura actualitzada després de modificar
 * @note describe inclou CREATE TABLE statement complet
 */

// Carregar configuració des de tools-config.json
function loadToolsConfig() {
    $config_file = __DIR__ . '/tools-config.json';
    if (file_exists($config_file)) {
        return json_decode(file_get_contents($config_file), true);
    }
    return null;
}

function getDatabaseConfig($config_name = 'etera') {
    // Configuracions per defecte
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
    
    // Carregar des del fitxer de configuració
    $tools_config = loadToolsConfig();
    
    if ($tools_config && isset($tools_config['database_configs'][$config_name])) {
        return $tools_config['database_configs'][$config_name];
    }
    
    return $default_configs[$config_name] ?? $default_configs['etera'];
}

// Definicions específiques per projecte
function getProjectTableDefinitions($config_name) {
    switch ($config_name) {
        case 'etera':
            return getEteraTableDefinitions();
        case 'ctponts':
            return getCTPontsTableDefinitions();
        default:
            return [];
    }
}

function getEteraTableDefinitions() {
    return [
        'etera_literal' => [
            'description' => 'Registre literal de totes les converses i cadenes de pensament',
            'sql' => "
                CREATE TABLE IF NOT EXISTS `etera_literal` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `usuari` VARCHAR(100) NOT NULL,
                    `tipus` ENUM('conversa', 'pensament', 'accio', 'tool') NOT NULL,
                    `contingut` LONGTEXT NOT NULL,
                    `metadata` JSON,
                    INDEX `idx_timestamp` (`timestamp`),
                    INDEX `idx_usuari` (`usuari`),
                    INDEX `idx_tipus` (`tipus`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            "
        ],
        'etera_context' => [
            'description' => 'Context conversacional optimitzat segons finestra disponible',
            'sql' => "
                CREATE TABLE IF NOT EXISTS `etera_context` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `conversa_id` VARCHAR(100) NOT NULL,
                    `posicio` INT NOT NULL,
                    `contingut` TEXT NOT NULL,
                    `tipus` ENUM('normal', 'conceptualitzat', 'conclusio') DEFAULT 'normal',
                    `estat` ENUM('actiu', 'arxivat', 'purgat') DEFAULT 'actiu',
                    `prioritat` TINYINT DEFAULT 5,
                    `timestamp_creacio` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `timestamp_modificacio` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX `idx_conversa` (`conversa_id`),
                    INDEX `idx_posicio` (`posicio`),
                    INDEX `idx_estat` (`estat`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            "
        ],
        'etera_coneixements' => [
            'description' => 'Coneixements universals extrets de converses',
            'sql' => "
                CREATE TABLE IF NOT EXISTS `etera_coneixements` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `tipus` ENUM('fet', 'dada_personal', 'procediment', 'regla') NOT NULL,
                    `clau` VARCHAR(255) NOT NULL,
                    `valor` TEXT NOT NULL,
                    `font` VARCHAR(255),
                    `timestamp_creacio` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `timestamp_actualitzacio` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    `paraules_clau` TEXT,
                    `descripcio` TEXT,
                    `fiabilitat` TINYINT DEFAULT 5,
                    INDEX `idx_clau` (`clau`),
                    INDEX `idx_tipus` (`tipus`),
                    INDEX `idx_fiabilitat` (`fiabilitat`),
                    FULLTEXT `idx_paraules_clau` (`paraules_clau`),
                    FULLTEXT `idx_descripcio` (`descripcio`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            "
        ],
        'etera_memoria' => [
            'description' => 'Memòria vivencial i contingut generat per Etera',
            'sql' => "
                CREATE TABLE IF NOT EXISTS `etera_memoria` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `projecte` VARCHAR(100) DEFAULT 'general',
                    `tipus_contingut` ENUM('document', 'concepte', 'decisio', 'resultat') NOT NULL,
                    `titol` VARCHAR(255) NOT NULL,
                    `descripcio` TEXT,
                    `ruta_arxiu` VARCHAR(500),
                    `contingut` LONGTEXT,
                    `paraules_clau` TEXT,
                    `timestamp_creacio` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `conversa_origen` VARCHAR(100),
                    `rellevancia` TINYINT DEFAULT 5,
                    INDEX `idx_projecte` (`projecte`),
                    INDEX `idx_tipus` (`tipus_contingut`),
                    INDEX `idx_rellevancia` (`rellevancia`),
                    FULLTEXT `idx_titol` (`titol`),
                    FULLTEXT `idx_descripcio` (`descripcio`),
                    FULLTEXT `idx_paraules_clau` (`paraules_clau`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            "
        ]
    ];
}

function getCTPontsTableDefinitions() {
    return [
        'lloguer_pistes' => [
            'description' => 'Gestió de reserves de pistes de tennis del Club Tennis Ponts',
            'sql' => "
                CREATE TABLE IF NOT EXISTS `lloguer_pistes` (
                    `id_reserva` INT(11) NOT NULL AUTO_INCREMENT,
                    `id_soci` INT(11) NOT NULL,
                    `pista` ENUM('1','2') NOT NULL DEFAULT '1',
                    `data_reserva` DATE NOT NULL,
                    `hora_inici` TIME NOT NULL,
                    `hora_fi` TIME NOT NULL,
                    `observacions` TEXT NULL,
                    `estat` ENUM('activa','cancelada','completada') NOT NULL DEFAULT 'activa',
                    `data_creacio` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `data_modificacio` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
                    
                    PRIMARY KEY (`id_reserva`),
                    INDEX `idx_soci` (`id_soci`),
                    INDEX `idx_data_pista` (`data_reserva`, `pista`),
                    INDEX `idx_estat` (`estat`),
                    INDEX `idx_data_hora` (`data_reserva`, `hora_inici`),
                    INDEX `idx_pista_estat` (`pista`, `estat`),
                    
                    UNIQUE KEY `uk_pista_datetime` (`pista`, `data_reserva`, `hora_inici`)
                    
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            "
        ]
    ];
}

// Paràmetres d'entrada
$action = $_GET['action'] ?? 'list';
$table = $_GET['table'] ?? '';
$config_name = $_GET['config'] ?? 'etera';
$custom_sql = $_GET['sql'] ?? '';

// Obtenir configuració de BD
$db_config = getDatabaseConfig($config_name);

// Informació d'accés
$access_info = [
    'user_param' => $_GET['user'] ?? '',
    'access_time' => date('Y-m-d H:i:s'),
    'method' => 'url_auth',
    'config_used' => $config_name,
    'database_host' => $db_config['host'],
    'database_name' => $db_config['database'],
    'action' => $action
];

try {
    $dsn = "mysql:host={$db_config['host']};port={$db_config['port']};dbname={$db_config['database']};charset={$db_config['charset']}";
    $pdo = new PDO($dsn, $db_config['username'], $db_config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $response = ['access' => $access_info];
    $table_definitions = getProjectTableDefinitions($config_name);
    
    switch ($action) {
        case 'list':
            // Llistar TOTES les taules de la BD
            $stmt = $pdo->query("SHOW TABLES");
            $all_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $response['all_tables'] = $all_tables;
            
            // Mostrar taules pre-definides per aquest projecte
            if (!empty($table_definitions)) {
                $response['predefined_tables'] = array_keys($table_definitions);
                $response['table_descriptions'] = array_map(function($def) {
                    return $def['description'];
                }, $table_definitions);
            }
            
            $response['total_tables'] = count($all_tables);
            break;
            
        case 'create':
            if (!$table) {
                throw new Exception("Cal especificar un nom de taula");
            }
            
            // Utilitzar SQL personalitzat o predefinit
            if ($custom_sql) {
                $sql = urldecode($custom_sql);
            } elseif (isset($table_definitions[$table])) {
                $sql = $table_definitions[$table]['sql'];
            } else {
                throw new Exception("Cal proporcionar SQL personalitzat o utilitzar una taula predefinida. Taules predefinides: " . implode(', ', array_keys($table_definitions)));
            }
            
            $pdo->exec($sql);
            
            $response['message'] = "Taula '$table' creada correctament";
            if (isset($table_definitions[$table])) {
                $response['table_description'] = $table_definitions[$table]['description'];
            }
            break;
            
        case 'describe':
            if (!$table) {
                throw new Exception("Cal especificar una taula");
            }
            
            $stmt = $pdo->query("DESCRIBE `$table`");
            $structure = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response['table_structure'] = $structure;
            
            // Obtenir informació addicional de la taula
            $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
            $create_info = $stmt->fetch(PDO::FETCH_ASSOC);
            $response['create_statement'] = $create_info['Create Table'] ?? '';
            
            if (isset($table_definitions[$table])) {
                $response['table_description'] = $table_definitions[$table]['description'];
            }
            break;
            
        case 'count':
            if (!$table) {
                throw new Exception("Cal especificar una taula");
            }
            
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM `$table`");
            $count = $stmt->fetch(PDO::FETCH_ASSOC);
            $response['table_count'] = $count['total'];
            $response['table_name'] = $table;
            break;
            
        case 'drop':
            if (!$table) {
                throw new Exception("Cal especificar una taula");
            }
            
            $confirm = $_GET['confirm'] ?? '';
            if ($confirm !== 'yes') {
                $response['warning'] = "Per eliminar la taula '$table', afegiu &confirm=yes a la URL";
                $response['message'] = "Operació cancel·lada per seguretat";
            } else {
                $pdo->exec("DROP TABLE IF EXISTS `$table`");
                $response['message'] = "Taula '$table' eliminada correctament";
            }
            break;
            
        case 'alter':
            if (!$table) {
                throw new Exception("Cal especificar una taula per ALTER TABLE");
            }
            if (!$custom_sql) {
                throw new Exception("Cal proporcionar SQL per ALTER TABLE");
            }
            
            $sql = urldecode($custom_sql);
            $response['sql_executed'] = $sql;
            
            // Executar ALTER TABLE
            $pdo->exec($sql);
            
            $response['message'] = "ALTER TABLE executat correctament";
            
            // Mostrar estructura actualitzada
            $stmt = $pdo->query("DESCRIBE `$table`");
            $structure = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response['updated_structure'] = $structure;
            $response['altered_table'] = $table;
            break;
            
        case 'execute_sql':
            if (!$custom_sql) {
                throw new Exception("Cal proporcionar SQL per executar");
            }
            
            $sql = urldecode($custom_sql);
            $response['sql_executed'] = $sql;
            
            // Executar la consulta
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            
            $response['message'] = "SQL executat correctament";
            $response['affected_rows'] = $stmt->rowCount();
            
            // Si retorna resultats (SELECT), mostrar-los
            if (stripos(trim($sql), 'SELECT') === 0) {
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $response['query_results'] = $results;
                $response['result_count'] = count($results);
            }
            break;
            
        default:
            throw new Exception("Acció no vàlida. Accions disponibles: list, create, describe, count, drop, alter, execute_sql");
    }
    
    header('Content-Type: application/json');
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => $e->getMessage(),
        'access' => $access_info
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>
