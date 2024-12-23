<?php
use app\Ngorei;
$url = parse_url($_SERVER["REQUEST_URI"]);
$path = $url["path"];
$ngorei = new Ngorei();
var_dump($_SERVER);
// // Penggunaan default
// $charts = $ngorei->getAutoChartData();

// // Dengan konfigurasi kustom
// $charts = $ngorei->getAutoChartData([
//     'max_tables' => 3,
//     'max_charts_per_table' => 2,
//     'min_rows' => 10,
//     'skip_tables' => ['logs', 'temp'],
//     'preferred_types' => ['int', 'date']
// ]);
// var_dump($charts);