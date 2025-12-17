# 🧪 Guía de Testing - Frontend Mejorado de Estructuras

## Resumen de Cambios Implementados

Se han realizado las siguientes mejoras al sistema de estructuras (ciudades, canales, sucursales, cargos):

1. **Tabla mejorada con columna de Ciudad**: Muestra a qué ciudad pertenece cada elemento
2. **Botón Editar**: Abre un modal para editar nombre y ciudad sin recargar página
3. **Sistema de multi-ciudad**: Permite tener el mismo canal en diferentes ciudades como elementos independientes
4. **Método helper**: `get_terms_with_cities()` para identificar estructuras duplicadas

---

## 📋 Plan de Testing

### Fase 1: Verificación Visual (UI)

#### Test 1.1: Visualizar Tabla con Ciudad
**Objetivo**: Confirmar que la tabla muestra la columna de ciudad

**Pasos**:
1. Ir a WordPress Admin → FairPlay LMS → Estructuras
2. Haz clic en pestaña "Canales / Franquicias"
3. Observa la tabla

**Resultado Esperado**:
```
┌─────────────────────┬──────────────┬────────┬─────────────┐
│ Nombre              │ Ciudad       │ Activo │ Acciones    │
├─────────────────────┼──────────────┼────────┼─────────────┤
│ (canal 1)           │ (ciudad 1)   │ Sí/No  │ [↓] [✎]    │
│ (canal 2)           │ (ciudad 2)   │ Sí/No  │ [↓] [✎]    │
└─────────────────────┴──────────────┴────────┴─────────────┘
```

**Verificar**:
- ✅ Columna "Ciudad" está visible (excepto en pestaña Ciudades)
- ✅ Muestra nombre de ciudad real (no ID)
- ✅ Si no tiene ciudad, muestra "Sin asignar" en itálica
- ✅ Botones Desactivar/Editar presentes

---

#### Test 1.2: Columnas en Diferentes Pestañas
**Objetivo**: Verificar que las columnas se adaptan a cada pestaña

**Pasos**:
1. Haz clic en pestaña "Ciudades"
2. Observa la tabla
3. Haz clic en "Sucursales"
4. Observa la tabla
5. Haz clic en "Cargos"
6. Observa la tabla

**Resultado Esperado**:
- Pestaña **Ciudades**: Columnas = Nombre | Activo | Acciones (sin Ciudad)
- Pestaña **Canales**: Columnas = Nombre | Ciudad | Activo | Acciones
- Pestaña **Sucursales**: Columnas = Nombre | Ciudad | Activo | Acciones
- Pestaña **Cargos**: Columnas = Nombre | Ciudad | Activo | Acciones

**Verificar**:
- ✅ No aparece columna Ciudad en pestaña Ciudades
- ✅ Colspan correcto en mensaje "No hay registros"

---

### Fase 2: Modal de Edición

#### Test 2.1: Abrir Modal
**Objetivo**: Verificar que el botón Editar abre el modal correctamente

**Pasos**:
1. Ve a pestaña Canales / Franquicias
2. Haz clic en botón "Editar" de cualquier fila
3. Observa lo que sucede

**Resultado Esperado**:
- Modal aparece en el centro de la pantalla
- Fondo oscuro semi-transparente
- Título: "Editar Estructura"
- Campos visibles:
  - Input "Nombre" con valor actual
  - Select "Ciudad" con opciones (para no-Ciudades)
  - Botones: "Cancelar" y "Guardar Cambios"

**Verificar**:
- ✅ Modal está visible
- ✅ Campos pre-rellenos con datos actuales
- ✅ El valor de nombre es correcto
- ✅ La ciudad seleccionada es la correcta

---

#### Test 2.2: Campo Ciudad Condicional
**Objetivo**: Verificar que el campo Ciudad solo aparece cuando aplica

**Pasos**:
1. Ve a pestaña "Ciudades"
2. Edita una ciudad
3. Observa si aparece campo "Ciudad" en el modal
4. Ve a pestaña "Canales"
5. Edita un canal
6. Observa si aparece campo "Ciudad" en el modal

**Resultado Esperado**:
- Pestaña Ciudades → Modal: Campo Ciudad NO aparece
- Pestaña Canales → Modal: Campo Ciudad SÍ aparece

**Verificar**:
- ✅ Campo Ciudad oculto en modal para Ciudades
- ✅ Campo Ciudad visible en modal para Canales/Sucursales/Cargos

---

#### Test 2.3: Cerrar Modal
**Objetivo**: Verificar formas de cerrar el modal

**Pasos**:

**Forma 1 - Botón Cancelar**:
1. Abre modal con Editar
2. Haz clic en botón "Cancelar"
3. Modal debe cerrarse

**Forma 2 - Clic Fuera**:
1. Abre modal con Editar
2. Haz clic fuera del modal (en el fondo oscuro)
3. Modal debe cerrarse

**Forma 3 - Tecla Escape** (opcional en navegadores):
1. Abre modal con Editar
2. Presiona tecla ESC en teclado
3. Modal debería cerrarse (si está implementado)

