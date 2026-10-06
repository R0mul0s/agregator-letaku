# =============================================================================
#  build-upload.ps1 — sestaví nahrávací balíček Slevohlídky do deploy\upload\
#
#  Postup (stejně jako u Počasí):
#    1) sestaví frontend assety (npm run build v kontejneru),
#    2) zkopíruje do deploy\upload\ jen vyjmenované části aplikace — ze storage\
#       jen prázdnou kostru adresářů (lokální logy se na hosting nesmí dostat),
#    3) doinstaluje PRODUKČNÍ composer závislosti (--no-dev) přímo v upload\
#       (přes docker, aby se nesáhlo na dev vendor v rootu); composer přitom
#       vygeneruje bootstrap\cache\packages.php pro produkční balíčky,
#    4) kontrola, že v balíčku nezůstala zkompilovaná cache z vývoje.
#
#  Balíček se staví jen z čistého pracovního stromu — nasazuje se commit, ne
#  rozpracované změny. Jeho hash se zapíše do public\version.txt (ověření, co
#  na produkci běží). Výjimka pro zkoušku: přepínač -AllowDirty.
#
#  Spouštěj z rootu repa:  powershell -ExecutionPolicy Bypass -File deploy\build-upload.ps1
#
#  @author Roman Hlaváček
#  @created 2026-10-02
# =============================================================================
param(
    # Sestavit i s necommitnutými změnami (verze pak dostane příponu -dirty)
    [switch] $AllowDirty
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root
$upload = Join-Path $root 'deploy\upload'

# npm i composer hlásí průběh na stderr. Windows PowerShell z toho při
# $ErrorActionPreference = 'Stop' udělá chybu a skript spadne, i když příkaz
# doběhl v pořádku — proto se nativní příkazy pouští přes obálku, která
# se dívá jen na návratový kód.
function Invoke-Native {
    param(
        [Parameter(Mandatory)] [scriptblock] $Command,
        [Parameter(Mandatory)] [string] $What
    )

    $previous = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try { & $Command } finally { $ErrorActionPreference = $previous }

    if ($LASTEXITCODE -ne 0) { throw "$What selhal (kód $LASTEXITCODE)" }
}

# Nasazuje se commit — necommitnuté změny by na produkci běžely bez záznamu v gitu
$dirty = git status --porcelain
if ($LASTEXITCODE -ne 0) { throw 'git status selhal — spouštěj skript v repozitáři.' }
if ($dirty -and -not $AllowDirty) {
    throw "Pracovní strom má necommitnuté změny — commitni je, nebo spusť s -AllowDirty:`n$($dirty -join "`n")"
}
$version = (git rev-parse --short HEAD).Trim()
if ($dirty) { $version = "$version-dirty" }

Write-Host "==> 1/4  Build frontend assetů (verze $version)…" -ForegroundColor Cyan
Invoke-Native -What 'npm run build' -Command { docker compose exec -T app npm run build }

Write-Host '==> 2/4  Kopíruji aplikaci do deploy\upload\…' -ForegroundColor Cyan
if (Test-Path $upload) { Remove-Item $upload -Recurse -Force }

# Co na hostingu potřebuje běžící aplikace. Seznam povoleného místo seznamu
# zakázaného — nový pracovní soubor v repu se tak na hosting nedostane omylem.
# database\ kvůli migracím a seederům (data katalogu) — artisan se na hostingu
# nespouští, ale composer autoload na ně odkazuje. resources\legal = podmínky
# a zásady (R51), aplikace je čte za běhu. resources\pwa = service worker (R66),
# server ho posílá na /sw.js.
$appDirs = @('app', 'bootstrap', 'config', 'database', 'lang', 'public', 'resources\views', 'resources\legal', 'resources\pwa', 'routes')
$appFiles = @('artisan', 'composer.json', 'composer.lock')
# Soubory, které vznikají lokálně a nahrát se nesmí (hot = běžící Vite dev server,
# _preview* = pomocné stránky pro snímky obrazovky).
$excludeFiles = @('hot', '*.php.tmp', '_preview*', '_csp*')

# Robocopy vrací kódy 0–7 jako úspěch (1 = něco se zkopírovalo).
function Invoke-Robocopy {
    param([Parameter(Mandatory)] [string[]] $Arguments)

    $ErrorActionPreference = 'Continue'
    robocopy @Arguments /NFL /NDL /NJH /NJS /NP | Out-Null
    if ($LASTEXITCODE -ge 8) { throw "robocopy selhal (kód $LASTEXITCODE): $($Arguments -join ' ')" }
}

foreach ($dir in $appDirs) {
    Invoke-Robocopy (@((Join-Path $root $dir), (Join-Path $upload $dir), '/E', '/XF') + $excludeFiles)
}
Invoke-Robocopy (@($root, $upload) + $appFiles)

# Ze storage jen kostra — adresáře s .gitignore, žádný obsah.
Invoke-Robocopy @((Join-Path $root 'storage'), (Join-Path $upload 'storage'), '.gitignore', '/S')

# Zkompilovanou cache z vývoje zahodit: config.php by na hostingu přebil .env
# a packages.php obsahuje dev balíčky. Produkční packages.php vytvoří composer.
Get-ChildItem "$upload\bootstrap\cache" -Filter '*.php' | Remove-Item -Force

Write-Host '==> 3/4  composer install --no-dev v upload\ (přes docker)…' -ForegroundColor Cyan
Invoke-Native -What 'composer install' -Command {
    docker compose exec -T app sh -lc 'cd /var/www/html/deploy/upload && composer install --no-dev --optimize-autoloader --no-interaction'
}

Write-Host '==> 4/4  Kontroluji balíček…' -ForegroundColor Cyan
# packages.php musí v balíčku být: Laravel ho na hostingu sám přegeneruje jen
# tehdy, když chybí — starý by po změně závislostí zůstal platit.
if (-not (Test-Path "$upload\bootstrap\cache\packages.php")) {
    throw 'V balíčku chybí bootstrap\cache\packages.php — composer install ho nevygeneroval.'
}
$forbidden = @('config.php', 'events.php', 'routes-v7.php') |
    Where-Object { Test-Path "$upload\bootstrap\cache\$_" }
if ($forbidden) { throw "V balíčku zůstala zkompilovaná cache: $($forbidden -join ', ')" }
$storageFiles = Get-ChildItem "$upload\storage" -Recurse -File | Where-Object Name -ne '.gitignore'
if ($storageFiles) { throw "Ve storage balíčku jsou soubory navíc: $($storageFiles.FullName -join ', ')" }
if (-not (Test-Path "$upload\public\build\manifest.json")) {
    throw 'V balíčku chybí public\build\manifest.json — build assetů neproběhl.'
}
foreach ($legal in @('terms.md', 'privacy.md')) {
    if (-not (Test-Path "$upload\resources\legal\$legal")) {
        throw "V balíčku chybí resources\legal\$legal — právní stránky by na hostingu spadly (R51)."
    }
}
if (-not (Test-Path "$upload\resources\pwa\service-worker.js")) {
    throw 'V balíčku chybí resources\pwa\service-worker.js — /sw.js by na hostingu spadl (R66).'
}

# Verze pro ověření po nasazení: https://slevohlidka.cz/version.txt?v=… (proxy
# hostingu statické soubory cachuje — bez unikátního ?v= může ukázat starou)
[IO.File]::WriteAllText((Join-Path $upload 'public\version.txt'), "$version`n")

Write-Host ''
Write-Host "HOTOVO (verze $version). K nahrání:  $upload" -ForegroundColor Green
Write-Host 'Nezapomeň: vyplněný .env (deploy\.env.production.example) a SQL skripty z deploy\ (viz DEPLOYMENT.md).' -ForegroundColor Yellow
