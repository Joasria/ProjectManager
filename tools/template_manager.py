#!/usr/bin/env python3
"""
Template Manager - Gestió de plantilles de projecte
Permet llistar i obtenir plantilles predefinides per al Project Manager
"""

import json
import os
import sys
from pathlib import Path
from typing import Dict, List, Optional

def get_templates_dir() -> Path:
    """Retorna el directori de plantilles"""
    # Assumeix que l'script està a /tools/ i les plantilles a /templates/
    script_dir = Path(__file__).parent
    templates_dir = script_dir.parent / "templates"
    
    if not templates_dir.exists():
        raise FileNotFoundError(f"Directori de plantilles no trobat: {templates_dir}")
    
    return templates_dir

def list_templates() -> List[Dict]:
    """Llista totes les plantilles disponibles amb metadata"""
    templates_dir = get_templates_dir()
    templates = []
    
    for json_file in templates_dir.glob("*.json"):
        try:
            with open(json_file, 'r', encoding='utf-8') as f:
                template_data = json.load(f)
                
                # Extraure metadata bàsica
                templates.append({
                    "filename": json_file.name,
                    "name": template_data.get("template_name", json_file.stem),
                    "version": template_data.get("template_version", "unknown"),
                    "description": template_data.get("description", ""),
                    "author": template_data.get("author", ""),
                    "tags": template_data.get("tags", []),
                    "path": str(json_file)
                })
        except Exception as e:
            print(f"Error llegint {json_file.name}: {e}", file=sys.stderr)
    
    return templates

def get_template(template_name: str) -> Optional[Dict]:
    """Obté una plantilla específica pel seu nom de fitxer o nom de plantilla"""
    templates_dir = get_templates_dir()
    
    # Intentar primer pel nom de fitxer exacte
    template_file = templates_dir / template_name
    if not template_file.suffix:
        template_file = template_file.with_suffix('.json')
    
    if template_file.exists():
        with open(template_file, 'r', encoding='utf-8') as f:
            return json.load(f)
    
    # Si no, buscar per template_name dins dels fitxers
    for json_file in templates_dir.glob("*.json"):
        try:
            with open(json_file, 'r', encoding='utf-8') as f:
                template_data = json.load(f)
                if template_data.get("template_name", "").lower() == template_name.lower():
                    return template_data
        except Exception:
            continue
    
    return None

def search_templates(query: str) -> List[Dict]:
    """Cerca plantilles que coincideixin amb la query (nom, descripció o tags)"""
    all_templates = list_templates()
    query_lower = query.lower()
    
    results = []
    for template in all_templates:
        # Buscar a nom, descripció i tags
        if (query_lower in template["name"].lower() or
            query_lower in template["description"].lower() or
            any(query_lower in tag.lower() for tag in template["tags"])):
            results.append(template)
    
    return results

def main():
    """Main CLI interface"""
    import argparse
    
    parser = argparse.ArgumentParser(description="Gestió de plantilles de projecte")
    parser.add_argument("action", choices=["list", "get", "search", "info"],
                       help="Acció a realitzar")
    parser.add_argument("--template", "-t", help="Nom de la plantilla")
    parser.add_argument("--query", "-q", help="Query de cerca")
    parser.add_argument("--format", "-f", choices=["json", "text"], default="json",
                       help="Format de sortida")
    
    args = parser.parse_args()
    
    try:
        if args.action == "list":
            templates = list_templates()
            
            if args.format == "json":
                print(json.dumps({
                    "success": True,
                    "total": len(templates),
                    "templates": templates
                }, indent=2, ensure_ascii=False))
            else:
                print(f"📁 {len(templates)} plantilles disponibles:\n")
                for t in templates:
                    print(f"  • {t['name']} (v{t['version']})")
                    print(f"    {t['description']}")
                    print(f"    Fitxer: {t['filename']}")
                    print(f"    Tags: {', '.join(t['tags'])}\n")
        
        elif args.action == "get":
            if not args.template:
                raise ValueError("Cal especificar --template")
            
            template = get_template(args.template)
            
            if template:
                if args.format == "json":
                    print(json.dumps({
                        "success": True,
                        "template": template
                    }, indent=2, ensure_ascii=False))
                else:
                    print(json.dumps(template, indent=2, ensure_ascii=False))
            else:
                print(json.dumps({
                    "success": False,
                    "error": f"Plantilla '{args.template}' no trobada"
                }, indent=2), file=sys.stderr)
                sys.exit(1)
        
        elif args.action == "search":
            if not args.query:
                raise ValueError("Cal especificar --query")
            
            results = search_templates(args.query)
            
            if args.format == "json":
                print(json.dumps({
                    "success": True,
                    "query": args.query,
                    "total": len(results),
                    "results": results
                }, indent=2, ensure_ascii=False))
            else:
                print(f"🔍 Resultats per '{args.query}': {len(results)} plantilles\n")
                for t in results:
                    print(f"  • {t['name']}")
                    print(f"    {t['description']}\n")
        
        elif args.action == "info":
            templates_dir = get_templates_dir()
            readme_path = templates_dir / "README.md"
            
            if readme_path.exists():
                with open(readme_path, 'r', encoding='utf-8') as f:
                    print(f.read())
            else:
                print("README.md no trobat", file=sys.stderr)
                sys.exit(1)
    
    except Exception as e:
        print(json.dumps({
            "success": False,
            "error": str(e)
        }, indent=2), file=sys.stderr)
        sys.exit(1)

if __name__ == "__main__":
    main()
