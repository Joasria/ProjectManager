// ==================== TREE RENDERING (NOVA ESTRUCTURA) ====================

const Tree = {
    
    // ==================== RENDER MAIN ====================
    async render() {
        // ABANS DE REGENERAR: Guardar camps amb focus (si n'hi ha)
        this.saveCurrentlyFocusedField();
        
        const container = document.getElementById('treeContainer');
        container.innerHTML = '';
        
        if (!STATE.treeData || STATE.treeData.length === 0) {
            container.className = 'tree-container empty';
            container.innerHTML = '<div>📋 No hi ha entrades. Crea la primera!</div>';
            return;
        }
        
        container.className = 'tree-container';
        
        const tree = document.createElement('ul');
        tree.className = 'tree';
        
        STATE.treeData.forEach(entry => {
            tree.appendChild(this.renderNode(entry));
        });
        
        container.appendChild(tree);
    },
    
    // ==================== SAVE CURRENTLY FOCUSED FIELD ====================
    saveCurrentlyFocusedField() {
        const activeElement = document.activeElement;
        
        // Si és un textarea de memo
        if (activeElement?.classList.contains('node-textarea')) {
            const entryId = activeElement.dataset.entryId;
            const content = activeElement.value;
            if (entryId) {
                this.saveMemoContent(entryId, content);
            }
        }
        
        // Si és un input d'URL
        if (activeElement?.classList.contains('node-url-input')) {
            const entryId = activeElement.dataset.entryId;
            const url = activeElement.value;
            if (entryId) {
                this.saveUrlContent(entryId, url);
            }
        }
        
        // Si és un input de form
        if (activeElement?.closest('.form-field')) {
            const fieldId = activeElement.closest('[data-field-id]')?.dataset.fieldId;
            const value = activeElement.value;
            if (fieldId && value !== undefined) {
                API.updateEntry(fieldId, { content: value })
                    .catch(err => console.error(`Error guardant field ${fieldId}:`, err));
            }
        }
    },
    
    // ==================== RENDER NODE (AMB DETECCIÓ COMPLEX) ====================
    renderNode(entry) {
        const li = document.createElement('li');
        li.className = 'tree-item';
        li.dataset.id = entry.id;
        
        // DETECCIÓ DE NODE COMPLEX
        if (entry.entry_type && entry.entry_type.startsWith('complex:')) {
            li.appendChild(this.renderComplexNode(entry));
            return li;
        }
        
        // Decidir si és grup o node simple
        if (entry.entry_type === 'group') {
            li.appendChild(this.renderGroupNode(entry));
        } else {
            li.appendChild(this.renderSimpleNode(entry));
        }
        
        return li;
    },
    
    // ==================== RENDER COMPLEX NODE (NOU) ====================
    renderComplexNode(entry) {
        const templateName = entry.entry_type.replace('complex:', '');
        const template = getTemplate(templateName);
        
        if (!template) {
            console.warn(`[Tree] Template no trobada: ${templateName}`);
            // Fallback a renderització normal
            return this.renderSimpleNode(entry);
        }
        
        const wrapper = document.createElement('div');
        wrapper.className = 'tree-node-wrapper complex';
        wrapper.dataset.type = entry.entry_type;
        wrapper.dataset.id = entry.id;
        wrapper.dataset.status = entry.status_color || 'blanc';
        
        // Header del complex node
        const header = this.createComplexHeader(entry, template);
        wrapper.appendChild(header);
        
        // Body amb els fills renderitzats per la template
        const body = document.createElement('div');
        body.className = 'node-body complex';
        
        const isExpanded = StateHelpers.isNodeExpanded(entry.id);
        if (isExpanded) {
            body.classList.add('expanded');
        }
        
        if (entry.children && entry.children.length > 0) {
            const renderedContent = template.render(entry, entry.children);
            body.appendChild(renderedContent);
        } else {
            // Sense fills
            const empty = document.createElement('div');
            empty.className = 'complex-empty';
            empty.textContent = 'No hi ha opcions. Afegeix fills a aquest node.';
            body.appendChild(empty);
        }
        
        wrapper.appendChild(body);
        
        // Toggle expand/collapse al click del header
        header.addEventListener('click', async (e) => {
            // No toggle si es fa click en botons d'acció
            if (e.target.closest('.node-actions') || e.target.closest('.btn-node-expand')) {
                return;
            }
            await this.toggleNodeExpansion(entry.id);
        });
        
        return wrapper;
    },
    
    // ==================== CREATE COMPLEX HEADER ====================
    createComplexHeader(entry, template) {
        const header = document.createElement('div');
        header.className = 'node-header complex';
        
        // Status dot
        const statusDot = document.createElement('div');
        statusDot.className = `status-dot ${entry.status_color || 'blanc'}`;
        header.appendChild(statusDot);
        
        // Title
        const title = document.createElement('span');
        title.className = 'node-title';
        title.innerHTML = entry.title; // Renderitzar HTML
        header.appendChild(title);
        
        // Tags (buit, per compatibilitat amb layout)
        const tags = document.createElement('div');
        tags.className = 'node-tags';
        header.appendChild(tags);
        
        // Version badge (si és nou)
        if (StateHelpers.isNewEntry(entry)) {
            const versionBadge = document.createElement('span');
            versionBadge.className = 'tree-version new';
            versionBadge.textContent = `🆕 v${entry.version}`;
            versionBadge.title = 'Nova entrada no revisada';
            header.appendChild(versionBadge);
        }
        
        // Botó expand/collapse
        const btnExpand = document.createElement('button');
        btnExpand.className = 'btn-node-expand';
        const isExpanded = StateHelpers.isNodeExpanded(entry.id);
        btnExpand.textContent = isExpanded ? '▼' : '▶';
        if (!isExpanded) {
            btnExpand.classList.add('collapsed');
        }
        btnExpand.addEventListener('click', async (e) => {
            e.stopPropagation();
            await this.toggleNodeExpansion(entry.id);
        });
        header.appendChild(btnExpand);
        
        // Actions
        const actions = document.createElement('div');
        actions.className = 'node-actions';
        
        // Botó editar
        const btnEdit = this.createActionButton('✏️', 'Editar', () => {
            UI.openEditModal(entry);
        });
        actions.appendChild(btnEdit);
        
        // Botó afegir fill
        const btnAddChild = this.createActionButton('➕', 'Afegir opció', () => {
            UI.openAddModal(entry.id);
        });
        actions.appendChild(btnAddChild);
        
        // Botó canviar estat
        const btnStatus = this.createActionButton('🎨', 'Canviar estat', (e) => {
            this.showStatusPicker(entry.id, e);
        });
        actions.appendChild(btnStatus);
        
        // Botó eliminar
        const btnDelete = this.createActionButton('🗑️', 'Eliminar', () => {
            this.handleDelete(entry.id, entry.title);
        });
        actions.appendChild(btnDelete);
        
        header.appendChild(actions);
        
        return header;
    },
    
    // ==================== RENDER GROUP NODE (carpeta amb navegació) ====================
    renderGroupNode(entry) {
        const node = document.createElement('div');
        node.className = 'tree-node';
        node.dataset.id = entry.id;
        node.dataset.status = entry.status_color || 'blanc';
        
        // Botó navegació [->]
        const navBtn = document.createElement('button');
        navBtn.className = 'tree-nav-btn';
        navBtn.textContent = '→';
        navBtn.title = 'Navegar dins';
        navBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            App.navigateInto(entry.id, entry.title);
        });
        node.appendChild(navBtn);
        
        // Icon carpeta
        const icon = document.createElement('span');
        icon.className = 'tree-icon';
        icon.textContent = '📁';
        node.appendChild(icon);
        
        // Title
        const title = document.createElement('span');
        title.className = 'tree-title';
        title.innerHTML = entry.title; // Renderitzar HTML
        node.appendChild(title);
        
        // Version badge (si és nou)
        if (StateHelpers.isNewEntry(entry)) {
            const versionBadge = document.createElement('span');
            versionBadge.className = 'tree-version new';
            versionBadge.textContent = `🆕 v${entry.version}`;
            versionBadge.title = 'Nova entrada no revisada';
            node.appendChild(versionBadge);
        }
        
        // Actions
        const actions = document.createElement('div');
        actions.className = 'tree-actions';
        
        // Botó editar
        const btnEdit = this.createActionButton('✏️', 'Editar', () => {
            UI.openEditModal(entry);
        });
        actions.appendChild(btnEdit);
        
        // Botó canviar estat
        const btnStatus = this.createActionButton('🎨', 'Canviar estat', (e) => {
            this.showStatusPicker(entry.id, e);
        });
        actions.appendChild(btnStatus);
        
        // Botó eliminar
        const btnDelete = this.createActionButton('🗑️', 'Eliminar', () => {
            this.handleDelete(entry.id, entry.title);
        });
        actions.appendChild(btnDelete);
        
        node.appendChild(actions);
        
        return node;
    },
    
    // ==================== RENDER SIMPLE NODE (nou format) ====================
    renderSimpleNode(entry) {
        const wrapper = document.createElement('div');
        wrapper.className = 'tree-node-wrapper';
        wrapper.dataset.id = entry.id;
        wrapper.dataset.type = entry.entry_type;  // Color de fons segons type
        
        // CAPÇALERA (sempre visible)
        const header = this.renderNodeHeader(entry);
        wrapper.appendChild(header);
        
        // BODY (desplegable)
        const body = this.renderNodeBody(entry);
        wrapper.appendChild(body);
        
        return wrapper;
    },
    
    // ==================== RENDER NODE HEADER ====================
    renderNodeHeader(entry) {
        const header = document.createElement('div');
        header.className = 'node-header';
        
        // 1. Status dot (rodona de color)
        const statusDot = document.createElement('div');
        statusDot.className = `status-dot ${entry.status_color || 'blanc'}`;
        header.appendChild(statusDot);
        
        // 2. Checkbox (si entry_type === 'check')
        if (entry.entry_type === 'check') {
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'tree-checkbox';
            checkbox.checked = entry.checked || false;
            checkbox.addEventListener('click', async (e) => {
                e.stopPropagation();
                await this.toggleCheck(entry.id);
            });
            header.appendChild(checkbox);
        }
        
        // 3. Títol
        const title = document.createElement('div');
        title.className = 'node-title';
        if (entry.checked) {
            title.classList.add('completed');
        }
        title.innerHTML = entry.title; // Renderitzar HTML
        header.appendChild(title);
        
        // 4. Tags (futur - ara buit)
        const tags = document.createElement('div');
        tags.className = 'node-tags';
        // TODO: Renderitzar tags del context_data
        header.appendChild(tags);
        
        // 5. Version badge (si és nou)
        if (StateHelpers.isNewEntry(entry)) {
            const versionBadge = document.createElement('span');
            versionBadge.className = 'tree-version new';
            versionBadge.textContent = `🆕 v${entry.version}`;
            versionBadge.title = 'Nova entrada no revisada';
            header.appendChild(versionBadge);
        }
        
        // 5b. Checkbox de revisió (si status és TARONJA)
        if (entry.status_color === 'taronja') {
            const reviewLabel = document.createElement('label');
            reviewLabel.className = 'review-checkbox';
            
            const reviewCheckbox = document.createElement('input');
            reviewCheckbox.type = 'checkbox';
            reviewCheckbox.className = 'review-check';
            reviewCheckbox.addEventListener('change', async (e) => {
                e.stopPropagation();
                if (e.target.checked) {
                    await this.markAsReviewed(entry.id);
                }
            });
            
            const reviewText = document.createElement('span');
            reviewText.textContent = '✅ He revisat i accepto';
            
            reviewLabel.appendChild(reviewCheckbox);
            reviewLabel.appendChild(reviewText);
            header.appendChild(reviewLabel);
        }
        
        // 6. Botó expand/collapse
        const btnExpand = document.createElement('button');
        btnExpand.className = 'btn-node-expand';
        const isExpanded = StateHelpers.isNodeExpanded(entry.id);
        btnExpand.textContent = isExpanded ? '▼' : '▶';
        if (!isExpanded) {
            btnExpand.classList.add('collapsed');
        }
        btnExpand.addEventListener('click', async (e) => {
            e.stopPropagation();
            await this.toggleNodeExpansion(entry.id);
        });
        header.appendChild(btnExpand);
        
        // 7. Actions (✏️ 🎨 🗑️)
        const actions = document.createElement('div');
        actions.className = 'node-actions';
        
        // Botó editar
        const btnEdit = this.createActionButton('✏️', 'Editar', () => {
            UI.openEditModal(entry);
        });
        actions.appendChild(btnEdit);
        
        // Botó canviar estat
        const btnStatus = this.createActionButton('🎨', 'Canviar estat', (e) => {
            this.showStatusPicker(entry.id, e);
        });
        actions.appendChild(btnStatus);
        
        // Botó eliminar
        const btnDelete = this.createActionButton('🗑️', 'Eliminar', () => {
            this.handleDelete(entry.id, entry.title);
        });
        actions.appendChild(btnDelete);
        
        header.appendChild(actions);
        
        return header;
    },
    
    // ==================== MARK AS REVIEWED (NOU) ====================
    async markAsReviewed(entryId) {
        try {
            UI.showLoading();
            const response = await API.toggleUserReviewed(entryId);
            
            if (response.success) {
                UI.showToast('✅ Marcat com a revisat i acceptat → VERD', 'success');
                await App.loadTree(); // Recarregar arbre
            }
        } catch (error) {
            UI.showToast('Error: ' + error.message, 'error');
        } finally {
            UI.hideLoading();
        }
    },
    
    // ==================== RENDER NODE BODY ====================
    renderNodeBody(entry) {
        const body = document.createElement('div');
        body.className = 'node-body';
        
        const isExpanded = StateHelpers.isNodeExpanded(entry.id);
        if (isExpanded) {
            body.classList.add('expanded');
        }
        
        // Secció MEMO
        const memoSection = this.renderMemoSection(entry);
        body.appendChild(memoSection);
        
        // Secció URL
        const urlSection = this.renderUrlSection(entry);
        body.appendChild(urlSection);
        
        return body;
    },
    
    // ==================== RENDER MEMO SECTION ====================
    renderMemoSection(entry) {
        const section = document.createElement('div');
        section.className = 'node-section';
        
        // Header
        const header = document.createElement('div');
        header.className = 'node-section-header';
        header.innerHTML = '<span class="node-section-icon">📄</span> MEMO';
        section.appendChild(header);
        
        // Content
        const content = document.createElement('div');
        content.className = 'node-section-content';
        
        if (entry.content && entry.content.trim() !== '') {
            // Té contingut → mostrar textarea editable
            const textarea = document.createElement('textarea');
            textarea.className = 'node-textarea';
            textarea.value = entry.content;
            textarea.dataset.entryId = entry.id;
            
            // NOMÉS onBlur - sense timers
            textarea.addEventListener('blur', () => {
                this.saveMemoContent(entry.id, textarea.value);
            });
            
            content.appendChild(textarea);
        } else {
            // No té contingut → mostrar botó afegir
            const empty = document.createElement('div');
            empty.className = 'node-section-empty';
            empty.innerHTML = '<span class="node-section-empty-icon">➕</span> Afegir memo';
            empty.addEventListener('click', () => {
                this.showMemoEditor(entry.id, section);
            });
            content.appendChild(empty);
        }
        
        section.appendChild(content);
        return section;
    },
    
    // ==================== RENDER URL SECTION ====================
    renderUrlSection(entry) {
        const section = document.createElement('div');
        section.className = 'node-section';
        
        // Header
        const header = document.createElement('div');
        header.className = 'node-section-header';
        header.innerHTML = '<span class="node-section-icon">🔗</span> URL';
        section.appendChild(header);
        
        // Content
        const content = document.createElement('div');
        content.className = 'node-section-content';
        
        if (entry.url && entry.url.trim() !== '') {
            // Té URL → mostrar input editable
            const input = document.createElement('input');
            input.type = 'url';
            input.className = 'node-url-input';
            input.value = entry.url;
            input.dataset.entryId = entry.id;
            
            // NOMÉS onBlur - sense timers
            input.addEventListener('blur', () => {
                this.saveUrlContent(entry.id, input.value);
            });
            
            content.appendChild(input);
        } else {
            // No té URL → mostrar botó afegir
            const empty = document.createElement('div');
            empty.className = 'node-section-empty';
            empty.innerHTML = '<span class="node-section-empty-icon">➕</span> Afegir URL';
            empty.addEventListener('click', () => {
                this.showUrlEditor(entry.id, section);
            });
            content.appendChild(empty);
        }
        
        section.appendChild(content);
        return section;
    },
    
    // ==================== SHOW MEMO EDITOR ====================
    showMemoEditor(entryId, section) {
        // Trobar el content div
        const contentDiv = section.querySelector('.node-section-content');
        contentDiv.innerHTML = '';
        
        // Crear textarea
        const textarea = document.createElement('textarea');
        textarea.className = 'node-textarea';
        textarea.placeholder = 'Escriu el memo aquí...';
        textarea.dataset.entryId = entryId;
        textarea.focus();
        
        // ONÉS onBlur - sense timers
        textarea.addEventListener('blur', () => {
            if (textarea.value.trim() === '') {
                App.loadTree(); // Tornar a mostrar botó afegir
            } else {
                this.saveMemoContent(entryId, textarea.value);
            }
        });
        
        contentDiv.appendChild(textarea);
    },
    
    // ==================== SHOW URL EDITOR ====================
    showUrlEditor(entryId, section) {
        // Trobar el content div
        const contentDiv = section.querySelector('.node-section-content');
        contentDiv.innerHTML = '';
        
        // Crear input
        const input = document.createElement('input');
        input.type = 'url';
        input.className = 'node-url-input';
        input.placeholder = 'https://...';
        input.dataset.entryId = entryId;
        input.focus();
        
        // NOMÉS onBlur - sense timers
        input.addEventListener('blur', () => {
            if (input.value.trim() === '') {
                App.loadTree(); // Tornar a mostrar botó afegir
            } else {
                this.saveUrlContent(entryId, input.value);
            }
        });
        
        contentDiv.appendChild(input);
    },
    
    // ==================== SAVE MEMO CONTENT ====================
    async saveMemoContent(entryId, content) {
        // Comprovar si ha canviat respecte a STATE
        const entry = STATE.flatEntries.get(entryId);
        if (entry && content === entry.content) {
            console.log(`[Tree] Memo ${entryId} sense canvis, skip`);
            return; // No cal guardar
        }
        
        try {
            const textarea = document.querySelector(`textarea[data-entry-id="${entryId}"]`);
            if (textarea) {
                textarea.classList.add('saving');
            }
            
            await API.updateEntry(entryId, { content: content });
            
            if (textarea) {
                textarea.classList.remove('saving');
            }
            
            // ✅ CRÍTIC: Actualitzar STATE immediatament
            if (entry) {
                entry.content = content;
            }
            
            console.log(`[Tree] Memo ${entryId} guardat correctament`);
            
        } catch (error) {
            console.error('[Tree] Error desant memo:', error);
            UI.showToast('Error desant memo: ' + error.message, 'error');
        }
    },
    
    // ==================== SAVE URL CONTENT ====================
    async saveUrlContent(entryId, url) {
        // Comprovar si ha canviat respecte a STATE
        const entry = STATE.flatEntries.get(entryId);
        if (entry && url === entry.url) {
            console.log(`[Tree] URL ${entryId} sense canvis, skip`);
            return; // No cal guardar
        }
        
        try {
            await API.updateEntry(entryId, { url: url });
            
            // ✅ CRÍTIC: Actualitzar STATE immediatament
            if (entry) {
                entry.url = url;
            }
            
            console.log(`[Tree] URL ${entryId} guardada correctament`);
            
        } catch (error) {
            console.error('[Tree] Error desant URL:', error);
            UI.showToast('Error desant URL: ' + error.message, 'error');
        }
    },
    
    // ==================== TOGGLE NODE EXPANSION ====================
    async toggleNodeExpansion(entryId) {
        StateHelpers.toggleNodeExpanded(entryId);
        await this.render();
    },
    
    // ==================== ACTION BUTTON ====================
    createActionButton(icon, title, onClick) {
        const btn = document.createElement('button');
        btn.className = 'node-action-btn';
        btn.textContent = icon;
        btn.title = title;
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            onClick(e);
        });
        return btn;
    },
    
    // ==================== STATUS PICKER ====================
    showStatusPicker(entryId, event) {
        const colors = Object.keys(CONFIG.statusColors);
        const colorLabels = colors.map(c => `${CONFIG.statusColors[c].emoji} ${c}`).join('\n');
        const choice = prompt(`Selecciona color:\n\n${colorLabels}\n\nEscriu el nom:`);
        
        if (choice && colors.includes(choice.toLowerCase())) {
            App.changeStatus(entryId, choice.toLowerCase());
        }
    },
    
    // ==================== DELETE ====================
    async handleDelete(entryId, title) {
        if (!UI.confirm(`Segur que vols eliminar "${title}"?\n\nS'eliminaran també tots els seus fills.`)) {
            return;
        }
        
        try {
            UI.showLoading();
            await API.deleteEntry(entryId);
            UI.showToast('Entrada eliminada correctament', 'success');
            await App.loadTree();
        } catch (error) {
            UI.showToast('Error eliminant entrada: ' + error.message, 'error');
        } finally {
            UI.hideLoading();
        }
    },
    
    // ==================== TOGGLE CHECK ====================
    async toggleCheck(entryId) {
        try {
            await API.toggleCompleted(entryId);
            await App.loadTree();
        } catch (error) {
            console.error('[Tree] ErrorToggleCheck:', error);
            UI.showToast('Error canviant estat del check: ' + error.message, 'error');
        }
    },
    
    // ==================== EXPAND/COLLAPSE ALL ====================
    async expandAll() {
        StateHelpers.expandAllNodes();
        await this.render();
    },
    
    async collapseAll() {
        StateHelpers.collapseAllNodes();
        await this.render();
    }
};
