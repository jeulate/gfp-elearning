# Guía de Testing - Sistema Jerárquico de Estructuras

## 🎯 Objetivo

Validar que el sistema de relaciones jerárquicas funciona correctamente en todas sus fases:
1. ✅ Creación de estructuras con relaciones
2. ✅ Carga dinámica de opciones (AJAX)
3. ✅ Guardado y recuperación de datos
4. ✅ Base de datos

---

## 📋 Test 1: Crear Estructuras Base (10 min)

### Objetivo
Verificar que se pueden crear estructuras con relaciones jerárquicas.

### Pasos

**1.1 Crear Ciudades**

```
Ir a: FairPlay LMS → Estructuras → Tab "Ciudades"

Crear:
1. Nombre: "Bogotá" | Activo: ✓ | Guardar
2. Nombre: "Medellín" | Activo: ✓ | Guardar
3. Nombre: "Cali" | Activo: ✓ | Guardar
```

**Validación:**
- ✅ Aparecen en tabla
- ✅ Status = "Sí"
- ✅ Pueden desactivarse

**Resultado esperado:**
```
TABLA:
Nombre      | Activo | Acciones
Bogotá      | Sí     | [Desactivar]
Medellín    | Sí     | [Desactivar]
Cali        | Sí     | [Desactivar]
```

---

**1.2 Crear Canales en Bogotá**

```
Ir a: FairPlay LMS → Estructuras → Tab "Canales / Franquicias"

Crear:
1. Nombre: "Canal A" 
   Ciudad: "Bogotá" ← NUEVA OPCIÓN
   Activo: ✓ 
   Guardar

2. Nombre: "Canal B"
   Ciudad: "Bogotá"
   Activo: ✓
   Guardar
```

**Validación:**
- ✅ Campo "Ciudad relacionada" aparece (NO aparece en tabla, es la pestaña base)
- ✅ Debe seleccionar una ciudad
- ✅ Canales aparecen en tabla

**Resultado esperado:**
```
TABLA:
Nombre  | Activo | Acciones
Canal A | Sí     | [Desactivar]
Canal B | Sí     | [Desactivar]

(Sin mostrar la ciudad en tabla, se filtra por meta)
```

---

**1.3 Crear Canal A en Medellín**

```
Ir a: FairPlay LMS → Estructuras → Tab "Canales / Franquicias"

Crear:
1. Nombre: "Canal A" 
   Ciudad: "Medellín" ← ¡MISMO NOMBRE, DIFERENTE CIUDAD!
   Activo: ✓ 
   Guardar
```

**Validación:**
- ✅ Se crea exitosamente (WordPress permite dos términos con mismo nombre en misma taxonomía)
- ✅ Tienen diferentes IDs en BD
- ✅ Se distinguen por su ciudad padre

**Nota:** Pueden tener el mismo nombre porque se guardan en la misma taxonomía pero con diferente meta.

---

**1.4 Crear Sucursales**

```
Ir a: FairPlay LMS → Estructuras → Tab "Sucursales"

En Bogotá:
1. Nombre: "Sucursal Centro" | Ciudad: "Bogotá" | Activo: ✓
2. Nombre: "Sucursal Sur" | Ciudad: "Bogotá" | Activo: ✓

En Medellín:
3. Nombre: "Sucursal Centro" | Ciudad: "Medellín" | Activo: ✓ ← Mismo nombre
4. Nombre: "Sucursal Sabaneta" | Ciudad: "Medellín" | Activo: ✓
```

**Validación:**
- ✅ Sucursales se crean en ambas ciudades
- ✅ Mismo nombre permitido en diferentes ciudades

---

**1.5 Crear Cargos**

```
Ir a: FairPlay LMS → Estructuras → Tab "Cargos"

En Bogotá:
1. Nombre: "Gerente" | Ciudad: "Bogotá" | Activo: ✓
2. Nombre: "Vendedor" | Ciudad: "Bogotá" | Activo: ✓
3. Nombre: "Operario" | Ciudad: "Bogotá" | Activo: ✓

En Medellín:
4. Nombre: "Gerente" | Ciudad: "Medellín" | Activo: ✓ ← Mismo nombre
5. Nombre: "Asesor" | Ciudad: "Medellín" | Activo: ✓
```

**Validación:**
- ✅ Cargos se crean correctamente
- ✅ Mismo nombre permitido

---

### Resumen Test 1

**Resultado esperado: ✅ TODOS LOS DATOS CREADOS**

```
Ciudades: 3 (Bogotá, Medellín, Cali)
Canales: 3 (2 "Canal A" + 1 "Canal B")
Sucursales: 4 (2 "Centro" + 2 otras)
Cargos: 5 (2 "Gerente" + 3 otros)
```

