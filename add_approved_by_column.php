<?php
require 'includes/db.php';

// Add approved_by column if not exists
$checkColumn = $conn->query("SHOW COLUMNS FROM sign_requests LIKE 'approved_by'");
if ($checkColumn->num_rows == 0) {
    $sql = "ALTER TABLE sign_requests ADD COLUMN approved_by INT(11) NULL AFTER status";
    if ($conn->query($sql) === TRUE) {
        echo "Column 'approved_by' added successfully.";
    } else {
        echo "Error adding column: " . $conn->error;
    }
} else {
    echo "Column 'approved_by' already exists.";
}
?>