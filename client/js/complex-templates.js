/**
 * COMPLEX TEMPLATES - Registry i Templates Base
 * Sistema de templates flexibles per nodes complexos
 */

const COMPLEX_TEMPLATES = {};

/**
 * Registrar una nova template
 */
function registerTemplate(name, config) {
    COMPLEX_TEMPLATES[name] = {
        name: config.name,
        icon: config.icon || '📋',
        description: config.description || '',
        // Copiar totes les funcions del config per mantenir el context
        ...config,
        // Helpers compartits
        createHeader: createComplexHeader,
        createWrapper: createComplexWrapper
    };
}

/**
 * Obtenir template per nom
 */
function getTemplate(name) {
    return COMPLEX_TEMPLATES[name] || COMPLEX_TEMPLATES['default'];
}

/**
 * Crear header estàndard per complex nodes
 */
function createComplexHeader(entry, template) {
    const header = document.createElement('div');
    header.className = 'complex-header';
    
    const icon = document.createElement('span');
    icon.className = 'template-icon';
    icon.textContent = template.icon;
    
    const title = document.createElement('span');
    title.className = 'node-title';
    title.innerHTML = entry.title; // Renderitzar HTML
    
    const badge = document.createElement('span');
    badge.className = 'template-badge';
    badge.textContent = template.name;
    
    // Status indicator
    if (entry.status_color && entry.status_color !== 'blanc') {
        const status = document.createElement('span');
        status.className = `status-indicator status-${entry.status_color}`;
        header.appendChild(status);
    }
    
    header.appendChild(icon);
    header.appendChild(title);
    header.appendChild(badge);
    
    return header;
}

/**
 * Crear wrapper estàndard
 */
function createComplexWrapper(entry, templateClass) {
    const wrapper = document.createElement('div');
    wrapper.className = `complex-form ${templateClass}`;
    wrapper.dataset.formId = entry.id;
    wrapper.dataset.templateType = entry.entry_type;
    return wrapper;
}

/**
 * Helper per parsejar context_data de forma segura
 * L'API pot retornar tant objecte com string JSON
 */
function parseContextData(contextData) {
    if (!contextData) return {};
    if (typeof contextData === 'object') return contextData;
    try {
        return JSON.parse(contextData);
    } catch (e) {
        console.warn('[parseContextData] Error parsing:', contextData, e);
        return {};
    }
}

// ============================================
// TEMPLATE 1: SELECTOR (Radio buttons)
// ============================================

