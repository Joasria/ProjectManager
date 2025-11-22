# More Tools - Eines Addicionals Locals

Aquesta carpeta conté eines Python addicionals que no són específiques del Project Manager però que poden ser útils per a la gestió local de fitxers i continguts.

## Eines Disponibles

### gestio_contingut_arxius_per_linies.py
Gestió avançada del contingut d'arxius línia per línia:
- Llegir línies específiques
- Afegir/insertar línies
- Modificar línies existents
- Eliminar línies
- Buscar i reemplaçar text

**Modes:**
- `read` - Llegir línies específiques
- `add` - Afegir línia al final
- `insert` - Insertar línia en posició
- `modify` - Modificar línia existent
- `delete` - Eliminar línia
- `search` - Buscar text
- `replace` - Reemplaçar text

## Ús

Aquestes eines **NO són necessàries** per al funcionament bàsic del Project Manager, però poden ser útils per:

- Edició precisa de fitxers de configuració
- Modificació de scripts sense obrir editor
- Processament automatitzat de fitxers

## Integració MCP

Si configures correctament el MCP bridge, aquestes eines seran autodescobibles i disponibles a Claude Desktop.

**Exemple d'ús a Claude:**
```
Modifica la línia 5 del fitxer config.txt i canvia "DEBUG=false" per "DEBUG=true"
```

Claude utilitzarà automàticament `gestio_contingut_arxius_per_linies.py` per fer el canvi.

## Nota

Aquestes eines són **opcionals**. El Project Manager només necessita:
- `git_manager.py` (a la carpeta parent `tools/`)
- `gestio_arxius.py` (a la carpeta parent `tools/`)
- `db-insert-utf8.py` (a la carpeta parent `tools/`)
