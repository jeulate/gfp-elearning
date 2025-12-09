# 🎯 Guía Rápida - Panel de Usuarios FairPlay LMS

## 📍 Acceso

**Ruta**: WordPress Admin → **FairPlay LMS** → **Usuarios**

---

## 🔑 Requisitos de Acceso

- ✅ Rol: **Administrador** o permisos `fplms_manage_users`
- ✅ Capacidad: `CAP_MANAGE_USERS` de FairPlay

---

## 📊 Sección 1: Matriz de Privilegios

### ¿Qué es?
Tabla que muestra qué **roles** pueden hacer qué **acciones**.

### Roles Disponibles:
- **Alumno FairPlay** (`fplms_student`)
- **Tutor FairPlay** (`fplms_tutor`)
- **Profesor MasterStudy** (`stm_lms_instructor`)
- **Administrador** (`administrator`)

### Acciones/Capacidades:
- Gestionar estructuras
- Gestionar usuarios
- Gestionar cursos
- Ver informes
- Ver avances
- Ver calendario

### ¿Cómo Usar?
1. **Solo Admin** puede editar esta matriz
2. Marcar/desmarcar checkboxes
3. Hacer clic en **"Guardar matriz de privilegios"**
4. Cambios aplicados inmediatamente a roles WordPress

**Ejemplo**:
```
TutorFairPlay:
  ✓ Gestionar usuarios
  ✓ Ver informes
  ✓ Ver avances
  ✗ Gestionar estructuras
```

---

## ➕ Sección 2: Crear Nuevo Usuario

### Campos Requeridos (*)

| Campo | Descripción | Ejemplo |
|-------|-------------|---------|
| **Usuario*** | Login único | `juan.perez` |
| **Email*** | Correo válido | `juan@empresa.com` |
| **Contraseña*** | Mínimo 8 caracteres | `MiPass123!` |

### Campos Opcionales

| Campo | Descripción |
|-------|-------------|
| **Nombre** | Nombre de pila |
| **Apellido** | Apellido |

### Asignaciones (Estructura)

| Campo | Descripción | Ejemplo |
|-------|-------------|---------|
| **Roles** | Múltiple selección | TutorFairPlay, AlumnoFairPlay |
| **Ciudad** | Dropdown de ciudades | Bogotá |
| **Canal** | Canal/Franquicia | Premium |
| **Sucursal** | Ubicación | Centro |
| **Cargo** | Posición laboral | Gerente |

### Paso a Paso: Crear Tutor

1. Ir a **FairPlay LMS → Usuarios**
2. En "Crear nuevo usuario", llenar:
   ```
   Usuario:      carlos.gomez
   Email:        carlos@empresa.com
   Contraseña:   SecurePass2024!
   Nombre:       Carlos
   Apellido:     Gómez
   Roles:        ✓ TutorFairPlay
   Ciudad:       Bogotá
   Canal:        Premium
   Sucursal:     Centro
   Cargo:        Gerente de Ventas
   ```
3. Hacer clic en **"Crear usuario"**
4. Ver mensaje: ✅ "Usuario creado correctamente. ID: 123"

### Notas Importantes
- ⚠️ El usuario NO puede dejarse en blanco o repetido
- ⚠️ El email DEBE ser válido y único
- ✅ Puede asignar múltiples roles
- ✅ Puede dejar estructuras sin asignar
- ✅ El usuario podrá cambiar su contraseña después

---

## 🔍 Sección 3: Ver Usuarios por Estructura

### Opción A: Ver TODOS los Usuarios

1. **NO seleccionar** ningún filtro
2. Hacer clic en **"Filtrar"**
3. Se muestran todos los usuarios registrados

**Resultado**:
```
┌────────┬─────────────┬──────────┬────────┬────────┬──────────┬────────┬────────┐
│Usuario │ Email       │ Rol(es)  │Ciudad  │ Canal  │Sucursal  │ Cargo  │ Avance │
├────────┼─────────────┼──────────┼────────┼────────┼──────────┼────────┼────────┤
│Juan    │juan@emp.com │ Alumno   │Bogotá  │Premium │ Centro   │Vendedor│ 45%    │
│María   │maria@emp.co │ Tutor    │Bogotá  │Standard│ Norte    │Gerente │ 80%    │
│Carlos  │carlos@emp.c │ Alumno   │Medellín│Premium │ Sur      │Otro    │ 10%    │
└────────┴─────────────┴──────────┴────────┴────────┴──────────┴────────┴────────┘
```

### Opción B: Filtrar por Ciudad

1. En filtro "**Ciudad**", seleccionar **"Bogotá"**
2. Hacer clic en **"Filtrar"**
3. Solo muestra usuarios de Bogotá

**Resultado**:
```
Mostrando solo usuarios donde Ciudad = Bogotá
├─ Juan (Bogotá, Premium, Centro)
└─ María (Bogotá, Standard, Norte)
```

### Opción C: Filtrar por Cargo

1. En filtro "**Cargo**", seleccionar **"Gerente"**
2. Hacer clic en **"Filtrar"**
3. Solo muestra usuarios con cargo Gerente

**Resultado**:
```
Mostrando solo usuarios donde Cargo = Gerente
├─ María (Bogotá)
├─ Pedro (Medellín)
└─ Ana (Cali)
```

