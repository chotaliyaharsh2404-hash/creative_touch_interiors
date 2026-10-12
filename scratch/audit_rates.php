<?php
$files = [
    'consultation.php',
    'includes/config.php',
    'includes/quote_engine.php',
    'quote_details.php',
    'quote_pdf.php',
    'admin/quotes.php',
    'admin/quote_details.php'
];

$patterns = ['1800', '1,800', '1200', '1,200', '1100', '1,100', 'base_rate', 'base spatial', 'spatial execution', 'spatial rate'];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    $lines = explode("\n", $content);
    foreach ($lines as $lineNum => $line) {
        foreach ($patterns as $p) {
            if (stripos($line, $p) !== false) {
                echo "$file:" . ($lineNum + 1) . ": " . trim($line) . "\n";
                break;
            }
        }
    }
}
