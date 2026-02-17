# 📚 Documentación: Asignación en Cascada de Estructuras a Cursos

## 📋 Resumen

Sistema de asignación de estructuras jerárquicas a cursos con selección automática en cascada, similar al sistema implementado en la creación de usuarios.

**Fecha de implementación:** 16 de febrero de 2026  
**Versión:** 1.0.0  
**Desarrollador:** Sistema FairPlay LMS

---

## 🎯 Objetivo

Facilitar la asignación de estructuras a cursos permitiendo que al seleccionar un nivel jerárquico superior (ej: Ciudad), se carguen y preseleccionen automáticamente las estructuras relacionadas de niveles inferiores (Empresas, Canales, Sucursales, Cargos).

---

## 🏗️ Arquitectura de la Solución

### Jerarquía Implementada

```
📍 CIUDAD
   ├── 🏢 EMPRESAS (asociadas a la ciudad)
   │   ├── 🏪 CANALES (asociados a la empresa)
   │   │   ├── 🏬 SUCURSALES (asociadas al canal)
   │   │   │   └── 👔 CARGOS (asociados a la sucursal)
```

### Flujo de Selección en Cascada

```
Usuario selecciona Ciudad
        ↓
Sistema carga TODAS las Empresas de esa ciudad
        ↓
Usuario selecciona Empresa (o deja todas)
        ↓
Sistema carga TODOS los Canales de esas empresas
        ↓
Usuario selecciona Canal (o deja todos)
        ↓
Sistema carga TODAS las Sucursales de esos canales
        ↓
Usuario selecciona Sucursal (o deja todas)
        ↓
Sistema carga TODOS los Cargos de esas sucursales
        ↓
Usuario guarda → Se aplica cascada final
```

---

## 🔧 Componentes Técnicos

### 1. Backend: Métodos de Obtención de Estructuras

**Ubicación:** `class-fplms-structures.php`

#### Métodos Existentes Utilizados

```php
/**
 * Obtiene empresas asociadas a una ciudad
 * @param int $city_id ID de la ciudad
 * @return array IDs de empresas
 */
public function get_companies_by_city( int $city_id ): array

/**
 * Obtiene canales asociados a una o varias empresas
 * @param array $company_ids IDs de empresas
 * @return array IDs de canales
 */
public function get_channels_by_companies( array $company_ids ): array

/**
 * Obtiene sucursales asociadas a uno o varios canales
 * @param array $channel_ids IDs de canales
 * @return array IDs de sucursales
 */
public function get_branches_by_channels( array $channel_ids ): array

/**
 * Obtiene cargos asociados a una o varias sucursales
 * @param array $branch_ids IDs de sucursales
 * @return array IDs de cargos
 */
public function get_roles_by_branches( array $branch_ids ): array
```

### 2. Endpoint AJAX para Carga Dinámica

**Acción:** `wp_ajax_fplms_get_cascade_structures`  
**Archivo:** `class-fplms-structures.php`

#### Request
```javascript
POST admin-ajax.php
{
    action: 'fplms_get_cascade_structures',
    nonce: '...',
    level: 'cities',           // Nivel desde el que se inicia la cascada
    selected_ids: [1, 2, 3],   // IDs seleccionados en ese nivel
    _ajax_nonce: '...'
}
```

#### Response
```json
{
    "success": true,
    "data": {
        "companies": {
            "5": "Empresa A",
            "6": "Empresa B"
        },
        "channels": {
            "10": "Canal 1",
            "11": "Canal 2"
        },
        "branches": {
            "20": "Sucursal X",
            "21": "Sucursal Y"
        },
        "roles": {
            "30": "Gerente",
            "31": "Vendedor"
        }
    }
}
```

### 3. Frontend: JavaScript de Cascada

**Ubicación:** `class-fplms-courses.php` → `render_course_structures_view()`

#### Funcionalidad Principal

