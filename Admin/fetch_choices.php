<?php
session_start();
include_once '../server.php';

if (isset($_POST['question_id'])) {
    $questionID = $conn->real_escape_string($_POST['question_id']);
    $query = "SELECT choice_number, choices FROM tbl_choices WHERE question_id = '$questionID' ORDER BY choice_number";
    $result = $conn->query($query);

    if ($result->num_rows > 0) {
        $choices = [];
        while ($row = $result->fetch_assoc()) {
            $choices[] = [
                'number' => $row['choice_number'],
                'text' => $row['choices']
            ];
        }
        echo json_encode(['success' => true, 'choices' => $choices]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No choices found']);
    }
}
?>
