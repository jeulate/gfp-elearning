# 🧪 PLAN DE TESTING COMPLETO - Feature 1: Meta Box de Estructuras

**Fecha:** 2025-02-09  
**Feature:** Meta Box de Estructuras en Creación de Cursos  
**Estado:** 🔄 EN PROGRESO

---

## 📋 Objetivos del Testing

Verificar que la Feature 1 funciona correctamente:

1. ✅ Meta box aparece en el sidebar al crear/editar cursos
2. ✅ Administradores pueden asignar cualquier estructura
3. ✅ Instructores solo pueden asignar a sus propias estructuras
4. ✅ Las estructuras se guardan correctamente en la base de datos
5. ✅ La cascada jerárquica se aplica automáticamente
6. ✅ Las notificaciones por email se envían correctamente
7. ✅ La validación de permisos previene bypass

---

## 🔧 Pre-requisitos

Antes de comenzar el testing, verifica que tienes:

- [x] Plugin FairPlay LMS activo
- [x] MasterStudy LMS activo
- [ ] Al menos 2 estructuras creadas:
  - [ ] 1 ciudad (ej: "Bogotá")
  - [ ] 1 empresa asociada a esa ciudad (ej: "FairPlay HQ")
  - [ ] 1 canal asociado a esa empresa (ej: "Canal Distribuidores")
- [ ] Al menos 2 usuarios:
  - [ ] 1 administrador
  - [ ] 1 instructor asignado a una estructura específica
- [ ] Al menos 1 usuario "alumno" asignado a una estructura para probar emails

---

## 🧪 TEST 1: Meta Box Visible - Usuario Admin

**Objetivo:** Verificar que la meta box aparece correctamente para administradores.

### Pasos:

1. **Iniciar sesión como administrador**
   - Usuario: (tu usuario admin)

2. **Ir a crear nuevo curso**
   - Navegar a: `FairPlay LMS → Cursos`
   - Hacer clic en: `➕ Crear Nuevo Curso`

3. **Verificar que se abre el editor clásico**
   - ✅ Se debe abrir el editor de post estándar de WordPress
   - ❌ NO debe abrir el Course Builder de MasterStudy

4. **Verificar meta box en sidebar**
   - Buscar en el sidebar derecho la meta box: **"🏢 Asignar Estructuras FairPlay"**
   - ✅ Debe estar visible
   - ✅ Debe mostrar el banner: **"👑 Administrador - Puedes asignar a cualquier estructura"**

5. **Verificar contenido de la meta box**
   - ✅ Debe mostrar información de cascada: "ℹ️ Asignación en cascada"
   - ✅ Debe mostrar checkboxes para:
     - 📍 Ciudades
     - 🏢 Empresas
     - 🏪 Canales
     - 🏢 Sucursales
     - 👔 Cargos
   - ✅ Debe mostrar TODAS las estructuras del sistema

6. **Verificar aviso de notificaciones**
   - ✅ Al final debe mostrar: "📧 Los usuarios de las estructuras seleccionadas recibirán un correo cuando se publique el curso."

### Resultado Esperado:
```
✅ Meta box visible
✅ Banner de administrador presente
✅ Todas las estructuras disponibles
✅ Aviso de notificaciones visible
```

### Captura de pantalla sugerida:
📸 Tomar captura de la meta box completa en el sidebar

---

## 🧪 TEST 2: Asignación de Estructuras - Usuario Admin

**Objetivo:** Verificar que el admin puede asignar estructuras y se guardan correctamente.

### Pasos:

1. **En el editor de curso (continuando del Test 1)**
   - Título del curso: `CURSO TEST ADMIN - [Fecha Actual]`
   - Contenido: Agregar texto de prueba

2. **Seleccionar estructuras en la meta box**
   - ✅ Marcar checkbox: `Ciudad → Bogotá`
   - ✅ Marcar checkbox: `Empresa → FairPlay HQ`
   - ✅ Marcar checkbox: `Canal → Canal Distribuidores`

3. **Publicar el curso**
   - Hacer clic en: `Publicar`
   - Verificar mensaje de éxito

4. **Verificar estructuras guardadas**
   - Recargar la página del curso (F5)
   - ✅ Las estructuras seleccionadas deben seguir marcadas
   - ✅ Verificar en la vista de "FairPlay LMS → Cursos" que aparecen las estructuras

5. **Verificar cascada jerárquica (CRÍTICO)**
   - Si marcaste solo "Ciudad → Bogotá"
   - El sistema debe automáticamente asignar:
     - ✅ Todas las empresas de Bogotá
     - ✅ Todos los canales de esas empresas
     - ✅ Todas las sucursales de esos canales
     - ✅ Todos los cargos de esas sucursales

### Verificación en Base de Datos (Opcional):
```sql
-- Conectar a MySQL y ejecutar:
SELECT meta_key, meta_value 
FROM wp_postmeta 
WHERE post_id = [ID_DEL_CURSO]
AND meta_key LIKE 'fplms_course_%';
```

