# ✨ Visualización de Mejoras - Interfaz Creación de Usuarios

**Fecha:** 15 de Enero de 2026  
**Versión:** 1.0

---

## 🎨 Interfaz Visual Mejorada

### Layout General

```
════════════════════════════════════════════════════════════════════════════════
                         CREAR NUEVO USUARIO
════════════════════════════════════════════════════════════════════════════════

┏━━━━━━━━━━━━━━━━━━━━━━━━┓   ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
┃                        ┃   ┃ DATOS PERSONALES                         ┃
┃      📷 Haz clic       ┃   ┃ ─────────────────────────────────────   ┃
┃      para subir        ┃   ┃                                          ┃
┃     fotografía         ┃   ┃ ┌────────────────────┐ ┌──────────────┐ ┃
┃                        ┃   ┃ │ Nombre *           │ │ Apellido *   │ ┃
┃                        ┃   ┃ │ [______________]   │ │ [__________] │ ┃
┃                        ┃   ┃ └────────────────────┘ └──────────────┘ ┃
┃                        ┃   ┃                                          ┃
┃    280 × 280 px        ┃   ┃ CREDENCIALES DE ACCESO                  ┃
┃    Drag & Drop         ┃   ┃ ─────────────────────────────────────   ┃
┃                        ┃   ┃                                          ┃
┃   Borde naranja        ┃   ┃ ┌──────────────────────────────────────┐ ┃
┃   #e0a05d              ┃   ┃ │ Nombre de usuario *                  │ ┃
┃                        ┃   ┃ │ [__________________________________] │ ┃
┃                        ┃   ┃ └──────────────────────────────────────┘ ┃
┃                        ┃   ┃                                          ┃
┃                        ┃   ┃ ┌──────────────────────────────────────┐ ┃
┃                        ┃   ┃ │ Correo electrónico *                 │ ┃
┃                        ┃   ┃ │ [__________________________________] │ ┃
┃                        ┃   ┃ └──────────────────────────────────────┘ ┃
┃                        ┃   ┃                                          ┃
┃                        ┃   ┃ ┌──────────────────────────────────────┐ ┃
┃                        ┃   ┃ │ Contraseña *                         │ ┃
┃                        ┃   ┃ │ [__________________________________] │ ┃
┃                        ┃   ┃ └──────────────────────────────────────┘ ┃
┃                        ┃   ┃                                          ┃
┃                        ┃   ┃ ESTRUCTURA ORGANIZACIONAL              ┃
┃                        ┃   ┃ ─────────────────────────────────────   ┃
┃                        ┃   ┃                                          ┃
┃                        ┃   ┃ ┌────────────────────┐ ┌──────────────┐ ┃
┃                        ┃   ┃ │ Ciudad             │ │ Canal        │ ┃
┃                        ┃   ┃ │ [Dropdown ▼]       │ │ [Dropdown▼]  │ ┃
┃                        ┃   ┃ └────────────────────┘ └──────────────┘ ┃
┃                        ┃   ┃                                          ┃
┃                        ┃   ┃ ┌────────────────────┐ ┌──────────────┐ ┃
┃                        ┃   ┃ │ Sucursal           │ │ Cargo        │ ┃
┃                        ┃   ┃ │ [Dropdown ▼]       │ │ [Dropdown▼]  │ ┃
┃                        ┃   ┃ └────────────────────┘ └──────────────┘ ┃
┃                        ┃   ┃                                          ┃
┃                        ┃   ┃ TIPO DE USUARIO                         ┃
┃                        ┃   ┃ ─────────────────────────────────────   ┃
┃                        ┃   ┃ ┌──────────────────────────────────────┐ ┃
┃                        ┃   ┃ │ ☐ Alumno         ☐ Tutor             │ ┃
┃                        ┃   ┃ │ ☐ Instructor     ☐ Admin             │ ┃
┃                        ┃   ┃ └──────────────────────────────────────┘ ┃
┃                        ┃   ┃                                          ┃
┃                        ┃   ┃ ┌──────────────────────────────────────┐ ┃
┃                        ┃   ┃ │ ✓ Activo                             │ ┃
┃                        ┃   ┃ └──────────────────────────────────────┘ ┃
┃                        ┃   ┃                                          ┃
┃                        ┃   ┃                                          ┃
┃                        ┃   ┃ ┌──────────────┐  ┌──────────────────┐ ┃
┃                        ┃   ┃ │   Guardar    │  │    Cancelar      │ ┃
┃                        ┃   ┃ │  (Azul)      │  │  (Gris claro)    │ ┃
┃                        ┃   ┃ └──────────────┘  └──────────────────┘ ┃
┗━━━━━━━━━━━━━━━━━━━━━━━━┛   ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
```

