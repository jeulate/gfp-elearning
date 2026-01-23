# ✅ Checklist de Verificación - Sistema de Roles Actualizado

## 🎯 Objetivo
Verificar que el sistema de roles simplificado está funcionando correctamente con los 3 nuevos roles:
- **Estudiante** (subscriber)
- **Docente** (stm_lms_instructor) 
- **Administrador** (administrator)

---

## 📋 Lista de Verificación

### 1. Ejecutar Migración
- [ ] Acceder a: `https://tu-sitio.com/wp-content/plugins/fairplay-lms-masterstudy-extensions/migrate-update-roles-subscriber.php`
- [ ] Verificar mensaje: "✅ Migración Completada"
- [ ] Anotar estadísticas:
  - Estudiantes migrados: _______
  - Docentes migrados: _______
  - Total: _______

### 2. Verificar Interfaz de Creación de Usuarios
- [ ] Ir a: **FairPlay LMS → Usuarios**
- [ ] Clic en botón **"Crear Usuario"**
- [ ] Verificar campo "Tipo de Usuario" muestra un **select dropdown** (no checkboxes)
- [ ] Verificar opciones del select:
  - [ ] "Estudiante"
  - [ ] "Docente"
  - [ ] "Administrador"
- [ ] Verificar que el select tenga estilo mejorado (gradiente, borde redondeado)

### 3. Crear Usuario de Prueba - Estudiante
- [ ] Llenar formulario:
  - Nombre: Test
  - Apellido: Estudiante
  - Usuario: test_estudiante
  - Email: estudiante@test.com
  - Contraseña: Test123!
  - Ciudad: (seleccionar)
  - Empresa: (seleccionar)
  - Canal: (seleccionar)
  - **Tipo de Usuario: Estudiante**
- [ ] Clic en "Guardar"
- [ ] Verificar mensaje de éxito
- [ ] Ir a **Usuarios → Todos los usuarios**
- [ ] Buscar "test_estudiante"
- [ ] Verificar que el rol sea: **"Suscriptor"**

### 4. Crear Usuario de Prueba - Docente
- [ ] Crear nuevo usuario con **Tipo: Docente**
- [ ] Verificar en lista de usuarios que el rol sea: **"Instructor"**

### 5. Crear Usuario de Prueba - Administrador
- [ ] Crear nuevo usuario con **Tipo: Administrador**
- [ ] Verificar en lista de usuarios que el rol sea: **"Administrador"**

### 6. Verificar Matriz de Privilegios
- [ ] En **FairPlay LMS → Usuarios**
- [ ] Clic en **"Matriz de Privilegios"**
- [ ] Verificar que la tabla muestre 3 filas (roles):
  - [ ] **Estudiante**
  - [ ] **Docente**
  - [ ] **Administrador**
- [ ] Verificar permisos de Estudiante:
  - [ ] ❌ Gestionar estructuras
  - [ ] ❌ Gestionar usuarios
  - [ ] ❌ Gestionar cursos
  - [ ] ❌ Ver informes
  - [ ] ✅ Ver avances
  - [ ] ✅ Ver calendario
- [ ] Verificar permisos de Docente:
  - [ ] ❌ Gestionar estructuras
  - [ ] ❌ Gestionar usuarios
  - [ ] ✅ Gestionar cursos
  - [ ] ✅ Ver informes
  - [ ] ✅ Ver avances
  - [ ] ✅ Ver calendario
- [ ] Verificar permisos de Administrador: Todos ✅

### 7. Verificar Usuarios Migrados
- [ ] Ir a **Usuarios → Todos los usuarios**
- [ ] Filtrar por rol: **"Suscriptor"**
- [ ] Confirmar que aparecen usuarios que antes eran "Alumno FairPlay"
- [ ] Filtrar por rol: **"Instructor"**
- [ ] Confirmar que aparecen usuarios que antes eran "Tutor FairPlay"

### 8. Probar Login y Permisos

