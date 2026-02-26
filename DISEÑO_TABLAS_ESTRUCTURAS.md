# 🎨 Implementación de Tablas con Paginación y Exportación - Estructuras

## 📋 Resumen de Cambios Requeridos

Esta implementación transforma la visualización de estructuras jerárquicas de una lista simple a **tablas profesionales** con:

### ✅ Funcionalidades a Implementar

1. **Tabla HTML con columnas específicas**:
   - **Ciudades**: ☑ | Nombre | Descripción | Estado | Acciones
   - **Empresas**: ☑ | Nombre | Descripción | Relación (Ciudades) | Estado | Acciones
   - **Canales**: ☑ | Nombre | Descripción | Relación (Empresas) | Estado | Acciones
   - **Sucursales**: ☑ | Nombre | Descripción | Relación (Canales) | Estado | Acciones
   - **Cargos**: ☑ | Nombre | Descripción | Relación (Sucursales) | Estado | Acciones

2. **Barra de búsqueda** en tiempo real por nombre

3. **Paginación** con controles (anterior, siguient

e, selección de página)

4. **Checkboxes** para selección múltiple

5. **Exportación**:
   - Exportar TODO (todas las estructuras del tipo)
   - Exportar SELECCIONADAS (solo las marcadas)
   - Formatos: XLS y PDF

---

## 🏗️ Arquitectura de la Implementación

### Archivos a Modificar:

1. **includes/class-fplms-structures.php**
   - Cambiar renderizado de lista a tabla
   - Agregar controles de búsqueda y exportación
   - Mantener formularios de edición (en filas expandibles)

2. **JavaScript (inline en el mismo archivo)**
   - Función de búsqueda en tiempo real
   - Paginación funcional
   - Selección de checkboxes
   - Manejo de exportación

3. **CSS (inline en el mismo archivo)**
   - Estilos de tabla responsive
   - Estilos de paginación
   - Estilos de controles

4. **Funciones de Exportación (nuevas funciones en clase)**
   - `export_structures_excel()` - Generar archivo XLS
   - `export_structures_pdf()` - Generar archivo PDF
   - `handle_export_request()` - Manejar solicitudes de exportación

---

## 📊 Estructura de Tabla HTML

### Ejemplo: Ciudades

```html
<div class="fplms-table-controls">
    <div class="fplms-search-box">
        <input type="text" 
               id="fplms-search-city" 
               placeholder="🔍 Buscar ciudad..."
               onkeyup="fplmsFilterTable('city')">
    </div>
    <div class="fplms-export-buttons">
        <button onclick="fplmsSelectAll('city')" class="button">
            ☑ Seleccionar Todo
        </button>
        <button onclick="fplmsExport('city', 'all', 'xls')" class="button">
            📊 Exportar Todo (XLS)
        </button>
        <button onclick="fplmsExport('city', 'selected', 'xls')" class="button">
            📊 Exportar Selección (XLS)
        </button>
        <button onclick="fplmsExport('city', 'all', 'pdf')" class="button">
            📄 Exportar Todo (PDF)
        </button>
        <button onclick="fplmsExport('city', 'selected', 'pdf')" class="button">
            📄 Exportar Selección (PDF)
        </button>
    </div>
</div>

<table class="fplms-data-table" id="fplms-table-city">
    <thead>
        <tr>
            <th style="width: 40px;">
                <input type="checkbox" 
                       id="fplms-select-all-city" 
                       onclick="fplmsToggleAll('city', this.checked)">
            </th>
            <th>Nombre</th>
            <th>Descripción</th>
            <th style="width: 100px;">Estado</th>
            <th style="width: 150px;">Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($terms as $term): ?>
        <tr data-term-id="<?= $term->term_id ?>" 
            data-term-name="<?= esc_attr($term->name) ?>">
            <td>
                <input type="checkbox" 
                       class="fplms-row-checkbox" 
                       value="<?= $term->term_id ?>">
            </td>
            <td>
                <strong><?= esc_html($term->name) ?></strong>
            </td>
            <td>
                <?php
                $description = get_term_meta($term->term_id, FairPlay_LMS_Config::META_TERM_DESCRIPTION, true);
                echo esc_html(wp_trim_words($description, 10, '...'));
                ?>
            </td>
            <td>
                <span class="fplms-status-badge <?= $active ? 'active' : 'inactive' ?>">
                    <?= $active ? '✓ Activo' : '✗ Inactivo' ?>
                </span>
            </td>
            <td class="fplms-actions-cell">
                <button onclick="fplmsToggleStatus(<?= $term->term_id ?>)" 
                        class="fplms-btn-icon" 
                        title="Cambiar estado">
                    <?= $active ? '⊙' : '○' ?>
                </button>
                <button onclick="fplmsEditRow(<?= $term->term_id ?>)" 
                        class="fplms-btn-icon" 
                        title="Editar">
                    ✏️
                </button>
                <button onclick="fplmsDeleteRow(<?= $term->term_id ?>)" 
                        class="fplms-btn-icon" 
                        title="Eliminar">
                    🗑️
                </button>
            </td>
        </tr>
        <!-- Fila expandible para edición -->
        <tr id="fplms-edit-row-<?= $term->term_id ?>" class="fplms-edit-row" style="display: none;">
            <td colspan="5">
                <!-- Formulario de edición aquí -->
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="fplms-pagination" id="fplms-pagination-city">
    <button onclick="fplmsPrevPage('city')" class="button">← Anterior</button>
    <span class="fplms-page-info">
        Página <span id="fplms-current-page-city">1</span> de 
        <span id="fplms-total-pages-city">1</span>
    </span>
    <button onclick="fplmsNextPage('city')"class="button">Siguiente →</button>
</div>
```