**Resultado Esperado**:
- El modal se cierra
- Vuelves a ver la tabla
- No se guardan cambios si no hiciste clic "Guardar Cambios"

**Verificar**:
- ✅ Botón "Cancelar" cierra modal
- ✅ Clic en fondo oscuro cierra modal
- ✅ La tabla sigue igual (sin cambios)

---

### Fase 3: Funcionalidad de Edición

#### Test 3.1: Editar Nombre
**Objetivo**: Verificar que se puede editar el nombre de una estructura

**Pasos**:
1. Ve a pestaña Canales / Franquicias
2. Anota el nombre actual de un canal (ej: "Canal Original")
3. Haz clic en "Editar"
4. Modal se abre
5. Cambia el nombre a "Canal Modificado"
6. Haz clic en "Guardar Cambios"
7. Observa si la página se recarga

**Resultado Esperado**:
- La página se recarga
- La tabla muestra el nuevo nombre "Canal Modificado"
- El cambio se persiste en la base de datos

**Verificar**:
- ✅ El nombre cambió en la tabla
- ✅ No apareció error
- ✅ Sigue en la misma pestaña

---

#### Test 3.2: Editar Ciudad
**Objetivo**: Verificar que se puede cambiar la ciudad de una estructura

**Pasos**:
1. Ve a pestaña Canales / Franquicias
2. Busca un canal que esté asignado a una ciudad (ej: Bogotá)
3. Anota su nombre y ciudad actual
4. Haz clic en "Editar"
5. En el modal, cambia la ciudad a otra (ej: Medellín)
6. Haz clic en "Guardar Cambios"

**Resultado Esperado**:
- La página se recarga
- El mismo canal ahora muestra la nueva ciudad (Medellín)
- Aparece solo UNA fila con el nuevo nombre y ciudad

**Verificar**:
- ✅ La ciudad cambió en la tabla
- ✅ El nombre del canal se mantiene igual
- ✅ Aparece solo una fila (no duplicado)

---

#### Test 3.3: Validaciones del Formulario
**Objetivo**: Verificar que los campos obligatorios están validados

**Pasos**:
1. Abre modal de edición
2. Borra el contenido del campo "Nombre"
3. Intenta hacer clic en "Guardar Cambios"

**Resultado Esperado**:
- El navegador muestra validación HTML5
- No permite guardar si el nombre está vacío

**Verificar**:
- ✅ Campo Nombre tiene atributo `required`
- ✅ No se envía formulario vacío

---

### Fase 4: Escenarios Multi-Zona

#### Test 4.1: Crear Mismo Canal en Diferentes Ciudades
**Objetivo**: Verificar que se pueden crear estructuras con mismo nombre en diferentes ciudades

**Pasos**:
1. Ve a pestaña Canales / Franquicias
2. Crea un nuevo canal llamado "Canal Premium"
3. Selecciona ciudad "Bogotá"
4. Guarda
5. Crea OTRO canal con mismo nombre "Canal Premium"
6. Selecciona ciudad "Medellín"
7. Guarda
8. Observa la tabla

**Resultado Esperado**:
```
┌──────────────────┬──────────────┬────────┬─────────────┐
│ Nombre           │ Ciudad       │ Activo │ Acciones    │
├──────────────────┼──────────────┼────────┼─────────────┤
│ Canal Premium    │ Bogotá       │ Sí     │ [↓] [✎]    │
│ Canal Premium    │ Medellín     │ Sí     │ [↓] [✎]    │
└──────────────────┴──────────────┴────────┴─────────────┘
```

**Verificar**:
- ✅ Aparecen DOS filas con el mismo nombre
- ✅ Cada una con su ciudad correspondiente
- ✅ Son elementos independientes

---

#### Test 4.2: Editar Cada Copia Independientemente
**Objetivo**: Verificar que se pueden editar independientemente

**Pasos**:
1. Tienes "Canal Premium" en Bogotá y Medellín
2. Haz clic en "Editar" del Canal Premium en Bogotá
3. Cambia nombre a "Canal Premium Plus"
4. Guarda
5. Observa la tabla

**Resultado Esperado**:
```
┌──────────────────────┬──────────────┬────────┬─────────────┐
│ Nombre               │ Ciudad       │ Activo │ Acciones    │
├──────────────────────┼──────────────┼────────┼─────────────┤
│ Canal Premium Plus   │ Bogotá       │ Sí     │ [↓] [✎]    │
│ Canal Premium        │ Medellín     │ Sí     │ [↓] [✎]    │
└──────────────────────┴──────────────┴────────┴─────────────┘
```

**Verificar**:
- ✅ Solo la de Bogotá cambió
- ✅ La de Medellín sigue igual
- ✅ Fueron editadas de forma independiente

---

### Fase 5: Verificación en Base de Datos (Opcional - para desarrolladores)

#### Test 5.1: Verificar wp_termmeta
**Objetivo**: Confirmar que los cambios se guardan en la BD

