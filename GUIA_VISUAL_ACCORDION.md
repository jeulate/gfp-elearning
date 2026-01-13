# 🎨 Guía Visual - Nueva Interfaz Acordeón

## 📺 Vista General de la Interfaz

```
┌─────────────────────────────────────────────────────────────────┐
│  WordPress Admin                                       ☰ Menu    │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  ⚙️ Gestión de Estructuras                                       │
│  Organiza tu empresa en ciudades, canales, sucursales y cargos.  │
│  Expande cada sección para ver, editar o eliminar elementos.     │
│                                                                   │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ ▶ 📍 Ciudades                                   (5)      │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                   │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ ▼ 🏪 Canales / Franquicias                      (3)      │   │
│  ├──────────────────────────────────────────────────────────┤   │
│  │                                                          │   │
│  │  ┌──────────────────────────────────────────────────┐  │   │
│  │  │ Premium         ✓ Activo    [⊙○] [✏️] [🗑️]     │  │   │
│  │  │ 🔗 Madrid, Barcelona, Valencia                   │  │   │
│  │  └──────────────────────────────────────────────────┘  │   │
│  │                                                          │   │
│  │  ┌──────────────────────────────────────────────────┐  │   │
│  │  │ Estándar        ✗ Inactivo  [⊙○] [✏️] [🗑️]     │  │   │
│  │  │ 🔗 Madrid, Barcelona                             │  │   │
│  │  └──────────────────────────────────────────────────┘  │   │
│  │                                                          │   │
│  │  ┌──────────────────────────────────────────────────┐  │   │
│  │  │ VIP             ✓ Activo    [⊙○] [✏️] [🗑️]     │  │   │
│  │  │ 🔗 Todas las ciudades                            │  │   │
│  │  └──────────────────────────────────────────────────┘  │   │
│  │                                                          │   │
│  │  ┌──────────────────────────────────────────────────┐  │   │
│  │  │ ➕ Crear nuevo elemento                          │  │   │
│  │  │ Nombre: [_______________]                       │  │   │
│  │  │ Ciudades: [_____________] ✓ Activo [Crear]    │  │   │
│  │  └──────────────────────────────────────────────────┘  │   │
│  │                                                          │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                   │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ ▶ 🏢 Sucursales                                (8)      │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                   │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ ▶ 👔 Cargos                                   (12)      │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                   │
└─────────────────────────────────────────────────────────────────┘
```

---

## 🎯 Estados del Acordeón

### Estado Cerrado
```
┌────────────────────────────────────────┐
│ ▶ 📍 Ciudades                    (5)  │
└────────────────────────────────────────┘
```
- Flecha apunta a la derecha
- Header con fondo gradiente suave
- Contador visible
- Al pasar mouse: fondo más oscuro

### Estado Abierto
```
┌────────────────────────────────────────┐
│ ▼ 📍 Ciudades                    (5)  │
├────────────────────────────────────────┤
│ [Lista de ciudades]                    │
│ [Formulario crear nuevo]               │
└────────────────────────────────────────┘
```
- Flecha rota 90° hacia abajo
- Header más claro
- Body se expande con animación slideDown
- Solo una sección abierta a la vez

---

## 🔷 Elemento Individual (Término)

### Estructura Visual

```
┌─────────────────────────────────────────────────────┐
│ ┌──────────────────────┬──────────┬────┬────┬────┐ │
│ │ Madrid               │ Ciudades │ ✓  │ ⊙○ │ ✏️ │🗑️│
│ │                      │ Ciudades │ A. │    │    │ │
│ │                      │          │ c  │    │    │ │
│ │                      │          │ t  │    │    │ │
│ │                      │          │ i  │    │    │ │
│ │                      │          │ v  │    │    │ │
│ │                      │          │ o  │    │    │ │
│ │                      │          │    │    │    │ │
│ └──────────────────────┴──────────┴────┴────┴────┘ │
└─────────────────────────────────────────────────────┘

Colores por estructura:
🔵 Azul (#0073aa) para Ciudades
🟢 Verde (#00a000) para Canales
🟠 Naranja (#ff6f00) para Sucursales
🟣 Púrpura (#7c3aed) para Cargos
```

### Componentes

```
┌─────────────────────────────────────────┐
│ [Nombre]  [Ciudades]  [Status] [Acciones]│
│                                          │
│ Madrid    📍 Vínculo    ✓ Activo   ⊙○ ✏️ 🗑️│
│ Barcelona               ✗ Inactivo ⊙○ ✏️ 🗑️│
│ Valencia               ✓ Activo   ⊙○ ✏️ 🗑️│
└─────────────────────────────────────────┘

[Nombre]     = Texto del término
[Ciudades]   = "🔗 Madrid, Barcelona" (solo si aplica)
[Status]     = "✓ Activo" (verde) o "✗ Inactivo" (rojo)
[Acciones]   = 3 botones con acciones
```

---

## 🎨 Paleta de Colores

