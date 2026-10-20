# Met UniversityQuiz en ligne depuis ce PC :
#   1. vérifie MySQL (Laragon) et démarre le backend Laravel (port 8001) si besoin
#   2. (re)démarre un tunnel Cloudflare gratuit vers le backend -> nouvelle adresse publique
#   3. écrit cette adresse dans frontend/.env.production, commit + push
#   4. GitHub Actions republie le site (~2 min) : https://jilanimarwen.github.io/quiz-app/
#
# Utilisation : clic droit > « Exécuter avec PowerShell », ou dans un terminal :
#   powershell -ExecutionPolicy Bypass -File start-online.ps1
# Les fenêtres « Laravel » et « Tunnel » qui s'ouvrent doivent rester ouvertes pendant le cours.

$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot
$backend = Join-Path $root 'backend'
$envFile = Join-Path $root 'frontend\.env.production'
$cloudflared = @(
    "${env:ProgramFiles(x86)}\cloudflared\cloudflared.exe",
    "$env:ProgramFiles\cloudflared\cloudflared.exe"
) | Where-Object { Test-Path $_ } | Select-Object -First 1
$siteUrl = 'https://jilanimarwen.github.io/quiz-app/'

function Step($text) { Write-Host "`n==> $text" -ForegroundColor Cyan }
function Test-Up($url) {
    try { (Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 5).StatusCode -eq 200 } catch { $false }
}

if (-not $cloudflared) { throw 'cloudflared introuvable. Installe-le : winget install Cloudflare.cloudflared' }

# 1. MySQL + backend --------------------------------------------------------------
Step 'Vérification de MySQL (Laragon)'
if (-not (Test-NetConnection 127.0.0.1 -Port 3306 -InformationLevel Quiet -WarningAction SilentlyContinue)) {
    throw 'MySQL ne répond pas : démarre Laragon (bouton « Start All ») puis relance ce script.'
}
Write-Host 'MySQL OK'

Step 'Backend Laravel (http://localhost:8001)'
if (Test-Up 'http://localhost:8001/up') {
    Write-Host 'Déjà démarré'
} else {
    Start-Process -FilePath 'cmd.exe' -WorkingDirectory $backend -WindowStyle Minimized `
        -ArgumentList '/k', 'title Laravel - UniversityQuiz && php artisan serve --port=8001'
    $deadline = (Get-Date).AddSeconds(30)
    while (-not (Test-Up 'http://localhost:8001/up')) {
        if ((Get-Date) -gt $deadline) { throw 'Le backend ne démarre pas (voir la fenêtre « Laravel »).' }
        Start-Sleep -Milliseconds 500
    }
    Write-Host 'Démarré'
}

# 2. Tunnel -----------------------------------------------------------------------
Step 'Tunnel Cloudflare'
Get-Process cloudflared -ErrorAction SilentlyContinue | Stop-Process -Force
$log = Join-Path $env:TEMP 'universityquiz-tunnel.log'
Remove-Item $log -ErrorAction SilentlyContinue
Start-Process -FilePath 'cmd.exe' -WindowStyle Minimized -ArgumentList '/k', `
    "title Tunnel - UniversityQuiz && `"$cloudflared`" tunnel --no-autoupdate --url http://localhost:8001 --logfile `"$log`""

$tunnel = $null
$deadline = (Get-Date).AddSeconds(60)
while (-not $tunnel) {
    if ((Get-Date) -gt $deadline) { throw "Pas d'adresse de tunnel après 60 s (voir $log)." }
    Start-Sleep -Seconds 1
    if (Test-Path $log) {
        $match = Select-String -Path $log -Pattern 'https://[a-z0-9-]+\.trycloudflare\.com' | Select-Object -First 1
        if ($match) { $tunnel = $match.Matches[0].Value }
    }
}
Write-Host "Adresse : $tunnel"

# Wait with a public DNS (asking the router too early makes it cache "not found" for a while).
Write-Host 'Attente que l''adresse soit joignable…'
$deadline = (Get-Date).AddSeconds(90)
$host_ = ([uri]$tunnel).Host
while (-not (Resolve-DnsName $host_ -Server 1.1.1.1 -Type A -ErrorAction SilentlyContinue -DnsOnly |
        Where-Object { $_.IPAddress })) {
    if ((Get-Date) -gt $deadline) { throw 'Le tunnel ne devient pas joignable. Relance le script.' }
    Start-Sleep -Seconds 3
}
Write-Host 'Joignable'

# 3. Publish the new address ------------------------------------------------------
Step 'Mise à jour du site GitHub Pages'
$line = "VITE_API_URL=$tunnel/api"
$current = Get-Content $envFile | Where-Object { $_ -like 'VITE_API_URL=*' }
if ($current -eq $line) {
    Write-Host 'Adresse inchangée, rien à publier.'
} else {
    $content = (Get-Content $envFile -Encoding UTF8) -replace '^VITE_API_URL=.*', $line
    [IO.File]::WriteAllLines($envFile, [string[]]$content, (New-Object Text.UTF8Encoding $false))
    git -C $root add frontend/.env.production
    git -C $root commit -q -m "Tunnel : nouvelle adresse de l'API"
    git -C $root push -q
    if ($LASTEXITCODE -ne 0) { throw 'git push a échoué.' }
    Write-Host 'Poussé sur GitHub : le site sera à jour dans ~2 minutes.'
}

Write-Host "`nUniversityQuiz est en ligne : $siteUrl" -ForegroundColor Green
Write-Host 'Garde les fenêtres « Laravel » et « Tunnel » ouvertes, et ce PC allumé (sans mise en veille).'
