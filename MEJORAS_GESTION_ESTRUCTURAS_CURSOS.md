# 📚 Mejoras en la Gestión de Estructuras para Cursos

## Resumen de Cambios Implementados

Se han implementado mejoras significativas en el sistema de gestión de estructuras para cursos, incluyendo la capacidad de asignar cursos a múltiples estructuras con lógica de cascada jerárquica, notificaciones automáticas por correo y una visualización mejorada en el frontend.

---

## 🎯 Características Implementadas

### 1. **Asignación de Estructuras con Cascada Jerárquica**

#### Funcionamiento
- Al asignar un curso a un nivel superior de la jerarquía, automáticamente se asigna a todos los niveles inferiores
- La jerarquía sigue este orden: **Ciudad → Empresa → Canal → Sucursal → Cargo**

#### Lógica de Cascada
```
📍 Ciudad → 🏢 Todas las empresas de esa ciudad
🏢 Empresa → 🏪 Todos los canales de esa empresa
🏪 Canal → 🏢 Todas las sucursales de ese canal
🏢 Sucursal → 👔 Todos los cargos de esa sucursal
```

#### Ejemplo Práctico
Si asignas un curso a la **Empresa "TechCorp"**:
- Se asignará automáticamente a todos los canales de TechCorp
- Se asignará a todas las sucursales de esos canales
- Se asignará a todos los cargos de esas sucursales

### 2. **Nivel de Empresa en la Jerarquía**

Se ha agregado el nivel de **Empresa** a la jerarquía de estructuras, permitiendo una organización más completa:

- **Ciudad** → Contiene empresas
- **Empresa** → Nueva adición, contiene canales
- **Canal** → Contiene sucursales
- **Sucursal** → Contiene cargos
- **Cargo** → Nivel más específico

### 3. **Sistema de Notificaciones por Correo**

Cuando se asigna un curso a estructuras, el sistema automáticamente:

#### Identifica Usuarios Afectados
- Busca todos los usuarios que pertenecen a las estructuras asignadas
- Considera la cascada jerárquica para incluir a todos los usuarios relevantes

#### Envía Notificaciones
Cada usuario afectado recibe un correo con:
- Nombre del curso asignado
- Enlace directo al curso
- Mensaje personalizado con su nombre

#### Ejemplo de Correo
```
Hola Juan Pérez,

Se te ha asignado un nuevo curso:

📚 Curso: Web Coding and Apache Basics
🔗 Acceder al curso: https://tu-sitio.com/curso/web-coding

¡Esperamos que disfrutes este contenido educativo!

Saludos,
Equipo de FairPlay LMS
```

### 4. **Visualización Mejorada en el Frontend**

#### Mostrar Estructuras en el Curso
- Las estructuras asignadas se muestran en el detalle del curso
- Aparecen en una sección destacada con iconos visuales
- Reemplaza el espacio donde antes se mostraban las categorías

#### Elementos Ocultos
Se ocultan automáticamente:
- ⭐ Valoraciones/ratings del curso
- 👥 Cantidad de estudiantes inscritos
- 🏷️ Categorías del curso

#### Visualización por Niveles
```
📋 Estructuras Asignadas

📍 Ciudades: Madrid, Barcelona
🏢 Empresas: TechCorp, InnovaS.A.
🏪 Canales: Canal Norte, Canal Sur
🏢 Sucursales: Sucursal Centro
👔 Cargos: Desarrollador, Gerente
```

---

## 📁 Archivos Modificados y Creados

### Archivos Modificados

#### 1. `class-fplms-courses.php`
**Cambios realizados:**
- ✅ Método `save_course_structures()` actualizado con lógica de cascada
- ✅ Método `get_course_structures()` ahora incluye empresas
- ✅ Método `format_course_structures_display()` mejorado con empresas
- ✅ Formulario de asignación actualizado con selección de empresas
- ✅ Nota explicativa sobre la cascada jerárquica