### Acordeón
```
Header Background:  Gradient #f5f5f5 → #f9f9f9
Header Hover:       Gradient #e8e8e8 → #efefef
Border:             #ddd (1px)
Sombra:             0 2px 4px rgba(0,0,0,0.1)
```

### Términos
```
Fondo Normal:       #fff
Fondo Hover:        #f9f9f9
Borde Normal:       #e0e0e0
Borde Hover:        #bbb
Borde Activo:       #0073aa (izquierdo 4px)
```

### Badges de Estado
```
Activo:             Fondo #d4edda, Texto #155724
Inactivo:           Fondo #f8d7da, Texto #721c24
Ciudades:           Fondo #f0f0f0, Texto #666
Contador:           Fondo #f0f0f0, Texto #666
```

### Botones
```
Toggle (⊙○):
  Normal:           Fondo #e8f5e9, Texto #2e7d32
  Hover:            Fondo #c8e6c9

Edit (✏️):
  Normal:           Fondo #e3f2fd, Texto #1565c0
  Hover:            Fondo #bbdefb

Delete (🗑️):
  Normal:           Fondo #ffebee, Texto #c62828
  Hover:            Fondo #ffcdd2
```

---

## 📊 Layout Desktop (> 768px)

```
┌─────────────────────────────────────────────────────────────┐
│ Nombre             Ciudades           Status   Acciones    │
│ ─────────────────────────────────────────────────────────  │
│ Madrid             -                  ✓ Activo  ⊙○ ✏️ 🗑️   │
│ Barcelona          -                  ✓ Activo  ⊙○ ✏️ 🗑️   │
│ Valencia           -                  ✗ Inactivo ⊙○ ✏️ 🗑️  │
│                                                              │
│ En Canales:                                                │
│ Premium            Madrid, Barcelona  ✓ Activo  ⊙○ ✏️ 🗑️   │
│ Estándar           Madrid             ✗ Inactivo ⊙○ ✏️ 🗑️  │
└─────────────────────────────────────────────────────────────┘

Ancho total: 100% (hasta 1200px max-width)
Columnas: 4 (Nombre | Ciudades | Status | Acciones)
Botones: Inline (sin saltos)
```

---

## 📱 Layout Tablet (480px - 768px)

```
┌─────────────────────────────────┐
│ Madrid                          │
│ 🔗 Ciudades                     │
│ ✓ Activo                        │
│ ⊙○ ✏️ 🗑️ ← Stacked             │
│                                 │
│ Barcelona                       │
│ 🔗 Ciudades                     │
│ ✗ Inactivo                      │
│ ⊙○ ✏️ 🗑️ ← Stacked             │
└─────────────────────────────────┘

Ancho: 100% del contenedor
Elementos se apilan horizontalmente
Botones en fila
```

---

## 📱 Layout Mobile (< 480px)

```
┌──────────────────────┐
│ Madrid              │
│ 🔗 Ciudades         │
│ ✓ Activo            │
│ ⊙○                  │
│ ✏️                  │
│ 🗑️                  │
│                     │
│ Barcelona           │
│ 🔗 Ciudades         │
│ ✗ Inactivo          │
│ ⊙○ ✏️ 🗑️           │
└──────────────────────┘

Ancho: 100% menos padding
Elementos en columna
Botones pequeños pero palpables (min 32x32px)
Texto truncado con ellipsis si es necesario
```

---

## 🪟 Modal de Edición

```
┌─────────────────────────────────────────────────┐
│ ✏️ Editar Estructura                        ✕  │
├─────────────────────────────────────────────────┤
│                                                 │
│  Nombre del elemento                           │
│  [_________________________________]            │
│                                                 │
│  Ciudades Relacionadas                         │
│  [_________________________ ✕ _____ ✕ ___]     │
│  [Tags azules con ciudades seleccionadas]      │
│                                                 │
├─────────────────────────────────────────────────┤
│  [Cancelar]                    [Guardar Cambios]│
└─────────────────────────────────────────────────┘

Modal Properties:
- Max-width: 600px
- Centrado en pantalla
- Animación: fadeIn + slideIn
- Overlay: rgba(0,0,0,0.5)
- Selectable: Cancelar al hacer clic afuera
```

---

## 🗑️ Modal de Confirmación Eliminación

```
┌──────────────────────────────────────┐
│ 🗑️ Confirmar Eliminación         ✕  │
├──────────────────────────────────────┤
│                                      │
│ ¿Estás seguro de que deseas          │
│ eliminar este elemento?              │
│                                      │
│ "Madrid"                             │
│                                      │
│ Esta acción no se puede deshacer.   │
│                                      │
├──────────────────────────────────────┤
│ [Cancelar]   [Eliminar Definitivamente]
└──────────────────────────────────────┘

Modal Properties:
- Max-width: 400px
- Color de botón delete: #c00
- Muestra nombre del elemento
- Advertencia clara
```

---

## 🆕 Formulario Crear Elemento