---

## 🎯 Secciones del Formulario

### Sección 1: DATOS PERSONALES

```
╔════════════════════════════════════════════════════════════════╗
║ DATOS PERSONALES                                               ║
║ ──────────────────                                              ║
╠════════════════════════════════════════════════════════════════╣
║                                                                ║
║ ┌──────────────────────────────┐ ┌──────────────────────────┐ ║
║ │ Nombre *                     │ │ Apellido *               │ ║
║ │                              │ │                          │ ║
║ │ [____________________________]│ │ [______________________]│ ║
║ │                              │ │                          │ ║
║ └──────────────────────────────┘ └──────────────────────────┘ ║
║                                                                ║
╚════════════════════════════════════════════════════════════════╝
```

**Campos:**
- Nombre (required)
- Apellido (required)

**Layout:** 2 columnas

---

### Sección 2: CREDENCIALES DE ACCESO

```
╔════════════════════════════════════════════════════════════════╗
║ CREDENCIALES DE ACCESO                                         ║
║ ──────────────────────                                          ║
╠════════════════════════════════════════════════════════════════╣
║                                                                ║
║ Nombre de usuario *                                            ║
║ ┌────────────────────────────────────────────────────────────┐ ║
║ │ ________________________________________________             │ ║
║ └────────────────────────────────────────────────────────────┘ ║
║                                                                ║
║ Correo electrónico *                                           ║
║ ┌────────────────────────────────────────────────────────────┐ ║
║ │ ________________________________________________             │ ║
║ └────────────────────────────────────────────────────────────┘ ║
║                                                                ║
║ Contraseña *                                                   ║
║ ┌────────────────────────────────────────────────────────────┐ ║
║ │ ●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●●  │ ║
║ └────────────────────────────────────────────────────────────┘ ║
║                                                                ║
╚════════════════════════════════════════════════════════════════╝
```

**Campos:**
- Usuario (required) - Único, sin espacios
- Email (required) - Formato válido
- Contraseña (required) - Mínimo 6 caracteres

**Layout:** 1 columna (ancho completo)

---

### Sección 3: ESTRUCTURA ORGANIZACIONAL

```
╔════════════════════════════════════════════════════════════════╗
║ ESTRUCTURA ORGANIZACIONAL                                      ║
║ ────────────────────────                                        ║
╠════════════════════════════════════════════════════════════════╣
║                                                                ║
║ ┌──────────────────────┐ ┌──────────────────────────────────┐ ║
║ │ Ciudad               │ │ Canal / Franquicia               │ ║
║ │                      │ │                                  │ ║
║ │ [Dropdown ▼]         │ │ [Dropdown ▼]                     │ ║
║ │  Sin asignar         │ │  Sin asignar                     │ ║
║ │  Santa Cruz          │ │  Adidas                          │ ║
║ │  La Paz              │ │  Nike                            │ ║
║ └──────────────────────┘ └──────────────────────────────────┘ ║
║                                                                ║
║ ┌──────────────────────┐ ┌──────────────────────────────────┐ ║
║ │ Sucursal             │ │ Cargo                            │ ║
║ │                      │ │                                  │ ║
║ │ [Dropdown ▼]         │ │ [Dropdown ▼]                     │ ║
║ │  Sin asignar         │ │  Sin asignar                     │ ║
║ │  Adidas Ventura      │ │  Asesor                          │ ║
║ │  Nike Downtown       │ │  Gerente                         │ ║
║ └──────────────────────┘ └──────────────────────────────────┘ ║
║                                                                ║
╚════════════════════════════════════════════════════════════════╝
```

