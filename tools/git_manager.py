"""
Gestor de Repositoris Git per Project Manager

Sistema d'integració Git per sincronitzar projectes amb repositoris remots.

Funcions:
- init: Configura Git per un projecte (crea/actualitza git-config.json)
- status: Mostra fitxers modificats i estat actual
- sync_check: Compara local vs remot (fetch + comparació)
- pull: Actualitza carpeta local des del remot
- commit: Crea commit amb missatge descriptiu
- push: Puja commits locals al remot
- log: Mostra historial de commits
- help: Mostra ajuda detallada

Workflow recomanat:
1. Iniciar sessió: sync_check -> pull (si cal)
2. Durant la feina: status per veure canvis
3. Checkpoint: commit amb missatge descriptiu
4. Final sessió: push per pujar canvis

Cada projecte té git-config.json amb URL remot, branch, ruta local.
"""

import os
import json
import subprocess
import argparse
from datetime import datetime
from pathlib import Path


def run_git_command(command: list, cwd: str) -> dict:
    """
    Executa una comanda Git i retorna el resultat.
    
    Args:
        command: Llista amb la comanda i arguments ['git', 'status', '--porcelain']
        cwd: Directori de treball on executar la comanda
        
    Returns:
        Dict amb success, output, code
    """
    try:
        result = subprocess.run(
            command,
            cwd=cwd,
            capture_output=True,
            text=True,
            encoding='utf-8'
        )
        
        return {
            'success': result.returncode == 0,
            'output': result.stdout.strip() if result.stdout else result.stderr.strip(),
            'code': result.returncode
        }
    except Exception as e:
        return {
            'success': False,
            'output': str(e),
            'code': -1
        }


def load_config(project_path: str) -> dict:
    """Carrega la configuració Git del projecte."""
    config_file = os.path.join(project_path, 'git-config.json')
    
    if os.path.exists(config_file):
        with open(config_file, 'r', encoding='utf-8') as f:
            return json.load(f)
    return None


def save_config(project_path: str, config: dict):
    """Guarda la configuració Git del projecte."""
    config_file = os.path.join(project_path, 'git-config.json')
    
    with open(config_file, 'w', encoding='utf-8') as f:
        json.dump(config, f, indent=2, ensure_ascii=False)


def init_project(project_path: str, remote_url: str, branch: str = 'main') -> dict:
    """
    Inicialitza o actualitza la configuració Git d'un projecte.
    
    Args:
        project_path: Ruta absoluta del projecte
        remote_url: URL del repositori remot (https://github.com/user/repo.git)
        branch: Branch a utilitzar (default: main)
        
    Returns:
        Dict amb success, message, config, git_version
    """
    # Verificar que el directori existeix
    if not os.path.exists(project_path):
        return {
            'success': False,
            'error': f'El directori no existeix: {project_path}'
        }
    
    # Verificar si Git està instal·lat
    git_test = run_git_command(['git', '--version'], project_path)
    if not git_test['success']:
        return {
            'success': False,
            'error': 'Git no està instal·lat o no és accessible',
            'details': git_test['output']
        }
    
    # Verificar si és un repositori Git
    is_git_repo = run_git_command(['git', 'rev-parse', '--git-dir'], project_path)
    
    if not is_git_repo['success']:
        # Inicialitzar repositori
        init_result = run_git_command(['git', 'init'], project_path)
        if not init_result['success']:
            return {
                'success': False,
                'error': 'No s\'ha pogut inicialitzar el repositori Git',
                'details': init_result['output']
            }
    
    # Configurar remote
    remote_exists = run_git_command(['git', 'remote', 'get-url', 'origin'], project_path)
    if remote_exists['success']:
        # Actualitzar URL
        run_git_command(['git', 'remote', 'set-url', 'origin', remote_url], project_path)
    else:
        # Afegir remote
        run_git_command(['git', 'remote', 'add', 'origin', remote_url], project_path)
    
    # Crear/actualitzar configuració
    project_name = os.path.basename(project_path)
    
    config = {
        'project_name': project_name,
        'project_path': project_path,
        'git': {
            'remote_url': remote_url,
            'branch': branch,
            'auto_pull_on_start': True,
            'last_sync': datetime.now().strftime('%Y-%m-%d %H:%M:%S'),
            'initialized': datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        }
    }
    
    save_config(project_path, config)
    
    return {
        'success': True,
        'message': 'Projecte configurat amb Git correctament',
        'config': config,
        'git_version': git_test['output']
    }


