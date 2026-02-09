# 🎯 Plan Maestro de Implementación: Sistema Completo de Estructuras

**Fecha:** 5 de febrero de 2026  
**Objetivo:** Implementar 3 features relacionadas con estructuras de forma ordenada y eficiente.

---

## 📋 Documentos de Análisis Disponibles

1. ✅ [ANALISIS_CANALES_COMO_CATEGORIAS.md](ANALISIS_CANALES_COMO_CATEGORIAS.md)
   - Mostrar canales en lugar de categorías
   - Filtros de búsqueda por canal

2. ✅ [ANALISIS_INTEGRACION_ESTRUCTURAS_CREACION_CURSO.md](ANALISIS_INTEGRACION_ESTRUCTURAS_CREACION_CURSO.md)
   - Meta box en creación de curso (Admin tradicional)
   - Notificaciones automáticas

3. ✅ [ANALISIS_COURSE_BUILDER_ESTRUCTURAS.md](ANALISIS_COURSE_BUILDER_ESTRUCTURAS.md)
   - Meta box en Course Builder (Frontend SPA)
   - Control de permisos por rol (Instructor vs Admin)

---

## 🎯 Features a Implementar

### Feature 1: Meta Box de Estructuras en Creación de Curso
**Ubicación:** `/wp-admin/post-new.php?post_type=stm-courses`  
**Usuarios:** Administradores e Instructores  
**Prioridad:** 🔴 ALTA - Base fundamental

#### Funcionalidades:
- ✅ Sidebar con checkboxes de estructuras
- ✅ Guardado al publicar curso
- ✅ Cascada jerárquica automática
- ✅ Notificaciones por correo
- ✅ Control de permisos (Admin vs Instructor)

#### Archivos involucrados:
- `class-fplms-courses.php` - 5 métodos nuevos
- `class-fplms-plugin.php` - 2 hooks

---

### Feature 2: Canales como Categorías + Filtros
**Ubicación:** Frontend - Vista de cursos  
**Usuarios:** Todos los usuarios (visualización)  
**Prioridad:** 🟡 MEDIA - Mejora de UX

#### Funcionalidades:
- ✅ Mostrar canales donde estarían las categorías
- ✅ Links a filtro por canal
- ✅ Widget de filtros en sidebar
- ✅ Query modificada para filtrar cursos
- ✅ Contador de cursos por canal

#### Archivos involucrados:
- `class-fplms-course-display.php` - Modificaciones
- `class-fplms-course-filters.php` - **NUEVO**
- `class-fplms-plugin.php` - 1 hook

---

### Feature 3: Estructuras en Course Builder (SPA)
**Ubicación:** `/user-account/edit-course/{id}/settings/main`  
**Usuarios:** Administradores e Instructores  
**Prioridad:** 🟢 BAJA - Alternativa a Feature 1

#### Funcionalidades:
- ✅ Meta box adaptada al Course Builder
- ✅ Control de permisos avanzado
- ✅ Filtrado de opciones por rol
- ✅ Validación en backend
- ✅ Mismo guardado que Feature 1

#### Archivos involucrados:
- `class-fplms-courses.php` - Reutiliza métodos de Feature 1

---

## 🔄 Análisis de Dependencias

### Dependencias Técnicas

```
Feature 1 (Meta Box Admin)
    ↓
    ├─ Métodos base (get_course_structures, apply_cascade_logic)
    ├─ Sistema de notificaciones (send_course_assignment_notifications)
    └─ Validación de permisos (validate_instructor_structures)
    
Feature 2 (Canales como Categorías)
    ↓
    ├─ Depende de META_COURSE_CHANNELS estar guardado
    └─ Usa get_course_structures() de Feature 1

Feature 3 (Course Builder)
    ↓
    ├─ Reutiliza TODOS los métodos de Feature 1
    └─ Solo agrega get_user_structures() y get_available_structures_for_user()
```

### Reutilización de Código

| Método | Feature 1 | Feature 2 | Feature 3 |
|--------|-----------|-----------|-----------|
| `get_course_structures()` | ✅ Crea | ✅ Usa | ✅ Usa |
| `save_course_structures()` | ✅ Crea | ❌ No usa | ✅ Usa |
| `apply_cascade_logic()` | ✅ Crea | ❌ No usa | ✅ Usa |
| `send_course_assignment_notifications()` | ✅ Crea | ❌ No usa | ✅ Usa |
| `get_user_structures()` | ❌ No necesita | ❌ No usa | ✅ Crea |
| `validate_instructor_structures()` | ✅ Crea | ❌ No usa | ✅ Usa |

**Conclusión:** Feature 1 es la BASE. Feature 3 la EXTIENDE. Feature 2 es INDEPENDIENTE.

---

## 📊 Orden de Implementación Recomendado

### 🥇 Opción A: Secuencial Lógica (RECOMENDADA)

**Orden:** Feature 1 → Feature 3 → Feature 2

