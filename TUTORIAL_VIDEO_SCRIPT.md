# 🎬 Video Tutorial Script - Frontend Mejorado de Estructuras

## Duración Total: ~5 minutos

---

## 📺 Escena 1: Introducción (0:00 - 0:30)

**Narración**:
> Hola, bienvenido al tutorial de las mejoras en el sistema de estructuras del FairPlay LMS. 
> En este video te mostraremos las nuevas funcionalidades que harán tu trabajo más eficiente.
> Las mejoras incluyen: visualización de ciudades en la tabla, edición inline sin recargas, 
> y soporte para estructuras duplicadas en diferentes ciudades.

**Visual**:
- Pantalla de inicio del plugin FairPlay LMS
- Zoom a menú "Estructuras"
- Transición suave

---

## 📺 Escena 2: Tabla Mejorada (0:30 - 1:15)

**Narración**:
> Primero, observa cómo la tabla ahora muestra una nueva columna: "Ciudad".
> Esto te permite identificar rápidamente a qué ciudad pertenece cada estructura.
> Como ves, tenemos:
> - Canal A asignado a Bogotá
> - Canal B asignado a Medellín
> - Sucursal X sin asignar ciudad aún

**Visual**:
- Pantalla: FairPlay LMS → Estructuras → Canales
- Mostrar tabla con columnas: Nombre | Ciudad | Activo | Acciones
- Señalar con cursor cada elemento
- Zoom a columna "Ciudad"
- Mostrar caso "Sin asignar" en itálica

**Acciones**:
1. Haz clic en pestaña "Canales / Franquicias"
2. Señala cada fila
3. Muestra el contenido de la columna Ciudad

---

## 📺 Escena 3: Abrir Modal de Edición (1:15 - 2:15)

**Narración**:
> Ahora, vamos a editar una estructura usando la nueva interfaz modal.
> Observa cómo se abre una ventana elegante en el centro de la pantalla
> sin necesidad de recargar la página. Esto es mucho más rápido que antes.

**Visual**:
- Mostrar tabla nuevamente
- Usuario hace clic en botón "Editar" de una fila
- Modal aparece con animación suave
- Zoom a los campos del modal
- Mostrar: Campo "Nombre" pre-relleno, Campo "Ciudad" pre-relleno

**Acciones**:
1. Haz clic en botón "Editar" de Canal A
2. Modal se abre
3. Muestra los campos pre-rellenos
4. Señala el campo Nombre: "Canal A"
5. Señala el campo Ciudad: "Bogotá"
6. Muestra los botones "Cancelar" y "Guardar Cambios"

---

## 📺 Escena 4: Editar Nombre (2:15 - 3:00)

**Narración**:
> Vamos a cambiar el nombre del canal. Haremos clic en el campo de nombre y escribiremos uno nuevo.
> Como ves, es un proceso muy simple y rápido.

**Visual**:
- Mostrar cursor en campo Nombre
- Borrar "Canal A"
- Escribir "Canal Premium"
- Mostrar que el campo ahora dice "Canal Premium"
- Zoom al botón "Guardar Cambios"

**Acciones**:
1. Haz triple clic en el campo Nombre para seleccionar todo
2. Escribe: "Canal Premium"
3. Muestra el texto nuevo
4. Haz clic en "Guardar Cambios"

---

## 📺 Escena 5: Guardar Cambios y Resultado (3:00 - 3:45)

**Narración**:
> Hemos hecho clic en "Guardar Cambios". Observa cómo la página se recarga 
> y los cambios se reflejan inmediatamente en la tabla.
> El modal se cierra automáticamente y volvemos a la vista de la tabla.

**Visual**:
- Página se recarga
- Modal se cierra
- Tabla aparece nuevamente
- Mostrar la fila editada ahora dice "Canal Premium | Bogotá | Sí | [↓] [✎]"
- Zoom a la fila modificada

**Acciones**:
1. Espera a que la página recargue
2. Señala la fila modificada
3. Muestra que el nombre cambió a "Canal Premium"
4. Muestra que la ciudad sigue siendo "Bogotá"

---

## 📺 Escena 6: Editar Ciudad (3:45 - 4:45)

**Narración**:
> También podemos cambiar la ciudad relacionada con una estructura.
> Vamos a hacer clic en "Editar" nuevamente, pero esta vez cambiaremos la ciudad.

**Visual**:
- Mostrar tabla nuevamente
- Hacer clic en "Editar" de la misma estructura o diferente
- Modal se abre
- Mostrar campo "Ciudad"
- Hacer clic en el dropdown de ciudad
- Seleccionar ciudad diferente
- Mostrar que cambió

**Acciones**:
1. Haz clic en "Editar" de una estructura
2. Modal se abre
3. Señala el campo "Ciudad"
4. Haz clic en el dropdown
5. Selecciona "Medellín"
6. Muestra que el dropdown ahora muestra "Medellín"
7. Haz clic en "Guardar Cambios"
8. Página recarga
9. La estructura ahora muestra "Medellín" en la columna Ciudad

---

## 📺 Escena 7: Casos de Uso Especiales (4:45 - 5:00)

**Narración**:
> Un caso especial importante: puedes tener el mismo nombre de estructura 
> en diferentes ciudades como elementos independientes.
> Por ejemplo, "Canal Premium" en Bogotá y "Canal Premium" en Medellín
> serán filas completamente separadas en la tabla, cada una con su propia edición.

**Visual**:
- Mostrar tabla con ejemplo:
  ```
  Canal Premium | Bogotá   | Sí
  Canal Premium | Medellín | Sí
  ```
