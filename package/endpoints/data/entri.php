<?php
use app\Ngorei;
   $queue = Ngorei::init()->Queue('demo_queue');
   $stats = $queue->viewQueue('demo');
   // Batasi data menjadi 3 item menggunakan array_slice
   $limitedStats = array_slice($stats, 0,3);
// Return response tanpa echo apapun
return $limitedStats;