def get_status(project_path: str) -> dict:
    """
    Obté l'estat actual del repositori.
    
    Returns:
        Dict amb success, branch, files (modified/added/deleted/untracked), has_changes, config
    """
    config = load_config(project_path)
    if not config:
        return {
            'success': False,
            'error': 'Projecte no inicialitzat. Executa action=init primer.'
        }
    
    status_result = run_git_command(['git', 'status', '--porcelain'], project_path)
    branch_result = run_git_command(['git', 'branch', '--show-current'], project_path)
    
    # Parsejar fitxers modificats
    files = {
        'modified': [],
        'added': [],
        'deleted': [],
        'untracked': []
    }
    
    if status_result['success'] and status_result['output']:
        for line in status_result['output'].split('\n'):
            if not line.strip():
                continue
            
            status = line[:2]
            file = line[3:].strip()
            
            if 'M' in status:
                files['modified'].append(file)
            elif 'A' in status:
                files['added'].append(file)
            elif 'D' in status:
                files['deleted'].append(file)
            elif '?' in status:
                files['untracked'].append(file)
    
    has_changes = any(files.values())
    
    return {
        'success': True,
        'branch': branch_result['output'],
        'files': files,
        'has_changes': has_changes,
        'config': config
    }


def sync_check(project_path: str) -> dict:
    """
    Comprova si hi ha diferències entre local i remot.
    
    Returns:
        Dict amb success, behind, ahead, needs_pull, needs_push, in_sync, message
    """
    config = load_config(project_path)
    if not config:
        return {
            'success': False,
            'error': 'Projecte no inicialitzat'
        }
    
    # Fetch per actualitzar referències
    fetch_result = run_git_command(['git', 'fetch', 'origin'], project_path)
    
    # Comparar local amb remot
    branch = config['git']['branch']
    behind_result = run_git_command(
        ['git', 'rev-list', '--count', f'HEAD..origin/{branch}'],
        project_path
    )
    ahead_result = run_git_command(
        ['git', 'rev-list', '--count', f'origin/{branch}..HEAD'],
        project_path
    )
    
    behind = int(behind_result['output']) if behind_result['success'] else 0
    ahead = int(ahead_result['output']) if ahead_result['success'] else 0
    
    # Generar missatge descriptiu
    if behind == 0 and ahead == 0:
        message = 'Local i remot estan sincronitzats OK'
    elif behind > 0 and ahead == 0:
        message = f"El remot te {behind} commit(s) nous. Executa pull per actualitzar."
    elif behind == 0 and ahead > 0:
        message = f"Tens {ahead} commit(s) locals pendents de pujar. Executa push."
    else:
        message = f"Hi ha divergencies: {behind} commits al remot, {ahead} locals. Pot requerir merge."
    
    return {
        'success': True,
        'behind': behind,
        'ahead': ahead,
        'needs_pull': behind > 0,
        'needs_push': ahead > 0,
        'in_sync': behind == 0 and ahead == 0,
        'message': message
    }


def pull_changes(project_path: str) -> dict:
    """
    Actualitza el repositori local des del remot.
    
    Returns:
        Dict amb success, output, message
    """
    config = load_config(project_path)
    if not config:
        return {
            'success': False,
            'error': 'Projecte no inicialitzat'
        }
    
    branch = config['git']['branch']
    pull_result = run_git_command(['git', 'pull', 'origin', branch], project_path)
    
    # Actualitzar last_sync
    config['git']['last_sync'] = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    save_config(project_path, config)
    
    return {
        'success': pull_result['success'],
        'output': pull_result['output'],
        'message': 'Pull completat correctament' if pull_result['success'] else 'Error en el pull'
    }


def commit_changes(project_path: str, message: str) -> dict:
    """
    Fa commit dels canvis actuals.
    
    Args:
        project_path: Ruta del projecte
        message: Missatge de commit
        
    Returns:
        Dict amb success, output, message
    """
    config = load_config(project_path)
    if not config:
        return {
            'success': False,
            'error': 'Projecte no inicialitzat'
        }
    
    if not message:
        return {
            'success': False,
            'error': 'Cal proporcionar un missatge de commit'
        }
    
    # Afegir tots els fitxers
    add_result = run_git_command(['git', 'add', '.'], project_path)
    if not add_result['success']:
        return {
            'success': False,
            'error': 'Error afegint fitxers',
            'details': add_result['output']
        }
    
    # Fer commit
    commit_result = run_git_command(['git', 'commit', '-m', message], project_path)
    
    return {
        'success': commit_result['success'],
        'output': commit_result['output'],
        'message': 'Commit creat correctament' if commit_result['success'] else 'Error en el commit (potser no hi ha canvis?)'
    }


