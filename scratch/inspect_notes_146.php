<?php
require_once __DIR__ . '/../../../../wp-load.php';

$order_id = 146;
$notes = wc_get_order_notes(array('order_id' => $order_id));
echo "Total notes for order 146: " . count($notes) . "\n";
foreach ($notes as $n) {
    echo "ID: " . $n->id . " | Added by: '" . $n->added_by . "' | Customer note: " . ($n->customer_note ? '1' : '0') . " | Content: " . substr($n->content, 0, 50) . "\n";
}