**Campos:**
- Ciudad (optional)
- Canal / Franquicia (optional)
- Sucursal (optional)
- Cargo (optional)

**Layout:** 2 columnas (2x2)

---

### Sección 4: TIPO DE USUARIO

```
╔════════════════════════════════════════════════════════════════╗
║ TIPO DE USUARIO *                                              ║
║ ──────────────────                                              ║
╠════════════════════════════════════════════════════════════════╣
║                                                                ║
║ ┌────────────────────────────────────────────────────────────┐ ║
║ │ ┌──────────────────────┐  ┌──────────────────────────────┐ ║
║ │ │ ☐ Alumno             │  │ ☐ Tutor                      │ ║
║ │ └──────────────────────┘  └──────────────────────────────┘ ║
║ │                                                            ║
║ │ ┌──────────────────────┐  ┌──────────────────────────────┐ ║
║ │ │ ☐ Instructor         │  │ ☐ Administrador              │ ║
║ │ └──────────────────────┘  └──────────────────────────────┘ ║
║ └────────────────────────────────────────────────────────────┘ ║
║                                                                ║
║ Nota: Seleccionar al menos uno                                ║
║                                                                ║
╚════════════════════════════════════════════════════════════════╝
```

**Opciones:**
- Alumno
- Tutor
- Instructor
- Administrador

**Layout:** 2x2 grid  
**Requerido:** Sí, mínimo uno

---

### Sección 5: ESTADO

```
╔════════════════════════════════════════════════════════════════╗
║                                                                ║
║ ┌────────────────────────────────────────────────────────────┐ ║
║ │ ✓ Activo                                                   │ ║
║ └────────────────────────────────────────────────────────────┘ ║
║                                                                ║
╚════════════════════════════════════════════════════════════════╝
```

**Opciones:**
- ✓ Activo (default)
- ☐ Inactivo

---

### Sección 6: BOTONES DE ACCIÓN

```
╔════════════════════════════════════════════════════════════════╗
║                                                                ║
║ ┌────────────────┐                ┌──────────────────────────┐ ║
║ │ Guardar        │                │ Cancelar                 │ ║
║ │ (Azul #1976d2) │                │ (Gris #f5f5f5)           │ ║
║ │ Hover: Lift ↑  │                │ Hover: Oscurecer         │ ║
║ └────────────────┘                └──────────────────────────┘ ║
║                                                                ║
╚════════════════════════════════════════════════════════════════╝
```

**Botones:**
- Guardar - Azul, acción principal
- Cancelar - Gris, acción secundaria

---

## 🎨 Sección de Fotografía (Detalle)

### Sin Imagen

```
┌──────────────────────────────────────┐
│                                      │
│               📷                     │
│                                      │
│         Haz clic para subir          │
│            fotografía                │
│                                      │
│        (Borde naranja punteado)      │
│        Fondo: #fff8f0                │
│        280 × 280 px                  │
│                                      │
└──────────────────────────────────────┘
```

**Estados:**
- Normal: Borde #e0a05d, fondo #fff8f0
- Hover: Borde #ff9800, fondo #ffe8d1
- Dragover: Borde #ff9800, fondo #ffe8d1

### Con Imagen

```
┌──────────────────────────────────────┐
│                                      │
│                                      │
│        [Foto 280×280px]              │
│        Rincones redondeados          │
│        Object-fit: cover              │
│                                      │
│                                      │
│                                      │
│                                      │
└──────────────────────────────────────┘
```

**Características:**
- Sin borde
- Fondo transparente
- Imagen visible
- Rincones redondeados (8px)

---

## 🖱️ Interactividad

### Click en Área

```
Usuario hace clic
        ↓
Dialog "Abrir archivo"
        ↓
Selecciona imagen
        ↓
FileReader procesa
        ↓
Preview se actualiza
        ↓
Clase 'has-image' agregada
```

### Drag & Drop

```
Usuario arrastra archivo
        ↓
Dragover (fondo cambia)
        ↓
Suelta sobre área
        ↓
Drop event dispara
        ↓
FileReader procesa
        ↓
Preview se actualiza
        ↓
Clase 'has-image' agregada
```

---

## 📐 Medidas y Espaciado

