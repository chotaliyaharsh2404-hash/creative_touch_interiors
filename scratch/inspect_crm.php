<?php
require_once __DIR__ . '/../includes/config.php';

echo "Leads count: " . $conn->query("SELECT COUNT(*) FROM leads")->fetch_row()[0] . "\n";
echo "Consultations count: " . $conn->query("SELECT COUNT(*) FROM consultations")->fetch_row()[0] . "\n";
echo "Quote Requests count: " . $conn->query("SELECT COUNT(*) FROM quote_requests")->fetch_row()[0] . "\n";
echo "Contact Inquiries count: " . $conn->query("SELECT COUNT(*) FROM contact_inquiries")->fetch_row()[0] . "\n";

echo "\n--- Last 3 Quote Requests ---\n";
$qr = $conn->query("SELECT id, quote_number, customer_name, email, status, created_at FROM quote_requests ORDER BY id DESC LIMIT 3");
while ($r = $qr->fetch_assoc()) {
    print_r($r);
}

echo "\n--- Last 3 Leads ---\n";
$lr = $conn->query("SELECT id, name, email, status, created_at FROM leads ORDER BY id DESC LIMIT 3");
while ($r = $lr->fetch_assoc()) {
    print_r($r);
}

echo "\n--- Last 3 Consultations ---\n";
$cr = $conn->query("SELECT * FROM consultations ORDER BY id DESC LIMIT 3");
while ($r = $cr->fetch_assoc()) {
    print_r($r);
}

echo "\n--- Last 3 Inquiries ---\n";
$ir = $conn->query("SELECT * FROM contact_inquiries ORDER BY id DESC LIMIT 3");
while ($r = $ir->fetch_assoc()) {
    print_r($r);
}
