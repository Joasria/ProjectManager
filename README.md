# Project Manager - Sistema Complet

Sistema de gestió de projectes jeràrquic amb integració Git i suport MCP per Claude Desktop.

## Què és aquest projecte?

**Project Manager** és un sistema complet que permet gestionar projectes de manera jeràrquica amb integració amb Claude Desktop via MCP (Model Context Protocol). Combina:

- **Backend PHP** per servidor web
- **API REST** completa
- **Dashboard web** per visualització
- **Eines Python** per gestió local i Git
- **Integració MCP** per Claude Desktop

## Estructura del Projecte

```
ProjectManager/
├── README.md                    # Aquest fitxer
├── INSTALL.md                   # Guia instal·lació completa
├── .gitignore
│
├── client/                      # Part client (interfície web)
│   └── dashboard.html          # Dashboard interactiu
│
├── server/                      # Fitxers PHP per pujar al servidor
│   ├── project_manager.php     # API principal CRUD
│   ├── pm_docs.php            # Endpoint documentació
│   ├── api_get_tree.php       # API arbre d'entrades
│   ├── pm_config.php          # Configuració BD específica PM
│   ├── config.php             # Configuració BD general
│   └── pmdocs/
│       └── PM_INSTRUCTIONS.md # Documentació completa
│
└── tools/                       # Eines locals (MCP)
    ├── git_manager.py          # Gestió Git integrada
    ├── gestio_arxius.py       # Gestió fitxers Windows
    └── db-insert-utf8.py      # Inserts BD amb UTF-8
```

## Instal·lació Ràpida

### 1. Clonar Repositori

```bash
git clone https://github.com/Joasria/ProjectManager.git
cd ProjectManager
```

### 2. Servidor Web

Pujar fitxers de `server/` al teu servidor web (Apache/Nginx amb PHP):

```
/htdocs/claudetools/
├── project_manager.php
├── pm_docs.php
├── api_get_tree.php
├── pm_config.php
├── config.php
└── pmdocs/
    └── PM_INSTRUCTIONS.md
```

Configurar base de dades a `pm_config.php`.

### 3. Claude Desktop (MCP)

Copiar scripts de `tools/` a la teva carpeta de scripts MCP i configurar `claude_desktop_config.json`.

**Veure [INSTALL.md](INSTALL.md) per instruccions detallades.**

## Característiques

### Gestió Jeràrquica
- ✅ **Groups** - Carpetes/contenidors
- ✅ **Memos** - Notes i apunts
- ✅ **Checks** - Tasques amb checkbox
- ✅ **Links** - Enllaços externs

### Sistema de Colors
- `blanc` - Sense prioritat
- `groc` - En progrés
- `gris` - Pausat
- `vermell` - Urgent
- `blau` - Informació
- `taronja` - Pendent revisió
- `verd` - Completat

### Integració Git
- Sincronització automàtica
- Control de versions per sessió
- Workflow professional: sync → pull → feina → commit → push

### Sessions de Treball
- Control de canvis entre Claude i Usuari
- Sistema de versions (v_user, v_system)
- Revisió de canvis pendents

## Workflow de Treball

### Iniciar Sessió
```
1. sync_check - Veure diferències local vs remot
2. pull - Actualitzar si cal
```

### Durant la Feina
```
- Treballar amb project_manager
- Fer status de tant en tant
- NO fer commit per cada petit canvi
```

### Checkpoint (funcionalitat completada)
```
commit amb missatge descriptiu
```

### Final de Sessió
```
1. status - Verificar canvis
2. commit - Si hi ha canvis pendents
3. push - Pujar tot al remot
```

## API Endpoints

### Project Manager
```
GET  /project_manager.php?action=get_entry&project_id=001&entry_id=123
POST /project_manager.php
     action=add_entry
     project_id=001
     title=Nova tasca
     entry_type=check
```

### Documentació
```
GET /pm_docs.php
GET /pm_docs.php?section=structure
```

### API Tree
```
GET /api_get_tree.php?project_id=001
GET /api_get_tree.php?project_id=001&format=tree
```

## Dependències

### Servidor
- PHP 7.4+
- MySQL/MariaDB
- Extensions: mysqli, json

### Local (MCP)
- Python 3.8+
- Git instal·lat
- Claude Desktop

## Documentació

- [Instal·lació completa](INSTALL.md)
- [Instruccions PM](server/pmdocs/PM_INSTRUCTIONS.md)

## Desenvolupament

Aquest projecte està en desenvolupament actiu. Per contribuir:

1. Fork del repositori
2. Crear branch per la teva funcionalitat
3. Fer commit dels canvis
4. Push al branch
5. Crear Pull Request

## Suport

Per problemes o preguntes, crear una issue al repositori de GitHub.

## Autor

**Joaquim Serra**
- Email: joaquim@contratemps.org
- GitHub: [@Joasria](https://github.com/Joasria)

## Llicència

© 2024 Joaquim Serra - Tots els drets reservats

---

**Nota:** Aquest és un projecte autocontingut. Tots els fitxers necessaris estan inclosos al repositori per facilitar la instal·lació en sistemes nous.
