# 🎯 Guía de Uso - Nueva Interfaz de Estructuras (Acordeón)

## 📖 Introducción

La interfaz de gestión de estructuras ha sido completamente rediseñada con un formato de acordeón moderno que mejora significativamente la usabilidad y experiencia del usuario.

---

## 🎮 Cómo Usar la Interfaz

### 1️⃣ **Abrir/Cerrar Secciones (Acordeón)**

```
ANTES:
Debías hacer clic en pestañas individuales:
[Ciudades] [Canales] [Sucursales] [Cargos]

AHORA:
Haz clic directamente en la sección que quieres abrir:
```

**Pasos:**
1. En el dashboard, ve a **FairPlay LMS > Estructuras**
2. Haz clic en cualquiera de estos encabezados:
   - 📍 Ciudades
   - 🏪 Canales/Franquicias
   - 🏢 Sucursales
   - 👔 Cargos

3. El acordeón se expandirá mostrando todos los términos
4. Haz clic nuevamente para cerrarlo

**💡 Nota**: Solo puedes tener una sección abierta a la vez para mantener la interfaz limpia.

---

### 2️⃣ **Crear un Nuevo Elemento**

**Para Ciudades:**

1. Abre la sección 📍 **Ciudades**
2. Desplázate hacia abajo hasta la sección "➕ Agregar Nueva Ciudad"
3. Escribe el nombre en el campo de texto
4. Marca si debe estar **Activo** (recomendado)
5. Haz clic en **GUARDAR**
6. Se recargará la página y el nuevo elemento aparecerá en la lista

**Para Canales, Sucursales o Cargos:**

1. Abre la sección correspondiente
2. Desplázate al formulario "➕ Agregar Nuevo..."
3. **Nombre**: Escribe el nombre del elemento
4. **Ciudades Relacionadas**: Haz clic en el campo y selecciona una o más ciudades
   - Aparecerán como "tags" azules debajo del campo
   - Para quitar una ciudad, haz clic en ella
5. Marca si debe estar **Activo**
6. Haz clic en **GUARDAR**

---

### 3️⃣ **Editar un Elemento**

1. Encuentra el elemento que quieres editar en la lista
2. Haz clic en el botón **✏️ (Editar)**
3. Se abrirá un modal con los campos para editar
4. **Nombre**: Modifica el nombre si necesario
5. **Ciudades** (si aplica): Actualiza las ciudades relacionadas
6. Haz clic en **Guardar Cambios**
7. Se cerrará el modal y se guardará

---

### 4️⃣ **Cambiar Estado (Activo/Inactivo)**

Cada elemento tiene un botón **⊙○** (toggle) para activar/desactivar:

1. Busca el elemento en la lista
2. Haz clic en el botón **⊙○**
3. El elemento cambiará de:
   - ✓ Activo → ✗ Inactivo
   - ✗ Inactivo → ✓ Activo
4. Se guardará inmediatamente

**¿Cuándo usar esto?**
- Desactiva temporalmente sin eliminar
- Mantiene historial de datos
- Útil para auditoría

---

### 5️⃣ **Eliminar un Elemento**

⚠️ **CUIDADO**: Esta acción no se puede deshacer

1. Encuentra el elemento que quieres eliminar
2. Haz clic en el botón **🗑️ (Eliminar)**
3. Aparecerá una ventana de confirmación con:
   - El nombre del elemento a eliminar
   - Advertencia: "Esta acción no se puede deshacer"
   - Dos botones: **Cancelar** o **Eliminar Definitivamente**
4. Si haces clic en **Eliminar Definitivamente**:
   - El elemento se borrará completamente
   - Se eliminarán sus relaciones con otras estructuras
   - No se puede recuperar

---

## 🎨 Entendiendo los Colores y Símbolos

### Emojis de Secciones
| Emoji | Sección | Color |
|-------|---------|-------|
| 📍 | Ciudades | Azul |
| 🏪 | Canales/Franquicias | Verde |
| 🏢 | Sucursales | Naranja |
| 👔 | Cargos | Púrpura |

### Indicadores de Estado
| Símbolo | Significado |
|---------|------------|
| ✓ Activo | El elemento está disponible y visible |
| ✗ Inactivo | El elemento está deshabilitado |
| (5) | Número de elementos en esa sección |

### Botones de Acción
| Botón | Acción | Color |
|-------|--------|-------|
| ⊙○ | Activar/Desactivar | Verde |
| ✏️ | Editar | Azul |
| 🗑️ | Eliminar | Rojo |

---

## 📱 Dispositivos Móviles

### En Smartphone (< 480px):
- El acordeón se adapta al ancho de la pantalla
- Los botones se apilan verticalmente debajo de cada elemento
- Toca directamente en el elemento para acciones
- Más fácil de usar con dedos

### En Tablet (480px - 768px):
- Layout semi-responsivo
- Botones horizontales pero con más espacio
- Formularios adaptados a pantalla más pequeña

### En Desktop (> 768px):
- Experiencia completa con todos los elementos visibles
- Botones en línea
- Formularios amplios

---

## ⚡ Tips y Trucos

