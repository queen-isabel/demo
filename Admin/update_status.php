<?php
session_start();
include_once '../server.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $selectedReasons = isset($_POST['reasons']) ? implode(", ", $_POST['reasons']) : '';
    $additionalReason = isset($_POST['additional_reason']) ? trim($_POST['additional_reason']) : '';

    $finalReason = '';

    if (!empty($_POST['reasons'])) {
        foreach ($_POST['reasons'] as $reason) {
            $finalReason .= $reason . ", ";
        }
        $finalReason = rtrim($finalReason, ', ');
    }

    if (!empty($additionalReason)) {
        $finalReason .= " | Additional Notes: " . $additionalReason;
    }

    $examineeID = $conn->real_escape_string($_POST['examinee_id']);
    $action = $_POST['action'];

    if ($action === 'approve') {
        $updateQuery = "UPDATE tbl_examinee SET estatus = 'approved' WHERE examinee_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("i", $examineeID);
        $stmt->execute();
        $stmt->close();

        $_SESSION['message'] = "Examinee status updated to approved.";
        $_SESSION['msg_type'] = "success";
        header("Location: pending_applicants");
        exit();
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("SELECT email, fname, lname FROM tbl_examinee WHERE examinee_id = ?");
        $stmt->bind_param("i", $examineeID);
        $stmt->execute();
        $result = $stmt->get_result();
        $examinee = $result->fetch_assoc();
        $stmt->close();

        if (!$examinee) {
            $_SESSION['message'] = "Examinee not found.";
            $_SESSION['msg_type'] = "error";
            header("Location: pending_applicants");
            exit();
        }

        $stmt = $conn->prepare("UPDATE tbl_examinee SET estatus = 'rejected' WHERE examinee_id = ?");
        $stmt->bind_param("i", $examineeID);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("INSERT INTO tbl_rejection_reason (examinee_id, reasons) VALUES (?, ?)");
        $stmt->bind_param("is", $examineeID, $finalReason);
        $stmt->execute();
        $stmt->close();

        $_SESSION['message'] = "Examinee rejected and reason recorded.";
        $_SESSION['msg_type'] = "success";
        header("Location: pending_applicants");
        exit();
    }
}
?>
