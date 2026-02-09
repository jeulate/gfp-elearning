# 📊 Análisis: Mostrar Canales como Categorías en Vista de Cursos

**Fecha:** 5 de febrero de 2026  
**Objetivo:** Hacer que los canales sean visibles en la vista de cursos como si fueran categorías y que formen parte de los filtros de búsqueda.

---

## 🔍 Análisis de la Situación Actual

### 1. **Estado Actual de Canales**

Los **canales** (taxonomía `fplms_channel`) actualmente:
- ✅ Existen como taxonomía interna
- ✅ Se asignan a cursos mediante metadata (`fplms_course_channels`)
- ✅ Se usan para control de visibilidad
- ❌ NO se muestran en el campo de categorías
- ❌ NO aparecen como opciones de filtro en la búsqueda

### 2. **Visualización Actual de Estructuras**

Según el código en [`class-fplms-course-display.php`](wordpress/wp-content/plugins/fairplay-lms-masterstudy-extensions/includes/class-fplms-course-display.php):

```php
// Las estructuras se muestran en una sección separada
'channels'  => [ 'icon' => '🏪', 'label' => 'Canales' ],

// Se ocultan las categorías de MasterStudy
add_filter( 'stm_lms_show_course_categories', '__return_false', 999 );
```

**Ubicación actual:**
- Las estructuras (incluyendo canales) se muestran en un bloque separado titulado "📋 Estructuras Asignadas"
- Se muestra ANTES del contenido del curso
- Usa un diseño diferenciado con fondo gris

### 3. **Sistema de Categorías de MasterStudy**

MasterStudy usa:
- **Taxonomía:** `stm_lms_course_category` (categorías nativas)
- **Visualización:** Campo de categorías en la vista del curso
- **Filtros:** Sistema de filtros por categoría en archivo/búsqueda

**Problema:** Las categorías de MasterStudy están **OCULTAS** actualmente.

---

## 🎯 Requisito del Usuario

> "Necesito que en el campo que muestra la captura de categorías, el canal sea visible y que sea identificado como si fuera una categoría, que sea visible en la vista del curso y que forme parte de los filtros de búsqueda como filtro por canal"

### Interpretación del Requisito

1. **Mostrar canales en el mismo lugar donde aparecerían las categorías**
   - Sustituir o complementar las categorías de MasterStudy
   - Usar el mismo formato visual

2. **Los canales deben ser tratados como categorías**
   - Aparecer en la misma ubicación visual
   - Mismo estilo de presentación
   - Click/interacción similar

3. **Incluir en filtros de búsqueda**
   - Agregar filtro "Por Canal" en archivos de cursos
   - Permitir búsqueda/filtrado por canal
   - Integrar con sistema de búsqueda de MasterStudy

---

## 🏗️ Arquitectura de la Solución

### Opción A: **Integrar Canales como Pseudo-Categorías** ⭐ RECOMENDADA

**Concepto:**
- Mostrar los canales en el lugar de las categorías
- NO usar la taxonomía de MasterStudy
- Mantener el sistema actual pero modificar la visualización

**Ventajas:**
- ✅ Mantiene la lógica de visibilidad actual
- ✅ No requiere migrar datos
- ✅ Fácil de implementar
- ✅ No interfiere con MasterStudy

**Implementación:**

```php
// 1. Mostrar canales como categorías en la vista del curso
public function display_channels_as_categories( $course_id ) {
    $channels = get_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_CHANNELS, true );
    
    if ( empty( $channels ) ) {
        return '';
    }
    
    // Obtener nombres de canales
    $channel_names = [];
    foreach ( $channels as $channel_id ) {
        $term = get_term( $channel_id );
        if ( $term && ! is_wp_error( $term ) ) {
            $channel_names[] = [
                'id' => $channel_id,
                'name' => $term->name,
                'link' => add_query_arg( 'channel_filter', $channel_id, get_post_type_archive_link( 'stm-courses' ) )
            ];
        }
    }
    
    // Generar HTML similar a categorías de MasterStudy
    return $this->render_channel_categories( $channel_names );
}
```

---

### Opción B: **Usar Taxonomía de MasterStudy**

