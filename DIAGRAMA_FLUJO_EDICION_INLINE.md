# Diagrama de Flujo - Sistema de Edición Inline

## 🔄 Flujo Principal de Edición

```
┌────────────────────────────────────────────────────────────────┐
│  USUARIO VE ESTRUCTURA (Acordeón Cerrado)                     │
│  ┌────────────────────────────────────────────────────────┐   │
│  │ ▶ Barcelona (3 ciudades)  [Editar] [Eliminar]         │   │
│  └────────────────────────────────────────────────────────┘   │
└────────────────────────────────────────────────────────────────┘
                              ▼
                    Click: "Editar Estructura"
                              ▼
┌────────────────────────────────────────────────────────────────┐
│  USUARIO VE FORMULARIO INLINE (Acordeón Expandido)            │
│  ┌────────────────────────────────────────────────────────┐   │
│  │ ▼ Barcelona (3 ciudades)  [Cancelar] [Eliminar]        │   │
│  ├────────────────────────────────────────────────────────┤   │
│  │ FORMULARIO DE EDICIÓN:                                 │   │
│  │                                                        │   │
│  │ Nombre: [Barcelona                               ]     │   │
│  │                                                        │   │
│  │ Ciudades asociadas:                                    │   │
│  │ ┌─────────────────────────────────────────────┐        │   │
│  │ │ Buscar: [search...                        ] │        │   │
│  │ └─────────────────────────────────────────────┘        │   │
│  │                                                        │   │
│  │ [☑] Barcelona    [☐] Madrid      [☐] Alicante         │   │
│  │ [☑] Valencia     [☐] Sevilla     [☐] Málaga           │   │
│  │ [☑] Bilbao       [☐] Zaragoza    [☐] Murcia           │   │
│  │                                                        │   │
│  │ [Guardar Cambios]  [Cancelar]                          │   │
│  └────────────────────────────────────────────────────────┘   │
└────────────────────────────────────────────────────────────────┘
                        ▼ (Usuario modifica)
         ┌─────────────────────────────────────┐
         │                                       │
    ▼ Búsqueda      ▼ Selección     ▼ Edición
    ("madr")        (Checkboxes)    (Nombre)
         │                                       │
         │      Valida                          │
         │      nombre?                         │
         │          │                           │
         └─────────►NO                          │
                   ▼                           │
            [ALERTA]                           │
          (Nombre vacío)                       │
                   │                           │
                   └──────────────────────────┘
                                ▼
                        Usuario rellena
                        y hace click
                        "Guardar"
                                ▼
         ┌──────────────────────────────────┐
         │   ✓ Cambio Guardado              │
         │   "Barcelona" con 3 ciudades     │
         │                                  │ ← Auto-cierra en 4s
         │   [×]                            │
         └──────────────────────────────────┘
                        ▼
        Formulario se cierra
        Botón vuelve a "Editar"
        Cambios persistidos en BD
```

---

## 🔍 Flujo de Búsqueda de Ciudades

```
USUARIO ESCRIBE EN CAMPO DE BÚSQUEDA
         ▼
    "m" → Filtra en tiempo real
    "ma" → Sigue filtrando
    "mad" → Sigue filtrando
    "madr" → Muestra solo "Madrid"
         ▼
LISTA ACTUALIZADA DINÁMICAMENTE:

ANTES (todas):
[☑] Barcelona   [☐] Madrid      [☐] Alicante
[☑] Valencia    [☐] Sevilla     [☐] Málaga
[☑] Bilbao      [☐] Zaragoza    [☐] Murcia

DESPUÉS (búsqueda "madr"):
[☐] Madrid    ← Solo opción que contiene "madr"

         ▼
USUARIO SELECCIONA Y GUARDA
```

---

## 📤 Flujo de Envío de Datos

