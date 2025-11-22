<?php
/**
 * Gestor de Llistes TODO amb Estructura Jeràrquica
 * @description Sistema complet de gestió de tasques TODO amb jerarquia il·limitada (1, 1-1, 1-1-1...). Emmagatzema llistes en JSON amb timestamps automàtics. Funcions: add (auto-numeració amb parent), check/uncheck, modify, delete recursiu, show (JSON/text amb emojis), stats (totals/%), list_all, help. Suporta CLI i web, sanititza noms, inclou CORS. Format dual: API JSON o visualització amb emojis i indentació.
 * @param string $list Nom de la llista TODO (obligatori per la majoria d'accions)
 * @param string $action Acció a realitzar: show (mostra llista), add (afegeix tasca), check (marca com feta), uncheck (desmarca), delete (elimina), modify (modifica text), list_all (llista totes les llistes), stats (estadístiques), help (ajuda)
 * @param string $task Número de tasca en format jeràrquic: "1", "1-1", "1-1-2", etc.
 * @param string $text Text de la tasca (necessari per add i modify)
 * @param string $parent Tasca pare per afegir subtasca amb auto-numeració (opcional, alternativa a especificar task complet)
 * @param string $format Format de sortida per show (opcional): 'json' (per defecte) o 'text' (amb emojis i indentació)
 * @usage todo_manager.php?list=projecte&action=show
 * @usage todo_manager.php?list=projecte&action=add&text=Crear base de dades
 * @usage todo_manager.php?list=projecte&action=add&parent=1&text=Definir esquema
 * @usage todo_manager.php?list=projecte&action=add&task=1-1&text=Taula usuaris
 * @usage todo_manager.php?list=projecte&action=check&task=1-1
 * @usage todo_manager.php?list=projecte&action=modify&task=1&text=Crear BD completa
 * @usage todo_manager.php?list=projecte&action=delete&task=1-2
 * @usage todo_manager.php?action=list_all
 * @usage todo_manager.php?list=projecte&action=stats
 * @usage todo_manager.php?action=help
 * @category General
 * @reusable true
 * @note Emmagatzema llistes a data/todos/ en format JSON
 * @note Timestamps automàtics (created, modified) per llista i tasca
 * @note Sanititza noms de llista per seguretat (alfanumèric + guions)
 * @note Delete és recursiu: elimina tasca + totes les subtasques
 */

class TodoManager {
    
    private $dataDir;
    private $listName;
    private $listPath;
    private $data;
    
    public function __construct($listName = null) {
        $this->dataDir = __DIR__ . '/data/todos';
        
        // Crear directori si no existeix
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }
        
