<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Controla la visualización de cursos en el frontend.
 * 
 * - Muestra las estructuras asignadas en lugar de categorías
 * - Oculta valoraciones
 * - Oculta cantidad de estudiantes inscritos
 */
class FairPlay_LMS_Course_Display {

    /**
     * Registra todos los hooks para modificar la visualización del curso.
     */
    public function register_hooks(): void {
        // Agregar estructuras al contenido del curso (single course)
        add_filter( 'the_content', [ $this, 'add_structures_to_course_content' ], 20 );
        
        // Ocultar categorías en el curso
        add_filter( 'stm_lms_show_course_categories', '__return_false', 999 );
        
        // Ocultar valoraciones/ratings
        add_filter( 'stm_lms_show_course_rating', '__return_false', 999 );
        
        // Ocultar cantidad de estudiantes
        add_filter( 'stm_lms_show_course_students', '__return_false', 999 );
        
        // Ocultar contador de estudiantes con otro filtro alternativo
        add_filter( 'stm_lms_course_students_count', '__return_empty_string', 999 );
        
        // Agregar CSS personalizado para ocultar elementos
        add_action( 'wp_head', [ $this, 'add_custom_css' ] );
        
        // Modificar la información del curso en shortcodes y listados
        add_filter( 'stm_lms_archive_card_meta', [ $this, 'modify_course_card_meta' ], 10, 2 );
    }

    /**
     * Agrega las estructuras asignadas al contenido del curso.
     * Esto aparecerá donde normalmente están las categorías.
     * 
     * @param string $content Contenido del post
     * @return string Contenido modificado
     */
    public function add_structures_to_course_content( string $content ): string {
        // Solo aplicar en single course
        if ( ! is_singular( FairPlay_LMS_Config::MS_PT_COURSE ) ) {
            return $content;
        }

        $course_id = get_the_ID();
        if ( ! $course_id ) {
            return $content;
        }

        // Obtener estructuras del curso
        $structures = $this->get_course_structures( $course_id );
        $structures_html = $this->format_structures_display( $structures );

        // Si hay estructuras, agregarlas antes del contenido
        if ( $structures_html ) {
            $structures_section = '<div class="fplms-course-structures" style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-left: 4px solid #0073aa; border-radius: 4px;">';
            $structures_section .= '<h3 style="margin-top: 0; font-size: 1.1em; color: #0073aa;">📋 Estructuras Asignadas</h3>';
            $structures_section .= $structures_html;
            $structures_section .= '</div>';

            $content = $structures_section . $content;
        }

        return $content;
    }