Deberías ver algo como:
```
fplms_course_cities    → [1]
fplms_course_companies → [1,2,3]
fplms_course_channels  → [1,2,3,4]
fplms_course_branches  → [5,6,7]
fplms_course_roles     → [10,11,12]
```

### Resultado Esperado:
```
✅ Estructuras se guardan correctamente
✅ Cascada jerárquica aplicada
✅ Datos persisten después de recargar
```

---

## 🧪 TEST 3: Notificaciones por Email - Nuevo Curso

**Objetivo:** Verificar que se envían emails a los usuarios cuando se publica un curso nuevo.

### Pre-requisito:
- Tener al menos 1 usuario asignado a la estructura "Canal Distribuidores"
- Verificar que ese usuario tiene un email válido

### Pasos:

1. **Crear otro curso nuevo**
   - Título: `CURSO TEST EMAIL - [Fecha]`
   - Asignar a: `Canal → Canal Distribuidores`

2. **Publicar el curso**
   - Hacer clic en: `Publicar`

3. **Verificar envío de emails**
   - ✅ Revisar la bandeja de entrada del usuario asignado a "Canal Distribuidores"
   - ✅ Debe llegar un correo con:
     - Asunto: `Nuevo curso asignado: CURSO TEST EMAIL - [Fecha]`
     - Contenido:
       ```
       Hola [Nombre del Usuario],
       
       Se te ha asignado un nuevo curso:
       
       📚 Curso: CURSO TEST EMAIL - [Fecha]
       🔗 Acceder al curso: [URL]
       
       ¡Esperamos que disfrutes este contenido educativo!
       
       Saludos,
       Equipo de FairPlay LMS
       ```

### Verificación de Logs (si no llegan emails):
```bash
# Verificar logs de WordPress
tail -f wp-content/debug.log | grep "wp_mail"
```

O activar logging de emails en `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Resultado Esperado:
```
✅ Email enviado correctamente
✅ Asunto correcto
✅ Contenido del email correcto
✅ URL del curso funciona
```

---

## 🧪 TEST 4: Meta Box - Usuario Instructor

**Objetivo:** Verificar que los instructores solo ven sus propias estructuras.

### Pre-requisito:
- Crear un usuario instructor con:
  - Rol: `Instructor` (stm_lms_instructor)
  - Estructuras asignadas:
    - Ciudad: Bogotá
    - Empresa: FairPlay HQ
    - Canal: Canal Distribuidores
    - (No debe tener acceso a otros canales/ciudades)

### Pasos:

1. **Iniciar sesión como instructor**
   - Cerrar sesión del admin
   - Iniciar sesión con el usuario instructor

2. **Ir a crear nuevo curso**
   - Navegar a: `Cursos → Añadir nuevo`
   - O desde: `FairPlay LMS → Cursos → ➕ Crear Nuevo Curso`

3. **Verificar meta box en sidebar**
   - ✅ Debe estar visible la meta box
   - ✅ Debe mostrar el banner: **"👨‍🏫 Modo Instructor - Solo puedes asignar a tus estructuras"**

4. **Verificar estructuras limitadas**
   - ✅ SOLO debe mostrar:
     - 📍 Ciudades: Bogotá (solo su ciudad)
     - 🏢 Empresas: FairPlay HQ (solo su empresa)
     - 🏪 Canales: Canal Distribuidores (solo su canal)
   - ❌ NO debe mostrar otras ciudades/empresas/canales del sistema

5. **Crear y publicar curso**
   - Título: `CURSO TEST INSTRUCTOR - [Fecha]`
   - Marcar: `Canal → Canal Distribuidores`
   - Publicar

6. **Verificar que se guarda correctamente**
   - Recargar la página
   - ✅ El canal debe seguir marcado

### Resultado Esperado:
```
✅ Banner de instructor visible
✅ Solo ve sus propias estructuras
✅ Puede crear y asignar cursos correctamente
```

### Captura de pantalla sugerida:
📸 Comparar meta box de admin vs instructor (mostrar la diferencia)

---

## 🧪 TEST 5: Validación de Permisos (CRÍTICO)

**Objetivo:** Verificar que instructores NO pueden bypassear la validación.

### Pasos (Testing de Seguridad):

1. **Como instructor, abrir DevTools**
   - Navegar a: `Cursos → Añadir nuevo`
   - Abrir DevTools (F12)
   - Ir a la pestaña: `Elements` o `Inspector`

2. **Intentar manipular HTML**
   - Buscar la meta box de estructuras
   - Agregar manualmente un checkbox para otro canal:
   ```html
   <label class="fplms-parent-option">
       <input type="checkbox" name="fplms_course_channels[]" value="99" checked>
       <span>Canal Hackeado</span>
   </label>
   ```

3. **Intentar guardar el curso**
   - Título: `CURSO TEST SEGURIDAD`
   - Publicar el curso

4. **Verificar que falla la validación**
   - ✅ Debe mostrar un mensaje de error:
     ```
     ⚠️ Error: No puedes asignar el curso a estructuras donde no estás asignado.
     ```
   - ✅ El curso NO debe guardarse con el canal manipulado
   - ✅ Solo debe guardar las estructuras legítimas

### Verificación en Base de Datos:
```sql
SELECT meta_key, meta_value 
FROM wp_postmeta 
WHERE post_id = [ID_CURSO_TEST_SEGURIDAD]
AND meta_key = 'fplms_course_channels';
```

Resultado esperado:
```
fplms_course_channels → Solo debe contener los canales del instructor
                        NO debe contener el canal ID 99
