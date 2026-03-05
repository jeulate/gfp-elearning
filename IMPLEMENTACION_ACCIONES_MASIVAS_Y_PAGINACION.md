# 📦 Implementación de Acciones Masivas y Paginación en Bitácora

**Fecha**: 4 de marzo de 2026  
**Archivos modificados**: 
- `class-fplms-structures.php`
- `class-fplms-audit-admin.php`

---

## 📋 Resumen de Cambios

### 1. **Acciones Masivas en Estructuras**
- Dropdown de selección de acciones (Activar, Desactivar, Eliminar)
- Modal de confirmación para cada acción
- Registro automático en bitácora
- Headers no-cache para actualización inmediata

### 2. **Paginación en Bitácora de Auditoría**
- Selector de registros por página: 10, 20, 50, 100
- Mantiene filtros y paginación en navegación
- Interfaz mejorada

---

## 🎯 Parte 1: Acciones Masivas

### 🎨 Interfaz de Usuario

#### **Dropdown de Acciones Masivas**

Se agrega automáticamente cuando hay checkboxes seleccionados en cualquier tabla de estructuras (Ciudades, Empresas, Canales, Sucursales, Cargos).

**Ubicación**: Encima de los botones de exportación

**Aspecto visual**:
```
┌─────────────────────────────────────────────────────────────┐
│ 🏢 Canales: 3 seleccionados                                 │
│ [-- Acciones masivas --▼] [Aplicar]                        │
└─────────────────────────────────────────────────────────────┘
```

**Opciones del dropdown**:
- ❌ Desactivar seleccionados
- ✅ Activar seleccionados
- 🗑️ Eliminar seleccionados

---

### 🎨 CSS Agregado

**Archivo**: `class-fplms-structures.php` (líneas 702-754)

```css
/* ACCIONES MASIVAS */
.fplms-bulk-actions-container {
    display: none;
    align-items: center;
    gap: 10px;
    padding: 10px;
    background: #fff3cd;
    border: 1px solid #ffc107;
    border-radius: 5px;
    margin-bottom: 10px;
}

.fplms-bulk-actions-container.visible {
    display: flex;
}

.fplms-bulk-select {
    padding: 6px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 13px;
    color: #333;
    background: white;
}

.fplms-bulk-apply-btn {
    background: #0073aa !important;
    color: white !important;
    border: none !important;
    padding: 6px 15px !important;
    border-radius: 4px !important;
    cursor: pointer !important;
    font-size: 13px !important;
    transition: all 0.2s ease !important;
}

.fplms-bulk-apply-btn:hover {
    background: #005a87 !important;
}

.fplms-bulk-apply-btn:disabled {
    background: #ccc !important;
    cursor: not-allowed !important;
}
```