---

## 📋 Test 2: AJAX Dinámico (15 min)

### Objetivo
Verificar que las opciones se cargan dinámicamente cuando selecciona una ciudad.

### Pasos

**2.1 Navegar a Asignar Estructuras**

```
Ir a: FairPlay LMS → Cursos
Seleccionar cualquier curso
Botón: "Asignar Estructuras" (o similar según interfaz)
```

**Validación:**
- ✅ Página se carga
- ✅ Sección "Ciudades" tiene checkboxes
- ✅ Secciones "Canales", "Sucursales", "Cargos" muestran placeholders

**Estado inicial:**
```
[ ] Bogotá
[ ] Medellín  
[ ] Cali

Canales: "Selecciona una ciudad para ver sus canales"
Sucursales: "Selecciona una ciudad para ver sus sucursales"
Cargos: "Selecciona una ciudad para ver sus cargos"
```

---

**2.2 Marcar Bogotá y Verificar AJAX**

```
Acción: Hacer clic en checkbox "Bogotá"

Esperar 1-2 segundos mientras carga...
```

**Validación:**
- ✅ Los placeholders desaparecen
- ✅ Aparecen opciones dinámicas:
  - Canales: "Canal A", "Canal B"
  - Sucursales: "Sucursal Centro", "Sucursal Sur"
  - Cargos: "Gerente", "Vendedor", "Operario"
- ✅ No hay errores en consola (F12)

**Resultado esperado:**
```
[✓] Bogotá
[ ] Medellín
[ ] Cali

Canales:
  [ ] Canal A
  [ ] Canal B

Sucursales:
  [ ] Sucursal Centro
  [ ] Sucursal Sur

Cargos:
  [ ] Gerente
  [ ] Vendedor
  [ ] Operario
```

---

**2.3 Desmarcar Bogotá y Marcar Medellín**

```
Acción:
1. Hacer clic en checkbox "Bogotá" para desmarcar
2. Hacer clic en checkbox "Medellín" para marcar
3. Esperar 1-2 segundos
```

**Validación:**
- ✅ Las opciones de Bogotá desaparecen
- ✅ Aparecen DIFERENTES opciones de Medellín:
  - Canales: "Canal A" (el de Medellín, NO "Canal B")
  - Sucursales: "Sucursal Centro", "Sucursal Sabaneta"
  - Cargos: "Gerente", "Asesor"

**Resultado esperado:**
```
[ ] Bogotá
[✓] Medellín
[ ] Cali

Canales:
  [ ] Canal A          ← DIFERENTE (solo la de Medellín)

Sucursales:
  [ ] Sucursal Centro  ← DIFERENTE (la de Medellín)
  [ ] Sucursal Sabaneta

Cargos:
  [ ] Gerente
  [ ] Asesor           ← DIFERENTE
```

---

**2.4 Marcar Múltiples Ciudades**

```
Acción:
1. Marcar ✓ Bogotá
2. Esperar a que cargue
3. Marcar TAMBIÉN ✓ Medellín (Bogotá sigue marcado)
4. Esperar a que cargue nuevamente
```

**Validación:**
- ✅ Las opciones se actualizan
- ✅ Se muestran opciones de la última ciudad seleccionada
- ✅ NO duplica opciones

**Nota:** Cuando marcas múltiples ciudades, el frontend muestra opciones de la última. En guardado se guardan todas las seleccionadas.

---

### Resumen Test 2

**Resultado esperado: ✅ AJAX FUNCIONA**

- ✅ Carga dinámica sin recargar
- ✅ Diferentes opciones según ciudad
- ✅ Sin errores en consola
- ✅ Transiciones suaves

---

## 📋 Test 3: Guardar y Recuperar (10 min)

### Objetivo
Verificar que se guardan y recuperan correctamente los datos.

### Pasos

**3.1 Guardar Configuración Simple**

```
En página "Asignar Estructuras":

1. Marcar: [✓] Bogotá
2. Esperar AJAX
3. En la sección de "Canales", marcar: [ ] Canal A
4. En la sección de "Sucursales", dejar VACÍO (todos)
5. En la sección de "Cargos", marcar: [✓] Gerente

Configuración final:
✓ Bogotá
  └─ Canal A (específico)
  └─ Todas las sucursales
  └─ Gerente (específico)

6. Hacer clic en "Guardar estructuras"
```

**Validación:**
- ✅ Se muestra mensaje de éxito o redirecciona
- ✅ Sin errores

---

**3.2 Editar Curso y Verificar Valores**