**Pasos**:
1. En terminal o herramienta de BD, ejecuta:
```sql
SELECT 
    t.term_id,
    t.name,
    tm.meta_key,
    tm.meta_value
FROM wp_terms t
LEFT JOIN wp_termmeta tm ON t.term_id = tm.term_id
WHERE t.taxonomy IN ('fplms_channel', 'fplms_city')
ORDER BY t.term_id, tm.meta_key;
```

**Resultado Esperado**:
- Los términos editados aparecen con los nombres nuevos
- Meta key `fplms_parent_city` tiene el valor correcto
- Meta key `fplms_active` tiene valores 0 o 1

---

### Fase 6: Integración con Cursos

#### Test 6.1: Seleccionar Múltiples Ciudades para Curso
**Objetivo**: Verificar que el curso usa correctamente las estructuras

**Pasos**:
1. Ve a Cursos / Editar un curso
2. Sección "Asignar estructuras"
3. Selecciona múltiples ciudades
4. Verifica que los canales se cargan dinámicamente
5. Selecciona un canal que existe en una de esas ciudades
6. Guarda el curso

**Resultado Esperado**:
- Los canales disponibles corresponden a las ciudades seleccionadas
- El curso se asigna correctamente a usuarios de esas ciudades

---

## 📊 Matriz de Validación

| Test | Descripción | Estado | Notas |
|------|-------------|--------|-------|
| 1.1 | Tabla muestra columna Ciudad | 🔄 | Por validar |
| 1.2 | Columnas adaptables por pestaña | 🔄 | Por validar |
| 2.1 | Modal abre correctamente | 🔄 | Por validar |
| 2.2 | Campo Ciudad condicional | 🔄 | Por validar |
| 2.3 | Modal cierra correctamente | 🔄 | Por validar |
| 3.1 | Editar nombre funciona | 🔄 | Por validar |
| 3.2 | Editar ciudad funciona | 🔄 | Por validar |
| 3.3 | Validaciones del formulario | 🔄 | Por validar |
| 4.1 | Crear mismo canal en diferentes ciudades | 🔄 | Por validar |
| 4.2 | Editar copias independientemente | 🔄 | Por validar |
| 5.1 | BD guarda cambios correctamente | 🔄 | Por validar |
| 6.1 | Integración con asignación de cursos | 🔄 | Por validar |

---

## 🔍 Checklist de Bugs Potenciales

### Problemas Comunes a Verificar:

- [ ] Modal no aparece al hacer clic en Editar
- [ ] Campos del modal no se pre-rellenan
- [ ] Botón Guardar no hace nada
- [ ] La página se recarga pero no hay cambios
- [ ] Campo Ciudad muestra en pestaña Ciudades (no debería)
- [ ] Error al guardar (revisar logs de WordPress)
- [ ] Nonce inválido en formulario
- [ ] El nombre guardado no es el que escribí
- [ ] La ciudad guardada es diferente a la que seleccioné
- [ ] Aparecen errores en consola del navegador (F12)

---

## 📝 Documentación de Código

### Métodos Nuevos/Modificados:

#### 1. `render_page()` - Mejoras
- Agregada columna condicional "Ciudad"
- Agregado botón "Editar" con llamada a `fplmsEditStructure()`
- Agregado HTML del modal de edición
- Agregadas funciones JavaScript para modal

#### 2. `handle_form()` - Nueva acción 'edit'
```php
if ('edit' === $action) {
    // Obtiene term_id y nombre
    // Valida inputs
    // Llama wp_update_term() para actualizar nombre
    // Llama save_hierarchy_relation() para actualizar ciudad
    // Redirecciona a misma pestaña
}
```

#### 3. `get_terms_with_cities()` - Nuevo método
```php
public function get_terms_with_cities(string $taxonomy): array {
    // Retorna array con:
    // term_id => [
    //     'name' => nombre del término,
    //     'city' => ciudad_id relacionada,
    //     'active' => estado del término
    // ]
}
```

### Funciones JavaScript:

#### `fplmsEditStructure(termId, termName, cityId, taxonomy)`
- Pre-rellena el modal con datos del término
- Muestra/oculta campo Ciudad según taxonomía
- Abre el modal

#### `fplmsCloseEditModal()`
- Cierra el modal
- Oculta overlay

---

## 🚀 Próximos Pasos

Después de completar todos los tests:

1. [ ] Documentar cualquier bug encontrado
2. [ ] Ajustar CSS si es necesario
3. [ ] Optimizar rendimiento si hay muchas estructuras
4. [ ] Implementar historial de cambios (opcional)
5. [ ] Agregar bulk edit (opcional)

---

## 📞 Contacto y Soporte

Si encuentras algún problema:

1. Revisa los logs de WordPress en `/wp-content/debug.log`
2. Abre la consola del navegador (F12) y busca errores JavaScript
3. Verifica que tienes permisos suficientes
4. Consulta la base de datos directamente para verificar datos

---

**Última Actualización**: [Fecha de hoy]
**Versión**: 1.0
**Estado**: Pendiente Testing
