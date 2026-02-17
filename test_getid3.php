<?php

require __DIR__ . '/vendor/autoload.php';

try {
    $getID3 = new \getID3;
    echo "getID3 instantiated successfully.\n";
    echo "Version: " . $getID3->version() . "\n";
} catch (\Throwable $e) {
    echo "Error instantiating getID3: " . $e->getMessage() . "\n";
}
