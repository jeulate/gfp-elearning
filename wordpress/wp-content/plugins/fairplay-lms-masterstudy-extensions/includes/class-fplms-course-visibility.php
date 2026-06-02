<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Servicio de visibilidad de cursos basado en estructuras del usuario.
 *
 * Controla qué cursos son visibles para cada usuario según su asignación
 * de estructuras (ciudad, canal, sucursal, cargo).
 */
class FairPlay_LMS_Course_Visibility_Service {

    /**
     * Regla de estado para visibilidad de cursos.
     * - publish: visible para todos según restricciones de estructura.
     * - inactivo (draft/private/pending): solo creador o administrador.
     */
    private function can_user_view_course_status( int $user_id, int $course_id ): bool {
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }

        $status = get_post_status( $course_id );
        if ( false === $status ) {
            return false;
        }

        if ( 'publish' === $status ) {
            return true;
        }

        $author_id = (int) get_post_field( 'post_author', $course_id );
        return $author_id > 0 && $author_id === $user_id;
    }

    /**
     * Obtiene los cursos visibles para un usuario específico.
     *
     * @param int $user_id ID del usuario.
     * @return array Array de IDs de cursos visibles.
     */
    public function get_visible_courses_for_user( int $user_id ): array {

        // Obtener estructura del usuario
        $user_structures = $this->get_user_structures( $user_id );

        // Si el usuario no tiene estructura asignada, puede ver todos los cursos
        if ( empty( $user_structures ) ) {
            return $this->get_all_courses();
        }

        // Obtener todos los cursos
        $courses = $this->get_all_courses();

        // Filtrar cursos según estructura del usuario
        $visible_courses = [];

        foreach ( $courses as $course_id ) {
            if ( $this->can_user_see_course( $user_id, $course_id, $user_structures ) ) {
                $visible_courses[] = $course_id;
            }
        }

        return $visible_courses;
    }

    /**
     * Verifica si un usuario puede ver un curso específico.
     *
     * @param int   $user_id ID del usuario.
     * @param int   $course_id ID del curso.
     * @param array $user_structures Estructuras del usuario (opcional, se obtienen si no se pasan).
     * @return bool True si el usuario puede ver el curso, false en caso contrario.
     */
    public function can_user_see_course( int $user_id, int $course_id, array $user_structures = [] ): bool {
    // Depuración temporal para curso 53818
    if ( $course_id === 53818 && $user_id === 5 ) {
        error_log( "=== FPLMS DEBUG: can_user_see_course para curso 53818 ===" );
    }
    
    if ( ! $this->can_user_view_course_status( $user_id, $course_id ) ) {
        if ( $course_id === 53818 && $user_id === 5 ) {
            error_log( "FPLMS: Curso 53818 - FALLÓ por can_user_view_course_status" );
        }
        return false;
    }

    // Si no se pasan estructuras, obtenerlas
    if ( empty( $user_structures ) ) {
        $user_structures = $this->get_user_structures( $user_id );
        if ( $course_id === 53818 && $user_id === 5 ) {
            error_log( "FPLMS: Estructuras del usuario: " . print_r($user_structures, true) );
        }
    }

    // Si el usuario no tiene estructura asignada, puede ver todos los cursos
    if ( empty( $user_structures ) ) {
        if ( $course_id === 53818 && $user_id === 5 ) {
            error_log( "FPLMS: Curso 53818 - visible porque usuario no tiene estructuras" );
        }
        return true;
    }

    // Obtener estructuras asignadas al curso
    $course_structures = $this->get_course_structures( $course_id );
    if ( $course_id === 53818 && $user_id === 5 ) {
        error_log( "FPLMS: Estructuras del curso: " . print_r($course_structures, true) );
    }

    // Si el curso no tiene restricciones de estructura, es visible para todos
    if ( $this->course_has_no_restrictions( $course_structures ) ) {
        if ( $course_id === 53818 && $user_id === 5 ) {
            error_log( "FPLMS: Curso 53818 - visible porque curso no tiene restricciones" );
        }
        return true;
    }

    $result = $this->structures_match( $user_structures, $course_structures );
    if ( $course_id === 53818 && $user_id === 5 ) {
        error_log( "FPLMS: Curso 53818 - structures_match = " . ($result ? 'TRUE' : 'FALSE') );
    }
    
    return $result;
}

    /**
     * Obtiene las estructuras asignadas a un usuario.
     * Las meta keys son las que realmente existen en la base de datos.
     *
     * @param int $user_id ID del usuario.
     * @return array Array con estructura: ['channel' => id, 'branch' => id, 'role' => id, 'city' => id, 'company' => id].
     */
    private function get_user_structures( int $user_id ): array {

        // Las meta keys REALES en la base de datos
        $structures = [
            'city'    => (int) get_user_meta( $user_id, 'fplms_city', true ),
            'company' => (int) get_user_meta( $user_id, 'fplms_company', true ),
            'channel' => (int) get_user_meta( $user_id, 'fplms_channel', true ),
            'branch'  => (int) get_user_meta( $user_id, 'fplms_branch', true ),
            'role'    => (int) get_user_meta( $user_id, 'fplms_job_role', true ),
        ];

        // Remover estructura vacía (0 significa no asignada)
        $structures = array_filter( $structures );

        return $structures;
    }

    /**
     * Obtiene las estructuras asignadas a un curso.
     *
     * @param int $course_id ID del curso.
     * @return array Array con estructura: ['cities' => [ids], 'companies' => [ids], 'channels' => [ids], 'branches' => [ids], 'roles' => [ids]].
     */
    private function get_course_structures( int $course_id ): array {

        return [
            'cities'    => array_filter( array_map( 'intval', (array) get_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_CITIES, true ) ) ),
            'companies' => array_filter( array_map( 'intval', (array) get_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_COMPANIES, true ) ) ),
            'channels'  => array_filter( array_map( 'intval', (array) get_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_CHANNELS, true ) ) ),
            'branches'  => array_filter( array_map( 'intval', (array) get_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_BRANCHES, true ) ) ),
            'roles'     => array_filter( array_map( 'intval', (array) get_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_ROLES, true ) ) ),
        ];
    }

    /**
     * Verifica si un curso no tiene restricciones de estructura.
     *
     * @param array $course_structures Estructuras del curso.
     * @return bool True si el curso no tiene restricciones.
     */
    private function course_has_no_restrictions( array $course_structures ): bool {

        $has_restriction = false;

        foreach ( [ 'cities', 'companies', 'channels', 'branches', 'roles' ] as $key ) {
            if ( ! empty( $course_structures[ $key ] ) ) {
                $has_restriction = true;
                break;
            }
        }

        return ! $has_restriction;
    }

    /**
     * Verifica si hay coincidencia entre estructuras del usuario y del curso.
     *
     * @param array $user_structures Estructuras del usuario.
     * @param array $course_structures Estructuras del curso.
     * @return bool True si hay coincidencia.
     */
    private function structures_match( array $user_structures, array $course_structures ): bool {

        $mapping = [
            'city'    => 'cities',
            'company' => 'companies',
            'channel' => 'channels',
            'branch'  => 'branches',
            'role'    => 'roles',
        ];

        foreach ( $mapping as $user_key => $course_key ) {

            if ( empty( $course_structures[ $course_key ] ) ) {
                continue;
            }

            if ( ! isset( $user_structures[ $user_key ] ) ) {
                return false;
            }

            $user_val    = (int) $user_structures[ $user_key ];
            $course_vals = array_map( 'intval', (array) $course_structures[ $course_key ] );

            if ( ! in_array( $user_val, $course_vals, true ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Obtiene todos los cursos MasterStudy publicados.
     *
     * @return array Array de IDs de cursos.
     */
    private function get_all_courses(): array {

        $courses = get_posts(
            [
                'post_type'      => FairPlay_LMS_Config::MS_PT_COURSE,
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'fields'         => 'ids',
            ]
        );

        return (array) $courses;
    }

    /**
     * Filtra un array de cursos según la visibilidad del usuario.
     *
     * @param array $course_ids Array de IDs de cursos.
     * @param int   $user_id ID del usuario.
     * @return array Array filtrado de IDs de cursos.
     */
    public function filter_courses_array( array $course_ids, $raw_user_id = 0 ): array {

        $user_id = (int) $raw_user_id;
        if ( 0 === $user_id ) {
            $user_id = get_current_user_id();
        }
        if ( 0 === $user_id ) {
            return $course_ids;
        }

        if ( current_user_can( 'manage_options' ) ) {
            return $course_ids;
        }

        return array_filter(
            $course_ids,
            function( $value, $key ) use ( $user_id ) {
                if ( is_array( $value ) || is_object( $value ) ) {
                    if ( is_numeric( $key ) ) {
                        $course_id = (int) $key;
                    } else {
                        preg_match( '/(\d+)$/', (string) $key, $m );
                        $course_id = ! empty( $m[1] ) ? (int) $m[1] : 0;
                    }
                } else {
                    $course_id = (int) $value;
                }

                if ( $course_id <= 0 ) {
                    return false;
                }
                return $this->can_user_see_course( $user_id, $course_id );
            },
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * Filtra la respuesta AJAX de cursos matriculados.
     *
     * @param array $response Respuesta completa.
     * @return array Respuesta con cursos filtrados.
     */
    public function filter_user_courses_response( array $response ): array {
    // Administrador: ve todo sin restricción
    if ( current_user_can( 'manage_options' ) ) {
        return $response;
    }

    // Log para depuración
    error_log( "FPLMS: filter_user_courses_response - Inicio. Claves de respuesta: " . print_r(array_keys($response), true) );
    
    // Intentar encontrar los cursos en diferentes estructuras posibles
    $courses = null;
    $courses_key = null;
    
    // Formato 1: $response['courses'] (más común)
    if ( isset( $response['courses'] ) && is_array( $response['courses'] ) ) {
        $courses = $response['courses'];
        $courses_key = 'courses';
        error_log( "FPLMS: Cursos encontrados en 'courses', count=" . count($courses) );
    }
    // Formato 2: $response['data']['courses']
    elseif ( isset( $response['data']['courses'] ) && is_array( $response['data']['courses'] ) ) {
        $courses = $response['data']['courses'];
        $courses_key = 'data.courses';
        error_log( "FPLMS: Cursos encontrados en 'data.courses', count=" . count($courses) );
    }
    // Formato 3: $response directo es un array indexado
    elseif ( is_array( $response ) && ! isset( $response['courses'] ) && ! isset( $response['data'] ) ) {
        // Verificar si el primer elemento tiene 'course_id'
        $first = reset($response);
        if ( is_array( $first ) && ( isset( $first['course_id'] ) || isset( $first['id'] ) ) ) {
            $courses = $response;
            $courses_key = 'direct';
            error_log( "FPLMS: Cursos encontrados en array directo, count=" . count($courses) );
        }
    }
    
    if ( empty( $courses ) ) {
        error_log( "FPLMS: No se encontraron cursos en la respuesta. Retornando original." );
        return $response;
    }
    
    $user_id = get_current_user_id();
    $original_count = count( $courses );
    error_log( "FPLMS: Procesando {$original_count} cursos para usuario {$user_id}" );
    
    // Filtrar cursos
    $filtered_courses = array_values(
        array_filter(
            $courses,
            function( $course ) use ( $user_id ) {
                // Extraer course_id de diferentes formatos
                $course_id = 0;
                
                if ( is_array( $course ) ) {
                    if ( isset( $course['course_id'] ) ) {
                        $course_id = (int) $course['course_id'];
                    } elseif ( isset( $course['id'] ) ) {
                        $course_id = (int) $course['id'];
                    } elseif ( isset( $course['ID'] ) ) {
                        $course_id = (int) $course['ID'];
                    } elseif ( isset( $course['post_id'] ) ) {
                        $course_id = (int) $course['post_id'];
                    }
                } elseif ( is_numeric( $course ) ) {
                    $course_id = (int) $course;
                    } elseif ( is_object( $course ) ) {
                        $course_id = (int) ($course->course_id ?? $course->id ?? $course->ID ?? 0);
                    }
                    
                    if ( $course_id <= 0 ) {
                        error_log( "FPLMS: No se pudo extraer course_id del curso: " . print_r($course, true) );
                        return false;
                    }
                    
                    $visible = $this->can_user_see_course( $user_id, $course_id );
                    
                    if ( $course_id === 53818 ) {
                        error_log( "FPLMS: Curso 53818 - can_user_see_course = " . ($visible ? 'TRUE' : 'FALSE') );
                    }
                    
                    return $visible;
                }
            )
        );
        
        $filtered_count = count( $filtered_courses );
        error_log( "FPLMS: Cursos después de filtrar: {$filtered_count}" );
        
        // Actualizar la respuesta con los cursos filtrados
        if ( $courses_key === 'courses' ) {
            $response['courses'] = $filtered_courses;
        } elseif ( $courses_key === 'data.courses' ) {
            $response['data']['courses'] = $filtered_courses;
        } elseif ( $courses_key === 'direct' ) {
            $response = $filtered_courses;
        }
        
        // Recalcular totales
        $per_page = isset( $_POST['per_page'] ) ? (int) $_POST['per_page'] : 10;
        if ( $per_page <= 0 ) $per_page = 10;
        
        $total_pages = max( 1, (int) ceil( $filtered_count / $per_page ) );
        $current_page = isset( $_POST['page'] ) ? (int) $_POST['page'] : 1;
        if ( $current_page < 1 ) $current_page = 1;
        
        // Actualizar totales en la respuesta
        if ( isset( $response['total_posts'] ) ) {
            $response['total_posts'] = $filtered_count;
        }
        if ( isset( $response['total_pages'] ) ) {
            $response['total_pages'] = $total_pages;
        }
        if ( isset( $response['data']['total_posts'] ) ) {
            $response['data']['total_posts'] = $filtered_count;
        }
        if ( isset( $response['data']['total_pages'] ) ) {
            $response['data']['total_pages'] = $total_pages;
        }
        
        // Actualizar paginación si existe
        if ( isset( $response['pagination'] ) && is_array( $response['pagination'] ) ) {
            $response['pagination']['total_pages'] = $total_pages;
            $response['pagination']['total_posts'] = $filtered_count;
            $response['pagination']['per_page'] = $per_page;
            $response['pagination']['current_page'] = $current_page;
        }
        
        error_log( "FPLMS: Resultado final - Total visible: {$filtered_count}, Per page: {$per_page}, Total pages: {$total_pages}, Current page: {$current_page}" );
        
        return $response;
    }

    /**
     * Filtra los argumentos de la consulta de cursos ANTES de que MasterStudy
     * renderice las tarjetas. Esto permite filtrar los IDs de cursos visibles
     * antes de que se genere el HTML.
     *
     * @param array $args Argumentos de WP_Query.
     * @param int   $user_id ID del usuario.
     * @return array Argumentos modificados.
     */
    public function filter_user_courses_query( array $args, int $user_id ): array {
        // Administrador: ver todo sin restricción
        if ( current_user_can( 'manage_options' ) ) {
            return $args;
        }
        
        error_log( "FPLMS: filter_user_courses_query para usuario {$user_id}" );
        
        // Obtener cursos visibles para este usuario
        $visible_courses = $this->get_visible_courses_for_user( $user_id );
        
        error_log( "FPLMS: Cursos visibles para usuario {$user_id}: " . count($visible_courses) );
        
        if ( ! empty( $visible_courses ) ) {
            $args['post__in'] = $visible_courses;
            $args['posts_per_page'] = -1; // Obtener todos los cursos visibles
            $args['nopaging'] = true;
        } else {
            $args['post__in'] = [ 0 ]; // No mostrar ningún curso
        }
        
        return $args;
    }
}