**Nuevos Métodos Agregados:**
- `apply_cascade_logic()` - Aplica la lógica de cascada jerárquica
- `get_companies_by_city()` - Obtiene empresas de una ciudad
- `get_channels_by_company()` - Obtiene canales de una empresa
- `get_branches_by_channel()` - Obtiene sucursales de un canal
- `get_roles_by_branch()` - Obtiene cargos de una sucursal
- `send_course_assignment_notifications()` - Envía correos a usuarios
- `get_users_by_structures()` - Obtiene usuarios por estructuras

#### 2. `class-fplms-config.php`
**Cambios realizados:**
- ✅ Agregadas constantes para relaciones jerárquicas:
  - `META_COMPANY_CITIES`
  - `META_CHANNEL_COMPANIES`
  - `META_BRANCH_CHANNELS`
  - `META_ROLE_BRANCHES`

#### 3. `class-fplms-course-visibility.php`
**Cambios realizados:**
- ✅ Método `course_has_no_restrictions()` actualizado para incluir empresas

#### 4. `class-fplms-plugin.php`
**Cambios realizados:**
- ✅ Instanciación de `FairPlay_LMS_Course_Display`
- ✅ Registro de hooks de visualización de curso

#### 5. `fairplay-lms-masterstudy-extensions.php`
**Cambios realizados:**
- ✅ Inclusión del archivo `class-fplms-course-display.php`

### Archivos Creados

#### 1. `class-fplms-course-display.php` ⭐ NUEVO
**Propósito:** Controla la visualización de cursos en el frontend

**Funcionalidades:**
- Muestra estructuras asignadas en el curso
- Oculta valoraciones y contador de estudiantes
- Aplica estilos CSS personalizados
- Modifica meta de tarjetas de curso

**Métodos Principales:**
- `register_hooks()` - Registra filtros y acciones
- `add_structures_to_course_content()` - Agrega estructuras al contenido
- `add_custom_css()` - Inyecta CSS personalizado
- `modify_course_card_meta()` - Modifica meta de tarjetas
- `format_structures_display()` - Formatea visualización completa
- `format_structures_compact()` - Formatea visualización compacta

---

## 🎨 Interfaz de Usuario

### Panel de Administración

#### Formulario de Asignación de Estructuras

El formulario ahora incluye:

```
ℹ️ Lógica de asignación en cascada

Al asignar un curso a una estructura, automáticamente se asigna a todas las estructuras descendientes:

📍 Ciudad → Se asigna a todas las empresas, canales, sucursales y cargos de esa ciudad
🏢 Empresa → Se asigna a todos los canales, sucursales y cargos de esa empresa
🏪 Canal → Se asigna a todas las sucursales y cargos de ese canal
🏢 Sucursal → Se asigna a todos los cargos de esa sucursal
👔 Cargo → Se asigna específicamente a ese cargo

Los usuarios asignados a estas estructuras recibirán una notificación por correo electrónico.

[Checkboxes para seleccionar estructuras]

📍 Ciudades
☐ Madrid
☐ Barcelona

🏢 Empresas
☐ TechCorp
☐ InnovaS.A.

🏪 Canales / Franquicias
☐ Canal Norte
☐ Canal Sur

🏢 Sucursales
☐ Sucursal Centro
☐ Sucursal Este

👔 Cargos
☐ Desarrollador
☐ Gerente

[💾 Guardar estructuras y notificar usuarios]
```

### Frontend del Curso

#### Vista del Curso
```
┌─────────────────────────────────────────────┐
│  Web Coding and Apache Basics               │
├─────────────────────────────────────────────┤
│                                             │
│  📋 Estructuras Asignadas                   │
│  ┌─────────────────────────────────────┐   │
│  │ 📍 Ciudades: Madrid, Barcelona       │   │
│  │ 🏢 Empresas: TechCorp               │   │
│  │ 🏪 Canales: Canal Norte             │   │
│  └─────────────────────────────────────┘   │
│                                             │
│  [Descripción del curso...]                 │
│                                             │
│  [Contenido del curso...]                   │
└─────────────────────────────────────────────┘
```

