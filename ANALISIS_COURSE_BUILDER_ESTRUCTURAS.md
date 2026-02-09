# 🎨 Análisis: Integración de Estructuras en Course Builder

**Fecha:** 5 de febrero de 2026  
**Objetivo:** Permitir asignar estructuras desde el Course Builder con control de permisos según el rol del usuario (Instructor limitado a sus canales, Admin sin restricciones).

---

## 🔍 Análisis de la Vista Course Builder

### Pantalla Analizada

**Ubicación:** `/user-account/edit-course/{course_id}/settings/main`

**URL de ejemplo:** `boostacademy.com.bo/user-account/edit-course/53680/settings/main`

**Características de la interfaz:**

### Navegación (Tabs)
- ⚙️ **Main** (activa)
- 🔒 Access
- 📋 Prerrequisitos
- 📁 Course files
- 🎓 Certificado
- 🎨 Elegir la página

### Sección Main (Activa)

**Campos visibles:**

1. **Course info**
   - Nombre del curso (input text)
   - URL (input text con edición)
   
2. **Categoría**
   - Dropdown selector
   - Botón "+" para agregar nueva categoría
   
3. **Nivel**
   - Dropdown: "Select level"
   
4. **Add a co-instructor**
   - Selector de instructor (dropdown)
   - Avatar del owner actual
   
5. **Imagen**
   - Área de drag & drop
   - Botón "Upload an image"

6. **Botón Save** (inferior derecha)

---

## 🎯 Requisitos Específicos

### Requisito Principal

> "Instructores podrán asignar el curso a un canal correspondiente pero **solo si ellos se encuentran en el mismo canal**"

### Desglose de Requisitos

1. **Para Administradores:**
   - ✅ Pueden asignar a CUALQUIER canal
   - ✅ Pueden asignar a CUALQUIER estructura
   - ✅ Sin restricciones

2. **Para Instructores (stm_lms_instructor):**
   - ⚠️ Solo pueden asignar a **sus propios canales**
   - ⚠️ Solo ven las estructuras a las que pertenecen
   - ⚠️ No pueden asignar a estructuras fuera de su alcance

3. **Validación de Seguridad:**
   - 🔒 Verificar en el backend que el instructor pertenece al canal
   - 🔒 No confiar solo en el frontend (podría manipularse)
   - 🔒 Rechazar guardado si intenta asignar canal no autorizado

---

## 🏗️ Arquitectura de la Solución

### Enfoque: Hook de MasterStudy + Meta Box Condicional

**El Course Builder de MasterStudy:**
- Es una interfaz SPA (Single Page Application)
- Usa AJAX para guardar cambios
- Tiene hooks/filtros propios para extender

**Nuestra solución:**
- Agregar nueva sección en el Course Builder
- Filtrar estructuras según el rol del usuario
- Validar en el backend al guardar

---

## 📋 Plan de Implementación

### Fase 1: Detectar Usuario Actual y sus Estructuras

**Nuevo método en [`class-fplms-courses.php`](wordpress/wp-content/plugins/fairplay-lms-masterstudy-extensions/includes/class-fplms-courses.php):**

