param(
    [ValidateSet('check', 'migrate')]
    [string]$Action = 'check'
)

$ErrorActionPreference = 'Stop'

$rewardingRoot = Split-Path -Parent $PSScriptRoot
$hostRoot = Join-Path (Split-Path -Parent $rewardingRoot) 'App'
$resolver = Join-Path $hostRoot 'tools\resolve-database-url.php'

if (-not (Test-Path -LiteralPath $resolver -PathType Leaf)) {
    throw 'Host DATABASE_URL resolver was not found.'
}

$resolved = & php $resolver 2>&1
if ($LASTEXITCODE -ne 0) {
    throw 'Host DATABASE_URL resolution failed.'
}

$parts = @{}
foreach ($line in $resolved) {
    if ($line -notmatch '^(host|port|user|password)=(.+)$') {
        continue
    }

    $parts[$Matches[1]] = [Text.Encoding]::UTF8.GetString(
        [Convert]::FromBase64String($Matches[2])
    )
}

foreach ($required in @('host', 'port', 'user', 'password')) {
    if (-not $parts.ContainsKey($required)) {
        throw "Host DATABASE_URL resolver did not provide $required."
    }
}

function Encode-UriComponent([string]$Value) {
    return [Uri]::EscapeDataString($Value)
}

$hostName = Encode-UriComponent $parts['host']
$port = $parts['port']
$user = Encode-UriComponent $parts['user']
$password = Encode-UriComponent $parts['password']
$env:REWARD_DATA_DATABASE_URL = 'postgresql://' + $user + ':' + $password + '@' + $hostName + ':' + $port + '/app?serverVersion=16&charset=utf8'

Push-Location $rewardingRoot
try {
    if ('check' -eq $Action) {
        & php bin/console doctrine:migrations:status --no-interaction
        if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
        & php bin/console doctrine:migrations:up-to-date --no-interaction
        exit $LASTEXITCODE
    }

    & php bin/console doctrine:migrations:migrate --dry-run --no-interaction
    if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }
    & php bin/console doctrine:migrations:migrate --no-interaction
    exit $LASTEXITCODE
}
finally {
    Pop-Location
    Remove-Item Env:REWARD_DATA_DATABASE_URL -ErrorAction SilentlyContinue
}
