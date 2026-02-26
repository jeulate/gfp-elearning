# 📊 Análisis de Estructuras y Campo Descripción

## 🔍 Análisis Inicial - Almacenamiento en Base de Datos

### Estado Actual del Sistema

#### 1. Taxonomías Personalizadas (WordPress)

Las estructuras se almacenan como **taxonomías personalizadas** en WordPress:

```php
public const TAX_CITY    = 'fplms_city';       // Ciudades
public const TAX_COMPANY = 'fplms_company';    // Empresas
public const TAX_CHANNEL = 'fplms_channel';    // Canales/Franquicias
public const TAX_BRANCH  = 'fplms_branch';     // Sucursales
public const TAX_ROLE    = 'fplms_job_role';   // Cargos
```

#### 2. Tablas de Base de Datos Utilizadas

**Tabla `wp_terms`:**
```sql
term_id  | name                    | slug
---------|-------------------------|------------------
1        | Barcelona               | barcelona
2        | Mad Human: Soluciones para Recursos Humanos de Vanguardia

 | madrid-human
3        | Administración - Finanzas | administracion-finanzas
```

**Tabla `wp_term_taxonomy`:**
```sql
term_taxonomy_id | term_id | taxonomy       | description
-----------------|---------|----------------|-------------
1                | 1       | fplms_city     |
2                | 2       | fplms_company  |
3                | 3       | fplms_channel  |
```

**Tabla `wp_termmeta`:** (metadatos de términos)
```sql
meta_id | term_id | meta_key            | meta_value
--------|---------|---------------------|------------------
1       | 1       | fplms_active        | 1
2       | 2       | fplms_active        | 1
3       | 2       | fplms_cities        | ["1","3"]
4       | 3       | fplms_companies     | ["2"]
```

#### 3. Metadatos Actuales por Estructura

| Estructura     | Meta Keys Guardados                |
|----------------|------------------------------------|
| **Ciudad**     | `fplms_active` (1/0)               |
| **Empresa**    | `fplms_active`, `fplms_cities` (JSON) |
| **Canal**      | `fplms_active`, `fplms_companies` (JSON), `fplms_linked_category_id` |
| **Sucursal**   | `fplms_active`, `fplms_channels` (JSON) |
| **Cargo**      | `fplms_active`, `fplms_branches` (JSON) |

---

## ✅ Confirmación: Las Estructuras SÍ se Están Guardando

### Evidencia en Código

**1. Registro de Taxonomías** (`class-fplms-structures.php`, líneas 9-47):
```php
public function register_taxonomies(): void {
    register_taxonomy(
        FairPlay_LMS_Config::TAX_CITY,
        'post',
        [ 'public' => false, 'show_ui' => false, 'hierarchical' => false ]
    );
    // ... (todas las demás taxonomías)
}
```

**2. Guardado en Handle Form** (`class-fplms-structures.php`, líneas 87-140):
```php
if ( 'create' === $action ) {
    $name = sanitize_text_field( wp_unslash( $_POST['fplms_name'] ?? '' ) );
    $term = wp_insert_term( $name, $taxonomy );
    if ( ! is_wp_error( $term ) ) {
        update_term_meta( $term['term_id'], FairPlay_LMS_Config::META_ACTIVE, $active );
        // Guarda relaciones jerárquicas (ciudades, empresas, canales, sucursales)
    }
}
```

**3. Guardado de Relaciones Jerárquicas**:
```php
// Empresas → Ciudades
$this->save_multiple_cities( $term_id, $city_ids );
    → update_term_meta( $term_id, 'fplms_cities', json_encode($city_ids) );

// Canales → Empresas
$this->save_term_companies( $term_id, $company_ids );
    → update_term_meta( $term_id, 'fplms_companies', json_encode($company_ids) );

// Sucursales → Canales
$this->save_term_channels( $term_id, $channel_ids );
    → update_term_meta( $term_id, 'fplms_channels', json_encode($channel_ids) );

// Cargos → Sucursales
$this->save_term_branches( $term_id, $branch_ids );
    → update_term_meta( $term_id, 'fplms_branches', json_encode($branch_ids) );
```