```
┌─────────────────────────────────────────────┐
│ ➕ Crear nuevo elemento                     │
├─────────────────────────────────────────────┤
│                                             │
│ [Nombre del elemento...]                    │
│ [Selecciona ciudades...] ✓ Activo [Crear]  │
│                                             │
│ Desktop (1 fila):                          │
│ [Input] [Multiselect] [Checkbox] [Botón]   │
│                                             │
│ Mobile (4 filas):                          │
│ [Input Full Width]                         │
│ [Multiselect Full Width]                   │
│ [Checkbox]                                 │
│ [Botón Full Width]                         │
│                                             │
└─────────────────────────────────────────────┘

Estilos:
- Fondo: #f5f5f5
- Borde: 2px dashed #ddd
- Border-radius: 4px
- Padding: 16px
```

---

## 🎬 Animaciones

### Acordeón Expand
```
Duración: 0.3s ease
De:       Body opacity: 0, translateY(-10px)
A:        Body opacity: 1, translateY(0)
```

### Flecha Rotate
```
Duración: 0.3s ease
De:       rotate(0deg)
A:        rotate(90deg)
Trigger:  .active class
```

### Modal Fade In
```
Duración: 0.2s ease
De:       overlay opacity: 0
A:        overlay opacity: 1
```

### Modal Slide In
```
Duración: 0.3s ease
De:       Content opacity: 0, translateY(-50px)
A:        Content opacity: 1, translateY(0)
```

### Hover Term Item
```
Duración: 0.2s ease
De:       Normal styling
A:        Fondo #f9f9f9, translateX(2px)
```

---

## 🎯 Estados de Botones

### Toggle Button (⊙○)
```
Normal:    Verde claro #e8f5e9, Texto #2e7d32
Hover:     Verde más oscuro #c8e6c9
Active:    En formulario, siempre visible
Disabled:  Nunca deshabilitado
```

### Edit Button (✏️)
```
Normal:    Azul claro #e3f2fd, Texto #1565c0
Hover:     Azul más oscuro #bbdefb, Scale 1.1
Active:    Abre modal
Disabled:  Nunca deshabilitado
```

### Delete Button (🗑️)
```
Normal:    Rojo claro #ffebee, Texto #c62828
Hover:     Rojo más oscuro #ffcdd2, Scale 1.1
Active:    Abre modal confirmación
Disabled:  Nunca deshabilitado
```

### Primary Button (Crear/Guardar)
```
Color:     Usa color de sección (variable)
Texto:     Blanco
Hover:     Más oscuro + Scale 1.05
Active:    Submite forma
```

---

## 🔢 Indicadores Numéricos

### Contador en Header
```
Formato:   "( 5 )"
Color:     #666 sobre #f0f0f0
Padding:   2px 8px
Border-radius: 12px
Tamaño:    12px font-size
```

### Posición en Badge Status
```
Activo:    "✓ Activo" (Verde)
Inactivo:  "✗ Inactivo" (Rojo)
Tamaño:    11px font-size
Padding:   4px 10px
Border-radius: 12px
```

---

## 🖱️ Interactividad

### Acordeón
```
Click Header:    Abre/Cierra body
              ↓
Flecha rota (▶→▼)
Body animado (slideDown)
```

### Botón Toggle
```
Click ⊙○:        Toggle active meta
              ↓
Status cambia (✓ Activo ← → ✗ Inactivo)
Fondo cambia (verde ← → rojo)
Forma se envia POST
Página recarga
```

### Botón Edit
```
Click ✏️:        Abre modal con datos
              ↓
Campos precargan valores
Multiselect muestra ciudades
User edita
Click "Guardar Cambios"
              ↓
Form POST
Página recarga
```

### Botón Delete
```
Click 🗑️:       Abre modal confirmación
              ↓
Muestra nombre elemento
User confirma "Eliminar Definitivamente"
              ↓
Form POST (action=delete)
Página recarga
```

---

## ✨ Efecto Visual General

La interfaz mantiene:
- **Consistencia**: Mismo color para misma acción
- **Claridad**: Iconos y texto claro
- **Retroalimentación**: Hover, focus, active estados
- **Animación**: Smooth transitions
- **Accesibilidad**: Tamaños palpables, contraste
- **Responsividad**: Adapta a cualquier tamaño

---

## 📐 Medidas CSS

```css
/* Espacios */
Gap: 12px (entre elementos)
Padding: 16px 20px (header)
Padding: 20px (body)
Margin: 15px 0 (items)

/* Tamaños de Botón */
Min-width: 32px
Height: 32px
Border-radius: 4px

/* Tamaños de Fuente */
Header: 15px
Body: 13-14px
Status Badge: 11px
Contador: 12px

/* Sombras */
Normal: 0 2px 4px rgba(0,0,0,0.1)
Hover: 0 4px 8px rgba(0,0,0,0.15)
Modal: 0 10px 40px rgba(0,0,0,0.3)
```

---

**Nota**: Esta guía visual es una representación ASCII de la interfaz real. Los colores, animaciones y efectos son los descritos en los archivos CSS del proyecto.

---

**Versión**: 1.0  
**Tipo**: Documentación Visual  
**Estado**: ✅ Completado
