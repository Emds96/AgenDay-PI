# AgenDay - inicio do ambiente de WhatsApp (Docker + WAHA + ngrok).
# Use depois de ligar/reiniciar o computador. Nao contem segredos: a chave do
# WAHA e lida do proprio container e nunca e exibida.
#
#   powershell -ExecutionPolicy Bypass -File scripts\iniciar-feira.ps1
#   ... -ComPhp     tambem sobe o PHP local em 127.0.0.1:8080
#   ... -SemPausa   nao espera Enter no final (para automacao)
param(
    [string]$Dominio = 'additionally-nondeluded-elane.ngrok-free.dev',
    [switch]$ComPhp,
    [switch]$SemPausa
)

$ErrorActionPreference = 'Continue'
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

$project = Split-Path -Parent $PSScriptRoot
$tools = Join-Path $env:USERPROFILE 'Documents\AgenDay-Tools'
$ngrokExe = Join-Path $tools 'ngrok\ngrok.exe'
$phpExe = Join-Path $tools 'php-8.4\php.exe'
$container = 'agenday-waha'
$wahaUrl = 'http://127.0.0.1:3000'
$sessao = 'default'
$chave = ''
$falhas = New-Object System.Collections.Generic.List[string]

function Passo([string]$texto) { Write-Host ''; Write-Host "== $texto" -ForegroundColor Cyan }
function Ok([string]$texto) { Write-Host "   OK: $texto" -ForegroundColor Green }
function Aviso([string]$texto) { Write-Host "   ATENCAO: $texto" -ForegroundColor Yellow }
function Falha([string]$texto) { Write-Host "   FALHOU: $texto" -ForegroundColor Red }

function Test-Docker {
    & docker info --format '{{.ServerVersion}}' 2>$null | Out-Null
    return ($LASTEXITCODE -eq 0)
}

function Esperar([scriptblock]$condicao, [int]$segundos, [int]$intervalo = 2) {
    $limite = (Get-Date).AddSeconds($segundos)
    while ((Get-Date) -lt $limite) {
        if (& $condicao) { return $true }
        Start-Sleep -Seconds $intervalo
    }
    return $false
}

function Get-ChaveWaha {
    $linhas = & docker inspect --format '{{range .Config.Env}}{{println .}}{{end}}' $container 2>$null
    foreach ($linha in $linhas) {
        if ("$linha" -match '^WAHA_API_KEY=(.+)$') { return $Matches[1].Trim() }
    }
    return ''
}

