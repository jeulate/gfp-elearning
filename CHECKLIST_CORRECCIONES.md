# Checklist de Verificación Rápida - Correcciones de Usuarios

## 🎯 Antes de Probar en WordPress

### 1. Verificar que los cambios estén en el código

**En la terminal PowerShell:**

```powershell
# Ir a la carpeta del plugin
cd "d:\Programas\gfp-elearning\wordpress\wp-content\plugins\fairplay-lms-masterstudy-extensions\includes"

# Buscar el cambio del remove_role
Select-String -Path "class-fplms-users.php" -Pattern "remove_role" | Select-Object -First 3

# Resultado esperado:
# Debe mostrar una línea con: $user->remove_role( 'subscriber' );
```

### 2. Verificar estructura del filtrado

```powershell
# Buscar los cambios en meta_query
Select-String -Path "class-fplms-users.php" -Pattern "'type' => 'NUMERIC'" | Select-Object -First 2

# Resultado esperado:
# Debe mostrar líneas que contengan 'type' => 'NUMERIC'
```

### 3. Verificar que el archivo sea válido PHP

```powershell
# Validar sintaxis PHP (si tienes PHP instalado)
php -l class-fplms-users.php

# Si no tienes PHP, ignorar este paso
# Los cambios ya fueron validados por el editor
```

---

## ✅ Tests en WordPress

### ✅ TEST 1: Crear Usuario Sin Roles Duplicados

**Pasos:**
1. Inicia sesión en WordPress como Administrador
2. Ve a: **FairPlay LMS → Usuarios**
3. En "Crear nuevo usuario":
   - Usuario: `testuser_nodupe`
   - Email: `test.nodupe@example.com`
   - Contraseña: `TestPass123`
   - Nombre: `Test`
   - Apellido: `NoDupe`
   - **Roles:** Marca SOLO "☑ Alumno FairPlay"
   - Ciudad: Bogotá
4. Clic en "**Crear usuario**"

**Verificación:**
- [ ] Aparece mensaje: "Usuario creado correctamente. ID: [número]"
- [ ] El usuario aparece en la tabla de "Usuarios por estructura"
- [ ] En la columna "Rol(es)" muestra: `fplms_student`
- [ ] Haz clic en el nombre del usuario
- [ ] Ve a: **Usuarios → Editar [testuser_nodupe]**
- [ ] En la sección "Roles de WordPress" (antes de "Estructura..."):
  - [ ] Debe mostrar: "Alumno FairPlay"
  - [ ] **NO debe mostrar: Subscriber**

**Resultado:** 
- ✅ PASS: Solo tiene el rol Alumno FairPlay
- ❌ FAIL: Si sigue teniendo Subscriber

---

### ✅ TEST 2: Crear Usuario con Múltiples Roles

**Pasos:**
1. En **FairPlay LMS → Usuarios → Crear nuevo usuario**
2. Datos:
   - Usuario: `testuser_multirole`
   - Email: `test.multirole@example.com`
   - Contraseña: `TestPass123`
   - **Roles:** Marca AMBAS:
     - ☑ Alumno FairPlay
     - ☑ Tutor FairPlay
   - Ciudad: Medellín
3. Clic en "**Crear usuario**"

**Verificación:**
- [ ] Usuario creado exitosamente
- [ ] En tabla muestra: `fplms_student, fplms_tutor`
- [ ] Clic en usuario → Editar en WordPress
- [ ] En "Roles de WordPress":
  - [ ] Muestra: "Alumno FairPlay" y "Tutor FairPlay"
  - [ ] **NO muestra: Subscriber**

**Resultado:**
- ✅ PASS: Tiene SOLO los dos roles seleccionados, sin Subscriber
- ❌ FAIL: Si tiene Subscriber o si faltan roles

---

### ✅ TEST 3: Filtrar por Ciudad

**Pasos:**
1. Ve a **FairPlay LMS → Usuarios**
2. En "Usuarios por estructura":
   - Ciudad: Selecciona **Bogotá**
   - Canal: Deja en blanco (— Todos —)
   - Sucursal: Deja en blanco (— Todas —)
   - Cargo: Deja en blanco (— Todos —)
3. Clic en "**Filtrar**"

**Verificación:**
- [ ] La tabla SOLO muestra usuarios con Ciudad = Bogotá
- [ ] Los usuarios de Medellín desaparecen
- [ ] Si no hay usuarios de Bogotá: "No se encontraron usuarios con estos filtros."
- [ ] La URL cambia a: `...&fplms_filter_city=X`

**Resultado:**
- ✅ PASS: Filtra correctamente por ciudad
- ❌ FAIL: Si muestra todos los usuarios sin filtrar

---

### ✅ TEST 4: Filtrar por Múltiples Criterios

**Pasos:**
1. En **FairPlay LMS → Usuarios → Usuarios por estructura**
2. Selecciona:
   - Ciudad: **Bogotá**
   - Canal: **Online**
   - Sucursal: (deja en blanco)
   - Cargo: (deja en blanco)
3. Clic en "**Filtrar**"

**Verificación:**
- [ ] La tabla muestra SOLO usuarios que tienen:
  - Estructura Ciudad = Bogotá **Y**
  - Estructura Canal = Online
- [ ] Usuarios que solo tienen Bogotá desaparecen
- [ ] La búsqueda es precisa

**Resultado:**
- ✅ PASS: Retorna usuarios que cumplen AMBOS criterios
- ❌ FAIL: Si muestra usuarios que solo cumplen uno

---

### ✅ TEST 5: Limpiar Filtros