**Características**:
- Fondo amarillo suave (#fff3cd) para destacar
- Borde amarillo (#ffc107) para alerta visual
- Botón azul (#0073aa) consistente con WordPress
- Animaciones suaves con transiciones CSS

---

### 📱 HTML Agregado

**Archivo**: `class-fplms-structures.php` (líneas 903-920)

```html
<!-- Acciones masivas -->
<div class="fplms-bulk-actions-container" id="fplms-bulk-actions-<?php echo esc_attr( $tab_key ); ?>">
    <strong><?php echo esc_html( $tab_info['label'] ); ?>:</strong>
    <span id="fplms-bulk-count-<?php echo esc_attr( $tab_key ); ?>">0 seleccionados</span>
    <select id="fplms-bulk-action-<?php echo esc_attr( $tab_key ); ?>" class="fplms-bulk-select">
        <option value="">-- Acciones masivas --</option>
        <option value="deactivate">❌ Desactivar seleccionados</option>
        <option value="activate">✅ Activar seleccionados</option>
        <option value="delete">🗑️ Eliminar seleccionados</option>
    </select>
    <button type="button" 
            class="button fplms-bulk-apply-btn" 
            onclick="fplmsApplyBulkAction('<?php echo esc_attr( $tab_key ); ?>', '<?php echo esc_attr( $tab_info['taxonomy'] ); ?>')">
        Aplicar
    </button>
</div>
```

**Elementos dinámicos**:
- `fplms-bulk-actions-{tab}`: Contenedor único por pestaña
- `fplms-bulk-count-{tab}`: Contador de elementos seleccionados
- `fplms-bulk-action-{tab}`: Dropdown de acciones

---

### 🎭 Modal de Confirmación

**Archivo**: `class-fplms-structures.php` (líneas 1451-1478)

```html
<!-- Modal de Confirmación de Acciones Masivas -->
<div id="fplms-bulk-modal" class="fplms-modal" style="display:none;">
    <div class="fplms-modal-content" style="max-width: 500px;">
        <div class="fplms-modal-header">
            <h3 id="fplms-bulk-modal-title">⚠️ Confirmar Acción Masiva</h3>
            <button class="fplms-modal-close" onclick="fplmsCloseBulkModal()">✕</button>
        </div>
        <div class="fplms-modal-body">
            <p id="fplms-bulk-modal-question">¿Estás seguro de que deseas realizar esta acción?</p>
            
            <!-- Info de la acción -->
            <div style="background: #fff3cd; padding: 12px; border-radius: 4px; border-left: 3px solid #ffc107; margin: 12px 0;">
                <p id="fplms_bulk_action_text"></p>
                <p id="fplms_bulk_elements"></p>
            </div>
            
            <!-- Advertencia para eliminación -->
            <div id="fplms-bulk-delete-warning" style="display: none; background: #ffebee; padding: 12px; border-radius: 4px; border-left: 3px solid #d32f2f; margin: 12px 0;">
                <p style="margin: 0; color: #c62828; font-weight: 600;">⚠️ ADVERTENCIA: Esta acción es IRREVERSIBLE</p>
                <p style="margin: 4px 0 0 0; color: #c62828; font-size: 13px;">Los elementos y sus relaciones se eliminarán permanentemente.</p>
            </div>
            
            <p style="color: #666; font-size: 12px; margin-bottom: 0;">Esta acción se registrará en la bitácora del sistema.</p>
        </div>
        <div class="fplms-modal-footer">
            <button type="button" class="button" onclick="fplmsCloseBulkModal()">Cancelar</button>
            <button type="button" class="button button-primary" id="fplms-bulk-confirm-btn" onclick="fplmsConfirmBulkAction()">✓ Confirmar</button>
        </div>
    </div>
</div>
```

**Estados dinámicos del modal**:

#### 1. **Activar seleccionados**:
- Título: "✅ Activar Elementos"
- Texto: "Activar 3 elementos"
- Color botón: Verde (#4caf50)
- Sin advertencia especial

#### 2. **Desactivar seleccionados**:
- Título: "❌ Desactivar Elementos"
- Texto: "Desactivar 3 elementos"
- Color botón: Naranja (#ff9800)
- Sin advertencia especial

#### 3. **Eliminar seleccionados**:
- Título: "🗑️ Eliminar Elementos"
- Texto: "Eliminar 3 elementos"
- Color botón: Rojo (#d32f2f)
- ⚠️ Advertencia ROJA visible: "Esta acción es IRREVERSIBLE"

**Lista de elementos**:
- Muestra hasta 10 nombres
- Si hay más de 10: "Elemento1, Elemento2, ... y 5 más"

---

### 💻 JavaScript Implementado

#### **Función 1: `fplmsUpdateExportButton(tabKey)`**

**Ubicación**: Líneas 3204-3240

**Nueva funcionalidad**:
```javascript
function fplmsUpdateExportButton(tabKey) {
    const table = document.getElementById('fplms-table-' + tabKey);
    const checkboxes = table.querySelectorAll('.fplms-row-checkbox:checked');
    const exportBtn = document.getElementById('fplms-export-selected-' + tabKey);
    const checkAll = document.getElementById('fplms-check-all-' + tabKey);
    
    // NUEVO: Acciones masivas
    const bulkContainer = document.getElementById('fplms-bulk-actions-' + tabKey);
    const bulkCount = document.getElementById('fplms-bulk-count-' + tabKey);
    
    if (checkboxes.length > 0) {
        exportBtn.style.display = 'inline-block';
        exportBtn.textContent = '✓ Exportar Seleccionados (' + checkboxes.length + ')';
        
        // Mostrar acciones masivas
        if (bulkContainer) {
            bulkContainer.classList.add('visible');
        }
        if (bulkCount) {
            bulkCount.textContent = checkboxes.length + ' seleccionado' + (checkboxes.length > 1 ? 's' : '');
        }
    } else {
        exportBtn.style.display = 'none';
        
        // Ocultar acciones masivas
        if (bulkContainer) {
            bulkContainer.classList.remove('visible');
        }
    }
    
    // Actualizar checkbox "Todos" (código existente)
    ...
}
```

**Cambios**:
- ✅ Muestra/oculta contenedor de acciones masivas
- ✅ Actualiza contador de elementos seleccionados
- ✅ Se ejecuta cada vez que cambia un checkbox

---

#### **Función 2: `fplmsApplyBulkAction(tabKey, taxonomy)`**

**Ubicación**: Líneas 2547-2641

```javascript
function fplmsApplyBulkAction(tabKey, taxonomy) {
    const select = document.getElementById('fplms-bulk-action-' + tabKey);
    const action = select.value;
    
    if (!action) {
        alert('Por favor, selecciona una acción del menú.');
        return;
    }
    
    // Obtener checkboxes marcados
    const table = document.getElementById('fplms-table-' + tabKey);
    const checkboxes = Array.from(table.querySelectorAll('.fplms-row-checkbox:checked'));
    
    if (checkboxes.length === 0) {
        alert('Por favor, selecciona al menos un elemento.');
        return;
    }
    
    // Obtener IDs y nombres
    const termIds = checkboxes.map(cb => cb.getAttribute('data-term-id'));
    const termNames = checkboxes.map(cb => {
        const row = cb.closest('tr');
        const nameCell = row.querySelector('td:nth-child(2) strong');
        return nameCell ? nameCell.textContent : '';
    });
    
    // Guardar datos para el modal
    bulkActionData = {
        action: action,
        termIds: termIds,
        termNames: termNames,
        taxonomy: taxonomy,
        tab: tabKey
    };
    
    // Configurar modal según la acción
    let actionLabel = '';
    let questionText = '';
    let btnColor = '';
    
    if (action === 'delete') {
        modalTitle.textContent = '🗑️ Eliminar Elementos';
        actionLabel = 'Eliminar ' + termIds.length + ' elemento' + (termIds.length > 1 ? 's' : '');
        questionText = '¿Estás seguro de que deseas ELIMINAR estos elementos?';
        btnColor = '#d32f2f'; // Rojo
        deleteWarning.style.display = 'block'; // Mostrar advertencia
    } else if (action === 'deactivate') {
        modalTitle.textContent = '❌ Desactivar Elementos';
        actionLabel = 'Desactivar ' + termIds.length + ' elemento' + (termIds.length > 1 ? 's' : '');
        questionText = '¿Estás seguro de que deseas DESACTIVAR estos elementos?';
        btnColor = '#ff9800'; // Naranja
        deleteWarning.style.display = 'none';
    } else if (action === 'activate') {
        modalTitle.textContent = '✅ Activar Elementos';
        actionLabel = 'Activar ' + termIds.length + ' elemento' + (termIds.length > 1 ? 's' : '');
        questionText = '¿Estás seguro de que deseas ACTIVAR estos elementos?';
        btnColor = '#4caf50'; // Verde
        deleteWarning.style.display = 'none';
    }
    
    // Actualizar textos del modal
    modalQuestion.textContent = questionText;
    actionText.textContent = actionLabel;
    
    // Mostrar lista de elementos (máximo 10)
    const displayNames = termNames.slice(0, 10);
    let namesHtml = displayNames.join(', ');
    if (termNames.length > 10) {
        namesHtml += ', ... y ' + (termNames.length - 10) + ' más';
    }
    elementsText.textContent = namesHtml;
    
    // Cambiar color del botón
    confirmBtn.style.backgroundColor = btnColor;
    confirmBtn.style.borderColor = btnColor;
    
    // Mostrar modal
    document.getElementById('fplms-bulk-modal').style.display = 'flex';
}
```

**Flujo**:
1. Valida que haya acción seleccionada
2. Valida que haya elementos marcados
3. Obtiene IDs y nombres de elementos
4. Configura modal según acción (colores, textos, advertencias)
5. Limita lista a 10 elementos + contador
6. Muestra modal de confirmación

---

#### **Función 3: `fplmsConfirmBulkAction()`**

**Ubicación**: Líneas 2647-2671

```javascript
function fplmsConfirmBulkAction() {
    if (!bulkActionData.termIds || bulkActionData.termIds.length === 0) return;
    
    // Crear formulario para enviar
    const form = document.createElement('form');
    form.method = 'POST';
    
    let actionValue = '';
    if (bulkActionData.action === 'delete') {
        actionValue = 'bulk_delete';
    } else if (bulkActionData.action === 'deactivate') {
        actionValue = 'bulk_deactivate';
    } else if (bulkActionData.action === 'activate') {
        actionValue = 'bulk_activate';
    }
    
    let hiddenFields = `
        <?php wp_nonce_field( 'fplms_structures_save', 'fplms_structures_nonce' ); ?>
        <input type="hidden" name="fplms_structures_action" value="${actionValue}">
        <input type="hidden" name="fplms_taxonomy" value="${bulkActionData.taxonomy}">
        <input type="hidden" name="fplms_tab" value="${bulkActionData.tab}">
    `;
    
    // Agregar IDs
    bulkActionData.termIds.forEach((id, index) => {
        hiddenFields += `<input type="hidden" name="fplms_term_ids[${index}]" value="${id}">`;
    });
    
    form.innerHTML = hiddenFields;
    document.body.appendChild(form);
    form.submit();
}
```

**Proceso**:
1. Crea formulario POST dinámicamente
2. Agrega nonce de seguridad
3. Traduce acción (delete/deactivate/activate) a valor PHP
4. Agrega array de IDs seleccionados
5. Envía formulario al servidor

---

#### **Función 4: `fplmsCloseBulkModal()`**

**Ubicación**: Líneas 2643-2645

```javascript
function fplmsCloseBulkModal() {
    document.getElementById('fplms-bulk-modal').style.display = 'none';
}
```

---

#### **Event Listener: Cerrar modal al hacer clic fuera**

**Ubicación**: Líneas 2352-2365

```javascript
// Cerrar modales al hacer clic fuera de ellos
const deleteModal = document.getElementById('fplms-delete-modal');
const saveModal = document.getElementById('fplms-save-modal');
const toggleModal = document.getElementById('fplms-toggle-modal');
const bulkModal = document.getElementById('fplms-bulk-modal'); // NUEVO

[deleteModal, saveModal, toggleModal, bulkModal].forEach(modal => {
    if (modal) {
        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    }
});
```

---

### 🔧 PHP Backend - Handlers de Acciones Masivas

#### **Handler 1: Eliminar Masivo (`bulk_delete`)**

**Ubicación**: Líneas 542-590

```php
if ( 'bulk_delete' === $action ) {
    $term_ids = isset( $_POST['fplms_term_ids'] ) ? array_map( 'absint', (array) $_POST['fplms_term_ids'] ) : [];
    $term_ids = array_filter( $term_ids );
    
    $deleted_count = 0;
    $deleted_names = [];
    
    if ( ! empty( $term_ids ) ) {
        foreach ( $term_ids as $term_id ) {
            $term = get_term( $term_id, $taxonomy );
            
            if ( $term && ! is_wp_error( $term ) ) {
                $term_name = $term->name;
                $deleted_names[] = $term_name;
                
                // Capturar metadatos antes de eliminar
                $term_description = get_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_DESCRIPTION, true );
                
                // Eliminar todas las relaciones
                delete_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_CITIES );
                delete_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_COMPANIES );
                delete_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_CHANNELS );
                delete_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_BRANCHES );
                delete_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_DESCRIPTION );
                delete_term_meta( $term_id, 'fplms_linked_category_id' );
                
                // Registrar en bitácora
                if ( class_exists( 'FairPlay_LMS_Audit_Logger' ) ) {
                    $audit = new FairPlay_LMS_Audit_Logger();
                    $structure_type = $this->get_structure_type_name( $taxonomy );
                    
                    $audit->log_structure_deleted(
                        $structure_type,
                        $term_id,
                        $term_name,
                        [ 'taxonomy' => $taxonomy, 'bulk_action' => true ]
                    );
                }
                
                // Eliminar término
                wp_delete_term( $term_id, $taxonomy );
                $deleted_count++;
            }
        }
    }
    
    // Redirigir con mensaje
    $tab = sanitize_text_field( wp_unslash( $_POST['fplms_tab'] ?? '' ) );
    $success_msg = urlencode( "✓ {$deleted_count} elemento" . ( $deleted_count > 1 ? 's eliminados' : ' eliminado' ) . " exitosamente" );
    $redirect_url = add_query_arg(
        array(
            'page' => 'fplms-structures',
            'fplms_success' => $success_msg,
            'tab' => $tab
        ),
        admin_url( 'admin.php' )
    );
    wp_redirect( $redirect_url );
    exit;
}
```

**Características**:
- ✅ Valida y sanitiza array de IDs
- ✅ Elimina relaciones jerárquicas antes de eliminar
- ✅ Registra cada eliminación en bitácora con flag `bulk_action: true`
- ✅ Mensaje de éxito con contador de elementos eliminados
- ✅ Pluralización correcta: "1 elemento eliminado" / "5 elementos eliminados"

---

#### **Handler 2: Desactivar Masivo (`bulk_deactivate`)**

**Ubicación**: Líneas 592-641

```php
if ( 'bulk_deactivate' === $action ) {
    $term_ids = isset( $_POST['fplms_term_ids'] ) ? array_map( 'absint', (array) $_POST['fplms_term_ids'] ) : [];
    $term_ids = array_filter( $term_ids );
    
    $updated_count = 0;
    
    if ( ! empty( $term_ids ) ) {
        foreach ( $term_ids as $term_id ) {
            $term = get_term( $term_id, $taxonomy );
            
            if ( $term && ! is_wp_error( $term ) ) {
                $current = get_term_meta( $term_id, FairPlay_LMS_Config::META_ACTIVE, true );
                
                // Solo desactivar si está activo
                if ( '1' === $current ) {
                    update_term_meta( $term_id, FairPlay_LMS_Config::META_ACTIVE, '0' );
                    
                    // Registrar en bitácora
                    if ( class_exists( 'FairPlay_LMS_Audit_Logger' ) ) {
                        $audit = new FairPlay_LMS_Audit_Logger();
                        $structure_type = $this->get_structure_type_name( $taxonomy );
                        
                        $audit->log_structure_status_changed(
                            $structure_type,
                            $term_id,
                            $term->name,
                            '1', // Estado anterior
                            '0'  // Estado nuevo
                        );
                    }
                    
                    $updated_count++;
                }
            }
        }
    }
    
    // Redirigir con mensaje
    $tab = sanitize_text_field( wp_unslash( $_POST['fplms_tab'] ?? '' ) );
    $success_msg = urlencode( "✓ {$updated_count} elemento" . ( $updated_count > 1 ? 's desactivados' : ' desactivado' ) . " exitosamente" );
    $redirect_url = add_query_arg(
        array(
            'page' => 'fplms-structures',
            'fplms_success' => $success_msg,
            'tab' => $tab
        ),
        admin_url( 'admin.php' )
    );
    
    // Headers para evitar caché
    header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
    header( 'Cache-Control: post-check=0, pre-check=0', false );
    header( 'Pragma: no-cache' );
    
    wp_redirect( $redirect_url );
    exit;
}
```

**Inteligencia**:
- ✅ Solo desactiva elementos que **estén activos** (estado = '1')
- ✅ No cuenta elementos ya inactivos
- ✅ Registra cada cambio de estado en bitácora
- ✅ Headers no-cache para ver cambios inmediatamente

---

#### **Handler 3: Activar Masivo (`bulk_activate`)**

**Ubicación**: Líneas 643-693

```php
if ( 'bulk_activate' === $action ) {
    $term_ids = isset( $_POST['fplms_term_ids'] ) ? array_map( 'absint', (array) $_POST['fplms_term_ids'] ) : [];
    $term_ids = array_filter( $term_ids );
    
    $updated_count = 0;
    
    if ( ! empty( $term_ids ) ) {
        foreach ( $term_ids as $term_id ) {
            $term = get_term( $term_id, $taxonomy );
            
            if ( $term && ! is_wp_error( $term ) ) {
                $current = get_term_meta( $term_id, FairPlay_LMS_Config::META_ACTIVE, true );
                
                // Solo activar si está inactivo
                if ( '0' === $current || '' === $current ) {
                    update_term_meta( $term_id, FairPlay_LMS_Config::META_ACTIVE, '1' );
                    
                    // Registrar en bitácora
                    if ( class_exists( 'FairPlay_LMS_Audit_Logger' ) ) {
                        $audit = new FairPlay_LMS_Audit_Logger();
                        $structure_type = $this->get_structure_type_name( $taxonomy );
                        
                        $audit->log_structure_status_changed(
                            $structure_type,
                            $term_id,
                            $term->name,
                            $current ?: '0', // Estado anterior
                            '1'              // Estado nuevo
                        );
                    }
                    
                    $updated_count++;
                }
            }
        }
    }
    
    // Redirigir con mensaje
    $tab = sanitize_text_field( wp_unslash( $_POST['fplms_tab'] ?? '' ) );
    $success_msg = urlencode( "✓ {$updated_count} elemento" . ( $updated_count > 1 ? 's activados' : ' activado' ) . " exitosamente" );
    $redirect_url = add_query_arg(
        array(
            'page' => 'fplms-structures',
            'fplms_success' => $success_msg,
            'tab' => $tab
        ),
        admin_url( 'admin.php' )
    );
    
    // Headers para evitar caché
    header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
    header( 'Cache-Control: post-check=0, pre-check=0', false );
    header( 'Pragma: no-cache' );
    
    wp_redirect( $redirect_url );
    exit;
}
```

**Inteligencia**:
- ✅ Solo activa elementos que **estén inactivos** (estado = '0' o vacío)
- ✅ No cuenta elementos ya activos
- ✅ Registra cada cambio de estado en bitácora
- ✅ Headers no-cache para ver cambios inmediatamente

---

### 📊 Registro en Bitácora

#### **Formato de registro para acciones masivas**:

##### **Eliminar múltiples elementos**:
```json
{
  "id": 1235,
  "timestamp": "2026-03-04 16:45:32",
  "user_id": 5,
  "user_name": "Juan Pérez",
  "action": "structure_deleted",
  "entity_type": "channel",
  "entity_id": 42,
  "entity_title": "Fair Play Kids",
  "old_value": {
    "taxonomy": "fplms_channel",
    "bulk_action": true
  },
  "new_value": null,
  "ip_address": "192.168.1.100"
}
```

**Nota**: Se crea **UN REGISTRO POR CADA ELEMENTO** eliminado, con flag `bulk_action: true`

##### **Activar/Desactivar múltiples elementos**:
```json
{
  "id": 1236,
  "timestamp": "2026-03-04 16:47:15",
  "user_id": 5,
  "user_name": "Juan Pérez",
  "action": "structure_status_changed",
  "entity_type": "channel",
  "entity_id": 43,
  "entity_title": "Fair Play Outlets",
  "old_value": {
    "active": "1",
    "status_text": "Activo"
  },
  "new_value": {
    "active": "0",
    "status_text": "Inactivo"
  },
  "ip_address": "192.168.1.100"
}
```

**Nota**: Se crea **UN REGISTRO POR CADA ELEMENTO** modificado

---

## 📄 Parte 2: Paginación en Bitácora de Auditoría

### 🎨 Interfaz de Usuario

**Ubicación**: Parte superior derecha de la tabla de auditoría

```
┌────────────────────────────────────────────────────────────────────────┐
│ Registros de Auditoría (1,245 total)          Mostrar: [50▼] registros│
└────────────────────────────────────────────────────────────────────────┘
```

**Selector de registros**:
- 10 registros por página
- 20 registros por página
- 50 registros por página (predeterminado)
- 100 registros por página

---

### 📝 Cambios en PHP

#### **Archivo**: `class-fplms-audit-admin.php`

##### **Cambio 1: Obtener per_page del parámetro GET**

**Ubicación**: Líneas 71-79

```php
// Obtener filtros
$current_page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$per_page     = isset( $_GET['per_page'] ) ? intval( $_GET['per_page'] ) : 50;

// Validar per_page (solo permitir valores específicos)
if ( ! in_array( $per_page, [ 10, 20, 50, 100 ], true ) ) {
    $per_page = 50; // Valor predeterminado seguro
}

$offset       = ( $current_page - 1 ) * $per_page;
```

**Seguridad**:
- ✅ Valida que per_page sea uno de los valores permitidos
- ✅ Si el valor es inválido, usa 50 por defecto
- ✅ Previene ataques de inyección con valores arbitrarios

---

##### **Cambio 2: Pasar per_page a render_logs_table()**

**Ubicación**: Línea 106

```php
<?php $this->render_logs_table( $logs, $current_page, $total_pages, $total_logs, $per_page ); ?>
```

---

##### **Cambio 3: Actualizar firma del método**

**Ubicación**: Líneas 243-262

```php
/**
 * Renderizar tabla de logs
 *
 * @param array $logs        Array de logs
 * @param int   $current_page Página actual
 * @param int   $total_pages  Total de páginas
 * @param int   $total_logs   Total de registros
 * @param int   $per_page     Registros por página
 * @return void
 */
private function render_logs_table( array $logs, int $current_page, int $total_pages, int $total_logs, int $per_page ): void {
    ?>
    <div class="fplms-audit-table">
        <div style="background: #fff; padding: 15px; border: 1px solid #ccd0d4;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 style="margin: 0;">Registros de Auditoría (<?php echo esc_html( number_format( $total_logs ) ); ?> total)</h3>
                
                <!-- NUEVO: Selector de per_page -->
                <form method="get" action="" style="margin: 0;">
                    <input type="hidden" name="page" value="fairplay-lms-audit">
                    <?php
                    // Mantener todos los filtros actuales
                    foreach ( $_GET as $key => $value ) {
                        if ( $key !== 'page' && $key !== 'per_page' && $key !== 'paged' ) {
                            echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
                        }
                    }
                    ?>
                    <label for="per_page" style="margin-right: 5px; font-weight: 600;">Mostrar:</label>
                    <select name="per_page" id="per_page" onchange="this.form.submit()" style="padding: 5px;">
                        <option value="10" <?php selected( $per_page, 10 ); ?>>10</option>
                        <option value="20" <?php selected( $per_page, 20 ); ?>>20</option>
                        <option value="50" <?php selected( $per_page, 50 ); ?>>50</option>
                        <option value="100" <?php selected( $per_page, 100 ); ?>>100</option>
                    </select>
                    <span style="margin-left: 5px;">registros por página</span>
                </form>
            </div>
```

**Características**:
- ✅ Formulario auto-submit al cambiar selector
- ✅ Mantiene TODOS los filtros activos (acción, entidad, fechas, usuario)
- ✅ No resetea la página (vuelve a página 1 automáticamente)
- ✅ Dropdown con 4 opciones: 10, 20, 50, 100

---

##### **Cambio 4: Actualizar render_pagination() para mantener per_page**

**Ubicación**: Líneas 409-444

```php
private function render_pagination( int $current_page, int $total_pages ): void {
    if ( $total_pages <= 1 ) {
        return;
    }

    $base_url = admin_url( 'admin.php?page=fairplay-lms-audit' );
    
    // Mantener filtros y per_page en la paginación
    $query_params = [];
    if ( ! empty( $_GET['filter_action'] ) ) {
        $query_params['filter_action'] = sanitize_text_field( wp_unslash( $_GET['filter_action'] ) );
    }
    if ( ! empty( $_GET['filter_entity'] ) ) {
        $query_params['filter_entity'] = sanitize_text_field( wp_unslash( $_GET['filter_entity'] ) );
    }
    if ( ! empty( $_GET['filter_date_from'] ) ) {
        $query_params['filter_date_from'] = sanitize_text_field( wp_unslash( $_GET['filter_date_from'] ) );
    }
    if ( ! empty( $_GET['filter_date_to'] ) ) {
        $query_params['filter_date_to'] = sanitize_text_field( wp_unslash( $_GET['filter_date_to'] ) );
    }
    // NUEVO: Mantener per_page
    if ( ! empty( $_GET['per_page'] ) ) {
        $query_params['per_page'] = intval( $_GET['per_page'] );
    }

    ?>
    <div class="tablenav" style="margin-top: 15px;">
        <div class="tablenav-pages">
            <span class="displaying-num"><?php echo esc_html( sprintf( 'Página %d de %d', $current_page, $total_pages ) ); ?></span>
            <span class="pagination-links">
                <?php if ( $current_page > 1 ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( array_merge( $query_params, [ 'paged' => 1 ] ), $base_url ) ); ?>" class="button">« Primera</a>
                    <a href="<?php echo esc_url( add_query_arg( array_merge( $query_params, [ 'paged' => $current_page - 1 ] ), $base_url ) ); ?>" class="button">‹ Anterior</a>
                <?php endif; ?>

                <?php if ( $current_page < $total_pages ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( array_merge( $query_params, [ 'paged' => $current_page + 1 ] ), $base_url ) ); ?>" class="button">Siguiente ›</a>
                    <a href="<?php echo esc_url( add_query_arg( array_merge( $query_params, [ 'paged' => $total_pages ] ), $base_url ) ); ?>" class="button">Última »</a>
                <?php endif; ?>
            </span>
        </div>
    </div>
    <?php
}
```

**Garantiza**:
- ✅ Navegación entre páginas mantiene el per_page seleccionado
- ✅ Filtros activos persisten al cambiar página
- ✅ URLs limpias y predecibles

---

### 📊 Ejemplos de Uso - Paginación

#### **Escenario 1: 1,245 registros en total**

##### Con per_page = 10:
- Total páginas: 125
- Página 1: Registros 1-10
- Página 2: Registros 11-20
- ...
- Página 125: Registros 1241-1245

##### Con per_page = 50 (predeterminado):
- Total páginas: 25
- Página 1: Registros 1-50
- Página 2: Registros 51-100
- ...
- Página 25: Registros 1201-1245

##### Con per_page = 100:
- Total páginas: 13
- Página 1: Registros 1-100
- Página 2: Registros 101-200
- ...
- Página 13: Registros 1201-1245

---

## ✅ Checklist de Verificación

### **Acciones Masivas**:

#### 1. **Interfaz**:
- [ ] Dropdown de acciones masivas aparece al seleccionar checkboxes
- [ ] Contador muestra número correcto de seleccionados
- [ ] Dropdown tiene 3 opciones (Activar, Desactivar, Eliminar)
- [ ] Botón "Aplicar" está habilitado

#### 2. **Modal de Confirmación - Activar**:
- [ ] Título: "✅ Activar Elementos"
- [ ] Texto: "Activar X elementos"
- [ ] Botón verde (#4caf50)
- [ ] Lista de nombres (máx 10 + "... y X más")
- [ ] Sin advertencia roja

#### 3. **Modal de Confirmación - Desactivar**:
- [ ] Título: "❌ Desactivar Elementos"
- [ ] Texto: "Desactivar X elementos"
- [ ] Botón naranja (#ff9800)
- [ ] Lista de nombres (máx 10 + "... y X más")
- [ ] Sin advertencia roja

#### 4. **Modal de Confirmación - Eliminar**:
- [ ] Título: "🗑️ Eliminar Elementos"
- [ ] Texto: "Eliminar X elementos"
- [ ] Botón rojo (#d32f2f)
- [ ] Lista de nombres (máx 10 + "... y X más")
- [ ] ⚠️ Advertencia ROJA visible

#### 5. **Funcionalidad**:
- [ ] Activar seleccionados funciona (solo activa inactivos)
- [ ] Desactivar seleccionados funciona (solo desactiva activos)
- [ ] Eliminar seleccionados funciona
- [ ] Mensaje de éxito con contador correcto
- [ ] Cambios visibles inmediatamente (sin actualizar)
- [ ] Checkboxes se desmarca después de acción

#### 6. **Bitácora**:
- [ ] Cada elemento activado registra en bitácora
- [ ] Cada elemento desactivado registra en bitácora
- [ ] Cada elemento eliminado registra en bitácora con flag `bulk_action: true`
- [ ] Usuario correcto en todos los registros
- [ ] Fecha y hora correctas

### **Paginación en Bitácora**:

#### 1. **Selector**:
- [ ] Selector visible en esquina superior derecha
- [ ] Opciones: 10, 20, 50, 100
- [ ] Valor predeterminado: 50
- [ ] Cambia automáticamente al seleccionar

#### 2. **Funcionalidad**:
- [ ] Muestra cantidad correcta de registros por página
- [ ] Navegación entre páginas mantiene per_page
- [ ] Filtros activos persisten con per_page
- [ ] Cálculo correcto de total de páginas
- [ ] Cálculo correcto de offset (página 3 con per_page=20 → registros 41-60)

#### 3. **Navegación**:
- [ ] Botones "Primera" y "Última" página funcionan
- [ ] Botones "Anterior" y "Siguiente" funcionan
- [ ] Indicador "Página X de Y" correcto
- [ ] URLs mantienen todos los parámetros

---

## 📚 Código de Referencia Completo

### **Flujo Completo de Acción Masiva**:

```
1. Usuario selecciona checkboxes
   ↓
2. fplmsUpdateExportButton() actualiza UI
   - Muestra contenedor de acciones masivas
   - Actualiza contador
   ↓
3. Usuario selecciona acción del dropdown
   ↓
4. Usuario hace clic en "Aplicar"
   ↓
5. fplmsApplyBulkAction() prepara datos
   - Obtiene IDs y nombres
   - Configura modal según acción
   - Muestra modal de confirmación
   ↓
6. Usuario confirma en modal
   ↓
7. fplmsConfirmBulkAction() envía formulario
   - Crea formulario POST dinámico
   - Agrega nonce de seguridad
   - Envía al servidor
   ↓
8. PHP procesa (bulk_delete / bulk_deactivate / bulk_activate)
   - Valida permisos y nonce
   - Itera sobre cada ID
   - Aplica acción correspondiente
   - Registra en bitácora
   ↓
9. PHP redirige con mensaje de éxito
   - Headers no-cache
   - Mensaje con contador
   ↓
10. Usuario ve cambios inmediatamente
    - Notificación verde
    - Tabla actualizada
    - Bitácora con nuevos registros
```

---

## 🚀 Ventajas de la Implementación

### **Acciones Masivas**:
1. ✅ **Eficiencia**: Modificar múltiples elementos en una sola acción
2. ✅ **Seguridad**: Modal de confirmación previene errores
3. ✅ **Trazabilidad**: Cada cambio registrado en bitácora
4. ✅ **Inteligencia**: Solo afecta elementos elegibles (activar solo inactivos, etc.)
5. ✅ **UX**: Feedback visual claro con colores y advertencias
6. ✅ **Performance**: Headers no-cache garantizan actualización inmediata

### **Paginación en Bitácora**:
1. ✅ **Flexibilidad**: Usuario elige cuántos registros ver
2. ✅ **Performance**: Menos carga para tablas con miles de registros
3. ✅ **Usabilidad**: Mantiene filtros y configuración al navegar
4. ✅ **Escalabilidad**: Funciona con cualquier cantidad de registros

---

## 📞 Soporte y Troubleshooting

### **Problema**: Acciones masivas no aparecen
- **Verificar**: Que haya checkboxes seleccionados
- **Verificar**: JavaScript no tiene errores en consola
- **Verificar**: IDs de elementos son correctos

### **Problema**: Modal no se cierra
- **Solución**: Click fuera del modal o botón X
- **Verificar**: Event listeners registrados correctamente

### **Problema**: Cambios no se ven inmediatamente
- **Verificar**: Headers no-cache en PHP
- **Solución**: Limpiar caché del navegador (Ctrl + Shift + R)

### **Problema**: Per page no se mantiene
- **Verificar**: Parámetro `per_page` en URLs de paginación
- **Verificar**: Formulario de selector mantiene filtros

---

**Documentación generada**: 4 de marzo de 2026  
**Versión**: FairPlay LMS Extensions v1.0  
**Compatibilidad**: WordPress 5.8+, PHP 7.4+