```php
/**
 * Obtiene las estructuras asignadas al usuario actual.
 * 
 * @param int $user_id ID del usuario (0 = actual)
 * @return array Array con estructura: ['city' => ID, 'company' => ID, 'channel' => ID, ...]
 */
private function get_user_structures( int $user_id = 0 ): array {
    if ( 0 === $user_id ) {
        $user_id = get_current_user_id();
    }
    
    return [
        'city'    => (int) get_user_meta( $user_id, FairPlay_LMS_Config::USER_META_CITY, true ),
        'company' => (int) get_user_meta( $user_id, FairPlay_LMS_Config::USER_META_COMPANY, true ),
        'channel' => (int) get_user_meta( $user_id, FairPlay_LMS_Config::USER_META_CHANNEL, true ),
        'branch'  => (int) get_user_meta( $user_id, FairPlay_LMS_Config::USER_META_BRANCH, true ),
        'role'    => (int) get_user_meta( $user_id, FairPlay_LMS_Config::USER_META_ROLE, true ),
    ];
}

/**
 * Obtiene las estructuras disponibles para asignar según el rol del usuario.
 * 
 * - Admin: Todas las estructuras
 * - Instructor: Solo sus propias estructuras y descendientes
 * 
 * @return array Array con estructura: ['cities' => [...], 'channels' => [...], ...]
 */
private function get_available_structures_for_user(): array {
    $user_id = get_current_user_id();
    
    // Si es administrador, devuelve todas las estructuras
    if ( current_user_can( 'manage_options' ) || current_user_can( FairPlay_LMS_Config::CAP_MANAGE_STRUCTURES ) ) {
        return [
            'cities'    => $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_CITY ),
            'companies' => $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_COMPANY ),
            'channels'  => $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_CHANNEL ),
            'branches'  => $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_BRANCH ),
            'roles'     => $this->structures->get_active_terms_for_select( FairPlay_LMS_Config::TAX_ROLE ),
        ];
    }
    
    // Si es instructor, solo sus estructuras
    $user_structures = $this->get_user_structures( $user_id );
    
    $available = [
        'cities'    => [],
        'companies' => [],
        'channels'  => [],
        'branches'  => [],
        'roles'     => [],
    ];
    
    // Ciudad del instructor
    if ( $user_structures['city'] > 0 ) {
        $city_term = get_term( $user_structures['city'] );
        if ( $city_term && ! is_wp_error( $city_term ) ) {
            $available['cities'][ $city_term->term_id ] = $city_term->name;
        }
    }
    
    // Empresa del instructor
    if ( $user_structures['company'] > 0 ) {
        $company_term = get_term( $user_structures['company'] );
        if ( $company_term && ! is_wp_error( $company_term ) ) {
            $available['companies'][ $company_term->term_id ] = $company_term->name;
        }
    }
    
    // Canal del instructor (MUY IMPORTANTE)
    if ( $user_structures['channel'] > 0 ) {
        $channel_term = get_term( $user_structures['channel'] );
        if ( $channel_term && ! is_wp_error( $channel_term ) ) {
            $available['channels'][ $channel_term->term_id ] = $channel_term->name;
        }
    }
    
    // Sucursal del instructor
    if ( $user_structures['branch'] > 0 ) {
        $branch_term = get_term( $user_structures['branch'] );
        if ( $branch_term && ! is_wp_error( $branch_term ) ) {
            $available['branches'][ $branch_term->term_id ] = $branch_term->name;
        }
    }
    
    // Cargo del instructor
    if ( $user_structures['role'] > 0 ) {
        $role_term = get_term( $user_structures['role'] );
        if ( $role_term && ! is_wp_error( $role_term ) ) {
            $available['roles'][ $role_term->term_id ] = $role_term->name;
        }
    }
    
    return $available;
}
```

---

### Fase 2: Agregar Meta Box en Course Builder

**Hook a usar:** `add_meta_boxes` (igual que la implementación anterior)

**IMPORTANTE:** La meta box debe detectar el contexto:
- Si está en `/wp-admin/post.php` → Mostrar meta box estándar
- Si está en Course Builder → Mostrar versión adaptada

**Código actualizado del método `register_structures_meta_box()`:**

