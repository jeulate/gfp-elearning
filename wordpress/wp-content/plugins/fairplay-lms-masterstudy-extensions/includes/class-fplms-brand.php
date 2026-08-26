<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FairPlay_LMS_Brand {

    private string $brand_key = 'boostacademy';

    private array $config = [];

    public function __construct() {
        $brands_file = dirname( __DIR__ ) . '/config/brands.php';

        if ( ! file_exists( $brands_file ) ) {
            return;
        }

        $brands = require $brands_file;

        if ( ! is_array( $brands ) || empty( $brands ) ) {
            return;
        }

        $host = strtolower(
            (string) wp_parse_url(
                home_url(),
                PHP_URL_HOST
            )
        );

        foreach ( $brands as $brand_key => $config ) {
            $domains = $config['domains'] ?? [];

            if ( in_array( $host, $domains, true ) ) {
                $this->brand_key = (string) $brand_key;
                $this->config    = $config;

                return;
            }
        }

        // Fallback seguro para instalaciones existentes.
        $this->config = $brands['boostacademy'] ?? [];
    }

    public function get_key(): string {
        return $this->brand_key;
    }

    public function color( string $name, string $fallback = '' ): string {
        return isset( $this->config['colors'][ $name ] )
            ? (string) $this->config['colors'][ $name ]
            : $fallback;
    }

    public function get_config(): array {
        return $this->config;
    }

    public function is( string $brand ): bool {
        return $this->brand_key === $brand;
    }
}