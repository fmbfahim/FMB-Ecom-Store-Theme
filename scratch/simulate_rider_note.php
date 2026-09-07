<?php
require_once __DIR__ . '/../../../../wp-load.php';

$order_id = 146;
$test_rider_remark = 'Rider: Shakil (01711223344) - Customer requested delivery at 5:30 PM';
$test_status = 'in_transit';

$order = wc_get_order($order_id);
$order->update_meta_data('_courier_rider_note', $test_rider_remark);
$order->update_meta_data('_ofls_courier_latest_remark', $test_rider_remark);
$order->update_meta_data('_courier_delivery_status', $test_status);
$order->save();

if (function_exists('fmb_courier_db_get') && function_exists('fmb_courier_db_save')) {
    $c = fmb_courier_db_get($order_id);
    if ($c) {
        $c['rider_note'] = $test_rider_remark;
        $c['delivery_status'] = $test_status;
        fmb_courier_db_save($order_id, $c);
    }
}

if (function_exists('fmb_record_courier_history_entry')) {
    fmb_record_courier_history_entry($order_id, 'Rider Assigned & Note', $test_rider_remark, 'Steadfast Rider');
}

echo "Simulated live courier tracking and rider note on Order #146!\n";
$info = fmb_get_order_courier_info($order);
echo "Fetched Courier Info:\n";
print_r($info);
