<?php
session_start();
include('../server.php');

if (!isset($_GET['examinee_id']) || !isset($_GET['file_type'])) {
    die(json_encode(['success' => false, 'message' => 'Invalid request']));
}

$examinee_id = intval($_GET['examinee_id']);
$file_type = $_GET['file_type'];

// Whitelist allowed file types
$allowedFiles = ['grade11_1stsem', 'grade11_2ndsem', 'grade12_1stsem', 'grade12_2ndsem', 'tor'];
if (!in_array($file_type, $allowedFiles)) {
    die(json_encode(['success' => false, 'message' => 'Invalid file type']));
}

// Path to requirements folder
$requirementsDir = '../examinee_registration/requirements/';

// Get filename from database
$query = "SELECT $file_type FROM tbl_requirements WHERE examinee_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $examinee_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if ($row && !empty($row[$file_type])) {
    $filename = $row[$file_type];
    $filepath = $requirementsDir . $filename;
    
    // Check if file exists in requirements folder
    if (file_exists($filepath)) {
        // Get file info
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($filepath);
        
        // Set appropriate headers
        header("Content-Type: $mimeType");
        header("Content-Disposition: inline; filename=\"$filename\"");
        
        // Output the file
        readfile($filepath);
        exit();
    }
}

// If we get here, file wasn't found
header("HTTP/1.0 404 Not Found");
echo "File not found.";
?>