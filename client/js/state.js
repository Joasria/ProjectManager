// ==================== ESTAT GLOBAL ====================
const STATE = {
    // Data
    projectData: null,
    treeData: [],
    flatEntries: new Map(), // id -> entry (per accés ràpid)
    
    // NAVEGACIÓ PER NIVELLS
    navigation: {
        currentParentId: null,      // null = root level
        breadcrumbs: [],             // [{id, title}, ...]
        history: []                  // Per back/forward
    },
    
    // Versions
    versionInfo: {
        v_actual: 0,
        v_claude: 0,
        v_user: 0
    },
    pendingChanges: [],
    
    // UI State
    selectedEntryId: null,
    expandedNodes: new Set(),       // Per compounds (inline expand)
    theme: localStorage.getItem('pm_theme') || 'dark',
    
    // Filters
    filters: {
        search: '',
        status: '',
        type: '',
        showOnlyNew: false
    },
    
    // History (per undo/redo)
    history: [],
    historyIndex: -1,
    
    // Timers
    autoSaveTimer: null,
    refreshTimer: null
};

// ==================== STATE HELPERS ====================
const StateHelpers = {
    // ========== NAVEGACIÓ ==========
    
    // Navegar dins d'un grup
    navigateInto(entryId, entryTitle) {
        const entry = this.getEntry(entryId);
        if (!entry) return;
        
        // Afegir a breadcrumbs
        STATE.navigation.breadcrumbs.push({
            id: entryId,
            title: entryTitle || entry.title
        });
        
        // Actualitzar parent actual
        STATE.navigation.currentParentId = entryId;
        
        // Guardar en history
        STATE.navigation.history.push(entryId);
        
        this.saveNavigationState();
    },
    
    // Pujar un nivell
    navigateUp() {
        if (STATE.navigation.breadcrumbs.length === 0) return;
        
        // Treure últim breadcrumb
        STATE.navigation.breadcrumbs.pop();
        
        // Actualitzar parent (si queden breadcrumbs, l'últim, sinó null=root)
        if (STATE.navigation.breadcrumbs.length > 0) {
            const last = STATE.navigation.breadcrumbs[STATE.navigation.breadcrumbs.length - 1];
            STATE.navigation.currentParentId = last.id;
        } else {
            STATE.navigation.currentParentId = null;
        }
        
        this.saveNavigationState();
    },
    
    // Navegar a root
    navigateToRoot() {
        STATE.navigation.breadcrumbs = [];
        STATE.navigation.currentParentId = null;
        this.saveNavigationState();
    },
    
    // Navegar a breadcrumb específic
    navigateToBreadcrumb(index) {
        // Tallar breadcrumbs fins l'index
        STATE.navigation.breadcrumbs = STATE.navigation.breadcrumbs.slice(0, index + 1);
        
        // Actualitzar parent
        if (index >= 0) {
            STATE.navigation.currentParentId = STATE.navigation.breadcrumbs[index].id;
        } else {
            STATE.navigation.currentParentId = null;
        }
        
        this.saveNavigationState();
    },
    
    // Guardar estat navegació
    saveNavigationState() {
        try {
            localStorage.setItem('pm_navigation', JSON.stringify({
                currentParentId: STATE.navigation.currentParentId,
                breadcrumbs: STATE.navigation.breadcrumbs
            }));
        } catch (e) {
            console.warn('Error saving navigation:', e);
        }
    },
    
    // Carregar estat navegació
    loadNavigationState() {
        try {
            const saved = localStorage.getItem('pm_navigation');
            if (saved) {
                const nav = JSON.parse(saved);
                STATE.navigation.currentParentId = nav.currentParentId;
                STATE.navigation.breadcrumbs = nav.breadcrumbs || [];
            }
        } catch (e) {
            console.warn('Error loading navigation:', e);
        }
    },
    
    // ========== VERSIONS ==========
    
    updateVersionInfo(data) {
        STATE.versionInfo = {
            v_actual: data.v_actual || 0,
            v_claude: data.v_claude || 0,
            v_user: data.v_user || 0
        };
    },
    
    // ========== PERSISTÈNCIA ==========
    
    saveToLocalStorage() {
        try {
            localStorage.setItem('pm_theme', STATE.theme);
            localStorage.setItem('pm_expanded', JSON.stringify([...STATE.expandedNodes]));
        } catch (e) {
            console.warn('Error saving to localStorage:', e);
        }
    },
    
    loadFromLocalStorage() {
        try {
            const expanded = localStorage.getItem('pm_expanded');
            if (expanded) {
                STATE.expandedNodes = new Set(JSON.parse(expanded));
            }
        } catch (e) {
            console.warn('Error loading from localStorage:', e);
        }
        
        // Carregar navegació
        this.loadNavigationState();
    },
    
    // ========== FLAT MAP ==========
    
    buildFlatMap(entries, map = new Map()) {
        entries.forEach(entry => {
            map.set(parseInt(entry.id), entry);
            if (entry.children && entry.children.length > 0) {
                this.buildFlatMap(entry.children, map);
            }
        });
        return map;
    },
    
    getEntry(id) {
        return STATE.flatEntries.get(parseInt(id));
    },
    
    // ========== EXPANDED (per compounds inline) ==========
    
    isExpanded(id) {
        return STATE.expandedNodes.has(parseInt(id));
    },
    
    toggleExpanded(id) {
        const numId = parseInt(id);
        if (STATE.expandedNodes.has(numId)) {
            STATE.expandedNodes.delete(numId);
        } else {
            STATE.expandedNodes.add(numId);
        }
        this.saveToLocalStorage();
    },
    
    expandAll(entries = STATE.treeData) {
        entries.forEach(entry => {
            if (entry.children && entry.children.length > 0) {
                STATE.expandedNodes.add(parseInt(entry.id));
                this.expandAll(entry.children);
            }
        });
        this.saveToLocalStorage();
    },
    
    collapseAll() {
        STATE.expandedNodes.clear();
        this.saveToLocalStorage();
    },
    
    // ========== NODES EXPANDITS (nova estructura) ==========
    
    isNodeExpanded(id) {
        return STATE.expandedNodes.has(parseInt(id));
    },
    
    toggleNodeExpanded(id) {
        const numId = parseInt(id);
        if (STATE.expandedNodes.has(numId)) {
            STATE.expandedNodes.delete(numId);
        } else {
            STATE.expandedNodes.add(numId);
        }
        this.saveToLocalStorage();
    },
    
    expandAllNodes() {
        // Expandir tots els nodes simples
        STATE.treeData.forEach(entry => {
            if (entry.entry_type !== 'group') {
                STATE.expandedNodes.add(parseInt(entry.id));
            }
        });
        this.saveToLocalStorage();
    },
    
    collapseAllNodes() {
        STATE.expandedNodes.clear();
        this.saveToLocalStorage();
    },
    
    // ========== CANVIS ==========
    
    countNewChanges() {
        return STATE.pendingChanges.length;
    },
    
    isNewEntry(entry) {
        return parseInt(entry.version) > STATE.versionInfo.v_user;
    }
};

// Carregar estat inicial
StateHelpers.loadFromLocalStorage();