```javascript
document.addEventListener('DOMContentLoaded', function() {
    // Elementos del DOM
    const cityCheckboxes = document.querySelectorAll('[name="fplms_course_cities[]"]');
    const companyCheckboxes = document.querySelectorAll '[name="fplms_course_companies[]"]');
    const channelCheckboxes = document.querySelectorAll('[name="fplms_course_channels[]"]');
    const branchCheckboxes = document.querySelectorAll('[name="fplms_course_branches[]"]');
    const roleCheckboxes = document.querySelectorAll('[name="fplms_course_roles[]"]');
    
    /**
     * Carga estructuras en cascada desde un nivel inicial
     */
    function loadCascadeStructures(level, selectedIds) {
        if (selectedIds.length === 0) {
            clearDescendantLevels(level);
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'fplms_get_cascade_structures');
        formData.append('nonce', '<?php echo wp_create_nonce("fplms_cascade"); ?>');
        formData.append('level', level);
        formData.append('selected_ids', JSON.stringify(selectedIds));
        
        fetch(ajaxurl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateCheckboxes('companies', data.data.companies);
                updateCheckboxes('channels', data.data.channels);
                updateCheckboxes('branches', data.data.branches);
                updateCheckboxes('roles', data.data.roles);
            }
        });
    }
    
    /**
     * Actualiza checkboxes de un nivel específico
     */
    function updateCheckboxes(level, items) {
        const container = document.getElementById(`fplms-${level}-container`);
        if (!container) return;
        
        // Limpiar contenedor
        container.innerHTML = '';
        
        // Si no hay items, mostrar mensaje
        if (Object.keys(items).length === 0) {
            container.innerHTML = '<p><em>No hay opciones disponibles para la estructura seleccionada.</em></p>';
            return;
        }
        
        // Crear checkboxes
        for (const [id, name] of Object.entries(items)) {
            const label = document.createElement('label');
            label.style.display = 'block';
            label.style.margin = '5px 0';
            
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.name = `fplms_course_${level}[]`;
            checkbox.value = id;
            checkbox.checked = true; // Pre-seleccionar
            
            label.appendChild(checkbox);
            label.appendChild(document.createTextNode(' ' + name));
            container.appendChild(label);
        }
    }
    
    /**
     * Limpia niveles descendientes cuando se deselecciona un nivel superior
     */
    function clearDescendantLevels(fromLevel) {
        const levels = ['cities', 'companies', 'channels', 'branches', 'roles'];
        const startIndex = levels.indexOf(fromLevel) + 1;
        
        for (let i = startIndex; i < levels.length; i++) {
            const container = document.getElementById(`fplms-${levels[i]}-container`);
            if (container) {
                container.innerHTML = '<p><em>Selecciona una estructura superior primero.</em></p>';
            }
        }
    }
    
    // Event listeners
    addChangeListener(cityCheckboxes, 'cities');
    addChangeListener(companyCheckboxes, 'companies');
    addChangeListener(channelCheckboxes, 'channels');
    addChangeListener(branchCheckboxes, 'branches');
    
    function addChangeListener(checkboxes, level) {
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const selected = Array.from(checkboxes)
                    .filter(cb => cb.checked)
                    .map(cb => cb.value);
                loadCascadeStructures(level, selected);
            });
        });
    }
});
```

---

## 📝 Interfaz de Usuario

### Vista Mejorada

```html
<h2>Estructuras para: Curso XYZ</h2>

<div class="fplms-cascade-info">
    ℹ️ <strong>Asignación Inteligente:</strong>
    Al seleccionar una estructura, se cargarán automáticamente todas sus estructuras relacionadas.
    Puedes ajustar la selección manualmente después de la carga automática.
</div>

<form method="post">
    <table class="form-table">
        <tr>
            <th>📍 Ciudades</th>
            <td id="fplms-cities-container">
                <label><input type="checkbox" name="fplms_course_cities[]" value="1"> Santa Cruz</label>
                <label><input type="checkbox" name="fplms_course_cities[]" value="2"> La Paz</label>
            </td>
        </tr>
        <tr>
            <th>🏢 Empresas</th>
            <td id="fplms-companies-container">
                <p><em>Selecciona una ciudad primero</em></p>
            </td>
        </tr>
        <tr>
            <th>🏪 Canales</th>
            <td id="fplms-channels-container">
                <p><em>Selecciona una empresa primero</em></p>
            </td>
        </tr>
        <tr>
            <th>🏬 Sucursales</th>
            <td id="fplms-branches-container">
                <p><em>Selecciona un canal primero</em></p>
            </td>
        </tr>
        <tr>
            <th>👔 Cargos</th>
            <td id="fplms-roles-container">
                <p><em>Selecciona una sucursal primero</em></p>
            </td>
        </tr>
    </table>
    
    <p class="submit">
        <button type="submit" class="button button-primary">💾 Guardar y Notificar</button>
    </p>
</form>
```