```
┌─────────────────────────────────────────────┐
│ FORMULARIO EN NAVEGADOR                     │
├─────────────────────────────────────────────┤
│ Nombre: Barcelona                           │
│ Ciudades: [3, 5, 7] (IDs seleccionados)    │
│ Nonce: (validación CSRF)                    │
│ Taxonomía: fplms_channel                    │
└─────────────────────────────────────────────┘
                    ▼
        POST /wp-admin/admin.php
        action=fplms_structures_save
                    ▼
        ┌──────────────────────────────┐
        │ SERVIDOR PHP                 │
        ├──────────────────────────────┤
        │ 1. Verifica nonce ✓          │
        │ 2. Valida permisos ✓         │
        │ 3. Sanitiza datos ✓          │
        │ 4. Actualiza término         │
        │ 5. Guarda relaciones ciudad  │
        │ 6. Redirige (auto-reload) ✓  │
        └──────────────────────────────┘
                    ▼
        ┌──────────────────────────────┐
        │ BASE DE DATOS               │
        ├──────────────────────────────┤
        │ wp_terms:                    │
        │ - Barcelona (updated)        │
        │                              │
        │ wp_termmeta:                 │
        │ - city_relations: [3,5,7]    │
        └──────────────────────────────┘
                    ▼
        ┌──────────────────────────────┐
        │ NAVEGADOR (reload)           │
        ├──────────────────────────────┤
        │ JS showSuccess("✓...")       │
        │ Form closes                  │
        │ Page reloaded with new data  │
        └──────────────────────────────┘
```

---

## 🎭 Estados del Botón "Editar"

```
ESTADO 1: NORMAL (Acordeón cerrado)
┌────────────────────────┐
│ ▶ Barcelona  [Editar]  │  ← Botón azul (#e3f2fd)
└────────────────────────┘

ESTADO 2: EDITANDO (Acordeón abierto con formulario)
┌────────────────────────────────────────────────┐
│ ▼ Barcelona  [Cancelar]  [Eliminar]            │
├────────────────────────────────────────────────┤
│ <formulario inline>                            │
└────────────────────────────────────────────────┘
  Botón cambia a naranja (#ffe0b2) y dice "Cancelar"

ESTADO 3: DESPUÉS DE GUARDAR
┌────────────────────────┐
│ ▼ Barcelona  [Editar]  │  ← Vuelve a azul
└────────────────────────┘
  Formulario se oculta
  Aparece notificación verde
```

---

## 🔄 Interacción con Sistema Existente

```
┌──────────────────────────────────────────────────────┐
│ ESTRUCTURA DE DATOS JERÁRQUICA                      │
├──────────────────────────────────────────────────────┤
│                                                      │
│          CIUDADES (fplms_city)                      │
│         /        |        \                         │
│     Madrid    Barcelona  Valencia                    │
│        │           │          │                      │
│        └───────────┼──────────┘                      │
│                    │                                │
│              CANALES (fplms_channel)                 │
│              /      |       \                        │
│           Web     App    Presencial                  │
│            │        │        │                       │
│            └────────┼────────┘                       │
│                     │                               │
│          CURSOS VISIBLES (course_visibility)        │
│                     │                               │
│    (Combinación de city + channel = visibility)     │
│                                                      │
└──────────────────────────────────────────────────────┘

EJEMPLO DE RELACIÓN:
- Canal "Web" relacionado con ciudades: Barcelona, Valencia
- Curso "Python 101" en canal "Web"
- Resultado: Curso visible en Barcelona y Valencia (web)
           pero NO en Madrid
```

---

## 🎯 Puntos de Validación

```
┌─ VALIDACIÓN 1: Nombre no vacío
│  └─ Si falla: [ALERTA] "Por favor, ingresa un nombre"
│
├─ VALIDACIÓN 2: Nonce CSRF válido
│  └─ Si falla: [ERROR 403] "Acción no permitida"
│
├─ VALIDACIÓN 3: Usuario tiene capacidad CAP_MANAGE_STRUCTURES
│  └─ Si falla: [ERROR 403] "Sin permisos"
│
├─ VALIDACIÓN 4: Taxonomía válida
│  └─ Si falla: [ERROR] "Taxonomía no válida"
│
└─ VALIDACIÓN 5: Ciudades existen en DB
   └─ Si una no existe: [IGNORADA] (skip silenciosamente)

SI TODOS LOS CHECKS PASAN:
   ✓ Guarda cambios
   ✓ Muestra notificación
   ✓ Cierra formulario
```

---

## 📊 Estados de la Interfaz

