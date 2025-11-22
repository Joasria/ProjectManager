// ==================== UI HELPERS ====================

const UI = {
    
    // ==================== LOADING ====================
    showLoading() {
        document.getElementById('loadingOverlay').classList.add('active');
    },
    
    hideLoading() {
        document.getElementById('loadingOverlay').classList.remove('active');
    },
    
    // ==================== TOASTS ====================
    showToast(message, type = 'info', duration = 3000) {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        // Afegir icona segons tipus
        const icons = {
            success: '✅',
            error: '❌',
            info: 'ℹ️',
            warning: '⚠️'
        };
        
        toast.innerHTML = `
            <span>${icons[type] || ''}</span>
            <span>${message}</span>
        `;
        
        container.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, duration);
    },
    
    // ==================== MODALS ====================
    openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    },
    
    closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    },
    
    // ==================== VERSION INFO ====================
    updateVersionInfo() {
        document.getElementById('vActual').textContent = STATE.versionInfo.v_actual;
        document.getElementById('vClaude').textContent = STATE.versionInfo.v_claude;
        document.getElementById('vUser').textContent = STATE.versionInfo.v_user;
        
        // Actualitzar badge de canvis pendents
        const count = STATE.pendingChanges.length;
        document.getElementById('pendingCount').textContent = count;
        
        const badge = document.getElementById('changesPending');
        if (count > 0) {
            badge.textContent = `${count} nous`;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    },
    
    // ==================== STATS ====================
    updateStats() {
        let total = 0;
        let working = 0;
        let completed = 0;
        
        // Comptar recursivament
        const count = (entries) => {
            entries.forEach(entry => {
                total++;
                if (entry.status_color === 'groc') working++;
                if (entry.checked) completed++;
                if (entry.children && entry.children.length > 0) {
                    count(entry.children);
                }
            });
        };
        
        count(STATE.treeData);
        
        document.getElementById('statTotal').textContent = total;
        document.getElementById('statWorking').textContent = working;
        document.getElementById('statCompleted').textContent = completed;
    },
    
    // ==================== LAST UPDATE ====================
    updateLastUpdateTime() {
        const now = new Date();
        const timeStr = now.toLocaleTimeString('ca-ES', { 
            hour: '2-digit', 
            minute: '2-digit' 
        });
        document.getElementById('lastUpdate').textContent = `Actualitzat: ${timeStr}`;
    },
    
    // ==================== COLOR SELECTOR ====================
    setupColorSelector() {
        const container = document.getElementById('colorSelector');
        container.innerHTML = '';
        
        // DESHABILITAT: L'usuari no pot canviar colors manualment
        // Els colors es gestionen automàticament:
        // - Nova entrada → blanc
        // - Modificar entrada → blau
        // - Revisar entrada taronja → verd (via checkbox)
        
        const info = document.createElement('div');
        info.className = 'color-selector-info';
        info.innerHTML = `
            <p><strong>🎨 Els colors es gestionen automàticament:</strong></p>
            <ul>
                <li>⚪ <strong>Blanc:</strong> Nova entrada</li>
                <li>🔵 <strong>Blau:</strong> Modificada per tu (Claude revisa)</li>
                <li>🟠 <strong>Taronja:</strong> Modificada per Claude (tu revises)</li>
                <li>🟢 <strong>Verd:</strong> Consensuada i acceptada</li>
            </ul>
            <p><em>Claude pot assignar qualsevol color lliurement.</em></p>
        `;
        container.appendChild(info);
    },
    
    selectColor(color) {
        const options = document.querySelectorAll('.color-option');
        options.forEach(opt => {
            if (opt.dataset.color === color) {
                opt.classList.add('selected');
            } else {
                opt.classList.remove('selected');
            }
        });
        document.getElementById('entryColor').value = color;
    },
    
    // ==================== ENTRY MODAL ====================
    openEntryModal(parentId = null) {
        document.getElementById('modalEntryTitle').textContent = 'Nova Entrada';
        document.getElementById('formEntry').reset();
        document.getElementById('entryId').value = '';
        document.getElementById('entryParentId').value = parentId || '';
        this.selectColor('blanc');
        this.updateEntryFormFields();
        this.openModal('modalEntry');
    },
    
    openEditModal(entry) {
        document.getElementById('modalEntryTitle').textContent = 'Editar Entrada';
        document.getElementById('entryId').value = entry.id;
        document.getElementById('entryParentId').value = entry.parent_id || '';
        document.getElementById('entryType').value = entry.entry_type;
        document.getElementById('entryTitle').value = entry.title;
        document.getElementById('entryContent').value = entry.content || '';
        document.getElementById('entryUrl').value = entry.url || '';
        this.selectColor(entry.status_color || 'blanc');
        this.updateEntryFormFields();
        this.openModal('modalEntry');
    },
    
    updateEntryFormFields() {
        const type = document.getElementById('entryType').value;
        const contentGroup = document.getElementById('groupContent');
        const urlGroup = document.getElementById('groupUrl');
        
        // Mostrar/ocultar camps segons tipus
        if (type === 'link') {
            urlGroup.classList.remove('hidden');
            contentGroup.classList.remove('hidden');
        } else if (type === 'check') {
            urlGroup.classList.add('hidden');
            contentGroup.classList.add('hidden');
        } else {
            urlGroup.classList.add('hidden');
            contentGroup.classList.remove('hidden');
        }
    },
    
    // ==================== PENDING MODAL ====================
    showPendingModal() {
        const list = document.getElementById('pendingList');
        list.innerHTML = '';
        
        if (STATE.pendingChanges.length === 0) {
            list.innerHTML = '<div class="pending-list-empty">No hi ha canvis pendents 😊</div>';
        } else {
            STATE.pendingChanges.forEach(entry => {
                const item = document.createElement('div');
                item.className = 'pending-item';
                
                const icon = CONFIG.entryTypes[entry.entry_type]?.icon || '📄';
                const colorEmoji = CONFIG.statusColors[entry.status_color]?.emoji || '⚪';
                
                item.innerHTML = `
                    <div class="pending-item-header">
                        <div class="pending-item-title">
                            ${icon} ${entry.title}
                        </div>
                        <span class="tree-version new">🆕 v${entry.version}</span>
                    </div>
                    <div class="pending-item-meta">
                        ${entry.entry_type} · ${colorEmoji} ${entry.status_color}
                    </div>
                `;
                
                list.appendChild(item);
            });
        }
        
        this.openModal('modalPending');
    },
    
    // ==================== BREADCRUMBS ====================
    updateBreadcrumbs() {
        const container = document.getElementById('breadcrumbsContainer');
        if (!container) return;
        
        container.innerHTML = '';
        
        // Botó Root
        const rootBtn = document.createElement('button');
        rootBtn.className = 'breadcrumb-item';
        rootBtn.innerHTML = '🏠 Root';
        rootBtn.onclick = () => App.navigateToRoot();
        container.appendChild(rootBtn);
        
        // Breadcrumbs
        STATE.navigation.breadcrumbs.forEach((crumb, index) => {
            // Separador
            const sep = document.createElement('span');
            sep.className = 'breadcrumb-separator';
            sep.textContent = '>';
            container.appendChild(sep);
            
            // Breadcrumb
            const btn = document.createElement('button');
            btn.className = 'breadcrumb-item';
            btn.textContent = crumb.title;
            btn.onclick = () => App.navigateToBreadcrumb(index);
            container.appendChild(btn);
        });
        
        // Botó pujar nivell (si no estem a root)
        if (STATE.navigation.breadcrumbs.length > 0) {
            const upBtn = document.getElementById('btnNavigateUp');
            if (upBtn) {
                upBtn.classList.remove('hidden');
                upBtn.onclick = () => App.navigateUp();
            }
        } else {
            const upBtn = document.getElementById('btnNavigateUp');
            if (upBtn) {
                upBtn.classList.add('hidden');
            }
        }
    },
    
    // ==================== THEME ====================
    toggleTheme() {
        const currentTheme = document.body.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        document.body.setAttribute('data-theme', newTheme);
        STATE.theme = newTheme;
        StateHelpers.saveToLocalStorage();
        
        // Actualitzar icona botó
        const icon = newTheme === 'dark' ? '🌓' : '☀️';
        document.getElementById('btnTheme').textContent = icon;
    },
    
    // ==================== CONFIRM ====================
    confirm(message) {
        return window.confirm(message);
    }
};
