<?php
include('../server.php'); // Database connection

if (isset($_GET['question_id'])) {
    $question_id = intval($_GET['question_id']);
    
    // Fetch image from database
    $query = "SELECT question_image FROM tbl_questions WHERE question_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $question_id);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($imageData);

    if ($stmt->fetch() && !empty($imageData)) {
        header("Content-Type: image/jpeg"); // Adjust the MIME type as needed (jpeg, png, gif, etc.)
        echo $imageData; // Output the image data
        exit();
    }
}

// If no image found, return a placeholder
header("Content-Type: image/png");
readfile("default-placeholder.png"); // Provide a default image
?>