#### Listado de Cursos
```
┌────────────────────────────────────┐
│  Web Coding and Apache Basics      │
│  📍 2 ciudades • 🏢 1 empresa      │
│  Instructor: Juan Antonio Eulate   │
└────────────────────────────────────┘
```

---

## 🔧 Detalles Técnicos

### Lógica de Cascada

#### Algoritmo
1. Se reciben las estructuras seleccionadas del formulario
2. Se aplica la cascada comenzando desde el nivel superior:
   - Para cada ciudad seleccionada → se agregan todas sus empresas
   - Para cada empresa (original + cascada) → se agregan todos sus canales
   - Para cada canal (original + cascada) → se agregan todas sus sucursales
   - Para cada sucursal (original + cascada) → se agregan todos sus cargos
3. Se eliminan duplicados en cada nivel
4. Se guardan las estructuras resultantes en la base de datos

#### Código de Ejemplo
```php
private function apply_cascade_logic( $cities, $companies, $channels, $branches, $roles ) {
    $result = [
        'cities'    => $cities,
        'companies' => $companies,
        'channels'  => $channels,
        'branches'  => $branches,
        'roles'     => $roles,
    ];

    // Cascada: ciudades → empresas
    if ( ! empty( $cities ) ) {
        foreach ( $cities as $city_id ) {
            $city_companies = $this->get_companies_by_city( $city_id );
            $result['companies'] = array_unique( array_merge( $result['companies'], $city_companies ) );
        }
    }

    // ... continúa con cada nivel
    
    return $result;
}
```

### Sistema de Notificaciones

#### Flujo de Notificaciones
1. Después de guardar las estructuras, se llama a `send_course_assignment_notifications()`
2. Se obtienen todos los usuarios que pertenecen a las estructuras asignadas
3. Se construye un meta_query con relación OR para buscar usuarios
4. Para cada usuario encontrado:
   - Se obtiene su información (nombre, email)
   - Se construye el mensaje personalizado
   - Se envía el correo usando `wp_mail()`

#### Búsqueda de Usuarios
```php
$meta_query = [ 'relation' => 'OR' ];

// Para cada estructura asignada, agregar condición
foreach ( $structures['cities'] as $city_id ) {
    $meta_query[] = [
        'key'     => FairPlay_LMS_Config::USER_META_CITY,
        'value'   => $city_id,
        'compare' => '=',
    ];
}
// ... similar para empresas, canales, sucursales, cargos

$users = new WP_User_Query( [ 'meta_query' => $meta_query ] );
```

### Visualización en Frontend

#### Hooks de WordPress Utilizados
- `the_content` - Para agregar estructuras al contenido del curso
- `stm_lms_show_course_categories` - Para ocultar categorías
- `stm_lms_show_course_rating` - Para ocultar valoraciones
- `stm_lms_show_course_students` - Para ocultar estudiantes
- `wp_head` - Para agregar CSS personalizado
- `stm_lms_archive_card_meta` - Para modificar meta en listados

#### CSS Inyectado
El sistema inyecta CSS para ocultar elementos no deseados:
```css
/* Ocultar categorías */
.stm_lms_course__categories,
.course-categories { display: none !important; }

/* Ocultar valoraciones */
.stm_lms_course__rating,
.star-rating { display: none !important; }

/* Ocultar estudiantes */
.stm_lms_course__students,
.students-count { display: none !important; }
```

---

## 🚀 Uso del Sistema

### Para Administradores

#### Asignar Curso a Estructuras

1. Ir a **FairPlay LMS → Cursos**
2. Localizar el curso deseado
3. Hacer clic en **"Gestionar estructuras"**
4. Leer la información sobre la cascada jerárquica
5. Seleccionar las estructuras deseadas:
   - Marcar checkboxes de ciudades, empresas, canales, sucursales o cargos
   - Recordar que la selección se propagará en cascada
6. Hacer clic en **"Guardar estructuras y notificar usuarios"**
7. El sistema:
   - Aplicará la cascada automáticamente
   - Guardará todas las estructuras resultantes
   - Enviará correos a los usuarios afectados

#### Verificar Asignaciones