- Señalar ambas filas
- Señalar que son diferentes elementos
- Muestra que cada uno tiene su botón "Editar" independiente

**Acciones**:
1. Navega a la tabla
2. Muestra dos filas con mismo nombre pero diferente ciudad
3. Señala ambas
4. Haz clic en "Editar" de la primera
5. Muestra que solo la primera se modifica
6. Cierra modal
7. Haz clic en "Editar" de la segunda
8. Muestra que la segunda está intacta

---

## 📺 Escena 8: Cerrar Modal (Opcional - 5:00+)

**Narración**:
> También puedes cerrar el modal de varias formas:
> 1. Haciendo clic en el botón "Cancelar"
> 2. Haciendo clic fuera del modal, en el área oscura
> 3. Los cambios NO se guardan si no haces clic "Guardar Cambios"

**Visual**:
- Mostrar modal abierto
- Hacer clic en botón "Cancelar"
- Modal se cierra
- Tabla aparece sin cambios

**Acciones**:
1. Abre modal nuevamente
2. Cambia algún valor (ejemplo: nombre)
3. Haz clic en "Cancelar"
4. Modal se cierra
5. Muestra que la tabla sigue igual (sin cambios)

---

## 🎥 Notas Técnicas de Grabación

### Resolución Recomendada
- 1920x1080 (Full HD)
- O 1280x720 (HD)

### Velocidad de Reproduc
- Mantén velocidad normal (1x)
- Para acciones rápidas, ralentiza ligeramente

### Zoom
- Zoom en elementos importantes (3-4 veces)
- Vuelve a zoom normal después

### Cursor
- Señala elementos con cursor
- Usa herramienta de resaltado si está disponible

### Audio
- Habla claro y pausado
- Pausa 1-2 segundos después de cada acción
- Espera a que las acciones terminen antes de continuar

### Colores
- Fondo del modal: Blanco
- Overlay: Gris oscuro semi-transparente
- Botones: Azul (estándar WordPress)

---

## 📝 Guión Alternativo (Más Corto - 3 minutos)

Si tienes poco tiempo, puedes usar este guión condensado:

### Introducción (0:00 - 0:15)
Muestra solo: Título + Menú Estructuras

### Tabla (0:15 - 1:00)
Muestra: Tabla con nueva columna Ciudad

### Modal (1:00 - 2:00)
Muestra: Editar nombre y ciudad

### Resultado (2:00 - 3:00)
Muestra: Cambios guardados en tabla

### Cierre (3:00 - 3:00)
"Con estas mejoras, ahora es más fácil gestionar tus estructuras. ¡Pruébalo!"

---

## 🎨 Elementos Visuales

### Transiciones Recomendadas
1. Entre escenas: Fade In/Out (0.5 segundos)
2. Entre acciones: Cut directo
3. Modal abiéndose: Zoom + Fade In

### Gráficos/Overlays
- Flechas para señalar elementos
- Círculos para resaltar
- Texto emergente con nombres de botones

### Música/Sonido
- Background: Música ambiental suave
- Transiciones: Sonido de "whoosh"
- Clics del botón: Sonido suave
- Final: Sonido de "ding" o "tada"

---

## 🎬 Estructura Final

```
[INTRO - 30 seg]
    ↓
[TABLA - 45 seg]
    ↓
[ABRIR MODAL - 60 seg]
    ↓
[EDITAR NOMBRE - 45 seg]
    ↓
[GUARDAR Y RESULTADO - 45 seg]
    ↓
[EDITAR CIUDAD - 60 seg]
    ↓
[CASOS ESPECIALES - 15 seg]
    ↓
[CIERRE - 10 seg]
```

**Tiempo Total: ~5 minutos**

---

## 📊 Checklist de Grabación

- [ ] Resolución correcta (1920x1080)
- [ ] Audio claro y sin ruido
- [ ] Velocidad de locución adecuada
- [ ] Zoom legible en elementos
- [ ] Acciones pausadas (no muy rápidas)
- [ ] Incluye todos los casos de uso
- [ ] Transiciones suaves
- [ ] Cierre claro

---

## 🔊 Versión Solo Audio (Para Podcast/Tutorial de Voz)

Si solo quieres grabar audio:

> "Hola, hoy te muestro cómo usar el nuevo sistema de edición de estructuras en FairPlay LMS.
> 
> Primero, notarás que en la tabla ahora hay una columna que muestra la ciudad de cada estructura.
> Esto es mucho más visible que antes.
> 
> Segundo, puedes editar cualquier estructura sin recargar la página. Solo haz clic en 'Editar'
> y aparecerá una ventana donde puedes cambiar el nombre y la ciudad.
> 
> Tercero, puedes tener el mismo nombre de estructura en diferentes ciudades.
> Por ejemplo, 'Canal Premium' en Bogotá y 'Canal Premium' en Medellín serán elementos separados.
> 
> Estos cambios hacen que la gestión de estructuras sea mucho más rápida y eficiente.
> ¡Gracias por usar FairPlay LMS!"

**Duración**: ~1 minuto

---

## 📱 Variante para Redes Sociales (30 segundos)

```
[0-5 seg] Mostrar tabla vieja sin ciudad
[5-10 seg] Corte a tabla nueva con ciudad
[10-15 seg] Hacer clic en Editar
[15-20 seg] Modal se abre
[20-25 seg] Editar y guardar
[25-30 seg] Mostrar resultado + "¡Nueva función disponible! 🚀"
```

---

Fin del script.