---

## 🎨 CSS para Tablas

```css
/* Contenedor de controles */
.fplms-table-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding: 15px;
    background: #f5f5f5;
    border-radius: 4px;
    flex-wrap: wrap;
    gap: 10px;
}

.fplms-search-box input {
    width: 300px;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.fplms-export-buttons {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

/* Tabla de datos */
.fplms-data-table {
    width: 100%;
   border-collapse: collapse;
    background: white;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.fplms-data-table thead {
    background: #0073aa;
    color: white;
}

.fplms-data-table th {
    padding: 12px;
    text-align: left;
    font-weight: 600;
    font-size: 13px;
    border-bottom: 2px solid #005a87;
}

.fplms-data-table tbody tr {
    border-bottom: 1px solid #e5e5e5;
    transition: background 0.2s;
}

.fplms-data-table tbody tr:hover {
    background: #f9f9f9;
}

.fplms-data-table td {
    padding: 12px;
    font-size: 13px;
    vertical-align: middle;
}

/* Status badge */
.fplms-status-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
    display: inline-block;
}

.fplms-status-badge.active {
    background: #d4edda;
    color: #155724;
}

.fplms-status-badge.inactive {
    background: #f8d7da;
    color: #721c24;
}

/* Botones de acción */
.fplms-actions-cell {
    display: flex;
    gap: 5px;
}

.fplms-btn-icon {
    background: none;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 4px 8px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s;
}

.fplms-btn-icon:hover {
    background: #0073aa;
    border-color: #0073aa;
    transform: scale(1.1);
}

/* Paginación */
.fplms-pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 15px;
    margin-top: 20px;
    padding: 15px;
}

.fplms-page-info {
    font-size: 14px;
    color: #666;
}

/* Fila de edición expandible */
.fplms-edit-row td {
    padding: 20px !important;
    background: #f9f9f9;
}

/* Responsive */
@media (max-width: 768px) {
    .fplms-table-controls {
        flex-direction: column;
        align-items: stretch;
    }
    
    .fplms-search-box input {
        width: 100%;
    }
    
    .fplms-export-buttons {
        justify-content: center;
    }
    
    .fplms-data-table {
        font-size: 12px;
    }
    
    .fplms-data-table th,
    .fplms-data-table td {
        padding: 8px;
    }
}
```

---

## ⚙️ JavaScript para Funcionalidad

### 1. Búsqueda en Tiempo Real