        if ($listName) {
            $this->listName = $this->sanitizeListName($listName);
            $this->listPath = $this->dataDir . '/' . $this->listName . '.json';
            $this->loadList();
        }
    }
    
    /**
     * Sanititza el nom de la llista per evitar problemes de seguretat
     */
    private function sanitizeListName($name) {
        // Només alfanumèrics, guions i guions baixos
        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
    }
    
    /**
     * Carrega la llista des del fitxer JSON
     */
    private function loadList() {
        if (file_exists($this->listPath)) {
            $content = file_get_contents($this->listPath);
            $this->data = json_decode($content, true);
        } else {
            // Crear nova llista
            $this->data = [
                'name' => $this->listName,
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
                'tasks' => []
            ];
            $this->saveList();
        }
    }
    
    /**
     * Guarda la llista al fitxer JSON
     */
    private function saveList() {
        $this->data['modified'] = date('Y-m-d H:i:s');
        file_put_contents($this->listPath, json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    /**
     * Llista totes les llistes disponibles
     */
    public function listAllLists() {
        $lists = [];
        $files = glob($this->dataDir . '/*.json');
        
        foreach ($files as $file) {
            $content = json_decode(file_get_contents($file), true);
            $stats = $this->calculateStatsFromData($content);
            
            $lists[] = [
                'name' => $content['name'],
                'created' => $content['created'],
                'modified' => $content['modified'],
                'total_tasks' => $stats['total'],
                'completed' => $stats['completed'],
                'pending' => $stats['pending'],
                'completion_rate' => $stats['completion_rate']
            ];
        }
        
        return $lists;
    }
    
    /**
     * Calcula estadístiques d'una llista
     */
    private function calculateStatsFromData($data) {
        $total = 0;
        $completed = 0;
        
        $countTasks = function($tasks) use (&$countTasks, &$total, &$completed) {
            foreach ($tasks as $task) {
                $total++;
                if ($task['checked']) {
                    $completed++;
                }
                if (!empty($task['subtasks'])) {
                    $countTasks($task['subtasks']);
                }
            }
        };
        
        $countTasks($data['tasks']);
        
        $pending = $total - $completed;
        $completion_rate = $total > 0 ? round(($completed / $total) * 100, 1) : 0;
        
        return [
            'total' => $total,
            'completed' => $completed,
            'pending' => $pending,
            'completion_rate' => $completion_rate . '%'
        ];
    }
    
    /**
     * Obté estadístiques de la llista actual
     */
    public function getStats() {
        return array_merge(
            [
                'list' => $this->listName,
                'created' => $this->data['created'],
                'modified' => $this->data['modified']
            ],
            $this->calculateStatsFromData($this->data)
        );
    }
    
    /**
     * Afegeix una nova tasca
     */
    public function addTask($text, $taskNumber = null, $parent = null) {
        if (empty($text)) {
            throw new Exception("El text de la tasca és obligatori");
        }
        
        // Si es proporciona parent, generar automàticament el taskNumber
        if ($parent && !$taskNumber) {
            $taskNumber = $this->generateNextSubtaskNumber($parent);
        }
        
        // Si no hi ha taskNumber, és una tasca de primer nivell
        if (!$taskNumber) {
            $taskNumber = $this->generateNextMainTaskNumber();
        }
        
        // Validar format del número de tasca
        if (!preg_match('/^[0-9]+(-[0-9]+)*$/', $taskNumber)) {
            throw new Exception("Format de número de tasca invàlid: $taskNumber");
        }
        
        // Crear la nova tasca
        $newTask = [
            'text' => $text,
            'checked' => false,
            'created' => date('Y-m-d H:i:s'),
            'modified' => date('Y-m-d H:i:s'),
            'subtasks' => []
        ];
        
        // Afegir la tasca a l'estructura
        $this->insertTask($taskNumber, $newTask);
        $this->saveList();
        
        return [
            'success' => true,
            'task_number' => $taskNumber,
            'message' => "✅ Tasca '$taskNumber' afegida: $text"
        ];
    }
    
    /**
     * Genera el següent número de tasca principal
     */
    private function generateNextMainTaskNumber() {
        $maxNumber = 0;
        foreach (array_keys($this->data['tasks']) as $key) {
            $number = intval($key);
            if ($number > $maxNumber) {
                $maxNumber = $number;
            }
        }
        return (string)($maxNumber + 1);
    }
    
    /**
     * Genera el següent número de subtasca
     */
    private function generateNextSubtaskNumber($parent) {
        $parts = explode('-', $parent);
        $parentTask = $this->findTask($parent);
        
        if (!$parentTask) {
            throw new Exception("La tasca pare '$parent' no existeix");
        }
        
        $maxNumber = 0;
        foreach (array_keys($parentTask['subtasks']) as $key) {
            $parts = explode('-', $key);
            $number = intval(end($parts));
            if ($number > $maxNumber) {
                $maxNumber = $number;
            }
        }
        
        return $parent . '-' . ($maxNumber + 1);
    }
    
    /**
     * Insereix una tasca a l'estructura
     */
    private function insertTask($taskNumber, $task) {
        $parts = explode('-', $taskNumber);
        
        if (count($parts) === 1) {
            // Tasca de primer nivell
            $this->data['tasks'][$taskNumber] = $task;
        } else {
            // Subtasca - navegar fins al pare
            $parent = implode('-', array_slice($parts, 0, -1));
            $parentTask = &$this->findTaskReference($parent);
            
            if (!$parentTask) {
                throw new Exception("La tasca pare '$parent' no existeix");
            }
            
            $parentTask['subtasks'][$taskNumber] = $task;
        }
    }
    
    /**
     * Troba una tasca i retorna la seva referència (VERSIÓ CORREGIDA)
     */
    private function &findTaskReference($taskNumber) {
        $parts = explode('-', $taskNumber);
        
        // Començar des de l'arrel
        if (count($parts) === 1) {
            // Tasca de primer nivell
            if (isset($this->data['tasks'][$taskNumber])) {
                return $this->data['tasks'][$taskNumber];
            }
        } else {
            // Subtasca: navegar pas a pas
            $current = &$this->data['tasks'];
            
            // Trobar la tasca pare de primer nivell
            $mainTaskNumber = $parts[0];
            if (!isset($current[$mainTaskNumber])) {
                $null = null;
                return $null;
            }
            
            $current = &$current[$mainTaskNumber];
            
            // Navegar per les subtasques
            for ($i = 1; $i < count($parts); $i++) {
                $partialKey = implode('-', array_slice($parts, 0, $i + 1));
                
                if (!isset($current['subtasks'][$partialKey])) {
                    $null = null;
                    return $null;
                }
                
                $current = &$current['subtasks'][$partialKey];
            }
            
            return $current;
        }
        
        $null = null;
        return $null;
    }
    
    /**
     * Troba una tasca i retorna les seves dades (VERSIÓ CORREGIDA)
     */
    private function findTask($taskNumber) {
        $parts = explode('-', $taskNumber);
        
        if (count($parts) === 1) {
            // Tasca de primer nivell
            return $this->data['tasks'][$taskNumber] ?? null;
        } else {
            // Subtasca: navegar pas a pas
            $current = $this->data['tasks'];
            
            // Trobar la tasca pare de primer nivell
            $mainTaskNumber = $parts[0];
            if (!isset($current[$mainTaskNumber])) {
                return null;
            }
            
            $current = $current[$mainTaskNumber];
            
            // Navegar per les subtasques
            for ($i = 1; $i < count($parts); $i++) {
                $partialKey = implode('-', array_slice($parts, 0, $i + 1));
                
                if (!isset($current['subtasks'][$partialKey])) {
                    return null;
                }
                
                $current = $current['subtasks'][$partialKey];
            }
            
            return $current;
        }
    }
    
    /**
     * Marca o desmarca una tasca com a completada
     */
    public function toggleTask($taskNumber, $checked = true) {
        $task = &$this->findTaskReference($taskNumber);
        
        if (!$task) {
            throw new Exception("Tasca '$taskNumber' no trobada");
        }
        
        $task['checked'] = $checked;
        $task['modified'] = date('Y-m-d H:i:s');
        
        $this->saveList();
        
        $status = $checked ? '✅ completada' : '⭕ pendent';
        return [
            'success' => true,
            'message' => "Tasca '$taskNumber' marcada com $status"
        ];
    }
    
    /**
     * Modifica el text d'una tasca
     */
    public function modifyTask($taskNumber, $newText) {
        if (empty($newText)) {
            throw new Exception("El nou text és obligatori");
        }
        
        $task = &$this->findTaskReference($taskNumber);
        
        if (!$task) {
            throw new Exception("Tasca '$taskNumber' no trobada");
        }
        
        $oldText = $task['text'];
        $task['text'] = $newText;
        $task['modified'] = date('Y-m-d H:i:s');
        
        $this->saveList();
        
        return [
            'success' => true,
            'message' => "✏️ Tasca '$taskNumber' modificada",
            'old_text' => $oldText,
            'new_text' => $newText
        ];
    }
    
    /**
     * Elimina una tasca i totes les seves subtasques
     */
    public function deleteTask($taskNumber) {
        $parts = explode('-', $taskNumber);
        
        if (count($parts) === 1) {
            // Tasca de primer nivell
            if (!isset($this->data['tasks'][$taskNumber])) {
                throw new Exception("Tasca '$taskNumber' no trobada");
            }
            
            $task = $this->data['tasks'][$taskNumber];
            unset($this->data['tasks'][$taskNumber]);
        } else {
            // Subtasca
            $parent = implode('-', array_slice($parts, 0, -1));
            $parentTask = &$this->findTaskReference($parent);
            
            if (!$parentTask || !isset($parentTask['subtasks'][$taskNumber])) {
                throw new Exception("Tasca '$taskNumber' no trobada");
            }
            
            $task = $parentTask['subtasks'][$taskNumber];
            unset($parentTask['subtasks'][$taskNumber]);
        }
        
        $this->saveList();
        
        // Comptar subtasques eliminades
        $deletedCount = 1 + $this->countSubtasks($task);
        
        return [
            'success' => true,
            'message' => "🗑️ Tasca '$taskNumber' eliminada (+ $deletedCount subtasques)",
            'deleted_count' => $deletedCount
        ];
    }
    
    /**
     * Compta el total de subtasques recursivament
     */
    private function countSubtasks($task) {
        if (empty($task['subtasks'])) {
            return 0;
        }
        
        $count = count($task['subtasks']);
        foreach ($task['subtasks'] as $subtask) {
            $count += $this->countSubtasks($subtask);
        }
        
        return $count;
    }
    
    /**
     * Mostra la llista completa amb format
     */
    public function showList($format = 'text') {
        if (empty($this->data['tasks'])) {
            return [
                'list' => $this->listName,
                'message' => '📝 La llista està buida',
                'tasks' => []
            ];
        }
        
        if ($format === 'json') {
            return $this->data;
        }
        
        // Format text amb indentació
        $output = [];
        $output[] = "📋 LLISTA: " . $this->listName;
        $output[] = "📅 Creada: " . $this->data['created'];
        $output[] = "🔄 Modificada: " . $this->data['modified'];
        $output[] = "";
        
        $stats = $this->getStats();
        $output[] = "📊 ESTADÍSTIQUES:";
        $output[] = "   Total: {$stats['total']} | ✅ {$stats['completed']} | ⭕ {$stats['pending']} | 📈 {$stats['completion_rate']}";
        $output[] = "";
        $output[] = "📝 TASQUES:";
        $output[] = str_repeat('─', 50);
        
        $this->renderTasks($this->data['tasks'], $output, 0);
        
        return [
            'list' => $this->listName,
            'output' => implode("\n", $output),
            'stats' => $stats
        ];
    }
    
    /**
     * Renderitza les tasques recursivament
     */
    private function renderTasks($tasks, &$output, $level = 0) {
        foreach ($tasks as $number => $task) {
            $indent = str_repeat('  ', $level);
            $checkbox = $task['checked'] ? '✅' : '⭕';
            $strike = $task['checked'] ? '~~' : '';
            
            $output[] = $indent . "$checkbox $number. {$strike}{$task['text']}{$strike}";
            
            if (!empty($task['subtasks'])) {
                $this->renderTasks($task['subtasks'], $output, $level + 1);
            }
        }
    }
    
    /**
     * Output amb callback o echo
     */
    private function output($data, $asJson = true) {
        if ($asJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            if (is_array($data) && isset($data['output'])) {
                echo $data['output'];
            } else {
                print_r($data);
            }
        }
    }
}

// =====================================================
// ÚS DE L'EINA (execució directa)
// =====================================================

if (basename($_SERVER['PHP_SELF']) === 'todo_manager.php') {
    
    try {
        $action = $_GET['action'] ?? 'help';
        $list = $_GET['list'] ?? null;
        $task = $_GET['task'] ?? null;
        $text = $_GET['text'] ?? null;
        $parent = $_GET['parent'] ?? null;
        
        // Detectar si és CLI o web
        $isCli = php_sapi_name() === 'cli';
        $asJson = !$isCli && (!isset($_GET['format']) || $_GET['format'] === 'json');
        
        // Accions sense llista específica
        if ($action === 'list_all') {
            $manager = new TodoManager();
            $lists = $manager->listAllLists();
            
            if ($asJson) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => true,
                    'lists' => $lists,
                    'total' => count($lists)
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            } else {
                echo "📚 LLISTES TODO DISPONIBLES:\n\n";
                foreach ($lists as $l) {
                    echo "  📋 {$l['name']}\n";
                    echo "     Total: {$l['total_tasks']} | ✅ {$l['completed']} | ⭕ {$l['pending']} | 📈 {$l['completion_rate']}\n";
                    echo "     Modificada: {$l['modified']}\n\n";
                }
            }
            exit;
        }
        
        if ($action === 'help' || !$list) {
            $help = [
                'tool' => 'todo_manager.php',
                'description' => 'Gestor de llistes TODO amb suport jeràrquic',
                'actions' => [
                    'list_all' => 'Llista totes les llistes disponibles',
                    'show' => 'Mostra la llista completa',
                    'add' => 'Afegeix una nova tasca',
                    'check' => 'Marca tasca com completada',
                    'uncheck' => 'Desmarca tasca',
                    'modify' => 'Modifica el text d\'una tasca',
                    'delete' => 'Elimina una tasca i subtasques',
                    'stats' => 'Mostra estadístiques de la llista'
                ],
                'examples' => [
                    '?action=list_all',
                    '?list=projecte&action=show',
                    '?list=projecte&action=add&text=Crear base dades',
                    '?list=projecte&action=add&parent=1&text=Definir esquema',
                    '?list=projecte&action=check&task=1-1',
                    '?list=projecte&action=modify&task=1&text=Nou text',
                    '?list=projecte&action=delete&task=1-2',
                    '?list=projecte&action=stats'
                ]
            ];
            
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($help, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // Accions amb llista específica
        $manager = new TodoManager($list);
        
        switch ($action) {
            case 'show':
                $result = $manager->showList($asJson ? 'json' : 'text');
                if ($asJson) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                } else {
                    echo $result['output'];
                }
                break;
                
            case 'add':
                $result = $manager->addTask($text, $task, $parent);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                break;
                
            case 'check':
                $result = $manager->toggleTask($task, true);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                break;
                
            case 'uncheck':
                $result = $manager->toggleTask($task, false);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                break;
                
            case 'modify':
                $result = $manager->modifyTask($task, $text);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                break;
                
            case 'delete':
                $result = $manager->deleteTask($task);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                break;
                
            case 'stats':
                $result = $manager->getStats();
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                break;
                
            default:
                throw new Exception("Acció desconeguda: $action");
        }
        
    } catch (Exception $e) {
        $error = [
            'success' => false,
            'error' => $e->getMessage()
        ];
        
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode($error, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
?>