```php
/**
 * Registra la meta box de estructuras para cursos MasterStudy.
 */
public function register_structures_meta_box(): void {
    add_meta_box(
        'fplms_course_structures_metabox',
        '🏢 Asignar Estructuras',
        [ $this, 'render_structures_meta_box' ],
        FairPlay_LMS_Config::MS_PT_COURSE,
        'side',
        'default'
    );
}

/**
 * Renderiza el contenido de la meta box de estructuras.
 * Adapta el contenido según el rol del usuario.
 */
public function render_structures_meta_box( $post ): void {
    wp_nonce_field( 'fplms_save_course_structures', 'fplms_structures_nonce' );
    
    // Obtener estructuras actuales
    $current_structures = [];
    if ( $post->ID ) {
        $current_structures = $this->get_course_structures( $post->ID );
    }
    
    // Obtener estructuras disponibles según rol del usuario
    $available_structures = $this->get_available_structures_for_user();
    
    // Verificar si el usuario es instructor
    $is_instructor = in_array( FairPlay_LMS_Config::MS_ROLE_INSTRUCTOR, wp_get_current_user()->roles ?? [], true );
    $is_admin = current_user_can( 'manage_options' );
    
    ?>
    <div class="fplms-metabox-structures">
        <style>
            .fplms-metabox-structures {
                font-size: 13px;
            }
            .fplms-structure-section {
                margin-bottom: 15px;
                padding-bottom: 15px;
                border-bottom: 1px solid #ddd;
            }
            .fplms-structure-section:last-child {
                border-bottom: none;
            }
            .fplms-structure-title {
                font-weight: 600;
                margin-bottom: 8px;
                color: #1d2327;
            }
            .fplms-structure-checkbox {
                display: block;
                margin: 5px 0;
                padding: 3px 0;
            }
            .fplms-structure-checkbox input {
                margin-right: 5px;
            }
            .fplms-instructor-info {
                background: #fff3cd;
                border-left: 3px solid #ffc107;
                padding: 10px;
                margin-bottom: 15px;
                font-size: 12px;
                line-height: 1.5;
            }
            .fplms-admin-info {
                background: #d1ecf1;
                border-left: 3px solid #0c5460;
                padding: 10px;
                margin-bottom: 15px;
                font-size: 12px;
            }
        </style>
        
        <?php if ( $is_instructor && ! $is_admin ) : ?>
            <div class="fplms-instructor-info">
                <strong>👨‍🏫 Instructor</strong><br>
                Solo puedes asignar este curso a las estructuras donde estás asignado.
            </div>
        <?php else : ?>
            <div class="fplms-admin-info">
                <strong>👑 Administrador</strong><br>
                Puedes asignar a cualquier estructura.
            </div>
        <?php endif; ?>
        
        <!-- Ciudades -->
        <?php if ( ! empty( $available_structures['cities'] ) ) : ?>
        <div class="fplms-structure-section">
            <div class="fplms-structure-title">📍 Ciudades</div>
            <?php foreach ( $available_structures['cities'] as $term_id => $term_name ) : ?>
                <label class="fplms-structure-checkbox">
                    <input type="checkbox" 
                           name="fplms_course_cities[]" 
                           value="<?php echo esc_attr( $term_id ); ?>"
                           <?php checked( in_array( $term_id, $current_structures['cities'] ?? [], true ) ); ?>>
                    <?php echo esc_html( $term_name ); ?>
                </label>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Empresas -->
        <?php if ( ! empty( $available_structures['companies'] ) ) : ?>
        <div class="fplms-structure-section">
            <div class="fplms-structure-title">🏢 Empresas</div>
            <?php foreach ( $available_structures['companies'] as $term_id => $term_name ) : ?>
                <label class="fplms-structure-checkbox">
                    <input type="checkbox" 
                           name="fplms_course_companies[]" 
                           value="<?php echo esc_attr( $term_id ); ?>"
                           <?php checked( in_array( $term_id, $current_structures['companies'] ?? [], true ) ); ?>>
                    <?php echo esc_html( $term_name ); ?>
                </label>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Canales (CRÍTICO PARA INSTRUCTORES) -->
        <?php if ( ! empty( $available_structures['channels'] ) ) : ?>
        <div class="fplms-structure-section">
            <div class="fplms-structure-title">🏪 Canales</div>
            <?php foreach ( $available_structures['channels'] as $term_id => $term_name ) : ?>
                <label class="fplms-structure-checkbox">
                    <input type="checkbox" 
                           name="fplms_course_channels[]" 
                           value="<?php echo esc_attr( $term_id ); ?>"
                           <?php checked( in_array( $term_id, $current_structures['channels'] ?? [], true ) ); ?>>
                    <?php echo esc_html( $term_name ); ?>
                </label>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Sucursales -->
        <?php if ( ! empty( $available_structures['branches'] ) ) : ?>
        <div class="fplms-structure-section">
            <div class="fplms-structure-title">🏢 Sucursales</div>
            <?php foreach ( $available_structures['branches'] as $term_id => $term_name ) : ?>
                <label class="fplms-structure-checkbox">
                    <input type="checkbox" 
                           name="fplms_course_branches[]" 
                           value="<?php echo esc_attr( $term_id ); ?>"
                           <?php checked( in_array( $term_id, $current_structures['branches'] ?? [], true ) ); ?>>
                    <?php echo esc_html( $term_name ); ?>
                </label>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Cargos -->
        <?php if ( ! empty( $available_structures['roles'] ) ) : ?>
        <div class="fplms-structure-section">
            <div class="fplms-structure-title">👔 Cargos</div>
            <?php foreach ( $available_structures['roles'] as $term_id => $term_name ) : ?>
                <label class="fplms-structure-checkbox">
                    <input type="checkbox" 
                           name="fplms_course_roles[]" 
                           value="<?php echo esc_attr( $term_id ); ?>"
                           <?php checked( in_array( $term_id, $current_structures['roles'] ?? [], true ) ); ?>>
                    <?php echo esc_html( $term_name ); ?>
                </label>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <?php if ( empty( $available_structures['cities'] ) && empty( $available_structures['channels'] ) ) : ?>
            <p style="color: #d63638; font-size: 12px;">
                ⚠️ No tienes estructuras asignadas. Contacta al administrador.
            </p>
        <?php endif; ?>
    </div>
    <?php
}
```

