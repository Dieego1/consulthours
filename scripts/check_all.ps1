<#
.SYNOPSIS
ES: Automatizacion -- version PowerShell de check_all.sh. Un solo comando
    que verifica sintaxis PHP, sintaxis JS, la suite de pruebas del API
    (test_api.py) y la verificacion del resumen mensual
    (verify_summary.py). Ver check_all.sh para la version Bash y el
    detalle bilingue completo.
EN: Automation -- PowerShell version of check_all.sh. One command that
    checks PHP syntax, JS syntax, the API test suite (test_api.py), and
    the monthly summary verification (verify_summary.py). See
    check_all.sh for the Bash version and the full bilingual detail.

.EXAMPLE
    powershell -File scripts\check_all.ps1
#>

$ErrorActionPreference = 'Stop'
$ProjectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectRoot

$PhpBin = if ($env:PHP_BIN) { $env:PHP_BIN } else { 'C:\xampp\php\php.exe' }
$PythonBin = if ($env:PYTHON_BIN) { $env:PYTHON_BIN } else { 'python' }

$Failed = $false

function Step($msg) { Write-Host "`n==> $msg" -ForegroundColor Cyan }
function Ok($msg)   { Write-Host "  [OK] $msg" -ForegroundColor Green }
function Fail($msg) { Write-Host "  [FAIL] $msg" -ForegroundColor Red; $script:Failed = $true }

# --- 1) Sintaxis PHP / PHP syntax ---
Step "1/4 Sintaxis PHP (php -l)"
$phpOk = $true
Get-ChildItem -Recurse -Filter "*.php" -File | Where-Object { $_.FullName -notmatch '\\\.git\\' } | ForEach-Object {
    $output = & $PhpBin -l $_.FullName 2>&1
    if ($LASTEXITCODE -ne 0) {
        Fail $_.FullName
        Write-Host $output
        $phpOk = $false
    }
}
if ($phpOk) { Ok "Todos los archivos .php tienen sintaxis valida" }

# --- 2) Sintaxis JS / JS syntax ---
Step "2/4 Sintaxis JavaScript (node --check)"
$jsOk = $true
Get-ChildItem -Recurse -Filter "*.js" -File | Where-Object { $_.FullName -notmatch '\\\.git\\' } | ForEach-Object {
    & node --check $_.FullName 2>&1 | Out-Null
    if ($LASTEXITCODE -ne 0) {
        Fail $_.FullName
        & node --check $_.FullName
        $jsOk = $false
    }
}
if ($jsOk) { Ok "Todos los archivos .js tienen sintaxis valida" }

# ES: Los dos pasos de Python se corren via "cmd /c ... > archivo 2>&1"
#     en vez de "2>&1" nativo de PowerShell: en PowerShell 5.1,
#     redirigir el stderr de un .exe nativo con "2>&1" envuelve cada
#     línea en un NativeCommandError y ensucia $LASTEXITCODE aunque el
#     proceso haya terminado bien -- unittest (test_api.py) escribe su
#     resultado en stderr por diseño, así que sin este rodeo el script
#     reportaba "fallo" incluso cuando las 15 pruebas pasaban.
# EN: Both Python steps run via "cmd /c ... > file 2>&1" instead of
#     PowerShell's native "2>&1": in PowerShell 5.1, redirecting a
#     native .exe's stderr with "2>&1" wraps every line in a
#     NativeCommandError and corrupts $LASTEXITCODE even when the
#     process finished fine -- unittest (test_api.py) writes its result
#     to stderr by design, so without this workaround the script
#     reported "failed" even when all 15 tests passed.
Step "3/4 Pruebas del API (scripts/test_api.py)"
$testLogFile = Join-Path $env:TEMP 'consulthours_check_all_test_api.log'
cmd /c "`"$PythonBin`" scripts\test_api.py > `"$testLogFile`" 2>&1"
$testLog = Get-Content $testLogFile -Encoding utf8 -ErrorAction SilentlyContinue
if ($LASTEXITCODE -eq 0) {
    Ok (($testLog | Select-String '^Ran \d+ tests').Line)
} else {
    Fail "scripts/test_api.py -- ver detalle abajo"
    $testLog | Write-Host
}

Step "4/4 Resumen mensual vs. seed_data.json (scripts/verify_summary.py)"
$summaryLogFile = Join-Path $env:TEMP 'consulthours_check_all_verify_summary.log'
cmd /c "`"$PythonBin`" scripts\verify_summary.py > `"$summaryLogFile`" 2>&1"
$summaryLog = Get-Content $summaryLogFile -Encoding utf8 -ErrorAction SilentlyContinue
if ($LASTEXITCODE -eq 0) {
    Ok ($summaryLog | Select-Object -Last 1)
} else {
    Fail "scripts/verify_summary.py -- ver detalle abajo"
    $summaryLog | Write-Host
}

Write-Host ""
if (-not $Failed) {
    Write-Host "Todo paso. El sistema esta verificado de punta a punta." -ForegroundColor Green
    exit 0
} else {
    Write-Host "Algo fallo arriba -- revisa el detalle antes de dar por buenos los cambios." -ForegroundColor Red
    exit 1
}
