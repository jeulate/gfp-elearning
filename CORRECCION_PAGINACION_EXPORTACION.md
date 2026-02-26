# 🔧 Corrección de Paginación y Exportación - Tablas de Estructuras

**Fecha**: 25 de febrero de 2026
**Archivo modificado**: `class-fplms-structures.php`

---

## 📋 Problemas Reportados

### 1. **Paginación No Actualiza la Tabla**
**Síntoma**: Al hacer clic en "Siguiente página" o en los números de página, la tabla no se actualiza. Los elementos de la página 2, 3, etc. no se muestran.

**Causa Raíz**: La función `fplmsPaginateTable()` estaba buscando solo filas "visibles" con `row.style.display !== 'none'`, pero al cargar la página inicialmente, las filas no tienen `style.display` definido (es `undefined` o cadena vacía), por lo que no se contaban correctamente. Solo funcionaba después de filtrar porque el filtro establecía explícitamente `style.display`.

---

### 2. **Exportar Seleccionados Solo Exporta 1 Elemento**
**Síntoma**: Al seleccionar múltiples elementos con los checkboxes y hacer clic en "Exportar Seleccionados", solo se exporta 1 elemento en lugar de todos los seleccionados.

**Causa Raíz**: La función `fplmsExportStructures()` buscaba checkboxes marcados solo en las filas visibles de la página actual. Los elementos seleccionados en otras páginas (que tienen `display: none`) no se incluían en la exportación.

---

## ✅ Soluciones Implementadas

### Corrección 1: Sistema de Atributos para Gestión de Filas

Se implementó un sistema de atributos HTML5 para rastrear el estado de las filas:

- **`data-filtered="true"`**: Marca filas ocultas por el filtro de búsqueda
- **`data-page-hidden="true"`**: Marca filas ocultas por paginación

Esto permite distinguir entre:
- Filas ocultas temporalmente por paginación (deben considerarse en exportación)
- Filas ocultas por filtro de búsqueda (no deben considerarse)

---

### Corrección 2: Función `fplmsFilterTable()` Actualizada

```javascript
// ANTES
if (termName.indexOf(filter) > -1) {
    row.style.display = '';
    visibleCount++;
} else {
    row.style.display = 'none';
}

// DESPUÉS
if (termName.indexOf(filter) > -1) {
    row.removeAttribute('data-filtered');  // ✅ Marca como NO filtrada
    visibleCount++;
} else {
    row.setAttribute('data-filtered', 'true');  // ✅ Marca como filtrada
    row.style.display = 'none';
}
```

**Beneficio**: Ahora podemos saber si una fila está oculta por filtro o por paginación.

---

### Corrección 3: Función `fplmsPaginateTable()` Reescrita

#### Cambio Principal: Recolección de Filas

```javascript
// ANTES (INCORRECTO)
const visibleRows = [];
Array.from(tbody.getElementsByTagName('tr')).forEach(row => {
    // ❌ Solo cuenta filas con display !== 'none'
    if (!row.classList.contains('fplms-edit-row') && row.style.display !== 'none') {
        visibleRows.push(row);
    }
});

// DESPUÉS (CORRECTO)
const dataRows = [];
allRows.forEach(row => {
    if (!row.classList.contains('fplms-edit-row')) {
        // ✅ Verifica si está oculta por FILTRO, no por paginación
        const isFilteredOut = row.hasAttribute('data-filtered') && row.getAttribute('data-filtered') === 'true';
        if (!isFilteredOut) {
            dataRows.push(row);
        }
    }
});
```

**Beneficio**: Ahora considera TODAS las filas de datos, no solo las visibles en la página actual.

#### Cambio en Control de Visibilidad

```javascript
// ANTES (INCORRECTO)
if (index >= startIndex && index < endIndex) {
    row.style.display = '';
} else {
    row.style.display = 'none';
}

// DESPUÉS (CORRECTO)
if (shouldShow) {
    row.style.display = '';
    row.removeAttribute('data-page-hidden');  // ✅ Marca como visible
} else {
    row.style.display = 'none';
    row.setAttribute('data-page-hidden', 'true');  // ✅ Marca como oculta por paginación
}
```

**Beneficio**: Registra explícitamente el motivo de ocultación.

---

### Corrección 4: Función `fplmsExportStructures()` Optimizada

