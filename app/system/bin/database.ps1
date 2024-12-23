param(
    [string]$in,
    [string]$tatiye,
    [string]$ComputerName = "localhost",
    [string]$DBHost,
    [string]$DBUser,
    [string]$DBPass,
    [string]$DBName
)

# Ubah path untuk membaca package.json dari folder server
try {
    $serverPath = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
    $packagePath = Join-Path $serverPath "package.json"
    if (Test-Path $packagePath) {
        $packageConfig = Get-Content $packagePath -Raw | ConvertFrom-Json
        $MySQLPath = $packageConfig.MySQLPath
    } else {
        Write-Host "Error: File package.json tidak ditemukan di: $packagePath" -ForegroundColor Red
        exit 1
    }
} catch {
    Write-Host "Error: Gagal membaca package.json: $_" -ForegroundColor Red
    exit 1
}

# Tidak perlu konversi JSON lagi
$envVars = @{
    'DB_HOST' = $DBHost
    'DB_USER' = $DBUser
    'DB_PASS' = $DBPass
    'DB_NAME' = $DBName
}

$separatorIN="/"
$row=$in.Split($separatorIN)
$TarPath = $PSScriptRoot
$Utput=$tatiye.replace("=","")
$segment=$row[0]
$stream=$row[1]
if($in -eq "env") {
    # Tampilkan header
    Write-Host "`n===  KONFIGURASI .ENV ===`n" -ForegroundColor Green
    $envVars.GetEnumerator() | Sort-Object Key | ForEach-Object {
        $key = $_.Key
        $value = $_.Value
        Write-Host "$key" -ForegroundColor Yellow -NoNewline
        Write-Host " = " -NoNewline
        Write-Host "$value" -ForegroundColor Cyan
    }
    Write-Host "`n=== AKHIR KONFIGURASI ===`n" -ForegroundColor Green
} elseif($in -eq "dir") {
    # Tampilkan header
    Write-Host "`n=== ISI DIREKTORI ===`n" -ForegroundColor Green
    
    # Gunakan $PSScriptRoot untuk mendapatkan path secara otomatis
    
    Write-Host "Path: $currentPath`n" -ForegroundColor Yellow
    
    # Ambil semua file dan direktori
    $items = Get-ChildItem -Path $currentPath
    
    # Tampilkan setiap item dengan format yang rapi
    foreach ($item in $items) {
        if ($item.PSIsContainer) {
            # Jika item adalah folder
            Write-Host "[DIR] " -ForegroundColor Blue -NoNewline
            Write-Host "$($item.Name)" -ForegroundColor Yellow
        } else {
            # Jika item adalah file
            Write-Host "[FILE] " -ForegroundColor Cyan -NoNewline
            Write-Host "$($item.Name)" -ForegroundColor White
        }
    }
    
    Write-Host "`n=== TOTAL: $($items.Count) item(s) ===`n" -ForegroundColor Green
} elseif($in -eq "tables") {
    try {
        # Gunakan $MySQLPath dari package.json
        $MySQLHost = $envVars['DB_HOST']
        $MySQLUser = $envVars['DB_USER'] 
        $MySQLPass = $envVars['DB_PASS']
        $MySQLDB = $envVars['DB_NAME']

        # Buat file konfigurasi sementara untuk MySQL di folder TEMP
        $tmpConfigPath = Join-Path $env:TEMP "mysql_config.cnf"
        @"
[client]
host=$MySQLHost
user=$MySQLUser
password=$MySQLPass
"@ | Out-File -FilePath $tmpConfigPath -Encoding ASCII

        # Query untuk mendapatkan informasi detail tabel
        $query = @"
SELECT 
    TABLE_NAME as 'Table',
    TABLE_ROWS as 'Rows',
    ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) as 'Size_MB',
    ROUND(INDEX_LENGTH / 1024 / 1024, 2) as 'Index_MB',
    UPDATE_TIME as 'Last_Update',
    ENGINE as 'Engine',
    TABLE_COLLATION as 'Collation'
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = '$MySQLDB'
ORDER BY TABLE_ROWS DESC;
"@
        
        # Gunakan $MySQLPath untuk command mysql
        $command = "& '$MySQLPath\mysql' --defaults-file=`"$tmpConfigPath`" -N -B $MySQLDB -e `"$query`""
        
        Write-Host "`n=== ANALISIS POTENSI DATABASE $MySQLDB ===`n" -ForegroundColor Green
        
        # Header untuk statistik umum
        Write-Host "STATISTIK UMUM:" -ForegroundColor Cyan
        Write-Host "".PadRight(75,"-") -ForegroundColor Gray
        
        # Eksekusi query dan tampilkan hasil
        $tables = $null
        $tables = Invoke-Expression "$command 2>&1" | Where-Object { $_ -notmatch '^mysql: \[Warning\]' }

        # Query untuk analisis storage
        $storageQuery = @"
SELECT 
    SUM(DATA_LENGTH) / 1024 / 1024 as data_size,
    SUM(INDEX_LENGTH) / 1024 / 1024 as index_size,
    SUM(DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024 as total_size,
    COUNT(*) as total_tables,
    SUM(TABLE_ROWS) as total_rows
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = '$MySQLDB';
"@
        
        $storageCommand = "& '$MySQLPath\mysql' --defaults-file=`"$tmpConfigPath`" -N -B $MySQLDB -e `"$storageQuery`""
        $storageInfo = Invoke-Expression "$storageCommand 2>&1" | Where-Object { $_ -notmatch '^mysql: \[Warning\]' }
        $storageData = $storageInfo -split "\t"

        # Tampilkan statistik storage
        Write-Host "Penggunaan Storage:" -ForegroundColor Yellow
        Write-Host "[1] Total Data: $("{0:N2}" -f [float]$storageData[0]) MB" -ForegroundColor White
        Write-Host "[2] Total Index: $("{0:N2}" -f [float]$storageData[1]) MB" -ForegroundColor White
        Write-Host "[3] Total Ukuran: $("{0:N2}" -f [float]$storageData[2]) MB" -ForegroundColor White
        Write-Host "[4] Rasio Index/Data: $("{0:P2}" -f ([float]$storageData[1]/[float]$storageData[0]))" -ForegroundColor White

        Write-Host "`nStatistik Tabel:" -ForegroundColor Yellow
        Write-Host "[1] Jumlah Tabel: $($storageData[3])" -ForegroundColor White
        Write-Host "[2] Total Baris: $("{0:N0}" -f [float]$storageData[4])" -ForegroundColor White
        Write-Host "[3] Rata-rata Baris/Tabel: $("{0:N0}" -f ([float]$storageData[4]/[float]$storageData[3]))" -ForegroundColor White

        # Tampilkan 5 tabel terbesar
        Write-Host "`nTOP 5 TABEL TERBESAR:" -ForegroundColor Cyan
        Write-Host "".PadRight(75,"-") -ForegroundColor Gray
        Write-Host ("NO".PadRight(5)) -NoNewline -ForegroundColor Yellow
        Write-Host ("NAMA TABEL".PadRight(40)) -NoNewline -ForegroundColor Yellow
        Write-Host ("JUMLAH BARIS".PadRight(20)) -NoNewline -ForegroundColor Yellow
        Write-Host "UKURAN (MB)" -ForegroundColor Yellow

        $tableNo = 1
        $tables | Select-Object -First 5 | ForEach-Object {
            $tableInfo = $_ -split "\t"
            Write-Host ("[$tableNo]".PadRight(5)) -NoNewline -ForegroundColor Cyan
            Write-Host ($tableInfo[0].PadRight(40)) -NoNewline -ForegroundColor White
            Write-Host ($tableInfo[1].ToString().PadRight(20)) -NoNewline -ForegroundColor White
            Write-Host ("{0:N2}" -f [float]$tableInfo[2]) -ForegroundColor Green
            $tableNo++
        }

        # Rekomendasi
        Write-Host "`nREKOMENDASI OPTIMASI:" -ForegroundColor Magenta
        Write-Host "".PadRight(75,"-") -ForegroundColor Gray
        
        $rekomNo = 1
        # Analisis rasio index
        if ([float]$storageData[1]/[float]$storageData[0] > 0.5) {
            Write-Host "[$rekomNo] Index terlalu besar! Pertimbangkan untuk mengoptimasi index" -ForegroundColor Red
            $rekomNo++
        }
        
        # Analisis ukuran tabel
        if ([float]$storageData[2] > 1000) {
            Write-Host "[$rekomNo] Database cukup besar (>1GB). Pertimbangkan untuk melakukan partisi tabel" -ForegroundColor Yellow
            $rekomNo++
        }
        
        # Analisis distribusi data
        if ([float]$storageData[4]/[float]$storageData[3] > 1000000) {
            Write-Host "[$rekomNo] Beberapa tabel memiliki jumlah baris yang sangat besar. Pertimbangkan untuk melakukan archiving" -ForegroundColor Yellow
        }

        Write-Host "`n=== AKHIR ANALISIS ===`n" -ForegroundColor Green

        # Hapus file konfigurasi temporary
        if (Test-Path $tmpConfigPath) {
            Remove-Item -Path $tmpConfigPath -Force
        }
    } catch {
        Write-Host "Terjadi kesalahan saat menganalisis database: $_" -ForegroundColor Red
    }
} elseif ($in -match "^desc\s+(.+)$") {
    try {
        # Ambil nama tabel dari parameter
        $tableName = $Matches[1]
        
        # Gunakan path MySQL secara langsung
        $MySQLHost = $envVars['DB_HOST']
        $MySQLUser = $envVars['DB_USER'] 
        $MySQLPass = $envVars['DB_PASS']
        $MySQLDB = $envVars['DB_NAME']

        # Buat koneksi langsung menggunakan mysql.exe
        $mysqlCmd = "mysql -h$MySQLHost -u$MySQLUser -p$MySQLPass $MySQLDB -e"
        
        Write-Host "`n=== STRUKTUR TABEL: $tableName ===`n" -ForegroundColor Green

        # Query untuk struktur tabel
        $query = "DESCRIBE $tableName;"
        $result = Invoke-Expression "$mysqlCmd `"$query`"" 2>&1

        if ($result -match "ERROR") {
            Write-Host "Error: Tabel '$tableName' tidak ditemukan atau akses ditolak" -ForegroundColor Red
        } else {
            # Tampilkan header
            Write-Host "FIELD".PadRight(30) -NoNewline -ForegroundColor Yellow
            Write-Host "TYPE".PadRight(20) -NoNewline -ForegroundColor Yellow
            Write-Host "NULL".PadRight(10) -NoNewline -ForegroundColor Yellow
            Write-Host "KEY".PadRight(10) -NoNewline -ForegroundColor Yellow
            Write-Host "DEFAULT".PadRight(15) -NoNewline -ForegroundColor Yellow
            Write-Host "EXTRA" -ForegroundColor Yellow
            Write-Host "".PadRight(90,"-") -ForegroundColor Gray

            # Parse dan tampilkan hasil
            $result | ForEach-Object {
                $cols = $_ -split "\t"
                if ($cols.Count -ge 6) {
                    Write-Host $cols[0].PadRight(30) -NoNewline -ForegroundColor White
                    Write-Host $cols[1].PadRight(20) -NoNewline -ForegroundColor Cyan
                    Write-Host $cols[2].PadRight(10) -NoNewline -ForegroundColor White
                    Write-Host $cols[3].PadRight(10) -NoNewline -ForegroundColor Yellow
                    Write-Host $cols[4].PadRight(15) -NoNewline -ForegroundColor White
                    Write-Host $cols[5] -ForegroundColor Green
                }
            }

            # Tampilkan informasi tambahan
            Write-Host "`nINFORMASI TAMBAHAN:" -ForegroundColor Yellow
            Write-Host "".PadRight(50,"-") -ForegroundColor Gray

            # Query untuk informasi tabel
            $infoQuery = "SELECT ENGINE, TABLE_ROWS, DATA_LENGTH FROM information_schema.TABLES WHERE TABLE_SCHEMA='$MySQLDB' AND TABLE_NAME='$tableName';"
            $tableInfo = Invoke-Expression "$mysqlCmd `"$infoQuery`"" 2>&1
            
            if ($tableInfo -notmatch "ERROR") {
                $info = $tableInfo -split "\t"
                if ($info.Count -ge 3) {
                    Write-Host "[1] Engine: $($info[0])" -ForegroundColor White
                    Write-Host "[2] Jumlah Baris: $($info[1])" -ForegroundColor White
                    
                    # Konversi ukuran data dengan penanganan error
                    try {
                        $dataLength = [double]$info[2]
                        $dataSizeMB = [math]::Round($dataLength/1024/1024, 2)
                        Write-Host "[3] Ukuran Data: $dataSizeMB MB" -ForegroundColor White
                    } catch {
                        Write-Host "[3] Ukuran Data: Tidak dapat dihitung" -ForegroundColor Yellow
                    }
                }
            }

        }

        Write-Host "`n=== AKHIR STRUKTUR TABEL ===`n" -ForegroundColor Green
    }
    catch {
        Write-Host "Terjadi kesalahan: $_" -ForegroundColor Red
    }
} elseif ($in -match "^last\s+(.+)$") {
    try {
        # Ambil nama tabel dari parameter
        $tableName = $Matches[1]
        
        # Ambil kredensial dari env vars
        $MySQLHost = $envVars['DB_HOST']
        $MySQLUser = $envVars['DB_USER'] 
        $MySQLPass = $envVars['DB_PASS']
        $MySQLDB = $envVars['DB_NAME']
        
        Write-Host "`n=== DATA TERAKHIR TABEL: $tableName ===`n" -ForegroundColor Green

        # Buat koneksi langsung menggunakan mysql.exe dengan password
        $mysqlCmd = "mysql -h$MySQLHost -u$MySQLUser -p$MySQLPass $MySQLDB -e"

        # Query untuk mendapatkan nama kolom
        $columnQuery = "$mysqlCmd `"SHOW COLUMNS FROM $tableName`" --skip-column-names"
        $columns = Invoke-Expression $columnQuery 2>&1 | Where-Object { $_ -notmatch "Warning" } | ForEach-Object { ($_ -split "\t")[0] }

        # Query untuk data terakhir
        $dataQuery = "$mysqlCmd `"SELECT * FROM $tableName ORDER BY id DESC LIMIT 1`" --skip-column-names"
        $lastData = Invoke-Expression $dataQuery 2>&1 | Where-Object { $_ -notmatch "Warning" }
        $values = $lastData -split "\t"

        Write-Host "DATA TERAKHIR:" -ForegroundColor Cyan
        Write-Host "".PadRight(50,"-") -ForegroundColor Gray

        # Tampilkan data dalam format vertikal
        for ($i = 0; $i -lt $columns.Count; $i++) {
            $columnName = $columns[$i]
            $value = $values[$i]
            
            Write-Host ("[" + ($i + 1) + "] ") -NoNewline -ForegroundColor Yellow
            Write-Host ($columnName.PadRight(20)) -NoNewline -ForegroundColor Cyan
            Write-Host ": " -NoNewline
            Write-Host $value -ForegroundColor White
        }

        # Query untuk statistik
        $statsQuery = "$mysqlCmd `"SELECT COUNT(*) as total, MAX(id) as last_id FROM $tableName`" --skip-column-names"
        $stats = Invoke-Expression $statsQuery 2>&1 | Where-Object { $_ -notmatch "Warning" }
        $statsValues = $stats -split "\t"

        Write-Host "`nINFORMASI TAMBAHAN:" -ForegroundColor Yellow
        Write-Host "".PadRight(50,"-") -ForegroundColor Gray
        Write-Host "[1] Total Data    : $($statsValues[0]) baris" -ForegroundColor White
        Write-Host "[2] ID Terakhir   : $($statsValues[1])" -ForegroundColor White

        Write-Host "`n=== AKHIR DATA TERAKHIR ===`n" -ForegroundColor Green
    }
    catch {
        Write-Host "Terjadi kesalahan: $_" -ForegroundColor Red
    }
} elseif ($in -match "^count\s+(.+)$") {
    try {
        $tableName = $Matches[1]
        $query = "SELECT COUNT(*) as total FROM $tableName;"
        $command = "& '$MySQLPath\mysql' --defaults-file=`"$tmpConfigPath`" -N -B $MySQLDB -e `"$query`""
        $result = Invoke-Expression $command
        
        Write-Host "`n=== JUMLAH DATA TABEL: $tableName ===`n" -ForegroundColor Green
        Write-Host "Total Records: $result" -ForegroundColor Cyan
        Write-Host "`n=== SELESAI ===`n" -ForegroundColor Green
    } catch {
        Write-Host "Error: $_" -ForegroundColor Red
    }
} elseif ($in -match "^size\s+(.+)$") {
    try {
        $tableName = $Matches[1]
        $query = @"
SELECT 
    ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) AS 'Size (MB)',
    ROUND((DATA_LENGTH / 1024 / 1024), 2) AS 'Data (MB)',
    ROUND((INDEX_LENGTH / 1024 / 1024), 2) AS 'Index (MB)'
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = '$MySQLDB' 
AND TABLE_NAME = '$tableName';
"@
        $command = "& '$MySQLPath\mysql' --defaults-file=`"$tmpConfigPath`" -N -B $MySQLDB -e `"$query`""
        $result = Invoke-Expression $command
        $sizes = $result -split "\t"
        
        Write-Host "`n=== UKURAN TABEL: $tableName ===`n" -ForegroundColor Green
        Write-Host "Total Size : $($sizes[0]) MB" -ForegroundColor Cyan
        Write-Host "Data Size  : $($sizes[1]) MB" -ForegroundColor White
        Write-Host "Index Size : $($sizes[2]) MB" -ForegroundColor Yellow
        Write-Host "`n=== SELESAI ===`n" -ForegroundColor Green
    } catch {
        Write-Host "Error: $_" -ForegroundColor Red
    }
} elseif ($in -match "^top\s+(\w+)\s+(\d+)$") {
    try {
        $tableName = $Matches[1]
        $limit = $Matches[2]
        $query = "SELECT * FROM $tableName LIMIT $limit;"
        $command = "& '$MySQLPath\mysql' --defaults-file=`"$tmpConfigPath`" -N -B $MySQLDB -e `"$query`""
        $results = Invoke-Expression $command
        
        Write-Host "`n=== TOP $limit DATA DARI: $tableName ===`n" -ForegroundColor Green
        $results | ForEach-Object {
            $data = $_ -split "\t"
            Write-Host ($data -join " | ") -ForegroundColor Cyan
        }
        Write-Host "`n=== SELESAI ===`n" -ForegroundColor Green
    } catch {
        Write-Host "Error: $_" -ForegroundColor Red
    }
} elseif ($in -match "^backup\s+(.+)$") {
    try {
        $tableName = $Matches[1]
        $backupPath = Join-Path $PSScriptRoot "backups"
        if (-not (Test-Path $backupPath)) {
            New-Item -ItemType Directory -Path $backupPath
        }
        
        $timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
        $outputFile = Join-Path $backupPath "${tableName}_${timestamp}.sql"
        
        $command = "& '$MySQLPath\mysqldump' --defaults-file=`"$tmpConfigPath`" $MySQLDB $tableName > `"$outputFile`""
        Invoke-Expression $command
        
        Write-Host "`n=== BACKUP TABEL: $tableName ===`n" -ForegroundColor Green
        Write-Host "Backup berhasil disimpan di: $outputFile" -ForegroundColor Cyan
        Write-Host "`n=== SELESAI ===`n" -ForegroundColor Green
    } catch {
        Write-Host "Error: $_" -ForegroundColor Red
    }
} elseif ($in -match "^truncate\s+(.+)$") {
    try {
        $tableName = $Matches[1]
        
        # Buat file konfigurasi sementara untuk MySQL
        $tmpConfigPath = Join-Path $env:TEMP "mysql_config.cnf"
        
        Write-Host "`n=== PERINGATAN! ===" -ForegroundColor Red
        Write-Host "Anda akan mengosongkan tabel: $tableName" -ForegroundColor Yellow
        Write-Host "Semua data akan dihapus dan tidak dapat dikembalikan!" -ForegroundColor Red
        Write-Host "".PadRight(50,"-") -ForegroundColor Gray
        $confirm = Read-Host "Ketik 'YES' untuk melanjutkan atau tekan Enter untuk membatalkan"
        
        if ($confirm -eq "YES") {
            # Buat file konfigurasi MySQL sementara
            @"
[client]
host=$($envVars['DB_HOST'])
user=$($envVars['DB_USER'])
password=$($envVars['DB_PASS'])
"@ | Out-File -FilePath $tmpConfigPath -Encoding ASCII

            # Query untuk truncate tabel
            $truncateQuery = "TRUNCATE TABLE $tableName;"
            $command = "& '$MySQLPath\mysql' --defaults-file=`"$tmpConfigPath`" -N -B $($envVars['DB_NAME']) -e `"$truncateQuery`""
            Invoke-Expression $command
            
            Write-Host "`nTabel $tableName berhasil dikosongkan!" -ForegroundColor Green
        } else {
            Write-Host "`nOperasi dibatalkan." -ForegroundColor Yellow
        }
        
        Write-Host "`n=== SELESAI ===`n" -ForegroundColor Green
    } catch {
        Write-Host "Error: $_" -ForegroundColor Red
    } finally {
        # Hapus file konfigurasi temporary jika ada
        if (Test-Path $tmpConfigPath) {
            Remove-Item -Path $tmpConfigPath -Force
        }
    }
} elseif ($in -match "^create\s+(.+)$") {
    try {
        $tableName = $Matches[1]
        
        # Baca definisi tabel dari tabel.json di folder server
        $serverPath = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
        $tablePath = Join-Path $serverPath "tabel.json"
        if (Test-Path $tablePath) {
            $tableConfig = Get-Content $tablePath -Raw | ConvertFrom-Json
            
            # Cek apakah tabel ada dalam konfigurasi
            if ($tableConfig.PSObject.Properties.Name -contains $tableName) {
                $tableDefinition = $tableConfig.$tableName[0]
                
                # Buat query CREATE TABLE
                $columns = @()
                foreach ($prop in $tableDefinition.PSObject.Properties) {
                    $columns += "`n    $($prop.Name) $($prop.Value)"
                }
                
                # Tambahkan PRIMARY KEY untuk kolom id
                $columns += "`n    PRIMARY KEY (id)"
                
                $createQuery = @"
CREATE TABLE IF NOT EXISTS $tableName ($($columns -join ','));
"@
                
                # Buat file konfigurasi sementara untuk MySQL
                $tmpConfigPath = Join-Path $env:TEMP "mysql_config.cnf"
                @"
[client]
host=$($envVars['DB_HOST'])
user=$($envVars['DB_USER'])
password=$($envVars['DB_PASS'])
"@ | Out-File -FilePath $tmpConfigPath -Encoding ASCII

                # Eksekusi query CREATE TABLE
                $command = "& '$MySQLPath\mysql' --defaults-file=`"$tmpConfigPath`" -N -B $($envVars['DB_NAME']) -e `"$createQuery`""
                Invoke-Expression $command
                
                Write-Host "`n=== MEMBUAT TABEL: $tableName ===`n" -ForegroundColor Green
                Write-Host "Query yang dijalankan:" -ForegroundColor Yellow
                Write-Host $createQuery -ForegroundColor Cyan
                Write-Host "`nTabel berhasil dibuat!" -ForegroundColor Green
                
                # Tampilkan struktur tabel yang baru dibuat
                Write-Host "`nStruktur Tabel:" -ForegroundColor Yellow
                $descQuery = "DESCRIBE $tableName;"
                $command = "& '$MySQLPath\mysql' --defaults-file=`"$tmpConfigPath`" -N -B $($envVars['DB_NAME']) -e `"$descQuery`""
                $result = Invoke-Expression $command
                $result | ForEach-Object {
                    $fields = $_ -split "`t"
                    Write-Host "[$($fields[0])] " -NoNewline -ForegroundColor Cyan
                    Write-Host "$($fields[1]) " -NoNewline -ForegroundColor White
                    Write-Host "$($fields[2]) " -NoNewline -ForegroundColor Yellow
                    Write-Host "$($fields[3])" -ForegroundColor Green
                }
            } else {
                Write-Host "Error: Tabel '$tableName' tidak ditemukan dalam konfigurasi" -ForegroundColor Red
            }
        } else {
            Write-Host "Error: File tabel.json tidak ditemukan di: $tablePath" -ForegroundColor Red
        }
        
        Write-Host "`n=== SELESAI ===`n" -ForegroundColor Green
    } catch {
        Write-Host "Error: $_" -ForegroundColor Red
    } finally {
        # Hapus file konfigurasi temporary jika ada
        if (Test-Path $tmpConfigPath) {
            Remove-Item -Path $tmpConfigPath -Force
        }
    }
} elseif ($in -match "^alter\s+(.+)$") {
    try {
        $tableName = $Matches[1]
        
        # Baca definisi tabel dari tabel.json
        $tablePath = Join-Path $PSScriptRoot "tabel.json"
        if (Test-Path $tablePath) {
            $tableConfig = Get-Content $tablePath -Raw | ConvertFrom-Json
            
            # Cek apakah tabel ada dalam konfigurasi
            if ($tableConfig.PSObject.Properties.Name -contains $tableName) {
                $tableDefinition = $tableConfig.$tableName[0]
                
                # Buat file konfigurasi sementara untuk MySQL
                $tmpConfigPath = Join-Path $env:TEMP "mysql_config.cnf"
                @"
[client]
host=$($envVars['DB_HOST'])
user=$($envVars['DB_USER'])
password=$($envVars['DB_PASS'])
"@ | Out-File -FilePath $tmpConfigPath -Encoding ASCII

                # Dapatkan struktur tabel yang ada
                $descQuery = "DESCRIBE $tableName;"
                $command = "& '$MySQLPath\mysql' --defaults-file=`"$tmpConfigPath`" -N -B $($envVars['DB_NAME']) -e `"$descQuery`""
                $existingColumns = @{}
                Invoke-Expression $command | ForEach-Object {
                    $fields = $_ -split "`t"
                    $existingColumns[$fields[0]] = $fields[1]
                }

                Write-Host "`n=== MEMPERBARUI TABEL: $tableName ===`n" -ForegroundColor Green
                
                # Daftar kolom yang ada di tabel.json
                $newColumns = @{}
                $tableDefinition.PSObject.Properties | ForEach-Object {
                    $newColumns[$_.Name] = $_.Value
                }

                # Cek kolom yang perlu dihapus (ada di database tapi tidak ada di tabel.json)
                foreach ($existingColumn in $existingColumns.Keys) {
                    if (-not $newColumns.ContainsKey($existingColumn) -and $existingColumn -ne "id") {
                        Write-Host "`n=== PERINGATAN! ===" -ForegroundColor Red
                        Write-Host "Kolom [$existingColumn] akan dihapus dari tabel $tableName" -ForegroundColor Yellow
                        Write-Host "Data dalam kolom ini akan hilang permanen!" -ForegroundColor Red
                        Write-Host "".PadRight(50,"-") -ForegroundColor Gray
                        $confirm = Read-Host "Ketik 'YES' untuk menghapus kolom atau tekan Enter untuk membatalkan"
                        
                        if ($confirm -eq "YES") {
                            $dropQuery = "ALTER TABLE $tableName DROP COLUMN $existingColumn;"
                            $command = "& '$MySQLPath\mysql' --defaults-file=`"$tmpConfigPath`" -N -B $($envVars['DB_NAME']) -e `"$dropQuery`""
                            Invoke-Expression $command
                            Write-Host "Kolom dihapus: [$existingColumn]" -ForegroundColor Red
                        } else {
                            Write-Host "Penghapusan kolom [$existingColumn] dibatalkan." -ForegroundColor Yellow
                        }
                    }
                }
                
                # Bandingkan dan buat ALTER TABLE statements untuk kolom yang ada/baru
                foreach ($prop in $tableDefinition.PSObject.Properties) {
                    $columnName = $prop.Name
                    $columnDef = $prop.Value
                    
                    if ($existingColumns.ContainsKey($columnName)) {
                        # Kolom sudah ada, cek apakah perlu diubah
                        if ($existingColumns[$columnName] -ne $columnDef) {
                            $alterQuery = "ALTER TABLE $tableName MODIFY COLUMN $columnName $columnDef;"
                            $command = "& '$MySQLPath\mysql' --defaults-file=`"$tmpConfigPath`" -N -B $($envVars['DB_NAME']) -e `"$alterQuery`""
                            Invoke-Expression $command
                            Write-Host "Kolom dimodifikasi: [$columnName] " -NoNewline -ForegroundColor Yellow
                            Write-Host $columnDef -ForegroundColor Cyan
                        }
                    } else {
                        # Kolom baru, tambahkan
                        $alterQuery = "ALTER TABLE $tableName ADD COLUMN $columnName $columnDef;"
                        $command = "& '$MySQLPath\mysql' --defaults-file=`"$tmpConfigPath`" -N -B $($envVars['DB_NAME']) -e `"$alterQuery`""
                        Invoke-Expression $command
                        Write-Host "Kolom ditambahkan: [$columnName] " -NoNewline -ForegroundColor Green
                        Write-Host $columnDef -ForegroundColor Cyan
                    }
                }
                
                # Tampilkan struktur tabel yang baru
                Write-Host "`nStruktur Tabel Terbaru:" -ForegroundColor Yellow
                $descQuery = "DESCRIBE $tableName;"
                $command = "& '$MySQLPath\mysql' --defaults-file=`"$tmpConfigPath`" -N -B $($envVars['DB_NAME']) -e `"$descQuery`""
                $result = Invoke-Expression $command
                $result | ForEach-Object {
                    $fields = $_ -split "`t"
                    Write-Host "[$($fields[0])] " -NoNewline -ForegroundColor Cyan
                    Write-Host "$($fields[1]) " -NoNewline -ForegroundColor White
                    Write-Host "$($fields[2]) " -NoNewline -ForegroundColor Yellow
                    Write-Host "$($fields[3])" -ForegroundColor Green
                }
            } else {
                Write-Host "Error: Tabel '$tableName' tidak ditemukan dalam konfigurasi" -ForegroundColor Red
            }
        } else {
            Write-Host "Error: File tabel.json tidak ditemukan di: $tablePath" -ForegroundColor Red
        }
        
        Write-Host "`n=== SELESAI ===`n" -ForegroundColor Green
    } catch {
        Write-Host "Error: $_" -ForegroundColor Red
    } finally {
        # Hapus file konfigurasi temporary jika ada
        if (Test-Path $tmpConfigPath) {
            Remove-Item -Path $tmpConfigPath -Force
        }
    }
} elseif ($in -match "^export\s+(.+)$") {
    try {
        $tableName = $Matches[1]
        
        # Buat direktori backup jika belum ada
        $backupPath = Join-Path $PSScriptRoot "backup"
        if (-not (Test-Path $backupPath)) {
            New-Item -ItemType Directory -Path $backupPath | Out-Null
        }
        
        # Set nama file backup
        $backupFile = Join-Path $backupPath "${tableName}.sql"
        
        Write-Host "`n=== EXPORT TABEL: $tableName ===`n" -ForegroundColor Green
        Write-Host "Lokasi backup: $backupFile" -ForegroundColor Yellow
        
        # Buat file konfigurasi sementara untuk MySQL jika belum ada
        $tmpConfigPath = Join-Path $env:TEMP "mysql_config.cnf"
        @"
[client]
host=$($envVars['DB_HOST'])
user=$($envVars['DB_USER'])
password=$($envVars['DB_PASS'])
"@ | Out-File -FilePath $tmpConfigPath -Encoding ASCII

        # Export menggunakan mysqldump dengan opsi tambahan
        $command = "& '$MySQLPath\mysqldump' --defaults-file=`"$tmpConfigPath`" $MySQLDB $tableName > `"$backupFile`""
        
        Invoke-Expression $command
        
        if (Test-Path $backupFile) {
            $fileSize = (Get-Item $backupFile).Length / 1KB
            Write-Host "`nExport berhasil!" -ForegroundColor Green
            Write-Host "Ukuran file: $("{0:N2}" -f $fileSize) KB" -ForegroundColor Cyan
            
            # Tampilkan preview struktur tabel yang di-export
            Write-Host "`nStruktur Tabel yang Di-export:" -ForegroundColor Yellow
            $descQuery = "DESCRIBE $tableName;"
            $command = "& '$MySQLPath\mysql' --defaults-file=`"$tmpConfigPath`" -N -B $($envVars['DB_NAME']) -e `"$descQuery`""
            $result = Invoke-Expression $command
            $result | ForEach-Object {
                $fields = $_ -split "`t"
                Write-Host "[$($fields[0])] " -NoNewline -ForegroundColor Cyan
                Write-Host "$($fields[1]) " -NoNewline -ForegroundColor White
                Write-Host "$($fields[2]) " -NoNewline -ForegroundColor Yellow
                Write-Host "$($fields[3])" -ForegroundColor Green
            }
        } else {
            Write-Host "`nError: Gagal membuat file backup" -ForegroundColor Red
        }
        
        Write-Host "`n=== SELESAI ===`n" -ForegroundColor Green
    } catch {
        Write-Host "Error: $_" -ForegroundColor Red
    } finally {
        # Hapus file konfigurasi temporary jika ada
        if (Test-Path $tmpConfigPath) {
            Remove-Item -Path $tmpConfigPath -Force
        }
    }
} elseif ($in -eq "export-all") {
    try {
        # Buat direktori backup jika belum ada
        $backupPath = Join-Path $PSScriptRoot "backup"
        if (-not (Test-Path $backupPath)) {
            New-Item -ItemType Directory -Path $backupPath | Out-Null
        }
        
        # Set nama file backup dengan timestamp
        $timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
        $backupFile = Join-Path $backupPath "all_tables_$timestamp.sql"
        
        Write-Host "`n=== EXPORT SEMUA TABEL ===`n" -ForegroundColor Green
        Write-Host "Lokasi backup: $backupFile" -ForegroundColor Yellow
        
        # Buat file konfigurasi sementara untuk MySQL
        $tmpConfigPath = Join-Path $env:TEMP "mysql_config.cnf"
        @"
[client]
host=$($envVars['DB_HOST'])
user=$($envVars['DB_USER'])
password=$($envVars['DB_PASS'])
"@ | Out-File -FilePath $tmpConfigPath -Encoding ASCII

        # Dapatkan daftar semua tabel
        $query = "SHOW TABLES;"
        $command = "& '$MySQLPath\mysql' --defaults-file=`"$tmpConfigPath`" -N -B $($envVars['DB_NAME']) -e `"$query`""
        $tables = Invoke-Expression $command

        Write-Host "`nDaftar tabel yang akan di-export:" -ForegroundColor Cyan
        $tableCount = 0
        $tables | ForEach-Object {
            $tableCount++
            Write-Host "[$tableCount] $_" -ForegroundColor Yellow
        }

        Write-Host "`nMemulai proses export..." -ForegroundColor Green
        
        # Export menggunakan mysqldump dengan opsi tambahan
        $command = "& '$MySQLPath\mysqldump' --defaults-file=`"$tmpConfigPath`" " + `
                  "--add-drop-table --routines --triggers --events --single-transaction " + `
                  "--databases $($envVars['DB_NAME']) > `"$backupFile`""
        
        Invoke-Expression $command
        
        if (Test-Path $backupFile) {
            $fileSize = (Get-Item $backupFile).Length / 1MB
            Write-Host "`nExport berhasil!" -ForegroundColor Green
            Write-Host "Ukuran file: $("{0:N2}" -f $fileSize) MB" -ForegroundColor Cyan
            
            # Tampilkan ringkasan
            Write-Host "`nRingkasan Export:" -ForegroundColor Yellow
            Write-Host "Total Tabel: $tableCount" -ForegroundColor White
            Write-Host "Lokasi File: $backupFile" -ForegroundColor White
            Write-Host "Ukuran File: $("{0:N2}" -f $fileSize) MB" -ForegroundColor White
            Write-Host "Waktu: $(Get-Date -Format "dd/MM/yyyy HH:mm:ss")" -ForegroundColor White
            
            # Tampilkan isi file backup
            Write-Host "`nPreview 5 baris pertama file backup:" -ForegroundColor Yellow
            Get-Content $backupFile -TotalCount 5 | ForEach-Object {
                Write-Host $_ -ForegroundColor Gray
            }
        } else {
            Write-Host "`nError: Gagal membuat file backup" -ForegroundColor Red
        }
        
        Write-Host "`n=== SELESAI ===`n" -ForegroundColor Green
    } catch {
        Write-Host "Error: $_" -ForegroundColor Red
    } finally {
        # Hapus file konfigurasi temporary jika ada
        if (Test-Path $tmpConfigPath) {
            Remove-Item -Path $tmpConfigPath -Force
        }
    }
} elseif ($in -match "^import\s+(.+)$") {
    try {
        $sqlFile = $Matches[1]
        $backupPath = Join-Path $PSScriptRoot "backup"
        $importFile = Join-Path $backupPath "$sqlFile.sql"
        
        if (Test-Path $importFile) {
            Write-Host "`n=== IMPORT DATA ===`n" -ForegroundColor Green
            Write-Host "File: $importFile" -ForegroundColor Yellow
            
            $command = "& '$MySQLPath\mysql' --defaults-file=`"$tmpConfigPath`" $($envVars['DB_NAME']) < `"$importFile`""
            Invoke-Expression $command
            
            Write-Host "`nImport berhasil!" -ForegroundColor Green
        } else {
            Write-Host "Error: File SQL tidak ditemukan: $importFile" -ForegroundColor Red
        }
    } catch {
        Write-Host "Error: $_" -ForegroundColor Red
    }
    
} elseif ($in -eq "db help") {
    try {
        $docPath = Join-Path $PSScriptRoot "database.md"
        if (Test-Path $docPath) {
            $content = Get-Content $docPath -Raw
            
            Write-Host "`n=== DOKUMENTASI DATABASE ===`n" -ForegroundColor Green
            
            # Counter untuk penomoran list items
            $listCounter = 1
            
            # Parsing dan formatting markdown sederhana
            $content -split "`n" | ForEach-Object {
                $line = $_
                
                # Header 1
                if ($line -match '^# (.+)') {
                    Write-Host "`n$($Matches[1].ToUpper())" -ForegroundColor Cyan
                    Write-Host ("".PadRight($Matches[1].Length, "=")) -ForegroundColor Cyan
                    $listCounter = 1  # Reset counter untuk setiap section baru
                }
                # Header 2
                elseif ($line -match '^## (.+)') {
                    Write-Host "`n$($Matches[1])" -ForegroundColor Yellow
                    Write-Host ("".PadRight($Matches[1].Length, "-")) -ForegroundColor Yellow
                    $listCounter = 1  # Reset counter untuk setiap section baru
                }
                # Header 3
                elseif ($line -match '^### (.+)') {
                    Write-Host "`n$($Matches[1])" -ForegroundColor White
                    $listCounter = 1  # Reset counter untuk setiap subsection
                }
                # List item
                elseif ($line -match '^\- (.+)') {
                    Write-Host "[$listCounter] $($Matches[1])" -ForegroundColor Gray
                    $listCounter++
                }
                # Code block
                elseif ($line -match '^```') {
                    Write-Host ""
                }
                # Normal text
                else {
                    if ($line.Trim() -ne "") {
                        Write-Host $line -ForegroundColor Gray
                    }
                }
            }
            
            Write-Host "`n=== AKHIR DOKUMENTASI ===`n" -ForegroundColor Green
            
        } else {
            Write-Host "Error: File dokumentasi tidak ditemukan di: $docPath" -ForegroundColor Red
        }
    } catch {
        Write-Host "Error: $_" -ForegroundColor Red
    }
} elseif ($in -match "^create-view\s+(.+)$") {
    try {
        $viewName = $Matches[1]
        
        # Baca definisi view dari views.json di folder server
        $serverPath = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
        $viewPath = Join-Path $serverPath "views.json"
        if (Test-Path $viewPath) {
            $viewConfig = Get-Content $viewPath -Raw | ConvertFrom-Json
            
            if ($viewConfig.PSObject.Properties.Name -contains $viewName) {
                $viewDefinition = $viewConfig.$viewName
                
                # Buat query CREATE VIEW
                $createQuery = @"
CREATE OR REPLACE VIEW $viewName AS 
$($viewDefinition.query);
"@
                
                # Eksekusi query
                $command = "& '$MySQLPath\mysql' --defaults-file=`"$tmpConfigPath`" -N -B $($envVars['DB_NAME']) -e `"$createQuery`""
                Invoke-Expression $command
                
                Write-Host "`n=== MEMBUAT VIEW: $viewName ===`n" -ForegroundColor Green
                Write-Host "Deskripsi: $($viewDefinition.description)" -ForegroundColor Yellow
                Write-Host "`nQuery:" -ForegroundColor Cyan
                Write-Host $viewDefinition.query -ForegroundColor White
                Write-Host "`nView berhasil dibuat!" -ForegroundColor Green
            } else {
                Write-Host "Error: View '$viewName' tidak ditemukan dalam konfigurasi" -ForegroundColor Red
            }
        } else {
            Write-Host "Error: File views.json tidak ditemukan di: $viewPath" -ForegroundColor Red
        }
    } catch {
        Write-Host "Error: $_" -ForegroundColor Red
    }
} elseif ($in -match "^show-view\s+(.+)$") {
    try {
        $viewName = $Matches[1]
        
        # Query untuk melihat definisi view
        $query = "SHOW CREATE VIEW $viewName;"
        $command = "& '$MySQLPath\mysql' --defaults-file=`"$tmpConfigPath`" -N -B $($envVars['DB_NAME']) -e `"$query`""
        $result = Invoke-Expression $command
        
        Write-Host "`n=== DEFINISI VIEW: $viewName ===`n" -ForegroundColor Green
        Write-Host $result -ForegroundColor Cyan
    } catch {
        Write-Host "Error: $_" -ForegroundColor Red
    }
} elseif ($in -eq "list-views") {
    try {
        # Query untuk mendapatkan daftar view
        $query = @"
SELECT 
    TABLE_NAME as view_name,
    CREATE_TIME,
    UPDATE_TIME,
    CHECK_OPTION,
    IS_UPDATABLE
FROM information_schema.views 
WHERE TABLE_SCHEMA = '$($envVars['DB_NAME'])';
"@
        $command = "& '$MySQLPath\mysql' --defaults-file=`"$tmpConfigPath`" -N -B $($envVars['DB_NAME']) -e `"$query`""
        $views = Invoke-Expression $command
        
        Write-Host "`n=== DAFTAR VIEWS ===`n" -ForegroundColor Green
        
        $views | ForEach-Object {
            $viewInfo = $_ -split "`t"
            Write-Host "[$($viewInfo[0])]" -ForegroundColor Yellow
            Write-Host "Created : $($viewInfo[1])" -ForegroundColor White
            Write-Host "Updated : $($viewInfo[2])" -ForegroundColor White
            Write-Host "Updatable: $($viewInfo[4])" -ForegroundColor Cyan
            Write-Host "".PadRight(50,"-") -ForegroundColor Gray
        }
    } catch {
        Write-Host "Error: $_" -ForegroundColor Red
    }
} elseif ($in -match "^drop\s+(.+)$") {
    try {
        $tableName = $Matches[1]
        
        Write-Host "`n=== PERINGATAN! ===" -ForegroundColor Red
        Write-Host "Anda akan MENGHAPUS tabel: $tableName" -ForegroundColor Yellow
        Write-Host "Semua data akan dihapus dan tidak dapat dikembalikan!" -ForegroundColor Red
        Write-Host "".PadRight(50,"-") -ForegroundColor Gray
        $confirm = Read-Host "Ketik 'YES' untuk melanjutkan atau tekan Enter untuk membatalkan"
        
        if ($confirm -eq "YES") {
            # Buat file konfigurasi sementara untuk MySQL
            $tmpConfigPath = Join-Path $env:TEMP "mysql_config.cnf"
            @"
[client]
host=$($envVars['DB_HOST'])
user=$($envVars['DB_USER'])
password=$($envVars['DB_PASS'])
"@ | Out-File -FilePath $tmpConfigPath -Encoding ASCII

            # Query untuk menghapus tabel
            $dropQuery = "DROP TABLE IF EXISTS $tableName;"
            $command = "& '$MySQLPath\mysql' --defaults-file=`"$tmpConfigPath`" -N -B $($envVars['DB_NAME']) -e `"$dropQuery`""
            Invoke-Expression $command
            
            Write-Host "`nTabel $tableName berhasil dihapus!" -ForegroundColor Green
        } else {
            Write-Host "`nPenghapusan tabel dibatalkan." -ForegroundColor Yellow
        }
        
        Write-Host "`n=== SELESAI ===`n" -ForegroundColor Green
    } catch {
        Write-Host "Error: $_" -ForegroundColor Red
    } finally {
        # Hapus file konfigurasi temporary jika ada
        if (Test-Path $tmpConfigPath) {
            Remove-Item -Path $tmpConfigPath -Force
        }
    }
} else {
    # Kode untuk kondisi lainnya
    if($in -eq "apps") {
        # Kode apps
    }
}
