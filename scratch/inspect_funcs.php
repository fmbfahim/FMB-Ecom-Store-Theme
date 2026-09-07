<?php
require 'c:/xampp/htdocs/fmbecstore/wp-load.php';

// Set error handler to capture any notices, warnings, errors
$errors = [];
set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$errors) {
    $errors[] = [
        'errno' => $errno,
        'errstr' => $errstr,
        'errfile' => $errfile,
        'errline' => $errline
    ];
    return false;
});

// Execute functions defined in functions.php that can be called safely
echo "Inspecting declared functions...\n";
$funcs = get_defined_functions()['user'];
$fmb_funcs = array_filter($funcs, function($f) {
    return strpos($f, 'fmb_') === 0;
});

echo "Found " . count($fmb_funcs) . " fmb_ functions.\n";

// Check if any error occurred during load
if (!empty($errors)) {
    echo "Errors caught:\n";
    print_r($errors);
} else {
    echo "No errors caught during wp-load!\n";
}