**Pasos:**
1. Con filtros activos del Test 4
2. En los desplegables:
   - Ciudad: Cambia a **"— Todas —"**
   - Canal: Cambia a **"— Todos —"**
3. Clic en "**Filtrar**"

**Verificación:**
- [ ] La tabla vuelve a mostrar TODOS los usuarios
- [ ] La cantidad de filas aumenta
- [ ] No hay restricción de búsqueda

**Resultado:**
- ✅ PASS: Retorna todos los usuarios
- ❌ FAIL: Si sigue mostrando solo filtrados

---

### ✅ TEST 6: Filtro Individual - Canal

**Pasos:**
1. En **FairPlay LMS → Usuarios → Usuarios por estructura**
2. Selecciona:
   - Ciudad: (deja en blanco)
   - Canal: **Online**
   - Sucursal: (deja en blanco)
   - Cargo: (deja en blanco)
3. Clic en "**Filtrar**"

**Verificación:**
- [ ] La tabla muestra SOLO usuarios con Canal = Online
- [ ] Independientemente de su ciudad
- [ ] Si no hay usuarios con Canal Online: "No se encontraron..."

**Resultado:**
- ✅ PASS: Filtra por canal correctamente
- ❌ FAIL: Si no filtra

---

### ✅ TEST 7: Filtro Individual - Sucursal

**Pasos:**
1. En **FairPlay LMS → Usuarios → Usuarios por estructura**
2. Selecciona:
   - Ciudad: (deja en blanco)
   - Canal: (deja en blanco)
   - Sucursal: **Principal** (o la que exista)
   - Cargo: (deja en blanco)
3. Clic en "**Filtrar**"

**Verificación:**
- [ ] La tabla muestra SOLO usuarios con esa Sucursal
- [ ] Otros usuarios desaparecen

**Resultado:**
- ✅ PASS: Filtra por sucursal correctamente
- ❌ FAIL: Si no filtra

---

### ✅ TEST 8: Filtro Individual - Cargo

**Pasos:**
1. En **FairPlay LMS → Usuarios → Usuarios por estructura**
2. Selecciona:
   - Ciudad: (deja en blanco)
   - Canal: (deja en blanco)
   - Sucursal: (deja en blanco)
   - Cargo: **Gerente** (o el que exista)
3. Clic en "**Filtrar**"

**Verificación:**
- [ ] La tabla muestra SOLO usuarios con ese Cargo
- [ ] Otros usuarios desaparecen

**Resultado:**
- ✅ PASS: Filtra por cargo correctamente
- ❌ FAIL: Si no filtra

---

## 📊 Resumen de Resultados

Copia y completa este resumen después de los tests:

```
RESUMEN DE TESTS - CORRECCIONES DE USUARIOS

Fecha: _______________
Probador: _______________

TEST 1 - Crear usuario sin roles duplicados:      [ ] ✅ PASS [ ] ❌ FAIL
TEST 2 - Crear usuario con múltiples roles:       [ ] ✅ PASS [ ] ❌ FAIL
TEST 3 - Filtrar por ciudad:                      [ ] ✅ PASS [ ] ❌ FAIL
TEST 4 - Filtrar por múltiples criterios:         [ ] ✅ PASS [ ] ❌ FAIL
TEST 5 - Limpiar filtros:                         [ ] ✅ PASS [ ] ❌ FAIL
TEST 6 - Filtro individual canal:                 [ ] ✅ PASS [ ] ❌ FAIL
TEST 7 - Filtro individual sucursal:              [ ] ✅ PASS [ ] ❌ FAIL
TEST 8 - Filtro individual cargo:                 [ ] ✅ PASS [ ] ❌ FAIL

RESULTADO GENERAL:
[ ] ✅ TODOS PASS - Sistema funcionando correctamente
[ ] ⚠️  PARCIAL - Algunos tests fallaron (especificar cuáles)
[ ] ❌ CRÍTICO - Sistema no funcionando (especificar problemas)

Observaciones:
___________________________________________________________________
___________________________________________________________________
___________________________________________________________________
```

---

## 🔧 Troubleshooting Rápido

### Problema: "No se encontraron usuarios con estos filtros"

**Posible causa:** No hay usuarios con esa estructura asignada

**Solución:**
1. Ve a un usuario existente
2. Edítalo: **Usuarios → [Nombre] → Editar**
3. Baja a "Estructura organizacional FairPlay"
4. Asigna una Ciudad, Canal, Sucursal, etc.
5. Guarda
6. Vuelve a filtrar

---

### Problema: Usuario creado tiene "Subscriber" aún

**Posible causa:** El cambio no se aplicó correctamente

**Solución:**
1. Verifica que el archivo tenga `remove_role('subscriber')`
2. Si no está, copia el código nuevamente
3. Recarga el plugin

---

### Problema: Filtro retorna usuarios que NO coinciden

**Posible causa:** Los usuarios no tienen estructura asignada

**Solución:**
1. Asigna estructura a los usuarios primero
2. Edita cada usuario: **Usuarios → [Nombre] → Editar → Estructura FairPlay**
3. Luego intenta filtrar

---

## ✅ Conclusión

Si **todos los tests resultan PASS**, los cambios están funcionando correctamente y el sistema está listo para usar en producción.

Si hay **tests que fallan**, consulta la guía CORRECCIONES_USUARIOS_V2.md para más detalles técnicos.

---

**Documento:** Checklist de Verificación Rápida  
**Versión:** 1.0  
**Fecha:** 9 de Diciembre de 2024
