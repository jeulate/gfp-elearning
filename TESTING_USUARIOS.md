# 🔧 Guía de Testing y Debugging - Panel de Usuarios

## 🚀 Quick Start - Verificar que Funciona

### ✅ Test 1: Usuarios Se Visualizan (5 min)

**Objetivo**: Verificar que el listado de usuarios funciona

**Pasos**:
1. Ir a **FairPlay LMS → Usuarios**
2. **NO seleccionar** ningún filtro
3. Hacer clic en **"Filtrar"**

**Resultado Esperado**:
```
✅ Tabla con usuarios registrados
✅ Mínimo 3-5 usuarios visibles
✅ Columnas: Usuario, Email, Rol(es), Ciudad, Canal, Sucursal, Cargo, Avance
```

**Si ❌ No aparecen usuarios**:
- Verificar que hay usuarios creados en WordPress (`wp-content/wp-admin/users.php`)
- Revisar logs: `wp-content/debug.log`
- Ejecutar en terminal: `wp user list --field=ID,user_login`

---

### ✅ Test 2: Crear Nuevo Usuario (10 min)

**Objetivo**: Crear usuario desde el panel FairPlay

**Pasos**:
1. Ir a **FairPlay LMS → Usuarios**
2. En "Crear nuevo usuario", llenar:
   ```
   Usuario:      testuser_001
   Email:        testuser@test.com
   Contraseña:   TestPass123!
   Nombre:       Usuario
   Apellido:     Test
   ```
3. Seleccionar **Rol**: AlumnoFairPlay
4. Hacer clic en **"Crear usuario"**

**Resultado Esperado**:
```
✅ Mensaje verde: "Usuario creado correctamente. ID: XXX"
✅ Nuevo usuario aparece en tabla sin filtros
✅ Usuario tiene email testuser@test.com
```

**Si ❌ Error "Usuario ya existe"**:
- Username está duplicado, usar otro: `testuser_002`

**Si ❌ Error "Datos incompletos"**:
- Verificar que Usuario, Email y Contraseña estén llenos
- Email debe tener formato válido (xxx@xxx.xxx)

---

### ✅ Test 3: Filtro de Estructura (8 min)

**Objetivo**: Filtrar usuarios por estructura

**Pasos Previos** (si no hay datos):
1. Crear estructura: **FairPlay LMS → Estructuras**
   - Crear ciudad "Test-Bogota"
   - Crear cargo "Test-Vendedor"
   - Activar ambas

2. Editar usuario: **WordPress → Usuarios → Editar tu usuario**
   - Bajar a "Estructura organizacional FairPlay"
   - Asignar Ciudad: Test-Bogota
   - Asignar Cargo: Test-Vendedor
   - Actualizar

**Pasos del Test**:
1. Ir a **FairPlay LMS → Usuarios**
2. Seleccionar **Ciudad**: Test-Bogota
3. Hacer clic en **"Filtrar"**

**Resultado Esperado**:
```
✅ Tabla muestra solo usuarios de Test-Bogota
✅ Si hay 5 usuarios en total y 2 de Bogotá, muestra 2
✅ Otros filtros sin seleccionar (vacíos) no afectan
```

**Si ❌ Muestra lista vacía**:
- Verificar que el usuario tiene estructura asignada
- Verificar en BD: `SELECT * FROM wp_usermeta WHERE meta_key='fplms_city'`

---

### ✅ Test 4: Filtros Combinados (OR Logic) (8 min)

**Objetivo**: Verificar que los filtros usan OR (no AND)

**Setup Previo**:
- Usuario 1: Bogotá + Vendedor
- Usuario 2: Medellín + Gerente
- Usuario 3: Bogotá + Gerente

**Pasos**:
1. Ir a **FairPlay LMS → Usuarios**
2. Filtro **Ciudad**: Bogotá
3. Filtro **Cargo**: Gerente
4. Hacer clic en **"Filtrar"**

**Resultado Esperado**:
```
✅ Muestra 3 usuarios:
  - Usuario 1 (Bogotá + Vendedor) ✓
  - Usuario 2 (Medellín + Gerente) ✓
  - Usuario 3 (Bogotá + Gerente) ✓

❌ NO muestra solo usuario 3 (sería AND)
```

**Si muestra solo 1 usuario**:
- El filtro está en AND en lugar de OR
- Revisar: `class-fplms-users.php` línea 436
- Debe tener: `'relation' => 'OR'`

---

## 🔍 Verificación de Base de Datos

### Check 1: Usuarios Tienen Estructura