    /**
     * Agrega CSS personalizado para ocultar elementos no deseados.
     */
    public function add_custom_css(): void {
        // Solo aplicar en páginas de cursos
        if ( ! is_singular( FairPlay_LMS_Config::MS_PT_COURSE ) && ! is_post_type_archive( FairPlay_LMS_Config::MS_PT_COURSE ) ) {
            return;
        }

        ?>
        <style type="text/css">
            /* Ocultar categorías del curso */
            .stm_lms_course__categories,
            .stm-lms-course_category,
            .course_category,
            .course-categories,
            .stm-lms-course-categories {
                display: none !important;
            }

            /* Ocultar valoraciones/ratings */
            .stm_lms_course__rating,
            .stm-lms-course_rating,
            .course-rating,
            .average-rating,
            .star-rating,
            .stm-lms-course__reviews,
            .course_marks,
            .reviews_average {
                display: none !important;
            }

            /* Ocultar contador de estudiantes */
            .stm_lms_course__students,
            .stm-lms-course_students,
            .course-students,
            .students-count,
            .course_students,
            .stm-lms-course-students {
                display: none !important;
            }

            /* Estilo para la sección de estructuras */
            .fplms-course-structures {
                animation: fadeIn 0.3s ease-in;
            }

            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(-10px); }
                to { opacity: 1; transform: translateY(0); }
            }

            .fplms-structure-item {
                display: inline-block;
                margin: 5px 10px 5px 0;
                padding: 6px 12px;
                background: #ffffff;
                border: 1px solid #ddd;
                border-radius: 3px;
                font-size: 0.9em;
            }

            .fplms-structure-icon {
                margin-right: 5px;
            }
        </style>
        <?php
    }

    /**
     * Modifica el meta de las tarjetas de curso en listados y archivos.
     * 
     * @param array $meta Array de meta a mostrar
     * @param int   $course_id ID del curso
     * @return array Meta modificado
     */
    public function modify_course_card_meta( array $meta, int $course_id ): array {
        // Remover elementos de meta no deseados
        $meta = array_filter( $meta, function( $item ) {
            // Filtrar items que contengan ratings, estudiantes o categorías
            $unwanted = [ 'rating', 'student', 'category', 'review' ];
            foreach ( $unwanted as $keyword ) {
                if ( stripos( $item, $keyword ) !== false ) {
                    return false;
                }
            }
            return true;
        });

        // Agregar estructuras al meta
        $structures = $this->get_course_structures( $course_id );
        if ( ! empty( $structures ) ) {
            $structures_display = $this->format_structures_compact( $structures );
            if ( $structures_display ) {
                array_unshift( $meta, $structures_display );
            }
        }

        return $meta;
    }

    /**
     * Obtiene las estructuras asignadas a un curso.
     * 
     * @param int $course_id ID del curso
     * @return array Estructuras del curso
     */
    private function get_course_structures( int $course_id ): array {
        return [
            'cities'    => (array) get_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_CITIES, true ),
            'companies' => (array) get_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_COMPANIES, true ),
            'channels'  => (array) get_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_CHANNELS, true ),
            'branches'  => (array) get_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_BRANCHES, true ),
            'roles'     => (array) get_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_ROLES, true ),
        ];
    }

    /**
     * Formatea las estructuras para mostrar de forma completa.
     * 
     * @param array $structures Estructuras del curso
     * @return string HTML formateado
     */
    private function format_structures_display( array $structures ): string {
        $output = '';
        $has_structures = false;

        $structure_types = [
            'cities'    => [ 'icon' => '📍', 'label' => 'Ciudades' ],
            'companies' => [ 'icon' => '🏢', 'label' => 'Empresas' ],
            'channels'  => [ 'icon' => '🏪', 'label' => 'Canales' ],
            'branches'  => [ 'icon' => '🏢', 'label' => 'Sucursales' ],
            'roles'     => [ 'icon' => '👔', 'label' => 'Cargos' ],
        ];

        foreach ( $structure_types as $key => $data ) {
            if ( ! empty( $structures[ $key ] ) ) {
                $has_structures = true;
                $names = $this->get_term_names_by_ids( $structures[ $key ] );
                
                if ( ! empty( $names ) ) {
                    $output .= '<div style="margin: 10px 0;">';
                    $output .= '<strong>' . esc_html( $data['icon'] . ' ' . $data['label'] ) . ':</strong> ';
                    
                    foreach ( $names as $name ) {
                        $output .= '<span class="fplms-structure-item">' . esc_html( $name ) . '</span>';
                    }
                    
                    $output .= '</div>';
                }
            }
        }

        if ( ! $has_structures ) {
            return '<p style="margin: 0; color: #666; font-style: italic;">Este curso está disponible para todos los usuarios.</p>';
        }

        return $output;
    }

    /**
     * Formatea las estructuras de forma compacta para listados.
     * 
     * @param array $structures Estructuras del curso
     * @return string HTML formateado compacto
     */
    private function format_structures_compact( array $structures ): string {
        $items = [];

        if ( ! empty( $structures['cities'] ) ) {
            $count = count( $structures['cities'] );
            $items[] = "📍 {$count} ciudad" . ( $count > 1 ? 'es' : '' );
        }

        if ( ! empty( $structures['companies'] ) ) {
            $count = count( $structures['companies'] );
            $items[] = "🏢 {$count} empresa" . ( $count > 1 ? 's' : '' );
        }

        if ( ! empty( $structures['channels'] ) ) {
            $count = count( $structures['channels'] );
            $items[] = "🏪 {$count} canal" . ( $count > 1 ? 'es' : '' );
        }

        if ( ! empty( $structures['branches'] ) ) {
            $count = count( $structures['branches'] );
            $items[] = "🏢 {$count} sucursal" . ( $count > 1 ? 'es' : '' );
        }

        if ( ! empty( $structures['roles'] ) ) {
            $count = count( $structures['roles'] );
            $items[] = "👔 {$count} cargo" . ( $count > 1 ? 's' : '' );
        }

        if ( empty( $items ) ) {
            return '<span style="color: #666; font-size: 0.9em;">✅ Disponible para todos</span>';
        }

        return '<span style="color: #0073aa; font-size: 0.9em;">' . implode( ' • ', $items ) . '</span>';
    }

    /**
     * Obtiene los nombres de términos por sus IDs.
     * 
     * @param array $term_ids Array de IDs
     * @return array Array de nombres
     */
    private function get_term_names_by_ids( array $term_ids ): array {
        $names = [];
        foreach ( $term_ids as $term_id ) {
            $term = get_term( (int) $term_id );
            if ( $term && ! is_wp_error( $term ) ) {
                $names[] = $term->name;
            }
        }
        return $names;
    }
}
