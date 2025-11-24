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

## 🎨 SISTEMA DE COLORS

### **Significat de cada color:**

| Color | Emoji | Significat | Ús |
|-------|-------|------------|----|
| **BLANC** | ⚪ | Nou / Sense començar | Entrada nova, estructura generada inicial |
| **GROC** | 🟡 | En treball actiu | "Estem treballant aquí ara mateix" |
| **BLAU** | 🔵 | User ha modificat | "He canviat/afegit això, IA revisa-ho" |
| **TARONJA** | 🟠 | IA ha modificat | "He canviat/afegit això, User revisa-ho" |
| **VERD** | 🟢 | Aprovat / Completat | Consensuat per ambdós, no cal tocar més |
| **GRIS** | 🔘 | Pausat temporalment | Era GROC, però ara treballem en altra cosa |
| **VERMELL** | 🔴 | Problema / Bloqueig | Decisió pendent, error, atenció requerida |

### **Fluxos de treball:**

#### **Flux 1: IA treballa en un apartat**
```
BLANC (estructura inicial)
  ↓
GROC (IA comença a treballar aquí)
  ↓
[IA fa canvis] → TARONJA ("revisa això")
  ↓
[User revisa i aprova] → VERD (fet)
```

#### **Flux 2: User fa canvis**
```
BLANC/GROC
  ↓
[User edita/crea node] → BLAU ("revisa això")
  ↓
[IA revisa i aprova] → VERD (consensuat)
```

#### **Flux 3: Canviar de focus de treball**
```
GROC (treballant aquí)
  ↓
[Canviem a treballar en altre apartat]
  ↓
GRIS (aquest apartat queda pausat)

NOTA: VERD, VERMELL, BLAU, TARONJA NO canvien a GRIS
Només GROC → GRIS quan pausem el treball actiu
```

#### **Flux 4: Detectar problema**
```
Qualsevol color
  ↓
[Detectem error / bloqueig / decisió pendent]
  ↓
VERMELL (problema a resoldre)
  ↓
[Resolem el problema]
  ↓
GROC o VERD segons correspongui
```

### **Exemples pràctics:**

**Exemple 1: IA documenta requisits**
```
📋 Requisits (BLANC inicial)
  ↓ IA comença a documentar
📋 Requisits (GROC - treballant)
  ↓ IA afegeix contingut al memo
📋 Requisits (TARONJA - "revisa això")
  ↓ User llegeix i aprova
📋 Requisits (VERD - aprovat)
```

**Exemple 2: User defineix arquitectura**
```
🏗️ Arquitectura (BLANC)
  ↓ User crea memo amb proposta
🏗️ Arquitectura (BLAU - "revisa això")
  ↓ IA llegeix i valida
🏗️ Arquitectura (VERD - consensuat)
```

**Exemple 3: Treballar en múltiples mòduls**
```
Estat inicial:
├─ 📋 Documentació (BLANC)
├─ 🎯 Mòdul 1 (BLANC)
└─ 🎯 Mòdul 2 (BLANC)

Comença treball:
├─ 📋 Documentació (GROC) ← treballant aquí
├─ 🎯 Mòdul 1 (BLANC)
└─ 🎯 Mòdul 2 (BLANC)

Canvi de focus:
├─ 📋 Documentació (GRIS) ← pausat
├─ 🎯 Mòdul 1 (GROC) ← ara treballant aquí
└─ 🎯 Mòdul 2 (BLANC)

Documentació aprovada:
├─ 📋 Documentació (VERD) ← ja no és GRIS, és VERD
├─ 🎯 Mòdul 1 (GROC)
└─ 🎯 Mòdul 2 (BLANC)
```

**Exemple 4: Problema bloquejar**
```
🎯 Mòdul 1 (GROC - treballant)
  ↓ Descobrim que falta decidir algo crític
🎯 Mòdul 1 (VERMELL - bloqueig!)
  └─ ❓ Decisió: JWT vs Sessions (VERMELL)
  ↓ User decideix: JWT
❓ Decisió: JWT vs Sessions (BLAU - user ha decidit)
  ↓ IA valida decisió
❓ Decisió: JWT vs Sessions (VERD - decidit)
🎯 Mòdul 1 (GROC - podem continuar)
```

### **Regles del sistema:**

1. **BLANC** = Punt de partida, sense processar
2. **GROC** = "Estem treballant aquí ara"
3. **TARONJA** = "IA ha fet canvis, user revisa"
4. **BLAU** = "User ha fet canvis, IA revisa"
5. **VERD** = "Aprovat per ambdós, completat"
6. **GRIS** = "Era GROC, però hem pausat per treballar en altra cosa"
7. **VERMELL** = "STOP: problema/decisió pendent"

### **Transicions vàlides:**

```
BLANC → GROC      (començar a treballar)
BLANC → BLAU      (user crea/edita directament)

GROC → TARONJA    (IA fa canvis)
GROC → BLAU       (user fa canvis)
GROC → GRIS       (pausar treball)
GROC → VERMELL    (detectar problema)

TARONJA → VERD    (user aprova)
TARONJA → VERMELL (user detecta problema)

BLAU → VERD       (IA aprova)
BLAU → VERMELL    (IA detecta problema)

GRIS → GROC       (reprendre treball)

VERMELL → GROC    (problema resolt, reprendre)
VERMELL → VERD    (problema resolt, ja està fet)

VERD → BLAU       (user fa modificacions posteriors)
VERD → TARONJA    (IA fa modificacions posteriors)
```

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