---

## 🔄 Flujo Completo de Uso

### Caso 1: Asignación desde Cero

1. **Usuario accede:** FairPlay LMS → Cursos → [Curso] → Estructuras
2. **Selecciona ciudad:** ☑ Santa Cruz
3. **Sistema carga automáticamente:**
   - ✅ Empresa A (Santa Cruz)
   - ✅ Empresa B (Santa Cruz)
   - ✅ Canal 1 (Empresa A)
   - ✅ Canal 2 (Empresa A)
   - ✅ Canal 3 (Empresa B)
   - ✅ Sucursal X (Canal 1)
   - ✅ Sucursal Y (Canal 2)
   - ✅ Gerente (Sucursal X)
   - ✅ Vendedor (Sucursal Y)
4. **Usuario ajusta:** Desmarca "Canal 3" (ya no quiere ese canal)
5. **Sistema recalcula:** Elimina automáticamente sucursales y cargos de "Canal 3"
6. **Usuario guarda:** Clic en "💾 Guardar y Notificar"
7. **Sistema procesa:**
   - Aplica cascada de estructuras seleccionadas
   - Guarda en `post_meta`:
     - `fplms_course_cities` → [1]
     - `fplms_course_companies` → [5, 6]
     - `fplms_course_channels` → [10, 11]
     - `fplms_course_branches` → [20, 21]
     - `fplms_course_roles` → [30, 31]
   - Envía notificaciones a usuarios de esas estructuras
   - Registra en auditoría

### Caso 2: Edición de Estructuras Existentes

1. **Usuario accede:** Curso ya tiene estructuras asignadas
2. **Sistema carga:** Todas las estructuras actuales pre-seleccionadas
3. **Usuario modifica:** Agrega nueva ciudad "La Paz"
4. **Sistema carga:** Estructuras de La Paz y las agrega a la selección
5. **Usuario guarda:** Se combinan estructuras antiguas + nuevas

---

## 🎨 Estilos CSS

```css
.fplms-cascade-info {
    background: #e7f3ff;
    border-left: 4px solid #2271b1;
    padding: 15px;
    margin: 20px 0;
    border-radius: 4px;
}

.fplms-cascade-info strong {
    color: #135e96;
}

#fplms-cities-container label,
#fplms-companies-container label,
#fplms-channels-container label,
#fplms-branches-container label,
#fplms-roles-container label {
    display: block;
    padding: 6px 10px;
    margin: 3px 0;
    background: #f9f9f9;
    border-radius: 4px;
    cursor: pointer;
    transition: background 0.2s;
}

#fplms-cities-container label:hover,
#fplms-companies-container label:hover,
#fplms-channels-container label:hover,
#fplms-branches-container label:hover,
#fplms-roles-container label:hover {
    background: #e7f3ff;
}

#fplms-cities-container input[type="checkbox"],
#fplms-companies-container input[type="checkbox"],
#fplms-channels-container input[type="checkbox"],
#fplms-branches-container input[type="checkbox"],
#fplms-roles-container input[type="checkbox"] {
    margin-right: 8px;
    vertical-align: middle;
}

.fplms-loading {
    color: #999;
    font-style: italic;
    padding: 10px;
}
```

---

## 🔍 Consideraciones Técnicas

### Pre-requisitos

1. **Categoría asignada:** El curso DEBE tener una categoría de MasterStudy asignada que esté vinculada a un canal
2. **Estructuras activas:** Solo se cargan estructuras marcadas como "activas" en el sistema
3. **Permisos:** Usuario debe tener capacidad `manage_options` o equivalente

### Validaciones

```php
// Al guardar, validar que:
1. Las estructuras seleccionadas existen y están activas
2. La jerarquía es coherente (no sucursal sin canal padre)
3. La categoría del curso está vinculada a un canal de las estructuras seleccionadas
```

### Sincronización con Categorías

