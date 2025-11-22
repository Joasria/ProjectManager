// ==================== CONFIGURATION ====================

const CONFIG = {
    // URL base de l'API
    baseUrl: 'https://www.contratemps.org/claudetools/',
    
    // Project ID (es canviarà dinàmicament)
    projectId: localStorage.getItem('pm_project_id') || '001',
    
    // Entry types
    entryTypes: {
        group: {
            icon: '📁',
            label: 'Grup',
            allowsContent: true,
            allowsUrl: false,
            allowsChildren: true
        },
        memo: {
            icon: '📝',
            label: 'Memo',
            allowsContent: true,
            allowsUrl: false,
            allowsChildren: false
        },
        check: {
            icon: '☑️',
            label: 'Check',
            allowsContent: false,
            allowsUrl: false,
            allowsChildren: false
        },
        link: {
            icon: '🔗',
            label: 'Link',
            allowsContent: true,
            allowsUrl: true,
            allowsChildren: false
        }
    },
    
    // Status colors
    statusColors: {
        blanc: { emoji: '⚪', label: 'Blanc - Nou/sense processar', color: '#ffffff' },
        groc: { emoji: '🟡', label: 'Groc - Treballant activament', color: '#ffd700' },
        gris: { emoji: '🔘', label: 'Gris - Processat però inactiu', color: '#808080' },
        vermell: { emoji: '🔴', label: 'Vermell - Erroni/bloquejat', color: '#f44336' },
        blau: { emoji: '🔵', label: 'Blau - User ha validat proposta Claude', color: '#2196f3' },
        taronja: { emoji: '🟠', label: 'Taronja - Claude ha incorporat actualització user', color: '#ff9800' },
        verd: { emoji: '🟢', label: 'Verd - Completat/consensuat', color: '#4caf50' }
    },
    
    // Funció per canviar projecte
    setProject(projectId) {
        this.projectId = projectId;
        localStorage.setItem('pm_project_id', projectId);
        console.log('[CONFIG] Project changed to:', projectId);
    }
};
