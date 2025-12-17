# Guía de Uso - Sistema Jerárquico de Estructuras

## 🎯 Introducción

El nuevo sistema permite que cada **ciudad** tenga sus propios **canales**, **sucursales** y **cargos**. Esto evita conflictos cuando tienes el mismo nombre en diferentes ciudades.

Ejemplo:
- Bogotá tiene "Canal A" → Sucursal Calle 5
- Medellín tiene "Canal A" → Sucursal Centro Comercial
- Ambos coexisten sin problemas ✓

---

## 📋 Paso 1: Crear Ciudades

### Instrucciones

1. Ve a **FairPlay LMS → Estructuras**
2. Estás en la pestaña **"Ciudades"** por defecto
3. En la sección "Nuevo registro":
   - **Nombre:** Escribe el nombre de la ciudad (ej: "Bogotá")
   - **Activo:** Marca si debe estar visible
   - Haz clic en **"Guardar"**

### Ciudades Recomendadas

```
✓ Bogotá
✓ Medellín
✓ Cali
✓ Barranquilla
✓ (Agrega todas tus ciudades)
```

**Resultado esperado:**
- Tabla actualizada con la nueva ciudad
- Status: "Sí" en columna Activo

---

## 📋 Paso 2: Crear Canales / Franquicias

### Instrucciones

1. Ve a **FairPlay LMS → Estructuras**
2. Haz clic en pestaña **"Canales / Franquicias"**
3. En la sección "Nuevo registro":
   - **Nombre:** (ej: "Canal A", "Franquicia Premium")
   - **Ciudad relacionada:** ⭐ **NUEVO** - Selecciona la ciudad (REQUIRED)
   - **Activo:** Marca si debe estar visible
   - Haz clic en **"Guardar"**

### Ejemplo

```
Crear en Bogotá:
├─ Canal A (Bogotá)
├─ Canal B (Bogotá)
├─ Franquicia Especial (Bogotá)

Crear en Medellín:
├─ Canal A (Medellín)          ← ¡Mismo nombre! Pero diferente ciudad
├─ Canal B (Medellín)
└─ Franquicia Especial (Medellín)
```

⚠️ **Importante:** Si no seleccionas ciudad, el formulario no se envía. Esto es por diseño.

---

## 📋 Paso 3: Crear Sucursales

### Instrucciones

1. Ve a **FairPlay LMS → Estructuras**
2. Haz clic en pestaña **"Sucursales"**
3. En la sección "Nuevo registro":
   - **Nombre:** (ej: "Sucursal Centro", "Sucursal Sur")
   - **Ciudad relacionada:** Selecciona la ciudad ⭐
   - **Activo:** Marca si debe estar visible
   - Haz clic en **"Guardar"**

### Ejemplo

```
Bogotá:
├─ Sucursal Centro (Bogotá)
├─ Sucursal Sur (Bogotá)
└─ Sucursal Norte (Bogotá)

Medellín:
├─ Sucursal Centro Comercial (Medellín)
├─ Sucursal Sur (Medellín)        ← ¡Mismo nombre, diferente ciudad!
└─ Sucursal Sabaneta (Medellín)
```

---

## 📋 Paso 4: Crear Cargos

### Instrucciones

1. Ve a **FairPlay LMS → Estructuras**
2. Haz clic en pestaña **"Cargos"**
3. En la sección "Nuevo registro":
   - **Nombre:** (ej: "Gerente", "Vendedor", "Operario")
   - **Ciudad relacionada:** Selecciona la ciudad ⭐
   - **Activo:** Marca si debe estar visible
   - Haz clic en **"Guardar"**

### Ejemplo

```
Bogotá:
├─ Gerente (Bogotá)
├─ Vendedor (Bogotá)
└─ Operario (Bogotá)

Medellín:
├─ Gerente (Medellín)
├─ Asesor (Medellín)             ← Diferente cargo según ciudad
└─ Coordinador (Medellín)
```

---

## 🎓 Paso 5: Asignar Estructuras a Cursos (CON CARGA DINÁMICA)

### ⭐ NUEVA FUNCIONALIDAD

