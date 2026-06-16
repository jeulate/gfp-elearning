# Documentacion de ajuste: unificacion de estructura visual de cursos hidratados

## Contexto
Se detecto que algunos cursos (IDs 53854, 53841 y 53818) se renderizaban con una estructura HTML diferente al resto (sin bloque `masterstudy-course-card__wrapper` y con layout compacto), mientras que otros como el ID 53894 usaban la estructura completa de MasterStudy.

La causa principal era el orden de fusion durante la hidratacion inicial: primero se conservaba el DOM existente y solo despues se agregaban faltantes desde AJAX. Si un curso ya existia en DOM con estructura legacy, nunca era reemplazado por su version nativa completa.

## Ajustes aplicados
Archivo modificado:
- `wordpress/wp-content/plugins/fairplay-lms-masterstudy-extensions/includes/class-fplms-plugin.php`

Bloque funcional:
- `inject_student_dashboard_script()`
- sub-bloque de hidratacion inicial dentro de `renderStudent()`

Cambios especificos:
1. Se agrego ` _hasCompleteCardStructure(node)` para detectar si una tarjeta tiene estructura completa de MasterStudy.
   - Considera completa cuando existe `.masterstudy-course-card__wrapper`.
   - Fallback de estructura completa cuando existen `.masterstudy-course-card__info-title`, `.masterstudy-course-card__meta` y `.masterstudy-course-card__bottom`.

2. Se agrego `_mergeNodeByKey(map, orderedKeys, key, node)` para fusionar por clave de curso (`id:*` o `url:*`) con criterio de calidad estructural.
   - Si el curso no existe en mapa, lo agrega.
   - Si ya existe, reemplaza solo cuando el nuevo nodo tiene estructura mas completa que el existente.

3. Se cambio el orden de fusion del hidratador:
   - Primero se cargan `incomingNodes` desde `data.data.courses` (respuesta AJAX con `per_page=500`).
   - Luego se procesa `list.children` como fallback para conservar estado solo si no aporta una estructura inferior.

4. Se elimino helper no usado (`_collectExistingKeys`) luego del refactor.

## Resultado esperado
- Cursos como 53854, 53841 y 53818 deben adoptar el mismo esquema visual que 53894 cuando exista version completa en la respuesta AJAX.
- Se mantiene el objetivo de no usar plantillas custom; se preserva HTML nativo de MasterStudy.
- Se mantiene la llamada AJAX con `per_page=500`.
- Se mantiene compatibilidad con filtros y visibilidad existentes (todos, completado, en progreso, proximo, por vencer), al no reescribir markup manual.

## Verificacion sugerida
1. Abrir `/user-account/` con el usuario que tiene 9 cursos.
2. Confirmar que 53854, 53841 y 53818 presentan wrapper y bloques de meta/bottom como cursos de referencia.
3. Probar tabs de estado y confirmar que no se pierde funcionalidad de filtrado.
4. Revisar que la paginacion se oculte/muestre segun `syncStudentPaginationVisibility()`.