def push_changes(project_path: str) -> dict:
    """
    Puja els commits locals al remot.
    
    Returns:
        Dict amb success, output, message
    """
    config = load_config(project_path)
    if not config:
        return {
            'success': False,
            'error': 'Projecte no inicialitzat'
        }
    
    branch = config['git']['branch']
    push_result = run_git_command(['git', 'push', 'origin', branch], project_path)
    
    return {
        'success': push_result['success'],
        'output': push_result['output'],
        'message': 'Push completat correctament' if push_result['success'] else 'Error en el push'
    }


def get_log(project_path: str, limit: int = 10, show_full: bool = False) -> dict:
    """
    Mostra l'historial de commits.
    
    Args:
        project_path: Ruta del projecte
        limit: Número màxim de commits a mostrar
        show_full: Si True, mostra format complet; si False, format oneline
        
    Returns:
        Dict amb success, output, commits
    """
    config = load_config(project_path)
    if not config:
        return {
            'success': False,
            'error': 'Projecte no inicialitzat'
        }
    
    cmd = ['git', 'log', f'-n', str(limit)]
    if not show_full:
        cmd.append('--oneline')
    
    log_result = run_git_command(cmd, project_path)
    
    commits = log_result['output'].split('\n') if log_result['output'] else []
    
    return {
        'success': log_result['success'],
        'output': log_result['output'],
        'commits': commits
    }