**Concepto:**
- Sincronizar canales con `stm_lms_course_category`
- Crear términos de categoría automáticamente
- Usar el sistema nativo de MasterStudy

**Ventajas:**
- ✅ Integración nativa con MasterStudy
- ✅ Filtros funcionan automáticamente

**Desventajas:**
- ❌ Duplicación de datos
- ❌ Complejidad de sincronización
- ❌ Posibles conflictos

---

## 📋 Plan de Implementación (Opción A)

### Fase 1: Modificar Visualización de Categorías

**Archivos a modificar:**
- [`class-fplms-course-display.php`](wordpress/wp-content/plugins/fairplay-lms-masterstudy-extensions/includes/class-fplms-course-display.php)

**Cambios:**

1. **Habilitar visualización de categorías con canales**

```php
// ANTES:
add_filter( 'stm_lms_show_course_categories', '__return_false', 999 );

// DESPUÉS:
add_filter( 'stm_lms_show_course_categories', [ $this, 'show_channels_as_categories' ], 999 );
```

2. **Nuevo método para renderizar canales como categorías**

```php
/**
 * Muestra los canales como si fueran categorías del curso.
 * 
 * @param bool $show Valor original del filtro
 * @return bool True para mostrar
 */
public function show_channels_as_categories( $show ) {
    global $post;
    
    if ( ! $post || FairPlay_LMS_Config::MS_PT_COURSE !== $post->post_type ) {
        return $show;
    }
    
    $channels = get_post_meta( $post->ID, FairPlay_LMS_Config::META_COURSE_CHANNELS, true );
    
    // Si hay canales, mostrar sección de categorías
    return ! empty( $channels );
}
```

3. **Hook para inyectar canales en el lugar de categorías**

```php
// En register_hooks()
add_filter( 'the_content', [ $this, 'inject_channel_categories' ], 15 );
add_filter( 'stm_lms_course_item_meta', [ $this, 'add_channel_to_course_meta' ], 10, 2 );
```

4. **Método para inyectar HTML de canales**

```php
/**
 * Inyecta los canales en el contenido del curso donde irían las categorías.
 */
public function inject_channel_categories( $content ) {
    if ( ! is_singular( FairPlay_LMS_Config::MS_PT_COURSE ) ) {
        return $content;
    }
    
    $course_id = get_the_ID();
    $channels = (array) get_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_CHANNELS, true );
    
    if ( empty( $channels ) ) {
        return $content;
    }
    
    // Generar HTML de canales similar a categorías de MasterStudy
    $channel_html = '<div class="stm_lms_course__categories stm-lms-course-categories fplms-channel-categories">';
    $channel_html .= '<div class="stm_lms_course__category_label">Canal:</div>';
    $channel_html .= '<div class="stm_lms_course__category_items">';
    
    foreach ( $channels as $channel_id ) {
        $term = get_term( $channel_id );
        if ( $term && ! is_wp_error( $term ) ) {
            $filter_url = add_query_arg(
                'channel_filter',
                $channel_id,
                get_post_type_archive_link( FairPlay_LMS_Config::MS_PT_COURSE )
            );
            
            $channel_html .= sprintf(
                '<a href="%s" class="stm-lms-course-category fplms-channel-tag">🏪 %s</a>',
                esc_url( $filter_url ),
                esc_html( $term->name )
            );
        }
    }
    
    $channel_html .= '</div></div>';
    
    // Inyectar antes del contenido principal
    return $channel_html . $content;
}
```

---

### Fase 2: Implementar Filtros de Búsqueda

**Archivos a modificar:**
- [`class-fplms-plugin.php`](wordpress/wp-content/plugins/fairplay-lms-masterstudy-extensions/includes/class-fplms-plugin.php)
- [`class-fplms-course-display.php`](wordpress/wp-content/plugins/fairplay-lms-masterstudy-extensions/includes/class-fplms-course-display.php)

**Cambios:**

1. **Agregar hook para modificar query de archivos**

```php
// En class-fplms-plugin.php register_hooks()
add_action( 'pre_get_posts', [ $this->course_display, 'filter_courses_by_channel' ] );
```

2. **Método para filtrar por canal**

