<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log incoming request untuk debug
file_put_contents('debug.log', print_r($_POST, true), FILE_APPEND);

use app\tatiye;
use app\Routing;
use app\Queue;
tatiye::index(true);
$json_arr = tatiye::rootDirectory(PUBLIC_DIR,'public/');
tatiye::generateSitemap($json_arr['sdk'], PUBLIC_DIR . '/sitemap.xml');
$metadata = tatiye::generateMetadata($json_arr['sdk']);
tatiye::saveMetadataToJson($metadata, PUBLIC_DIR . 'properti.json');
tatiye::pathRequest(PUBLIC_DIR,PACKAGE.'/properti.json');

// Pastikan response dalam format JSON yang valid
$response = array(
    'status' => 'success',
    'message' => 'File change detected',
    'data' => $_POST
);

header('Content-Type: application/json');
echo json_encode($response);