---

## 🎯 Objetivo: Añadir Campo "Descripción"

### Requisitos

1. **Nombre del campo:** `descripción`
2. **Tipo:** Textarea o input text
3. **Máximo de caracteres:** 300
4. **Aplicación:** Todos los niveles (Ciudad, Empresa, Canal, Sucursal, Cargo)
5. **Almacenamiento:** `wp_termmeta` con meta_key `fplms_description`

### Estructura Propuesta

**Tabla `wp_termmeta` después de implementar:**
```sql
meta_id | term_id | meta_key         | meta_value
--------|---------|------------------|----------------------------------
1       | 1       | fplms_active     | 1
2       | 1       | fplms_description| Oficina central ubicada en el centro de la ciudad
3       | 2       | fplms_active     | 1
4       | 2       | fplms_description| Empresa especializada en soluciones de recursos humanos
```

---

## 📋 Plan de Implementación

### Paso 1: Añadir Constante en Config
**Archivo:** `class-fplms-config.php`

```php
// Después de línea 25 (META_TERM_BRANCHES)
public const META_TERM_DESCRIPTION = 'fplms_description';  // Descripción de estructura (max 300 chars)
```

### Paso 2: Modificar Handle Form para Guardar Descripción
**Archivo:** `class-fplms-structures.php` (líneas 87-95)

**Acción `create`:**
```php
if ( 'create' === $action ) {
    $name = sanitize_text_field( wp_unslash( $_POST['fplms_name'] ?? '' ) );
    $description = sanitize_textarea_field( wp_unslash( $_POST['fplms_description'] ?? '' ) );
    $active = ! empty( $_POST['fplms_active'] ) ? '1' : '0';

    // Validar longitud de descripción
    if ( strlen( $description ) > 300 ) {
        $description = substr( $description, 0, 300 );
    }

    if ( $name ) {
        $term = wp_insert_term( $name, $taxonomy );
        if ( ! is_wp_error( $term ) ) {
            update_term_meta( $term['term_id'], FairPlay_LMS_Config::META_ACTIVE, $active );
            
            // Guardar descripción si no está vacía
            if ( ! empty( $description ) ) {
                update_term_meta( $term['term_id'], FairPlay_LMS_Config::META_TERM_DESCRIPTION, $description );
            }
            
            // ... resto del código (relaciones jerárquicas)
        }
    }
}
```

**Acción `edit`:**
```php
if ( 'edit' === $action ) {
    $term_id = isset( $_POST['fplms_term_id'] ) ? absint( $_POST['fplms_term_id'] ) : 0;
    $name = sanitize_text_field( wp_unslash( $_POST['fplms_name'] ?? '' ) );
    $description = sanitize_textarea_field( wp_unslash( $_POST['fplms_description'] ?? '' ) );

    // Validar longitud
    if ( strlen( $description ) > 300 ) {
        $description = substr( $description, 0, 300 );
    }

    if ( $term_id && $name ) {
        wp_update_term( $term_id, $taxonomy, [ 'name' => $name ] );
        
        // Actualizar descripción
        if ( ! empty( $description ) ) {
            update_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_DESCRIPTION, $description );
        } else {
            delete_term_meta( $term_id, FairPlay_LMS_Config::META_TERM_DESCRIPTION );
        }
        
        // ... resto del código (relaciones jerárquicas)
    }
}
```

### Paso 3: Añadir Campo en Formulario de Creación (Acordeón)
**Archivo:** `class-fplms-structures.php` (después de línea 580)

