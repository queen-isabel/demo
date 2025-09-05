<?php
    include '../server.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_id'])) {
        $batch_id = (int) $_POST['batch_id'];
        $release_status = 'released';
        $release_datetime = date('Y-m-d H:i:s');

        // Check if batch already has a release record
        $sql = "SELECT * FROM tbl_release_result WHERE batch_id = '$batch_id'";
        $result = mysqli_query($conn, $sql);

        if (mysqli_num_rows($result) > 0) {
            // If exists, update the status to 'released'
            $updateSql = "UPDATE tbl_release_result SET 
                        release_status = '$release_status', 
                        release_datetime = '$release_datetime' 
                        WHERE batch_id = '$batch_id'";

            if (mysqli_query($conn, $updateSql)) {
                echo "<script>
                    alert('Results successfully re-released for the selected batch.');
                    window.location.href = 'reports.php';
                </script>";
            } else {
                echo "Error updating release result: " . mysqli_error($conn);
            }
        } else {
            // If not exists, insert new record
            $insertSql = "INSERT INTO tbl_release_result 
                        (batch_id, release_status, release_datetime) 
                        VALUES ('$batch_id', '$release_status', '$release_datetime')";

            if (mysqli_query($conn, $insertSql)) {
                echo "<script>
                    alert('Results successfully released.');
                    window.location.href = 'reports';
                </script>";
            } else {
                echo "Error inserting release result: " . mysqli_error($conn);
            }
        }

        $conn->close();
    } else {
        echo "Invalid request.";
    }
?>