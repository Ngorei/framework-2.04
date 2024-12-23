param(
    [string]$in,
    [string]$tatiye
)

# Baca file .env
$currentPath = $PSScriptRoot
$envContent = Get-Content "$currentPath\.env" -Raw

# Perbaiki parsing konten .env menjadi hashtable
$envVars = @{}
$envContent -split "`n" | ForEach-Object {
    $line = $_.Trim()
    # Skip baris kosong dan komentar
    if ($line -and !$line.StartsWith('#')) {
        # Cari posisi tanda = pertama
        $equalPos = $line.IndexOf('=')
        if ($equalPos -gt 0) {
            $key = $line.Substring(0, $equalPos).Trim()
            $value = $line.Substring($equalPos + 1).Trim()
            # Hapus tanda kutip jika ada
            $value = $value -replace '^["'']|["'']$'
            $envVars[$key] = $value
        }
    }
}

$separatorIN="/"
$row=$in.Split($separatorIN)
$TarPath = $PSScriptRoot
$Utput=$tatiye.replace("=","")
$segment=$row[0]
$stream=$row[1]

if($in -eq "env") {
    Clear-Host
    Write-Host "`n=== KONFIGURASI ENVIRONMENT NGOREI FRAMEWORK ===`n" -ForegroundColor Green

    # Definisikan kategori
    $categories = @{
        "DATABASE" = @("DB_HOST", "DB_NAME", "DB_USER", "DB_PASS")
        "SERVER" = @("SERVER_HOST", "SDK_PORT", "PUBLIC_URL", "API_URL")
        "APP" = @("APP_NAME", "APP_ENV", "APP_DEBUG", "APP_URL")
    }

    # Hitung panjang maksimum untuk alignment
    $maxKeyLength = ($envVars.Keys | Measure-Object -Maximum Length).Maximum

    # Tampilkan variabel berdasarkan kategori
    foreach ($category in $categories.Keys) {
        Write-Host "  [$category]" -ForegroundColor White
        
        $categoryVars = $envVars.GetEnumerator() | Where-Object { $categories[$category] -contains $_.Key }
        
        foreach ($var in $categoryVars) {
            Write-Host "  Variabel    : " -NoNewline
            Write-Host $var.Key -ForegroundColor Yellow
            Write-Host "  Nilai       : " -NoNewline
            Write-Host $var.Value -ForegroundColor Cyan
            Write-Host ""
        }
    }

    # Tampilkan variabel yang tidak masuk kategori
    $uncategorizedVars = $envVars.GetEnumerator() | Where-Object { 
        -not ($categories.Values | ForEach-Object { $_ -contains $_.Key })
    }

    if ($uncategorizedVars) {
        Write-Host "  [LAINNYA]" -ForegroundColor White
        foreach ($var in $uncategorizedVars) {
            Write-Host "  Variabel    : " -NoNewline
            Write-Host $var.Key -ForegroundColor Yellow
            Write-Host "  Nilai       : " -NoNewline
            Write-Host $var.Value -ForegroundColor Cyan
            Write-Host ""
        }
    }

    Write-Host "  Total Variabel: $($envVars.Count)" -ForegroundColor Green
    Write-Host "`n=== AKHIR KONFIGURASI ===`n" -ForegroundColor Green

} elseif($in -eq "config") {
    # Tampilkan header
    Write-Host "`n=== KONFIGURASI SERVER ===`n" -ForegroundColor Green
    
    # Tampilkan nilai-nilai konfigurasi
    Write-Host "PUBLIC_URL  = " -NoNewline -ForegroundColor Yellow
    Write-Host $envVars['PUBLIC_URL'] -ForegroundColor Cyan
    
    Write-Host "SERVER_HOST = " -NoNewline -ForegroundColor Yellow
    Write-Host $envVars['SERVER_HOST'] -ForegroundColor Cyan
    
    Write-Host "SDK_PORT    = " -NoNewline -ForegroundColor Yellow
    Write-Host $envVars['SDK_PORT'] -ForegroundColor Cyan 
    Write-Host "`n=== AKHIR KONFIGURASI ===`n" -ForegroundColor Green

} elseif($in -eq "dir") {
    # Tampilkan header
    Write-Host "`n=== ISI DIREKTORI ===`n" -ForegroundColor Green
    Write-Host "Path: $currentPath`n" -ForegroundColor Yellow
    
    # Format header tabel
    $headerFormat = "{0,-8} {1,-50} {2,-15} {3,-20}"
    $lineFormat = "{0,-8} {1,-50} {2,-15} {3,-20}"
    
    # Tampilkan header tabel
    Write-Host ($headerFormat -f "TIPE", "NAMA", "UKURAN", "TERAKHIR DIUBAH") -ForegroundColor Cyan
    Write-Host ("-" * 95) -ForegroundColor Gray
    
    # Ambil semua file dan direktori
    $items = Get-ChildItem -Path $currentPath
    
    # Fungsi untuk mengkonversi ukuran ke format yang mudah dibaca
    function Format-FileSize {
        param([long]$size)
        $suffix = "B", "KB", "MB", "GB", "TB"
        $index = 0
        while ($size -gt 1024 -and $index -lt ($suffix.Count - 1)) {
            $size = $size / 1024
            $index++
        }
        return "{0:N2} {1}" -f $size, $suffix[$index]
    }
    
    # Tampilkan setiap item dengan format tabel
    foreach ($item in $items) {
        $type = if ($item.PSIsContainer) { "[DIR]" } else { "[FILE]" }
        $name = $item.Name
        $size = if ($item.PSIsContainer) {
            # Hitung ukuran folder (opsional karena bisa memakan waktu untuk folder besar)
            $folderSize = (Get-ChildItem $item.FullName -Recurse -ErrorAction SilentlyContinue | 
                          Measure-Object -Property Length -Sum).Sum
            Format-FileSize $folderSize
        } else {
            Format-FileSize $item.Length
        }
        $lastModified = $item.LastWriteTime.ToString("yyyy-MM-dd HH:mm:ss")
        
        # Warna berbeda untuk folder dan file
        $itemColor = if ($item.PSIsContainer) { "Yellow" } else { "White" }
        $typeColor = if ($item.PSIsContainer) { "Blue" } else { "Cyan" }
        
        # Tampilkan baris dengan warna
        Write-Host ($lineFormat -f $type, $name, $size, $lastModified) -ForegroundColor $itemColor
    }
    
    Write-Host "`nTotal: $($items.Count) item(s)" -ForegroundColor Green
    
    # Hitung dan tampilkan total ukuran
    $totalSize = ($items | Where-Object { !$_.PSIsContainer } | 
                 Measure-Object -Property Length -Sum).Sum
    Write-Host "Total Ukuran: $(Format-FileSize $totalSize)`n" -ForegroundColor Green
    Write-Host "=== AKHIR DAFTAR ===`n" -ForegroundColor Green

} elseif($in -eq "ls") {
    Clear-Host
    Write-Host "`n=== DAFTAR PERINTAH NGOREI FRAMEWORK ===`n" -ForegroundColor Green

    # Definisikan kategori dan perintah
    $commands = @{
        "KONFIGURASI" = @(
            @{Name="env"; Desc="Menampilkan konfigurasi .env"},
            @{Name="config"; Desc="Konfigurasi server"}
        );
        "UTILITAS" = @(
            @{Name="dir"; Desc="Menampilkan isi direktori"},
            @{Name="ls"; Desc="Menampilkan daftar perintah"}
        )
    }

    # Hitung total perintah
    $totalCommands = 0
    foreach ($category in $commands.Keys) {
        $totalCommands += $commands[$category].Count
    }

    # Tampilkan setiap kategori
    foreach ($category in $commands.Keys) {
        Write-Host "  [$category]" -ForegroundColor White
        
        foreach ($cmd in $commands[$category]) {
            Write-Host "  Perintah    : " -NoNewline
            Write-Host "ngi $($cmd.Name)" -ForegroundColor Yellow
            Write-Host "  Keterangan  : " -NoNewline
            Write-Host "$($cmd.Desc)" -ForegroundColor Cyan
        }
        Write-Host ""
    }

    Write-Host "  Total Perintah: $totalCommands" -ForegroundColor Green
    Write-Host "`n=== AKHIR DAFTAR PERINTAH ===`n" -ForegroundColor Green
} elseif($in -eq "monitor") {
    # Panggil monitor.ps1
    $monitorScriptPath = Join-Path $PSScriptRoot "app\system\bin\monitor.ps1"
    if (Test-Path $monitorScriptPath) {
        & $monitorScriptPath -segment $segment -stream $stream -in $in -tatiye $tatiye -envVars $envVars
    } else {
        Write-Host "Error: File monitor.ps1 tidak ditemukan di: $monitorScriptPath" -ForegroundColor Red
    }
} elseif($in -match "^(tables|desc\s+.+|last\s+.+|count\s+.+|size\s+.+|top\s+\w+\s+\d+|backup\s+.+|truncate\s+.+|create\s+.+|alter\s+.+|export\s+.+|drop\s+.+|export-all|db help)$") {
    # Panggil database.ps1 untuk perintah database
    $databaseScriptPath = Join-Path $PSScriptRoot "app\system\bin\database.ps1"
    if (Test-Path $databaseScriptPath) {
        # Set working directory ke folder bin sebelum menjalankan script
        Push-Location (Split-Path $databaseScriptPath)
        try {
            # Teruskan environment variables sebagai string terpisah
            & $databaseScriptPath -in $in -tatiye $tatiye -ComputerName $ComputerName `
                -DBHost $envVars['DB_HOST'] `
                -DBUser $envVars['DB_USER'] `
                -DBPass $envVars['DB_PASS'] `
                -DBName $envVars['DB_NAME']
        }
        finally {
            # Kembalikan working directory ke lokasi semula
            Pop-Location
        }
    } else {
        Write-Host "Error: File database.ps1 tidak ditemukan di: $databaseScriptPath" -ForegroundColor Red
    }
}
