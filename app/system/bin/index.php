<?php
// Router untuk mengemulasi .htaccess
$url = parse_url($_SERVER["REQUEST_URI"]);
$path = $url["path"];

// Hapus trailing slash dan split path
$path = trim($path, '/');
$_GET['url'] = $path; // Menyimpan path ke $_GET['url']

// Implementasi aturan .htaccess:
if (empty($path)) {
    require dirname(__DIR__, 3) . "/app/index.php";
    return true;
} else {
    $file = __DIR__ . $url["path"];
    
    // Cek apakah ini file statis
    if (is_file($file)) {
        return false; // Serve file statis langsung
    }
    
    // Redirect semua request ke app/
    require dirname(__DIR__, 3) . "/app/index.php";
    return true;
}