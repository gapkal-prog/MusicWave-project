param(
    [Parameter(Mandatory = $true)]
    [string]$Destination
)

$ErrorActionPreference = 'Stop'
$tool = Join-Path $PSScriptRoot 'package-release.php'
& php $tool ("--destination={0}" -f $Destination)
exit $LASTEXITCODE