```sql
-- Ver usuarios con estructura asignada
SELECT u.ID, u.user_login, u.user_email, 
       um1.meta_value as ciudad_id,
       um2.meta_value as canal_id
FROM wp_users u
LEFT JOIN wp_usermeta um1 ON u.ID = um1.user_id AND um1.meta_key = 'fplms_city'
LEFT JOIN wp_usermeta um2 ON u.ID = um2.user_id AND um2.meta_key = 'fplms_channel'
LIMIT 10;
```

**Resultado esperado**:
```
ID  | user_login    | user_email          | ciudad_id | canal_id
--- | ------------- | ------------------- | --------- | ---------
1   | admin         | admin@test.com      | 1         | 2
2   | juan.perez    | juan@test.com       | 1         | 2
3   | maria.gomez   | maria@test.com      | 2         | 3
```

### Check 2: Estructura Existe en BD

```sql
-- Ver términos de estructura
SELECT t.term_id, t.name, t.slug, tx.taxonomy
FROM wp_terms t
JOIN wp_term_taxonomy tx ON t.term_id = tx.term_id
WHERE tx.taxonomy IN ('fplms_city', 'fplms_channel', 'fplms_branch', 'fplms_job_role')
ORDER BY tx.taxonomy;
```

**Resultado esperado**:
```
term_id | name        | slug      | taxonomy
------- | ----------- | --------- | ---------------
1       | Bogotá      | bogota    | fplms_city
2       | Medellín    | medellin  | fplms_city
3       | Premium     | premium   | fplms_channel
```

### Check 3: Nuevo Usuario Se Creó

```sql
-- Buscar usuario recién creado
SELECT * FROM wp_users WHERE user_login = 'testuser_001';

-- Ver metadata del usuario
SELECT meta_key, meta_value FROM wp_usermeta 
WHERE user_id = (SELECT ID FROM wp_users WHERE user_login = 'testuser_001');
```

---

## 🧪 Tests Unitarios (Simulación Manual)

### Test Unitario 1: `get_users_filtered_by_structure()` Sin Filtros

**Función**: `$users = $controller->get_users_filtered_by_structure(0, 0, 0, 0);`

**Esperado**: Array con TODOS los usuarios
```php
Array (
    [0] => WP_User Object ( ID => 1, user_login => admin )
    [1] => WP_User Object ( ID => 2, user_login => juan.perez )
    [2] => WP_User Object ( ID => 3, user_login => maria.gomez )
    [4] => WP_User Object ( ID => 4, user_login => carlos.lopez )
)
```

**Verificación**:
```php
// En functions.php o plugin
add_action( 'admin_init', function() {
    if ( current_user_can( 'manage_options' ) && isset( $_GET['debug_users'] ) ) {
        global $wpdb;
        $controller = new FairPlay_LMS_Users_Controller( ... );
        $users = $controller->get_users_filtered_by_structure(0, 0, 0, 0);
        error_log('USUARIOS SIN FILTRO: ' . count($users) . ' found');
        error_log(print_r($users, true));
    }
});
// URL: /wp-admin/admin.php?debug_users=1
```

### Test Unitario 2: `get_users_filtered_by_structure()` Con Filtro City

**Función**: `$users = $controller->get_users_filtered_by_structure(1, 0, 0, 0);`
(Asumiendo city_id = 1 es Bogotá)

**Esperado**: Array solo con usuarios de Bogotá
```php
Array (
    [0] => WP_User Object ( ID => 2, user_login => juan.perez )
    [1] => WP_User Object ( ID => 4, user_login => carlos.lopez )
)
```

---

## 🐛 Debugging - Problemas Comunes

### Problema 1: Página de Usuarios No Carga

**Síntomas**:
- Página blanca
- Error 500

**Pasos de Debug**:
1. Verificar errores PHP: `wp-content/debug.log`
2. Buscar línea con: `call_user_func` o `render_users_page`
3. Revisar sintaxis en `class-fplms-users.php`

**Común**: Syntax error en PHP
```php
❌ if ( ! $city_id || $channel_id || $branch_id || $role_id ) {
✅ if ( $city_id || $channel_id || $branch_id || $role_id ) {
```

### Problema 2: Usuarios No Se Muestran

**Síntomas**:
- Página carga OK pero tabla vacía
- "No se encontraron usuarios"

**Debug**:
```php
// Agregar en render_users_page() después de get_users_filtered_by_structure()
error_log('USUARIOS ENCONTRADOS: ' . count($users));
error_log('FILTROS: city=' . $filter_city . ', channel=' . $filter_channel);
```

