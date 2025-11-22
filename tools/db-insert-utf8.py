#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
DB Insert UTF-8
Eina per executar SQL amb UTF-8 correcte via upload temporal
Soluciona els problemes d'encoding en URLs llargues
"""

import os
import sys
import json
import argparse
import tempfile
import requests
from datetime import datetime

# Forçar UTF-8 per stdout/stderr (Windows fix)
if sys.platform == 'win32':
    import codecs
    sys.stdout = codecs.getwriter('utf-8')(sys.stdout.buffer, 'strict')
    sys.stderr = codecs.getwriter('utf-8')(sys.stderr.buffer, 'strict')


def execute_sql_utf8(config: str, sql: str, cleanup: bool = False, debug: bool = False) -> dict:
    """
    Executa SQL amb UTF-8 correcte via upload temporal
    
    Args:
        config: Configuració BD (tutor, etera, ctponts...)
        sql: SQL a executar
        cleanup: Si True, esborra el fitxer remot després
        debug: Si True, mostra informació de debug
    
    Returns:
        dict amb success, message, result...
    """
    
    temp_filename = None
    
    try:
        # 1. Crear fitxer temporal local
        temp_file = tempfile.NamedTemporaryFile(
            mode='w', 
            encoding='utf-8',
            suffix='.txt',
            delete=False,
            prefix='sql_temp_'
        )
        temp_filename = temp_file.name
        temp_file.write(sql)
        temp_file.close()
        
        if debug:
            print(f"[DEBUG] Fitxer temporal local: {temp_filename}", file=sys.stderr)
            print(f"[DEBUG] SQL size: {len(sql)} chars", file=sys.stderr)
        
        # 2. Pujar fitxer al servidor
        upload_url = 'https://www.contratemps.org/claudetools/upload.php'
        remote_filename = 'temp_sql.txt'
        
        with open(temp_filename, 'rb') as f:
            files = {'file': (remote_filename, f, 'text/plain; charset=utf-8')}
            upload_resp = requests.post(upload_url, files=files, timeout=30)
        
        if upload_resp.status_code != 200:
            return {
                "success": False,
                "error": f"Error pujant fitxer: HTTP {upload_resp.status_code}",
                "details": upload_resp.text[:500],
                "step": "upload"
            }
        
        upload_result = upload_resp.json()
        
        if not upload_result.get('success'):
            return {
                "success": False,
                "error": "Upload fallit",
                "details": upload_result,
                "step": "upload"
            }
        
        if debug:
            print(f"[DEBUG] Upload OK: {upload_result}", file=sys.stderr)
        
        # 3. Executar SQL des del fitxer remot
        execute_url = 'https://www.contratemps.org/claudetools/table_editor.php'
        params = {
            'action': 'execute_sql',
            'config': config,
            'sql_file': remote_filename
        }
        
        execute_resp = requests.get(execute_url, params=params, timeout=60)
        
        if execute_resp.status_code != 200:
            return {
                "success": False,
                "error": f"Error executant SQL: HTTP {execute_resp.status_code}",
                "details": execute_resp.text[:500],
                "step": "execute"
            }
        
        result = execute_resp.json()
        
        if debug:
            print(f"[DEBUG] Execute result: {result}", file=sys.stderr)
        
        # 4. Cleanup remot (opcional)
        cleanup_done = False
        if cleanup:
            try:
                cleanup_params = {
                    'action': 'delete_file',
                    'file': remote_filename
                }
                cleanup_resp = requests.get(upload_url, params=cleanup_params, timeout=10)
                cleanup_done = cleanup_resp.status_code == 200
                
                if debug:
                    print(f"[DEBUG] Cleanup remot: {cleanup_done}", file=sys.stderr)
            except Exception as e:
                if debug:
                    print(f"[DEBUG] Error cleanup remot: {e}", file=sys.stderr)
        
        # 5. Retornar resultat
        return {
            "success": True,
            "message": result.get('message', 'SQL executat correctament'),
            "affected_rows": result.get('affected_rows', 0),
            "query_results": result.get('query_results'),
            "result_count": result.get('result_count'),
            "sql_executed": result.get('sql_executed'),
            "access_info": result.get('access'),
            "process": {
                "temp_file_local": temp_filename,
                "temp_file_remote": remote_filename,
                "uploaded": True,
                "executed": True,
                "cleanup_remote": cleanup_done
            }
        }
        
    except requests.exceptions.Timeout as e:
        return {
            "success": False,
            "error": f"Timeout: {str(e)}",
            "step": "request",
            "details": "La petició ha trigat massa temps"
        }
        
    except requests.exceptions.RequestException as e:
        return {
            "success": False,
            "error": f"Error de xarxa: {str(e)}",
            "step": "request"
        }
        
    except Exception as e:
        return {
            "success": False,
            "error": str(e),
            "step": "general",
            "type": type(e).__name__
        }
        
    finally:
        # 6. Cleanup local sempre
        if temp_filename and os.path.exists(temp_filename):
            try:
                os.unlink(temp_filename)
                if debug:
                    print(f"[DEBUG] Cleanup local OK", file=sys.stderr)
            except Exception as e:
                if debug:
                    print(f"[DEBUG] Error cleanup local: {e}", file=sys.stderr)


def test_connection(config: str = 'tutor') -> dict:
    """
    Testa la connexió executant un SELECT simple
    """
    test_sql = "SELECT 1 as test, 'àèéíòú' as utf8_test, '💙' as emoji_test"
    
    result = execute_sql_utf8(
        config=config,
        sql=test_sql,
        cleanup=True,
        debug=False
    )
    
    if result.get('success'):
        query_results = result.get('query_results', [])
        if query_results:
            first_row = query_results[0]
            utf8_ok = first_row.get('utf8_test') == 'àèéíòú'
            emoji_ok = first_row.get('emoji_test') == '💙'
            
            return {
                "success": True,
                "message": "Test de connexió OK",
                "utf8_working": utf8_ok,
                "emoji_working": emoji_ok,
                "test_results": first_row
            }
    
    return {
        "success": False,
        "message": "Test de connexió fallit",
        "details": result
    }


if __name__ == "__main__":
    parser = argparse.ArgumentParser(
        description="Eina per executar SQL amb UTF-8 correcte via upload temporal."
    )
    
    parser.add_argument(
        "--info", 
        action="store_true", 
        help="Mostra la informació d'autodescripció de la tool."
    )

    subparsers = parser.add_subparsers(dest="command", help="Comandes disponibles")

    # Subparser per a execute (ARGUMENTS POSICIONALS)
    parser_execute = subparsers.add_parser(
        "execute", 
        help="Executa SQL amb UTF-8 correcte."
    )
    parser_execute.add_argument(
        "config",
        type=str,
        help="Configuració BD (tutor, etera, ctponts...)"
    )
    parser_execute.add_argument(
        "sql",
        type=str,
        help="SQL a executar"
    )
    parser_execute.add_argument(
        "cleanup",
        type=str,
        nargs='?',
        default='false',
        help="true/false - Esborrar fitxer remot després (opcional)"
    )
    parser_execute.add_argument(
        "debug",
        type=str,
        nargs='?',
        default='false',
        help="true/false - Mostrar info debug (opcional)"
    )

    # Subparser per a test (ARGUMENT POSICIONAL)
    parser_test = subparsers.add_parser(
        "test", 
        help="Testa la connexió i UTF-8."
    )
    parser_test.add_argument(
        "config",
        type=str,
        nargs='?',
        default="tutor",
        help="Configuració BD (opcional, default: tutor)"
    )

    args = parser.parse_args()

    if args.info:
        tool_info = {
            "nom": "db-insert-utf8",
            "versio": "1.1",
            "que_fa": "Executa SQL amb UTF-8 correcte via upload temporal, solucionant problemes d'encoding en URLs llargues i permetent executar SQLs de qualsevol mida.",
            "com_ho_fa": "1) Crea fitxer temporal local amb SQL, 2) Puja fitxer al servidor via upload.php, 3) Crida table_editor.php amb sql_file=temp_sql.txt, 4) Retorna resultat, 5) Neteja fitxers temporals.",
            "que_necessita": [
                {
                    "nom": "config",
                    "tipus": "string",
                    "descripcio": "Configuració BD (tutor, etera, ctponts...)"
                },
                {
                    "nom": "sql",
                    "tipus": "string",
                    "descripcio": "SQL a executar (INSERT, UPDATE, SELECT, DELETE...)"
                },
                {
                    "nom": "cleanup",
                    "tipus": "string",
                    "descripcio": "true/false - Esborrar fitxer remot després (opcional, default: false)"
                },
                {
                    "nom": "debug",
                    "tipus": "string",
                    "descripcio": "true/false - Mostrar info debug (opcional, default: false)"
                }
            ],
            "que_retorna": "Objecte JSON amb success (bool), message (str), affected_rows (int), query_results (array si SELECT), i informació del procés.",
            "funcions_disponibles": [
                {
                    "nom": "execute",
                    "descripcio": "Executa SQL amb UTF-8 correcte.",
                    "parametres": ["config", "sql", "cleanup", "debug"]
                },
                {
                    "nom": "test",
                    "descripcio": "Testa la connexió i verifica que UTF-8 funciona correctament.",
                    "parametres": ["config"]
                }
            ],
            "avantatges": [
                "UTF-8 perfecte (accents, emojis, caràcters especials)",
                "Sense límit de mida (SQLs de MB si cal)",
                "Autònom (Claude pot fer tot el procés)",
                "Consum mínim de tokens (només SQL, no Base64)",
                "Retrocompatible (no afecta altres eines)"
            ],
            "dependències": [
                "requests (pip install requests)"
            ],
            "endpoints": [
                "https://www.contratemps.org/claudetools/upload.php",
                "https://www.contratemps.org/claudetools/table_editor.php"
            ]
        }
        print(json.dumps(tool_info, indent=2, ensure_ascii=False))
        
    elif args.command == "execute":
        # Convertir strings "true"/"false" a booleans
        cleanup_bool = args.cleanup.lower() == 'true' if args.cleanup else False
        debug_bool = args.debug.lower() == 'true' if args.debug else False
        
        result = execute_sql_utf8(
            config=args.config,
            sql=args.sql,
            cleanup=cleanup_bool,
            debug=debug_bool
        )
        print(json.dumps(result, indent=2, ensure_ascii=False))
        
    elif args.command == "test":
        result = test_connection(config=args.config)
        print(json.dumps(result, indent=2, ensure_ascii=False))
        
    else:
        parser.print_help()
