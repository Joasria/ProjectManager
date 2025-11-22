# Changelog - Project Manager

## [v2.1] - 2025-01-22

### 🎨 **Optimització CSS**
- Unificat estils nodes normals i complex
- Eliminades 91 línies de codi duplicat
- Suprimitús de `!important` i overrides
- Millor expansió de títols amb `margin-left: auto`
- CSS més mantenible i consistent

**Arxius modificats:**
- `client/css/components.css`
- `client/css/complex-nodes.css`
- `client/js/tree.js`

---

### 💾 **Sistema de Guardat Simplificat**

#### Eliminat
- ❌ Tots els timers (800ms, 1500ms)
- ❌ Lògica complexa de `saveAllPendingChanges()`
- ❌ Variables `saveTimers` i `typingTimer`

#### Nou Sistema
- ✅ **onBlur** per textareas/inputs (guardat quan perds focus)
- ✅ **onChange immediat** per radios/checkboxes
- ✅ `saveCurrentlyFocusedField()` abans de cada render
- ✅ Event `beforeunload` per guardar abans de tancar finestra

**Avantatges:**
- Codi més simple i mantenible
- No es perden dades mai
- Guardat natural i previsible
- Sense race conditions

**Arxius modificats:**
- `client/js/tree.js`
- `client/js/complex-templates.js`
- `client/js/app.js`

---

### 🔄 **Sincronització STATE ↔ BD**

**Problema resolt:** 
Abans les dades es guardaven a BD però no s'actualitzava `STATE.flatEntries`, causant que en minimitzar/maximitzar nodes es mostressin dades antigues del cache.

**Solució implementada:**
Actualitzar `STATE.flatEntries.get(id)` immediatament després de cada guardat a BD.

**Aplicat a:**
- Selectors (radios) - `handleSelectorChange()`
- Checkboxes - `handleCheckChange()`
- Form fields - inputs i textareas
- Form selects - dropdowns
- URLs i memos

---

### 🐛 **Bug Crític: flatMap Sobreescrit**

**Problema:**
`buildFlatMap()` es cridava 4 vegades, sobreescrivint entries. Els fills dels complex nodes NO estaven a `STATE.flatEntries`.

**Solució:**
Cridar `buildFlatMap(response.data)` només UNA vegada amb tot l'arbre complet.

**Arxius modificats:**
- `client/js/app.js`
- `client/js/state.js`

---

## Testing Realitzat

✅ Radios/Checkboxes/Forms - Guardat immediat + STATE sincronitzat  
✅ Minimitzar/maximitzar nodes - Dades persisteixen  
✅ Tancar finestra - Camp amb focus es guarda  
✅ flatMap - Tots els fills disponibles  

---

## Migració des de v2.0

1. Actualitzar fitxers JavaScript del client
2. Netejar cache del navegador (Ctrl+Shift+R)
3. Recarregar pàgina
