<?php
session_start();
include('../server.php');

// Ensure the request is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve the data from the request
    $schedule_id = filter_input(INPUT_POST, 'schedule_id', FILTER_VALIDATE_INT);
    $new_date = filter_input(INPUT_POST, 'new_date', FILTER_SANITIZE_STRING);
    $new_time = filter_input(INPUT_POST, 'new_time', FILTER_SANITIZE_STRING);

    // Validate inputs
    if (!$schedule_id || !$new_date || !$new_time) {
        echo "Invalid or missing data. Please ensure all fields are correctly filled.";
        exit;
    }

    // Convert the new date and time to a DateTime object
    $newDateTime = new DateTime($new_date . ' ' . $new_time);

    // Calculate the new exam end time 
    $newDateTimeEnd = clone $newDateTime;
    $newDateTimeEnd->modify('+2 hours'); 
    $newEndTime = $newDateTimeEnd->format('H:i:s');

    // Check if there is an existing schedule with the same date, start time, and end time
    $checkForDuplicateQuery = "SELECT 1 FROM tbl_schedule 
                               WHERE exam_date = ? AND exam_start_time = ? AND exam_end_time = ? AND schedule_id != ?";
    $checkStmt = $conn->prepare($checkForDuplicateQuery);
    $checkStmt->bind_param("sssi", $new_date, $new_time, $newEndTime, $schedule_id);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        // If a duplicate schedule exists, return an error
        echo "A schedule already exists for this date and time. Please choose a different date and time.";
        exit;
    }

    // Check if the schedule exists
    $checkScheduleQuery = "SELECT 1 FROM tbl_schedule WHERE schedule_id = ?";
    $checkStmt = $conn->prepare($checkScheduleQuery);
    $checkStmt->bind_param("i", $schedule_id);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        // If the schedule exists, update it with the new date, start time, and end time
        $updateQuery = "UPDATE tbl_schedule 
                        SET exam_date = ?, exam_start_time = ?, exam_end_time = ?
                        WHERE schedule_id = ?";
        
        $updateStmt = $conn->prepare($updateQuery);
        $examStartTime = $newDateTime->format('H:i:s');
        $examDate = $newDateTime->format('Y-m-d');

        $updateStmt->bind_param("sssi", $examDate, $examStartTime, $newEndTime, $schedule_id);
        
        // Execute the update and check for errors
        if ($updateStmt->execute()) {
            echo "Exam schedule successfully updated!";
        } else {
            // If update fails, log the error
            echo "Failed to update the schedule. MySQL Error: " . $conn->error;
        }
    } else {
        echo "Schedule not found.";
    }
}
?>