```
CONTENEDOR:
├─ Padding: 40px
├─ Gap entre columnas: 40px
├─ Border radius: 8px
├─ Shadow: 0 2px 8px rgba(0,0,0,0.1)

COLUMNA IMAGEN:
├─ Max-width: 280px
├─ Aspect ratio: 1:1
├─ Border: 2px dashed

COLUMNA FORMULARIO:
├─ Flex direction: column
├─ Gap entre secciones: 20px

CAMPOS:
├─ Padding: 12px
├─ Border: 1px solid #ddd
├─ Border-radius: 6px
├─ Gap entre campos: 20px

SECCIONES:
├─ Margin-bottom: 15px
├─ Padding-bottom: 10px
├─ Border-bottom: 2px solid #f0f0f0

BOTONES:
├─ Padding: 14px 30px
├─ Border-radius: 6px
├─ Font-size: 16px
├─ Gap: 15px
```

---

## 🎨 Paleta de Colores

```
PRIMARIOS:
  Azul:           #1976d2 (botón guardar)
  Azul Dark:      #1565c0 (hover guardar)

NARANJA (Imagen):
  Principal:      #ff9800
  Borde:          #e0a05d
  Claro:          #fff8f0
  Hover:          #ffe8d1

GRISES:
  Muy claro:      #fafafa (fondos)
  Claro:          #f5f5f5 (botón cancel)
  Borde:          #ddd
  Separador:      #eee
  Texto:          #666 (secundario)
  Texto:          #333 (primario)

VERDE (Checkbox activo):
  Color:          #4caf50

ROJO (Requerido):
  Color:          #f44336
```

---

## 📱 Responsividad

### Desktop (≥1024px)

```
┌──────────────────────────────────────────────────┐
│ Foto (280×280)          Formulario (2 cols)      │
│                                                  │
│ [IMAGEN]                DATOS PERSONALES         │
│                         CREDENCIALES             │
│                         ESTRUCTURA               │
│                         TIPO USUARIO             │
│                         ESTADO                   │
│                         BOTONES                  │
└──────────────────────────────────────────────────┘

Gap: 40px
```

### Tablet (768px - 1024px)

```
┌──────────────────────────────┐
│ Foto (280×280)               │
├──────────────────────────────┤
│ Formulario (2 columnas)      │
│                              │
│ DATOS PERSONALES             │
│ CREDENCIALES                 │
│ ESTRUCTURA                   │
│ TIPO USUARIO                 │
│ ESTADO                       │
│ BOTONES                      │
└──────────────────────────────┘

Gap: 30px
```

### Móvil (<768px)

```
┌────────────────────┐
│ Foto (280×280)     │
├────────────────────┤
│ Formulario         │
│ (1 columna)        │
│                    │
│ DATOS              │
│ CREDENCIALES       │
│ ESTRUCTURA         │
│ TIPO USUARIO       │
│ ESTADO             │
│ BOTONES            │
└────────────────────┘

Gap: 30px
```

---

## ✨ Efectos y Transiciones

### Hover en Área de Imagen

```
Normal:                  Hover:
Borde: #e0a05d         Borde: #ff9800
Fondo: #fff8f0         Fondo: #ffe8d1
Cursor: pointer        Cursor: pointer
                       Transition: 0.3s ease
```

### Focus en Campos

```
Normal:                           Focus:
Border: #ddd                     Border: #ff9800
Box-shadow: none                 Box-shadow: 0 0 0 3px
                                 rgba(255, 152, 0, 0.1)
Transition: 0.3s                 Transition: 0.3s
```

### Hover en Botón Guardar

```
Normal:                  Hover:
BG: #1976d2            BG: #1565c0
Color: white           Color: white
Transform: none        Transform: translateY(-2px)
Box-shadow: none       Box-shadow: 0 4px 8px
                       rgba(25, 118, 210, 0.3)
```

### Hover en Botón Cancelar

```
Normal:                  Hover:
BG: #f5f5f5            BG: #e0e0e0
Color: #333            Color: #333
Transform: none        Transform: none
Box-shadow: none       Box-shadow: none
```

---

## 🎭 Estados Visuales

### Cargando

```
Spinner o loader
(Implementación según preferencia)
```

