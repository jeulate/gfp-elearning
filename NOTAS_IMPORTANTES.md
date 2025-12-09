# ⚠️ NOTAS IMPORTANTES - Antes de Empezar los Tests

## 🔴 CRÍTICO: Activar/Desactivar Plugin

Después de las correcciones, **DEBES recargar el código del plugin** en WordPress.

### Opción A: Desactivar y Activar (Recomendado)

```
1. Ve a: WordPress Dashboard → Plugins
2. Busca: "FairPlay LMS - MasterStudy Extensions"
3. Si está ACTIVO (tiene botón "Deactivate"):
   • Haz clic en "Deactivate"
   • Espera 2-3 segundos
   • Haz clic en "Activate"
4. Si está INACTIVO (tiene botón "Activate"):
   • Haz clic en "Activate"
5. Espera a que recargue
6. Verifica que no haya errores en la página
```

### Opción B: Forzar Recarga de WordPress

Si la opción A no funciona:

```
1. Agregar línea al archivo wp-config.php:
   define( 'WP_DEBUG', true );
   
2. Acceder a: wp-admin/
   
3. WordPress recargará todos los plugins

4. Luego comentar la línea de debug si es necesario
```

### Opción C: Limpiar Cache

Si usas un plugin de caché:

```
1. Ve a: Dashboard → [Tu plugin de caché]
2. Busca: "Clear Cache" o "Purge"
3. Haz clic
4. Espera a que termine
```

---

## 📋 REQUISITOS ANTES DE TESTING

### Verificar que existan Estructuras

1. Ve a: **FairPlay LMS → Estructuras**
2. Verifica que existan:
   - ✅ Al menos 1 **Ciudad** (ejemplo: Bogotá, Medellín)
   - ✅ Al menos 1 **Canal** (ejemplo: Online, Presencial)
   - ✅ Al menos 1 **Sucursal** (ejemplo: Principal)
   - ✅ Al menos 1 **Cargo** (ejemplo: Gerente, Coordinador)

Si no existe alguna:
```
1. En "Crear nueva [estructura]"
2. Nombre: ejemplo "Bogotá"
3. Click "Create [estructura]"
4. Marcar como "Activo"
5. Guardar
```

### Verificar que haya Usuarios Existentes

1. Ve a: **WordPress → Usuarios**
2. Verifica que haya al menos 2-3 usuarios
3. Si no hay usuarios:
   ```
   1. Click "Add New User"
   2. Nombre: "testuser1"
   3. Email: "test@example.com"
   4. Password: "Test123"
   5. Role: Subscriber
   6. Click "Create User"
   ```

### Verificar Permisos de Administrador

1. Ve a: **WordPress → Usuarios → [Tu usuario]**
2. Verifica que tengas rol: **Administrator**
3. Sin este rol no verás todas las opciones

---

## 🎯 Orden Correcto de los Tests

**IMPORTANTE:** Ejecuta los tests en este orden exacto:

```
1º TEST 1: Crear usuario sin roles duplicados
   └─ Crea usuario "testuser_nodupe"

2º TEST 2: Crear usuario con múltiples roles  
   └─ Crea usuario "testuser_multirole"

3º TEST 3: Filtrar por ciudad
   └─ Usa usuarios creados en tests 1 y 2

4º TEST 4: Filtrar por múltiples criterios
   └─ Verifica que el filtrado AND funciona

5º TEST 5: Limpiar filtros
   └─ Vuelve a mostrar todos

6º TEST 6: Filtro individual - canal
   └─ Si hay usuarios con estructura

7º TEST 7: Filtro individual - sucursal
   └─ Si hay usuarios con estructura

8º TEST 8: Filtro individual - cargo
   └─ Si hay usuarios con estructura
```

---

## 🔍 Qué Hacer Si Algo Falla

### Si el plugin no se activa

```
Síntoma: "Error al activar plugin"
Causa: Probablemente error de sintaxis PHP
Solución:
  1. Ve a: /wordpress/wp-content/plugins/fairplay-lms-masterstudy-extensions/
  2. Busca archivo: error_log o debug.log
  3. Abre y busca la línea del error
  4. Verifica que no haya caracteres extraños
  5. Restaura el archivo si es necesario
  6. Prueba nuevamente
```

### Si TEST 1 o 2 fallan (usuario tiene Subscriber)

```
Síntoma: Usuario tiene [Alumno FairPlay, Subscriber]
Causa: El cambio remove_role() no se aplicó
Solución:
  1. Ve a: class-fplms-users.php
  2. Busca: handle_new_user_form()
  3. Busca: remove_role('subscriber')
  4. Si NO está presente:
     • Copia y pega el código correcto
     • Guarda el archivo
     • Desactiva/Activa el plugin
     • Prueba nuevamente
```

### Si TEST 3, 4, 5 fallan (filtrado no funciona)

```
Síntoma: Filtro retorna cero resultados
Causa: Meta_query aún no está correcta
Solución:
  1. Ve a: class-fplms-users.php
  2. Busca: get_users_filtered_by_structure()
  3. Verifica que tenga:
     • 'compare' => '='
     • 'type' => 'NUMERIC'
     • 'relation' => 'AND'
     • (string) $city_id (y otros valores)
  4. Si falta alguno:
     • Copia el código correcto
     • Guarda
     • Desactiva/Activa plugin
     • Prueba nuevamente
```

### Si los usuarios no tienen estructura asignada

