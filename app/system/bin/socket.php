<?php
namespace app;
use app\tatiye;
// Pastikan path autoload benar
$autoloadPath = dirname(__DIR__, 3) . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    die("Autoload file tidak ditemukan di: " . $autoloadPath);
}
require $autoloadPath;

// Load environment variables
$dotenvPath = dirname(__DIR__, 3);
if (!file_exists($dotenvPath . '/.env')) {
    die(".env file tidak ditemukan di: " . $dotenvPath);
}
$dotenv = \Dotenv\Dotenv::createImmutable($dotenvPath);
$dotenv->load();

// Ambil port dari environment variable atau gunakan default
$port = getenv('SDK_PORT') ?: (isset($_ENV['SDK_PORT']) ? $_ENV['SDK_PORT'] : 8080);
$host = getenv('SERVER_HOST') ?: (isset($_ENV['SERVER_HOST']) ? $_ENV['SERVER_HOST'] : '0.0.0.0');

// Debug info
echo "Using port: " . $port . "\n";
echo "Using host: " . $host . "\n";

// Debug namespace dan class
if (!class_exists(Websocket::class)) {
    die("Class RealtimeServer tidak ditemukan. Namespace: " . __NAMESPACE__);
}

// Import yang diperlukan dengan namespace lengkap
$loop = \React\EventLoop\Factory::create();
$realTimeServer = new Websocket($loop);

try {
    $server = \Ratchet\Server\IoServer::factory(
        new \Ratchet\Http\HttpServer(
            new \Ratchet\WebSocket\WsServer(
                $realTimeServer
            )
        ),
        $port,
        $host
    );

    echo "WebSocket Server berjalan di ws://{$host}:{$port}\n";
    $server->run();
} catch (\Exception $e) {
    die("Error starting WebSocket server: " . $e->getMessage() . "\n");
}

// Debug info
echo "Autoload file ditemukan\n";
echo "Current directory: " . __DIR__ . "\n";
echo "Namespace: " . __NAMESPACE__ . "\n";
echo "Looking for class: " . Websocket::class . "\n";
echo "Loaded classes:\n";
print_r(get_declared_classes());