#### Como Estudiante
- [ ] Cerrar sesión del administrador
- [ ] Iniciar sesión con: test_estudiante
- [ ] Verificar que puede acceder al sitio
- [ ] Verificar que puede ver cursos disponibles
- [ ] Verificar que NO puede acceder al panel de administración de WordPress
- [ ] Verificar que puede ver su progreso en cursos

#### Como Docente
- [ ] Cerrar sesión
- [ ] Iniciar sesión con usuario docente de prueba
- [ ] Verificar acceso al panel de administración
- [ ] Verificar que puede crear/editar cursos
- [ ] Verificar que puede ver reportes de estudiantes
- [ ] Verificar que NO puede gestionar estructuras

#### Como Administrador
- [ ] Iniciar sesión con usuario administrador de prueba
- [ ] Verificar acceso completo al panel
- [ ] Verificar que puede gestionar estructuras
- [ ] Verificar que puede gestionar usuarios
- [ ] Verificar que puede modificar la matriz de privilegios

### 9. Verificar Cascada de Selects
- [ ] Crear nuevo usuario
- [ ] Seleccionar Ciudad
- [ ] Verificar que el select "Empresa" se llena automáticamente
- [ ] Seleccionar Empresa
- [ ] Verificar que el select "Canal/Franquicia" se llena
- [ ] Seleccionar Canal
- [ ] Verificar que el select "Sucursal" se llena
- [ ] Seleccionar Sucursal
- [ ] Verificar que el select "Cargo" se llena

### 10. Verificar Compatibilidad con MasterStudy
- [ ] Ir a cursos de MasterStudy
- [ ] Asignar curso a usuario Estudiante (subscriber)
- [ ] Verificar que puede inscribirse al curso
- [ ] Iniciar sesión como estudiante
- [ ] Verificar que puede ver y acceder al curso

### 11. Limpiar y Finalizar
- [ ] **ELIMINAR archivo de migración:**
  ```bash
  rm wordpress/wp-content/plugins/fairplay-lms-masterstudy-extensions/migrate-update-roles-subscriber.php
  ```
- [ ] Eliminar usuarios de prueba (opcional)
- [ ] Documentar cualquier problema encontrado

---

## 🐛 Problemas Comunes y Soluciones

### El rol stm_lms_instructor no existe
**Causa:** MasterStudy LMS no está activo  
**Solución:** Activar el plugin MasterStudy LMS desde Plugins → Plugins instalados

### Usuarios no pueden iniciar sesión después de migración
**Causa:** Permisos de base de datos  
**Solución:** Ejecutar nuevamente el script de migración

### Select de tipo de usuario no muestra estilos nuevos
**Causa:** Caché del navegador  
**Solución:** Limpiar caché del navegador (Ctrl+F5) o Ctrl+Shift+R

### Matriz de privilegios no se guarda
**Causa:** Permisos de usuario  
**Solución:** Solo los administradores pueden modificar la matriz

---

## 📊 Resultados Esperados

✅ **Sistema simplificado:** Solo 3 roles visibles  
✅ **Compatibilidad:** Roles nativos de WordPress/MasterStudy  
✅ **Interfaz mejorada:** Select dropdown con estilos profesionales  
✅ **Migración exitosa:** Todos los usuarios con roles actualizados  
✅ **Permisos correctos:** Matriz de privilegios funcionando  

---

## 📝 Notas Adicionales

- Los roles antiguos (`fplms_student`, `fplms_tutor`) permanecen en la base de datos pero no se usan
- Se pueden eliminar manualmente si se confirma que todo funciona correctamente
- El sistema es compatible con versiones futuras de MasterStudy LMS
- Los datos de estructura (ciudad, empresa, canal, etc.) no se ven afectados

---

**Responsable:** _________________  
**Fecha de verificación:** _________________  
**Resultado:** ⬜ Exitoso  ⬜ Con observaciones  ⬜ Fallido  
**Observaciones:**  
_____________________________________________________________________
_____________________________________________________________________
_____________________________________________________________________
