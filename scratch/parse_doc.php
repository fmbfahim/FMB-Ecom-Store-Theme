<?php
$html = file_get_contents('C:/Users/FMB/.gemini/antigravity-ide/brain/9e774c3a-968e-46e3-bacc-cf01b7bd011a/.system_generated/steps/3642/content.md');

// Replace block tags with newlines
$html = preg_replace('/<(p|tr|li|div|h[1-6]|br)[^>]*>/i', "\n", $html);
$html = preg_replace('/<\/(p|tr|li|div|h[1-6])>/i', "\n", $html);

// Remove scripts and styles
$html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $html);
$html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', "", $html);

$text = strip_tags($html);
$lines = explode("\n", $text);
$clean = [];
foreach ($lines as $line) {
    $t = trim(html_entity_decode($line, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    // convert non-breaking space
    $t = trim(str_replace("\xc2\xa0", ' ', $t));
    if (!empty($t)) {
        $clean[] = $t;
    }
}

file_put_contents(__DIR__ . '/steadfast_api_doc_clean.txt', implode("\n", $clean));
echo "Total clean lines: " . count($clean) . "\n";
echo "Preview:\n";
for ($i = 0; $i < min(80, count($clean)); $i++) {
    echo $clean[$i] . "\n";
}
