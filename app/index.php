<?php
// Memastikan file diakses langsung dari server
if (!defined('BASEPATH')) {
    define('BASEPATH', dirname(__DIR__));
}

// Load autoloader Composer terlebih dahulu
require_once(BASEPATH . '/vendor/autoload.php');
$host = filter_var($_SERVER["HTTP_HOST"], FILTER_SANITIZE_STRING);
// Tentukan protokol
$HTTP = isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== 'off' ? "https" : "http";
// Buat URL lengkap dengan sanitasi
echo $DOMAIN_HOST = $HTTP."://". $_SERVER["HTTP_HOST"].str_replace("index.php", "", $_SERVER["PHP_SELF"]);

// Kemudian baru load .env
try {
    if (!file_exists(BASEPATH . '/.env')) {
        throw new Exception('.env file tidak ditemukan!');
    }
    
    $dotenv = Dotenv\Dotenv::createImmutable(BASEPATH);
    $dotenv->load();
    
    // Validasi variabel wajib
    $dotenv->required([
        'TIMEZONE',
        'APP_NAME',
        'APP_ENV',
        'APP_DEBUG',
        'PUBLIC_URL',
        'APP_MOBILE_DETECT',
        'DB_HOST',
        'DB_NAME',
        'DB_USER',
        'DB_PORT',
        'DB_CHARSET'
    ]);

    // Definisi konstanta dengan dokumentasi
    /**
     * Konstanta aplikasi
     * ROOT: Path dasar aplikasi
     * APP: Path untuk folder aplikasi
     * CTRL: Path untuk controllers
     * HELPERS: Path untuk helper functions
     * PUBLIC_DIR: Path untuk file publik
     * PACKAGE_DIR: Path untuk package
     * HOST: Base URL aplikasi
     */
    define("TIMEZONE",             $_ENV['TIMEZONE']);
    define("APP_NAME",             $_ENV['APP_NAME']);
    define("VERSION",              $_ENV['APP_VERSION']);
    define("APP_ENV",              $_ENV['APP_ENV']);
    define("APP_DEBUG",            $_ENV['APP_DEBUG'] === 'true');
    define("APP_URL",              $_ENV['PUBLIC_URL']);
    define("ROOT",                 BASEPATH); 
    define("APP",                  BASEPATH.'/app/');
    define("ASSET",                BASEPATH.'/assets/');
    define("PUBLIC_DIR",           BASEPATH.'/public/');
    define("UPLOADS",              BASEPATH.'/uploads/');
    define("ROOT_PUBLIC",          BASEPATH.'/public');
    define("PACKAGE",              BASEPATH.'/package/');
    define("INIT",                 BASEPATH.'/system/alias');
    define("HOST",                 $_ENV['PUBLIC_URL']);
    define("HOST",                 $_ENV['PUBLIC_URL']);
    define("MOBILE_DETECT",        $_ENV['APP_MOBILE_DETECT']);
    


    // Definisi konstanta database
    define("DB_HOST",     $_ENV['DB_HOST']);
    define("DB_NAME",     $_ENV['DB_NAME']);
    define("DB_USER",     $_ENV['DB_USER']);
    define("DB_PASS",     $_ENV['DB_PASS']);
    define("DB_PORT",     $_ENV['DB_PORT']);
    define("DB_CHARSET",  $_ENV['DB_CHARSET']);

} catch (Exception $e) {
    die('Konfigurasi Error: ' . $e->getMessage());
}

// Sanitasi variabel server


// Set timezone
date_default_timezone_set(TIMEZONE);

// Perbaikan implementasi autoloader
spl_autoload_register(function ($className) {
    // Konversi namespace ke path file
    $baseDir = BASEPATH;
    
    // Hapus 'app' dari awal namespace jika ada
    $className = str_replace('app\\', '', $className);
    
    // Konversi namespace separator ke directory separator
    $path = $baseDir . '/app/' . str_replace('\\', DIRECTORY_SEPARATOR, $className) . '.php';
    
    if (file_exists($path)) {
        require_once $path;
        return true;
    }
    
    // Log error jika file tidak ditemukan
    error_log("Class file not found: " . $path);
    return false;
});

try {
    // Menggunakan namespace yang benar untuk Autoload
    $autoload = new app\Autoload();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
