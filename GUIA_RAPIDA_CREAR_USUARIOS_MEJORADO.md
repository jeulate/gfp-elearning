# 🎯 Guía Rápida - Crear Usuarios con Nueva Interfaz

## 📍 Acceso

**Ruta:** Panel Admin → FairPlay LMS → Usuarios → Crear nuevo usuario

---

## 🖼️ Paso 1: Agregar Fotografía (Opcional pero Recomendado)

### Opción A: Hacer clic en la zona
```
1. Hacer clic en el área naranja con el ícono 📷
2. Seleccionar imagen de tu computadora
3. Esperar a que aparezca el preview
```

### Opción B: Drag & Drop
```
1. Arrastrar imagen desde tu explorador
2. Soltar sobre el área naranja
3. La imagen se previsualiza automáticamente
```

**Requisitos:**
- Formatos: JPG, PNG, GIF, WebP
- Tamaño máximo: 5MB
- Proporción recomendada: Cuadrada (1:1)

---

## 👤 Paso 2: Datos Personales

**Sección:** DATOS PERSONALES (lado derecho)

```
Nombre *           │ Ej: Juan
Apellido *         │ Ej: Pérez García
```

**Requerido:** Sí, ambos campos son obligatorios

---

## 🔐 Paso 3: Credenciales de Acceso

**Sección:** CREDENCIALES DE ACCESO

```
Nombre de usuario *    │ Ej: jperez (sin espacios)
Correo electrónico *   │ Ej: juan@empresa.com
Contraseña *           │ Ej: ••••••••• (mínimo 6 caracteres)
```

**Requerido:** Sí, los tres campos son obligatorios

**Tips:**
- Usuario debe ser único (no pueden haber dos iguales)
- Email debe ser válido y único
- Contraseña será enviada al usuario

---

## 🏢 Paso 4: Estructura Organizacional

**Sección:** ESTRUCTURA ORGANIZACIONAL (Opcional)

```
Ciudad          │ Seleccionar del dropdown
Canal / Franquicia │ Seleccionar del dropdown
─────────────────────────────────────────
Sucursal        │ Seleccionar del dropdown
Cargo           │ Seleccionar del dropdown
```

**Nota:** Estos campos permiten organizar al usuario en la estructura jerárquica.

**Opciones disponibles:**
- Si está vacío: "— Sin asignar —"
- Seleccionar de la lista desplegable
- El usuario puede tener solo una opción por categoría

---

## 👥 Paso 5: Tipo de Usuario

**Sección:** TIPO DE USUARIO (Requerido)

```
Grid 2x2:
┌─────────────────────┐
│ ☐ Alumno    ☐ Tutor │
│ ☐ Instructor ☐ Admin│
└─────────────────────┘
```

**Requerido:** Sí, debe seleccionar al menos uno

**Qué es cada rol:**

| Rol | Descripción |
|-----|-------------|
| Alumno | Estudiante, puede acceder a cursos |
| Tutor | Puede crear cursos y tutorizar |
| Instructor | Instructor de MasterStudy |
| Admin | Administrador con todos los permisos |

**Puedes seleccionar múltiples roles** (ej: Alumno + Tutor)

---

## ✅ Paso 6: Estado del Usuario

**Sección:** Parte final del formulario

```
✓ Activo
```

**Opciones:**
- ✓ Activo (por defecto) - Usuario puede acceder
- ☐ Inactivo - Usuario no puede acceder

---

## 💾 Paso 7: Guardar

**Botones en la parte inferior:**

```
┌──────────────┬──────────────┐
│   Guardar    │   Cancelar   │
│  (Azul)      │   (Gris)     │
└──────────────┴──────────────┘
```

### Al hacer clic en "Guardar"

✅ Se validan todos los campos requeridos  
✅ Se crea el usuario en la base de datos  
✅ Se guarda la fotografía en Media Library  
✅ Se asignan los roles y estructura  
✅ Se redirige a la lista de usuarios  

**Mensaje de éxito:**
```
Usuario creado correctamente. ID: 123456
```

---

## 📋 Checklist de Uso

Antes de hacer clic en "Guardar", verifica:

