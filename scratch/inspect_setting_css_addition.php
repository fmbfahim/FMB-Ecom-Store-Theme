<?php
$f2027 = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/inc/fmb-engine/assets/css/setting.css';
$fTarget = 'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/inc/fmb-engine/assets/css/setting.css';

$c2027 = file_get_contents($f2027);
$cTarget = file_get_contents($fTarget);

copy('c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store 2027/inc/fmb-engine/assets/css/incomplete.css',
     'c:/xampp/htdocs/fmbecstore/wp-content/themes/fmb-ecom-store/inc/fmb-engine/assets/css/incomplete.css');
echo "Copied incomplete.css successfully!\n";

$pos = strpos($c2027, '/* FMB Engine Settings Styles — Aligned');
if ($pos !== false) {
    echo "Found new section in 2027 setting.css at pos $pos. Length: " . (strlen($c2027) - $pos) . " bytes\n";
    if (strpos($cTarget, '/* FMB Engine Settings Styles — Aligned') === false) {
        // Append it to Target
        file_put_contents($fTarget, $cTarget . "\n\n" . substr($c2027, $pos));
        echo "Appended new design system section to target setting.css!\n";
    } else {
        echo "Already present in Target setting.css\n";
    }
}
