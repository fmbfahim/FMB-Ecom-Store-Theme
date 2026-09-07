<?php
require 'c:/xampp/htdocs/fmbecstore/wp-load.php';

$res = wp_remote_get('https://steadfast.com.bd/tracking', array(
    'headers' => array(
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    ),
    'timeout' => 15
));

echo "HTTP: " . wp_remote_retrieve_response_code($res) . "\n";
$body = wp_remote_retrieve_body($res);
echo "Length: " . strlen($body) . "\n";
// find form actions, api urls, scripts
preg_match_all('/action="([^"]+)"/i', $body, $actions);
print_r($actions[1]);
preg_match_all('/https?:\/\/[^\s"\'<>]+/i', $body, $urls);
$clean_urls = array_unique(array_filter($urls[0], function($u){ return strpos($u, 'steadfast') !== false; }));
print_r($clean_urls);
