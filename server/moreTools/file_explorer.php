<?php
/**
 * Explorador de Fitxers del Servidor
 * @description Explorador recursiu de fitxers del servidor amb lectura de contingut. Mode dual: sense paràmetre 'file' llista estructura completa (JSON dirs/files), amb paràmetre mostra contingut text. Metadata: size, modified, path relativa, flag is_text. Seguretat: exclou .git/.svn/node_modules/.vscode/.idea, .DS_Store/Thumbs.db/.htaccess. Només 15 extensions text suportades: php, html, css, js, txt, json, xml, sql, md, py, java, cpp, c, h. Base: DOCUMENT_ROOT. Inclou access_info amb PHP_VERSION.
 * @param string $file Ruta del fitxer a visualitzar, relativa al directori arrel del web. Si no s'especifica, llista tota l'estructura de fitxers.
 * @usage file_explorer.php (llista estructura completa)
 * @usage file_explorer.php?file=claudetools/tools-config.json (mostra contingut d'arxiu)
 * @usage file_explorer.php?file=claudetools/llistat.php (llegeix codi PHP)
 * @security Exclou directoris: .git, .svn, node_modules, .vscode, .idea
 * @security Exclou fitxers: .DS_Store, Thumbs.db, .htaccess
 * @security Extensions text suportades: php, html, css, js, txt, json, xml, sql, md, py, java, cpp, c, h
 * @category File Management
 * @reusable true
 * @note Resposta JSON amb estructura: {access: {...}, file_data|file_structure: {...}}
 * @note Cada fitxer inclou: name, size, modified, path, is_text
 * @note Base path: DOCUMENT_ROOT del servidor
 */

// Configuració
$base_path = $_SERVER['DOCUMENT_ROOT'];
$excluded_dirs = ['.git', '.svn', 'node_modules', '.vscode', '.idea'];
$excluded_files = ['.DS_Store', 'Thumbs.db', '.htaccess'];
$text_extensions = ['php', 'html', 'css', 'js', 'txt', 'json', 'xml', 'sql', 'md', 'py', 'java', 'cpp', 'c', 'h'];

function isTextFile($file) {
    global $text_extensions;
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    return in_array($extension, $text_extensions);
}

function getFileStructure($dir, $relativePath = '') {
    global $excluded_dirs, $excluded_files;
    
    $structure = [];
    
    if (!is_dir($dir) || !is_readable($dir)) {
        return $structure;
    }
    
    $items = scandir($dir);
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        if (in_array($item, $excluded_dirs) || in_array($item, $excluded_files)) continue;
        
        $fullPath = $dir . DIRECTORY_SEPARATOR . $item;
        $relativeItemPath = $relativePath . '/' . $item;
        
        if (is_dir($fullPath)) {
            $structure['dirs'][$item] = getFileStructure($fullPath, $relativeItemPath);
        } else {
            $fileInfo = [
                'name' => $item,
                'size' => filesize($fullPath),
                'modified' => date('Y-m-d H:i:s', filemtime($fullPath)),
                'path' => ltrim($relativeItemPath, '/'),
                'is_text' => isTextFile($fullPath)
            ];
            $structure['files'][] = $fileInfo;
        }
    }
    
    return $structure;
}

function displayFileContent($filePath) {
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $filePath;
    
    if (!file_exists($fullPath) || !is_readable($fullPath)) {
        return ['error' => 'Arxiu no trobat o no accessible'];
    }
    
    if (!isTextFile($fullPath)) {
        return ['error' => 'Aquest tipus d\'arxiu no es pot mostrar com a text'];
    }
    
    $content = file_get_contents($fullPath);
    
    return [
        'file' => $filePath,
        'size' => filesize($fullPath),
        'modified' => date('Y-m-d H:i:s', filemtime($fullPath)),
        'content' => $content
    ];
}

// Informació d'accés
$access_info = [
    'user_param' => $_GET['user'] ?? null,
    'access_time' => date('Y-m-d H:i:s'),
    'method' => 'url_auth',
    'server_info' => [
        'php_version' => PHP_VERSION,
        'document_root' => $_SERVER['DOCUMENT_ROOT']
    ]
];

// Determinar què retornar
if (isset($_GET['file'])) {
    // Mostrar contingut d'un arxiu específic
    $file_data = displayFileContent($_GET['file']);
    $result = [
        'access' => $access_info,
        'file_data' => $file_data
    ];
} else {
    // Mostrar estructura d'arxius
    $file_structure = getFileStructure($base_path);
    $result = [
        'access' => $access_info,
        'file_structure' => $file_structure
    ];
}

// Retornar com a JSON
header('Content-Type: application/json');
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
