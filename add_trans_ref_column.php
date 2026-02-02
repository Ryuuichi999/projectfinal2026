<?php
require 'includes/db.php';

// Add trans_ref column if not exists
$checkColumn = $conn->query("SHOW COLUMNS FROM sign_documents LIKE 'trans_ref'");
if ($checkColumn->num_rows == 0) {
    $sql = "ALTER TABLE sign_documents ADD COLUMN trans_ref VARCHAR(255) NULL AFTER file_path";
    if ($conn->query($sql) === TRUE) {
        echo "Column 'trans_ref' added successfully.";
    } else {
        echo "Error adding column: " . $conn->error;
    }
} else {
    echo "Column 'trans_ref' already exists.";
}
?>