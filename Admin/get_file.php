<?php
session_start();
include('../server.php');

if (!isset($_GET['examinee_id']) || !isset($_GET['file_type'])) {
    die(json_encode(['error' => 'Invalid request.']));
}

$examinee_id = intval($_GET['examinee_id']);
$file_type = $_GET['file_type'];

// Whitelist allowed file types
$allowedFiles = ['grade11_1stsem', 'grade11_2ndsem', 'grade12_1stsem', 'grade12_2ndsem', 'tor'];
if (!in_array($file_type, $allowedFiles)) {
    die(json_encode(['error' => 'Invalid file type.']));
}

// First check database
$query = "SELECT $file_type FROM tbl_requirements WHERE examinee_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $examinee_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if ($row && !empty($row[$file_type])) {
    // File is in database - create temp file
    $temp_dir = sys_get_temp_dir();
    $temp_file = tempnam($temp_dir, 'req_');
    file_put_contents($temp_file, $row[$file_type]);
    
    $mime_type = mime_content_type($temp_file);
    $is_image = strpos($mime_type, 'image/') === 0;
    $is_pdf = $mime_type === 'application/pdf';
    
    header('Content-Type: application/json');
    echo json_encode([
        'type' => $is_image ? 'image' : ($is_pdf ? 'pdf' : 'file'),
        'url' => 'data:' . $mime_type . ';base64,' . base64_encode(file_get_contents($temp_file))
    ]);
    
    unlink($temp_file);
    exit;
}

// If not in database, check folder
$folder_path = "requirements/{$examinee_id}/";
$file_pattern = $folder_path . $file_type . ".*";
$files = glob($file_pattern);

if (!empty($files)) {
    $file_path = $files[0];
    $mime_type = mime_content_type($file_path);
    $is_image = strpos($mime_type, 'image/') === 0;
    $is_pdf = $mime_type === 'application/pdf';
    
    header('Content-Type: application/json');
    echo json_encode([
        'type' => $is_image ? 'image' : ($is_pdf ? 'pdf' : 'file'),
        'url' => $file_path . '?t=' . time() // Cache buster
    ]);
    exit;
}

// No file found
echo json_encode(['error' => 'File not found.']);
?>