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
     * @return array Array con estructura: ['city' => id, 'channel' => id, 'branch' => id, 'role' => id].
     */
    private function get_user_structures( int $user_id ): array {

        $structures = [
            'city'    => (int) get_user_meta( $user_id, FairPlay_LMS_Config::USER_META_CITY, true ),
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
     * @return array Array con estructura: ['cities' => [ids], 'channels' => [ids], 'branches' => [ids], 'roles' => [ids]].
     */
    private function get_course_structures( int $course_id ): array {

        return [
            'cities'   => (array) get_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_CITIES, true ),
            'channels' => (array) get_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_CHANNELS, true ),
            'branches' => (array) get_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_BRANCHES, true ),
            'roles'    => (array) get_post_meta( $course_id, FairPlay_LMS_Config::META_COURSE_ROLES, true ),
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

        foreach ( [ 'cities', 'channels', 'branches', 'roles' ] as $key ) {
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
            'channel' => 'channels',
            'branch'  => 'branches',
            'role'    => 'roles',
        ];

        foreach ( $mapping as $user_key => $course_key ) {

            // Si el usuario tiene esta estructura asignada
            if ( isset( $user_structures[ $user_key ] ) && ! empty( $course_structures[ $course_key ] ) ) {

                // Verificar si la estructura del usuario está en la lista del curso
                if ( in_array( $user_structures[ $user_key ], $course_structures[ $course_key ], true ) ) {
                    return true;
                }
            }
        }

        return false;
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
    public function filter_courses_array( array $course_ids, int $user_id = 0 ): array {

        if ( 0 === $user_id ) {
            $user_id = get_current_user_id();
        }

        // Admin puede ver todos los cursos
        if ( current_user_can( 'manage_options' ) ) {
            return $course_ids;
        }

        return array_filter(
            $course_ids,
            function( $course_id ) use ( $user_id ) {
                return $this->can_user_see_course( $user_id, $course_id );
            }
        );
    }
}