Ahora el sistema carga **dinámicamente** las opciones según la ciudad que selecciones.

### Instrucciones

1. Ve a **FairPlay LMS → Cursos**
2. Busca el curso que quieres asignar estructuras
3. Haz clic en **"Asignar Estructuras"** (o icono correspondiente)
4. En la sección **"Ciudades"**:
   - Marca una o más ciudades

   ⚠️ **Aquí ocurre la magia:**
   - Cuando marcas una ciudad, el sistema carga automáticamente ✨
     - Canales de esa ciudad
     - Sucursales de esa ciudad
     - Cargos de esa ciudad
   - Espera 1-2 segundos a que se actualicen las opciones

5. En las secciones dinámicas (se actualizan automáticamente):
   - **Canales / Franquicias:**
     - Dejar VACÍO = Visible para TODOS los canales de la ciudad
     - Seleccionar específicos = Visible solo para esos
   
   - **Sucursales:**
     - Dejar VACÍO = Visible para TODAS las sucursales de la ciudad
     - Seleccionar específicos = Visible solo para esas
   
   - **Cargos:**
     - Dejar VACÍO = Visible para TODOS los cargos de la ciudad
     - Seleccionar específicos = Visible solo para esos

6. Haz clic en **"Guardar estructuras"**

### Ejemplo de Configuración

**Escenario:** Quiero que el curso "Python Avanzado" sea:
- Visible en Bogotá para TODOS
- Visible en Medellín SOLO para los vendedores

**Pasos:**

```
1. Marcar ☑ Bogotá
   ↓ Se cargan canales/sucursales/cargos de Bogotá
   ✓ Dejar VACÍO (accesible para todos)

2. Marcar ☑ Medellín
   ↓ Se cargan canales/sucursales/cargos de Medellín
   ✓ En sección "Cargos" marcar SOLO "Vendedor"

3. Guardar
```

**Resultado:**
- Usuarios en Bogotá (cualquier rol) → Ven el curso
- Usuarios en Medellín con rol "Vendedor" → Ven el curso
- Otros usuarios en Medellín → NO ven el curso

---

## 🔍 Verificación

### Verificar que Fue Guardado Correctamente

Después de guardar, edita el curso nuevamente:

1. Ve a **FairPlay LMS → Cursos → [Tu Curso] → Asignar Estructuras**
2. Verifica que aparezcan:
   - ✅ La ciudad marcada
   - ✅ Los canales/sucursales/cargos seleccionados mantenidos

### Si Algo Está Mal

**Problema:** No aparecen las opciones dinámicas
- **Solución:** Abre la consola de navegador (F12 → Consola)
- Mira si hay errores de AJAX
- Verifica que la ciudad tenga canales/sucursales creados

**Problema:** Se limpian las opciones al cambiar ciudad
- **Es normal.** El sistema carga las opciones de la nueva ciudad.
- Solo guarda lo que hayas seleccionado en esa ciudad.

**Problema:** Las opciones no se actualizan al seleccionar ciudad
- Espera 1-2 segundos
- Si sigue sin funcionar, recarga la página (F5)
- Contacta soporte si persiste

---

## 📊 Casos de Uso

### Caso 1: Curso Disponible para Una Ciudad Completa

```
Configuración:
├─ Ciudades: Bogotá
├─ Canales: (vacío = todos)
├─ Sucursales: (vacío = todos)
└─ Cargos: (vacío = todos)

Resultado:
✓ Cualquier usuario en Bogotá ve el curso
✓ Usuarios en otras ciudades NO ven el curso
```

### Caso 2: Curso Solo para Gerentes de Una Ciudad

```
Configuración:
├─ Ciudades: Bogotá
├─ Canales: (vacío = todos)
├─ Sucursales: (vacío = todas)
└─ Cargos: ✓ Gerente

Resultado:
✓ Gerentes en Bogotá ven el curso
✗ Vendedores en Bogotá NO ven el curso
✗ Usuarios en otras ciudades NO ven el curso
```

### Caso 3: Curso para Múltiples Ciudades

