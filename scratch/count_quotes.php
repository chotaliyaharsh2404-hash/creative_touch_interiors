<?php
require 'includes/config.php';
$res = $conn->query('SELECT COUNT(*) as c FROM quote_requests')->fetch_assoc();
echo 'Quote count: ' . $res['c'] . "\n";
