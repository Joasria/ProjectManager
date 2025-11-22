# More Tools - Eines Addicionals del Servidor

Aquesta carpeta conté eines PHP addicionals que no són específiques del Project Manager però que poden ser útils per a la gestió general del servidor i base de dades.

## Eines Disponibles

### config_manager.php
Gestor centralitzat de configuracions de base de dades.
- Llegeix/escriu `tools-config.json`
- Permet llistar configuracions actuals
- Afegir noves configuracions amb validació

### file_explorer.php
Explorador recursiu de fitxers amb múltiples modes:
- `TREE` - Estructura lleugera
- `FULL` - Estructura completa recursiva
- `INFO` - Metadata + contingut fitxer
- `SEARCH` - Buscar per nom

### file_manager.php
Sistema CRUD complet + Mini-FTP per gestió d'arxius de projectes:
- Upload (3 modes: POST JSON, GET local_path, GET file_url)
- List, download, delete, info
- Auto-crea taules amb FK CASCADE

### folder_manager.php
Gestor d'estructures de carpetes:
- POST JSON o GET json_file
- Crea directoris recursivament
- Genera README.md automàtic
- Verifica existència

### table_editor.php
Editor avançat d'estructures BD amb 7 accions:
- list, create, describe, count
- drop (amb confirm), alter, execute_sql
- Taules predefinides
- PDO prepared statements

### migrate.php
Sistema de migracions BD amb control de versions:
- Aplica fitxers .sql de migrations/
- Tracking via taula schema_migrations
- Transaccions completes amb rollback automàtic

### todo_manager.php
Gestor de llistes TODO amb estructura jeràrquica:
- Jerarquia il·limitada (1, 1-1, 1-1-1...)
- Emmagatzema en JSON
- Funcions: add, check/uncheck, modify, delete, show, stats

## Ús

Aquestes eines **NO són necessàries** per al funcionament bàsic del Project Manager, però poden ser útils per:

- Gestió de base de dades
- Explorració de fitxers del servidor
- Migracions SQL
- Gestió de tasques (TODO)

## Instal·lació Opcional

Si vols utilitzar aquestes eines, puja-les al servidor juntament amb els fitxers principals:

```
/htdocs/claudetools/moreTools/
├── config_manager.php
├── file_explorer.php
├── file_manager.php
├── folder_manager.php
├── table_editor.php
├── migrate.php
└── todo_manager.php
```

**Nota:** Aquestes eines comparteixen la configuració de BD amb el Project Manager (`config.php` i `pm_config.php`).