---

### Fase 3: Validación de Seguridad en el Backend

**Modificar método `save_course_structures_on_publish()`:**

```php
/**
 * Guarda las estructuras cuando se guarda/publica un curso de MasterStudy.
 * INCLUYE VALIDACIÓN DE PERMISOS PARA INSTRUCTORES.
 */
public function save_course_structures_on_publish( int $post_id, WP_Post $post, bool $update ): void {
    
    // Verificaciones de seguridad (nonce, autosave, permisos, etc.)
    // ... (código anterior) ...
    
    // Obtener estructuras del POST
    $cities    = isset( $_POST['fplms_course_cities'] ) ? array_map( 'absint', (array) $_POST['fplms_course_cities'] ) : [];
    $companies = isset( $_POST['fplms_course_companies'] ) ? array_map( 'absint', (array) $_POST['fplms_course_companies'] ) : [];
    $channels  = isset( $_POST['fplms_course_channels'] ) ? array_map( 'absint', (array) $_POST['fplms_course_channels'] ) : [];
    $branches  = isset( $_POST['fplms_course_branches'] ) ? array_map( 'absint', (array) $_POST['fplms_course_branches'] ) : [];
    $roles     = isset( $_POST['fplms_course_roles'] ) ? array_map( 'absint', (array) $_POST['fplms_course_roles'] ) : [];
    
    // NUEVA VALIDACIÓN: Verificar que el instructor solo asigna a sus estructuras
    if ( ! $this->validate_instructor_structures( $channels, $cities, $companies, $branches, $roles ) ) {
        // El instructor intentó asignar a estructuras no autorizadas
        add_action( 'admin_notices', function() {
            echo '<div class="error notice"><p>⚠️ Error: No puedes asignar el curso a estructuras donde no estás asignado.</p></div>';
        });
        return;
    }
    
    // Aplicar cascada jerárquica
    $cascaded_structures = $this->apply_cascade_logic( $cities, $companies, $channels, $branches, $roles );
    
    // Guardar en post_meta
    // ... (código anterior) ...
    
    // Enviar notificaciones
    // ... (código anterior) ...
}

/**
 * Valida que el instructor solo asigne a estructuras donde está asignado.
 * Los administradores siempre pasan la validación.
 * 
 * @param array $channels  Canales a asignar
 * @param array $cities    Ciudades a asignar
 * @param array $companies Empresas a asignar
 * @param array $branches  Sucursales a asignar
 * @param array $roles     Cargos a asignar
 * @return bool True si es válido, False si no
 */
private function validate_instructor_structures( array $channels, array $cities = [], array $companies = [], array $branches = [], array $roles = [] ): bool {
    // Admin siempre puede asignar a cualquier estructura
    if ( current_user_can( 'manage_options' ) || current_user_can( FairPlay_LMS_Config::CAP_MANAGE_STRUCTURES ) ) {
        return true;
    }
    
    $user_id = get_current_user_id();
    $user_structures = $this->get_user_structures( $user_id );
    
    // Validar ciudades
    foreach ( $cities as $city_id ) {
        if ( $city_id > 0 && $city_id !== $user_structures['city'] ) {
            return false; // Intenta asignar a una ciudad diferente
        }
    }
    
    // Validar empresas
    foreach ( $companies as $company_id ) {
        if ( $company_id > 0 && $company_id !== $user_structures['company'] ) {
            return false;
        }
    }
    
    // Validar canales (CRÍTICO)
    foreach ( $channels as $channel_id ) {
        if ( $channel_id > 0 && $channel_id !== $user_structures['channel'] ) {
            return false; // Intenta asignar a un canal donde NO está
        }
    }
    
    // Validar sucursales
    foreach ( $branches as $branch_id ) {
        if ( $branch_id > 0 && $branch_id !== $user_structures['branch'] ) {
            return false;
        }
    }
    
    // Validar cargos
    foreach ( $roles as $role_id ) {
        if ( $role_id > 0 && $role_id !== $user_structures['role'] ) {
            return false;
        }
    }
    
    return true; // Todas las validaciones pasaron
}
```