registerTemplate('selector', {
    name: 'Selector',
    icon: '○',
    description: 'Selecció única entre opcions (radio buttons)',
    
    render: function(parentNode, children) {
        const form = createComplexWrapper(parentNode, 'selector-form');
        
        // Crear grup de radios
        const radioGroup = document.createElement('div');
        radioGroup.className = 'radio-group';
        radioGroup.dataset.name = `selector-${parentNode.id}`;
        
        children.forEach((child, index) => {
            const optionWrapper = document.createElement('div');
            optionWrapper.className = 'radio-option';
            
            const radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = `selector-${parentNode.id}`;
            radio.id = `option-${child.id}`;
            radio.value = child.id;
            radio.dataset.childId = child.id;
            
            // ✅ CORRECCIÓ: Marcar si està seleccionat (checked té valor 1)
            if (child.checked === 1 || child.checked === '1' || child.checked === true) {
                radio.checked = true;
            }
            
            const label = document.createElement('label');
            label.htmlFor = `option-${child.id}`;
            label.innerHTML = child.title; // Renderitzar HTML
            
            // Event per actualitzar - GUARDAT IMMEDIAT
            radio.addEventListener('change', async (e) => {
                if (e.target.checked) {
                    await this.handleSelectorChange(parentNode.id, child.id);
                }
            });
            
            optionWrapper.appendChild(radio);
            optionWrapper.appendChild(label);
            radioGroup.appendChild(optionWrapper);
        });
        
        form.appendChild(radioGroup);
        
        // Mostrar selecció actual si n'hi ha
        // ✅ CORRECCIÓ: Usar checked en lloc de is_completed
        const selected = children.find(c => c.checked === 1 || c.checked === '1' || c.checked === true);
        if (selected) {
            const selectedInfo = document.createElement('div');
            selectedInfo.className = 'selected-info';
            selectedInfo.innerHTML = `<strong>Seleccionat:</strong> ${selected.title}`;
            form.appendChild(selectedInfo);
        }
        
        return form;
    },
    
    handleSelectorChange: async function(parentId, selectedChildId) {
        console.log(`Selector ${parentId}: Opció ${selectedChildId} seleccionada`);
        
        try {
            // Obtenir tots els fills des de la BD
            const response = await API.getSiblings(parentId);
            const allChildren = response.siblings || [];
            
            console.log(`[Selector] Fills obtinguts de BD:`, allChildren);
            
            // Actualitzar cada fill
            for (const child of allChildren) {
                const shouldBeChecked = (child.id == selectedChildId);
                const currentlyChecked = (child.checked === 1 || child.checked === '1' || child.checked === true);
                
                if (currentlyChecked !== shouldBeChecked) {
                    console.log(`[Selector] Actualitzant ${child.id}: ${currentlyChecked} → ${shouldBeChecked}`);
                    await API.updateEntry(child.id, { 
                        checked: shouldBeChecked ? 1 : 0 
                    });
                    
                    // ✅ CRÍTIC: Actualitzar STATE immediatament
                    const entry = STATE.flatEntries.get(child.id);
                    if (entry) {
                        entry.checked = shouldBeChecked ? 1 : 0;
                    }
                }
            }
            
            console.log(`✅ Selector ${parentId} desat correctament`);
            if (window.UI) {
                UI.showToast('Selecció desada correctament', 'success');
            }
            
        } catch (error) {
            console.error('[handleSelectorChange] Error:', error);
            if (window.UI) {
                UI.showToast('Error desant selecció: ' + error.message, 'error');
            }
        }
    }
});

// ============================================
// TEMPLATE 2: CHECK (Checkboxes múltiples)
// ============================================

registerTemplate('check', {
    name: 'Check',
    icon: '☑',
    description: 'Selecció múltiple entre opcions (checkboxes)',
    
    render: function(parentNode, children) {
        const form = createComplexWrapper(parentNode, 'check-form');
        
        // Crear grup de checkboxes
        const checkGroup = document.createElement('div');
        checkGroup.className = 'check-group';
        
        children.forEach((child, index) => {
            const optionWrapper = document.createElement('div');
            optionWrapper.className = 'check-option';
            
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.id = `option-${child.id}`;
            checkbox.value = child.id;
            checkbox.dataset.childId = child.id;
            
            // ✅ CORRECCIÓ: Marcar si està seleccionat (checked té valor 1)
            if (child.checked === 1 || child.checked === '1' || child.checked === true) {
                checkbox.checked = true;
            }
            
            const label = document.createElement('label');
            label.htmlFor = `option-${child.id}`;
            label.innerHTML = child.title; // Renderitzar HTML
            
            // Event per actualitzar - GUARDAT IMMEDIAT
            checkbox.addEventListener('change', async (e) => {
                await this.handleCheckChange(parentNode.id, child.id, e.target.checked);
            });
            
            optionWrapper.appendChild(checkbox);
            optionWrapper.appendChild(label);
            checkGroup.appendChild(optionWrapper);
        });
        
        form.appendChild(checkGroup);
        
        // Mostrar resum seleccionats
        // ✅ CORRECCIÓ: Usar checked en lloc de is_completed
        const selectedCount = children.filter(c => c.checked === 1 || c.checked === '1' || c.checked === true).length;
        const summary = document.createElement('div');
        summary.className = 'check-summary';
        summary.textContent = `${selectedCount} de ${children.length} seleccionats`;
        form.appendChild(summary);
        
        return form;
    },
    
    handleCheckChange: async function(parentId, childId, isChecked) {
        try {
            // Actualitzar BD amb valor concret (NO toggle)
            await API.updateEntry(childId, { checked: isChecked ? 1 : 0 });
            
            // ✅ CRÍTIC: Actualitzar STATE immediatament
            const entry = STATE.flatEntries.get(parseInt(childId));
            if (entry) {
                entry.checked = isChecked ? 1 : 0;
            }
            
            // Actualitzar resum visual
            const checkForm = document.querySelector(`[data-form-id="${parentId}"]`);
            if (checkForm) {
                const summary = checkForm.querySelector('.check-summary');
                if (summary) {
                    const checkboxes = checkForm.querySelectorAll('input[type="checkbox"]');
                    const total = checkboxes.length;
                    const checked = Array.from(checkboxes).filter(cb => cb.checked).length;
                    summary.textContent = `${checked} de ${total} seleccionats`;
                }
            }
            
            console.log(`✅ Check ${childId} desat correctament`);
            
        } catch (error) {
            console.error('[handleCheckChange] Error:', error);
            if (window.UI) {
                UI.showToast('Error desant checkbox: ' + error.message, 'error');
            }
        }
    }
});

