-- Plantilla. Sustituir valores entre {{ }} antes de ejecutar.
-- No guardar la contraseña ni la IP reales en Git.

CREATE USER IF NOT EXISTS '{{POWERBI_USER}}'@'{{GATEWAY_IP}}'
IDENTIFIED BY '{{STRONG_RANDOM_PASSWORD}}'
REQUIRE SSL;

GRANT SELECT ON `{{WORDPRESS_DATABASE}}`.wp_fplms_bi_structures
TO '{{POWERBI_USER}}'@'{{GATEWAY_IP}}';
GRANT SELECT ON `{{WORDPRESS_DATABASE}}`.wp_fplms_bi_users
TO '{{POWERBI_USER}}'@'{{GATEWAY_IP}}';
GRANT SELECT ON `{{WORDPRESS_DATABASE}}`.wp_fplms_bi_courses
TO '{{POWERBI_USER}}'@'{{GATEWAY_IP}}';
GRANT SELECT ON `{{WORDPRESS_DATABASE}}`.wp_fplms_bi_assignments
TO '{{POWERBI_USER}}'@'{{GATEWAY_IP}}';
GRANT SELECT ON `{{WORDPRESS_DATABASE}}`.wp_fplms_bi_evaluations
TO '{{POWERBI_USER}}'@'{{GATEWAY_IP}}';
GRANT SELECT ON `{{WORDPRESS_DATABASE}}`.wp_fplms_bi_satisfaction
TO '{{POWERBI_USER}}'@'{{GATEWAY_IP}}';
GRANT SELECT ON `{{WORDPRESS_DATABASE}}`.wp_fplms_bi_activity
TO '{{POWERBI_USER}}'@'{{GATEWAY_IP}}';
GRANT SELECT ON `{{WORDPRESS_DATABASE}}`.wp_fplms_bi_certificates
TO '{{POWERBI_USER}}'@'{{GATEWAY_IP}}';

SHOW GRANTS FOR '{{POWERBI_USER}}'@'{{GATEWAY_IP}}';