```php
<input name="fplms_name" type="text" class="regular-text" placeholder="<?php echo esc_attr( $placeholder ); ?>" required>

<!-- NUEVO CAMPO DESCRIPCIÓN -->
<div class="fplms-description-field" style="margin-top: 10px;">
    <label for="fplms_description_<?php echo esc_attr( $tab_key ); ?>">
        📝 Descripción (opcional)
    </label>
    <textarea 
        id="fplms_description_<?php echo esc_attr( $tab_key ); ?>"
        name="fplms_description" 
        class="large-text fplms-description-textarea" 
        maxlength="300" 
        rows="3"
        placeholder="Descripción breve de la estructura (máximo 300 caracteres)..."></textarea>
    <small class="fplms-char-count" style="color: #666; font-size: 12px;">
        <span class="fplms-current-chars">0</span>/300 caracteres
    </small>
</div>

<?php if ( 'city' !== $tab_key ) : ?>
    <!-- Resto del código (selectores de padres) -->
```

### Paso 4: Añadir Campo en Formulario de Edición Inline
**Archivo:** `class-fplms-structures.php` (buscar formularios de edición inline)

Buscar la sección donde se renderiza el formulario de edición (aproximadamente líneas 400-500) y agregar:

```php
<div class="fplms-edit-field">
    <label for="fplms_edit_name_<?php echo esc_attr( $term->term_id ); ?>">
        Nombre
    </label>
    <input 
        type="text" 
        id="fplms_edit_name_<?php echo esc_attr( $term->term_id ); ?>"
        name="fplms_name" 
        value="<?php echo esc_attr( $term->name ); ?>" 
        required>
</div>

<!-- NUEVO CAMPO DESCRIPCIÓN EN EDICIÓN -->
<div class="fplms-edit-field">
    <label for="fplms_edit_description_<?php echo esc_attr( $term->term_id ); ?>">
        📝 Descripción
    </label>
    <?php 
    $current_description = get_term_meta( $term->term_id, FairPlay_LMS_Config::META_TERM_DESCRIPTION, true );
    ?>
    <textarea 
        id="fplms_edit_description_<?php echo esc_attr( $term->term_id ); ?>"
        name="fplms_description" 
        class="fplms-description-textarea" 
        maxlength="300" 
        rows="3"
        placeholder="Descripción breve (máximo 300 caracteres)..."><?php echo esc_textarea( $current_description ); ?></textarea>
    <small class="fplms-char-count" style="color: #666; font-size: 12px;">
        <span class="fplms-current-chars"><?php echo esc_html( strlen( $current_description ) ); ?></span>/300 caracteres
    </small>
</div>
```

### Paso 5: Añadir JavaScript para Contador de Caracteres
**Archivo:** `class-fplms-structures.php` (en la sección de `<script>`, aproximadamente línea 1500+)

```javascript
// Contador de caracteres en tiempo real para descripciones
document.querySelectorAll('.fplms-description-textarea').forEach(function(textarea) {
    const container = textarea.closest('.fplms-description-field, .fplms-edit-field');
    const counterSpan = container ? container.querySelector('.fplms-current-chars') : null;
    
    if (counterSpan) {
        textarea.addEventListener('input', function() {
            const currentLength = this.value.length;
            counterSpan.textContent = currentLength;
            
            // Cambiar color si se acerca al límite
            if (currentLength >= 280) {
                counterSpan.style.color = '#d63638'; // Rojo
            } else if (currentLength >= 250) {
                counterSpan.style.color = '#f0b849'; // Amarillo
            } else {
                counterSpan.style.color = '#666'; // Gris normal
            }
        });
    }
});
```

### Paso 6: Añadir CSS para Estilizar Campo Descripción
**Archivo:** `class-fplms-structures.php` (en la sección de `<style>`, aproximadamente línea 1300+)

```css
/* Campo de descripción */
.fplms-description-field {
    margin-bottom: 15px;
}

.fplms-description-field label {
    display: block;
    font-weight: 600;
    margin-bottom: 5px;
    color: #333;
}

.fplms-description-textarea {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-family: inherit;
    font-size: 14px;
    resize: vertical;
    transition: border-color 0.2s;
}

.fplms-description-textarea:focus {
    outline: none;
    border-color: #0073aa;
    box-shadow: 0 0 0 1px #0073aa;
}

.fplms-char-count {
    display: block;
    margin-top: 5px;
    text-align: right;
}
```

### Paso 7: Mostrar Descripción en Listado (Opcional)
**Archivo:** `class-fplms-structures.php` (sección de visualización de términos)

