<?php
/**
 * PM Docs - Documentació Project Manager
 * @description Claudetool per accedir a la documentació completa del Project Manager. Retorna instruccions d'ús, exemples, patrons comuns i bones pràctiques per treballar amb el sistema jeràrquic d'entrades.
 * @param string $section Secció específica a consultar (opcional): structure, types, colors, api, patterns, workflow, practices
 * @usage pm_docs.php (retorna documentació completa)
 * @usage pm_docs.php?section=api (retorna només endpoints API)
 * @usage pm_docs.php?section=types (retorna només tipus d'entrada)
 * @category Documentation
 * @reusable true
 * @note Consulta aquesta eina abans de treballar amb project_manager.php
 * @note Conté exemples pràctics i patrons d'ús recomanats
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$section = $_GET['section'] ?? 'full';

$docsFile = __DIR__ . '/pmdocs/PM_INSTRUCTIONS.md';

if (!file_exists($docsFile)) {
    http_response_code(404);
    echo json_encode([
        'error' => 'Documentació no trobada',
        'path' => $docsFile
    ]);
    exit;
}

$content = file_get_contents($docsFile);

// Si es demana secció específica, extreure-la
if ($section !== 'full') {
    $content = extractSection($content, $section);
    if (!$content) {
        http_response_code(404);
        echo json_encode([
            'error' => 'Secció no trobada',
            'available_sections' => [
                'full' => 'Documentació completa',
                'structure' => 'Estructura de dades',
                'types' => 'Tipus d\'entrada (group, memo, check, link)',
                'colors' => 'Sistema d\'estats (colors)',
                'api' => 'Endpoints API',
                'patterns' => 'Patrons d\'ús comuns',
                'workflow' => 'Workflow típic amb Claude',
                'practices' => 'Bones pràctiques'
            ]
        ]);
        exit;
    }
}

echo json_encode([
    'success' => true,
    'section' => $section,
    'content' => $content,
    'note' => 'Documentació Project Manager v2',
    'available_sections' => [
        'full', 'structure', 'types', 'colors', 'api', 'patterns', 'workflow', 'practices'
    ]
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

/**
 * Extreu secció específica del markdown
 */
function extractSection($content, $section) {
    $sectionMap = [
        'structure' => '## 🗃️ ESTRUCTURA DE DADES',
        'types' => '## 📝 TIPUS D\'ENTRADA',
        'colors' => '## 🎨 SISTEMA D\'ESTATS (COLORS)',
        'api' => '## 🔧 API ENDPOINTS (project_manager.php)',
        'patterns' => '## 📋 PATRONS D\'ÚS COMUNS',
        'workflow' => '## 🎯 WORKFLOW TÍPIC AMB CLAUDE',
        'practices' => '## ⚠️ BONES PRÀCTIQUES'
    ];
    
    if (!isset($sectionMap[$section])) {
        return null;
    }
    
    $sectionTitle = $sectionMap[$section];
    $startPos = strpos($content, $sectionTitle);
    
    if ($startPos === false) {
        return null;
    }
    
    // Buscar següent secció de nivell 2 (##)
    $nextSectionPos = strpos($content, "\n## ", $startPos + 1);
    
    if ($nextSectionPos === false) {
        return substr($content, $startPos);
    }
    
    return substr($content, $startPos, $nextSectionPos - $startPos);
}
