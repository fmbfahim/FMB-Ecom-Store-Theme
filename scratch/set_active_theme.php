<?php
$c = mysqli_connect('localhost', 'root', '', 'fmbecstore');
if (!$c) die("Connection error\n");

mysqli_query($c, "UPDATE wp_options SET option_value = 'fmb-ecom-store' WHERE option_name IN ('template', 'stylesheet')");
echo "Active theme switched back to fmb-ecom-store successfully!\n";