// ============================================
// TEMPLATE 3: FORM (Formulari genèric)
// ============================================

registerTemplate('form', {
    name: 'Form',
    icon: '📋',
    description: 'Formulari genèric amb camps variats',
    
    render: function(parentNode, children) {
        const form = createComplexWrapper(parentNode, 'generic-form');
        
        // Renderitzar cada fill segons el seu tipus
        children.forEach(child => {
            const component = this.renderComponent(child);
            if (component) {
                form.appendChild(component);
            }
        });
        
        return form;
    },
    
    renderComponent: function(child) {
        switch(child.entry_type) {
            case 'field':
                return this.renderField(child);
            case 'select':
                return this.renderSelect(child);
            case 'action':
                return this.renderAction(child);
            case 'separator':
                return this.renderSeparator(child);
            case 'info':
                return this.renderInfo(child);
            default:
                return this.renderGeneric(child);
        }
    },
    
    renderField: function(child) {
        const wrapper = document.createElement('div');
        wrapper.className = 'form-field';
        wrapper.dataset.fieldId = child.id;
        
        const label = document.createElement('label');
        label.innerHTML = child.title; // Renderitzar HTML
        label.htmlFor = `field-${child.id}`;
        
        const contextData = parseContextData(child.context_data);
        const fieldType = contextData.field_type || 'text';
        
        let input;
        if (fieldType === 'textarea') {
            input = document.createElement('textarea');
            input.rows = 4;
        } else {
            input = document.createElement('input');
            input.type = fieldType;
        }
        
        input.id = `field-${child.id}`;
        input.value = child.content || '';
        input.placeholder = contextData.placeholder || '';
        
        if (contextData.required) {
            input.required = true;
            label.classList.add('required');
        }
        
        // NOMÉS onBlur - sense timers, actualitzant STATE
        input.addEventListener('blur', async () => {
            try {
                await API.updateEntry(child.id, { content: input.value });
                
                // ✅ CRÍTIC: Actualitzar STATE immediatament
                const entry = STATE.flatEntries.get(parseInt(child.id));
                if (entry) {
                    entry.content = input.value;
                }
                
                console.log(`✅ Field ${child.id} desat: ${input.value}`);
            } catch (error) {
                console.error(`❌ Error desant field ${child.id}:`, error);
                if (window.UI) {
                    UI.showToast('Error desant camp: ' + error.message, 'error');
                }
            }
        });
        
        wrapper.appendChild(label);
        wrapper.appendChild(input);
        
        return wrapper;
    },
    
    renderSelect: function(child) {
        const wrapper = document.createElement('div');
        wrapper.className = 'form-field';
        
        const label = document.createElement('label');
        label.innerHTML = child.title; // Renderitzar HTML
        label.htmlFor = `select-${child.id}`;
        
        const select = document.createElement('select');
        select.id = `select-${child.id}`;
        
        // Parse opcions de content: "opcio1|opcio2|opcio3"
        const options = (child.content || '').split('|').filter(o => o.trim());
        const contextData = parseContextData(child.context_data);
        
        // Opció buida si no és required
        if (!contextData.required) {
            const emptyOption = document.createElement('option');
            emptyOption.value = '';
            emptyOption.textContent = '-- Selecciona --';
            select.appendChild(emptyOption);
        }
        
        options.forEach(opt => {
            const option = document.createElement('option');
            option.value = opt.trim();
            option.textContent = opt.trim();
            if (opt.trim() === contextData.selected) {
                option.selected = true;
            }
            select.appendChild(option);
        });
        
        // onChange immediat, actualitzant STATE
        select.addEventListener('change', async (e) => {
            try {
                const newContextData = { ...contextData, selected: e.target.value };
                await API.updateEntry(child.id, { context_data: newContextData });
                
                // ✅ CRÍTIC: Actualitzar STATE immediatament
                const entry = STATE.flatEntries.get(child.id);
                if (entry) {
                    entry.context_data = newContextData;
                }
                
                console.log(`✅ Select ${child.id} desat: ${e.target.value}`);
            } catch (error) {
                console.error(`❌ Error desant select ${child.id}:`, error);
                if (window.UI) {
                    UI.showToast('Error desant selecció: ' + error.message, 'error');
                }
            }
        });
        
        wrapper.appendChild(label);
        wrapper.appendChild(select);
        
        return wrapper;
    },
    
    renderAction: function(child) {
        const contextData = parseContextData(child.context_data);
        
        const button = document.createElement('button');
        button.className = `form-action ${contextData.style || 'primary'}`;
        button.innerHTML = child.title; // Renderitzar HTML
        button.type = contextData.action_type === 'submit' ? 'submit' : 'button';
        
        button.addEventListener('click', (e) => {
            e.preventDefault();
            this.handleAction(child);
        });
        
        return button;
    },
    
    renderSeparator: function(child) {
        const sep = document.createElement('div');
        sep.className = 'form-separator';
        if (child.title) {
            sep.textContent = child.title;
            sep.classList.add('with-title');
        }
        return sep;
    },
    
    renderInfo: function(child) {
        const info = document.createElement('div');
        info.className = 'form-info';
        info.textContent = child.content || child.title;
        
        const contextData = parseContextData(child.context_data);
        if (contextData.style) {
            info.classList.add(`info-${contextData.style}`);
        }
        
        return info;
    },
    
    renderGeneric: function(child) {
        const div = document.createElement('div');
        div.className = 'form-generic';
        div.innerHTML = `<strong>${child.entry_type}:</strong> ${child.title}`;
        return div;
    },
    
    handleAction: function(child) {
        const contextData = parseContextData(child.context_data);
        const actionType = contextData.action_type || 'custom';
        console.log(`Action triggered:`, actionType, child);
        
        if (contextData.confirm) {
            if (!confirm(contextData.confirm)) {
                return;
            }
        }
        
        // TODO: Implementar accions (submit, reset, etc.)
        if (window.UI) {
            UI.showToast('Acció executada: ' + child.title, 'info');
        }
    }
});

// ============================================
// TEMPLATE DEFAULT (Fallback)
// ============================================

registerTemplate('default', {
    name: 'Unknown',
    icon: '❓',
    description: 'Template per defecte per tipus desconegut',
    
    render: function(parentNode, children) {
        const form = createComplexWrapper(parentNode, 'default-form');
        
        const warning = document.createElement('div');
        warning.className = 'template-warning';
        warning.innerHTML = `
            <strong>⚠️ Template no trobada:</strong> ${parentNode.entry_type}<br>
            <small>Els fills es mostren en format genèric</small>
        `;
        form.appendChild(warning);
        
        const list = document.createElement('ul');
        children.forEach(child => {
            const li = document.createElement('li');
            li.textContent = `${child.entry_type}: ${child.title}`;
            list.appendChild(li);
        });
        form.appendChild(list);
        
        return form;
    }
});

console.log('✅ Complex Templates carregat. Templates disponibles:', Object.keys(COMPLEX_TEMPLATES));