- [ ] **Foto:** Subida (opcional pero recomendado)
- [ ] **Nombre:** Completado ✓
- [ ] **Apellido:** Completado ✓
- [ ] **Usuario:** Único y sin espacios ✓
- [ ] **Email:** Válido y único ✓
- [ ] **Contraseña:** Establecida ✓
- [ ] **Ciudad:** Seleccionada o sin asignar
- [ ] **Canal:** Seleccionado o sin asignar
- [ ] **Sucursal:** Seleccionada o sin asignar
- [ ] **Cargo:** Seleccionado o sin asignar
- [ ] **Tipo de Usuario:** Al menos uno seleccionado ✓
- [ ] **Activo:** Marcado (por defecto) ✓

---

## ⚠️ Errores Comunes

### Error: "Datos incompletos"
**Causa:** Falta llenar un campo requerido
**Solución:** Verifica que todos los campos con `*` estén completos

### Error: "El usuario ya existe"
**Causa:** El nombre de usuario ya está en uso
**Solución:** Usa un nombre de usuario diferente

### Error: "Email inválido"
**Causa:** El formato del email no es correcto
**Solución:** Usa formato válido: usuario@dominio.com

### Foto no se guarda
**Causa:** Archivo muy grande (>5MB) o formato no soportado
**Solución:** Usa JPG, PNG o GIF, máximo 5MB

---

## 💡 Tips Útiles

### Nombres de Usuario Recomendados
```
✅ jperez
✅ jpgarcia
✅ maria.lopez
✅ mlopez2024
❌ juan pérez (con espacios)
❌ juan.pérez.garcía (muy largo)
```

### Contraseñas Seguras
```
✅ Mayúsculas: A, B, C...
✅ Minúsculas: a, b, c...
✅ Números: 1, 2, 3...
✅ Símbolos: !@#$%^&*

Ejemplos:
✅ Juan2024! (buena)
✅ Adidas123 (buena)
❌ password (débil)
❌ 123456 (muy débil)
```

### Fotografías Recomendadas
```
✅ Foto profesional
✅ Fondo neutral o blanco
✅ Rostro centrado
✅ Buena iluminación
✅ Resolución mínima: 200x200px
✅ Máximo: 5MB

Formatos recomendados:
✅ JPG (mejor para fotos)
✅ PNG (con transparencia)
✅ WebP (moderno)
```

---

## 🔄 Después de Crear el Usuario

### 1. **Email de Bienvenida (Opcional)**
El usuario recibe un correo automático con:
- Usuario
- Contraseña
- Link de acceso

### 2. **Asignación de Cursos**
- Ir a sección "Cursos"
- Asignar cursos al nuevo usuario
- Según su estructura y permisos

### 3. **Monitoreo**
- Ver progreso en "Avances"
- Generar reportes
- Comunicarse con el usuario

---

## 📞 Soporte

**Problemas con la interfaz:**
- Revisar navegador (Chrome, Firefox, Edge recomendado)
- Limpiar cache del navegador (Ctrl+Shift+Del)
- Contactar con administrador técnico

**Problemas con datos:**
- Verificar que los valores existan en "Estructuras"
- Asegurar permisos de administrador

---

## 🎓 Ejemplo Completo

### Crear usuario: María López Sánchez

**Paso 1 - Foto:**
```
Subir: maria-lopez.jpg ✓
```

**Paso 2 - Datos Personales:**
```
Nombre: María
Apellido: López Sánchez
```

**Paso 3 - Credenciales:**
```
Usuario: mlopez
Email: maria.lopez@empresa.com
Contraseña: MariaSanta2024!
```

**Paso 4 - Estructura:**
```
Ciudad: Santa Cruz
Canal: Adidas
Sucursal: Adidas Ventura
Cargo: Asesor
```

**Paso 5 - Tipo de Usuario:**
```
☑ Alumno
☐ Tutor
☐ Instructor
☐ Admin
```

**Paso 6 - Estado:**
```
✓ Activo
```

**Resultado:**
```
✅ Usuario creado correctamente. ID: 12345
```

---

**¡Listo!** El usuario está creado y puede comenzar a usar la plataforma.