---

## 🎨 Visualización en Course Builder

### Vista para Administrador

```
┌─────────────────────────────────────────┐
│ Main                                    │
├─────────────────────────────────────────┤
│                                         │
│ Course info                             │
│ Nombre del curso: [____________]        │
│ URL: [________________________]         │
│                                         │
│ Categoría: [Dropdown ▼]                │
│                                         │
│ Nivel: [Select level ▼]                │
│                                         │
│ Add a co-instructor: [Choose ▼]        │
│                                         │
│ Imagen: [Upload area]                  │
│                                         │
└─────────────────────────────────────────┘

SIDEBAR DERECHO:
┌─────────────────────────┐
│ 🏢 Asignar Estructuras  │
│ ─────────────────       │
│ 👑 Administrador        │
│ Puedes asignar a        │
│ cualquier estructura.   │
│                         │
│ 📍 Ciudades             │
│ ☑ Madrid                │
│ ☑ Barcelona             │
│ ☐ Valencia              │
│                         │
│ 🏪 Canales              │
│ ☑ Canal Norte           │
│ ☑ Canal Sur             │
│ ☐ Canal Este            │
│                         │
│ [... más estructuras]   │
└─────────────────────────┘
```

### Vista para Instructor

```
SIDEBAR DERECHO:
┌─────────────────────────┐
│ 🏢 Asignar Estructuras  │
│ ─────────────────       │
│ 👨‍🏫 Instructor           │
│ Solo puedes asignar a   │
│ tus estructuras.        │
│                         │
│ 📍 Ciudades             │
│ ☑ Barcelona             │
│ (tu ciudad)             │
│                         │
│ 🏪 Canales              │
│ ☑ Canal Norte           │
│ (tu canal)              │
│                         │
│ 🏢 Sucursales           │
│ ☐ Sucursal Centro       │
│ (tu sucursal)           │
│                         │
│ ℹ️ No ves más opciones  │
│ porque solo puedes      │
│ asignar a tu canal.     │
└─────────────────────────┘
```

---

## 🔒 Seguridad: Matriz de Permisos

### Tabla de Validación

| Rol | Puede asignar a | Validación Backend |
|-----|----------------|-------------------|
| **Administrator** | Todas las estructuras | ✅ Sin restricción |
| **stm_lms_instructor** | Solo sus estructuras | ⚠️ Validar con `get_user_structures()` |
| **subscriber** | Ninguna (no edita cursos) | ❌ No tiene acceso |