### 💡 Crear Múltiples Ciudades Rápidamente
1. Abre Ciudades
2. Agrega primera ciudad y guarda
3. Automáticamente la sección se recarga
4. Repite el proceso con la siguiente

### 💡 Vincular Canales a Ciudades
1. Abre la sección de Canales
2. Cuando crees/edites un canal, selecciona **las ciudades** donde operará
3. Esto es importante para la visibilidad de cursos

### 💡 Desactivar en lugar de Eliminar
Si no estás seguro de eliminar un elemento:
1. Usa el botón **⊙○** para desactivarlo primero
2. Verifica que no causa problemas
3. Luego puedes eliminarlo con seguridad

### 💡 Orden de Creación Recomendado
1. Primero: Crear todas las **Ciudades**
2. Segundo: Crear **Canales** y vincularlos a ciudades
3. Tercero: Crear **Sucursales** si aplica
4. Cuarto: Crear **Cargos** de usuario

---

## ❌ Errores Comunes y Soluciones

### ❓ "El campo de Ciudades no aparece"
**Razón**: Solo aparece para Canales, Sucursales y Cargos, no para Ciudades
**Solución**: Asegúrate de estar editando el elemento correcto

### ❓ "No puedo agregar un Canál sin Ciudades"
**Razón**: Un canal debe estar vinculado a al menos una ciudad
**Solución**: Selecciona una o más ciudades en el campo de selección

### ❓ "Eliminé algo por accidente"
**Razón**: No hay papelera de reciclaje, se elimina permanentemente
**Solución**: Pide a un administrador que restaure de backup o recrea el elemento

### ❓ "Los cambios no se guardan"
**Razón**: Posible problema de nonce/sesión
**Solución**: Recarga la página y vuelve a intentar

---

## 🔐 Permisos Requeridos

Para acceder a esta sección necesitas:
- ✅ Ser administrador de WordPress
- ✅ Tener capacidad `fplms_manage_structures`
- ✅ Estar autenticado en el sitio

Si no ves la opción "Estructuras", contacta con el administrador.

---

## 📊 Impacto en el Sistema

### Cuando creas/editas estructuras:
- Actualiza la disponibilidad de cursos en el frontend
- Cambia la visibilidad según ciudades/canales
- Afecta los reportes de usuarios asignados

### Cuando desactivas:
- El elemento no aparece en selects nuevos
- Usuarios existentes mantienen su asignación
- Se puede reactivar sin perder datos

### Cuando eliminas:
- Se borra completamente del sistema
- Usuarios vinculados pierden esa asignación
- No se puede deshacer

---

## 🎓 Ejemplos Prácticos

### Ejemplo 1: Agregar Nueva Sucursal

```
1. Dashboard > FairPlay LMS > Estructuras
2. Clic en "🏢 Sucursales" para expandir
3. Desplázate al formulario "➕ Agregar Nueva Sucursal"
4. Nombre: "Sucursal Centro"
5. Ciudades Relacionadas: Haz clic y selecciona "Madrid"
6. Marca "Activo"
7. Clic en "GUARDAR"
✅ Sucursal creada y vinculada a Madrid
```

### Ejemplo 2: Cambiar de Inactivo a Activo

```
1. Abre la sección donde está el elemento
2. Busca el elemento (tendrá un "✗ Inactivo")
3. Haz clic en el botón "⊙○"
4. Verás que cambió a "✓ Activo"
✅ El elemento ahora es visible en el sistema
```

### Ejemplo 3: Actualizar Vinculación de Ciudades

```
1. En Canales, busca "Canal Premium"
2. Haz clic en "✏️ Editar"
3. En el modal, haz clic en "Ciudades Relacionadas"
4. Desselecciona "Barcelona" y selecciona "Valencia"
5. Haz clic en "Guardar Cambios"
✅ El canal ahora solo está en Madrid y Valencia
```

---

## 📞 Soporte

Si encuentras problemas:

1. **Recarga la página** (Ctrl+F5 para limpiar caché)
2. **Verifica tu conexión** a internet
3. **Prueba en navegador diferente**
4. **Contacta al administrador** si persiste

**Información útil para reportar:**
- Navegador y versión
- Qué elemento estabas editando
- Qué paso exacto falló
- Mensaje de error (si hay)

---

## 🚀 Novedades de Esta Versión

✨ **Nuevo Diseño**
- Acordeón en lugar de pestañas
- Mejor organización visual

✨ **Nueva Funcionalidad de Eliminación**
- Botón 🗑️ para borrar elementos
- Modal de confirmación para seguridad
- Limpieza automática de relaciones

✨ **Mejor Responsividad**
- Funciona perfecto en móviles
- Adaptable a cualquier tamaño
- Experiencia mejorada en tablet

✨ **UX Mejorada**
- Colores para identificar secciones
- Iconos intuitivos
- Animaciones suaves
- Feedback visual claro

---

**¿Preguntas?** Consulta la sección de [Errores Comunes](#errores-comunes-y-soluciones) o contacta al administrador.

**Última actualización**: 2024  
**Versión**: 1.0
