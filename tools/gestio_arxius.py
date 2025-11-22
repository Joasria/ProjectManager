import os
import shutil
import json
import argparse
from datetime import datetime

def copy_file(source_path: str, dest_path: str) -> dict:
    """Copia un arxiu d'origen a destí."""
    try:
        if not os.path.exists(source_path):
            return {
                "success": False,
                "message": f"Error: L'arxiu origen no existeix: {source_path}",
                "source": source_path,
                "destination": dest_path
            }
        
        if not os.path.isfile(source_path):
            return {
                "success": False,
                "message": f"Error: El path origen no és un arxiu: {source_path}",
                "source": source_path,
                "destination": dest_path
            }
        
        # Crear directori destí si no existeix
        dest_dir = os.path.dirname(dest_path)
        if dest_dir and not os.path.exists(dest_dir):
            os.makedirs(dest_dir)
        
        shutil.copy2(source_path, dest_path)
        file_size = os.path.getsize(dest_path)
        
        return {
            "success": True,
            "message": f"Arxiu copiat correctament",
            "source": source_path,
            "destination": dest_path,
            "size_bytes": file_size
        }
        
    except Exception as e:
        return {
            "success": False,
            "message": f"Error en copiar l'arxiu: {str(e)}",
            "source": source_path,
            "destination": dest_path
        }


def move_file(source_path: str, dest_path: str) -> dict:
    """Mou o renombra un arxiu."""
    try:
        if not os.path.exists(source_path):
            return {
                "success": False,
                "message": f"Error: L'arxiu origen no existeix: {source_path}",
                "source": source_path,
                "destination": dest_path
            }
        
        if not os.path.isfile(source_path):
            return {
                "success": False,
                "message": f"Error: El path origen no és un arxiu: {source_path}",
                "source": source_path,
                "destination": dest_path
            }
        
        # Crear directori destí si no existeix
        dest_dir = os.path.dirname(dest_path)
        if dest_dir and not os.path.exists(dest_dir):
            os.makedirs(dest_dir)
        
        shutil.move(source_path, dest_path)
        
        return {
            "success": True,
            "message": f"Arxiu mogut/renombrat correctament",
            "source": source_path,
            "destination": dest_path
        }
        
    except Exception as e:
        return {
            "success": False,
            "message": f"Error en moure l'arxiu: {str(e)}",
            "source": source_path,
            "destination": dest_path
        }


def delete_file(file_path: str) -> dict:
    """Elimina un arxiu."""
    try:
        if not os.path.exists(file_path):
            return {
                "success": False,
                "message": f"Error: L'arxiu no existeix: {file_path}",
                "path": file_path
            }
        
        if not os.path.isfile(file_path):
            return {
                "success": False,
                "message": f"Error: El path no és un arxiu: {file_path}",
                "path": file_path
            }
        
        os.remove(file_path)
        
        return {
            "success": True,
            "message": f"Arxiu eliminat correctament",
            "path": file_path
        }
        
    except Exception as e:
        return {
            "success": False,
            "message": f"Error en eliminar l'arxiu: {str(e)}",
            "path": file_path
        }


def file_exists(file_path: str) -> dict:
    """Comprova si un arxiu existeix."""
    exists = os.path.exists(file_path)
    is_file = os.path.isfile(file_path) if exists else False
    
    result = {
        "exists": exists,
        "is_file": is_file,
        "path": file_path
    }
    
    if exists and is_file:
        stat = os.stat(file_path)
        result["size_bytes"] = stat.st_size
        result["modified"] = datetime.fromtimestamp(stat.st_mtime).isoformat()
        result["created"] = datetime.fromtimestamp(stat.st_ctime).isoformat()
    
    return result