#### Fase 1: Meta Box en Creación Admin (Feature 1)
**Tiempo estimado:** 2-3 días

**Razón:**
- ✅ Es la base fundamental
- ✅ Crea todos los métodos necesarios
- ✅ Implementa notificaciones
- ✅ Feature 3 depende de esto

**Entregables:**
1. Meta box funcional en `/wp-admin/post-new.php?post_type=stm-courses`
2. Guardado con cascada y notificaciones
3. Sistema de permisos básico

**Testing:**
- Crear curso como Admin
- Crear curso como Instructor
- Verificar notificaciones
- Validar cascada

#### Fase 2: Estructuras en Course Builder (Feature 3)
**Tiempo estimado:** 1-2 días

**Razón:**
- ✅ Reutiliza código de Feature 1
- ✅ Solo agrega control de permisos avanzado
- ✅ Complementa la experiencia de creación

**Entregables:**
1. Meta box adaptada al Course Builder
2. Filtrado de estructuras por rol
3. Validación de seguridad robusta

**Testing:**
- Instructor con canal asignado
- Instructor sin canal
- Admin en Course Builder
- Intentos de bypass de seguridad

#### Fase 3: Canales como Categorías (Feature 2)
**Tiempo estimado:** 2 días

**Razón:**
- ✅ Independiente de las otras
- ✅ Mejora de UX, no funcionalidad crítica
- ✅ Puede implementarse después sin afectar lo anterior

**Entregables:**
1. Canales visibles en vista de curso
2. Widget de filtros por canal
3. Sistema de búsqueda funcional

**Testing:**
- Ver curso con canales
- Filtrar por canal
- Combinar con otros filtros

---

### 🥈 Opción B: Por Impacto en Usuario Final

**Orden:** Feature 1 → Feature 2 → Feature 3

**Razón:**
- Feature 2 es más visible para usuarios finales
- Course Builder puede dejarse para después
- Prioriza experiencia del estudiante

**Tiempo total:** Similar a Opción A

---

### 🥉 Opción C: Simultánea (NO RECOMENDADA)

**Implementar las 3 a la vez**

**Desventajas:**
- ❌ Complejo de testear
- ❌ Difícil identificar errores
- ❌ Mayor riesgo de conflictos
- ❌ Dificultad para rollback

---

## 🎯 Recomendación Final: OPCIÓN A

### Justificación

1. **Lógica de Dependencias**
   - Feature 1 crea la base
   - Feature 3 la extiende directamente
   - Feature 2 es independiente

2. **Reducción de Riesgos**
   - Testing incremental
   - Problemas detectados temprano
   - Fácil rollback en cada fase

3. **Eficiencia de Desarrollo**
   - Sin duplicación de código
   - Reutilización máxima
   - Menos bugs

4. **Experiencia del Usuario**
   - Admin e Instructores tienen herramientas completas primero
   - Frontend mejora después

---

## 📅 Cronograma Detallado

### Semana 1: Feature 1 (Meta Box Admin)

**Día 1-2: Implementación Base**
- [ ] Crear método `register_structures_meta_box()`
- [ ] Crear método `render_structures_meta_box()`
- [ ] Crear método `save_course_structures_on_publish()`
- [ ] Crear método `get_user_structures()`
- [ ] Crear método `validate_instructor_structures()`
- [ ] Registrar hooks en `class-fplms-plugin.php`

**Día 3: Testing y Ajustes**
- [ ] Test: Admin crea curso con estructuras
- [ ] Test: Instructor crea curso con estructuras
- [ ] Test: Notificaciones funcionan
- [ ] Test: Cascada se aplica correctamente
- [ ] Ajustar bugs encontrados

### Semana 2: Feature 3 (Course Builder)

**Día 1: Adaptación de Meta Box**
- [ ] Crear método `get_available_structures_for_user()`
- [ ] Modificar `render_structures_meta_box()` para Course Builder
- [ ] Modificar `validate_instructor_structures()` con lógica avanzada
- [ ] Testing básico

**Día 2: Testing de Permisos**
- [ ] Test: Instructor ve solo sus estructuras
- [ ] Test: Admin ve todas
- [ ] Test: Instructor sin estructuras
- [ ] Test: Intentos de bypass
- [ ] Ajustes finales

### Semana 3: Feature 2 (Canales como Categorías)

**Día 1: Visualización**
- [ ] Modificar `class-fplms-course-display.php`
- [ ] Crear método `inject_channel_categories()`
- [ ] Crear método `show_channels_as_categories()`
- [ ] Testing visual

**Día 2: Filtros**
- [ ] Crear `class-fplms-course-filters.php`
- [ ] Implementar `filter_courses_by_channel()`
- [ ] Crear widget de filtros
- [ ] Testing de búsqueda y filtrado

---

## 🧪 Plan de Testing General

### Testing por Rol