### Flujo de Validación

```
Instructor guarda curso con estructuras
    ↓
Frontend envía POST con estructuras seleccionadas
    ↓
Backend: save_course_structures_on_publish()
    ↓
¿Es admin?
    ├─ SÍ → Guardar sin validar ✅
    └─ NO → Continúa validación
    ↓
validate_instructor_structures()
    ↓
Para cada estructura seleccionada:
    ¿Pertenece el instructor a esa estructura?
        ├─ SÍ → Continúa
        └─ NO → RECHAZAR y mostrar error ❌
    ↓
Si todas pasan → Guardar estructuras ✅
Si alguna falla → Mostrar admin_notice con error ❌
```

---

## 📁 Archivos a Modificar

### 1. [`class-fplms-courses.php`](wordpress/wp-content/plugins/fairplay-lms-masterstudy-extensions/includes/class-fplms-courses.php)

**Métodos nuevos:**
- `get_user_structures()` - Obtiene estructuras del usuario
- `get_available_structures_for_user()` - Filtra estructuras según rol
- `validate_instructor_structures()` - Valida permisos del instructor

**Métodos a modificar:**
- `render_structures_meta_box()` - Agregar lógica condicional de permisos
- `save_course_structures_on_publish()` - Agregar validación de seguridad

### 2. [`class-fplms-plugin.php`](wordpress/wp-content/plugins/fairplay-lms-masterstudy-extensions/includes/class-fplms-plugin.php)

**No requiere cambios** - Los hooks ya están registrados.

---

## 🧪 Casos de Prueba

### Test 1: Administrador Asigna Cualquier Canal

**Setup:**
- Usuario: admin
- Canal a asignar: Canal Norte (admin NO está en este canal)

**Pasos:**
1. Editar curso en Course Builder
2. Seleccionar "Canal Norte" en estructuras
3. Guardar

**Resultado esperado:**
- ✅ Curso guardado con Canal Norte asignado
- ✅ Sin errores
- ✅ Notificaciones enviadas a usuarios del canal

### Test 2: Instructor Asigna su Propio Canal

**Setup:**
- Usuario: instructor1 (canal = Canal Norte)
- Canal a asignar: Canal Norte

**Pasos:**
1. Editar curso en Course Builder
2. Ver que SOLO aparece "Canal Norte" (su canal)
3. Seleccionar "Canal Norte"
4. Guardar

**Resultado esperado:**
- ✅ Curso guardado correctamente
- ✅ Estructuras asignadas
- ✅ Notificaciones enviadas

### Test 3: Instructor Intenta Asignar Canal No Autorizado ⚠️

**Setup:**
- Usuario: instructor1 (canal = Canal Norte)
- Intento de asignación: Canal Sur (mediante manipulación del HTML)

**Pasos:**
1. Instructor abre DevTools
2. Modifica HTML para agregar checkbox de "Canal Sur"
3. Selecciona "Canal Sur"
4. Intenta guardar

**Resultado esperado:**
- ❌ Backend rechaza el guardado
- ❌ Mensaje de error: "No puedes asignar a estructuras no autorizadas"
- ❌ Estructuras NO se guardan

### Test 4: Instructor sin Estructuras Asignadas

**Setup:**
- Usuario: instructor2 (sin canal, ciudad, etc. asignado)

**Pasos:**
1. Editar curso en Course Builder
2. Ver sección de estructuras

**Resultado esperado:**
- ⚠️ Mensaje: "No tienes estructuras asignadas"
- ⚠️ Sin checkboxes disponibles
- ℹ️ Sugerencia: "Contacta al administrador"

---

## 🔄 Flujo Completo de Funcionamiento