```php
/**
 * Filtra los cursos por canal cuando se usa el parámetro channel_filter.
 * 
 * @param WP_Query $query Query principal
 */
public function filter_courses_by_channel( $query ) {
    // Solo en archivo de cursos y query principal
    if ( ! is_admin() && $query->is_main_query() && $query->is_post_type_archive( FairPlay_LMS_Config::MS_PT_COURSE ) ) {
        
        $channel_filter = isset( $_GET['channel_filter'] ) ? absint( $_GET['channel_filter'] ) : 0;
        
        if ( $channel_filter > 0 ) {
            // Obtener todos los cursos que tienen este canal
            $course_ids = $this->get_courses_by_channel( $channel_filter );
            
            if ( ! empty( $course_ids ) ) {
                $query->set( 'post__in', $course_ids );
            } else {
                // No hay cursos con este canal
                $query->set( 'post__in', [ 0 ] );
            }
        }
    }
}

/**
 * Obtiene todos los cursos asignados a un canal específico.
 * 
 * @param int $channel_id ID del canal
 * @return array Array de IDs de cursos
 */
private function get_courses_by_channel( $channel_id ) {
    global $wpdb;
    
    $course_ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT post_id 
         FROM {$wpdb->postmeta} 
         WHERE meta_key = %s 
         AND meta_value LIKE %s",
        FairPlay_LMS_Config::META_COURSE_CHANNELS,
        '%"' . $channel_id . '"%'
    ) );
    
    return array_map( 'absint', $course_ids );
}
```

---

### Fase 3: Agregar Widget/Selector de Filtro de Canal

**Nuevo archivo:** `class-fplms-course-filters.php`

```php
<?php
/**
 * Widget de filtros de cursos por canal.
 */
class FairPlay_LMS_Course_Filters {
    
    /**
     * Registra los hooks necesarios.
     */
    public function register_hooks() {
        // Agregar filtro en sidebar de archivo de cursos
        add_action( 'stm_lms_archive_sidebar', [ $this, 'render_channel_filter' ], 10 );
        
        // Shortcode para usar en cualquier lugar
        add_shortcode( 'fplms_channel_filter', [ $this, 'render_channel_filter_shortcode' ] );
    }
    
    /**
     * Renderiza el widget de filtro por canal.
     */
    public function render_channel_filter() {
        // Obtener todos los canales activos con cursos
        $channels = $this->get_channels_with_courses();
        
        if ( empty( $channels ) ) {
            return;
        }
        
        $current_channel = isset( $_GET['channel_filter'] ) ? absint( $_GET['channel_filter'] ) : 0;
        
        ?>
        <div class="fplms-channel-filter-widget stm-lms-archive-filter">
            <h4 class="fplms-filter-title">🏪 Filtrar por Canal</h4>
            <div class="fplms-channel-filter-list">
                <a href="<?php echo esc_url( get_post_type_archive_link( FairPlay_LMS_Config::MS_PT_COURSE ) ); ?>" 
                   class="fplms-channel-filter-item <?php echo $current_channel === 0 ? 'active' : ''; ?>">
                    Todos los canales
                </a>
                <?php foreach ( $channels as $channel ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'channel_filter', $channel->term_id, get_post_type_archive_link( FairPlay_LMS_Config::MS_PT_COURSE ) ) ); ?>" 
                       class="fplms-channel-filter-item <?php echo $current_channel === $channel->term_id ? 'active' : ''; ?>">
                        <?php echo esc_html( $channel->name ); ?>
                        <span class="fplms-course-count">(<?php echo esc_html( $channel->course_count ); ?>)</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <style>
            .fplms-channel-filter-widget {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 8px;
                margin-bottom: 20px;
            }
            
            .fplms-filter-title {
                margin: 0 0 15px 0;
                font-size: 1.1em;
                color: #0073aa;
            }
            
            .fplms-channel-filter-list {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }
            
            .fplms-channel-filter-item {
                padding: 10px 15px;
                background: white;
                border: 2px solid #ddd;
                border-radius: 5px;
                text-decoration: none;
                color: #333;
                transition: all 0.3s ease;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .fplms-channel-filter-item:hover {
                border-color: #0073aa;
                background: #f0f8ff;
            }
            
            .fplms-channel-filter-item.active {
                background: #0073aa;
                color: white;
                border-color: #0073aa;
            }
            
            .fplms-course-count {
                font-size: 0.9em;
                opacity: 0.7;
            }
        </style>
        <?php
    }
    
    /**
     * Obtiene todos los canales que tienen cursos asignados.
     * 
     * @return array Array de objetos term con course_count
     */
    private function get_channels_with_courses() {
        $channels = get_terms( [
            'taxonomy' => FairPlay_LMS_Config::TAX_CHANNEL,
            'hide_empty' => false,
            'meta_query' => [
                [
                    'key' => FairPlay_LMS_Config::META_ACTIVE,
                    'value' => '1',
                    'compare' => '='
                ]
            ]
        ] );
        
        if ( is_wp_error( $channels ) || empty( $channels ) ) {
            return [];
        }
        
        // Contar cursos por canal
        foreach ( $channels as &$channel ) {
            $channel->course_count = $this->count_courses_by_channel( $channel->term_id );
        }
        
        // Filtrar canales sin cursos
        $channels = array_filter( $channels, function( $channel ) {
            return $channel->course_count > 0;
        } );
        
        return $channels;
    }
    
    /**
     * Cuenta los cursos asignados a un canal.
     * 
     * @param int $channel_id ID del canal
     * @return int Cantidad de cursos
     */
    private function count_courses_by_channel( $channel_id ) {
        global $wpdb;
        
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(DISTINCT post_id) 
             FROM {$wpdb->postmeta} 
             WHERE meta_key = %s 
             AND meta_value LIKE %s",
            FairPlay_LMS_Config::META_COURSE_CHANNELS,
            '%"' . $channel_id . '"%'
        ) );
        
        return (int) $count;
    }
}
```