def copy_with_timestamp(source_path: str, dest_dir: str, suffix: str = None) -> dict:
    """Copia un arxiu afegint timestamp al nom (útil per backups)."""
    try:
        if not os.path.exists(source_path):
            return {
                "success": False,
                "message": f"Error: L'arxiu origen no existeix: {source_path}",
                "source": source_path
            }
        
        # Crear nom amb timestamp
        filename = os.path.basename(source_path)
        name, ext = os.path.splitext(filename)
        timestamp = datetime.now().strftime("%Y%m%d-%H%M%S")
        
        if suffix:
            new_name = f"{name}-{suffix}-{timestamp}{ext}"
        else:
            new_name = f"{name}-{timestamp}{ext}"
        
        dest_path = os.path.join(dest_dir, new_name)
        
        # Crear directori destí si no existeix
        if not os.path.exists(dest_dir):
            os.makedirs(dest_dir)
        
        shutil.copy2(source_path, dest_path)
        file_size = os.path.getsize(dest_path)
        
        return {
            "success": True,
            "message": f"Backup creat correctament",
            "source": source_path,
            "destination": dest_path,
            "size_bytes": file_size
        }
        
    except Exception as e:
        return {
            "success": False,
            "message": f"Error en crear backup: {str(e)}",
            "source": source_path
        }


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Eina per gestionar arxius (copiar, moure, eliminar).")
    parser.add_argument("--info", action="store_true", help="Mostra la informació d'autodescripció de la tool.")

    subparsers = parser.add_subparsers(dest="command", help="Comandes disponibles")

    # Subparser per a copy_file
    parser_copy = subparsers.add_parser("copy", help="Copia un arxiu.")
    parser_copy.add_argument("source", type=str, help="Ruta absoluta de l'arxiu origen.")
    parser_copy.add_argument("destination", type=str, help="Ruta absoluta de l'arxiu destí.")

    # Subparser per a move_file
    parser_move = subparsers.add_parser("move", help="Mou o renombra un arxiu.")
    parser_move.add_argument("source", type=str, help="Ruta absoluta de l'arxiu origen.")
    parser_move.add_argument("destination", type=str, help="Ruta absoluta de l'arxiu destí.")

    # Subparser per a delete_file
    parser_delete = subparsers.add_parser("delete", help="Elimina un arxiu.")
    parser_delete.add_argument("path", type=str, help="Ruta absoluta de l'arxiu a eliminar.")

    # Subparser per a file_exists
    parser_exists = subparsers.add_parser("exists", help="Comprova si un arxiu existeix.")
    parser_exists.add_argument("path", type=str, help="Ruta absoluta de l'arxiu.")

    # Subparser per a copy_with_timestamp (backup)
    parser_backup = subparsers.add_parser("backup", help="Copia un arxiu amb timestamp (backup).")
    parser_backup.add_argument("source", type=str, help="Ruta absoluta de l'arxiu origen.")
    parser_backup.add_argument("dest_dir", type=str, help="Directori destí.")
    parser_backup.add_argument("--suffix", type=str, help="Sufix opcional (ex: v2.2).", default=None)

    args = parser.parse_args()

    if args.info:
        tool_info = {
            "que_fa": "Permet copiar, moure, renombrar, eliminar i comprovar arxius en Windows.",
            "com_ho_fa": "Utilitza les funcions de Python (shutil, os) per manipular arxius de forma segura. Crea directoris destí automàticament si no existeixen. Inclou funció de backup amb timestamp.",
            "que_necessita": [
                {
                    "nom": "source",
                    "tipus": "string",
                    "descripcio": "Ruta absoluta de l'arxiu origen."
                },
                {
                    "nom": "destination",
                    "tipus": "string",
                    "descripcio": "Ruta absoluta de l'arxiu destí."
                },
                {
                    "nom": "path",
                    "tipus": "string",
                    "descripcio": "Ruta absoluta de l'arxiu a comprovar/eliminar."
                },
                {
                    "nom": "dest_dir",
                    "tipus": "string",
                    "descripcio": "Directori destí per backup."
                },
                {
                    "nom": "suffix",
                    "tipus": "string",
                    "descripcio": "(Opcional, per a backup) Sufix a afegir abans del timestamp."
                }
            ],
            "que_retorna": "Objecte JSON amb success (bool), message (str) i informació addicional (paths, mides, dates).",
            "funcions_disponibles": [
                {
                    "nom": "copy",
                    "descripcio": "Copia un arxiu d'origen a destí.",
                    "parametres": ["source", "destination"]
                },
                {
                    "nom": "move",
                    "descripcio": "Mou o renombra un arxiu.",
                    "parametres": ["source", "destination"]
                },
                {
                    "nom": "delete",
                    "descripcio": "Elimina un arxiu.",
                    "parametres": ["path"]
                },
                {
                    "nom": "exists",
                    "descripcio": "Comprova si un arxiu existeix i mostra informació.",
                    "parametres": ["path"]
                },
                {
                    "nom": "backup",
                    "descripcio": "Copia un arxiu afegint timestamp al nom (per backups).",
                    "parametres": ["source", "dest_dir", "suffix"]
                }
            ]
        }
        print(json.dumps(tool_info, indent=2, ensure_ascii=False))
    elif args.command == "copy":
        result = copy_file(args.source, args.destination)
        print(json.dumps(result, indent=2, ensure_ascii=False))
    elif args.command == "move":
        result = move_file(args.source, args.destination)
        print(json.dumps(result, indent=2, ensure_ascii=False))
    elif args.command == "delete":
        result = delete_file(args.path)
        print(json.dumps(result, indent=2, ensure_ascii=False))
    elif args.command == "exists":
        result = file_exists(args.path)
        print(json.dumps(result, indent=2, ensure_ascii=False))
    elif args.command == "backup":
        result = copy_with_timestamp(args.source, args.dest_dir, args.suffix)
        print(json.dumps(result, indent=2, ensure_ascii=False))
    else:
        parser.print_help()