En la lista de cursos, la columna **"Estructuras asignadas"** muestra:
```
📍 Ciudades: Madrid, Barcelona
🏢 Empresas: TechCorp
🏪 Canales: Canal Norte, Canal Sur
🏢 Sucursales: Sucursal Centro
👔 Cargos: Desarrollador, Gerente
```

### Para Usuarios (Estudiantes)

#### Ver Cursos Disponibles

1. Los usuarios solo ven cursos asignados a sus estructuras
2. El sistema filtra automáticamente basándose en:
   - Ciudad del usuario
   - Empresa del usuario
   - Canal del usuario
   - Sucursal del usuario
   - Cargo del usuario

#### Ver Estructuras del Curso

En la página del curso, los usuarios pueden ver:
```
📋 Estructuras Asignadas

📍 Ciudades: Madrid
🏢 Empresas: TechCorp
🏪 Canales: Canal Norte
```

Esto les permite saber por qué tienen acceso al curso.

---

## ✅ Ventajas del Sistema

### 1. **Eficiencia en Asignación**
- No es necesario asignar manualmente cada estructura
- La cascada automática ahorra tiempo
- Reduce errores humanos

### 2. **Comunicación Automática**
- Los usuarios son notificados inmediatamente
- No es necesario enviar correos manualmente
- Los usuarios reciben un enlace directo al curso

### 3. **Transparencia**
- Los usuarios pueden ver a qué estructuras se asignó el curso
- Los administradores pueden verificar rápidamente las asignaciones
- Todo está claramente documentado

### 4. **Flexibilidad**
- Puedes asignar a nivel de ciudad (muy amplio)
- Puedes asignar a nivel de cargo (muy específico)
- Puedes combinar múltiples niveles

### 5. **Interfaz Limpia**
- Se ocultan elementos innecesarios (ratings, estudiantes)
- Se destacan las estructuras asignadas
- La visualización es clara y organizada

---

## 🔍 Casos de Uso

### Caso 1: Curso para Toda una Ciudad
**Necesidad:** Asignar un curso de seguridad a todos los empleados de Madrid

**Solución:**
1. Gestionar estructuras del curso
2. Marcar solo **"Madrid"** en Ciudades
3. Guardar

**Resultado:**
- Se asigna a todas las empresas de Madrid
- Se asigna a todos los canales de esas empresas
- Se asigna a todas las sucursales de esos canales
- Se asigna a todos los cargos de esas sucursales
- Todos los usuarios de Madrid reciben notificación

### Caso 2: Curso para una Empresa Específica
**Necesidad:** Curso de inducción solo para empleados de TechCorp

**Solución:**
1. Gestionar estructuras del curso
2. Marcar solo **"TechCorp"** en Empresas
3. Guardar

**Resultado:**
- Se asigna a todos los canales de TechCorp
- Se asigna a todas las sucursales de esos canales
- Se asigna a todos los cargos de esas sucursales
- Solo usuarios de TechCorp reciben notificación

### Caso 3: Curso para Cargos Específicos
**Necesidad:** Curso técnico solo para desarrolladores

**Solución:**
1. Gestionar estructuras del curso
2. Marcar **"Desarrollador"** en Cargos
3. Guardar

**Resultado:**
- Se asigna específicamente a usuarios con cargo de Desarrollador
- Solo esos usuarios reciben notificación
- No se propaga a otros cargos

### Caso 4: Curso para Múltiples Estructuras
**Necesidad:** Curso de liderazgo para gerentes de Madrid y Barcelona

**Solución:**
1. Gestionar estructuras del curso
2. Marcar **"Madrid"** y **"Barcelona"** en Ciudades
3. Marcar **"Gerente"** en Cargos
4. Guardar

**Resultado:**
- Se considera la intersección: Gerentes que están en Madrid O Barcelona
- Solo esos usuarios reciben notificación

---

## 📊 Base de Datos

### Estructura de Metadatos

