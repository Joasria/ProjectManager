import os
from typing import Optional
import json
import argparse

def read_lines(file_path: str, start_line: int, end_line: Optional[int] = None) -> str:
    """Llegeix línies específiques d'un fitxer de text.

    Args:
        file_path: La ruta absoluta al fitxer.
        start_line: El número de la primera línia a llegir (base 1).
        end_line: El número de la darrera línia a llegir (base 1). Si és None, llegeix fins al final.

    Returns:
        El contingut de les línies especificades com una cadena, o un missatge d'error.
    """
    if not os.path.exists(file_path):
        return f"Error: El fitxer no existeix a {file_path}"

    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            lines = f.readlines()
            
        # Ajustar a índex base 0
        start_idx = start_line - 1
        end_idx = end_line - 1 if end_line is not None else len(lines)

        if not (0 <= start_idx < len(lines)):
            return f"Error: start_line ({start_line}) fora de rang. El fitxer té {len(lines)} línies."
        if end_idx < start_idx:
            return f"Error: end_line ({end_line}) és anterior a start_line ({start_line})."
        if end_idx >= len(lines):
            end_idx = len(lines) - 1 # Ajustar end_idx si supera el final del fitxer

        selected_lines = lines[start_idx : end_idx + 1]
        return "".join(selected_lines)

    except Exception as e:
        return f"Error en llegir el fitxer: {e}"

def insert_lines(file_path: str, content: str, line_number: int) -> bool:
    """Insereix contingut en una línia específica d'un fitxer de text.

    Args:
        file_path: La ruta absoluta al fitxer.
        content: El contingut a inserir.
        line_number: El número de línia on inserir el contingut (base 1).

    Returns:
        True si la inserció és exitosa, False en cas contrari.
    """
    try:
        if os.path.exists(file_path):
            with open(file_path, 'r', encoding='utf-8') as f:
                lines = f.readlines()
        else:
            lines = []

        # Ajustar a índex base 0
        insert_idx = line_number - 1

        if insert_idx < 0:
            insert_idx = 0
        if insert_idx > len(lines):
            insert_idx = len(lines) # Si el número de línia és més gran que el fitxer, afegir al final

        new_lines = lines[:insert_idx] + [content + '\n'] + lines[insert_idx:]

        with open(file_path, 'w', encoding='utf-8') as f:
            f.writelines(new_lines)
        return True

    except Exception as e:
        print(f"Error en inserir línies al fitxer {file_path}: {e}")
        return False