```
Acción:
1. Ir a FairPlay LMS → Cursos
2. Buscar el MISMO curso
3. Hacer clic en "Asignar Estructuras" nuevamente
```

**Validación:**
- ✅ Bogotá está MARCADO
- ✅ Canales: "Canal A" está MARCADO
- ✅ Sucursales: NINGUNO está marcado (todos, por defecto)
- ✅ Cargos: "Gerente" está MARCADO

**Resultado esperado:**
```
[✓] Bogotá
[ ] Medellín
[ ] Cali

Canales:
  [✓] Canal A         ← Recuperado
  [ ] Canal B

Sucursales:
  [ ] Sucursal Centro ← Ninguno marcado (todos)
  [ ] Sucursal Sur

Cargos:
  [✓] Gerente         ← Recuperado
  [ ] Vendedor
  [ ] Operario
```

---

**3.3 Modificar Configuración**

```
Acción:
1. Desmarcar "Gerente" en Cargos
2. Marcar "Vendedor"
3. Marcar también "Medellín" en Ciudades
4. Esperar AJAX
5. En Medellín, marcar "Canal A"
6. Guardar
```

**Validación:**
- ✅ Se guarda sin errores
- ✅ Editar nuevamente muestra cambios correctos

---

### Resumen Test 3

**Resultado esperado: ✅ GUARDAR/RECUPERAR FUNCIONA**

- ✅ Valores se guardan correctamente
- ✅ Se recuperan al editar
- ✅ Múltiples ciudades se manejan bien
- ✅ Sin duplicados

---

## 📋 Test 4: Base de Datos (Avanzado - 15 min)

### Objetivo
Verificar que los datos se guardan correctamente en la BD.

### Pasos

**4.1 Conectar a Base de Datos**

```
Usar: phpMyAdmin, Adminer, WorkBench o similar

Base de datos: WordPress (la tuya)
Conectar...
```

---

**4.2 Verificar Términos**

```
Query 1: Ver todos los términos de canales
SELECT t.term_id, t.name, t.slug 
FROM wp_terms t 
WHERE t.term_id IN (
  SELECT term_id FROM wp_term_taxonomy 
  WHERE taxonomy = 'fplms_channel'
)
ORDER BY t.term_id;

Resultado esperado:
term_id | name     | slug
--------|----------|----------
10      | Canal A  | canal-a
11      | Canal B  | canal-b
12      | Canal A  | canal-a-2 (o similar)
```

---

**4.3 Verificar Meta (Relaciones)**

```
Query 2: Ver relaciones de canales con ciudades
SELECT t.term_id, t.name, tm.meta_key, tm.meta_value 
FROM wp_terms t
JOIN wp_termmeta tm ON t.term_id = tm.term_id
WHERE tm.meta_key = 'fplms_parent_city'
ORDER BY t.term_id;

Resultado esperado:
term_id | name    | meta_key           | meta_value
--------|---------|-------------------|----------
10      | Canal A | fplms_parent_city | 1         (Bogotá)
11      | Canal B | fplms_parent_city | 1         (Bogotá)
12      | Canal A | fplms_parent_city | 2         (Medellín)
```

---

**4.4 Verificar Asignaciones de Cursos**

```
Query 3: Ver qué estructuras tiene asignado un curso
SELECT post_id, meta_key, meta_value
FROM wp_postmeta
WHERE post_id = 5  (← reemplaza con tu curso ID)
AND meta_key LIKE 'fplms_course_%';

Resultado esperado:
post_id | meta_key              | meta_value
--------|----------------------|-----------
5       | fplms_course_cities  | 1         (Bogotá)
5       | fplms_course_channels| a:1:{i:0;i:10;} (Canal A, ID 10)
5       | fplms_course_branches| a:0:{}  (todos)
5       | fplms_course_roles   | a:1:{i:0;i:15;} (Gerente, ID 15)
```

---

**4.5 Queries Útiles**

```
Query 4: Todos los canales de una ciudad
SELECT t1.term_id, t1.name 
FROM wp_terms t1
JOIN wp_termmeta tm ON t1.term_id = tm.term_id
WHERE tm.meta_key = 'fplms_parent_city' 
AND tm.meta_value = 1  (← Bogotá)
AND t1.term_id IN (
  SELECT term_id FROM wp_term_taxonomy 
  WHERE taxonomy = 'fplms_channel'
);

Resultado esperado:
term_id | name
--------|----------
10      | Canal A
11      | Canal B
```

```
Query 5: ¿A qué ciudad pertenece un término?
SELECT * FROM wp_termmeta 
WHERE term_id = 10  (← Canal A)
AND meta_key = 'fplms_parent_city';

Resultado esperado:
meta_id | term_id | meta_key          | meta_value
--------|---------|------------------|----------
45      | 10      | fplms_parent_city | 1         (Bogotá)
```

