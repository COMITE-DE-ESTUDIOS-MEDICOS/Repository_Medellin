# ============================================================
#  Instala PHP 8.3 (x64, Thread Safe) para el proyecto Documenta
#
#  - Lo deja en C:\Users\<tu usuario>\php83
#  - Crea su php.ini con las extensiones que el proyecto necesita
#  - Lo agrega al PATH de tu usuario
#  - NO toca XAMPP: tu MySQL y tus bases siguen igual
#  - No necesita permisos de administrador
#
#  Para ejecutarlo:
#     powershell -ExecutionPolicy Bypass -File "$HOME\Desktop\instalar-php83.ps1"
# ============================================================

$ErrorActionPreference = 'Stop'

$destino = Join-Path $HOME 'php83'

# Los binarios de 8.3 viven en la carpeta de archivos historicos:
# la rama ya no es la actual de PHP, pero es la que usa el servidor.
$listado = 'https://downloads.php.net/~windows/releases/archives/'

# Respaldo por si el listado no se puede leer: ultima 8.3 conocida.
$respaldo = 'php-8.3.36-Win32-vs16-x64.zip'

Write-Host ''
Write-Host '=== PHP 8.3 para Documenta ===' -ForegroundColor Cyan
Write-Host ''

# --- 1. Ver que PHP hay hoy -------------------------------------------------

$phpActual = (Get-Command php.exe -ErrorAction SilentlyContinue).Source
if ($phpActual) {
    $versionActual = (& $phpActual -r "echo PHP_VERSION;" 2>$null)
    Write-Host "PHP actual: $versionActual  ($phpActual)" -ForegroundColor Yellow
} else {
    Write-Host 'No hay ningun PHP en el PATH todavia.' -ForegroundColor Yellow
}

# --- 2. Buscar la ultima version 8.3 ---------------------------------------

Write-Host ''
Write-Host 'Buscando la ultima version de PHP 8.3...' -ForegroundColor Cyan

[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

$archivo = $null

try {
    $html = (Invoke-WebRequest -Uri $listado -UseBasicParsing).Content

    $candidatos = [regex]::Matches($html, 'php-8\.3\.\d+-Win32-vs\d+-x64\.zip') |
        ForEach-Object { $_.Value } |
        Where-Object   { $_ -notmatch 'nts' } |
        Sort-Object -Unique

    if ($candidatos) {
        $archivo = $candidatos |
            Sort-Object { [int]([regex]::Match($_, 'php-8\.3\.(\d+)-').Groups[1].Value) } |
            Select-Object -Last 1
    }
}
catch {
    Write-Host 'No pude leer el listado, uso la version conocida.' -ForegroundColor Yellow
}

if (-not $archivo) {
    $archivo = $respaldo
    Write-Host 'No encontre el listado; uso la ultima version conocida.' -ForegroundColor Yellow
}

Write-Host "Version a instalar: $archivo" -ForegroundColor Green

# --- 3. Descargar y descomprimir -------------------------------------------

$zip = Join-Path $env:TEMP $archivo

Write-Host ''
Write-Host 'Descargando...' -ForegroundColor Cyan

try {
    Invoke-WebRequest -Uri ($listado + $archivo) -OutFile $zip -UseBasicParsing
}
catch {
    Write-Host ''
    Write-Host "No pude descargar $archivo" -ForegroundColor Red
    Write-Host "Intenta a mano: $listado$archivo"
    exit 1
}

if (Test-Path $destino) {
    Write-Host "Ya existia $destino, lo reemplazo." -ForegroundColor Yellow
    Remove-Item $destino -Recurse -Force
}

Write-Host "Instalando en $destino ..." -ForegroundColor Cyan
Expand-Archive -Path $zip -DestinationPath $destino -Force
Remove-Item $zip -Force

# --- 4. Crear el php.ini con lo que necesita el proyecto -------------------

Write-Host 'Configurando php.ini...' -ForegroundColor Cyan

$ini = Join-Path $destino 'php.ini'
Copy-Item (Join-Path $destino 'php.ini-development') $ini -Force

$texto = Get-Content $ini -Raw
$texto = $texto -replace '(?m)^;?\s*extension_dir\s*=\s*"ext"', 'extension_dir = "ext"'

# pdo_pgsql y pgsql quedan listas desde ya, para la migracion a Postgres.
$extensiones = @(
    'curl', 'fileinfo', 'gd', 'intl', 'mbstring', 'openssl',
    'pdo_mysql', 'pdo_pgsql', 'pgsql', 'zip'
)

foreach ($ext in $extensiones) {
    $texto = $texto -replace "(?m)^;\s*extension\s*=\s*$ext\s*$", "extension=$ext"
}

Set-Content -Path $ini -Value $texto -Encoding UTF8

# --- 5. Dejarlo primero en el PATH del usuario -----------------------------

$pathUsuario = [Environment]::GetEnvironmentVariable('Path', 'User')
if ($pathUsuario -notlike "*$destino*") {
    [Environment]::SetEnvironmentVariable('Path', "$destino;$pathUsuario", 'User')
    Write-Host 'Agregado al PATH de tu usuario.' -ForegroundColor Green
} else {
    Write-Host 'Ya estaba en el PATH de tu usuario.' -ForegroundColor Green
}

# --- 6. Avisar si el PHP viejo va a seguir ganando -------------------------

$conflicto = $false
if ($phpActual) {
    $carpetaVieja = Split-Path $phpActual -Parent
    $pathMaquina = [Environment]::GetEnvironmentVariable('Path', 'Machine')
    if ($pathMaquina -like "*$carpetaVieja*") { $conflicto = $true }
}

Write-Host ''
Write-Host '=== Listo ===' -ForegroundColor Green
Write-Host ''

if ($conflicto) {
    Write-Host 'OJO: el PHP viejo esta en el PATH del sistema, que manda sobre el' -ForegroundColor Yellow
    Write-Host 'tuyo. Abre PowerShell COMO ADMINISTRADOR y corre esta linea:' -ForegroundColor Yellow
    Write-Host ''
    Write-Host "  [Environment]::SetEnvironmentVariable('Path', '$destino;' + [Environment]::GetEnvironmentVariable('Path','Machine'), 'Machine')" -ForegroundColor White
    Write-Host ''
    Write-Host 'Despues cierra TODAS las ventanas de PowerShell y abre una nueva.' -ForegroundColor Yellow
} else {
    Write-Host '1. Cierra TODAS las ventanas de PowerShell y abre una nueva.'
    Write-Host '2. Verifica con:   php -v        (debe decir 8.3.x)'
}

Write-Host ''
Write-Host '3. En la carpeta del proyecto:'
Write-Host '     composer install'
Write-Host '     npm install'
Write-Host '     php artisan migrate'
Write-Host ''