def show_help() -> dict:
    """Mostra ajuda detallada del tool."""
    return {
        'success': True,
        'tool': 'Git Manager per Project Manager',
        'description': 'Gestiona repositoris Git per sincronitzar projectes amb GitHub/GitLab',
        'workflow': {
            '1. Iniciar sessió': 'sync_check -> pull (si cal)',
            '2. Durant la feina': 'status per veure canvis',
            '3. Checkpoint': 'commit amb missatge descriptiu',
            '4. Final sessió': 'push per pujar canvis'
        },
        'actions': {
            'init': 'Configura Git per un projecte (requereix remote_url)',
            'status': 'Mostra fitxers modificats i estat actual',
            'sync_check': 'Compara local vs remot (fetch + comparació)',
            'pull': 'Actualitza carpeta local des del remot',
            'commit': 'Crea commit amb missatge (requereix message)',
            'push': 'Puja commits locals al remot',
            'log': 'Mostra historial de commits',
            'help': 'Mostra aquesta ajuda'
        }
    }


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Gestor de Repositoris Git per Project Manager")
    parser.add_argument("--info", action="store_true", help="Mostra la informació d'autodescripció de la tool.")

    subparsers = parser.add_subparsers(dest="command", help="Comandes disponibles")

    # Subparser per init
    parser_init = subparsers.add_parser("init", help="Configura Git per un projecte.")
    parser_init.add_argument("project_path", type=str, help="Ruta absoluta del projecte.")
    parser_init.add_argument("remote_url", type=str, help="URL del repositori remot.")
    parser_init.add_argument("--branch", type=str, default="main", help="Branch a utilitzar (default: main).")

    # Subparser per status
    parser_status = subparsers.add_parser("status", help="Mostra fitxers modificats i estat actual.")
    parser_status.add_argument("project_path", type=str, help="Ruta absoluta del projecte.")

    # Subparser per sync_check
    parser_sync = subparsers.add_parser("sync_check", help="Compara local vs remot.")
    parser_sync.add_argument("project_path", type=str, help="Ruta absoluta del projecte.")

    # Subparser per pull
    parser_pull = subparsers.add_parser("pull", help="Actualitza carpeta local des del remot.")
    parser_pull.add_argument("project_path", type=str, help="Ruta absoluta del projecte.")

    # Subparser per commit
    parser_commit = subparsers.add_parser("commit", help="Crea commit amb missatge.")
    parser_commit.add_argument("project_path", type=str, help="Ruta absoluta del projecte.")
    parser_commit.add_argument("message", type=str, help="Missatge de commit.")

    # Subparser per push
    parser_push = subparsers.add_parser("push", help="Puja commits locals al remot.")
    parser_push.add_argument("project_path", type=str, help="Ruta absoluta del projecte.")

    # Subparser per log
    parser_log = subparsers.add_parser("log", help="Mostra historial de commits.")
    parser_log.add_argument("project_path", type=str, help="Ruta absoluta del projecte.")
    parser_log.add_argument("--limit", type=int, default=10, help="Número de commits a mostrar.")
    parser_log.add_argument("--show_full", action="store_true", help="Mostrar log complet.")

    # Subparser per help
    parser_help = subparsers.add_parser("help", help="Mostra ajuda detallada.")

    args = parser.parse_args()

    if args.info:
        tool_info = {
            "que_fa": "Gestiona repositoris Git per sincronitzar projectes amb GitHub/GitLab. Cada projecte te git-config.json amb configuracio.",
            "com_ho_fa": "Executa comandes Git (init, status, fetch, pull, commit, push) i guarda configuració en JSON. Compara local vs remot per sincronització.",
            "que_necessita": [
                {
                    "nom": "project_path",
                    "tipus": "string",
                    "descripcio": "Ruta absoluta del projecte."
                },
                {
                    "nom": "remote_url",
                    "tipus": "string",
                    "descripcio": "URL del repositori remot (per init)."
                },
                {
                    "nom": "branch",
                    "tipus": "string",
                    "descripcio": "Branch a utilitzar (default: main)."
                },
                {
                    "nom": "message",
                    "tipus": "string",
                    "descripcio": "Missatge de commit (per commit)."
                },
                {
                    "nom": "limit",
                    "tipus": "integer",
                    "descripcio": "Número de commits a mostrar (per log)."
                },
                {
                    "nom": "show_full",
                    "tipus": "boolean",
                    "descripcio": "Mostrar log complet (per log)."
                }
            ],
            "que_retorna": "Objecte JSON amb success (bool), informació detallada segons l'acció.",
            "funcions_disponibles": [
                {
                    "nom": "init",
                    "descripcio": "Configura Git per un projecte (crea git-config.json).",
                    "parametres": ["project_path", "remote_url", "branch"]
                },
                {
                    "nom": "status",
                    "descripcio": "Mostra fitxers modificats i estat actual.",
                    "parametres": ["project_path"]
                },
                {
                    "nom": "sync_check",
                    "descripcio": "Compara local vs remot (fetch + comparació).",
                    "parametres": ["project_path"]
                },
                {
                    "nom": "pull",
                    "descripcio": "Actualitza carpeta local des del remot.",
                    "parametres": ["project_path"]
                },
                {
                    "nom": "commit",
                    "descripcio": "Crea commit amb missatge descriptiu.",
                    "parametres": ["project_path", "message"]
                },
                {
                    "nom": "push",
                    "descripcio": "Puja commits locals al remot.",
                    "parametres": ["project_path"]
                },
                {
                    "nom": "log",
                    "descripcio": "Mostra historial de commits.",
                    "parametres": ["project_path", "limit", "show_full"]
                },
                {
                    "nom": "help",
                    "descripcio": "Mostra ajuda detallada.",
                    "parametres": []
                }
            ]
        }
        print(json.dumps(tool_info, indent=2, ensure_ascii=False))
    elif args.command == "init":
        result = init_project(args.project_path, args.remote_url, args.branch)
        print(json.dumps(result, indent=2, ensure_ascii=False))
    elif args.command == "status":
        result = get_status(args.project_path)
        print(json.dumps(result, indent=2, ensure_ascii=False))
    elif args.command == "sync_check":
        result = sync_check(args.project_path)
        print(json.dumps(result, indent=2, ensure_ascii=False))
    elif args.command == "pull":
        result = pull_changes(args.project_path)
        print(json.dumps(result, indent=2, ensure_ascii=False))
    elif args.command == "commit":
        result = commit_changes(args.project_path, args.message)
        print(json.dumps(result, indent=2, ensure_ascii=False))
    elif args.command == "push":
        result = push_changes(args.project_path)
        print(json.dumps(result, indent=2, ensure_ascii=False))
    elif args.command == "log":
        result = get_log(args.project_path, args.limit, args.show_full)
        print(json.dumps(result, indent=2, ensure_ascii=False))
    elif args.command == "help":
        result = show_help()
        print(json.dumps(result, indent=2, ensure_ascii=False))
    else:
        parser.print_help()
