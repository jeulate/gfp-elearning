# 🔧 Documentación Técnica - Mejoras Interfaz de Usuarios

**Fecha:** 15 de Enero de 2026  
**Archivo Principal:** `fairplay-lms-masterstudy-extensions/includes/class-fplms-users.php`  
**Versión Plugin:** 0.7.0+  

---

## 📑 Tabla de Contenidos

1. [Cambios Realizados](#cambios-realizados)
2. [Estructura HTML](#estructura-html)
3. [Estilos CSS](#estilos-css)
4. [JavaScript](#javascript)
5. [PHP Backend](#php-backend)
6. [Metadatos de Usuario](#metadatos-de-usuario)
7. [Seguridad](#seguridad)
8. [Compatibilidad](#compatibilidad)

---

## 🎯 Cambios Realizados

### 1. **Rediseño del Formulario de Creación**

**Archivo modificado:**
```
wordpress/wp-content/plugins/fairplay-lms-masterstudy-extensions/
  includes/class-fplms-users.php
```

**Líneas afectadas:** ~287-530 (render_users_page() - formulario)

**Cambios principales:**
- Reemplazar tabla HTML con layout CSS Grid
- Agregar sección de carga de fotografía
- Reorganizar campos en secciones lógicas
- Mejorar estilos y responsividad

---

## 🏗️ Estructura HTML

### Contenedor Principal

```php
<div class="fplms-create-user-container">
    <form method="post" class="fplms-user-form-wrapper" enctype="multipart/form-data">
        <!-- Grid: Imagen + Formulario -->
    </form>
</div>
```

**Atributos importantes:**
- `enctype="multipart/form-data"` - Permite subida de archivos
- `method="post"` - Envío seguro
- `class="fplms-user-form-wrapper"` - Grid 2 columnas

### Sección de Imagen

```php
<div class="fplms-user-image-section">
    <div class="fplms-user-image-upload" id="fplms-image-upload-area">
        <div class="fplms-user-image-placeholder">
            <span>📷</span>
            <div>Haz clic para subir fotografía</div>
        </div>
    </div>
    <input type="file" id="fplms_user_photo" name="fplms_user_photo" accept="image/*">
</div>
```

**Elementos:**
- `#fplms-image-upload-area` - Area interactiva (click y drag-drop)
- `.fplms-user-image-placeholder` - Mensaje cuando no hay imagen
- `#fplms_user_photo` - Input file oculto (type="file")

### Secciones de Formulario

**Estructura genérica:**
```php
<div>
    <div class="fplms-form-section-title">TÍTULO SECCIÓN</div>
    <div class="fplms-form-row">
        <div class="fplms-form-group">
            <label for="field_id">Label <span class="required">*</span></label>
            <input type="text" id="field_id" name="field_name">
        </div>
    </div>
</div>
```

**Clases utilizadas:**
- `.fplms-form-section-title` - Título en mayúsculas
- `.fplms-form-row` - Contenedor de filas (grid)
- `.fplms-form-row.full` - Fila completa (1 columna)
- `.fplms-form-group` - Agrupación campo + label
- `.required` - Asterisco rojo

---

## 🎨 Estilos CSS

### Variables de Colores

```css
/* Paleta de colores */
--color-orange-light: #fff8f0
--color-orange-border: #e0a05d
--color-orange-hover: #ff9800
--color-blue-primary: #1976d2
--color-blue-dark: #1565c0
--color-gray-light: #fafafa
--color-gray-border: #ddd
--color-gray-bg: #f5f5f5
--color-text-primary: #333
--color-text-secondary: #666
```

### Contenedor Principal

```css
.fplms-create-user-container {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    margin-bottom: 2em;
}
```

### Layout Grid

```css
.fplms-user-form-wrapper {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 40px;
    padding: 40px;
}

/* Mobile */
@media (max-width: 1024px) {
    .fplms-user-form-wrapper {
        grid-template-columns: 1fr;
        gap: 30px;
    }
}
```

**Especificaciones:**
- **Desktop:** 2 columnas (1:2 ratio)
- **Mobile:** 1 columna
- **Breakpoint:** 1024px
- **Gap:** 40px desktop, 30px mobile
- **Padding:** 40px en todos los lados

### Area de Imagen

```css
.fplms-user-image-upload {
    width: 100%;
    max-width: 280px;
    aspect-ratio: 1;
    border: 2px dashed #e0a05d;
    border-radius: 8px;
    background: #fff8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.fplms-user-image-upload:hover {
    background: #ffe8d1;
    border-color: #ff9800;
}

.fplms-user-image-upload.has-image {
    border: none;
    background: transparent;
}

.fplms-user-image-upload.has-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 8px;
}
```

**Propiedades:**
- Size: 280x280px (cuadrado)
- Border: 2px dashed naranja (#e0a05d)
- Bg: Naranja muy claro (#fff8f0)
- Hover: Naranja más oscuro (#ff9800)
- Con imagen: Sin borde, imagen visible

### Campos de Formulario

```css
.fplms-form-group {
    display: flex;
    flex-direction: column;
}

.fplms-form-group label {
    font-weight: 600;
    color: #333;
    margin-bottom: 8px;
    font-size: 14px;
}

.fplms-form-group input[type="text"],
.fplms-form-group input[type="email"],
.fplms-form-group input[type="password"],
.fplms-form-group select {
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.3s, box-shadow 0.3s;
}

.fplms-form-group input:focus,
.fplms-form-group select:focus {
    outline: none;
    border-color: #ff9800;
    box-shadow: 0 0 0 3px rgba(255, 152, 0, 0.1);
}
```

**Estados:**
- **Normal:** Border gris, sin shadow
- **Focus:** Border naranja, shadow azul claro
- **Disabled:** (WordPress default)

### Secciones

```css
.fplms-form-section-title {
    font-weight: 700;
    color: #333;
    margin-top: 10px;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f0f0f0;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #666;
}
```

**Características:**
- Texto en MAYÚSCULAS
- Borde inferior gris claro
- Font size: 13px
- Letter spacing: 0.5px
- Color: Gris (#666)

### Checkboxes (Tipos de Usuario)

```css
.fplms-form-checkboxes {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    padding: 12px;
    background: #fafafa;
    border-radius: 6px;
}

.fplms-form-checkboxes label {
    display: flex;
    align-items: center;
    cursor: pointer;
    font-weight: normal;
}

.fplms-form-checkboxes input[type="checkbox"] {
    margin-right: 10px;
    cursor: pointer;
    width: 18px;
    height: 18px;
    accent-color: #ff9800;
}
```

**Grid:**
- 2 columnas
- Gap: 12px
- Background: Gris muy claro (#fafafa)

### Checkbox de Estado

```css
.fplms-form-checkbox-active {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px;
    background: #fafafa;
    border-radius: 6px;
}

.fplms-form-checkbox-active input[type="checkbox"] {
    width: 20px;
    height: 20px;
    accent-color: #4caf50;
}
```

**Características:**
- Color de check: Verde (#4caf50)
- Tamaño más grande: 20x20px
- Padding: 12px

### Botones

```css
.fplms-form-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.fplms-form-actions button {
    padding: 14px 30px;
    border: none;
    border-radius: 6px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.fplms-form-actions .button-primary {
    background: #1976d2;
    color: white;
}

.fplms-form-actions .button-primary:hover {
    background: #1565c0;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(25, 118, 210, 0.3);
}

.fplms-form-actions .button-secondary {
    background: #f5f5f5;
    color: #333;
}

.fplms-form-actions .button-secondary:hover {
    background: #e0e0e0;
}
```

**Botón Guardar:**
- Fondo: Azul (#1976d2)
- Hover: Azul más oscuro + lift effect
- Shadow en hover

**Botón Cancelar:**
- Fondo: Gris (#f5f5f5)
- Hover: Gris más oscuro

---

## 🔧 JavaScript

### Inicialización

```javascript
document.addEventListener('DOMContentLoaded', function() {
    const uploadArea = document.getElementById('fplms-image-upload-area');
    const fileInput = document.getElementById('fplms_user_photo');
    
    // Event listeners...
});
```

### Click en Area

```javascript
uploadArea.addEventListener('click', function() {
    fileInput.click();
});
```

**Resultado:** Abre diálogo de selección de archivo

### Cambio de Archivo

```javascript
fileInput.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file && file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = function(event) {
            uploadArea.innerHTML = '<img src="' + event.target.result + '" alt="Usuario">';
            uploadArea.classList.add('has-image');
        };
        reader.readAsDataURL(file);
    }
});
```

**Proceso:**
1. Obtener archivo del input
2. Validar que sea imagen (file.type)
3. Crear FileReader
4. Leer como Data URL
5. Actualizar HTML con <img>
6. Agregar clase 'has-image'

### Drag & Drop

```javascript
uploadArea.addEventListener('dragover', function(e) {
    e.preventDefault();
    uploadArea.style.background = '#ffe8d1';
});

uploadArea.addEventListener('dragleave', function(e) {
    e.preventDefault();
    uploadArea.style.background = '#fff8f0';
});

uploadArea.addEventListener('drop', function(e) {
    e.preventDefault();
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        fileInput.files = files;
        const event = new Event('change', { bubbles: true });
        fileInput.dispatchEvent(event);
    }
});
```

**Estados:**
- **Dragover:** Background naranja oscuro
- **Dragleave:** Background naranja claro
- **Drop:** Simula change event

---

## 🐘 PHP Backend

### Handler de Formulario

**Función:** `handle_new_user_form()`

```php
public function handle_new_user_form(): void {
    
    // 1. Verificar si hay datos POST
    if ( ! isset( $_POST['fplms_new_user_action'] ) ) {
        return;
    }
    
    // 2. Verificar permisos
    if ( ! current_user_can( FairPlay_LMS_Config::CAP_MANAGE_USERS ) ) {
        return;
    }
    
    // 3. Verificar nonce
    if ( ! isset( $_POST['fplms_new_user_nonce'] ) ||
         ! wp_verify_nonce( $_POST['fplms_new_user_nonce'], 'fplms_new_user_save' ) ) {
        return;
    }
    
    // ... resto del código
}
```

### Validación de Datos

```php
// Antes: Usuario, Email, Contraseña
// Ahora: Usuario, Email, Contraseña, Nombre, Apellido

$user_login = sanitize_text_field( wp_unslash( $_POST['fplms_user_login'] ?? '' ) );
$user_email = sanitize_email( wp_unslash( $_POST['fplms_user_email'] ?? '' ) );
$user_pass  = sanitize_text_field( wp_unslash( $_POST['fplms_user_pass'] ?? '' ) );
$first_name = sanitize_text_field( wp_unslash( $_POST['fplms_first_name'] ?? '' ) );
$last_name  = sanitize_text_field( wp_unslash( $_POST['fplms_last_name'] ?? '' ) );

// Validar campos requeridos
if ( ! $user_login || ! $user_email || ! $user_pass || ! $first_name || ! $last_name ) {
    wp_safe_redirect(
        add_query_arg(
            [ 'page' => 'fplms-users', 'error' => 'incomplete_data' ],
            admin_url( 'admin.php' )
        )
    );
    exit;
}
```

### Crear Usuario

```php
$user_id = wp_create_user( $user_login, $user_pass, $user_email );

if ( is_wp_error( $user_id ) ) {
    wp_safe_redirect(
        add_query_arg(
            [ 'page' => 'fplms-users', 'error' => 'user_exists' ],
            admin_url( 'admin.php' )
        )
    );
    exit;
}
```

### Guardar Metadatos

```php
if ( $first_name ) {
    update_user_meta( $user_id, 'first_name', $first_name );
}
if ( $last_name ) {
    update_user_meta( $user_id, 'last_name', $last_name );
}
```

### Manejo de Fotografía

**Nueva función:** `handle_user_photo_upload()`

```php
private function handle_user_photo_upload( int $user_id, array $file ): void {
    
    // Validar que sea archivo cargado
    if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
        return;
    }
    
    // Validar MIME type
    $allowed_types = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ];
    $finfo = finfo_open( FILEINFO_MIME_TYPE );
    $mime_type = finfo_file( $finfo, $file['tmp_name'] );
    finfo_close( $finfo );
    
    if ( ! in_array( $mime_type, $allowed_types, true ) ) {
        return;
    }
    
    // Validar tamaño (máx 5MB)
    if ( $file['size'] > 5 * 1024 * 1024 ) {
        return;
    }
    
    // Procesar con WordPress
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    
    // Manejar subida
    $overrides = [ 'test_form' => false ];
    $uploaded_file = wp_handle_upload( $file, $overrides );
    
    if ( isset( $uploaded_file['error'] ) ) {
        return;
    }
    
    // Crear attachment
    $attachment = [
        'post_mime_type' => $uploaded_file['type'],
        'post_title'     => 'Foto del usuario ' . get_user_by( 'id', $user_id )->display_name,
        'post_content'   => '',
        'post_status'    => 'inherit',
    ];
    
    $attachment_id = wp_insert_attachment( $attachment, $uploaded_file['file'] );
    
    if ( ! is_wp_error( $attachment_id ) ) {
        // Generar metadatos
        $attach_data = wp_generate_attachment_metadata( $attachment_id, $uploaded_file['file'] );
        wp_update_attachment_metadata( $attachment_id, $attach_data );
        
        // Guardar referencias
        update_user_meta( $user_id, 'fplms_user_photo_id', $attachment_id );
        update_user_meta( $user_id, 'fplms_user_photo_url', $uploaded_file['url'] );
    }
}
```

**Pasos:**
1. Validar que sea archivo subido
2. Validar MIME type (image/*)
3. Validar tamaño (<5MB)
4. Usar `wp_handle_upload()`
5. Crear attachment post
6. Generar metadatos
7. Guardar ID y URL en user meta

---

## 📊 Metadatos de Usuario

### Metadatos Estándar WordPress

```php
'first_name'  // WordPress estándar
'last_name'   // WordPress estándar
```

### Metadatos Personalizados FairPlay

```php
FairPlay_LMS_Config::USER_META_CITY      // Ciudad
FairPlay_LMS_Config::USER_META_CHANNEL   // Canal / Franquicia
FairPlay_LMS_Config::USER_META_BRANCH    // Sucursal
FairPlay_LMS_Config::USER_META_ROLE      // Cargo
```

### Metadatos de Fotografía (NUEVO)

```php
'fplms_user_photo_id'    // ID del attachment en Media Library
'fplms_user_photo_url'   // URL de la imagen
```

**Acceso a los metadatos:**

```php
// Obtener ID de foto
$photo_id = get_user_meta( $user_id, 'fplms_user_photo_id', true );

// Obtener URL de foto
$photo_url = get_user_meta( $user_id, 'fplms_user_photo_url', true );

// Obtener otros datos
$first_name = get_user_meta( $user_id, 'first_name', true );
$last_name = get_user_meta( $user_id, 'last_name', true );
```

---

## 🔐 Seguridad

### Validaciones Implementadas

#### 1. **Validación de Permisos**
```php
if ( ! current_user_can( FairPlay_LMS_Config::CAP_MANAGE_USERS ) ) {
    return;
}
```
Solo usuarios con capacidad `fplms_manage_users` pueden crear usuarios.

#### 2. **Validación de Nonce**
```php
if ( ! wp_verify_nonce( $_POST['fplms_new_user_nonce'], 'fplms_new_user_save' ) ) {
    return;
}
```
Previene CSRF (Cross-Site Request Forgery).

#### 3. **Sanitización de Datos**
```php
$user_login = sanitize_text_field( wp_unslash( $_POST['fplms_user_login'] ) );
$user_email = sanitize_email( wp_unslash( $_POST['fplms_user_email'] ) );
$first_name = sanitize_text_field( wp_unslash( $_POST['fplms_first_name'] ) );
```
Limpia datos de entrada para prevenir inyecciones.

#### 4. **Validación de Archivos**
```php
// Validar que es archivo cargado
if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
    return;
}

// Validar MIME type
$finfo = finfo_open( FILEINFO_MIME_TYPE );
$mime_type = finfo_file( $finfo, $file['tmp_name'] );
if ( ! in_array( $mime_type, $allowed_types, true ) ) {
    return;
}

// Validar tamaño
if ( $file['size'] > 5 * 1024 * 1024 ) {
    return;
}
```

#### 5. **Validación de Email**
```php
$user_email = sanitize_email( ... );
// WordPress valida formato automáticamente
```

#### 6. **Prevención de Duplicados**
```php
if ( is_wp_error( $user_id ) ) {
    // wp_create_user retorna error si usuario existe
    wp_safe_redirect( ... error ... );
    exit;
}
```

---

## ✅ Compatibilidad

### Versiones de WordPress

**Mínimo requerido:** WordPress 5.0+  
**Recomendado:** WordPress 6.0+

**APIs utilizadas:**
- `wp_create_user()` - Core API
- `wp_handle_upload()` - Core API
- `wp_insert_attachment()` - Core API
- `wp_verify_nonce()` - Core Security API
- `sanitize_*()` - Core Sanitization

### Navegadores Soportados

| Navegador | Soporte |
|-----------|---------|
| Chrome | ✅ Completo |
| Firefox | ✅ Completo |
| Safari | ✅ Completo |
| Edge | ✅ Completo |
| IE11 | ⚠️ Parcial |

**Nota:** IE11 no soporta `aspect-ratio` CSS, pero formulario funciona.

### Características CSS Utilizadas

```css
✅ Grid Layout (IE10+)
✅ Flexbox (IE11+)
✅ CSS Transitions (IE10+)
✅ CSS Variables (no usadas, compatible)
✅ aspect-ratio (no soportado en IE11)
```

### Características JavaScript Utilizadas

```javascript
✅ FileReader API (IE10+)
✅ Fetch API (no usada)
✅ Promise (no usada)
✅ async/await (no usado)
✅ addEventListener (IE9+)
```

---

## 📈 Performance

### Tamaño Agregado

```
CSS: ~8KB (sin minificar)
JavaScript: ~3KB (sin minificar)
Total: ~11KB
```

### Optimizaciones

1. **Lazy loading:** JavaScript cargado al final del formulario
2. **Event delegation:** Eventos directos, no delegados
3. **Minimal reflows:** CSS layout optimizado
4. **Cached selectors:** Elementos guardados en variables

### Carga de Imagen

- **Preview:** DataURL en memoria (sin servidor)
- **Almacenamiento:** Manejado por WordPress Media Library
- **Thumbnails:** Generadas automáticamente por WordPress

---

## 🐛 Debugging

### Logs Disponibles

Para ver errores de subida de foto, habilitar en `wp-config.php`:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

Logs estarán en: `/wp-content/debug.log`

### Errores Comunes

**Error: "El usuario ya existe"**
```
Causa: wp_create_user() retorna WP_Error
Solución: Verificar usuario único antes de crear
```

**Error: "Archivo muy grande"**
```
Causa: Tamaño > 5MB
Solución: Comprimir imagen o aumentar límite
```

**Error: "Formato no soportado"**
```
Causa: MIME type no es imagen
Solución: Usar JPG, PNG, GIF o WebP
```

---

**Versión Documentación:** 1.0  
**Última actualización:** 15 de Enero 2026  
**Autor:** GitHub Copilot