```javascript
let fplmsTableState = {
    city: { currentPage: 1, rowsPerPage: 10, filteredRows: [] },
    company: { currentPage: 1, rowsPerPage: 10, filteredRows: [] },
    channel: { currentPage: 1, rowsPerPage: 10, filteredRows: [] },
    branch: { currentPage: 1, rowsPerPage: 10, filteredRows: [] },
    role: { currentPage: 1, rowsPerPage: 10, filteredRows: [] }
};

function fplmsFilterTable(tableType) {
    const searchInput = document.getElementById(`fplms-search-${tableType}`);
    const filter = searchInput.value.toUpperCase();
    const table = document.getElementById(`fplms-table-${tableType}`);
    const tbody = table.getElementsByTagName('tbody')[0];
    const rows = Array.from(tbody.getElementsByTagName('tr')).filter(row => 
        !row.classList.contains('fplms-edit-row')
    );
    
    let visibleRows = [];
    
    rows.forEach(row => {
        const termName = row.getAttribute('data-term-name') || '';
        if (termName.toUpperCase().indexOf(filter) > -1) {
            visibleRows.push(row);
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
    
    fplmsTableState[tableType].filteredRows = visibleRows;
    fplmsTableState[tableType].currentPage = 1;
    fplmsPaginateTable(tableType);
}
```

### 2. Paginación

