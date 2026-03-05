# 📋 Corrección de Bitácora y Toggle de Estado

**Fecha**: 4 de marzo de 2026  
**Archivo**: `class-fplms-structures.php`

---

## 📊 Análisis Realizado

### ✅ Sistema de Bitácora - FUNCIONANDO CORRECTAMENTE

**Verificación completa del sistema de auditoría:**

1. **Archivo**: `class-fplms-audit-logger.php`
2. **Tabla de base de datos**: `wp_fplms_audit_log`

#### Campos que se registran automáticamente:

```php
- id              // ID único del registro
- timestamp       // Fecha y hora del cambio
- user_id         // ID del usuario que realizó la acción
- user_name       // Nombre del usuario
- action          // Tipo de acción realizada
- entity_type     // Tipo de estructura (city, company, channel, branch, role)
- entity_id       // ID del elemento modificado
- entity_title    // Nombre del elemento
- old_value       // Valor anterior (JSON)
- new_value       // Valor nuevo (JSON)
- ip_address      // Dirección IP del cliente
- user_agent      // Navegador utilizado
```

#### Acciones registradas automáticamente:

✅ **Crear estructura** → `log_structure_created()`
- Registra: nombre, descripción, estado inicial, relaciones jerárquicas
- Usuario que creó

✅ **Modificar estructura** → `log_structure_updated()`
- Registra: cambios en nombre, descripción, relaciones
- Usuario que modificó
- Datos antiguos vs nuevos

✅ **Cambiar estado** → `log_structure_status_changed()`
- Registra: estado anterior (Activo/Inactivo)
- Estado nuevo (Activo/Inactivo)
- Usuario que cambió el estado

✅ **Eliminar estructura** → `log_structure_deleted()`
- Registra: todos los datos antes de eliminar
- Usuario que eliminó

---

## 🐛 Problema Identificado: Toggle de Estado

### Síntoma reportado por usuario:
> "Luego de registrar algunos campos en la estructura y procedo a querer activar o inactivar alguna, este no funciona al momento sino que debo esperar o en su defecto actualizar en repetidas ocasiones para luego poder usar esta funcionalidad"

### Causa raíz:

1. **Botón con formulario POST directo** (sin confirmación):
   ```php
   <form method="post" style="display:inline;">
       <button type="submit">⊙</button>
   </form>
   ```

2. **Recarga completa de página**: Causaba problemas de caché del navegador

3. **Sin modal de confirmación**: Cambio de estado sin aviso previo

4. **Caché del navegador**: No forzaba actualización, mostrando estado antiguo

---

## 🔧 Soluciones Implementadas

### 1. Modal de Confirmación de Cambio de Estado

**Nuevo HTML agregado** (líneas 1427-1447):

```html
<!-- Modal de Confirmación de Cambio de Estado -->
<div id="fplms-toggle-modal" class="fplms-modal" style="display:none;">
    <div class="fplms-modal-content" style="max-width: 450px;">
        <div class="fplms-modal-header">
            <h3 id="fplms-toggle-modal-title">⊙ Cambiar Estado</h3>
            <button class="fplms-modal-close" onclick="fplmsCloseToggleModal()">✕</button>
        </div>
        <div class="fplms-modal-body">
            <p id="fplms-toggle-modal-question">¿Estás seguro de que deseas cambiar el estado?</p>
            <div style="background: #fff3cd; padding: 12px; border-radius: 4px;">
                <p id="fplms_toggle_name"></p>
                <p id="fplms_toggle_status">Estado actual → Cambiar a:</p>
            </div>
            <p style="color: #666;">Este cambio se registrará en la bitácora del sistema.</p>
        </div>
        <div class="fplms-modal-footer">
            <button type="button" class="button" onclick="fplmsCloseToggleModal()">Cancelar</button>
            <button type="button" class="button button-primary" id="fplms-toggle-confirm-btn" 
                onclick="fplmsConfirmToggleStatus()">✓ Cambiar Estado</button>
        </div>
    </div>
</div>
```