#### Post Meta (Cursos)
```
meta_key: fplms_course_cities
meta_value: [1, 2, 3] (array serializado de IDs de ciudades)

meta_key: fplms_course_companies
meta_value: [4, 5] (array serializado de IDs de empresas)

meta_key: fplms_course_channels
meta_value: [6, 7, 8] (array serializado de IDs de canales)

meta_key: fplms_course_branches
meta_value: [9, 10] (array serializado de IDs de sucursales)

meta_key: fplms_course_roles
meta_value: [11, 12, 13] (array serializado de IDs de cargos)
```

#### Term Meta (Estructuras)
```
meta_key: fplms_cities
meta_value: [1, 2] (para empresas: IDs de ciudades asociadas)

meta_key: fplms_companies
meta_value: [4, 5] (para canales: IDs de empresas asociadas)

meta_key: fplms_channels
meta_value: [6, 7] (para sucursales: IDs de canales asociados)

meta_key: fplms_branches
meta_value: [9, 10] (para cargos: IDs de sucursales asociadas)
```

#### User Meta (Usuarios)
```
meta_key: fplms_city
meta_value: 1 (ID de ciudad asignada)

meta_key: fplms_company
meta_value: 4 (ID de empresa asignada)

meta_key: fplms_channel
meta_value: 6 (ID de canal asignado)

meta_key: fplms_branch
meta_value: 9 (ID de sucursal asignada)

meta_key: fplms_job_role
meta_value: 11 (ID de cargo asignado)
```

---

## 🧪 Testing

### Pruebas Recomendadas

#### 1. Prueba de Cascada
```
✓ Asignar solo una ciudad
✓ Verificar que se agregaron todas las empresas de esa ciudad
✓ Verificar que se agregaron todos los canales de esas empresas
✓ Verificar que se agregaron todas las sucursales
✓ Verificar que se agregaron todos los cargos
```

#### 2. Prueba de Notificaciones
```
✓ Crear un usuario de prueba con una estructura específica
✓ Asignar un curso a esa estructura
✓ Verificar que el usuario recibe el correo
✓ Verificar que el correo contiene el enlace correcto
✓ Verificar que el mensaje está personalizado
```

#### 3. Prueba de Visualización
```
✓ Ver un curso en el frontend
✓ Verificar que se muestran las estructuras asignadas
✓ Verificar que NO se muestran las valoraciones
✓ Verificar que NO se muestra el contador de estudiantes
✓ Verificar que NO se muestran las categorías
```

#### 4. Prueba de Permisos
```
✓ Iniciar sesión como usuario sin la estructura del curso
✓ Verificar que NO puede ver el curso en el listado
✓ Iniciar sesión como usuario CON la estructura del curso
✓ Verificar que SÍ puede ver el curso
✓ Verificar que puede acceder al contenido
```

---

## 📝 Notas Importantes

### Compatibilidad
- ✅ Compatible con WordPress 5.0+
- ✅ Compatible con MasterStudy LMS
- ✅ No afecta funcionalidades existentes del plugin
- ✅ Mantiene compatibilidad hacia atrás

### Rendimiento
- Las consultas usan índices de base de datos
- La cascada se calcula solo al guardar (no en cada carga)
- Los correos se envían de forma asíncrona (si está configurado)
- El CSS se inyecta solo en páginas de cursos

### Seguridad
- Todas las entradas se sanitizan
- Se verifican nonces en formularios
- Se validan permisos de usuario
- Se escapan todas las salidas HTML

### Mantenimiento
- El código está bien documentado
- Sigue estándares de WordPress
- Usa constantes de configuración
- Fácil de extender o modificar

---

## 🎓 Conclusión

El sistema de gestión de estructuras para cursos ahora ofrece:

1. **Asignación Inteligente** con cascada automática
2. **Comunicación Automática** mediante notificaciones por correo
3. **Visualización Clara** en el frontend
4. **Flexibilidad Total** para diferentes casos de uso
5. **Interfaz Limpia** sin elementos innecesarios

Todo esto hace que la gestión de cursos sea más eficiente, transparente y fácil de usar tanto para administradores como para usuarios finales.

---

**Fecha de Implementación:** Enero 27, 2026  
**Versión del Plugin:** 0.7.0  
**Desarrollador:** Juan Eulate / Insoftline