```php
// Si el curso tiene categoría "Fair Play":
1. Obtener canal vinculado: get_linked_channel(category_id)
2. Verificar que el canal está en las estructuras seleccionadas
3. Si no está, agregarlo automáticamente
4. Aplicar cascada desde el canal hacia arriba (empresa, ciudad)
```

---

## 🧪 Testing

### Test 1: Carga Básica
- [ ] Seleccionar ciudad → Verificar que carga empresas
- [ ] Seleccionar empresa → Verificar que carga canales
- [ ] Seleccionar canal → Verificar que carga sucursales
- [ ] Seleccionar sucursal → Verificar que carga cargos

### Test 2: Deselección
- [ ] Deseleccionar ciudad → Verificar que limpia todos los descendientes
- [ ] Deseleccionar empresa → Verificar que limpia canales/sucursales/cargos
- [ ] Deseleccionar canal → Verificar que limpia sucursales/cargos

### Test 3: Múltiples Selecciones
- [ ] Seleccionar 2 ciudades → Verificar combinación de estructuras
- [ ] Seleccionar 3 empresas → Verificar todos los canales
- [ ] Deseleccionar 1 ciudad → Verificar eliminación parcial

### Test 4: Guardado
- [ ] Guardar estructuras → Verificar en `post_meta`
- [ ] Verificar auditoría → Revisar registro en `wp_fplms_audit_log`
- [ ] Verificar notificaciones → Comprobar envío de emails

### Test 5: Sincronización con Categorías
- [ ] Curso con categoría "Adidas" → Verificar que canal "Adidas" se incluye automáticamente
- [ ] Curso sin categoría → Verificar que permite selección libre
- [ ] Cambiar categoría → Verificar re-sincronización

---

## 📊 Diagrama de Flujo

```
        ┌─────────────────────┐
        │   Usuario accede    │
        │  a Estructuras     │
        └──────────┬──────────┘
                   │
                   ▼
        ┌─────────────────────┐
        │  ¿Tiene categoría   │
        │    asignada?        │
        └──────┬──────┬───────┘
               │ Sí   │ No
               ▼      ▼
        ┌──────────┐ ┌─────────────┐
        │Pre-cargar│ │Mostrar vacío│
        │estructuras│ │             │
        └─────┬────┘ └──────┬──────┘
              │             │
              └──────┬──────┘
                     │
                     ▼
        ┌─────────────────────┐
        │ Usuario selecciona  │
        │     estructura      │
        └──────────┬──────────┘
                   │
                   ▼
        ┌─────────────────────┐
        │  AJAX: Cargar       │
        │   descendientes     │
        └──────────┬──────────┘
                   │
                   ▼
        ┌─────────────────────┐
        │ Actualizar checkboxes│
        │  (pre-seleccionados) │
        └──────────┬──────────┘
                   │
                   ▼
        ┌─────────────────────┐
        │ Usuario ajusta      │
        │   selección         │
        └──────────┬──────────┘
                   │
                   ▼
        ┌─────────────────────┐
        │   Guardar cambios    │
        └──────────┬──────────┘
                   │
                   ▼
        ┌─────────────────────┐
        │  Aplicar cascada    │
        │  Guardar post_meta  │
        │  Enviar notifica    │
        │  Registrar auditoría│
        └─────────────────────┘
```

---

## 🚀 Próximas Mejoras

1. **Búsqueda en checkboxes:** Permitir filtrar estructuras por nombre
2. **Vista previa:** Mostrar cuántos usuarios serán notificados antes de guardar
3. **Batch selection:** Botones "Seleccionar todos" / "Deseleccionar todos" por nivel
4. **Historial:** Mostrar cambios anteriores de estructuras asignadas
5. **Templates:** Guardar combinaciones frecuentes de estructuras

---

## 📞 Soporte

Para dudas o errores relacionados con esta funcionalidad, revisar:
- `class-fplms-courses.php` → Métodos de asignación
- `class-fplms-structures.php` → Métodos de obtención de estructuras
- `wp_fplms_audit_log` → Registro de auditoría
- `CHECKLIST_VERIFICACION.md` → Pasos de verificación

---

**Última actualización:** 16 de febrero de 2026  
**Mantenedor:** Equipo FairPlay LMS