```

### Resultado Esperado:
```
✅ Validación backend funciona
✅ Mensaje de error visible
✅ Curso NO se guarda con datos manipulados
✅ Seguridad confirmada
```

---

## 🧪 TEST 6: Actualización de Curso - Notificaciones Inteligentes

**Objetivo:** Verificar que al actualizar un curso, solo los NUEVOS usuarios reciben email.

### Pre-requisitos:
- Curso existente asignado a "Canal Distribuidores"
- Usuario A asignado a "Canal Distribuidores"
- Usuario B asignado a "Canal Minoristas"

### Pasos:

1. **Editar curso existente**
   - Ir a: `FairPlay LMS → Cursos`
   - Editar: `CURSO TEST ADMIN`

2. **Agregar nueva estructura**
   - En la meta box, marcar:
     - ✅ `Canal → Canal Minoristas` (nuevo)
     - (Mantener `Canal Distribuidores` marcado)

3. **Actualizar el curso**
   - Hacer clic en: `Actualizar`

4. **Verificar emails enviados**
   - ✅ Usuario A (Canal Distribuidores): NO debe recibir nuevo email
   - ✅ Usuario B (Canal Minoristas): SÍ debe recibir email

### Resultado Esperado:
```
✅ Solo nuevos usuarios reciben email
✅ Usuarios existentes no reciben spam
✅ Sistema inteligente de notificaciones funciona
```

---

## 🧪 TEST 7: Editor Clásico vs Course Builder

**Objetivo:** Verificar que el editor clásico se fuerza correctamente.

### Pasos:

1. **Crear curso desde diferentes puntos**
   - Opción A: `FairPlay LMS → Cursos → ➕ Crear Nuevo Curso`
   - Opción B: `Cursos → Añadir nuevo` (menú de WordPress)

2. **Verificar editor**
   - ✅ Debe abrir: Editor clásico de WordPress
   - ❌ NO debe abrir: Course Builder de MasterStudy

3. **Verificar meta box presente**
   - ✅ Meta box debe estar en el sidebar
   - ✅ Funciones de WordPress deben funcionar normalmente

### Resultado Esperado:
```
✅ Editor clásico forzado correctamente
✅ Meta box visible y funcional
✅ No se abre Course Builder automáticamente
```

---

## 📊 Resumen de Resultados

| Test | Descripción | Estado | Notas |
|------|-------------|--------|-------|
| 1 | Meta box visible (Admin) | ⏳ | |
| 2 | Asignación estructuras (Admin) | ⏳ | |
| 3 | Notificaciones email (Nuevo curso) | ⏳ | |
| 4 | Meta box limitada (Instructor) | ⏳ | |
| 5 | Validación de permisos | ⏳ | |
| 6 | Notificaciones inteligentes | ⏳ | |
| 7 | Editor clásico forzado | ⏳ | |

**Leyenda:**
- ⏳ Pendiente
- ✅ Aprobado
- ❌ Fallido
- ⚠️ Con observaciones

---

## 🐛 Registro de Problemas Encontrados

### Problema 1: [Título]
**Severidad:** Alta / Media / Baja  
**Descripción:**  
**Pasos para reproducir:**  
**Resultado esperado:**  
**Resultado actual:**  
**Solución propuesta:**  

---

## ✅ Checklist Final

Antes de considerar Feature 1 como completada, verificar:

- [ ] Todos los tests pasaron exitosamente
- [ ] No hay errores 500 o warnings en PHP
- [ ] Emails se envían correctamente
- [ ] Validación de seguridad funciona
- [ ] Cascada jerárquica aplicada correctamente
- [ ] Meta box visible en ambos roles
- [ ] Editor clásico se fuerza correctamente
- [ ] Documentación actualizada
- [ ] Screenshots tomados para documentación

---

## 📸 Capturas de Pantalla Requeridas

1. Meta box en vista admin (todas las estructuras)
2. Meta box en vista instructor (solo sus estructuras)
3. Banner de rol (admin vs instructor)
4. Email recibido por usuario
5. Mensaje de error al intentar bypass
6. Vista de curso con estructuras asignadas en tabla

---

## 🚀 Próximos Pasos

Después de completar este testing:

1. **Si todos los tests pasan:**
   - ✅ Marcar Feature 1 como completada
   - ⏭️ Proceder con Feature 3: Course Builder
   - ⏭️ Después Feature 2: Canales como categorías

2. **Si hay problemas:**
   - 🐛 Documentar cada problema en "Registro de Problemas"
   - 🔧 Corregir los bugs encontrados
   - 🔄 Re-ejecutar los tests afectados

---

**Última actualización:** 2025-02-09  
**Testeado por:** [Tu nombre]  
**Entorno:** Producción / Staging
