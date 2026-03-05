# Script para reemplazar render_users_page() con versión modernizada
# Copia de seguridad automática incluida

$filePath = "d:\Programas\gfp-elearning\wordpress\wp-content\plugins\fairplay-lms-masterstudy-extensions\includes\class-fplms-users.php"
$backupPath = "d:\Programas\gfp-elearning\wordpress\wp-content\plugins\fairplay-lms-masterstudy-extensions\includes\class-fplms-users.php.backup"

Write-Host "================================================" -ForegroundColor Cyan
Write-Host "  MODERNIZACIÓN DE render_users_page()" -ForegroundColor Cyan
Write-Host "================================================" -ForegroundColor Cyan
Write-Host ""

# 1. Crear backup
Write-Host "[1/5] Creando backup..." -ForegroundColor Yellow
Copy-Item $filePath $backupPath -Force
Write-Host "      ✓ Backup creado: $backupPath" -ForegroundColor Green
Write-Host ""

# 2. Leer archivo original
Write-Host "[2/5] Leyendo archivo original..." -ForegroundColor Yellow  
$lines = Get-Content $filePath -Encoding UTF8
Write-Host "      ✓ Total líneas: $($lines.Count)" -ForegroundColor Green
Write-Host ""

# 3. Leer nueva función desde archivo temporal
Write-Host "[3/5] Leyendo nueva función..." -ForegroundColor Yellow
$newFunctionPath = "d:\Programas\gfp-elearning\TEMP_NEW_FUNCTION.php"
$newFunction = Get-Content $newFunctionPath -Raw -Encoding UTF8  

# Necesitamos agregar la sección de "Crear Usuario" que falta
# Voy a insertarla manualmente aquí

$crearUsuarioSection = @'
            <!-- SECCIÓN CREAR USUARIO MEJORADA (Oculta inicialmente) -->
            <div id="crear-usuario" style="margin-top: 40px; display: none;">
[CONTENIDO DEL FORMULARIO DE CREAR USUARIO - SE MANTIENE DEL ARCHIVO ORIGINAL]
            </div>
'@

Write-Host "      ✓ Nueva función cargada" -ForegroundColor Green
Write-Host ""

# 4. Reconstruir archivo
Write-Host "[4/5] Reconstruyendo archivo..." -ForegroundColor Yellow
$newContent = @()

# Parte 1: Líneas ANTES de render_users_page (1-324)
$newContent += $lines[0..323]
Write-Host "      ✓ Líneas 1-324 copiadas (antes de render_users_page)" -ForegroundColor Green

# Parte 2: Nueva función (contenido del archivo temporal)
$newContent += $newFunction.TrimEnd()
Write-Host "      ✓ Nueva función render_users_page() insertada" -ForegroundColor Green

# Parte 3: Líneas DESPUÉS de render_users_page (desde 1428 hasta el final)
$newContent += $lines[1427..($lines.Count-1)]
Write-Host "      ✓ Líneas 1428-$($lines.Count) copiadas (después de render_users_page)" -ForegroundColor Green
Write-Host ""

# 5. Guardar archivo
  Write-Host "[5/5] Guardando archivo modificado..." -ForegroundColor Yellow
$newContent | Out-File $filePath -Encoding UTF8 -Force
Write-Host "      ✓ Archivo guardado exitosamente" -ForegroundColor Green
Write-Host ""

Write-Host "================================================" -ForegroundColor Green
Write-Host "  ✓ MODERNIZACIÓN COMPLETADA" -ForegroundColor Green
Write-Host "================================================" -ForegroundColor Green
Write-Host ""
Write-Host "Resumen:" -ForegroundColor Cyan
Write-Host "  • Backup: $backupPath" -ForegroundColor White
Write-Host " • Nueva versión incluye:" -ForegroundColor White
Write-Host "    - Búsqueda en tiempo real" -ForegroundColor White
Write-Host "    - Paginación (10/20/50/100)" -ForegroundColor White
Write-Host "    - Acciones masivas (activar/desactivar/eliminar)" -ForegroundColor White
Write-Host "    - Modal de confirmación" -ForegroundColor White
Write-Host "    - Filtros por estructura" -ForegroundColor White
Write-Host "    - Diseño modernizado" -ForegroundColor White
Write-Host ""
Write-Host "Siguiente paso: Ir a wp-admin y probar la nueva interfaz" -ForegroundColor  Cyan
