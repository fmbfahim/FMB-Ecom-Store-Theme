<?php
$src = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/assets/css/main.css';
$dst = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/assets/css/main.css';
copy($src, $dst);
echo "main.css copied successfully (" . filesize($dst) . " bytes)\n";
