<?php
session_start();
include_once '../server.php';

if (isset($_POST['school_year'], $_POST['enrollment_status'])) {
    // Use trim and filter_var for sanitization and validation
    $schoolYearId = filter_var(trim($_POST['school_year']), FILTER_SANITIZE_NUMBER_INT);
    $enrollmentStatus = filter_var(trim($_POST['enrollment_status']), FILTER_SANITIZE_STRING);

    if (!empty($schoolYearId) && !empty($enrollmentStatus)) {
        // Prepare statement using your exact format
        $stmt = $conn->prepare("SELECT 1 FROM tbl_schedule
            JOIN tbl_batch ON tbl_schedule.batch_id = tbl_batch.batch_id
            JOIN tbl_examinee ON tbl_examinee.batch_id = tbl_batch.batch_id
            WHERE tbl_examinee.enrollment_status = ?
            AND tbl_batch.school_year_id = ?
            LIMIT 1");

        if ($stmt === false) {
            // Handle prepare error
            echo json_encode(['error' => 'Database prepare failed: ' . $conn->error]);
            exit();
        }

        // Bind parameters (s = string, i = integer)
        $stmt->bind_param("si", $enrollmentStatus, $schoolYearId);

        // Execute the statement
        $stmt->execute();

        // Store and check result
        $stmt->store_result();
        $scheduleExists = $stmt->num_rows > 0;

        // Return response
        echo json_encode(['scheduleExists' => $scheduleExists]);

        // Clean up
        $stmt->close();
    } else {
        echo json_encode(['error' => 'Invalid or missing input.']);
    }
}
?>
