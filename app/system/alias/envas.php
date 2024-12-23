<?php
namespace package;
use app\tatiye;

$val = json_decode(file_get_contents("php://input"), true);


// Perbaikan pengecekan dengan nama variabel yang benar
if (tatiye::env('PUBLIC_URL') == trim($val['payload']['server']) && 
    tatiye::env('APP_CREDENTIAL') == trim($val['payload']['cradensial'])) {
    
    $Exp = array(
        'status'  => 'ON',      // Perbaikan typo 'ststus' menjadi 'status'
        'header'  => tatiye::EnvAsJson(),
    );
    
} else {
    $Exp = array(
        'status'  => 'OFF',     // Perbaikan typo 'ststus' menjadi 'status' 
        'header'  => $val,
    );
}

// Tambahkan header untuk memastikan koneksi HTTPS
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

return $Exp;
//return tatiye::EnvAsJson();