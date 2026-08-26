# Capa de reportería Power BI

Vistas SQL de solo lectura para los dashboards e-learning de BoostAcademy y MatchUp.

## Reglas de negocio

- Curso finalizado: `status` es `completed` o `passed`, o `progress_percent >= 100`.
- Promedio general: primero se promedia `final_grade` por estudiante en cursos finalizados; después se promedian los estudiantes.
- Certificado emitido: existe `stm_lms_certificate_code_{course_id}` con valor no vacío.
- Satisfacción: escala de 1 a 5 convertida a porcentaje.
- Tiempo: los eventos consecutivos del usuario se suman solo cuando la separación es de hasta 15 minutos.
- Curso histórico: curso referenciado por hechos que ya no existe en `wp_posts`.

## Requisitos

- Prefijo de tablas WordPress `wp_`.
- MySQL 8.0+ o MariaDB 10.2+ por el uso de `LAG()` en la vista de actividad.
- Permiso `CREATE VIEW` para instalar o actualizar las vistas.
- Copia de seguridad reciente antes del despliegue.

## Archivos

- `001-create-reporting-views.sql`: crea o actualiza las vistas.
- `002-drop-reporting-views.sql`: elimina únicamente las vistas BI.
- `003-validation-queries.sql`: valida conteos e indicadores.
- `004-readonly-user.template.sql`: plantilla para el usuario del gateway.

## Despliegue

Ejecutar primero en BoostAcademy:

```bash
cd /var/www/wordpress
sudo -u www-data wp db query < \
  wp-content/plugins/fairplay-lms-masterstudy-extensions/database/powerbi/001-create-reporting-views.sql

sudo -u www-data wp db query < \
  wp-content/plugins/fairplay-lms-masterstudy-extensions/database/powerbi/003-validation-queries.sql
```

Después de validar BoostAcademy, aplicar en MatchUp con el cliente MariaDB/MySQL disponible en el servidor. No utilizar el archivo de usuario de solo lectura hasta conocer la IP fija del gateway.

## Seguridad

- Power BI no debe usar el usuario de WordPress ni `root`.
- Restringir el usuario BI a la IP fija del gateway.
- Exigir TLS.
- Conceder `SELECT` solo sobre estas vistas.
- No abrir el puerto 3306 a `0.0.0.0/0`.
- No versionar contraseñas, IP privadas ni credenciales.

## Power BI

Se recomienda modo Importación. Las dimensiones son usuarios, cursos y estructuras; los hechos son asignaciones, evaluaciones, satisfacción, actividad y certificados. La actualización programada se configura después de publicar el PBIX en Power BI Service mediante un gateway estándar.
