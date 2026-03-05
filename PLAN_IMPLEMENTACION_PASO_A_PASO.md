# 📋 PLAN DE IMPLEMENTACIÓN PASO A PASO
## Modernización de render_users_page()

---

## ✅ FASE 0: PREPARACIÓN (OPCIONAL)
**Objetivo:** Crear backup de seguridad  
**Tiempo:** 1 minuto  
**Cambios:** 0 líneas  
**Acción:** Copiar `class-fplms-users.php` → `class-fplms-users.php.backup`

---

## 🎨 FASE 1: ESTILOS CSS MODERNOS
**Objetivo:** Agregar estilos CSS al inicio de la función  
**Tiempo:** 5 minutos  
**Ubicación:** Después de abrir `<style>` tag  
**Líneas afectadas:** ~100 líneas nuevas de CSS  
**Riesgo:** ⚠️ BAJO - Solo agrega estilos, no modifica funcionalidad  

**Qué agrega:**
- Estilos para wrapper y container
- Estilos para header modernizado
- Estilos para botones de acción
- Estilos para controles de tabla
- Estilos para búsqueda y per_page
- Estilos para acciones masivas
- Estilos para tabla mejorada
- Estilos para paginación
- Estilos para modales
- Estilos para filtros colapsables
- Media queries responsive

**Verificación:**
- [ ] La página carga sin errores
- [ ] No hay cambios visuales todavía (estilos no se usan aún)

---

## 🎯 FASE 2: HEADER Y BOTONES
**Objetivo:** Modernizar el header de la página  
**Tiempo:** 5 minutos  
**Ubicación:** Reemplazar apertura `<div class="wrap">`  
**Líneas afectadas:** ~40 líneas  
**Riesgo:** ⚠️ BAJO - Cambio visual, funcionalidad se mantiene  

**Qué cambia:**
- Header con icono 👥 y título "Gestión de Usuarios"
- Botones modernos "➕ Crear Usuario" y "🔐 Matriz de Privilegios"
- Diseño con flexbox y gradientes
- Event listeners en JavaScript para mostrar/ocultar secciones

**Verificación:**
- [ ] El header se ve moderno con iconos
- [ ] Los botones "Crear Usuario" y "Matriz de Privilegios" funcionan
- [ ] Las secciones se muestran/ocultan correctamente

---

## 🔽 FASE 3: FILTROS COLAPSABLES
**Objetivo:** Hacer que los filtros sean colapsables  
**Tiempo:** 5 minutos  
**Ubicación:** Antes de la tabla de usuarios  
**Líneas afectadas:** ~60 líneas  
**Riesgo:** ⚠️ BAJO - Mejora UX, funcionalidad se mantiene  

**Qué agrega:**
- Botón "🔍 Filtros por Estructura" para toggle
- Sección de filtros colapsable con clase `.active`
- Botón "✖ Limpiar Filtros" visible cuando hay filtros activos
- Función JavaScript `fplmsToggleFilters()`

**Verificación:**
- [ ] El botón de filtros aparece
- [ ] Al hacer clic, los filtros se expanden/colapsan
- [ ] Los filtros se mantienen expandidos si hay filtros activos
- [ ] El botón "Limpiar Filtros" funciona

---

## 🔍 FASE 4: BÚSQUEDA Y PER_PAGE
**Objetivo:** Agregar búsqueda y selector de registros por página  
**Tiempo:** 10 minutos  
**Ubicación:** Antes de la tabla  
**Líneas afectadas:** ~30 líneas HTML + ~50 líneas JS  
**Riesgo:** ⚠️ MEDIO - Agrega funcionalidad nueva  

**Qué agrega:**
- Input de búsqueda con placeholder "🔍 Buscar por nombre..."
- Select de per_page (10/20/50/100)
- Función JavaScript `fplmsSearchUsers(query)`
- Data attributes en filas: `data-user-name`, `data-id-usuario`, etc.

**Verificación:**
- [ ] El input de búsqueda aparece y funciona
- [ ] Al escribir, filtra usuarios en tiempo real
- [ ] El selector per_page aparece
- [ ] No hay errores en consola del navegador

---

## ☑️ FASE 5: ACCIONES MASIVAS (BARRA)
**Objetivo:** Agregar barra de acciones masivas  
**Tiempo:** 10 minutos  
**Ubicación:** Después de controles de tabla  
**Líneas afectadas:** ~20 líneas HTML  
**Riesgo:** ⚠️ BAJO - Solo UI, sin funcionalidad todavía  

**Qué agrega:**
- Barra oculta por defecto (`display: none`)
- Contador de seleccionados
- Dropdown con 3 acciones (activar/desactivar/eliminar)
- Botón "Aplicar"
- Se mostrará cuando haya checkboxes marcados

**Verificación:**
- [ ] La barra NO se muestra por defecto
- [ ] Los elementos tienen los IDs correctos
- [ ] No hay errores visuales

---

## 📊 FASE 6: TABLA CON CHECKBOXES
**Objetivo:** Agregar checkboxes a la tabla  
**Tiempo:** 15 minutos  
**Ubicación:** Tabla de usuarios (`<thead>` y `<tbody>`)  
**Líneas afectadas:** ~10 líneas en thead, ~5 en cada fila  
**Riesgo:** ⚠️⚠️ MEDIO - Modifica estructura de tabla  