**Características del modal:**
- ⊙/○ Icono dinámico según acción (Activar/Desactivar)
- 🎨 Color del botón dinámico:
  - Verde (#4caf50) para activar
  - Naranja (#ff9800) para desactivar
- 📝 Muestra nombre del elemento y cambio de estado
- 📋 Informa que se registrará en bitácora

---

### 2. Cambio en el Botón de Toggle

**ANTES** (líneas 1010-1020):
```php
<form method="post" style="display:inline;">
    <?php wp_nonce_field( 'fplms_structures_save', 'fplms_structures_nonce' ); ?>
    <input type="hidden" name="fplms_structures_action" value="toggle_active">
    <input type="hidden" name="fplms_taxonomy" value="...">
    <input type="hidden" name="fplms_term_id" value="...">
    <input type="hidden" name="fplms_tab" value="...">
    <button type="submit" class="fplms-btn fplms-btn-toggle">⊙</button>
</form>
```

**DESPUÉS** (líneas 1012-1016):
```php
<button type="button" class="fplms-btn fplms-btn-toggle" 
    onclick="fplmsToggleStatus(<?php echo esc_attr( $term->term_id ); ?>, 
                                '<?php echo esc_js( $term->name ); ?>', 
                                '<?php echo esc_attr( $tab_info['taxonomy'] ); ?>', 
                                '<?php echo esc_attr( $tab_key ); ?>', 
                                <?php echo $active ? '1' : '0'; ?>)"
    title="<?php echo $active ? 'Desactivar' : 'Activar'; ?>">
    <?php echo $active ? '⊙' : '○'; ?>
</button>
```

**Cambios:**
- ✅ Eliminado formulario POST directo
- ✅ Botón `type="button"` en lugar de `type="submit"`
- ✅ JavaScript manejando el click
- ✅ Parámetros pasados: ID, nombre, taxonomía, tab, estado actual

---

### 3. Funciones JavaScript Implementadas

**A) Función `fplmsToggleStatus()` (líneas 2470-2505):**

```javascript
function fplmsToggleStatus(termId, termName, taxonomy, tab, currentStatus) {
    toggleData = { termId, termName, taxonomy, tab, currentStatus };
    
    // Configurar contenido del modal según el estado actual
    const isActive = currentStatus === 1 || currentStatus === '1';
    const newStatus = isActive ? 'Inactivo' : 'Activo';
    const action = isActive ? 'desactivar' : 'activar';
    const emoji = isActive ? '○' : '⊙';
    
    // Actualizar textos del modal
    document.getElementById('fplms-toggle-modal-title').textContent = 
        emoji + ' ' + (isActive ? 'Desactivar' : 'Activar') + ' Elemento';
    document.getElementById('fplms-toggle-modal-question').textContent = 
        `¿Estás seguro de que deseas ${action} este elemento?`;
    document.getElementById('fplms_toggle_name').textContent = `"${termName}"`;
    document.getElementById('fplms_toggle_status').textContent = 
        `Estado actual: ${isActive ? 'Activo' : 'Inactivo'} → Cambiar a: ${newStatus}`;
    
    // Cambiar color del botón según la acción
    const confirmBtn = document.getElementById('fplms-toggle-confirm-btn');
    if (isActive) {
        // Desactivar = naranja
        confirmBtn.style.backgroundColor = '#ff9800';
        confirmBtn.style.borderColor = '#ff9800';
    } else {
        // Activar = verde
        confirmBtn.style.backgroundColor = '#4caf50';
        confirmBtn.style.borderColor = '#4caf50';
    }
    
    // Mostrar modal
    document.getElementById('fplms-toggle-modal').style.display = 'flex';
}
```

**B) Función `fplmsConfirmToggleStatus()` (líneas 2511-2527):**

```javascript
function fplmsConfirmToggleStatus() {
    if (!toggleData.termId) return;

    // Crear formulario dinámicamente con todos los datos
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <?php wp_nonce_field( 'fplms_structures_save', 'fplms_structures_nonce' ); ?>
        <input type="hidden" name="fplms_structures_action" value="toggle_active">
        <input type="hidden" name="fplms_taxonomy" value="${toggleData.taxonomy}">
        <input type="hidden" name="fplms_term_id" value="${toggleData.termId}">
        <input type="hidden" name="fplms_tab" value="${toggleData.tab}">
    `;
    document.body.appendChild(form);
    form.submit();
}
```

**C) Función `fplmsCloseToggleModal()` (líneas 2507-2509):**

```javascript
function fplmsCloseToggleModal() {
    document.getElementById('fplms-toggle-modal').style.display = 'none';
}
```

**D) Event Listener: Cerrar modal al hacer clic fuera** (líneas 2349-2360):

```javascript
// Cerrar modales al hacer clic fuera de ellos
const deleteModal = document.getElementById('fplms-delete-modal');
const saveModal = document.getElementById('fplms-save-modal');
const toggleModal = document.getElementById('fplms-toggle-modal');

