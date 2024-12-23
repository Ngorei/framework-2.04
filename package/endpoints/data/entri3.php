<?php
use app\Ngorei;
$ngorei = new Ngorei();
// Dengan opsi kustom
$tree = $ngorei->getDatabaseTree([
    'exclude_tables' => ['logs', 'sessions'],
    'include_columns' => true,
    'include_views' => true,
    'show_details' => true
]);
return $tree;

