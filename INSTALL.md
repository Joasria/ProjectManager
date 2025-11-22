# Guia d'Instal·lació - Project Manager

Instruccions pas a pas per instal·lar el Project Manager en un sistema nou.

## Taula de Continguts

1. [Prerequisits](#prerequisits)
2. [Instal·lació Servidor](#instalacio-servidor)
3. [Instal·lació Client MCP](#instalacio-client-mcp)
4. [Configuració Git](#configuracio-git)
5. [Verificació](#verificacio)
6. [Troubleshooting](#troubleshooting)

---

## Prerequisits

### Servidor Web
- [ ] Apache o Nginx
- [ ] PHP 7.4 o superior
- [ ] MySQL o MariaDB 5.7+
- [ ] Extensions PHP: mysqli, json
- [ ] Accés FTP/SFTP al servidor

### Sistema Local
- [ ] Python 3.8 o superior
- [ ] Git instal·lat i accessible via PATH
- [ ] Claude Desktop instal·lat
- [ ] Editor de text (VSCode, Notepad++, etc.)

---

## Instal·lació Servidor

### Pas 1: Preparar Base de Dades

#### 1.1 Crear Base de Dades

Connectar a MySQL i executar:

```sql
CREATE DATABASE project_manager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### 1.2 Crear Usuari

```sql
CREATE USER 'pm_user'@'localhost' IDENTIFIED BY 'password_segur';
GRANT ALL PRIVILEGES ON project_manager.* TO 'pm_user'@'localhost';
FLUSH PRIVILEGES;
```

⚠️ **Canviar 'password_segur' per una contrasenya robusta!**

#### 1.3 Crear Taules

Executar el següent SQL:

```sql
USE project_manager;

-- Taula de projectes
CREATE TABLE projects (
    id VARCHAR(3) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Taula de migracions (per control de versions BD)
CREATE TABLE schema_migrations (
    version VARCHAR(255) PRIMARY KEY,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Nota:** Les taules específiques de cada projecte (project_001, project_002, etc.) es creen automàticament via API.

### Pas 2: Pujar Fitxers al Servidor

#### 2.1 Estructura al Servidor

Crear aquesta estructura al servidor:

```
/htdocs/claudetools/          (o /var/www/html/claudetools/)
├── project_manager.php
├── pm_docs.php
├── api_get_tree.php
├── pm_config.php
├── config.php
└── pmdocs/
    └── PM_INSTRUCTIONS.md
```

#### 2.2 Pujar Fitxers

Via FTP/SFTP, pujar els fitxers de la carpeta `server/`:

```bash
# Des del directori del projecte
scp -r server/* usuari@servidor:/htdocs/claudetools/
```

O utilitzar FileZilla, WinSCP, etc.

### Pas 3: Configurar Connexió BD

#### 3.1 Editar config.php

Obrir `server/config.php` i ajustar:

```php
<?php
// Configuració Base de Dades
define('DB_HOST', 'localhost');
define('DB_USER', 'pm_user');
define('DB_PASS', 'password_segur');
define('DB_NAME', 'project_manager');
define('DB_CHARSET', 'utf8mb4');
```

#### 3.2 Verificar pm_config.php

Assegurar que `server/pm_config.php` carrega correctament la configuració.

### Pas 4: Configurar Permisos

```bash
# Linux/Unix
chmod 644 *.php
chmod 755 pmdocs/

# Assegurar que Apache pot llegir
chown -R www-data:www-data /htdocs/claudetools/
```

### Pas 5: Verificar Servidor

Obrir al navegador:

```
http://el-teu-servidor.com/claudetools/project_manager.php?action=help
```

Hauries de veure un JSON amb les accions disponibles.

---

## Instal·lació Client MCP

### Pas 1: Copiar Scripts

#### 1.1 Crear Directori Scripts

**Windows:**
```cmd
mkdir C:\Users\%USERNAME%\scripts\pm-tools
```

**Mac/Linux:**
```bash
mkdir -p ~/scripts/pm-tools
```

#### 1.2 Copiar Fitxers Python

Copiar els fitxers de `tools/` al directori creat:

```
C:\Users\[USUARI]\scripts\pm-tools\
├── git_manager.py
├── gestio_arxius.py
└── db-insert-utf8.py
```

### Pas 2: Configurar Claude Desktop

#### 2.1 Localitzar Fitxer Configuració

**Windows:**
```
%APPDATA%\Claude\claude_desktop_config.json
```

**Mac:**
```
~/Library/Application Support/Claude/claude_desktop_config.json
```

**Linux:**
```
~/.config/Claude/claude_desktop_config.json
```

#### 2.2 Editar Configuració

Si el fitxer no existeix, crear-lo. Afegir:

```json
{
  "mcpServers": {
    "pm-tools": {
      "command": "python",
      "args": [
        "C:\\Users\\[USUARI]\\scripts\\pm-tools\\desktoptools_mcp.py"
      ],
      "env": {}
    }
  }
}
```

⚠️ **Ajustar la ruta segons el teu sistema!**

**Exemple Windows:**
```json
{
  "mcpServers": {
    "pm-tools": {
      "command": "python",
      "args": [
        "C:\\Users\\Joaquim\\scripts\\pm-tools\\desktoptools_mcp.py"
      ],
      "env": {}
    }
  }
}
```

**Exemple Mac:**
```json
{
  "mcpServers": {
    "pm-tools": {
      "command": "python3",
      "args": [
        "/Users/joaquim/scripts/pm-tools/desktoptools_mcp.py"
      ],
      "env": {}
    }
  }
}
```

### Pas 3: Crear Bridge MCP (PENDENT)

**Nota:** Actualment necessites el bridge MCP de desktoptools. En futures versions, s'inclourà un bridge autocontingut.

Mentrestant, assegurar-te que tens:
- `desktoptools_mcp.py` configurat correctament
- Els scripts Python són descoberts automàticament

### Pas 4: Reiniciar Claude Desktop

1. Tancar completament Claude Desktop
2. Tornar a obrir-lo
3. Esperar que els MCP es carreguin (pot trigar uns segons)

---

## Configuració Git

### Pas 1: Configurar Identitat

Obrir terminal/cmd i executar:

```bash
git config --global user.name "El Teu Nom"
git config --global user.email "el.teu@email.com"
```

### Pas 2: Crear Repositori GitHub

1. Anar a https://github.com/new
2. Crear repositori "ProjectManager" (o el nom que vulguis)
3. NO inicialitzar amb README (ja el tenim)
4. Copiar la URL del repositori: `https://github.com/[usuari]/ProjectManager.git`

### Pas 3: Inicialitzar Projecte

A Claude Desktop, dir:

```
Inicialitza Git per aquest projecte:
- Ruta local: C:\Users\[USUARI]\projectes\013-PM_CT_DT_def
- Repositori remot: https://github.com/[usuari]/ProjectManager.git
```

Claude executarà:
- `git init`
- `git remote add origin [URL]`
- Crearà `git-config.json` amb la configuració

### Pas 4: Primer Commit i Push

```
Fes commit inicial i push a GitHub
```

Claude executarà:
- `git add .`
- `git commit -m "Commit inicial - Project Manager"`
- `git push origin main`

---

## Verificació

### Verificar Servidor

#### Test 1: Project Manager
```
curl http://servidor/claudetools/project_manager.php?action=help
```

Esperat: JSON amb accions disponibles

#### Test 2: Documentació
```
curl http://servidor/claudetools/pm_docs.php
```

Esperat: Documentació completa

### Verificar MCP

A Claude Desktop:

```
Quins tools tens disponibles relacionats amb PM?
```

Hauries de veure:
- `pm-tools:git-manager`
- `pm-tools:gestio-arxius`
- `pm-tools:db-insert-utf8`

### Verificar Git

```bash
cd C:\Users\[USUARI]\projectes\013-PM_CT_DT_def
git status
```

Hauria de mostrar l'estat del repositori.

---

## Troubleshooting

### Error: "Connection refused" al servidor

**Causes possibles:**
- Apache no està funcionant
- Firewall bloquejant el port
- Ruta incorrecta als fitxers

**Solució:**
```bash
# Linux
sudo systemctl status apache2
sudo systemctl start apache2

# Windows XAMPP
Obrir XAMPP Control Panel i start Apache
```

### Error: "Access denied" Base de Dades

**Solució:**
1. Verificar credencials a `config.php`
2. Verificar que l'usuari MySQL té permisos
3. Testejar connexió:

```bash
mysql -u pm_user -p project_manager
```

### Error: "Tool not found" a Claude

**Solució:**
1. Verificar rutes a `claude_desktop_config.json`
2. Assegurar que Python està al PATH
3. Reiniciar Claude Desktop COMPLETAMENT

### Error: Git "Author identity unknown"

**Solució:**
```bash
git config --global user.name "El Teu Nom"
git config --global user.email "el.teu@email.com"
```

### Els scripts Python no funcionen

**Solució:**
1. Verificar versió Python: `python --version`
2. Assegurar que està al PATH
3. Provar amb `python3` en lloc de `python` (Mac/Linux)

---

## Workflow Post-Instal·lació

### 1. Crear Primer Projecte

A Claude:
```
Crea un projecte nou amb ID 001 i nom "Projecte Prova"
```

### 2. Afegir Primera Entrada

```
Afegeix una entrada memo al projecte 001:
- Títol: "Primera nota"
- Contingut: "Això és una prova"
```

### 3. Verificar Dashboard

Obrir `client/dashboard.html` al navegador i connectar al servidor.

---

## Recursos Addicionals

- [Documentació PM](server/pmdocs/PM_INSTRUCTIONS.md)
- [README principal](README.md)
- [Documentació Git](https://git-scm.com/doc)
- [Documentació Claude Desktop](https://claude.ai/download)

---

## Suport

Per problemes durant la instal·lació:

1. Revisar aquesta guia pas a pas
2. Verificar logs del servidor Apache/Nginx
3. Crear issue a GitHub amb detalls de l'error

---

**Última actualització:** Novembre 2024
