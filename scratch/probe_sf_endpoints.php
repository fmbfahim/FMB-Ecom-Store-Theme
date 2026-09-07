<?php
require 'c:/xampp/htdocs/fmbecstore/wp-load.php';

$cid = '290583033';
$api_key = get_option('steadfast_api_key');
$secret  = get_option('steadfast_secret_key');

$endpoints = array(
    'status_by_cid/' . $cid,
    'status_by_trackingcode/' . $cid,
    'tracking_details/' . $cid,
    'order_details/' . $cid,
    'view_order/' . $cid,
    'parcel/' . $cid,
    'tracking/' . $cid,
);

foreach ($endpoints as $ep) {
    $url = 'https://portal.packzy.com/api/v1/' . $ep;
    $res = wp_remote_get($url, array(
        'headers' => array('Api-Key' => $api_key, 'Secret-Key' => $secret),
        'timeout' => 10
    ));
    $code = wp_remote_retrieve_response_code($res);
    $body = wp_remote_retrieve_body($res);
    echo "$ep => HTTP $code: $body\n";
}