```
Síntoma: Filtro retorna "No se encontraron usuarios"
Causa: Los usuarios no tienen estructura asignada
Solución:
  1. Ve a: WordPress → Usuarios
  2. Haz click en un usuario
  3. Baja a: "Estructura organizacional FairPlay"
  4. Asigna:
     • Ciudad: Bogotá
     • Canal: Online
     • Sucursal: Principal
     • Cargo: Coordinador
  5. Click "Save"
  6. Repite con al menos 2-3 usuarios
  7. Vuelve a intentar el filtro
```

---

## 📱 Checklist Pre-Testing

Marca estos items antes de empezar:

- [ ] WordPress está activo y puedo acceder
- [ ] He iniciado sesión como Administrador
- [ ] He desactivado y vuelto a activar el plugin FairPlay LMS
- [ ] No hay errores en la página de WordPress
- [ ] Existen al menos 3 estructuras diferentes (Ciudad, Canal, etc.)
- [ ] Existen al menos 2-3 usuarios en WordPress
- [ ] He abierto el archivo CHECKLIST_CORRECCIONES.md
- [ ] Tengo un lugar para anotar resultados (papel o Word)

Si todos estos están marcados: ✅ **LISTO PARA EMPEZAR**

---

## 📊 Notas Durante los Tests

Mientras ejecutas los tests, anota cualquier cosa inusual:

```
Fecha: ___________
Hora de inicio: ___________

Observaciones durante testing:
_________________________________________________________________
_________________________________________________________________
_________________________________________________________________

Problemas encontrados:
_________________________________________________________________
_________________________________________________________________
_________________________________________________________________

Soluciones aplicadas:
_________________________________________________________________
_________________________________________________________________
_________________________________________________________________

Resultado general:
[ ] ✅ TODO FUNCIONA
[ ] ⚠️  PARCIALMENTE FUNCIONA
[ ] ❌ NO FUNCIONA

Comentarios finales:
_________________________________________________________________
_________________________________________________________________
_________________________________________________________________
```

---

## 🎓 Recordatorios Importantes

1. **No borres archivos originales**
   - Mantén un backup de class-fplms-users.php antes de hacer cambios

2. **Los cambios son retroactivos**
   - Usuarios creados antes TAMBIÉN necesitan estructura asignada para que los filtros los encuentren

3. **Los tests son independientes**
   - Cada test puede ejecutarse múltiples veces
   - No dañan datos previos

4. **Los usuarios de prueba pueden borrarse**
   - Al terminar, puedes eliminar testuser_nodupe y testuser_multirole
   - Van a: Usuarios → [Selecciona] → Delete

5. **La base de datos NO se modifica**
   - Solo se leen y escriben datos en wp_users y wp_usermeta
   - No hay cambios en estructura de tablas

---

## ⏰ Timing esperado

```
Verificación previa:      3 minutos
Creación de usuario 1:    2 minutos
Creación de usuario 2:    2 minutos
Prueba de filtros 1:      2 minutos
Prueba de filtros 2:      2 minutos
Prueba de filtros 3:      2 minutos
Prueba de filtros 4:      2 minutos
Prueba de filtros 5:      2 minutos
Documentación resultados: 2 minutos
                         ─────────
TOTAL:                   21 minutos
```

Si tardas más, probablemente hay un problema - revisa TROUBLESHOOTING más arriba.

---

## 🆘 Soporte Técnico Rápido

Si encuentras un error, busca exactamente el texto del error aquí:

| Error | Solución |
|-------|----------|
| `Call to undefined function...` | El plugin no está cargado. Desactiva/Activa. |
| `Parse error: syntax error...` | Hay un error en el código. Verifica caracteres especiales. |
| `No se encontraron usuarios...` | Los usuarios no tienen estructura. Asigna estructura a usuarios. |
| `Usuario tiene Subscriber...` | remove_role() no se ejecutó. Verifica que esté en el código. |
| `Plugin se desactiva solo...` | Error fatal en código. Restaura backup y prueba nuevamente. |
| `Filtro retorna resultados incorrectos...` | Los parámetros 'compare' o 'type' faltan. Verifica meta_query. |

---

## ✨ Próximo Nivel (Opcional)

Si TODO funciona correctamente y quieres ir más allá:

```
1. Crear 20-30 usuarios con diferentes estructuras
2. Probar filtros con combinaciones complejas
3. Revisar logs: WordPress Debug Log
4. Medir tiempo de respuesta en filtros
5. Validar que los roles se asignen correctamente
6. Integrar con MasterStudy LMS
7. Probar visibilidad de cursos con nueva estructura
```

---

## 📞 Resumiendo

**Antes de empezar los tests:**

1. ✅ Plugin desactivado y reactivado
2. ✅ Estructuras creadas
3. ✅ Usuarios existentes
4. ✅ Admin verificado
5. ✅ CHECKLIST_CORRECCIONES.md abierto

**Durante los tests:**
- Ejecuta los 8 tests en orden
- Anota PASS o FAIL
- Si falla: consulta TROUBLESHOOTING

**Después de los tests:**
- Completa resumen de resultados
- Si todo funciona: Sistema listo ✅
- Si algo falla: Contacta con soporte

---

**¡Listo! Ahora sí puedes empezar con CHECKLIST_CORRECCIONES.md**

*Documento: Notas Importantes Pre-Testing*  
*Versión: 1.0*  
*Fecha: 9 de Diciembre de 2024*
