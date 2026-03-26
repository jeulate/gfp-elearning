# Sistema de Ponderación de Preguntas — FairPlay LMS

## ¿Qué es y para qué sirve?

El sistema de ponderación permite que las preguntas de un quiz **no valgan todas lo mismo**. En lugar de que cada pregunta cuente por igual, el administrador puede darle a cada una un peso diferente, de forma que la nota final siempre sume **100 puntos** en total.

**Ejemplo real:**

> Un quiz de cumplimiento normativo tiene 5 preguntas. La pregunta sobre protección de datos es más importante que las demás, así que se le da más peso:
>
> | Pregunta | Peso |
> |---|---|
> | ¿Qué dice el Reglamento GDPR? | 35 |
> | ¿Quién es el responsable del tratamiento? | 25 |
> | ¿Cuándo aplica la base legal? | 20 |
> | ¿Qué es un DPO? | 10 |
> | ¿Qué es una brecha de seguridad? | 10 |
> | **Total** | **100** |

Si el estudiante acierta las 3 primeras preguntas obtiene **80 puntos** (35 + 25 + 20), no 60.

---

## Las dos reglas del sistema

### Regla 1 — Ponderación manual

El administrador asigna manualmente un peso numérico a cada pregunta. La suma de todos los pesos debe ser exactamente **100**. El sistema valida esto con un contador en tiempo real antes de guardar.

### Regla 2 — Ponderación automática

Si no se configura nada, el sistema reparte los 100 puntos de forma equitativa entre todas las preguntas del quiz.

| Preguntas | Peso por pregunta | Nota |
|---|---|---|
| 5 | 20.00 cada una | División exacta |
| 4 | 25.00 cada una | División exacta |
| 3 | 33.33 / 33.33 / **33.34** | El residuo se añade a la última |
| 7 | 14.28 × 6 + **14.32** | El residuo se añade a la última |

> El sistema usa aritmética de punto fijo a 2 decimales para evitar valores como `33.333333…`. El residuo siempre va a la última pregunta para que la suma sea **exactamente 100**.

---

## Arquitectura

### Archivos implicados

| Archivo | Rol |
|---|---|
| `includes/class-fplms-quiz-weights.php` | Clase principal — toda la lógica de ponderación |
| `includes/class-fplms-quiz-settings.php` | Página de ajustes — card global + tabla resumen |
| `includes/class-fplms-plugin.php` | Registro de hooks en el plugin |
| `fairplay-lms-masterstudy-extensions.php` | `require_once` de la clase |

### Almacenamiento en base de datos

| Clave | Tipo | Descripción |
|---|---|---|
| `_fplms_question_weights` | Post meta (`stm-quizzes`) | JSON `{ "question_id": peso, ... }` |
| `_fplms_quiz_weight_mode` | Post meta (`stm-quizzes`) | `''` (hereda global) · `'auto'` · `'manual'` |
| `fplms_quiz_weight_default` | WordPress option | Modo global por defecto (`'auto'` o `'manual'`) |

---

## Dónde se configura

### Nivel global — Ajustes de Tests

**Ruta:** Panel Admin → FairPlay LMS → Ajustes de Tests → tarjeta *Ponderación de Preguntas*

Aquí se establece el **comportamiento por defecto** que heredan todos los quizzes que no tengan configuración individual:

- **Automática** *(predeterminado)*: todos los quizzes reparten los puntos en partes iguales sin que el administrador tenga que configurar nada.
- **Manual**: todos los quizzes esperarán que el administrador defina pesos en el editor de cada quiz.

Además, en esta misma tarjeta hay una **tabla resumen** de todos los quizzes con:
- Modo efectivo (heredado / manual / automática)
- Número de preguntas
- Estado de la ponderación (si está bien configurado o no)
- Enlace directo al editor del quiz

### Nivel individual — Editor del quiz

**Ruta:** Panel Admin → Tests (`stm-quizzes`) → editar un quiz → metabox *Ponderación de Preguntas*

Cada quiz puede sobrescribir el modo global con tres opciones:

| Opción | Comportamiento |
|---|---|
| **Heredar configuración global** | Usa el modo definido en Ajustes de Tests |
| **Forzar automática** | Siempre reparte equitativamente, independientemente del global |
| **Ponderación manual** | Muestra la tabla de preguntas para asignar pesos |

Cuando se selecciona **Ponderación manual**:

1. Aparece una tabla con todas las preguntas del quiz.
2. Cada fila tiene un campo numérico para introducir el peso.
3. Un contador live muestra el total acumulado en tiempo real:
   - Verde con ✓ cuando el total es exactamente 100.
   - Rojo con aviso cuando supera 100.
   - Ámbar con aviso cuando falta llegar a 100.
4. El botón **"Distribuir equitativamente"** rellena automáticamente todos los campos con la distribución automática como punto de partida.

---

## Flujo completo de decisión

```
¿Tiene el quiz configurado _fplms_quiz_weight_mode?
│
├─ '' (vacío)  →  ¿Cuál es fplms_quiz_weight_default?
│                  ├─ 'auto'   →  Distribución equitativa
│                  └─ 'manual' →  ¿Hay pesos guardados en _fplms_question_weights?
│                                  ├─ Sí  →  Usa los pesos del admin
│                                  └─ No  →  Fallback: distribución equitativa
│
├─ 'auto'      →  Distribución equitativa (siempre)
│
└─ 'manual'    →  ¿Hay pesos guardados en _fplms_question_weights?
                   ├─ Sí  →  Usa los pesos del admin
                   └─ No  →  Fallback: distribución equitativa
```

