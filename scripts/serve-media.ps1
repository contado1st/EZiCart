param(
    [string]$BindAddress = '127.0.0.1',
    [ValidateRange(1024, 65535)]
    [int]$Port = 8000
)

# PHP's CLI server needs limits on the server process itself. These options
# affect this development server only and leave the installed php.ini alone.
$projectDirectory = Split-Path -Parent $PSScriptRoot
$publicDirectory = Join-Path $projectDirectory 'public'
$routerPath = Join-Path $projectDirectory 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'

Push-Location -LiteralPath $publicDirectory
try {
    & php -d upload_max_filesize=24M -d post_max_size=64M -S "${BindAddress}:$Port" $routerPath
} finally {
    Pop-Location
}