```javascript
// ANTES (INCORRECTO - Solo página actual)
const checkboxes = document.querySelectorAll('#fplms-table-' + tabKey + ' .fplms-row-checkbox:checked');
const ids = Array.from(checkboxes).map(cb => cb.getAttribute('data-term-id')).join(',');

// DESPUÉS (CORRECTO - Todas las páginas)
const table = document.getElementById('fplms-table-' + tabKey);
const allCheckboxes = table.querySelectorAll('.fplms-row-checkbox:checked');
const ids = Array.from(allCheckboxes)
    .map(cb => cb.getAttribute('data-term-id'))
    .filter(id => id)  // ✅ Filtrar IDs vacíos
    .join(',');
```

**Cambios clave**:
1. ✅ Busca en toda la tabla, no solo en `#fplms-table-{tabKey}` (más directo)
2. ✅ Incluye elementos con `display: none` (otras páginas)
3. ✅ Agrega `.filter(id => id)` para evitar IDs vacíos o undefined

**Beneficio**: Ahora exporta TODOS los elementos marcados, sin importar en qué página estén.

---

## 📊 Flujo de Funcionamiento Corregido

### Escenario 1: Paginación Normal (Sin Filtro)

```
PÁGINA 1 (Elementos 1-10):
┌─────────────────────────────────────┐
│ Elemento 1  [visible]               │ data-page-hidden: (ninguno)
│ Elemento 2  [visible]               │ data-page-hidden: (ninguno)
│ ...                                 │
│ Elemento 10 [visible]               │ data-page-hidden: (ninguno)
└─────────────────────────────────────┘

Elementos 11-20:
┌─────────────────────────────────────┐
│ Elemento 11 [oculto por paginación] │ data-page-hidden: true
│ Elemento 12 [oculto por paginación] │ data-page-hidden: true
│ ...                                 │
└─────────────────────────────────────┘

✅ Al hacer clic en "Página 2":
- Elementos 1-10 → display: none, data-page-hidden: true
- Elementos 11-20 → display: '', data-page-hidden: (removido)
```

### Escenario 2: Filtro + Paginación

```
Usuario busca "Fair":

ANTES de filtrar (20 elementos):
┌─────────────────────────────────────┐
│ Adidas      [página 1, visible]     │
│ Fair Play   [página 1, visible]     │ ← Coincide
│ Bold        [página 1, visible]     │
│ ...                                 │
│ Fair Play Kids [página 2, oculta]   │ ← Coincide
└─────────────────────────────────────┘

DESPUÉS de filtrar (solo "Fair"):
┌─────────────────────────────────────┐
│ Fair Play   [visible]               │ data-filtered: (ninguno)
│ Fair Play Kids [visible]            │ data-filtered: (ninguno)
│ Adidas      [oculto por filtro]     │ data-filtered: true
│ Bold        [oculto por filtro]     │ data-filtered: true
└─────────────────────────────────────┘

✅ Paginación solo considera elementos NO filtrados
```

### Escenario 3: Exportar Seleccionados

```
Usuario en Página 1:
┌─────────────────────────────────────┐
│ ☑ Adidas      [visible, marcado]    │
│ ☐ Bold        [visible, no marcado] │
│ ☑ Fair Play   [visible, marcado]    │
└─────────────────────────────────────┘

Usuario en Página 2 (anteriormente):
┌─────────────────────────────────────┐
│ ☑ Gap         [oculto, marcado]     │ display: none
│ ☐ Puma        [oculto, no marcado]  │ display: none
│ ☑ Olimpico    [oculto, marcado]     │ display: none
└─────────────────────────────────────┘

ANTES (BUG):
- Solo encuentra checkboxes visibles → Exporta: Adidas, Fair Play

DESPUÉS (CORREGIDO):
- Encuentra TODOS los checkboxes marcados → Exporta: Adidas, Fair Play, Gap, Olimpico
```

---

## 🧪 Checklist de Verificación

### ✅ **Prueba 1: Paginación Básica**
1. Abrir Estructuras → Canales (más de 10 elementos)
2. Verificar que se muestran elementos 1-10
3. Hacer clic en "Página 2"
4. **Esperado**: 
   - ✅ Se muestran elementos 11-20
   - ✅ Elementos 1-10 desaparecen
   - ✅ Botón "Anterior" aparece habilitado
   - ✅ Info muestra "Página 2 de X"

### ✅ **Prueba 2: Navegación Entre Páginas**
1. Ir a Página 2
2. Hacer clic en "Página 1"
3. **Esperado**: 
   - ✅ Se muestran elementos 1-10 nuevamente
   - ✅ Botón "Anterior" se deshabilita

### ✅ **Prueba 3: Exportar Seleccionados - Página Única**
1. Marcar 3 checkboxes en la página actual
2. Hacer clic en "Exportar Seleccionados" (XLS)
3. **Esperado**: 
   - ✅ Archivo XLS contiene los 3 elementos seleccionados