```
Configuración:
├─ Ciudades: ✓ Bogotá, ✓ Medellín
├─ Canales: (vacío)
├─ Sucursales: (vacío)
└─ Cargos: (vacío)

Resultado:
✓ Cualquier usuario en Bogotá ve el curso
✓ Cualquier usuario en Medellín ve el curso
✗ Usuarios en otras ciudades NO ven el curso
```

### Caso 4: Combinación Compleja

```
Configuración:
├─ Ciudades: ✓ Bogotá, ✓ Medellín
├─ Canales: ✓ Canal A (solo en Bogotá carga)
├─ Sucursales: (vacío)
└─ Cargos: ✓ Gerente (solo en Bogotá), ✓ Vendedor (solo en Medellín)

Resultado:
✓ Gerentes en Bogotá (cualquier sucursal) ven el curso
✓ Vendedores en Medellín (cualquier sucursal) ven el curso
✗ Otros NO ven el curso
```

---

## ⚠️ Cosas Importantes

### Orden de Creación

Crea en este orden:
1. Ciudades (primero)
2. Canales (asignados a ciudades)
3. Sucursales (asignadas a ciudades)
4. Cargos (asignados a ciudades)
5. Después asigna a cursos

### Validación Requerida

- **Ciudad:** REQUERIDA al crear canales/sucursales/cargos
- Si no seleccionas, el formulario no se envía

### Nombres Duplicados Permitidos

✅ PERMITIDO:
```
Bogotá → Canal A
Medellín → Canal A
Cali → Canal A
```

❌ NO PERMITIDO (en la misma ciudad):
```
Bogotá → Canal A
Bogotá → Canal A  ← WordPress lo rechaza
```

### Activar / Desactivar

Si desactivas una estructura en "Acciones", desaparece de:
- Dropdowns al crear cursos
- Opciones dinámicas en AJAX
- Pero se mantiene en BD (puede reactivarse)

---

## 🧪 Testing Rápido

### Test 1: Crear y Cargar (5 min)

```
1. Crear ciudad: Bogotá
2. Crear canal: Canal Test (Bogotá)
3. Ir a asignar estructuras de un curso
4. Marcar Bogotá
5. ✓ Debe aparecer "Canal Test" en las opciones
```

### Test 2: Dinámico (3 min)

```
1. Crear dos ciudades: Bogotá y Medellín
2. Crear canal "Test" en ambas ciudades
3. Ir a asignar estructuras
4. Marcar Bogotá
5. Esperar 1-2 segundos
6. Marcar Medellín
7. ✓ Opciones deben cambiar automáticamente
```

### Test 3: Guardar y Editar (5 min)

```
1. Asignar canal específico a un curso
2. Guardar
3. Editar el curso nuevamente
4. ✓ Los valores deben estar marcados correctamente
```

---

## 📞 Troubleshooting

| Problema | Causa Probable | Solución |
|----------|---|---|
| No aparecen opciones dinámicas | No hay canales creados para esa ciudad | Crea canales/sucursales/cargos en esa ciudad |
| Errores en consola | JavaScript no cargó correctamente | Recarga la página (F5) |
| Las opciones se limpian al cambiar ciudad | Comportamiento normal | Solo carga opciones de la nueva ciudad |
| No puedo crear canal sin ciudad | Validación correcta | Selecciona una ciudad en el formulario |
| Guarda pero luego no aparecen valores | Valores no se guardaron | Verifica en BD o intenta nuevamente |

---

## 📚 Resumen de Cambios

| Elemento | Antes | Ahora |
|----------|-------|--------|
| Crear canal | Solo nombre | Nombre + Ciudad (REQUERIDA) |
| Mismo nombre en diferentes ciudades | ❌ Conflicto | ✅ Permitido |
| Asignar a cursos | Dropdown estático | ✅ Dinámico (AJAX) |
| Actualizar opciones | Recargar página | ✅ Automático |
| UX | Confusa | ✅ Intuitiva |

---

**Versión:** 1.0  
**Última actualización:** Diciembre 2024  
**Estado:** Listo para usar ✅
