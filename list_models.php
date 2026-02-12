<?php

require __DIR__.'/vendor/autoload.php';

$apiKey = 'AIzaSyB39x1dGoMga8sQg83lGGVpSNgFp_WtSBk'; // From .env
$url = "https://generativelanguage.googleapis.com/v1beta/models?key={$apiKey}";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

echo $response;