> **Comportamiento seguro:** el sistema nunca fuerza un 0 a nadie. Si el modo es manual pero no hay pesos guardados, se usa distribución equitativa como fallback.

---

## Cálculo de la nota

La nota ponderada se calcula así:

$$\text{Nota} = \frac{\sum_{\text{preguntas correctas}} \text{peso}_i}{\sum_{\text{todas las preguntas}} \text{peso}_i} \times 100$$

**Ejemplo con ponderación manual:**

| Pregunta | Peso | ¿Correcta? |
|---|---|---|
| P1 | 35 | ✓ |
| P2 | 25 | ✗ |
| P3 | 20 | ✓ |
| P4 | 10 | ✓ |
| P5 | 10 | ✗ |

$$\text{Nota} = \frac{35 + 20 + 10}{100} \times 100 = 65$$

---

## Integración con MasterStudy

### Cómo funciona el filtro REST

El sistema usa el filtro `rest_post_dispatch` (prioridad 20) para interceptar las respuestas de MasterStudy cuando un estudiante envía un quiz. Si el quiz tiene modo manual activo, recalcula el score aplicando los pesos antes de que la respuesta llegue al frontend.

El filtro busca los resultados por pregunta en la respuesta de MasterStudy bajo las claves `questions` o `results`, y dentro de cada pregunta busca la clave `correct`, `status`, `verdict` o `is_correct` para determinar si la respuesta fue correcta.

**Comportamiento si MasterStudy cambia su estructura:** si el filtro no reconoce el formato de la respuesta, la pasa intacta sin modificar nada — no rompe el quiz.

### Helper estático para integración directa

Si en el futuro MasterStudy expone un hook interno con los IDs de preguntas correctas, se puede usar directamente:

```php
$score = FairPlay_LMS_Quiz_Weights::calculate_score( $quiz_id, $correct_question_ids );
```

Esto devuelve un `float` entre 0 y 100 con la nota ponderada.

### Otros helpers disponibles

```php
// Obtener el modo efectivo de un quiz ('auto' o 'manual')
$mode = FairPlay_LMS_Quiz_Weights::get_mode( $quiz_id );

// Obtener el modo global por defecto
$default = FairPlay_LMS_Quiz_Weights::get_default_mode();

// Obtener los IDs de preguntas de un quiz desde el post_meta de MasterStudy
$question_ids = FairPlay_LMS_Quiz_Weights::get_question_ids( $quiz_id );

// Obtener el mapa de pesos efectivos { question_id => peso }
$weights = FairPlay_LMS_Quiz_Weights::get_effective_weights( $quiz_id );

// Calcular distribución equitativa para un array de IDs de preguntas
$auto_weights = FairPlay_LMS_Quiz_Weights::compute_auto_weights( $question_ids );
```

---

## Guía de configuración rápida

### Escenario A: todos los quizzes con nota equitativa (sin configurar nada)

No hace falta tocar nada. El modo global por defecto es **Automática**.

### Escenario B: un quiz específico con preguntas de distinto valor

1. Ir a **Tests → editar el quiz**.
2. En el metabox *Ponderación de Preguntas*, seleccionar **Ponderación manual**.
3. Introducir el peso de cada pregunta (los demás quizzes siguen siendo automáticos).
4. Verificar que el total mostrado sea **100.00 ✓**.
5. Guardar el quiz.

### Escenario C: todos los quizzes con ponderación manual por defecto

1. Ir a **FairPlay LMS → Ajustes de Tests → Ponderación de Preguntas**.
2. Seleccionar **Manual** como modo global.
3. Guardar.
4. Ir a cada quiz y configurar los pesos en su metabox.

### Escenario D: quizzes globalmente manuales pero uno específico equitativo

1. Configurar el modo global en **Manual** (igual que el escenario C).
2. En el quiz que debe ser equitativo, seleccionar **Forzar automática** en su metabox.
3. Guardar el quiz.

---

## Tabla resumen de la página de ajustes

La tarjeta en *Ajustes de Tests* muestra una tabla con el estado de todos los quizzes:

| Badge | Significado |
|---|---|
| `Heredado (Auto)` / `Heredado (Manual)` | El quiz usa el modo global |
| `Automática` | El quiz tiene forzado el modo automático |
| `Manual` | El quiz tiene configurada ponderación manual |
| `Distribución equitativa` | Modo auto activo — puntos repartidos por igual |
| `Configurado (= 100)` | Ponderación manual con suma correcta |
| `Configurado (≠ 100)` | Ponderación manual guardada pero la suma no da 100 |
| `Sin configurar` | El quiz está en modo manual pero no tiene pesos guardados |

---

## Notas técnicas

- Los pesos se almacenan como JSON en un único post_meta para minimizar las consultas a la base de datos.
- La lectura de preguntas (`get_question_ids`) es compatible con los dos formatos de MasterStudy: array de IDs enteros y array de arrays con clave `'id'`.
- El metabox aparece en posición `normal` (ancho completo) para tener espacio suficiente para la tabla de preguntas.
- El nonce de seguridad usa la clave `fplms_quiz_weights` y se verifica antes de cualquier escritura.
- Las operaciones de escritura comprueban `current_user_can('edit_post', $post_id)` antes de guardar.
