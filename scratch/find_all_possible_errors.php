<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$errors_caught = [];

set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$errors_caught) {
    $errors_caught[] = [
        'errno' => $errno,
        'errstr' => $errstr,
        'errfile' => $errfile,
        'errline' => $errline
    ];
    return false; // let normal handler run too
});

require_once 'c:/xampp/htdocs/fmbecstore/wp-load.php';

echo "WordPress loaded successfully!\n";
echo "Active theme: " . get_stylesheet() . "\n";
echo "Total PHP errors/notices/warnings caught during wp-load: " . count($errors_caught) . "\n";

foreach ($errors_caught as $e) {
    echo "  [Line {$e['errline']}] {$e['errfile']}: {$e['errstr']}\n";
}
