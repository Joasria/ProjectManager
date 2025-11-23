# Plantilles de Projecte

Aquesta carpeta conté plantilles reutilitzables per diferents tipus de projectes. Cada plantilla proporciona una estructura jeràrquica completa llesta per importar al Project Manager.

## 📁 Plantilles Disponibles

### 1. **desenvolupament-programa.json**
**Propòsit:** Gestionar el desenvolupament complet d'un programa/aplicació

**Característiques:**
- ✅ Integració completa amb GitHub (issues, PRs, branches, releases)
- ✅ Estructura modular per diferents components
- ✅ Gestió de documentació tècnica
- ✅ Testing i qualitat de codi
- ✅ Build, release i versionat
- ✅ Manteniment i roadmap

**Ideal per:** Projectes de software amb Git, equips de desenvolupament, projectes amb múltiples mòduls

**Inclou:**
- 📍 Ubicacions (local/remot/GitHub)
- 📖 Descripció i documentació completa
- 🎯 Mòduls amb objectius i tasks
- 🧪 Testing i qualitat
- 📦 Build i release
- 🔧 Manteniment

---

### 2. **exemple-simple.json**
**Propòsit:** Plantilla senzilla d'exemple

**Característiques:**
- ✅ Estructura bàsica
- ✅ Planificació i disseny
- ✅ Complex nodes (check, selector)

**Ideal per:** Aprendre el sistema, projectes petits, prototips ràpids

---

## 🚀 Com Utilitzar les Plantilles

### Opció 1: Importar amb Tool (FUTUR)
```python
# Des de Claude Desktop amb MCP
project_manager.import_template(
    template_file="desenvolupament-programa.json",
    project_id="002",
    customize={
        "program_name": "MeuPrograma",
        "github_url": "https://github.com/user/repo"
    }
)
```

### Opció 2: Importar amb API (FUTUR)
```bash
POST /project_manager.php
action=import_template
project_id=002
template=desenvolupament-programa
```

### Opció 3: Manual (ACTUAL)
1. Obrir el fitxer JSON de la plantilla
2. Revisar l'estructura
3. Crear les entrades manualment al Project Manager
4. Personalitzar segons les necessitats

---

## 📝 Estructura d'una Plantilla

```json
{
  "template_name": "Nom de la plantilla",
  "template_version": "1.0",
  "description": "Descripció breu",
  "author": "Autor",
  "created_at": "2025-01-22",
  "tags": ["tag1", "tag2"],
  
  "structure": [
    {
      "title": "Grup Principal",
      "type": "group",
      "status": "blanc",
      "children": [
        {
          "title": "Subentrada",
          "type": "memo",
          "status": "blanc",
          "content": "Contingut..."
        }
      ]
    }
  ],
  
  "metadata": {
    "total_groups": 5,
    "difficulty": "intermedi"
  },
  
  "usage_instructions": "Instruccions d'ús..."
}
```

---

## 🎨 Personalització

Quan utilitzis una plantilla, personalitza els següents camps:

### Plantilla "desenvolupament-programa"
- `[NOM DEL PROGRAMA]` → Nom real del teu programa
- `[URL_GITHUB]` → URL del repositori GitHub
- `[URL_DOCS]` → URL de la documentació
- `[URL_ISSUE]` → URLs d'issues específiques
- Paths locals → Rutes reals del teu sistema
- Stack tecnològic → Tecnologies que utilitzaràs
- Mòduls → Afegir/eliminar segons necessitats

### Camps amb placeholders
Busca i substitueix aquests patrons:
- `[NOM]` → Nom real
- `[URL_...]` → URLs reals
- `[X ms]` → Valors reals
- `[especificar]` → Detalls específics

---

## 🛠️ Crear Plantilles Pròpies

### 1. Estructura Bàsica
```json
{
  "template_name": "La Meva Plantilla",
  "template_version": "1.0",
  "description": "Descripció...",
  "structure": [ ... ]
}
```

### 2. Tipus d'Entrades Disponibles

#### Grups
```json
{
  "title": "Grup",
  "type": "group",
  "status": "blanc",
  "children": [ ... ]
}
```

#### Memos
```json
{
  "title": "Nota",
  "type": "memo",
  "status": "blanc",
  "content": "Text del memo..."
}
```

#### Checks
```json
{
  "title": "Tasca",
  "type": "check",
  "status": "blanc",
  "content": "Descripció de la tasca"
}
```

#### Links
```json
{
  "title": "Enllaç",
  "type": "link",
  "status": "blanc",
  "url": "https://example.com",
  "content": "Descripció"
}
```

#### Complex: Checkboxes
```json
{
  "title": "Checklist",
  "type": "complex:check",
  "status": "blanc",
  "children": [
    {"title": "Opció 1", "type": "option"},
    {"title": "Opció 2", "type": "option"}
  ]
}
```

#### Complex: Selector (Radio)
```json
{
  "title": "Selecciona una opció",
  "type": "complex:selector",
  "status": "blanc",
  "children": [
    {"title": "Opció A", "type": "option"},
    {"title": "Opció B", "type": "option"}
  ]
}
```

#### Complex: Form
```json
{
  "title": "Formulari",
  "type": "complex:form",
  "status": "blanc",
  "children": [
    {
      "title": "Camp",
      "type": "field",
      "context_data": {
        "field_type": "text",
        "placeholder": "...",
        "required": true
      }
    },
    {"title": "Separador", "type": "separator"}
  ]
}
```

### 3. Colors d'Estat
- `blanc` - Sense prioritat
- `groc` - En progrés
- `gris` - Pausat
- `vermell` - Urgent
- `blau` - Informació
- `taronja` - Pendent revisió
- `verd` - Completat

---

## 📦 Contribuir amb Plantilles

Si vols afegir una plantilla nova:

1. Crea el fitxer JSON a `/templates/`
2. Segueix l'estructura estàndard
3. Afegeix documentació a aquest README
4. Fes commit i push
5. Crea PR al repositori

**Bones pràctiques:**
- Noms descriptius i en minúscules amb guions
- Versió semàntica (1.0, 1.1, 2.0...)
- Metadata completa
- Usage instructions clares
- Exemples de personalització

---

## 🔮 Futures Implementacions

### Funcionalitat prevista:
- [ ] Tool MCP per importar plantilles automàticament
- [ ] API endpoint per importació
- [ ] Editor de plantilles al dashboard
- [ ] Marketplace de plantilles comunitàries
- [ ] Exportar projecte com a plantilla
- [ ] Variables personalitzables amb UI

---

## 📧 Contacte

Per suggeriments de plantilles o millores:
- Crear issue a GitHub
- Email: joaquim@contratemps.org

---

**Última actualització:** 2025-01-22
**Mantenidor:** Joaquim Serra
