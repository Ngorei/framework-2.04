param(
    [string]$segment = "package",
    [string]$stream = "file",
    [string]$in = "monitor",
    [string]$tatiye = "system",
    [string]$envVars = "development"
)

# Dapatkan root path secara dinamis
$scriptPath = $PSScriptRoot # Path saat ini (app/system/bin)
$rootPath = Split-Path (Split-Path (Split-Path $scriptPath -Parent) -Parent) -Parent # Naik 3 level ke root
$configPath = Join-Path $rootPath ".env"

# Baca dan parse file .env
function Get-EnvConfig {
    param([string]$path)
    
    $config = @{}
    if (Test-Path $path) {
        Get-Content $path | ForEach-Object {
            if ($_ -match "^\s*([^#][^=]+)=(.*)$") {
                $key = $Matches[1].Trim()
                $value = $Matches[2].Trim(' "''')
                $config[$key] = $value
            }
        }
    } else {
        Write-Host "File .env tidak ditemukan di: $path" -ForegroundColor Red
        exit 1
    }
    return $config
}

# Fungsi untuk membersihkan URL dari port
function Clean-Url {
    param([string]$url)
    return $url -replace ':\d+$', ''
}

# Load konfigurasi dari .env
$envConfig = Get-EnvConfig $configPath

# Bersihkan PUBLIC_URL dari port
$envConfig.PUBLIC_URL = Clean-Url $envConfig.PUBLIC_URL

function Global:Invoke-RenamedRequest {
    param(
        [string]$changeType,
        [string]$path
    )
    
    $fileName = Split-Path $path -Leaf
    $folderPath = Split-Path $path -Parent
    $packagePath = Join-Path $rootPath "package" # Gunakan rootPath untuk package
    $relativePath = $folderPath.Replace($packagePath + "\", "")
    
    if (-not $relativePath.Contains("\") -or ($fileName -match "\.(json|php|html)$" -and $folderPath -eq $packagePath)) {
        return
    }
    
    $currentFolder = $relativePath.Split("\")[0]
    $status = "[$currentFolder] $changeType $fileName"
    Write-Host $status -NoNewline -ForegroundColor Yellow
    
    # Gunakan PUBLIC_URL yang sudah dibersihkan
    $Uri = "$($envConfig.PUBLIC_URL)/config"
    $postparam = @{
        segment = $segment
        stream = $stream
        base = $Uri
        param = $in
        user = $tatiye
        apps = $Uri
        file = $fileName
        path = $relativePath
    }
    
    try {
        $response = Invoke-RestMethod -Uri $Uri -Body $postparam -Method Post -TimeoutSec 30
        Write-Host " OK" -ForegroundColor Green
    } catch {
        Write-Host " ERROR" -ForegroundColor Red
        Write-Host "URL: $Uri" -ForegroundColor Red
        Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
    }
}

try {
    if ($envConfig.APP_ENV -ne "development") {
        Write-Host "Mode development diperlukan" -ForegroundColor Red
        return
    }

    Clear-Host
    $packagePath = Join-Path $rootPath "package" # Gunakan rootPath untuk package
    
    if (-not (Test-Path $packagePath)) {
        Write-Host "Folder package tidak ditemukan: $packagePath" -ForegroundColor Red
        return
    }
    
    Write-Host "=== MONITORING PACKAGE ===" -ForegroundColor Cyan
    Write-Host "Path: $packagePath" -ForegroundColor Yellow
    Write-Host "Status: Aktif" -ForegroundColor Green
    Write-Host "Server: $($envConfig.PUBLIC_URL)" -ForegroundColor Yellow
    Write-Host "Mode: $($envConfig.APP_ENV)" -ForegroundColor Yellow

    $watcher = New-Object System.IO.FileSystemWatcher
    $watcher.Path = $packagePath
    $watcher.IncludeSubdirectories = $true
    $watcher.EnableRaisingEvents = $true
    
    $action = {
        $path = $Event.SourceEventArgs.FullPath
        $changeType = $Event.SourceEventArgs.ChangeType
        
        switch ($changeType) {
            "Created" { Global:Invoke-RenamedRequest -changeType $changeType -path $path }
            "Deleted" { Global:Invoke-RenamedRequest -changeType $changeType -path $path }
            "Changed" { Global:Invoke-RenamedRequest -changeType $changeType -path $path }
            "Renamed" { Global:Invoke-RenamedRequest -changeType $changeType -path $path }
        }
    }

    $handlers = . {
        Register-ObjectEvent $watcher Created -Action $action
        Register-ObjectEvent $watcher Deleted -Action $action
        Register-ObjectEvent $watcher Changed -Action $action
        Register-ObjectEvent $watcher Renamed -Action $action
    }

    Write-Host "`nMonitoring aktif - Tekan Ctrl+C untuk berhenti" -ForegroundColor Yellow
    
    while ($true) { Start-Sleep -Seconds 1 }

} catch {
    Write-Host "Monitoring dihentikan" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
} finally {
    if ($handlers) {
        $handlers | ForEach-Object { Unregister-Event -SourceIdentifier $_.Name }
        Write-Host "Event handler dibersihkan" -ForegroundColor Gray
    }
    if ($watcher) {
        $watcher.Dispose()
        Write-Host "FileSystemWatcher dihentikan" -ForegroundColor Gray
    }
    Remove-Item Function:\Global:Invoke-RenamedRequest -ErrorAction SilentlyContinue
    Write-Host "MONITORING SELESAI" -ForegroundColor Cyan
}