```
Instructor accede a Course Builder
    ↓
GET /user-account/edit-course/{id}/settings/main
    ↓
WordPress carga el editor
    ↓
add_meta_boxes hook se ejecuta
    ↓
render_structures_meta_box() se llama
    ↓
get_available_structures_for_user() ejecuta:
    ├─ current_user_can('manage_options')?
    │   ├─ SÍ → Devuelve TODAS las estructuras
    │   └─ NO → Continúa
    ├─ get_user_structures( current_user_id )
    ├─ Construye array solo con sus estructuras
    └─ Devuelve estructuras limitadas
    ↓
Renderiza meta box con opciones limitadas
    ↓
Instructor selecciona sus estructuras
    ↓
Hace clic en "Save"
    ↓
POST enviado con estructuras seleccionadas
    ↓
save_course_structures_on_publish() ejecuta
    ↓
validate_instructor_structures() verifica:
    ├─ ¿Es admin? → SÍ → ✅ Permitir
    ├─ Para cada estructura:
    │   ¿Coincide con las del usuario?
    │       ├─ SÍ → Continúa
    │       └─ NO → ❌ RECHAZAR TODO
    └─ Si todas pasan → ✅ PERMITIR
    ↓
Si es válido:
    ├─ apply_cascade_logic()
    ├─ update_post_meta() x 5
    ├─ send_course_assignment_notifications()
    └─ Éxito ✅
    
Si NO es válido:
    ├─ add_action('admin_notices', error)
    └─ NO guardar ❌
```

---

## ⚠️ Consideraciones Importantes

### 1. UX para Instructores sin Estructuras

**Problema:**
Si un instructor no tiene canal asignado, no puede asignar cursos a ninguna estructura.

**Solución:**
- Mostrar mensaje claro
- Sugerir contactar al administrador
- No bloquear la creación del curso (solo la asignación)

### 2. Cascada Automática

**Pregunta:**
¿Debe aplicarse la cascada cuando un instructor asigna su canal?

**Respuesta:**
- **SÍ** - Aplicar cascada normal
- Si selecciona "Canal Norte" → Se asignan automáticamente todas las sucursales y cargos de ese canal

### 3. Notificaciones

**Pregunta:**
¿Enviar notificaciones cuando un instructor asigna estructuras?

**Respuesta:**
- **SÍ** - Mismo comportamiento que el admin
- Al publicar/actualizar → Enviar correos a usuarios afectados

---

## 📊 Comparativa de Implementaciones

| Característica | Meta Box Estándar | Course Builder |
|----------------|-------------------|----------------|
| **Ubicación** | Admin tradicional | Frontend SPA |
| **Control de permisos** | ✅ Implementado | ✅ Implementado |
| **Filtrado de opciones** | ✅ Por rol | ✅ Por rol |
| **Validación backend** | ✅ Segura | ✅ Segura |
| **UX para instructor** | Estándar WordPress | Moderna SPA |
| **Cascada automática** | ✅ Sí | ✅ Sí |
| **Notificaciones** | ✅ Sí | ✅ Sí |

---

## 🚀 Beneficios de esta Implementación

### Para Administradores
1. ✅ **Control total** - Sin restricciones
2. ✅ **Asignación flexible** - Cualquier estructura
3. ✅ **Gestión centralizada** - Una sola interfaz

### Para Instructores
1. ✅ **Autonomía limitada** - Pueden asignar cursos
2. ✅ **Seguridad** - Solo a sus propios canales
3. ✅ **Interfaz simple** - Solo ven sus opciones
4. ✅ **Sin confusión** - No ven estructuras ajenas

### Técnicos
1. ✅ **Seguridad robusta** - Validación en backend
2. ✅ **Código reutilizable** - Usa métodos existentes
3. ✅ **Mantenible** - Lógica clara y documentada
4. ✅ **Escalable** - Fácil agregar más roles

---

## 📌 Conclusión

Esta implementación permite que **instructores asignen cursos a estructuras** mientras mantiene la **seguridad** al limitar sus opciones solo a las estructuras donde están asignados.

**Características clave:**
- ✅ Control de permisos por rol
- ✅ Validación en backend (no solo frontend)
- ✅ UX adaptada según usuario
- ✅ Compatible con sistema actual

**Estado:** LISTO PARA IMPLEMENTACIÓN

**Siguiente:** Definir orden de implementación de las 3 features