**Qué agrega:**
- Columna nueva al inicio con checkbox "Seleccionar todos"
- Checkbox en cada fila de usuario
- Data attributes en cada `<tr>`:
  - `data-user-id`
  - `data-user-name`
  - `data-user-login`
  - `data-id-usuario`
  - `data-user-email`
- Función `onchange="fplmsUpdateBulkCount()"`

**Verificación:**
- [ ] La tabla muestra una columna extra al inicio
- [ ] Cada usuario tiene un checkbox funcional
- [ ] El checkbox "seleccionar todos" aparece en el header
- [ ] Los estilos se ven bien (sin desalinear)

---

## 📄 FASE 7: PAGINACIÓN
**Objetivo:** Agregar paginación dinámica  
**Tiempo:** 10 minutos  
**Ubicación:** Después de la tabla  
**Líneas afectadas:** ~5 líneas HTML + ~80 líneas JS  
**Riesgo:** ⚠️⚠️ MEDIO - Oculta filas automáticamente  

**Qué agrega:**
- Div `<div id="fplms-pagination">` después de la tabla
- Función JavaScript `fplmsPaginate(page, perPage)`
- Inicialización al cargar: `fplmsPaginate(1, 10)`
- Controles de página (Anterior, números, Siguiente)

**Verificación:**
- [ ] La paginación aparece debajo de la tabla
- [ ] Muestra "Página 1 de X (N usuarios)"
- [ ] Solo se ven 10 usuarios por defecto
- [ ] Al hacer clic en números, cambia de página
- [ ] Los botones Anterior/Siguiente funcionan

---

## ⚡ FASE 8: JAVASCRIPT COMPLETO
**Objetivo:** Agregar funciones de acciones masivas y modales  
**Tiempo:** 15 minutos  
**Ubicación:** Bloque `<script>` al final  
**Líneas afectadas:** ~150 líneas JS  
**Riesgo:** ⚠️⚠️⚠️ ALTO - Conecta con backend  

**Qué agrega:**
- `fplmsToggleAllCheckboxes(checked)` - Seleccionar todos
- `fplmsUpdateBulkCount()` - Actualizar contador
- `fplmsApplyBulkAction()` - Validar y preparar acción
- `fplmsShowBulkConfirmModal()` - Modal de confirmación
- `fplmsCloseBulkModal()` - Cerrar modal
- `fplmsConfirmBulkAction()` - Ejecutar acción (POST)

**Verificación:**
- [ ] Al marcar checkboxes, aparece la barra de acciones
- [ ] El contador actualiza correctamente
- [ ] Al seleccionar acción y clic en "Aplicar", aparece modal
- [ ] Modal muestra mensaje correcto según acción
- [ ] Al confirmar, se envía formulario POST
- [ ] Redirige con mensaje de éxito/error
- [ ] **PRUEBA REAL:** Activar 2-3 usuarios de prueba
- [ ] **PRUEBA REAL:** Desactivar 2-3 usuarios de prueba
- [ ] **PRUEBA REAL:** Eliminar 1 usuario de prueba
- [ ] Bitácora registra las acciones correctamente

---

## 📊 RESUMEN POR FASES

| Fase | Nombre | Tiempo | Riesgo | Líneas | Reversible |
|------|--------|--------|--------|--------|------------|
| 0 | Preparación | 1 min | ✅ Ninguno | 0 | ✅ Sí |
| 1 | CSS Estilos | 5 min | ⚠️ Bajo | ~100 | ✅ Sí |
| 2 | Header/Botones | 5 min | ⚠️ Bajo | ~40 | ✅ Sí |
| 3 | Filtros Colapsables | 5 min | ⚠️ Bajo | ~60 | ✅ Sí |
| 4 | Búsqueda/PerPage | 10 min | ⚠️ Medio | ~80 | ✅ Sí |
| 5 | Barra Acciones | 10 min | ⚠️ Bajo | ~20 | ✅ Sí |
| 6 | Tabla Checkboxes | 15 min | ⚠️⚠️ Medio | ~50 | ⚠️ Difícil |
| 7 | Paginación | 10 min | ⚠️⚠️ Medio | ~85 | ✅ Sí |
| 8 | JS Completo | 15 min | ⚠️⚠️⚠️ Alto | ~150 | ✅ Sí |
| **TOTAL** | | **76 min** | | **~585** | |

---

## 🎯 ESTRATEGIA DE IMPLEMENTACIÓN

### Orden recomendado:
1. **Fase 0** → Crear backup
2. **Fases 1-3** → Cambios visuales seguros (juntas en 1 sesión)
3. **Fase 4** → Búsqueda (probar independientemente)
4. **Fases 5-6** → Acciones masivas UI (juntas)
5. **Fase 7** → Paginación (probar independientemente)
6. **Fase 8** → JS completo (probar exhaustivamente)

### Puntos de "checkpoint" para probar:
- ✅ Después de Fase 3: Verificar visualmente
- ✅ Después de Fase 4: Probar búsqueda
- ✅ Después de Fase 6: Verificar tabla con checkboxes
- ✅ Después de Fase 7: Probar paginación
- ✅ Después de Fase 8: **Prueba completa de acciones masivas**

### Si algo falla:
1. No continúes con la siguiente fase
2. Revisa errores en consola (F12)
3. Restaura desde backup si es necesario
4. Ajusta el código antes de continuar

---

## 🚀 ¿LISTO PARA EMPEZAR?

Confirma que deseas comenzar y empezaremos con la **Fase 0** (backup) seguida de la **Fase 1** (CSS).

---

**Última actualización:** 2024  
**Autor:** GitHub Copilot