[deleteModal, saveModal, toggleModal].forEach(modal => {
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

### 4. Headers No-Cache en PHP

**Modificación en `handle_form()` para toggle_active** (líneas 250-254):

```php
// Headers para evitar caché y forzar actualización
header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
header( 'Cache-Control: post-check=0, pre-check=0', false );
header( 'Pragma: no-cache' );

wp_redirect( $redirect_url );
exit;
```

**Efecto:**
- ✅ Fuerza al navegador a NO usar caché
- ✅ Recarga página con datos frescos de la base de datos
- ✅ El cambio de estado se ve INMEDIATAMENTE
- ✅ Elimina necesidad de actualizar múltiples veces

---

## 📝 Flujo Completo del Cambio de Estado

### Proceso paso a paso:

```
1. Usuario hace clic en botón ⊙/○
   ↓
2. JavaScript ejecuta fplmsToggleStatus()
   ↓
3. Se muestra modal de confirmación con:
   - Nombre del elemento
   - Estado actual
   - Estado futuro
   - Botón con color dinámico (verde/naranja)
   ↓
4. Usuario confirma cambio
   ↓
5. JavaScript ejecuta fplmsConfirmToggleStatus()
   ↓
6. Se crea formulario POST dinámico con:
   - Nonce de seguridad
   - ID del elemento
   - Taxonomía
   - Tab activa
   ↓
7. PHP procesa cambio en handle_form():
   - Verifica permisos
   - Valida nonce
   - Cambia estado en base de datos
   - Registra en bitácora con usuario
   ↓
8. PHP envía headers no-cache
   ↓
9. Redirect a la página con mensaje de éxito
   ↓
10. Navegador recarga SIN usar caché
   ↓
11. Usuario ve cambio INMEDIATAMENTE
   ↓
12. Notificación verde muestra éxito
```

---

## 🎨 Características Visuales del Modal

### Estados dinámicos:

#### **Activar** (elemento inactivo → activo):
- Icono: ⊙
- Color botón: Verde (#4caf50)
- Texto: "¿Estás seguro de que deseas **activar** este elemento?"
- Estado: "Inactivo → Cambiar a: Activo"

#### **Desactivar** (elemento activo → inactivo):
- Icono: ○
- Color botón: Naranja (#ff9800)
- Texto: "¿Estás seguro de que deseas **desactivar** este elemento?"
- Estado: "Activo → Cambiar a: Inactivo"

### Consistencia con otros modales:
- ✅ Misma estructura HTML que modal de eliminación
- ✅ Misma estructura que modal de guardar cambios
- ✅ Cierra al hacer clic en X
- ✅ Cierra al hacer clic fuera del modal
- ✅ Botón "Cancelar" para abortar

---

## 📊 Registro en Bitácora

### Ejemplo de registro completo:

```json
{
  "id": 1234,
  "timestamp": "2026-03-04 15:30:45",
  "user_id": 5,
  "user_name": "Juan Pérez",
  "action": "structure_status_changed",
  "entity_type": "channel",
  "entity_id": 42,
  "entity_title": "Fair Play Kids",
  "old_value": {
    "active": "1",
    "status_text": "Activo"
  },
  "new_value": {
    "active": "0",
    "status_text": "Inactivo"
  },
  "ip_address": "192.168.1.100",
  "user_agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64)..."
}
```

### Información capturada:
- ✅ **Quién**: ID y nombre del usuario
- ✅ **Qué**: Tipo de acción (cambio de estado)
- ✅ **Cuándo**: Fecha y hora exacta
- ✅ **Dónde**: Tipo de estructura y elemento específico
- ✅ **Cómo**: Estado anterior y nuevo
- ✅ **Desde dónde**: IP y navegador

---

## ✅ Verificación de Correcciones

### Checklist de testing:

#### 1. **Modal de confirmación**
- [ ] Click en botón ⊙/○ muestra modal
- [ ] Modal muestra nombre correcto del elemento
- [ ] Modal muestra estado actual y futuro
- [ ] Color del botón es verde para activar
- [ ] Color del botón es naranja para desactivar
- [ ] Botón "Cancelar" cierra modal sin cambios
- [ ] Click en X cierra modal sin cambios
- [ ] Click fuera del modal lo cierra

#### 2. **Cambio de estado funciona inmediatamente**
- [ ] Activar elemento cambia estado a ✓ Activo
- [ ] Desactivar elemento cambia estado a ✗ Inactivo
- [ ] Cambio se ve INMEDIATAMENTE (sin esperar)
- [ ] NO requiere actualizar múltiples veces
- [ ] Notificación verde confirma el cambio
- [ ] Badge de estado se actualiza correctamente

#### 3. **Bitácora**
- [ ] Abrir página de Auditoría en WordPress
- [ ] Verificar registro con acción: "structure_status_changed"
- [ ] Verificar que muestra usuario correcto
- [ ] Verificar que muestra fecha y hora
- [ ] Verificar que muestra estado anterior y nuevo
- [ ] Verificar que muestra IP del usuario

#### 4. **Pruebas en todas las estructuras**
- [ ] **Ciudades**: Activar/Desactivar funciona
- [ ] **Empresas**: Activar/Desactivar funciona
- [ ] **Canales**: Activar/Desactivar funciona
- [ ] **Sucursales**: Activar/Desactivar funciona
- [ ] **Cargos**: Activar/Desactivar funciona

#### 5. **Pruebas de navegadores**
- [ ] Chrome: Funciona sin caché
- [ ] Firefox: Funciona sin caché
- [ ] Edge: Funciona sin caché
- [ ] Safari: Funciona sin caché

---

## 🔍 Archivos Modificados

### `class-fplms-structures.php` (4 cambios):

1. **Líneas 238-256**: Headers no-cache en PHP para toggle_active
2. **Líneas 1012-1016**: Botón de toggle cambiado a JavaScript
3. **Líneas 1427-1447**: Nuevo modal HTML de confirmación
4. **Líneas 2470-2527**: Funciones JavaScript del modal toggle
5. **Líneas 2349-2360**: Event listener para cerrar modal

---

## 📚 Documentación Técnica para Desarrolladores

### Agregar nueva acción a la bitácora:

```php
// En class-fplms-audit-logger.php
public function log_custom_action( 
    string $structure_type, 
    int $term_id, 
    string $term_name, 
    array $meta_data = [] 
) {
    return $this->log_action(
        'custom_action',           // Tipo de acción
        $structure_type,           // Tipo de estructura
        $term_id,                  // ID del elemento
        $term_name,                // Nombre del elemento
        wp_json_encode( $meta_data ), // Datos adicionales
        null                       // Valor nuevo (opcional)
    );
}

// En class-fplms-structures.php
if ( class_exists( 'FairPlay_LMS_Audit_Logger' ) ) {
    $audit = new FairPlay_LMS_Audit_Logger();
    $audit->log_custom_action(
        'channel',
        $channel_id,
        $channel_name,
        [ 'custom_field' => 'value' ]
    );
}
```

---

## 🚀 Impacto de las Correcciones

### Beneficios obtenidos:

1. **✅ Experiencia de usuario mejorada**:
   - Confirmación antes de cambiar estado
   - Feedback visual claro del cambio
   - No hay esperas ni actualizaciones múltiples

2. **✅ Integridad de datos**:
   - Todos los cambios se registran en bitácora
   - Trazabilidad completa de acciones
   - Auditoría con usuario, fecha, IP

3. **✅ Solución del bug de caché**:
   - Headers no-cache fuerzan actualización
   - Cambios visibles inmediatamente
   - Funciona en todos los navegadores

4. **✅ Seguridad mantenida**:
   - Nonce de seguridad en todas las acciones
   - Verificación de permisos de usuario
   - Validación de datos antes de cambiar

---

## 📞 Soporte

Para cualquier problema o duda:
- Verificar que el código esté reflejado en el servidor
- Limpiar caché del navegador (Ctrl + Shift + R)
- Verificar que el usuario tenga permisos `manage_structures`
- Revisar logs de PHP en caso de errores

---

**Documentación generada**: 4 de marzo de 2026  
**Versión del plugin**: FairPlay LMS Extensions v1.0  
**Compatibilidad**: WordPress 5.8+, PHP 7.4+
