# Template Manager - Documentació

Script Python per gestionar plantilles de projecte del Project Manager.

## 🎯 Funcionalitats

- **Llistar** totes les plantilles disponibles
- **Obtenir** una plantilla específica
- **Cercar** plantilles per nom, descripció o tags
- **Info** - Mostrar README de plantilles

## 📦 Ubicació

```
/tools/template_manager.py
```

## 🚀 Ús

### Llistar plantilles

```bash
python template_manager.py list
```

**Sortida JSON:**
```json
{
  "success": true,
  "total": 2,
  "templates": [
    {
      "filename": "desenvolupament-programa.json",
      "name": "Desenvolupament de Programa",
      "version": "1.0",
      "description": "Estructura completa...",
      "author": "Joaquim Serra",
      "tags": ["development", "github"],
      "path": "..."
    }
  ]
}
```

**Format text:**
```bash
python template_manager.py list --format text
```

### Obtenir plantilla

```bash
# Per nom de fitxer
python template_manager.py get --template desenvolupament-programa

# Per nom de plantilla
python template_manager.py get --template "Desenvolupament de Programa"
```

**Retorna:** JSON complet de la plantilla

### Cercar plantilles

```bash
python template_manager.py search --query github
python template_manager.py search --query web
```

### Veure info

```bash
python template_manager.py info
```

Mostra el README.md de `/templates/`

## 🔧 Integració amb MCP

Per utilitzar des de Claude Desktop, cal afegir al `claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "template-manager": {
      "command": "python",
      "args": [
        "C:\\Users\\Joaquim\\projectes\\013-PM_CT_DT_def\\tools\\template_manager.py",
        "list"
      ]
    }
  }
}
```

## 📝 Exemples d'Ús

### Des de Python

```python
import subprocess
import json

# Llistar plantilles
result = subprocess.run(
    ["python", "template_manager.py", "list"],
    capture_output=True,
    text=True
)
templates = json.loads(result.stdout)

# Obtenir plantilla
result = subprocess.run(
    ["python", "template_manager.py", "get", "--template", "desenvolupament-programa"],
    capture_output=True,
    text=True
)
template = json.loads(result.stdout)
```

### Des de Claude (MCP)

```
User: Llista les plantilles disponibles

Claude: [crida template_manager.py list]
Tinc 2 plantilles disponibles:
1. Desenvolupament de Programa (v1.0) - ...
2. Exemple Simple (v1.0) - ...

User: Mostra'm la plantilla de desenvolupament

Claude: [crida template_manager.py get --template desenvolupament-programa]
Aquí tens l'estructura completa...
```

## 🛠️ API Interna

### `list_templates() -> List[Dict]`

Retorna llista amb metadata de totes les plantilles.

### `get_template(template_name: str) -> Optional[Dict]`

Obté plantilla pel nom de fitxer o nom de plantilla.

### `search_templates(query: str) -> List[Dict]`

Cerca plantilles que coincideixin amb la query.

## 🔮 Futures Millores

- [ ] Validació d'estructura de plantilles
- [ ] Importació automàtica al projecte
- [ ] Creació de plantilles des de projectes existents
- [ ] Editor interactiu de plantilles
- [ ] Marketplace de plantilles comunitàries

## 📧 Suport

Per problemes o suggeriments:
- GitHub Issues
- Email: joaquim@contratemps.org
