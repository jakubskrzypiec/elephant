param(
  [ValidateSet('Start','Setup','Mail')][string]$Action = 'Start',
  [int]$Port = 4181,
  [string]$PrivateDirectory = (Join-Path $env:LOCALAPPDATA 'ElephantPanel/local')
)
$ErrorActionPreference = 'Stop'
$runtimePath = $env:ELEPHANT_PHP
if (-not $runtimePath) {
  $phpCommand = Get-Command php -ErrorAction SilentlyContinue
  if ($phpCommand) { $runtimePath = $phpCommand.Source }
  else { $runtimePath = Join-Path $env:LOCALAPPDATA 'ElephantPanel/tools/php/php.exe' }
}
if (-not (Test-Path -LiteralPath $runtimePath)) { throw 'Install PHP 8.4 with PDO SQLite, PDO MySQL, GD, Fileinfo, OpenSSL and mbstring; set ELEPHANT_PHP.' }
$projectRoot = Split-Path $PSScriptRoot -Parent
$env:ELEPHANT_CONFIG = Join-Path $PrivateDirectory 'config.php'
if ($Action -eq 'Setup' -or -not (Test-Path -LiteralPath $env:ELEPHANT_CONFIG)) {
  & $runtimePath (Join-Path $PSScriptRoot 'setup-local.php') $PrivateDirectory $Port
  if ($LASTEXITCODE -ne 0) { throw 'Local setup failed.' }
}
if ($Action -eq 'Start') {
  Write-Output "Website: http://127.0.0.1:$Port/ | Panel: http://127.0.0.1:$Port/panel/"
  & $runtimePath -S "127.0.0.1:$Port" -t $projectRoot (Join-Path $PSScriptRoot 'local-router.php')
}
elseif ($Action -eq 'Mail') {
  & $runtimePath (Join-Path $PSScriptRoot 'worker.php')
}