**Causas Posibles**:
- Parámetros vacíos no se interpretan como 0
- `meta_query` no se está ejecutando

**Solución**:
```php
// Asegurarse de que el query se ejecuta
var_dump($args); // Ver los argumentos de WP_User_Query
```

### Problema 3: Crear Usuario Falla

**Síntomas**:
- Hacer clic en "Crear usuario" pero nada pasa
- No hay mensaje de error

**Debug**:
1. Abrir DevTools (F12)
2. Network tab → Ejecutar formulario
3. Ver POST request
   - Respuesta debería ser redirect 302
   - Si es 200, formulario no se envía

**Causas**:
- Nonce inválido
- Falta verificar nonce en handle_new_user_form()
- Permisos insuficientes

**Solución**:
```php
error_log('NEW USER FORM POST RECEIVED');
error_log('Action: ' . ($_POST['fplms_new_user_action'] ?? 'NOT SET'));
error_log('Nonce valid: ' . (wp_verify_nonce(...) ? 'YES' : 'NO'));
error_log('User can: ' . (current_user_can(...) ? 'YES' : 'NO'));
```

---

## 📝 Checklist de Validación

Marcar ✓ en cada sección:

### Funcionalidad Básica
- [ ] Usuarios aparecen sin filtros
- [ ] Filtro por ciudad funciona
- [ ] Filtro por canal funciona
- [ ] Filtro por sucursal funciona
- [ ] Filtro por cargo funciona
- [ ] Filtros combinados usan OR
- [ ] Crear usuario funciona
- [ ] Usuario aparece en lista después de crear
- [ ] Nombre de usuario en tabla es clickeable
- [ ] Link abre perfil para editar

### Seguridad
- [ ] Solo admin puede ver "Matriz de privilegios"
- [ ] Solo usuarios con CAP_MANAGE_USERS pueden crear
- [ ] Nonce se valida en crear usuario
- [ ] Permisos se requieren para formulario
- [ ] Contraseña se hashea (no es texto plano)

### Base de Datos
- [ ] Usuarios se crean en wp_users
- [ ] Metadata se guarda en wp_usermeta
- [ ] Roles se asignan en wp_usermeta (wp_xxx_capabilities)
- [ ] Búsqueda SQL retorna resultados correctos

### UI/UX
- [ ] Tabla es responsiva
- [ ] Mensajes de error son claros
- [ ] Mensajes de éxito aparecen
- [ ] Formulario tiene validación HTML (required)
- [ ] Botones tienen estados visuales

---

## 🎬 Video Tutorial Simulado

Si esto fuera video, mostraría:

```
[00:00] Abriendo Panel FairPlay LMS → Usuarios
        ↓
[00:05] Viendo tabla vacía (sin usuarios cargados aún)
        ↓
[00:10] Quitando filtros y haciendo clic "Filtrar"
        ↓
[00:15] ✅ Tabla se llena con 5 usuarios
        ↓
[00:20] Abriendo sección "Crear nuevo usuario"
        ↓
[00:35] Llenando formulario: usuario, email, contraseña, rol
        ↓
[00:50] Haciendo clic "Crear usuario"
        ↓
[00:55] ✅ Mensaje verde: Usuario creado correctamente
        ↓
[01:00] Nuevo usuario aparece en tabla
        ↓
[01:05] Aplicando filtro "Ciudad: Bogotá"
        ↓
[01:10] ✅ Tabla se actualiza mostrando 2 usuarios de Bogotá
        ↓
[01:15] Haciendo clic en nombre de usuario
        ↓
[01:20] ✅ Se abre página de edición en WordPress
        ↓
[01:30] FIN
```

---

## 📊 Métricas de Éxito

| Métrica | Meta | Actual | Estado |
|---------|------|--------|--------|
| Usuarios Visibles | >0 | ? | ⏳ |
| Filtro por Ciudad | Funciona | ? | ⏳ |
| Crear Usuario | Funciona | ? | ⏳ |
| Tiempo Carga | <2s | ? | ⏳ |
| Errores JS | 0 | ? | ⏳ |
| Usuarios en BD | >3 | ? | ⏳ |

---

## 🆘 Contacto / Soporte

Si hay problemas:

1. **Revisar debug.log**: `wp-content/debug.log`
2. **Ver errores de console**: DevTools (F12)
3. **Verificar Base de Datos**: Consultas SQL
4. **Comparar código**: Con backup del archivo original
5. **Activar debug**: `define('WP_DEBUG', true);` en wp-config.php

---

**Última actualización**: Diciembre 2024
**Versión**: 1.0
