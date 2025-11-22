// ==================== API MODULE ====================
const API = {
    // ==================== HELPERS ====================
    async _call(endpoint, params = {}, method = 'GET', body = null) {
        const url = new URL(`${CONFIG.baseUrl}${endpoint}`);
        
        // Afegir query parameters
        Object.keys(params).forEach(key => {
            if (params[key] !== null && params[key] !== undefined) {
                url.searchParams.append(key, params[key]);
            }
        });
        
        const options = {
            method,
            headers: {
                'Content-Type': 'application/json'
            }
        };
        
        if (body && method !== 'GET') {
            options.body = JSON.stringify(body);
        }
        
        try {
            console.log(`[API] ${method} ${endpoint}`, params, body);
            
            const response = await fetch(url, options);
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || `HTTP ${response.status}`);
            }
            
            if (data.error && !data.success) {
                throw new Error(data.error);
            }
            
            console.log(`[API] Response:`, data);
            return data;
            
        } catch (error) {
            console.error('[API ERROR]', error);
            throw error;
        }
    },
    
    // ==================== PROJECTS ====================
    async listProjects() {
        const sql = `SELECT id, name, description, created_at, updated_at FROM projects ORDER BY id ASC`;
        const response = await this._call('table_editor.php', {
            action: 'execute_sql',
            config: 'project_manager',
            sql: encodeURIComponent(sql)
        });
        
        // Format: {success, query_results: [...]}
        return response.query_results || [];
    },
    
    async getProjectInfo() {
        const sql = `SELECT * FROM projects WHERE id = ${CONFIG.projectId}`;
        const response = await this._call('table_editor.php', {
            action: 'execute_sql',
            config: 'project_manager',
            sql: encodeURIComponent(sql)
        });
        return response;
    },
    
    async createProject(name, description = '') {
        console.log('[API] Creant projecte:', name, description);
        
        return await this._call('create_project.php', 
            {},
            'POST',
            { name, description }
        );
    },
    
    
    // ==================== VERSION ENDPOINTS ====================
    async startSession(actor = 'user') {
        const sessionId = `web_${Date.now()}`;
        return await this._call('project_manager.php', 
            { action: 'start_session', project_id: CONFIG.projectId },
            'POST',
            { actor, session_id: sessionId }
        );
    },
    
    async getPending(actor = 'user') {
        return await this._call('project_manager.php', {
            action: 'get_pending',
            project_id: CONFIG.projectId,
            actor
        });
    },
    
    async markReviewed(actor = 'user') {
        return await this._call('project_manager.php',
            { action: 'mark_reviewed', project_id: CONFIG.projectId },
            'POST',
            { actor }
        );
    },
    
    // ==================== TREE & ENTRIES ====================
    
    // Nou: Obtenir nivell específic
    async getLevel(parentId = null) {
        const params = {
            project_id: CONFIG.projectId,
            config: 'project_manager'
        };
        
        if (parentId !== null && parentId !== undefined) {
            params.parent_id = parentId;
        }
        
        return await this._call('pm_get_level.php', params);
    },
    
    async getTree(filters = {}) {
        const params = {
            project_id: CONFIG.projectId,
            format: 'tree'  // Jeràrquic amb children
        };
        
        // Afegir filtres opcionals
        if (filters.search) params.search = filters.search;
        if (filters.status) params.status = filters.status;
        if (filters.showNewOnly) params.show_new_only = 'true';
        
        return await this._call('api_get_tree.php', params);
    },
    
    async addEntry(data) {
        return await this._call('project_manager.php',
            { action: 'add_entry', project_id: CONFIG.projectId },
            'POST',
            data
        );
    },
    
    async updateEntry(entryId, data) {
        return await this._call('project_manager.php',
            { action: 'update_entry', project_id: CONFIG.projectId, entry_id: entryId },
            'POST',
            data
        );
    },
    
    async deleteEntry(entryId) {
        return await this._call('project_manager.php', {
            action: 'delete_entry',
            project_id: CONFIG.projectId,
            entry_id: entryId
        });
    },
    
    async getEntry(entryId) {
        return await this._call('project_manager.php', {
            action: 'get_entry',
            project_id: CONFIG.projectId,
            entry_id: entryId
        });
    },
    
    async getSiblings(parentId = null) {
        const params = {
            action: 'get_siblings',
            project_id: CONFIG.projectId
        };
        
        if (parentId !== null) {
            params.parent_id = parentId;
        }
        
        return await this._call('project_manager.php', params);
    },
    
    // ==================== ACTIONS ====================
    async toggleCompleted(entryId) {
        return await this._call('project_manager.php', {
            action: 'toggle_completed',
            project_id: CONFIG.projectId,
            entry_id: entryId
        });
    },
    
    async changeStatus(entryId, statusColor) {
        return await this._call('project_manager.php',
            { action: 'change_status', project_id: CONFIG.projectId, entry_id: entryId },
            'POST',
            { status_color: statusColor }
        );
    },
    
    async moveEntry(entryId, newParentId) {
        return await this._call('project_manager.php',
            { action: 'move_entry', project_id: CONFIG.projectId, entry_id: entryId },
            'POST',
            { new_parent_id: newParentId }
        );
    },
    
    async searchEntries(query) {
        return await this._call('project_manager.php', {
            action: 'search',
            project_id: CONFIG.projectId,
            query
        });
    },
    
    async getWorkingItems() {
        return await this._call('project_manager.php', {
            action: 'get_working_items',
            project_id: CONFIG.projectId
        });
    },
    
    // ==================== USER REVIEW ====================
    async toggleUserReviewed(entryId) {
        return await this._call('project_manager.php', {
            action: 'toggle_user_reviewed',
            project_id: CONFIG.projectId,
            entry_id: entryId
        });
    }
};
