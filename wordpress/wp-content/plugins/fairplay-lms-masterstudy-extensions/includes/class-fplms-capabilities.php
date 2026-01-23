<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FairPlay_LMS_Capabilities {

    /**
     * Activación: crear roles, capabilities y matriz por defecto.
     */
    public static function activate() {

        // Subscriber (rol nativo WP/MasterStudy para estudiantes): agregar capabilities del plugin
        $subscriber = get_role( 'subscriber' );
        if ( $subscriber ) {
            $subscriber->add_cap( FairPlay_LMS_Config::CAP_VIEW_PROGRESS );
            $subscriber->add_cap( FairPlay_LMS_Config::CAP_VIEW_CALENDAR );
        }

        // Administrador WP: asegurar capabilities del plugin
        $admin = get_role( 'administrator' );
        if ( $admin ) {
            foreach ( FairPlay_LMS_Config::get_plugin_caps() as $cap ) {
                $admin->add_cap( $cap );
            }
        }

        // Instructor MasterStudy (Docente): capacidades del plugin
        $ms_instructor_role = get_role( FairPlay_LMS_Config::MS_ROLE_INSTRUCTOR );
        if ( $ms_instructor_role ) {
            $ms_instructor_role->add_cap( FairPlay_LMS_Config::CAP_MANAGE_COURSES );
            $ms_instructor_role->add_cap( FairPlay_LMS_Config::CAP_VIEW_REPORTS );
            $ms_instructor_role->add_cap( FairPlay_LMS_Config::CAP_VIEW_PROGRESS );
            $ms_instructor_role->add_cap( FairPlay_LMS_Config::CAP_VIEW_CALENDAR );
        }

        // Matriz por defecto, si no existe
        $matrix = get_option( FairPlay_LMS_Config::OPTION_CAP_MATRIX );
        if ( ! is_array( $matrix ) ) {
            $matrix = self::get_default_capability_matrix();
            update_option( FairPlay_LMS_Config::OPTION_CAP_MATRIX, $matrix );
        }

        // Sincronizar matriz con roles reales
        self::sync_capabilities_to_roles( $matrix );
    }

    /**
     * Desactivación: no removemos roles/caps para no romper asignaciones.
     */
    public static function deactivate() {
        // Intencionalmente vacío.
    }

    /**
     * Matriz de privilegios por defecto.
     * Roles simplificados: subscriber (Estudiante), stm_lms_instructor (Docente), administrator
     */
    public static function get_default_capability_matrix(): array {
        return [
            // Subscriber (Estudiante) - Rol nativo de WordPress usado por MasterStudy
            'subscriber' => [
                FairPlay_LMS_Config::CAP_MANAGE_STRUCTURES => false,
                FairPlay_LMS_Config::CAP_MANAGE_USERS      => false,
                FairPlay_LMS_Config::CAP_MANAGE_COURSES    => false,
                FairPlay_LMS_Config::CAP_VIEW_REPORTS      => false,
                FairPlay_LMS_Config::CAP_VIEW_PROGRESS     => true,
                FairPlay_LMS_Config::CAP_VIEW_CALENDAR     => true,
            ],
            // Instructor MasterStudy (Docente)
            FairPlay_LMS_Config::MS_ROLE_INSTRUCTOR => [
                FairPlay_LMS_Config::CAP_MANAGE_STRUCTURES => false,
                FairPlay_LMS_Config::CAP_MANAGE_USERS      => false,
                FairPlay_LMS_Config::CAP_MANAGE_COURSES    => true,
                FairPlay_LMS_Config::CAP_VIEW_REPORTS      => true,
                FairPlay_LMS_Config::CAP_VIEW_PROGRESS     => true,
                FairPlay_LMS_Config::CAP_VIEW_CALENDAR     => true,
            ],
            // Administrador
            'administrator' => [
                FairPlay_LMS_Config::CAP_MANAGE_STRUCTURES => true,
                FairPlay_LMS_Config::CAP_MANAGE_USERS      => true,
                FairPlay_LMS_Config::CAP_MANAGE_COURSES    => true,
                FairPlay_LMS_Config::CAP_VIEW_REPORTS      => true,
                FairPlay_LMS_Config::CAP_VIEW_PROGRESS     => true,
                FairPlay_LMS_Config::CAP_VIEW_CALENDAR     => true,
            ],
        ];
    }

    /**
     * Devuelve la matriz almacenada o la de defecto.
     */
    public static function get_matrix(): array {
        $matrix = get_option( FairPlay_LMS_Config::OPTION_CAP_MATRIX );
        if ( ! is_array( $matrix ) ) {
            $matrix = self::get_default_capability_matrix();
        }
        return $matrix;
    }

    /**
     * Guarda la matriz y sincroniza con roles.
     */
    public static function save_matrix( array $matrix ): void {
        update_option( FairPlay_LMS_Config::OPTION_CAP_MATRIX, $matrix );
        self::sync_capabilities_to_roles( $matrix );
    }

    /**
     * Sincroniza las capabilities del plugin con los roles reales de WP.
     */
    public static function sync_capabilities_to_roles( array $matrix ): void {

        $plugin_caps = FairPlay_LMS_Config::get_plugin_caps();

        foreach ( $matrix as $role_key => $caps_map ) {
            $role_obj = get_role( $role_key );
            if ( ! $role_obj instanceof WP_Role ) {
                continue;
            }

            // Primero quitamos todas las caps del plugin
            foreach ( $plugin_caps as $cap ) {
                $role_obj->remove_cap( $cap );
            }

            // Luego aplicamos las marcadas como true
            foreach ( $caps_map as $cap => $enabled ) {
                if ( $enabled && in_array( $cap, $plugin_caps, true ) ) {
                    $role_obj->add_cap( $cap );
                }
            }
        }
    }
}
