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
     * Caché local de cursos publish matriculados por usuario.
     *
     * @var array<int, array<int>>
     */
    private static $enrolled_publish_cache = [];

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
    if ( ! $this->can_user_view_course_status( $user_id, $course_id ) ) {
        return false;
    }

    // Si no se pasan estructuras, obtenerlas
    if ( empty( $user_structures ) ) {
        $user_structures = $this->get_user_structures( $user_id );
    }

    // Si el usuario no tiene estructura asignada, puede ver todos los cursos
    if ( empty( $user_structures ) ) {
        return true;
    }

    // Obtener estructuras asignadas al curso
    $course_structures = $this->get_course_structures( $course_id );

    // Si el curso no tiene restricciones de estructura, es visible para todos
    if ( $this->course_has_no_restrictions( $course_structures ) ) {
        return true;
    }

    return $this->structures_match( $user_structures, $course_structures );
}

    /**
     * Devuelve IDs de cursos publish en los que el usuario está matriculado.
     *
     * @param int $user_id ID de usuario.
     * @return array<int>
     */
    private function get_user_enrolled_published_course_ids( int $user_id ): array {
        if ( $user_id <= 0 ) {
            return [];
        }

        if ( isset( self::$enrolled_publish_cache[ $user_id ] ) ) {
            return self::$enrolled_publish_cache[ $user_id ];
        }

        global $wpdb;

        $ms_table = $wpdb->prefix . 'stm_lms_user_courses';
        $ids = array_map( 'intval', (array) $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT uc.course_id
                 FROM {$ms_table} uc
                 INNER JOIN {$wpdb->posts} p
                    ON p.ID = uc.course_id
                   AND p.post_type = %s
                   AND p.post_status = 'publish'
                 WHERE uc.user_id = %d",
                FairPlay_LMS_Config::MS_PT_COURSE,
                $user_id
            )
        ) );

        self::$enrolled_publish_cache[ $user_id ] = $ids;
        return $ids;
    }

    /**
     * Normaliza meta de estructura a un ID entero (> 0) en formato tolerante.
     * Acepta scalar, arrays serializados y objetos con claves comunes.
     *
     * @param mixed $raw Valor raw desde user_meta.
     * @return int
     */
    private function normalize_structure_meta_id( $raw ): int {
        if ( is_numeric( $raw ) ) {
            $n = (int) $raw;
            return $n > 0 ? $n : 0;
        }

        if ( is_string( $raw ) ) {
            $maybe = maybe_unserialize( $raw );
            if ( $maybe !== $raw ) {
                return $this->normalize_structure_meta_id( $maybe );
            }
            return 0;
        }

        if ( is_object( $raw ) ) {
            $raw = (array) $raw;
        }

        if ( is_array( $raw ) ) {
            foreach ( [ 'term_id', 'id', 'value' ] as $k ) {
                if ( isset( $raw[ $k ] ) ) {
                    $n = $this->normalize_structure_meta_id( $raw[ $k ] );
                    if ( $n > 0 ) {
                        return $n;
                    }
                }
            }

            foreach ( $raw as $item ) {
                $n = $this->normalize_structure_meta_id( $item );
                if ( $n > 0 ) {
                    return $n;
                }
            }
        }

        return 0;
    }

    /**
     * Lee un ID de estructura desde múltiples meta keys (compatibilidad).
     *
     * @param int   $user_id ID de usuario.
     * @param array $meta_keys Lista de meta keys candidatas.
     * @return int
     */
    private function read_user_structure_id( int $user_id, array $meta_keys ): int {
        foreach ( $meta_keys as $meta_key ) {
            $raw = get_user_meta( $user_id, (string) $meta_key, true );
            $id  = $this->normalize_structure_meta_id( $raw );
            if ( $id > 0 ) {
                return $id;
            }
        }
        return 0;
    }

    /**
     * Obtiene las estructuras asignadas a un usuario.
     * Las meta keys son las que realmente existen en la base de datos.
     *
     * @param int $user_id ID del usuario.
     * @return array Array con estructura: ['channel' => id, 'branch' => id, 'role' => id, 'city' => id, 'company' => id].
     */
    private function get_user_structures( int $user_id ): array {

        // Compatibilidad: priorizar constantes y aceptar llaves legacy.
        $structures = [
            'city'    => $this->read_user_structure_id( $user_id, [ FairPlay_LMS_Config::USER_META_CITY, 'fplms_cities' ] ),
            'company' => $this->read_user_structure_id( $user_id, [ FairPlay_LMS_Config::USER_META_COMPANY, 'fplms_companies' ] ),
            'channel' => $this->read_user_structure_id( $user_id, [ FairPlay_LMS_Config::USER_META_CHANNEL, 'fplms_channels' ] ),
            'branch'  => $this->read_user_structure_id( $user_id, [ FairPlay_LMS_Config::USER_META_BRANCH, 'fplms_branches' ] ),
            'role'    => $this->read_user_structure_id( $user_id, [ FairPlay_LMS_Config::USER_META_ROLE, 'fplms_role', 'fplms_roles' ] ),
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
        if ( current_user_can( 'manage_options' ) ) {
            return $response;
        }

        $user_id = get_current_user_id();
        if ( $user_id <= 0 ) {
            return $response;
        }

        /*
         * MasterStudy entrega posts (datos) y courses (HTML) en el mismo orden.
         * posts es la fuente de identidad; los mismos índices se aplican a ambos
         * arreglos para evitar que una tarjeta quede asociada a otro curso.
         */
        if ( isset( $response['posts'] ) && is_array( $response['posts'] ) ) {
            $visible_indexes = [];

            foreach ( $response['posts'] as $index => $post ) {
                $course_id = $this->extract_course_id_from_response_item( $post );
                if ( $course_id > 0 && $this->can_user_see_course( $user_id, $course_id ) ) {
                    $visible_indexes[] = $index;
                }
            }

            $visible_posts = [];
            $visible_cards = [];

            foreach ( $visible_indexes as $index ) {
                $visible_posts[] = $response['posts'][ $index ];
                if ( isset( $response['courses'][ $index ] ) ) {
                    $visible_cards[] = $response['courses'][ $index ];
                }
            }

            $per_page     = max( 1, (int) apply_filters( 'fplms_enrolled_courses_per_page', 6 ) );
            $current_page = isset( $_REQUEST['fplms_requested_page'] )
                ? absint( wp_unslash( $_REQUEST['fplms_requested_page'] ) )
                : ( isset( $response['current_page'] ) ? absint( $response['current_page'] ) : 1 );
            $current_page = max( 1, $current_page );
            $total_posts  = count( $visible_posts );
            $total_pages  = $total_posts > 0 ? (int) ceil( $total_posts / $per_page ) : 0;

            if ( $total_pages > 0 ) {
                $current_page = min( $current_page, $total_pages );
            } else {
                $current_page = 1;
            }

            $offset              = ( $current_page - 1 ) * $per_page;
            $response['posts']   = array_slice( $visible_posts, $offset, $per_page );
            $response['courses'] = array_slice( $visible_cards, $offset, $per_page );
            $response['total_posts']  = $total_posts;
            $response['total_pages']  = $total_pages;
            $response['pages']        = $total_pages;
            $response['current_page'] = $current_page;
            $response['total']        = $total_posts;
            $response['pagination']   = '';

            return $response;
        }

        // Compatibilidad con respuestas antiguas que solo contienen datos.
        $courses_key = null;
        $courses     = [];

        if ( isset( $response['data']['courses'] ) && is_array( $response['data']['courses'] ) ) {
            $courses_key = 'data.courses';
            $courses     = $response['data']['courses'];
        } elseif ( isset( $response['courses'] ) && is_array( $response['courses'] ) ) {
            $first = reset( $response['courses'] );
            if ( $this->extract_course_id_from_response_item( $first ) > 0 ) {
                $courses_key = 'courses';
                $courses     = $response['courses'];
            }
        }

        if ( null === $courses_key ) {
            return $response;
        }

        $filtered_courses = array_values(
            array_filter(
                $courses,
                function ( $course ) use ( $user_id ): bool {
                    $course_id = $this->extract_course_id_from_response_item( $course );
                    return $course_id > 0 && $this->can_user_see_course( $user_id, $course_id );
                }
            )
        );

        if ( 'data.courses' === $courses_key ) {
            $response['data']['courses'] = $filtered_courses;
        } else {
            $response['courses'] = $filtered_courses;
        }

        return $response;
    }

    /**
     * Extrae el ID de curso de los formatos conocidos de MasterStudy.
     *
     * @param mixed $item Elemento de respuesta.
     * @return int
     */
    private function extract_course_id_from_response_item( $item ): int {
        if ( is_numeric( $item ) ) {
            return (int) $item;
        }

        if ( is_array( $item ) ) {
            foreach ( [ 'course_id', 'id', 'ID', 'post_id' ] as $key ) {
                if ( isset( $item[ $key ] ) && is_numeric( $item[ $key ] ) ) {
                    return (int) $item[ $key ];
                }
            }
        }

        if ( is_object( $item ) ) {
            foreach ( [ 'course_id', 'id', 'ID', 'post_id' ] as $key ) {
                if ( isset( $item->{$key} ) && is_numeric( $item->{$key} ) ) {
                    return (int) $item->{$key};
                }
            }
        }

        return 0;
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
        $user_id = $user_id > 0 ? $user_id : get_current_user_id();
        if ( $user_id <= 0 ) {
            return $args;
        }

        // Administrador: ver todo sin restricción
        if ( current_user_can( 'manage_options' ) ) {
            return $args;
        }
        
        // Obtener cursos visibles para este usuario
        $visible_courses = $this->get_visible_courses_for_user( $user_id );
        
        if ( ! empty( $visible_courses ) ) {
            $args['post__in'] = $visible_courses;
            $args['orderby'] = 'post__in';
            $args['posts_per_page'] = -1; // Obtener todos los cursos visibles
            $args['nopaging'] = true;
            $args['per_page'] = 500;
            $args['limit'] = 500;
            $args['page'] = 1;
            $args['paged'] = 1;
            $args['offset'] = 0;
        } else {
            $args['post__in'] = [ 0 ]; // No mostrar ningún curso
        }
        
        return $args;
    }

    /**
     * Devuelve únicamente los cursos publish en los que el usuario está
     * matriculado y que además son compatibles con su estructura actual.
     *
     * La matrícula histórica permanece intacta en MasterStudy. Este método
     * controla exclusivamente la visibilidad.
     *
     * @param int $user_id ID de usuario.
     * @return array<int>
     */
    public function get_visible_enrolled_course_ids_for_user( int $user_id ): array {
        if ( $user_id <= 0 ) {
            return [];
        }

        $user_structures = $this->get_user_structures( $user_id );
        $enrolled_ids    = $this->get_user_enrolled_published_course_ids( $user_id );

        return array_values(
            array_filter(
                $enrolled_ids,
                function ( int $course_id ) use ( $user_id, $user_structures ): bool {
                    return $this->can_user_see_course( $user_id, $course_id, $user_structures );
                }
            )
        );
    }
}