Para mostrar la descripción en un tooltip o directamente en el listado:

```php
<?php foreach ( $terms as $term ) : ?>
    <div class="fplms-item">
        <span class="fplms-item-name">
            <?php echo esc_html( $term->name ); ?>
            <?php 
            $description = get_term_meta( $term->term_id, FairPlay_LMS_Config::META_TERM_DESCRIPTION, true );
            if ( ! empty( $description ) ) : 
            ?>
                <span class="fplms-item-description-icon" 
                      title="<?php echo esc_attr( $description ); ?>"
                      style="cursor: help; color: #666; margin-left: 5px;">
                    ℹ️
                </span>
            <?php endif; ?>
        </span>
        <!-- Resto del código -->
    </div>
<?php endforeach; ?>
```

O mostrar descripción completa debajo del nombre:

```php
<span class="fplms-item-name">
    <?php echo esc_html( $term->name ); ?>
</span>
<?php 
$description = get_term_meta( $term->term_id, FairPlay_LMS_Config::META_TERM_DESCRIPTION, true );
if ( ! empty( $description ) ) : 
?>
    <div class="fplms-item-description" style="font-size: 12px; color: #666; margin-top: 3px; font-style: italic;">
        <?php echo esc_html( $description ); ?>
    </div>
<?php endif; ?>
```

---

## 🧪 Testing

### Test 1: Crear Ciudad con Descripción
```
1. Ir a FairPlay LMS → Estructuras → Ciudades
2. Completar formulario:
   - Nombre: "Barcelona"
   - Descripción: "Oficina central en el distrito 22@"
   - Activo: ✓
3. Click "Crear"
4. Verificar en BD:
   SELECT * FROM wp_termmeta WHERE meta_key = 'fplms_description';
```

### Test 2: Editar Descripción Existente
```
1. Hacer clic en ✏️ en una ciudad
2. Cambiar descripción
3. Click "Guardar Cambios"
4. Recargar página
5. Verificar que la descripción se mantuvo
```

### Test 3: Validación de 300 Caracteres
```
1. Escribir texto de más de 300 caracteres
2. Verificar que el textarea no permita más de 300
3. Verificar contador en rojo cuando se acerca al límite
```

### Test 4: Descripción Vacía (Opcional)
```
1. Crear estructura sin descripción
2. Verificar que no se guarda meta vacío en BD
3. Editar estructura y agregar descripción
4. Verificar que ahora sí se guarda
```

---

## ✅ Checklist de Implementación

- [ ] Añadir constante `META_TERM_DESCRIPTION` en `class-fplms-config.php`
- [ ] Modificar acción `create` en `handle_form()` para guardar descripción
- [ ] Modificar acción `edit` en `handle_form()` para actualizar descripción
- [ ] Añadir acción `delete` para eliminar descripción al borrar estructura
- [ ] Añadir campo descripción en formulario de creación (acordeón)
- [ ] Añadir campo descripción en formulario de edición inline
- [ ] Añadir JavaScript para contador de caracteres
- [ ] Añadir CSS para estilizar campo
- [ ] (Opcional) Mostrar descripción en listado de estructuras
- [ ] Probar en todos los niveles: Ciudad, Empresa, Canal, Sucursal, Cargo
- [ ] Verificar en base de datos que se guarda correctamente

---

## 📊 Resultado Final

Después de la implementación, cada estructura tendrá:

```php
// Ejemplo: Ciudad "Barcelona"
Term ID: 1
Term Name: "Barcelona"
Term Taxonomy: "fplms_city"

// Metadatos en wp_termmeta:
fplms_active = "1"
fplms_description = "Oficina principal ubicada en el distrito tecnológico 22@, con acceso a transporte público."
```

✅ **Campo opcional:** No es obligatorio completar descripción
✅ **Límite estricto:** Máximo 300 caracteres (truncado automáticamente)
✅ **Contador visual:** Muestra caracteres restantes en tiempo real
✅ **Guardado en BD:** Almacenado en `wp_termmeta` con meta_key `fplms_description`
