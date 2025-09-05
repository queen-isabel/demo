<?php
include '../server.php';

// Get all tables from the database
$result = $conn->query("SHOW TABLES");
if (!$result) {
    die("Error: " . mysqli_error($conn));
}

$tables = array();
while ($row = $result->fetch_array()) {
    $tables[] = $row[0];
}

// Create the SQL export
$sql = '';
foreach ($tables as $table) {
    // Disable foreign key checks
    $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n";

    // Drop the table if it exists
    $sql .= "DROP TABLE IF EXISTS $table;\n";

    // Create the table structure
    $stmt = $conn->prepare("SHOW CREATE TABLE $table");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_row();
    $sql .= $row[1] . ";\n";

    // Insert data into the table
    $stmt = $conn->prepare("SELECT * FROM $table");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_row()) {
        $sql .= "INSERT INTO $table VALUES(";
        for ($i = 0; $i < $result->field_count; $i++) {
            $row[$i] = mysqli_real_escape_string($conn, $row[$i]);
            $sql .= "'" . $row[$i] . "'";
            if ($i < $result->field_count - 1) {
                $sql .= ",";
            }
        }
        $sql .= ");\n";
    }

    // Enable foreign key checks
    $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
}

// Close the database connection
$conn->close();

// Set the headers for the download
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="database_export.sql"');
header('Expires: 0');
header('Cache-Control: no-cache');

// Output the SQL export
echo $sql;
exit;
?>