# 📋 PROJECT MANAGER - INSTRUCCIONS D'ÚS

## 🎯 CONCEPTE FONAMENTAL

Sistema de descomposició progressiva intel·ligent que converteix idees complexes en accions executables mitjançant una estructura jeràrquica amb workflow visual.

**Principi clau:** *"Puc generar aquest producte one-shot? Si NO → Què necessito?"*

---

## 🗃️ ESTRUCTURA DE DADES

### **Taules per projecte:**
```
project_001, project_002, project_003...
```

### **Camps de cada entrada:**

| Camp | Tipus | Descripció |
|------|-------|------------|
| `id` | INT AUTO_INCREMENT | Identificador únic (PRIMARY KEY) |
| `parent_id` | INT NULL | Referència al pare (NULL per root, FK CASCADE) |
| `local_path` | VARCHAR(100) | Ordre entre germans ("1", "2", "a", "b") |
| `entry_type` | ENUM | Tipus: `group`, `memo`, `check`, `link` |
| `title` | VARCHAR(255) | Títol (sempre obligatori) |
| `content` | TEXT NULL | Contingut llarg (per memo/codi/HTML) |
| `url` | VARCHAR(500) NULL | Enllaç extern (per link) |
| `is_completed` | BOOLEAN | Estat completat (per check) |
| `status_color` | ENUM | Color: `blanc`, `groc`, `gris`, `vermell`, `blau`, `taronja`, `verd` |
| `context_data` | JSON NULL | Metadades específiques |
| `created_at` | TIMESTAMP | Data creació |
| `updated_at` | TIMESTAMP | Data última modificació |

**IMPORTANT:**
- ❌ `full_path` NO existeix a la BD (deprecated)
- ✅ `full_path` es calcula dinàmicament a `api_get_tree.php`
- ✅ CASCADE automàtic: eliminar entrada elimina tots els fills

---

## 🔧 API ENDPOINTS (project_manager.php)

### **Base URL:**
```
https://www.contratemps.org/claudetools/project_manager.php
```

### **1. add_entry - Afegir entrada**

**URL:** `?action=add_entry&project_id=001`
**Mètode:** POST

**Body JSON:**
```json
{
  "parent_id": 5,
  "entry_type": "memo",
  "title": "Títol entrada",
  "content": "Contingut...",
  "status_color": "blanc"
}
```

**Response:**
```json
{
  "success": true,
  "entry_id": 12,
  "local_path": "2",
  "message": "Entrada creada correctament"
}
```

### **2. update_entry - Actualitzar**

**URL:** `?action=update_entry&project_id=001&entry_id=12`
**Mètode:** POST

**Body JSON:**
```json
{
  "title": "Nou títol",
  "status_color": "groc"
}
```

### **3. get_siblings - Llistar germans**

**URL:** `?action=get_siblings&project_id=001&parent_id=5`
**Mètode:** GET

### **4. delete_entry - Eliminar**

**URL:** `?action=delete_entry&project_id=001&entry_id=12`
**Mètode:** GET/POST

**Efecte:** Elimina entrada + tots els fills (CASCADE)

### **5. move_entry - Moure entrada**

**URL:** `?action=move_entry&project_id=001&entry_id=12`
**Mètode:** POST

**Body JSON:**
```json
{
  "new_parent_id": 8
}
```

### **6. search - Buscar**

**URL:** `?action=search&project_id=001&query=tasca`
**Mètode:** GET

### **7. change_status - Canviar color**

**URL:** `?action=change_status&project_id=001&entry_id=12`
**Mètode:** POST

**Body JSON:**
```json
{
  "status_color": "verd"
}
```

### **8. toggle_completed - Toggle check**

**URL:** `?action=toggle_completed&project_id=001&entry_id=12`
**Mètode:** GET/POST

### **9. get_working_items - Items en treball**

**URL:** `?action=get_working_items&project_id=001`
**Mètode:** GET

Retorna entrades amb `status_color='groc'`

---

## 📝 TIPUS D'ENTRADA

### **1. 📁 GROUP**
Contenidor jeràrquic per organitzar altres entrades

### **2. 📝 MEMO**  
Element amb contingut de text (preguntes, respostes, codi)

### **3. ☑️ CHECK**
Element verificable (tasques, opcions)

### **4. 🔗 LINK**
Referència a recurs extern

---

## 🎨 SISTEMA D'ESTATS

| Color | Significat |
|-------|------------|
| ⚪ blanc | Nou/sense processar |
| 🟡 groc | Treballant activament |
| 🔘 gris | Processat però inactiu |
| 🔴 vermell | Erroni/bloquejat |
| 🔵 blau | Usuari ha validat proposta |
| 🟠 taronja | Claude ha incorporat actualització |
| 🟢 verd | Completat/consensuat |

**Flux:** blanc → groc → blau/taronja → verd

---

## 📋 PATRONS D'ÚS

### **Qüestionari:**
```
📁 GRUP: "Anàlisi requisits"
├── 📝 MEMO: "Objectiu del projecte"
├── 📁 GRUP: "Tecnologia"
│   ├── 📝 MEMO: "Quina preferència?"
│   ├── ☑️ CHECK: "React"
│   └── ☑️ CHECK: "Vue"
└── 🔗 LINK: "Referències"
```

### **Projecte desenvolupament:**
```
📁 GRUP: "App gestió"
├── 📁 GRUP: "Backend"
│   ├── 📝 MEMO: "API endpoint" + codi
│   └── ☑️ CHECK: "Tests units"
└── 📁 GRUP: "Frontend"
    └── 📝 MEMO: "Component Login" + JSX
```

---

## ⚠️ BONES PRÀCTIQUES

### **PROHIBIT: Emojis en SQL**
❌ MAI utilitzar emojis en camps text via SQL
✅ Usar text pla descriptiu

### **Gestió local_path:**
✅ Deixar que l'eina gestioni automàticament
- No especificar = S'afegeix al final
- Especificar = Renumera germans si conflicte

### **Workflow recomanat:**
1. Obtenir arbre: `api_get_tree.php?project_id=001`
2. Crear entrades: `add_entry` (auto local_path)
3. Actualitzar status: `change_status`

---

## 🚀 ENDPOINTS COMPLETS

| Acció | URL Pattern |
|-------|-------------|
| add_entry | `?action=add_entry&project_id=001` |
| update_entry | `?action=update_entry&project_id=001&entry_id=X` |
| move_entry | `?action=move_entry&project_id=001&entry_id=X` |
| delete_entry | `?action=delete_entry&project_id=001&entry_id=X` |
| get_siblings | `?action=get_siblings&project_id=001&parent_id=X` |
| search | `?action=search&project_id=001&query=TEXT` |
| change_status | `?action=change_status&project_id=001&entry_id=X` |
| toggle_completed | `?action=toggle_completed&project_id=001&entry_id=X` |
| get_working_items | `?action=get_working_items&project_id=001` |

---

*Documentació Project Manager v2*
*Última actualització: 2025-10-24*