```javascript
function fplmsPaginateTable(tableType) {
    const state = fplmsTableState[tableType];
    const rows = state.filteredRows.length > 0 ? 
        state.filteredRows : 
        Array.from(document.querySelectorAll(`#fplms-table-${tableType} tbody tr:not(.fplms-edit-row)`));
    
    const totalPages = Math.ceil(rows.length / state.rowsPerPage);
    const start = (state.currentPage - 1) * state.rowsPerPage;
    const end = start + state.rowsPerPage;
    
    rows.forEach((row, index) => {
        if (index >= start && index < end) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
    
    document.getElementById(`fplms-current-page-${tableType}`).textContent = state.currentPage;
    document.getElementById(`fplms-total-pages-${tableType}`).textContent = totalPages;
}

function fplmsNextPage(tableType) {
    const state = fplmsTableState[tableType];
    const rows = state.filteredRows.length > 0 ? state.filteredRows : 
        Array.from(document.querySelectorAll(`#fplms-table-${tableType} tbody tr:not(.fplms-edit-row)`));
    const totalPages = Math.ceil(rows.length / state.rowsPerPage);
    
    if (state.currentPage < totalPages) {
        state.currentPage++;
        fplmsPaginateTable(tableType);
    }
}

function fplmsPrevPage(tableType) {
    const state = fplmsTableState[tableType];
    
    if (state.currentPage > 1) {
        state.currentPage--;
        fplmsPaginateTable(tableType);
    }
}
```

### 3. Selección de Checkboxes

```javascript
function fplmsToggleAll(tableType, checked) {
    const table = document.getElementById(`fplms-table-${tableType}`);
    const checkboxes = table.querySelectorAll('.fplms-row-checkbox');
    
    checkboxes.forEach(checkbox => {
        // Solo marcar las filas visibles
        const row = checkbox.closest('tr');
        if (row.style.display !== 'none') {
            checkbox.checked = checked;
        }
    });
}

function fplmsSelectAll(tableType) {
    const selectAllCheckbox = document.getElementById(`fplms-select-all-${tableType}`);
    selectAllCheckbox.checked = true;
    fplmsToggleAll(tableType, true);
}

function fplmsGetSelectedIds(tableType) {
    const table = document.getElementById(`fplms-table-${tableType}`);
    const checkboxes = table.querySelectorAll('.fplms-row-checkbox:checked');
    
    return Array.from(checkboxes).map(cb => cb.value);
}
```

### 4. Exportación

```javascript
function fplmsExport(tableType, mode, format) {
    let termIds = [];
    
    if (mode === 'selected') {
        termIds = fplmsGetSelectedIds(tableType);
        
        if (termIds.length === 0) {
            alert('Por favor, selecciona al menos un elemento para exportar.');
            return;
        }
    }
    
    // Crear formulario para enviar petición
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '';
    
    // Nonce
    const nonceInput = document.createElement('input');
    nonceInput.type = 'hidden';
    nonceInput.name = 'fplms_export_nonce';
    nonceInput.value = '<?php echo wp_create_nonce("fplms_export_structures"); ?>';
    form.appendChild(nonceInput);
    
    // Action
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'fplms_export_action';
    actionInput.value = 'export_structures';
    form.appendChild(actionInput);
    
    // Table Type
    const typeInput = document.createElement('input');
    typeInput.type = 'hidden';
    typeInput.name = 'fplms_export_type';
    typeInput.value = tableType;
    form.appendChild(typeInput);
    
    // Format
    const formatInput = document.createElement('input');
    formatInput.type = 'hidden';
    formatInput.name = 'fplms_export_format';
    formatInput.value = format;
    form.appendChild(formatInput);
    
    // Mode
    const modeInput = document.createElement('input');
    modeInput.type = 'hidden';
    modeInput.name = 'fplms_export_mode';
    modeInput.value = mode;
    form.appendChild(modeInput);
    
    // Term IDs (si es selección)
    if (mode === 'selected') {
        termIds.forEach(id => {
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'fplms_export_ids[]';
            idInput.value = id;
            form.appendChild(idInput);
        });
    }
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}
```

---

## 📦 Funciones PHP de Exportación

### 1. Handler Principal

```php
/**
 * Manejar solicitudes de exportación
 */
public function handle_export_request(): void {
    if (!isset($_POST['fplms_export_action']) || $_POST['fplms_export_action'] !== 'export_structures') {
        return;
    }
    
    if (!isset($_POST['fplms_export_nonce']) || !wp_verify_nonce($_POST['fplms_export_nonce'], 'fplms_export_structures')) {
        wp_die('Nonce inválido');
    }
    
    if (!current_user_can(FairPlay_LMS_Config::CAP_MANAGE_STRUCTURES)) {
        wp_die('No tienes permisos');
    }
    
    $type = sanitize_text_field(wp_unslash($_POST['fplms_export_type'] ?? ''));
    $format = sanitize_text_field(wp_unslash($_POST['fplms_export_format'] ?? 'xls'));
    $mode = sanitize_text_field(wp_unslash($_POST['fplms_export_mode'] ?? 'all'));
    
    $term_ids = [];
    if ($mode === 'selected' && !empty($_POST['fplms_export_ids'])) {
        $term_ids = array_map('absint', (array)$_POST['fplms_export_ids']);
    }
    
    if ($format === 'xls') {
        $this->export_structures_excel($type, $term_ids);
    } else {
        $this->export_structures_pdf($type, $term_ids);
    }
    
    exit;
}
```

### 2. Exportación a Excel (CSV mejorado)

```php
/**
 * Exportar estructuras a formato Excel (CSV UTF-8 con BOM)
 */
private function export_structures_excel(string $type, array $term_ids = []): void {
    $taxonomy_map = [
        'city' => FairPlay_LMS_Config::TAX_CITY,
        'company' => FairPlay_LMS_Config::TAX_COMPANY,
        'channel' => FairPlay_LMS_Config::TAX_CHANNEL,
        'branch' => FairPlay_LMS_Config::TAX_BRANCH,
        'role' => FairPlay_LMS_Config::TAX_ROLE,
    ];
    
    $label_map = [
        'city' => 'Ciudades',
        'company' => 'Empresas',
        'channel' => 'Canales',
        'branch' => 'Sucursales',
        'role' => 'Cargos',
    ];
    
    if (!isset($taxonomy_map[$type])) {
        wp_die('Tipo inválido');
    }
    
    $taxonomy = $taxonomy_map[$type];
    $label = $label_map[$type];
    
    // Obtener términos
    $args = [
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
    ];
    
    if (!empty($term_ids)) {
        $args['include'] = $term_ids;
    }
    
    $terms = get_terms($args);
    
    if (is_wp_error($terms) || empty($terms)) {
        wp_die('No hay datos para exportar');
    }
    
    // Preparar headers
    $filename = "fplms-{$type}-" . date('Y-m-d-His') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // UTF-8 BOM para Excel
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    
    // Headers de columnas
    $headers = ['ID', 'Nombre', 'Descripción', 'Estado'];
    
    if ($type !== 'city') {
        $relation_labels = [
            'company' => 'Ciudades',
            'channel' => 'Empresas',
            'branch' => 'Canales',
            'role' => 'Sucursales',
        ];
        $headers[] = $relation_labels[$type];
    }
    
    fputcsv($output, $headers);
    
    // Datos
    foreach ($terms as $term) {
        $active = get_term_meta($term->term_id, FairPlay_LMS_Config::META_ACTIVE, true);
        $description = get_term_meta($term->term_id, FairPlay_LMS_Config::META_TERM_DESCRIPTION, true);
        
        $row = [
            $term->term_id,
            $term->name,
            $description ?: '',
            $active === '1' ? 'Activo' : 'Inactivo',
        ];
        
        // Agregar relaciones
        if ($type !== 'city') {
            $relations = [];
            if ($type === 'company') {
                $parent_ids = $this->get_term_cities($term->term_id);
            } elseif ($type === 'channel') {
                $parent_ids = $this->get_term_companies($term->term_id);
            } elseif ($type === 'branch') {
                $parent_ids = $this->get_term_channels($term->term_id);
            } else {
                $parent_ids = $this->get_term_branches($term->term_id);
            }
            
            foreach ($parent_ids as $parent_id) {
                $parent_name = $this->get_term_name_by_id($parent_id);
                if ($parent_name) {
                    $relations[] = $parent_name;
                }
            }
            
            $row[] = implode(', ', $relations);
        }
        
        fputcsv($output, $row);
    }
    
    fclose($output);
}
```

### 3. Exportación a PDF

```php
/**
 * Exportar estructuras a formato PDF
 */
private function export_structures_pdf(string $type, array $term_ids = []): void {
    // Similar a Excel pero generando HTML y convirtiéndolo a PDF
    // Por ahora, una implementación simple sin librerías externas
    
    $taxonomy_map = [
        'city' => FairPlay_LMS_Config::TAX_CITY,
        'company' => FairPlay_LMS_Config::TAX_COMPANY,
        'channel' => FairPlay_LMS_Config::TAX_CHANNEL,
        'branch' => FairPlay_LMS_Config::TAX_BRANCH,
        'role' => FairPlay_LMS_Config::TAX_ROLE,
    ];
    
    $label_map = [
        'city' => 'Ciudades',
        'company' => 'Empresas',
        'channel' => 'Canales',
        'branch' => 'Sucursales',
        'role' => 'Cargos',
    ];
    
    if (!isset($taxonomy_map[$type])) {
        wp_die('Tipo inválido');
    }
    
    $taxonomy = $taxonomy_map[$type];
    $label = $label_map[$type];
    
    // Obtener términos
    $args = [
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
    ];
    
    if (!empty($term_ids)) {
        $args['include'] = $term_ids;
    }
    
    $terms = get_terms($args);
    
    if (is_wp_error($terms) || empty($terms)) {
        wp_die('No hay datos para exportar');
    }
    
    // Generar HTML para impresión
    $filename = "fplms-{$type}-" . date('Y-m-d-His') . '.pdf';
    
    // Por ahora, usaremos HTML con CSS de impresión que el navegador convierte a PDF
    header('Content-Type: text/html; charset=utf-8');
    
    echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . esc_html($label) . ' - FairPlay LMS</title>
    <style>
        @page { size: A4; margin: 1cm; }
        body { font-family: Arial, sans-serif; font-size: 11pt; }
        h1 { text-align: center; color: #0073aa; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #0073aa; color: white; padding: 8px; text-align: left; }
        td { border-bottom: 1px solid #ddd; padding: 8px; }
        tr:nth-child(even) { background: #f9f9f9; }
        .status-active { color: #155724; font-weight: bold; }
        .status-inactive { color: #721c24; }
        @media print {
            button { display: none; }
        }
    </style>
</head>
<body>
    <button onclick="window.print()" style="margin: 10px; padding: 10px 20px; background: #0073aa; color: white; border: none; cursor: pointer;">
        Imprimir / Guardar como PDF
    </button>
    <h1>📊 ' . esc_html($label) . '</h1>
    <p style="text-align: center; color: #666;">Generado el ' . date('d/m/Y H:i:s') . '</p>
    
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nombre</th>
                <th>Descripción</th>
                <th>Estado</th>';
    
    if ($type !== 'city') {
        $relation_labels = [
            'company' => 'Ciudades',
            'channel' => 'Empresas',
            'branch' => 'Canales',
            'role' => 'Sucursales',
        ];
        echo '<th>' . esc_html($relation_labels[$type]) . '</th>';
    }
    
    echo '</tr>
        </thead>
        <tbody>';
    
    foreach ($terms as $term) {
        $active = get_term_meta($term->term_id, FairPlay_LMS_Config::META_ACTIVE, true);
        $description = get_term_meta($term->term_id, FairPlay_LMS_Config::META_TERM_DESCRIPTION, true);
        $status_class = $active === '1' ? 'status-active' : 'status-inactive';
        $status_text = $active === '1' ? 'Activo' : 'Inactivo';
        
        echo '<tr>';
        echo '<td>' . esc_html($term->term_id) . '</td>';
        echo '<td><strong>' . esc_html($term->name) . '</strong></td>';
        echo '<td>' . esc_html($description ?: '-') . '</td>';
        echo '<td class="' . $status_class . '">' . $status_text . '</td>';
        
        if ($type !== 'city') {
            $relations = [];
            if ($type === 'company') {
                $parent_ids = $this->get_term_cities($term->term_id);
            } elseif ($type === 'channel') {
                $parent_ids = $this->get_term_companies($term->term_id);
            } elseif ($type === 'branch') {
                $parent_ids = $this->get_term_channels($term->term_id);
            } else {
                $parent_ids = $this->get_term_branches($term->term_id);
            }
            
            foreach ($parent_ids as $parent_id) {
                $parent_name = $this->get_term_name_by_id($parent_id);
                if ($parent_name) {
                    $relations[] = $parent_name;
                }
            }
            
            echo '<td>' . esc_html(implode(', ', $relations) ?: '-') . '</td>';
        }
        
        echo '</tr>';
    }
    
    echo '</tbody>
    </table>
    
    <script>
        // Auto-abrir diálogo de impresión después de cargar
        window.onload = function() {
            setTimeout(() => {
                // Comentar para que no abra automáticamente
                // window.print();
            }, 500);
        };
    </script>
</body>
</html>';
    
    exit;
}
```

---

## 🔧 Integración en Constructor de Clase

### Registrar Handler de Exportación

```php
// En el constructor de FairPlay_LMS_Plugin o en init hooks
add_action('admin_init', [$this->structures, 'handle_export_request']);
```

---

## ✅ Checklist de Implementación

- [ ] Reemplazar HTML de lista por tabla en accordion-body
- [ ] Agregar controles de búsqueda y exportación
- [ ] Implementar CSS para tablas responsive
- [ ] Agregar JavaScript de búsqueda en tiempo real
- [ ] Agregar JavaScript de paginación
- [ ] Agregar JavaScript de selección de checkboxes
- [ ] Implementar función PHP `handle_export_request()`
- [ ] Implementar función PHP `export_structures_excel()`
- [ ] Implementar función PHP `export_structures_pdf()`
- [ ] Registrar handler en hooks de WordPress
- [ ] Probar búsqueda por nombre
- [ ] Probar paginación
- [ ] Probar selección múltiple
- [ ] Probar exportación XLS (todas)
- [ ] Probar exportación XLS (seleccionadas)
- [ ] Probar exportación PDF (todas)
- [ ] Probar exportación PDF (seleccionadas)
- [ ] Verificar responsive en móvil

---

## 📝 Notas de Implementación

### Consideraciones:

1. **Performance**: Con paginación de 10 elementos por página, el sistema es eficiente incluso con 100+ elementos

2. **Búsqueda**: Se realiza en el cliente (JavaScript) para ser instantánea. Si hay miles de elementos, considerar búsqueda en el servidor con AJAX

3. **Exportación Excel**: Se usa CSV con UTF-8 BOM para compatibilidad perfecta con Excel

4. **Exportación PDF**: Se genera HTML con CSS de impresión. El usuario usa "Imprimir > Guardar como PDF" del navegador. Para PDF real sin interacción, se necesitaría una librería como TCPDF o DomPDF

5. **Checkboxes**: Se mantienen al paginar. La selección se almacena en JavaScript hasta que se exporta

6. **Editar inline**: Los formularios de edición se mantienen en filas expandibles debajo de cada elemento

---

**Estado Actual**: DOCUMENTACIÓN COMPLETA - PENDIENTE IMPLEMENTACIÓN DE CÓDIGO

Esta es una gran refactorización. ¿Quieres que proceda con la implementación del código o prefieres que primero revisemos el diseño/estructura propuesta?