def delete_lines(file_path: str, start_line: int, end_line: Optional[int] = None) -> bool:
    """Esborra línies específiques d'un fitxer de text.

    Args:
        file_path: La ruta absoluta al fitxer.
        start_line: El número de la primera línia a esborrar (base 1).
        end_line: El número de la darrera línia a esborrar (base 1). Si és None, esborra només start_line.

    Returns:
        True si l'esborrat és exitós, False en cas contrari.
    """
    if not os.path.exists(file_path):
        print(f"Error: El fitxer no existeix a {file_path}")
        return False

    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            lines = f.readlines()

        # Ajustar a índex base 0
        start_idx = start_line - 1
        end_idx = end_line - 1 if end_line is not None else start_idx

        if not (0 <= start_idx < len(lines)):
            print(f"Error: start_line ({start_line}) fora de rang. El fitxer té {len(lines)} línies.")
            return False
        if end_idx < start_idx:
            print(f"Error: end_line ({end_line}) és anterior a start_line ({start_line}).")
            return False
        if end_idx >= len(lines):
            end_idx = len(lines) - 1 # Ajustar end_idx si supera el final del fitxer

        new_lines = lines[:start_idx] + lines[end_idx + 1:]

        with open(file_path, 'w', encoding='utf-8') as f:
            f.writelines(new_lines)
        return True

    except Exception as e:
        print(f"Error en esborrar línies del fitxer {file_path}: {e}")
        return False


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Eina per manipular fitxers de text per línies.")
    parser.add_argument("--info", action="store_true", help="Mostra la informació d'autodescripció de la tool.")

    subparsers = parser.add_subparsers(dest="command", help="Comandes disponibles")

    # Subparser per a read_lines
    parser_read = subparsers.add_parser("read_lines", help="Llegeix línies d'un fitxer.")
    parser_read.add_argument("file_path", type=str, help="La ruta absoluta al fitxer.")
    parser_read.add_argument("start_line", type=int, help="El número de la primera línia a llegir (base 1).")
    parser_read.add_argument("--end_line", type=int, help="El número de la darrera línia a llegir (base 1).", default=None)

    # Subparser per a insert_lines
    parser_insert = subparsers.add_parser("insert_lines", help="Insereix contingut en una línia específica d'un fitxer.")
    parser_insert.add_argument("file_path", type=str, help="La ruta absoluta al fitxer.")
    parser_insert.add_argument("content", type=str, help="El contingut a inserir.")
    parser_insert.add_argument("line_number", type=int, help="El número de línia on inserir el contingut (base 1).")

    # Subparser per a delete_lines
    parser_delete = subparsers.add_parser("delete_lines", help="Esborra línies d'un fitxer.")
    parser_delete.add_argument("file_path", type=str, help="La ruta absoluta al fitxer.")
    parser_delete.add_argument("start_line", type=int, help="El número de la primera línia a esborrar (base 1).")
    parser_delete.add_argument("--end_line", type=int, help="El número de la darrera línia a esborrar (base 1).", default=None)

    args = parser.parse_args()

    if args.info:
        tool_info = {
            "que_fa": "Permet llegir, inserir i esborrar línies de fitxers de text.",
            "com_ho_fa": "Accedeix al fitxer, llegeix el seu contingut línia per línia, realitza la modificació sol·licitada (lectura, inserció o esborrat) i reescriu el fitxer si cal. Utilitza rutes absolutes per als fitxers.",
            "que_necessita": [
                {
                    "nom": "file_path",
                    "tipus": "string",
                    "descripcio": "La ruta absoluta al fitxer de text."
                },
                {
                    "nom": "start_line",
                    "tipus": "integer",
                    "descripcio": "El número de línia inicial (base 1) per a l'operació."
                },
                {
                    "nom": "end_line",
                    "tipus": "integer",
                    "descripcio": "(Opcional) El número de línia final (base 1) per a l'operació. Si no s'especifica, l'operació s'aplica a una sola línia o fins al final."
                },
                {
                    "nom": "content",
                    "tipus": "string",
                    "descripcio": "(Opcional, només per a 'insert_lines') El contingut de text a inserir."
                }
            ],
            "que_retorna": "Depèn de la funció: el contingut llegit (read_lines), True/False (insert_lines, delete_lines) o un missatge d'error.",
            "funcions_disponibles": [
                {
                    "nom": "read_lines",
                    "descripcio": "Llegeix línies d'un fitxer.",
                    "parametres": ["file_path", "start_line", "end_line"]
                },
                {
                    "nom": "insert_lines",
                    "descripcio": "Insereix contingut en una línia específica.",
                    "parametres": ["file_path", "content", "line_number"]
                },
                {
                    "nom": "delete_lines",
                    "descripcio": "Esborra línies d'un fitxer.",
                    "parametres": ["file_path", "start_line", "end_line"]
                }
            ]
        }
        print(json.dumps(tool_info, indent=2, ensure_ascii=False))
    elif args.command == "read_lines":
        result = read_lines(args.file_path, args.start_line, args.end_line)
        print(result)
    elif args.command == "insert_lines":
        result = insert_lines(args.file_path, args.content, args.line_number)
        print(result)
    elif args.command == "delete_lines":
        result = delete_lines(.file_path, args.start_line, args.end_line)
        print(result)