```
ESTADO: VISTA INICIAL (Acordeón contraído)
┌──────────────────────────────────────┐
│ ▶ Barcelona (3)  [Editar] [Eliminar] │
├──────────────────────────────────────┤
│ (Contenido del acordeón oculto)      │
└──────────────────────────────────────┘
   ↓ (usuario hace clic en acordeón)

ESTADO: ACORDEÓN EXPANDIDO, EDICIÓN CERRADA
┌──────────────────────────────────────┐
│ ▼ Barcelona (3)  [Editar] [Eliminar] │
├──────────────────────────────────────┤
│ (Contenido del acordeón visible)     │
│ Información de Barcelona...          │
│ Ciudades: Madrid, Valencia, Bilbao   │
│                                      │
└──────────────────────────────────────┘
   ↓ (usuario hace clic en "Editar")

ESTADO: FORMULARIO INLINE VISIBLE
┌──────────────────────────────────────┐
│ ▼ Barcelona (3)  [Cancelar] [Eliminar]│
├──────────────────────────────────────┤
│ EDICIÓN:                             │
│ Nombre: [Barcelona          ]        │
│ Buscar: [search...        ]          │
│ [☑] Madrid   [☑] Valencia [☑] Bilbao│
│ [☐] Sevilla  [☐] Alicante...       │
│                                      │
│ [Guardar Cambios]  [Cancelar]       │
└──────────────────────────────────────┘
   ↓ (usuario hace clic en "Guardar")

ESTADO: GUARDANDO (Transición)
   ✓ Notificación aparece
   ✓ Formulario se cierra
   ✓ Botón vuelve a "Editar"
   ✓ Datos en BD actualizados

ESTADO: POST-GUARDADO
┌──────────────────────────────────────┐
│ ▼ Barcelona (3)  [Editar] [Eliminar] │
├──────────────────────────────────────┤
│ (Contenido actualizado)              │
│ Barcelona está ahora en 3 ciudades   │
│                                      │
└──────────────────────────────────────┘

┌─────────────────────────────────────┐
│ ✓ Cambio guardado: "Barcelona"      │ ← Notificación
│   con 3 ciudad(es) relacionada(s)   │   (auto-cierra en 4s)
└─────────────────────────────────────┘
```

---

## 🔌 Integración con Visibilidad de Cursos

```
ANTES (Sin relación):
Canal "Web" → [NO asociado a ciudades] → Cursos: No visibles

DESPUÉS (Con relación inline):
1. Editor hace clic en "Editar" de canal "Web"
2. Selecciona ciudades: Barcelona, Madrid, Valencia
3. Hace clic "Guardar"
4. Sistema guarda relación en term_meta
5. Sistema de visibilidad de cursos:
   - Detecta que "Web" está en Barcelona, Madrid, Valencia
   - Cursos del canal "Web" aparecen en esas ciudades
   - En otras ciudades NO aparecen

RESULTADO:
Madrid:
  ├─ Cursos Web ✓
  ├─ Cursos App
  └─ Cursos Presencial

Barcelona:
  ├─ Cursos Web ✓
  ├─ Cursos App
  └─ Cursos Presencial

Valencia:
  ├─ Cursos Web ✓
  ├─ Cursos App
  └─ Cursos Presencial

Sevilla:
  ├─ Cursos Web ✗ (no relacionado)
  ├─ Cursos App
  └─ Cursos Presencial
```

---

## 💡 Mejoras de UX

```
BUSCAR MIENTRAS ESCRIBES:
┌─────────────────────────┐
│ Buscar: [m________ ]    │
│ [☐] Madrid              │  ← Se muestra
│ [☐] Málaga              │  ← Se muestra
│ [☐] Murcia              │  ← Se muestra
└─────────────────────────┘

┌─────────────────────────┐
│ Buscar: [ma______ ]     │
│ [☐] Madrid              │  ← Se muestra
│ [☐] Málaga              │  ← Se muestra
│ (☐ Murcia)              │  ← Se oculta
└─────────────────────────┘

┌─────────────────────────┐
│ Buscar: [val_____ ]     │
│ [☑] Valencia ✓          │  ← Se muestra (ya seleccionada)
└─────────────────────────┘

SELECCIONES CLARAS:
[☑] Seleccionada (azul, destacada)
[☐] No seleccionada (gris, normal)

NOTIFICACIÓN DE CAMBIO:
┌──────────────────────────────┐
│ ✓ Cambio guardado:           │
│   "Barcelona" con 3 ciudades  │ ← Detalles del cambio
│                        [×]    │
└──────────────────────────────┘
```

---

## ✅ Checklist de Funcionalidad

- [x] Formulario inline en acordeón (no modal)
- [x] Búsqueda de ciudades en tiempo real
- [x] Case-insensitive search
- [x] Checkboxes para múltiples selecciones
- [x] Validación de nombre no vacío
- [x] Validación de nonce (seguridad)
- [x] Mensaje de éxito con detalles
- [x] Auto-cierre de notificación
- [x] Botón cierre manual de notificación
- [x] Responsive design (mobile-friendly)
- [x] Integración con lógica existente
- [x] Relaciones ciudad-canal-curso funcionando
- [x] Cambios persistidos en BD

---

