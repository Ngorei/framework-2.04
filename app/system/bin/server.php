<?php
// Periksa ekstensi yang diperlukan
 $required_extensions = ['openssl'];
 foreach ($required_extensions as $ext) {
     if (!extension_loaded($ext)) {
         die("Ekstensi PHP '$ext' diperlukan tetapi tidak tersedia.\n");
     }
 }
require_once dirname(dirname(__DIR__)) . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(dirname(dirname(__DIR__)));
$dotenv->load();
use app\Websocket;
$server = new Websocket($_ENV['SERVER_HOST'], $_ENV['SDK_PORT']);
$server->run();