---

### Resumen Test 4

**Resultado esperado: ✅ BD CORRECTA**

- ✅ Términos creados con IDs únicos
- ✅ Meta relationships guardadas
- ✅ Mismo nombre en diferentes ciudades con diferentes IDs
- ✅ Post meta de cursos contiene arrays de IDs correctos

---

## 🧪 Test 5: Casos Límite (10 min)

### Objetivo
Verificar comportamiento en situaciones especiales.

### Casos de Prueba

**5.1 Crear Estructura Sin Seleccionar Ciudad**

```
Acción:
1. Ir a Canales
2. Llenar: Nombre = "Test", Activo = ✓
3. NO seleccionar ciudad
4. Hacer clic "Guardar"

Validación:
❌ DEBE fallar (validación requerida)
✅ Formulario debe mostrar error o no permitir envío
```

---

**5.2 Desactivar Ciudad**

```
Acción:
1. Ir a Ciudades
2. Hacer clic en "Desactivar" para Bogotá

Efecto:
✅ Bogotá desaparece de dropdowns
✅ Los canales de Bogotá SIGUEN EXISTIENDO en BD
✅ Los cursos SIGUEN GUARDADOS

Recuperar:
1. Hacer clic "Activar" en Bogotá
✅ Todo vuelve a aparecer
```

---

**5.3 Múltiples Ciudades en Curso**

```
Acción:
1. Marcar ✓ Bogotá Y ✓ Medellín
2. En ambas ciudades dejar VACÍO (todos accesibles)
3. Guardar

Efecto:
✅ Curso accesible para usuarios de AMBAS ciudades
✅ Se guardan ambas ciudades en post_meta
```

---

**5.4 Cambiar Ciudad de un Curso**

```
Acción:
1. Curso asignado a Bogotá
2. Editar: desmarcar Bogotá, marcar Medellín
3. Guardar

Efecto:
✅ Cambio se guarda
✅ Usuarios de Bogotá ya NO ven el curso
✅ Usuarios de Medellín SÍ lo ven
```

---

### Resumen Test 5

**Resultado esperado: ✅ CASOS LÍMITE MANEJADOS**

- ✅ Validación requerida en ciudad
- ✅ Desactivar/activar funciona
- ✅ Múltiples ciudades se manejan
- ✅ Cambios sin conflictos

---

## 🧩 Matriz de Verificación Final

| Test | Función | Status |
|------|---------|--------|
| 1.1 | Crear ciudades | ☐ Pasó |
| 1.2 | Crear canales con ciudad | ☐ Pasó |
| 1.3 | Mismo nombre en diferente ciudad | ☐ Pasó |
| 1.4 | Crear sucursales | ☐ Pasó |
| 1.5 | Crear cargos | ☐ Pasó |
| 2.1 | Navegar a asignar estructuras | ☐ Pasó |
| 2.2 | AJAX: Marcar una ciudad | ☐ Pasó |
| 2.3 | AJAX: Cambiar de ciudad | ☐ Pasó |
| 2.4 | AJAX: Múltiples ciudades | ☐ Pasó |
| 3.1 | Guardar configuración | ☐ Pasó |
| 3.2 | Recuperar valores | ☐ Pasó |
| 3.3 | Modificar configuración | ☐ Pasó |
| 4.1 | Conectar a BD | ☐ Pasó |
| 4.2 | Verificar términos en BD | ☐ Pasó |
| 4.3 | Verificar meta relationships | ☐ Pasó |
| 4.4 | Verificar asignaciones de cursos | ☐ Pasó |
| 5.1 | Validación: ciudad requerida | ☐ Pasó |
| 5.2 | Desactivar ciudad | ☐ Pasó |
| 5.3 | Múltiples ciudades | ☐ Pasó |
| 5.4 | Cambiar ciudad | ☐ Pasó |

**Total Tests:** 20  
**Resultado Final:** ☐ ✅ TODOS PASARON / ☐ ⚠️ ALGUNOS FALLARON

---

## 📝 Reporte de Errores

Si encuentras algún problema:

```
Fecha: _______________
Navegador: _______________
Paso que causó error: _______________
Mensaje de error: _______________
Pasos para reproducir:
1. _______________
2. _______________
3. _______________

Resultado esperado: _______________
Resultado real: _______________

Consola (F12):
_______________
_______________
```

---

**Versión:** 1.0  
**Última actualización:** Diciembre 2024  
**Estado:** Listo para Testing ✅
