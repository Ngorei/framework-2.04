<?php
namespace package;

// Ambil body dari parameter global
$body = $GLOBALS['flatkey_body'] ?? null;

// Data sederhana dalam bentuk array asosiatif
$data = [
    [
        'title' => 'terakhir',
        'content' => 'Isi artikel pertama...',
        'author' => '1',
        'published_date' => '2024-03-20'
    ],
    [
        'title' => 'terbaru',
        'content' => 'Isi artikel kedua...',
        'author' => '2',
        'published_date' => '2024-03-21'
    ]
];

// Filter data berdasarkan body jika ada
if ($body && isset($body['id'])) {
    return array_values(array_filter($data, function($item) use ($body) {
        return isset($item['author']) && $item['author'] == $body['id'];
    }));
}

return $data;
