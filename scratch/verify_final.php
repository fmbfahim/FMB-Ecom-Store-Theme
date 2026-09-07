<?php
require_once __DIR__ . '/../../../../wp-load.php';

$users = get_users(['role' => 'administrator']);
wp_set_current_user($users[0]->ID);

$_GET['page'] = 'fmb-order-manager';
$_GET['view'] = 146;

ob_start();
fmb_admin_order_manager_page();
$out = ob_get_clean();

echo "Rendered Order 146 length: " . strlen($out) . " bytes\n";
echo "Has Check & Sync button: " . (strpos($out, 'Check &amp; Sync Courier Live') !== false ? 'YES' : 'NO') . "\n";
echo "Has Rider note (Shakil): " . (strpos($out, 'Shakil') !== false ? 'YES' : 'NO') . "\n";
echo "Has Add Note button: " . (strpos($out, 'Add Note') !== false ? 'YES' : 'NO') . "\n";