### Error

```
┌─────────────────────────────────────┐
│ ⚠️ Datos incompletos. Verifica      │
│    que llenes todos los campos      │
│    requeridos.                      │
└─────────────────────────────────────┘
```

### Éxito

```
┌─────────────────────────────────────┐
│ ✓ Usuario creado correctamente.     │
│   ID: 12345                         │
└─────────────────────────────────────┘
```

---

## 📊 Ejemplo Completo Rellenado

```
════════════════════════════════════════════════════════════════════════════════
                         CREAR NUEVO USUARIO
════════════════════════════════════════════════════════════════════════════════

┏━━━━━━━━━━━━━━━━━━━━━━━━┓   ┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
┃                        ┃   ┃ DATOS PERSONALES                         ┃
┃    [FOTO MARÍA]        ┃   ┃ ─────────────────────────────────────   ┃
┃                        ┃   ┃                                          ┃
┃                        ┃   ┃ ┌────────────────────┐ ┌──────────────┐ ┃
┃                        ┃   ┃ │ María              │ │ López        │ ┃
┃                        ┃   ┃ └────────────────────┘ └──────────────┘ ┃
┃                        ┃   ┃                                          ┃
┃                        ┃   ┃ CREDENCIALES DE ACCESO                  ┃
┃                        ┃   ┃ ─────────────────────────────────────   ┃
┃                        ┃   ┃ ┌──────────────────────────────────────┐ ┃
┃                        ┃   ┃ │ mlopez                               │ ┃
┃                        ┃   ┃ └──────────────────────────────────────┘ ┃
┃                        ┃   ┃ ┌──────────────────────────────────────┐ ┃
┃                        ┃   ┃ │ maria.lopez@empresa.com              │ ┃
┃                        ┃   ┃ └──────────────────────────────────────┘ ┃
┃                        ┃   ┃ ┌──────────────────────────────────────┐ ┃
┃                        ┃   ┃ │ ••••••••••••••••••••••••••••••       │ ┃
┃                        ┃   ┃ └──────────────────────────────────────┘ ┃
┃                        ┃   ┃                                          ┃
┃                        ┃   ┃ ESTRUCTURA ORGANIZACIONAL              ┃
┃                        ┃   ┃ ─────────────────────────────────────   ┃
┃                        ┃   ┃ ┌────────────────────┐ ┌──────────────┐ ┃
┃                        ┃   ┃ │ Santa Cruz ▼       │ │ Adidas ▼     │ ┃
┃                        ┃   ┃ └────────────────────┘ └──────────────┘ ┃
┃                        ┃   ┃ ┌────────────────────┐ ┌──────────────┐ ┃
┃                        ┃   ┃ │ Adidas Ventura ▼   │ │ Asesor ▼     │ ┃
┃                        ┃   ┃ └────────────────────┘ └──────────────┘ ┃
┃                        ┃   ┃                                          ┃
┃                        ┃   ┃ TIPO DE USUARIO                         ┃
┃                        ┃   ┃ ─────────────────────────────────────   ┃
┃                        ┃   ┃ ┌──────────────────────────────────────┐ ┃
┃                        ┃   ┃ │ ☑ Alumno         ☐ Tutor             │ ┃
┃                        ┃   ┃ │ ☐ Instructor     ☐ Admin             │ ┃
┃                        ┃   ┃ └──────────────────────────────────────┘ ┃
┃                        ┃   ┃                                          ┃
┃                        ┃   ┃ ┌──────────────────────────────────────┐ ┃
┃                        ┃   ┃ │ ✓ Activo                             │ ┃
┃                        ┃   ┃ └──────────────────────────────────────┘ ┃
┃                        ┃   ┃                                          ┃
┃                        ┃   ┃ ┌──────────────┐  ┌──────────────────┐ ┃
┃                        ┃   ┃ │   Guardar    │  │    Cancelar      │ ┃
┃                        ┃   ┃ └──────────────┘  └──────────────────┘ ┃
┗━━━━━━━━━━━━━━━━━━━━━━━━┛   ┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛
```

---

**Visualización completada** ✅  
**Todas las características mostradas visualmente**  
**Listo para referencia durante desarrollo**
