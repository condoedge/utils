<?php

// Load all the files of this folder
$files = glob(__DIR__ . '/*.php');

foreach ($files as $file) {
    if (basename($file) !== 'loader.php') {
        require_once $file;
    }
}