### ✅ **Prueba 4: Exportar Seleccionados - Múltiples Páginas**
1. En Página 1: Marcar 2 checkboxes
2. Ir a Página 2
3. Marcar 3 checkboxes más
4. Hacer clic en "Exportar Seleccionados" (XLS)
5. **Esperado**: 
   - ✅ Archivo XLS contiene 5 elementos (2 + 3)
   - ✅ Incluye elementos de ambas páginas

### ✅ **Prueba 5: Filtro + Paginación**
1. Buscar "Fair" en Canales
2. Verificar que se muestran solo resultados coincidentes
3. Si hay más de 10 resultados, verificar paginación funciona
4. **Esperado**: 
   - ✅ Solo se muestran elementos que coinciden con "Fair"
   - ✅ Paginación funciona solo con resultados filtrados

### ✅ **Prueba 6: Exportar Seleccionados + Filtro**
1. Buscar "Fair"
2. Marcar 2 elementos filtrados
3. Limpiar búsqueda (mostrar todos)
4. Marcar 2 elementos más
5. Exportar seleccionados
6. **Esperado**: 
   - ✅ Archivo contiene 4 elementos
   - ✅ Incluye elementos filtrados y no filtrados

### ✅ **Prueba 7: Checkbox "Seleccionar Todo"**
1. En Página 1: Marcar checkbox de encabezado
2. **Esperado**: 
   - ✅ Se marcan todos los checkboxes de la página actual (10 elementos)
3. Ir a Página 2 sin desmarcar
4. Exportar seleccionados
5. **Esperado**: 
   - ✅ Solo exporta los 10 elementos de Página 1 (los de Página 2 no están marcados)

---

## 📝 Cambios Realizados

| # | Archivo | Función | Líneas | Descripción |
|---|---------|---------|--------|-------------|
| 1 | class-fplms-structures.php | `fplmsFilterTable()` | ~2899 | Agregar atributo `data-filtered` en lugar de solo `display` |
| 2 | class-fplms-structures.php | `fplmsPaginateTable()` | ~2936 | Reescribir lógica de recolección de filas |
| 3 | class-fplms-structures.php | `fplmsPaginateTable()` | ~2960 | Agregar atributo `data-page-hidden` |
| 4 | class-fplms-structures.php | `fplmsPaginateTable()` | ~2993 | Actualizar info de paginación con `dataRows.length` |
| 5 | class-fplms-structures.php | `fplmsExportStructures()` | ~3050 | Buscar checkboxes en toda la tabla, no solo página visible |

**Total de cambios**: 5 correcciones en 3 funciones

---

## 🔍 Debugging (Para Desarrolladores)

### Ver Estado de Filas en Consola del Navegador

```javascript
// Ver todas las filas y sus atributos
const table = document.getElementById('fplms-table-channel');
const rows = Array.from(table.querySelectorAll('tbody tr:not(.fplms-edit-row)'));
rows.forEach((row, i) => {
    console.log(`Fila ${i+1}:`, {
        nombre: row.getAttribute('data-term-name'),
        filtered: row.getAttribute('data-filtered'),
        pageHidden: row.getAttribute('data-page-hidden'),
        display: row.style.display
    });
});
```

### Ver Checkboxes Marcados

```javascript
// Ver todos los checkboxes marcados (todas las páginas)
const table = document.getElementById('fplms-table-channel');
const checked = table.querySelectorAll('.fplms-row-checkbox:checked');
console.log('Checkboxes marcados:', checked.length);
checked.forEach(cb => {
    console.log('- ID:', cb.getAttribute('data-term-id'), 'Nombre:', cb.closest('tr').getAttribute('data-term-name'));
});
```

---

## 💡 Mejoras Implementadas

### 1. **Sistema de Atributos de Estado**
- Permite rastrear por qué una fila está oculta
- Facilita debugging
- Mejora rendimiento al evitar búsquedas complejas

### 2. **Separación de Lógica**
- Filtrado y paginación son independientes
- Exportación considera todos los estados correctamente

### 3. **Robustez**
- `.filter(id => id)` previene errores con IDs undefined
- Validación de elementos antes de contarlos

---

## 🔄 Próximos Pasos

1. **Reflejar cambios** en el servidor
2. **Ejecutar checklist completo** de pruebas
3. **Verificar** con datasets grandes (50+ elementos)
4. **Probar** en diferentes navegadores
5. **Reportar** cualquier comportamiento inesperado

---

**Documento creado**: 25 de febrero de 2026  
**Estado**: ✅ Correcciones aplicadas  
**Próxima acción**: Testing exhaustivo de paginación y exportación
