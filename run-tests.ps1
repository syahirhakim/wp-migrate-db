Set-Location ( Join-Path $PSScriptRoot 'tests' )
composer test
if ( $LASTEXITCODE -ne 0 ) { exit $LASTEXITCODE }
