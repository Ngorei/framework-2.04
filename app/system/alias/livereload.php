<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

while (true) {
    if (connection_aborted()) break;
    
    // Cek perubahan file di sini
    // Bisa menggunakan filemtime() untuk mengecek waktu modifikasi file
    
    echo "data: reload\n\n";
    flush();
    sleep(2);
} 