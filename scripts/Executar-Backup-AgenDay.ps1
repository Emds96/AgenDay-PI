# AgenDay Backup - iniciador Windows, sem credenciais no codigo.
# -CheckOnly faz diagnostico sem gerar backup.
param([switch]$CheckOnly)

$ErrorActionPreference = 'Stop'
$project = Split-Path -Parent $PSScriptRoot
$profilePath = $env:USERPROFILE
$tools = Join-Path $profilePath 'Documents\AgenDay-Tools'
$data = Join-Path $tools 'agenday-restore-db'
$bin = Join-Path $tools 'mariadb-11.8.9-winx64\bin'
$mysql = Join-Path $bin 'mariadb.exe'
$server = Join-Path $bin 'mariadbd.exe'
$admin = Join-Path $bin 'mariadb-admin.exe'
$privateConfig = Join-Path $profilePath 'Documents\AgenDay-Backups\.mysql-local.cnf'
$backupScript = Join-Path $project 'scripts\backup_agenday.py'
$python = (Get-Command python -ErrorAction Stop).Source
$started = $false
$success = $false

function Test-MySQLLocal {
    try {
        $check = & $mysql "--defaults-extra-file=$privateConfig" '--host=127.0.0.1' '--port=3306' '--batch' '--skip-column-names' '--execute=SELECT 1' 2>$null
        return ($LASTEXITCODE -eq 0 -and "$check".Trim() -eq '1')
    } catch { return $false }
}

try {
    foreach ($required in @($mysql, $server, $admin, (Join-Path $data 'my.ini'), $privateConfig, $backupScript)) {
        if (-not (Test-Path -LiteralPath $required)) {
            throw "Arquivo local obrigatorio indisponivel: $required"
        }
    }
    if (-not (Test-MySQLLocal)) {
        if (Get-NetTCPConnection -LocalPort 3306 -State Listen -ErrorAction SilentlyContinue) {
            throw 'A porta 3306 esta ocupada por outro banco local; nao vou interferir.'
        }
        Write-Host 'Iniciando MariaDB de restauracao local...'
        $out = Join-Path $tools 'mariadb-restore.out.log'
        $err = Join-Path $tools 'mariadb-restore.err.log'
        $args = @("--defaults-file=$data\my.ini", '--bind-address=127.0.0.1', '--port=3306', '--console')
        $proc = Start-Process -FilePath $server -ArgumentList $args -WorkingDirectory $bin -WindowStyle Hidden -RedirectStandardOutput $out -RedirectStandardError $err -PassThru
        $started = $true
        for ($i=0; $i -lt 30; $i++) {
            if (Test-MySQLLocal) { break }
            if ($proc.HasExited) { break }
            Start-Sleep -Milliseconds 500
        }
        if (-not (Test-MySQLLocal)) {
            throw 'MariaDB local nao iniciou; consultar logs privados em AgenDay-Tools.'
        }
    }
    if ($CheckOnly) {
        Write-Host 'MariaDB local OK. Testando FTPS somente leitura...'
        & $python $backupScript '--check-ftps'
    } else {
        Write-Host 'MariaDB local OK. Iniciando backup AgenDay completo...'
        & $python $backupScript '--guard-verified' '--mysql-cli' $mysql
    }
    if ($LASTEXITCODE -ne 0) { throw 'O backup/diagnostico retornou erro.' }
    $success = $true
} catch {
    Write-Host ('BACKUP FALHOU: ' + $_.Exception.Message) -ForegroundColor Red
} finally {
    if ($started) {
        Write-Host 'Encerrando MariaDB local iniciado por este comando...'
        try {
            & $admin "--defaults-extra-file=$privateConfig" '--host=127.0.0.1' '--port=3306' 'shutdown' *> $null
            if ($LASTEXITCODE -ne 0) { Write-Warning 'Falha ao encerrar MariaDB local; verificar processo.' }
        } catch {
            Write-Warning 'MariaDB local pode ter permanecido ativo.'
        }
    }
}
if ($success) {
    Write-Host 'OPERACAO CONCLUIDA COM SUCESSO.' -ForegroundColor Green
    exit 0
}
exit 1