function Invoke-Waha([string]$caminho, [string]$metodo = 'Get', $corpo = $null) {
    $cabecalhos = @{ 'Accept' = 'application/json' }
    if ($chave) { $cabecalhos['X-Api-Key'] = $chave }
    if ($null -ne $corpo) {
        return Invoke-RestMethod -Method $metodo -Uri "$wahaUrl$caminho" -Headers $cabecalhos `
            -ContentType 'application/json' -Body ($corpo | ConvertTo-Json -Compress) -TimeoutSec 10
    }
    return Invoke-RestMethod -Method $metodo -Uri "$wahaUrl$caminho" -Headers $cabecalhos -TimeoutSec 10
}

function Get-StatusSessao {
    try {
        $info = Invoke-Waha "/api/sessions/$sessao"
        if ($info.status) { return "$($info.status)" }
        return ''
    } catch {
        return ''
    }
}

function Get-TunelNgrok {
    try {
        $resposta = Invoke-RestMethod -Uri 'http://127.0.0.1:4040/api/tunnels' -TimeoutSec 5
    } catch {
        return @{ api = $false; url = '' }
    }
    foreach ($tunel in $resposta.tunnels) {
        if ("$($tunel.public_url)" -eq "https://$Dominio") {
            return @{ api = $true; url = "$($tunel.public_url)" }
        }
    }
    return @{ api = $true; url = '' }
}

try {
    Passo 'Limpando variaveis de proxy (atrapalham o ngrok)'
    foreach ($nome in @('http_proxy', 'https_proxy', 'HTTP_PROXY', 'HTTPS_PROXY', 'ALL_PROXY', 'all_proxy')) {
        Remove-Item "Env:$nome" -ErrorAction SilentlyContinue
    }
    Ok 'variaveis de proxy removidas nesta janela'

    Passo 'Docker Desktop'
    if (Test-Docker) {
        Ok 'motor do Docker respondendo'
    } else {
        $atalho = Join-Path $env:ProgramFiles 'Docker\Docker\Docker Desktop.exe'
        if (-not (Test-Path -LiteralPath $atalho)) {
            throw "Docker Desktop nao encontrado em $atalho"
        }
        Start-Process -FilePath $atalho | Out-Null
        if (Esperar { Test-Docker } 180 5) {
            Ok 'Docker Desktop iniciado'
        } else {
            throw 'Docker Desktop nao respondeu em 3 minutos'
        }
    }

    Passo "Container $container"
    & docker inspect --format '{{.State.Running}}' $container 2>$null | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw "Container $container nao existe. Recrie seguindo docs/WHATSAPP_WAHA_GUIA.md (passo 3)."
    }
    $rodando = (& docker inspect --format '{{.State.Running}}' $container 2>$null)
    if ("$rodando".Trim() -ne 'true') {
        & docker start $container | Out-Null
        if (-not (Esperar { "$(& docker inspect --format '{{.State.Running}}' $container 2>$null)".Trim() -eq 'true' } 60 2)) {
            throw "Nao foi possivel iniciar o container $container"
        }
        Ok 'container iniciado'
    } else {
        Ok 'container ja estava rodando'
    }

    $chave = Get-ChaveWaha
    if (-not $chave) { Aviso 'chave do WAHA nao encontrada no container; as consultas podem ser recusadas' }

    Passo 'API do WAHA'
    if (-not (Esperar { try { Invoke-Waha '/api/server/version' | Out-Null; $true } catch { $false } } 120 3)) {
        throw 'API do WAHA nao respondeu em 127.0.0.1:3000'
    }
    Ok 'API respondendo em 127.0.0.1:3000'

    Passo "Sessao $sessao"
    $status = Get-StatusSessao
    if ($status -eq 'WORKING') {
        Ok 'sessao WORKING (WhatsApp conectado)'
    } elseif ($status -eq 'STOPPED') {
        try {
            Invoke-Waha '/api/sessions/start' 'Post' @{ name = $sessao } | Out-Null
        } catch {
            Aviso 'nao foi possivel pedir a inicializacao da sessao pela API'
        }
        if (Esperar { (Get-StatusSessao) -eq 'WORKING' } 90 3) {
            Ok 'sessao iniciada e WORKING'
            $status = 'WORKING'
        } else {
            $status = Get-StatusSessao
            Falha "sessao continua em '$status'"
        }
    } elseif ($status) {
        Falha "sessao em '$status'"
    } else {
        Falha 'nao foi possivel ler o status da sessao'
    }

    if ($status -ne 'WORKING') {
        $falhas.Add('WhatsApp nao esta WORKING: abra http://localhost:3000/dashboard, inicie a sessao default e escaneie o QR se aparecer SCAN_QR.')
    }

    Passo 'Tunel do ngrok'
    $tunel = Get-TunelNgrok
    if ($tunel.url) {
        Ok "tunel ativo em $($tunel.url)"
    } elseif ($tunel.api) {
        Aviso 'o ngrok esta rodando, mas sem o dominio configurado'
        $falhas.Add("Feche o ngrok atual e rode de novo o script, ou use o dominio $Dominio.")
    } else {
        if (-not (Test-Path -LiteralPath $ngrokExe)) {
            throw "ngrok nao encontrado em $ngrokExe"
        }
        $logOut = Join-Path $tools 'ngrok\ngrok.out.log'
        $logErr = Join-Path $tools 'ngrok\ngrok.err.log'
        Start-Process -FilePath $ngrokExe -ArgumentList @('http', '3000', "--domain=$Dominio") `
            -WindowStyle Hidden -RedirectStandardOutput $logOut -RedirectStandardError $logErr | Out-Null
        if (Esperar { (Get-TunelNgrok).url } 60 3) {
            $tunel = Get-TunelNgrok
            Ok "tunel aberto em $($tunel.url)"
        } else {
            $detalhe = ''
            if (Test-Path -LiteralPath $logErr) {
                $detalhe = (Get-Content -LiteralPath $logErr -Tail 3 -ErrorAction SilentlyContinue) -join ' '
            }
            Falha "ngrok nao abriu o tunel. $detalhe"
            $falhas.Add('ngrok sem tunel: confira o authtoken (ngrok config add-authtoken) e o dominio.')
        }
    }

    if ($ComPhp) {
        Passo 'PHP local (127.0.0.1:8080)'
        $porta = Get-NetTCPConnection -LocalPort 8080 -State Listen -ErrorAction SilentlyContinue
        if ($porta) {
            Ok 'porta 8080 ja esta em uso (PHP local rodando)'
        } elseif (-not (Test-Path -LiteralPath $phpExe)) {
            Aviso "PHP nao encontrado em $phpExe"
        } else {
            Start-Process -FilePath $phpExe -ArgumentList @('-S', '127.0.0.1:8080') -WorkingDirectory $project `
                -WindowStyle Hidden -RedirectStandardOutput (Join-Path $tools 'php-local.out.log') `
                -RedirectStandardError (Join-Path $tools 'php-local.err.log') | Out-Null
            if (Esperar { (Get-NetTCPConnection -LocalPort 8080 -State Listen -ErrorAction SilentlyContinue) } 30 2) {
                Ok 'PHP local em http://127.0.0.1:8080'
            } else {
                Aviso 'PHP local nao confirmou a porta 8080'
            }
        }
    }

    Passo 'Resumo'
    Write-Host "   Docker:      $(if (Test-Docker) { 'ok' } else { 'parado' })"
    Write-Host "   WAHA:        $wahaUrl"
    Write-Host "   Sessao:      $(if ($status) { $status } else { 'desconhecida' })"
    Write-Host "   ngrok:       $(if ($tunel.url) { $tunel.url } else { 'sem tunel' })"
    Write-Host "   Endereco:    https://$Dominio"
    Write-Host ''
    Write-Host '   Lembrete: o endereco salvo em Configuracoes > WhatsApp precisa ser o mesmo.'
    Write-Host '   A fila de mensagens e processada ao abrir a agenda ou o portal.'

    if ($falhas.Count -gt 0) {
        Write-Host ''
        Write-Host 'Pendencias:' -ForegroundColor Yellow
        foreach ($item in $falhas) { Write-Host " - $item" -ForegroundColor Yellow }
        exit 1
    }
    Write-Host ''
    Write-Host 'Ambiente pronto para enviar mensagens.' -ForegroundColor Green
    exit 0
} catch {
    Write-Host ''
    Falha $_.Exception.Message
    exit 1
} finally {
    if (-not $SemPausa) {
        Write-Host ''
        Read-Host 'Pressione Enter para fechar'
    }
}