### Opción D: Filtros Combinados (OR Logic)

1. **Ciudad**: Bogotá
2. **Cargo**: Gerente
3. Hacer clic en **"Filtrar"**

**Resultado**: Usuarios que cumplan CUALQUIERA de estas:
- Usuarios de Bogotá (sin importar cargo)
- Usuarios con cargo Gerente (sin importar ciudad)

---

## ✏️ Editar Usuario

### Desde el Panel de Usuarios

1. En la tabla, hacer clic en el **nombre del usuario**
2. Se abre la página de edición de WordPress
3. Bajar a sección **"Estructura organizacional FairPlay"**
4. Cambiar:
   - Ciudad
   - Canal
   - Sucursal
   - Cargo
5. Hacer clic en **"Actualizar"**

### Desde WordPress → Usuarios

También puede editar directamente en **WordPress Admin → Usuarios → Editar**

---

## 📱 Estructura del Formulario de Crear Usuario

```
┌─ CREAR NUEVO USUARIO ─────────────────────┐
│                                            │
│ Datos de Login (Requeridos)               │
│  └─ Usuario: _________________            │
│  └─ Email:   _________________            │
│  └─ Contraseña: _____________             │
│                                            │
│ Datos Personales (Opcionales)             │
│  └─ Nombre:  _________________            │
│  └─ Apellido: _________________            │
│                                            │
│ Rol(es) *                                 │
│  ☐ Alumno FairPlay                        │
│  ☑ Tutor FairPlay                         │
│  ☐ Profesor MasterStudy                   │
│  ☐ Administrador                          │
│                                            │
│ Estructura Organizacional                 │
│  └─ Ciudad: [Seleccionar▼]                │
│  └─ Canal:  [Seleccionar▼]                │
│  └─ Sucursal: [Seleccionar▼]              │
│  └─ Cargo:  [Seleccionar▼]                │
│                                            │
│         [Crear usuario]                   │
│                                            │
└────────────────────────────────────────────┘
```

---

## 🎓 Casos de Uso Comunes

### Caso 1: Onboarding de Nuevo Tutor
```
1. Crear usuario: carlos_martinez
2. Asignar roles: TutorFairPlay
3. Asignar ciudad: Bogotá
4. Asignar canal: Premium
5. Los alumnos de Bogotá+Premium verán a Carlos como tutor
```

### Caso 2: Crear Alumno de Estructura Específica
```
1. Crear usuario: estudiante_001
2. Asignar roles: AlumnoFairPlay
3. Asignar ciudad: Medellín
4. Asignar cargo: Vendedor
5. Solo ve cursos para Vendedores de Medellín
```

### Caso 3: Ver Equipos por Sucursal
```
1. Filtro "Sucursal": Centro
2. Ver todos los usuarios del Centro
3. Identificar tutores y alumnos
```

---

## ❌ Errores Comunes y Soluciones

| Error | Causa | Solución |
|-------|-------|----------|
| "No se encontraron usuarios" | No hay filtros apropiados | Dejar filtros vacíos para ver todos |
| "Usuario ya existe" | Username o email duplicado | Cambiar a valores únicos |
| Campos requeridos en blanco | Falta completar * | Llenar Usuario, Email, Contraseña |
| No aparece usuario creado | Formulario no se envió | Verificar que no haya errores JS en consola |
| "No tienes permisos" | Rol insuficiente | Debe tener CAP_MANAGE_USERS |

---

## 🔐 Detalles de Seguridad

- ✅ Todas las contraseñas se hashean (no se guardan en texto plano)
- ✅ Nonce validation en cada formulario
- ✅ Solo usuarios con permisos pueden crear/editar
- ✅ Los datos se sanitizan antes de guardar
- ✅ Redirecciones son seguras (wp_safe_redirect)

---

## 📞 Preguntas Frecuentes

**P: ¿Puedo crear múltiples roles a un usuario?**
A: Sí, marca varios checkboxes en la sección "Roles"

**P: ¿Qué pasa si no asigno estructura?**
A: El usuario verá TODOS los cursos sin restricción

**P: ¿Puedo editar un usuario después de crearlo?**
A: Sí, desde WordPress → Usuarios o haciendo clic en el nombre en la tabla

**P: ¿Cuál es el máximo de usuarios que puedo crear?**
A: Sin límite técnico, depende de tu servidor

**P: ¿Los usuarios pueden cambiar su contraseña?**
A: Sí, desde su perfil en WordPress

---

## 📊 Vista Rápida de Permisos por Rol

| Capacidad | Alumno | Tutor | Profesor | Admin |
|-----------|--------|-------|----------|-------|
| Ver cursos | ✓ | ✓ | ✓ | ✓ |
| Ver avances | ✓ | ✓ | ✓ | ✓ |
| Gestionar usuarios | ✗ | ✗ | ✗ | ✓ |
| Gestionar cursos | ✗ | ✓ | ✓ | ✓ |
| Ver informes | ✗ | ✓ | ✗ | ✓ |
| Gestionar estructuras | ✗ | ✗ | ✗ | ✓ |

---

**Última actualización**: Diciembre 2024
**Versión**: 1.0
**Estado**: Documentación Oficial
