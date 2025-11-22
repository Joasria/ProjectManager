// ==================== APPLICATION MAIN ====================

const App = {
    
    // ==================== INITIALIZATION ====================
    async init() {
        console.log('[APP] Inicialitzant Project Manager Dashboard v2.1...');
        
        try {
            // Setup UI
            this.setupUI();
            
            // Carregar llista de projectes
            await this.loadProjects();
            
            // Setup event listeners
            this.setupEventListeners();
            
            // Carregar dades inicials del projecte actual
            await this.loadInitialData();
            
            // Renderitzar
            Tree.render();
            UI.updateStats();
            UI.updateLastUpdateTime();
            
            console.log('[APP] Dashboard inicialitzat correctament ✅');
            UI.showToast('Dashboard carregat correctament', 'success');
            
        } catch (error) {
            console.error('[APP] Error inicialitzant:', error);
            UI.showToast('Error carregant dashboard: ' + error.message, 'error');
        }
    },
    
    // ==================== LOAD PROJECTS ====================
    async loadProjects() {
        try {
            const projects = await API.listProjects();
            const select = document.getElementById('projectSelect');
            select.innerHTML = '';
            
            if (projects.length === 0) {
                select.innerHTML = '<option value="">Cap projecte disponible</option>';
                return;
            }
            
            projects.forEach(project => {
                const option = document.createElement('option');
                option.value = project.id.toString().padStart(3, '0');
                option.textContent = `${option.value} - ${project.name}`;
                if (option.value === CONFIG.projectId) {
                    option.selected = true;
                }
                select.appendChild(option);
            });
            
        } catch (error) {
            console.error('[APP] Error carregant projectes:', error);
            UI.showToast('Error carregant projectes', 'error');
        }
    },
    
    // ==================== CHANGE PROJECT ====================
    async changeProject(projectId) {
        if (projectId === CONFIG.projectId) return;
        
        try {
            UI.showLoading();
            
            // Canviar projecte
            CONFIG.setProject(projectId);
            
            // Recarregar dades i resetejar navegació
            STATE.treeData = [];
            STATE.flatEntries = new Map();
            STATE.pendingChanges = [];
            StateHelpers.navigateToRoot();
            
            await this.loadInitialData();
            Tree.render();
            
            UI.showToast(`Projecte canviat a ${projectId}`, 'success');
            
        } catch (error) {
            UI.showToast('Error canviant projecte: ' + error.message, 'error');
        } finally {
            UI.hideLoading();
        }
    },
    
    // ==================== SETUP UI ====================
    setupUI() {
        // Aplicar tema guardat
        document.body.setAttribute('data-theme', STATE.theme);
        const themeIcon = STATE.theme === 'dark' ? '🌓' : '☀️';
        document.getElementById('btnTheme').textContent = themeIcon;
        
        // Setup color selector al modal
        UI.setupColorSelector();
    },
    
    // ==================== LOAD INITIAL DATA ====================
    async loadInitialData() {
        UI.showLoading();
        
        try {
            // Carregar info del projecte
            const projectInfo = await API.getProjectInfo();
            if (projectInfo.query_results && projectInfo.query_results.length > 0) {
                STATE.projectData = projectInfo.query_results[0];
                StateHelpers.updateVersionInfo(STATE.projectData);
                UI.updateVersionInfo();
            }
            
            // Carregar arbre
            await this.loadTree();
            
            // Comprovar canvis pendents
            await this.checkPendingChanges();
            
        } finally {
            UI.hideLoading();
        }
    },
    
    // ==================== LOAD TREE ====================
    async loadTree(filters = {}) {
        try {
            // IMPORTANT: Utilitzar getTree() per obtenir fills recursivament
            // això és necessari per nodes complexos que depenen dels children
            const response = await API.getTree(filters);
            
            if (response.success && response.data) {
                // Si estem navegant dins d'un node, filtrar per mostrar només els fills
                const parentId = STATE.navigation.currentParentId;
                if (parentId) {
                    // Trobar el node pare i mostrar només els seus fills
                    STATE.treeData = this.findNodeChildren(response.data, parentId);
                } else {
                    // Root level: mostrar tots els nodes de primer nivell
                    STATE.treeData = response.data;
                }
                
                // Construir mapa pla per accés ràpid
                STATE.flatEntries = StateHelpers.buildFlatMap(response.data);
                
                Tree.render();
                UI.updateStats();
                UI.updateLastUpdateTime();
                UI.updateBreadcrumbs();
            }
        } catch (error) {
            console.error('[APP] Error carregant arbre:', error);
            UI.showToast('Error carregant dades: ' + error.message, 'error');
        }
    },
    
    // ==================== FIND NODE CHILDREN (NOU) ====================
    findNodeChildren(nodes, parentId) {
        for (const node of nodes) {
            if (node.id == parentId) {
                return node.children || [];
            }
            if (node.children && node.children.length > 0) {
                const found = this.findNodeChildren(node.children, parentId);
                if (found) return found;
            }
        }
        return [];
    },
    
    // ==================== NAVEGACIÓ ====================
    async navigateInto(entryId, entryTitle) {
        StateHelpers.navigateInto(entryId, entryTitle);
        await this.loadTree();
    },
    
    async navigateUp() {
        StateHelpers.navigateUp();
        await this.loadTree();
    },
    
    async navigateToRoot() {
        StateHelpers.navigateToRoot();
        await this.loadTree();
    },
    
    async navigateToBreadcrumb(index) {
        StateHelpers.navigateToBreadcrumb(index);
        await this.loadTree();
    },
    
    // ==================== CHECK PENDING CHANGES ====================
    async checkPendingChanges() {
        try {
            const response = await API.getPending('user');
            
            if (response.success) {
                STATE.pendingChanges = response.entries || [];
                STATE.versionInfo.v_user = response.v_actor;
                STATE.versionInfo.v_actual = response.v_actual;
                
                UI.updateVersionInfo();
            }
        } catch (error) {
            console.error('[APP] Error comprovant canvis:', error);
        }
    },
    
    // ==================== EVENT LISTENERS ====================
    setupEventListeners() {
        // Project selector
        document.getElementById('projectSelect').addEventListener('change', (e) => {
            this.changeProject(e.target.value);
        });
        
        // New project button
        document.getElementById('btnNewProject').addEventListener('click', () => this.handleNewProject());
        
        // Header buttons
        document.getElementById('btnStartSession').addEventListener('click', () => this.handleStartSession());
        document.getElementById('btnViewPending').addEventListener('click', () => UI.showPendingModal());
        document.getElementById('btnTheme').addEventListener('click', () => UI.toggleTheme());
        document.getElementById('btnRefresh').addEventListener('click', () => this.handleRefresh());
        
        // Sidebar buttons
        document.getElementById('btnExpandAll').addEventListener('click', () => Tree.expandAll());
        document.getElementById('btnCollapseAll').addEventListener('click', () => Tree.collapseAll());
        
        // Breadcrumbs add entry button
        document.getElementById('btnAddEntry').addEventListener('click', () => {
            // Passar el parent_id actual de la navegació
            const parentId = STATE.navigation.currentParentId;
            UI.openEntryModal(parentId);
        });
        
        // Search
        document.getElementById('searchInput').addEventListener('input', (e) => this.handleSearch(e.target.value));
        
        // Filters
        document.getElementById('filterStatus').addEventListener('change', () => this.applyFilters());
        document.getElementById('filterType').addEventListener('change', () => this.applyFilters());
        document.getElementById('filterNewOnly').addEventListener('change', () => this.applyFilters());
        
        // Entry form
        document.getElementById('formEntry').addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleSaveEntry();
        });
        document.getElementById('entryType').addEventListener('change', () => UI.updateEntryFormFields());
        
        // Pending modal
        document.getElementById('btnMarkReviewed').addEventListener('click', () => this.handleMarkReviewed());
        
        // Modal close buttons
        document.getElementById('btnCloseEntry')?.addEventListener('click', () => UI.closeModal('modalEntry'));
        document.getElementById('btnCancelEntry')?.addEventListener('click', () => UI.closeModal('modalEntry'));
        document.getElementById('btnClosePending')?.addEventListener('click', () => UI.closeModal('modalPending'));
        document.getElementById('btnCancelPending')?.addEventListener('click', () => UI.closeModal('modalPending'));
        
        // Modal overlays
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                const modal = e.target.closest('.modal');
                if (modal) {
                    UI.closeModal(modal.id);
                }
            });
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => this.handleKeyboard(e));
    },
    
    // ==================== HANDLERS ====================
    
    async handleNewProject() {
        const projectName = prompt('Nom del nou projecte:');
        if (!projectName || projectName.trim() === '') {
            return;
        }
        
        const projectDescription = prompt('Descripció del projecte (opcional):') || '';
        
        try {
            UI.showLoading();
            const response = await API.createProject(projectName.trim(), projectDescription.trim());
            
            if (response.success) {
                UI.showToast(`Projecte "${projectName}" creat correctament! Refrescant...`, 'success');
                
                // Esperar 2 segons abans de refrescar
                await new Promise(resolve => setTimeout(resolve, 2000));
                
                // Recarregar llista de projectes
                await this.loadProjects();
                
                // Canviar al nou projecte si té ID
                if (response.project_id_padded) {
                    const newProjectId = response.project_id_padded;
                    document.getElementById('projectSelect').value = newProjectId;
                    await this.changeProject(newProjectId);
                } else if (response.project_id) {
                    const newProjectId = response.project_id.toString().padStart(3, '0');
                    document.getElementById('projectSelect').value = newProjectId;
                    await this.changeProject(newProjectId);
                }
            }
        } catch (error) {
            UI.showToast('Error creant projecte: ' + error.message, 'error');
        } finally {
            UI.hideLoading();
        }
    },
    
    async handleStartSession() {
        try {
            UI.showLoading();
            const response = await API.startSession('user');
            
            if (response.success) {
                UI.showToast(`Sessió iniciada! v${response.v_actual}`, 'success');
                await this.loadInitialData();
                Tree.render();
            }
        } catch (error) {
            UI.showToast('Error iniciant sessió: ' + error.message, 'error');
        } finally {
            UI.hideLoading();
        }
    },
    
    async handleRefresh() {
        await this.loadInitialData();
        Tree.render();
        UI.showToast('Dades actualitzades', 'info');
    },
    
    handleSearch(query) {
        STATE.filters.search = query.toLowerCase();
        this.applyFilters();
    },
    
    async applyFilters() {
        const filters = {
            search: document.getElementById('searchInput').value,
            status: document.getElementById('filterStatus').value,
            showNewOnly: document.getElementById('filterNewOnly').checked
        };
        
        // Filtrar per tipus es fa al client
        const typeFilter = document.getElementById('filterType').value;
        
        // Carregar amb filtres de servidor
        await this.loadTree(filters);
        
        // Aplicar filtre de tipus al client si cal
        if (typeFilter) {
            STATE.treeData = this.filterByType(STATE.treeData, typeFilter);
            Tree.render();
        }
    },
    
    filterByType(entries, type) {
        return entries.filter(entry => {
            const matches = entry.entry_type === type;
            if (entry.children && entry.children.length > 0) {
                entry.children = this.filterByType(entry.children, type);
            }
            return matches || (entry.children && entry.children.length > 0);
        });
    },
    
    async handleSaveEntry() {
        const entryId = document.getElementById('entryId').value;
        const isEdit = !!entryId;
        
        const data = {
            entry_type: document.getElementById('entryType').value,
            title: document.getElementById('entryTitle').value,
            content: document.getElementById('entryContent').value,
            url: document.getElementById('entryUrl').value
        };
        
        // AUTOMATISME DE COLORS:
        // - Si és nova entrada → 'blanc' (per defecte al backend)
        // - Si és edició → 'blau' (User ha modificat, Claude ha de revisar)
        if (isEdit) {
            data.status_color = 'blau';
        } else {
            // Nova entrada: deixar que backend assigni 'blanc'
            const parentId = document.getElementById('entryParentId').value;
            data.parent_id = parentId || null;
            // No especificar status_color per que sigui 'blanc' per defecte
        }
        
        try {
            UI.showLoading();
            
            if (isEdit) {
                await API.updateEntry(entryId, data);
                UI.showToast('Entrada actualitzada → Estat: BLAU (pendent revisió Claude)', 'success');
            } else {
                await API.addEntry(data);
                UI.showToast('Entrada creada', 'success');
            }
            
            UI.closeModal('modalEntry');
            await this.loadTree();
            
        } catch (error) {
            UI.showToast('Error desant entrada: ' + error.message, 'error');
        } finally {
            UI.hideLoading();
        }
    },
    
    async handleMarkReviewed() {
        try {
            UI.showLoading();
            const response = await API.markReviewed('user');
            
            if (response.success) {
                UI.showToast('Tots els canvis marcats com revisats ✅', 'success');
                STATE.pendingChanges = [];
                UI.updateVersionInfo();
                UI.closeModal('modalPending');
                await this.checkPendingChanges();
                Tree.render();
            }
        } catch (error) {
            UI.showToast('Error marcant revisat: ' + error.message, 'error');
        } finally {
            UI.hideLoading();
        }
    },
    
    async changeStatus(entryId, color) {
        try {
            await API.changeStatus(entryId, color);
            UI.showToast(`Estat canviat a ${color}`, 'success');
            await this.loadTree();
        } catch (error) {
            UI.showToast('Error canviant estat: ' + error.message, 'error');
        }
    },
    
    handleKeyboard(e) {
        // Ctrl/Cmd + K = Focus search
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            document.getElementById('searchInput').focus();
        }
        
        // Ctrl/Cmd + N = Nova entrada
        if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
            e.preventDefault();
            const parentId = STATE.navigation.currentParentId;
            UI.openEntryModal(parentId);
        }
        
        // Escape = Tancar modals
        if (e.key === 'Escape') {
            UI.closeModal('modalEntry');
            UI.closeModal('modalPending');
        }
    }
};

// ==================== INIT ON LOAD ====================
document.addEventListener('DOMContentLoaded', () => {
    App.init();
});