---

### Fase 4: Integración con el Plugin Principal

**Modificar:** [`class-fplms-plugin.php`](wordpress/wp-content/plugins/fairplay-lms-masterstudy-extensions/includes/class-fplms-plugin.php)

```php
// Agregar propiedad
private $course_filters;

// En __construct()
$this->course_filters = new FairPlay_LMS_Course_Filters();

// En register_hooks()
$this->course_filters->register_hooks();
```

---

## 🎨 Visualización Final

### En la Vista del Curso (Single Course)

```
┌─────────────────────────────────────────┐
│  FAIR PLAY SS26                         │
│                                         │
│  Canal: 🏪 Canal Norte                  │
│  ▲ Aparece donde estarían las categorías│
│                                         │
│  Instructor: Juan Antonio Eulate        │
│  Duración: 20 horas                     │
│                                         │
│  [Contenido del curso...]               │
└─────────────────────────────────────────┘
```

### En el Archivo de Cursos (Course Archive)

```
┌─────────────────┐  ┌────────────────────────┐
│ FILTROS         │  │  CURSOS ENCONTRADOS    │
│                 │  │                        │
│ 🏪 Por Canal    │  │  📚 Curso 1            │
│ ☑ Todos         │  │  Canal: Canal Norte    │
│ ☐ Canal Norte   │  │                        │
│ ☐ Canal Sur     │  │  📚 Curso 2            │
│ ☐ Canal Este    │  │  Canal: Canal Sur      │
│                 │  │                        │
└─────────────────┘  └────────────────────────┘
```

---

## 📁 Archivos a Crear/Modificar

### Nuevos Archivos
1. ✨ `class-fplms-course-filters.php` - Widget de filtros

### Archivos a Modificar
1. 📝 [`class-fplms-course-display.php`](wordpress/wp-content/plugins/fairplay-lms-masterstudy-extensions/includes/class-fplms-course-display.php) - Visualización de canales como categorías
2. 📝 [`class-fplms-plugin.php`](wordpress/wp-content/plugins/fairplay-lms-masterstudy-extensions/includes/class-fplms-plugin.php) - Integración del nuevo filtro
3. 📝 [`fairplay-lms-masterstudy-extensions.php`](wordpress/wp-content/plugins/fairplay-lms-masterstudy-extensions/fairplay-lms-masterstudy-extensions.php) - Include del nuevo archivo

