<?php
require 'includes/db.php';

// 1. Alter Table to add 'waiting_permit'
$alter_sql = "ALTER TABLE sign_requests MODIFY COLUMN status ENUM('pending','reviewing','need_documents','waiting_payment','waiting_receipt','waiting_permit','approved','rejected') NOT NULL DEFAULT 'pending'";

if ($conn->query($alter_sql)) {
    echo "Success: Table altered to include 'waiting_permit'.\n";
} else {
    echo "Error altering table: " . $conn->error . "\n";
}

// 2. Fix existing records that have empty status but have receipt
// empty string in enum is index 0 usually, but let's check for ''
$fix_sql = "UPDATE sign_requests SET status='waiting_permit' WHERE status='' AND receipt_no IS NOT NULL AND receipt_no != ''";
if ($conn->query($fix_sql)) {
    echo "Success: Fixed " . $conn->affected_rows . " records with empty status.\n";
} else {
    echo "Error fixing records: " . $conn->error . "\n";
}
