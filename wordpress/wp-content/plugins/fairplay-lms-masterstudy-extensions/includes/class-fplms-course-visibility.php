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

        // Verificar si hay coincidencia entre estructuras del usuario y del curso
        return $this->structures_match( $user_structures, $course_structures );
    }

    /**
     * Obtiene las estructuras asignadas a un usuario.
     *
     * @param int $user_id ID del usuario.
     * @return array Array con estructura: ['city' => id, 'company' => id, 'channel' => id, 'branch' => id, 'role' => id].
     */
    private function get_user_structures( int $user_id ): array {

        $structures = [
            'city'    => (int) get_user_meta( $user_id, FairPlay_LMS_Config::USER_META_CITY, true ),
            'company' => (int) get_user_meta( $user_id, FairPlay_LMS_Config::USER_META_COMPANY, true ),
            'channel' => (int) get_user_meta( $user_id, FairPlay_LMS_Config::USER_META_CHANNEL, true ),
            'branch'  => (int) get_user_meta( $user_id, FairPlay_LMS_Config::USER_META_BRANCH, true ),
            'role'    => (int) get_user_meta( $user_id, FairPlay_LMS_Config::USER_META_ROLE, true ),
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

        // array_filter elimina strings vacíos / ceros que WordPress devuelve cuando
        // el meta nunca fue guardado: get_post_meta() → '' → (array)'' = [''] ≠ []
        // Sin este filtro, [''] se interpreta como restricción real y bloquea todo.
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

        // Mapeo de claves: usuario vs curso
        $mapping = [
            'city'    => 'cities',
            'company' => 'companies',
            'channel' => 'channels',
            'branch'  => 'branches',
            'role'    => 'roles',
        ];

        foreach ( $mapping as $user_key => $course_key ) {

            // Si el curso no tiene restricción en este nivel, no filtra → continuar
            if ( empty( $course_structures[ $course_key ] ) ) {
                continue;
            }

            // El curso restringe este nivel → el usuario DEBE tener valor y coincidir
            if ( ! isset( $user_structures[ $user_key ] ) ) {
                return false; // Usuario no tiene asignado un nivel que el curso requiere
            }

            // Normalizar a int y verificar coincidencia
            $user_val    = (int) $user_structures[ $user_key ];
            $course_vals = array_map( 'intval', (array) $course_structures[ $course_key ] );

            if ( ! in_array( $user_val, $course_vals, true ) ) {
                return false; // No coincide en este nivel
            }
        }

        // Todos los niveles asignados al curso coinciden con el usuario (AND entre niveles)
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
     * Útil para aplicar como filtro WordPress a queries de cursos.
     *
     * @param array $course_ids Array de IDs de cursos.
     * @param int   $user_id ID del usuario (actual si no se especifica).
     * @return array Array filtrado de IDs de cursos.
     */
    public function filter_courses_array( array $course_ids, $raw_user_id = 0 ): array {

        // MasterStudy puede pasar el user_id como segundo argumento o no pasarlo.
        // Normalizamos a int y fallback a current user.
        $user_id = (int) $raw_user_id;
        if ( 0 === $user_id ) {
            $user_id = get_current_user_id();
        }
        if ( 0 === $user_id ) {
            return $course_ids; // Sin usuario identificado, no filtrar
        }

        // Admin puede ver todos los cursos
        if ( current_user_can( 'manage_options' ) ) {
            return $course_ids;
        }

        // MasterStudy puede pasar el array en tres formatos distintos:
        //   A) Indexado:              [ 0 => 877, 1 => 882 ]
        //      → el valor ES el course_id (numérico)
        //   B) Asociativo numérico:   [ 877 => ['progress'=>0,...], 882 => [...] ]
        //      → la clave es el course_id como int/string numérico
        //   C) Asociativo con clave:  [ 'stm_lms_course_877' => ['progress'=>0,...] ]
        //      → la clave es un string tipo 'stm_lms_course_877'; extraemos el número
        return array_filter(
            $course_ids,
            function( $value, $key ) use ( $user_id ) {
                if ( is_array( $value ) || is_object( $value ) ) {
                    // Formato B o C: la clave identifica el curso
                    if ( is_numeric( $key ) ) {
                        $course_id = (int) $key; // Formato B
                    } else {
                        // Formato C: extraer el número del final del string (ej: 'stm_lms_course_877')
                        preg_match( '/(\d+)$/', (string) $key, $m );
                        $course_id = ! empty( $m[1] ) ? (int) $m[1] : 0;
                    }
                } else {
                    $course_id = (int) $value; // Formato A
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
     * Filtra la respuesta AJAX de cursos matriculados (filtro 'stm_lms_get_user_courses_filter').
     *
     * MasterStudy pasa $r = ['courses' => [...], 'total_posts' => N, ...] al AJAX handler.
     * Filtramos $r['courses'] para mostrar solo los que el usuario puede ver.
     *
     * @param array $response Respuesta completa con claves: courses, pagination, total_pages, total_posts.
     * @return array Respuesta con cursos filtrados según visibilidad de estructura.
     */
    public function filter_user_courses_response( array $response ): array {
        $user_id = get_current_user_id();
        if ( 0 === $user_id ) {
            return $response;
        }
        if ( current_user_can( 'manage_options' ) ) {
            return $response;
        }
        if ( empty( $response['courses'] ) || ! is_array( $response['courses'] ) ) {
            return $response;
        }

        $response['courses'] = array_values(
            array_filter(
                $response['courses'],
                function( $course ) use ( $user_id ) {
                    $course_id = ! empty( $course['course_id'] ) ? (int) $course['course_id'] : 0;
                    if ( $course_id <= 0 ) {
                        return false;
                    }
                    return $this->can_user_see_course( $user_id, $course_id );
                }
            )
        );

        return $response;
    }
}