---

## 🔄 Flujo de Datos

```
Usuario ve curso
     ↓
Display Hook (inject_channel_categories)
     ↓
Obtiene canales del curso (META_COURSE_CHANNELS)
     ↓
Genera HTML similar a categorías
     ↓
Inyecta en el lugar de categorías

Usuario hace click en canal
     ↓
Redirige a: /cursos/?channel_filter=123
     ↓
Hook pre_get_posts (filter_courses_by_channel)
     ↓
Busca cursos con ese canal
     ↓
Modifica query para mostrar solo esos cursos
```

---

## ✅ Checklist de Implementación

### Backend (Admin)
- [x] Canales ya se asignan a cursos ✅ (Ya implementado)
- [x] Sistema de guardado funciona ✅ (Ya implementado)

### Frontend (Visualización)
- [ ] Mostrar canales en lugar de categorías
- [ ] Aplicar estilos de MasterStudy a los canales
- [ ] Links funcionales a filtro por canal
- [ ] Ocultar/mostrar según configuración

### Filtros de Búsqueda
- [ ] Crear widget de filtro por canal
- [ ] Implementar lógica de filtrado
- [ ] Integrar con query de WordPress
- [ ] Contador de cursos por canal

### Testing
- [ ] Ver curso con canales asignados
- [ ] Ver curso sin canales
- [ ] Filtrar por canal en archivo
- [ ] Combinación con otros filtros
- [ ] Responsive design

---

## 🚀 Beneficios de la Implementación

### Para Usuarios
1. ✅ **Visibilidad clara** de a qué canal pertenece cada curso
2. ✅ **Filtrado rápido** por canal de interés
3. ✅ **Experiencia consistente** con otras plataformas LMS

### Para Administradores
1. ✅ **Sin duplicación de datos** - usa el sistema actual
2. ✅ **Fácil gestión** - mismo flujo de asignación
3. ✅ **Reportes precisos** - basados en canales

### Técnicos
1. ✅ **No modifica MasterStudy** - solo extiende
2. ✅ **Mantenible** - código modular
3. ✅ **Escalable** - fácil agregar más filtros

---

## 💡 Mejoras Futuras (Opcional)

### Fase 5: Filtros Avanzados
- Combinar filtros (Canal + Empresa + Sucursal)
- Búsqueda por texto + canal
- Ordenamiento personalizado

### Fase 6: Analytics
- Tracking de clics por canal
- Cursos más populares por canal
- Reportes de uso

### Fase 7: Shortcodes
- `[fplms_courses channel="norte"]` - Mostrar cursos de un canal
- `[fplms_channel_list]` - Lista de canales con contador
- `[fplms_channel_widget]` - Widget de filtros

---

## 📌 Notas Importantes

### Compatibilidad
- ✅ Compatible con sistema actual de visibilidad
- ✅ No interfiere con categorías de MasterStudy
- ✅ Funciona con temas de MasterStudy

### Rendimiento
- Usa caché de WordPress cuando es posible
- Queries optimizadas con índices de database
- Lazy loading de contadores de cursos

### Seguridad
- Sanitización de parámetros GET
- Validación de IDs de canales
- Escape de output HTML

---

## 🎯 Resultado Final

Después de implementar este análisis, los usuarios podrán:

1. **Ver claramente** el canal de cada curso en su página
2. **Filtrar cursos** por canal desde el archivo de cursos
3. **Navegar fácilmente** entre cursos del mismo canal
4. **Buscar específicamente** cursos de un canal

Todo esto **sin modificar** la estructura actual y manteniendo la **compatibilidad** con MasterStudy.

---

## 📞 Próximos Pasos

1. ✅ **Revisión de este análisis** - Confirmar que cumple con los requisitos
2. ⏭️ **Implementación Fase 1** - Visualización de canales
3. ⏭️ **Implementación Fase 2** - Filtros de búsqueda
4. ⏭️ **Implementación Fase 3** - Widget de filtros
5. ⏭️ **Testing y ajustes** - Validar en entorno real

**¿Procedemos con la implementación?** 🚀