#### Como Administrador
- [ ] Crear curso en Admin con estructuras
- [ ] Crear curso en Course Builder con estructuras
- [ ] Ver curso con canales visibles
- [ ] Filtrar cursos por canal
- [ ] Verificar notificaciones enviadas

#### Como Instructor (con canal)
- [ ] Crear curso en Admin - Ver solo mi canal
- [ ] Crear curso en Course Builder - Ver solo mi canal
- [ ] Intentar asignar canal ajeno (debe fallar)
- [ ] Verificar notificaciones

#### Como Instructor (sin canal)
- [ ] Crear curso en Admin - Ver mensaje de error
- [ ] Crear curso en Course Builder - Ver mensaje de error

#### Como Estudiante (subscriber)
- [ ] Ver curso con canales visibles
- [ ] Usar filtros por canal
- [ ] Verificar que solo ve cursos de su estructura

### Testing de Integración

- [ ] Crear curso → Asignar estructuras → Publicar → Verificar notificaciones
- [ ] Actualizar estructuras → Verificar solo nuevos usuarios notificados
- [ ] Filtrar por canal → Verificar resultados correctos
- [ ] Admin asigna todas estructuras → Instructor ve solo las suyas

---

## 📁 Resumen de Archivos a Modificar

### Archivos Nuevos
1. `class-fplms-course-filters.php` ⭐ (Feature 2)

### Archivos a Modificar

| Archivo | Feature 1 | Feature 2 | Feature 3 |
|---------|-----------|-----------|-----------|
| `class-fplms-courses.php` | ✅ 5 métodos | ❌ | ✅ 2 métodos |
| `class-fplms-plugin.php` | ✅ 2 hooks | ✅ 1 hook | ❌ |
| `class-fplms-course-display.php` | ❌ | ✅ 3 métodos | ❌ |

---

## ✅ Checklist de Implementación Completa

### Feature 1: Meta Box Admin
- [ ] Código implementado
- [ ] Testing completado
- [ ] Documentación actualizada
- [ ] ✅ PRODUCTION READY

### Feature 2: Canales como Categorías
- [ ] Código implementado
- [ ] Testing completado
- [ ] Documentación actualizada
- [ ] ✅ PRODUCTION READY

### Feature 3: Course Builder
- [ ] Código implementado
- [ ] Testing completado
- [ ] Documentación actualizada
- [ ] ✅ PRODUCTION READY

### Integración Final
- [ ] Testing de las 3 features juntas
- [ ] Performance verificado
- [ ] Seguridad auditada
- [ ] Documentación completa
- [ ] ✅ SISTEMA COMPLETO

---

## 🎓 Capacitación de Usuarios

### Para Administradores
**Contenido:**
1. Cómo asignar estructuras al crear curso
2. Diferencia entre Admin y Course Builder
3. Cómo usar filtros de canal
4. Gestión de notificaciones

**Duración:** 30 minutos

### Para Instructores
**Contenido:**
1. Cómo asignar curso a su canal
2. Limitaciones de permisos
3. Qué hacer si no tienen estructura asignada
4. Entender las notificaciones automáticas

**Duración:** 20 minutos

### Para Estudiantes
**Contenido:**
1. Cómo usar filtros por canal
2. Por qué ven ciertos cursos y otros no
3. Interpretar las notificaciones de nuevos cursos

**Duración:** 10 minutos

---

## 🚀 Criterios de Éxito

### Funcionalidad
- ✅ Admin puede asignar cualquier estructura
- ✅ Instructor solo puede asignar su canal
- ✅ Notificaciones se envían correctamente
- ✅ Cascada funciona automáticamente
- ✅ Filtros muestran resultados correctos

### Performance
- ✅ Tiempo de carga < 2 segundos
- ✅ Consultas optimizadas
- ✅ Cache utilizado apropiadamente

### Seguridad
- ✅ Validación en backend
- ✅ Nonce verificado
- ✅ Permisos correctos
- ✅ Bypass imposible

### UX
- ✅ Interfaz intuitiva
- ✅ Mensajes claros
- ✅ Feedback visual apropiado
- ✅ Mobile responsive

---

## 📊 Métricas de Seguimiento

### Durante Implementación
- Tiempo real vs estimado
- Bugs encontrados por fase
- Tests pasados/fallados
- Líneas de código añadidas

### Post Implementación
- Usuarios usando la feature
- Cursos creados con estructuras
- Notificaciones enviadas
- Errores reportados

---

## 🎯 Conclusión

**Orden recomendado:** Feature 1 → Feature 3 → Feature 2

**Ventajas:**
- ✅ Lógica progresiva
- ✅ Máxima reutilización de código
- ✅ Testing incremental
- ✅ Menor riesgo

**Tiempo total estimado:** 5-7 días de desarrollo + 2 días de testing

**Estado:** LISTO PARA INICIAR IMPLEMENTACIÓN

**Siguiente paso:** Implementar Feature 1 (Meta Box Admin) 🚀
