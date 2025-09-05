<?php
include_once '../server.php';
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// First check if we're just fetching batches
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['examinee_id']) && !isset($_POST['batch_id'])) {
    try {
        // Get active school year
        $schoolYearQuery = "SELECT school_year_id FROM tbl_school_year WHERE school_year_status = 'active' LIMIT 1";
        $schoolYearResult = mysqli_query($conn, $schoolYearQuery);
        
        if (!$schoolYearResult || mysqli_num_rows($schoolYearResult) === 0) {
            throw new Exception("No active school year found");
        }
        
        $schoolYear = mysqli_fetch_assoc($schoolYearResult);
        $schoolYearId = $schoolYear['school_year_id'];
        
        // Get all batches for active school year
        $batchQuery = "SELECT batch_id, batch_number 
                      FROM tbl_batch 
                      WHERE school_year_id = $schoolYearId
                      ORDER BY batch_number";
        
        $batchResult = mysqli_query($conn, $batchQuery);
        
        if (!$batchResult) {
            throw new Exception("Database error: " . mysqli_error($conn));
        }
        
        $batches = [];
        while ($row = mysqli_fetch_assoc($batchResult)) {
            $batches[] = [
                'batch_id' => (int)$row['batch_id'],
                'batch_number' => $row['batch_number']
            ];
        }
        
        if (empty($batches)) {
            throw new Exception("No batches available for active school year");
        }
        
        echo json_encode([
            'status' => 'success',
            'batches' => $batches
        ]);
        exit();
        
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
        exit();
    }
}

// Handle examinee movement (separate from batch fetching)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['examinee_id'], $_POST['batch_id'])) {
    $examineeID = (int)$_POST['examinee_id'];
    $newBatchID = (int)$_POST['batch_id'];
    mysqli_begin_transaction($conn);

    try {
        if ($examineeID <= 0 || $newBatchID <= 0) {
            throw new Exception("Invalid parameters: examinee_id or batch_id must be positive integers");
        }

        // Verify batch exists
        $batchCheck = mysqli_query($conn, "SELECT batch_number FROM tbl_batch WHERE batch_id = $newBatchID");
        if (!$batchCheck || mysqli_num_rows($batchCheck) === 0) {
            throw new Exception("Invalid batch ID selected");
        }
        $batchRow = mysqli_fetch_assoc($batchCheck);
        $batchNumber = $batchRow['batch_number'];

        // Generate new unique code
        $uniqueCode = generateUniqueCode($batchNumber);

        // Start transaction
        mysqli_begin_transaction($conn);

        try {
            // Delete existing schedule
            $deleteQuery = "DELETE FROM examinee_schedules WHERE examinee_id = $examineeID";
            if (!mysqli_query($conn, $deleteQuery)) {
                throw new Exception("Delete failed: " . mysqli_error($conn));
            }

            // Update examinee record
            $updateQuery = "UPDATE tbl_examinee SET batch_id = $newBatchID, unique_code = '" . 
                          mysqli_real_escape_string($conn, $uniqueCode) . "' WHERE examinee_id = $examineeID";
            if (!mysqli_query($conn, $updateQuery)) {
                throw new Exception("Update failed: " . mysqli_error($conn));
            }

            // Find available exam schedule
            $examScheduleQuery = "SELECT es.exam_schedule_id
                                FROM exam_schedules es
                                JOIN tbl_schedule s ON es.schedule_id = s.schedule_id
                                WHERE s.batch_id = $newBatchID AND es.exam_status = 'not started'
                                LIMIT 1";
            $examScheduleResult = mysqli_query($conn, $examScheduleQuery);
            
            if ($examScheduleResult && mysqli_num_rows($examScheduleResult) > 0) {
                $row = mysqli_fetch_assoc($examScheduleResult);
                $examScheduleID = (int)$row['exam_schedule_id'];

                $insertQuery = "INSERT INTO examinee_schedules (exam_schedule_id, examinee_id)
                              VALUES ($examScheduleID, $examineeID)";
                if (!mysqli_query($conn, $insertQuery)) {
                    throw new Exception("Schedule insert failed: " . mysqli_error($conn));
                }
            }

            mysqli_commit($conn);
            echo json_encode([
                'status' => 'success', 
                'message' => 'Examinee successfully moved to new batch!'
            ]);
        } catch (Exception $e) {
            mysqli_rollback($conn);
            throw $e; // Re-throw to outer catch
        }
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error', 
            'message' => 'Error moving examinee: ' . $e->getMessage(),
            'debug' => [
                'examinee_id' => $examineeID,
                'batch_id' => $newBatchID,
                'query_error' => mysqli_error($conn)
            ]
        ]);
    }
    exit();
}

function generateUniqueCode($batch_number) {
    $batch_number = str_pad($batch_number, 2, '0', STR_PAD_LEFT);
    return $batch_number . '-' . str_pad(mt_rand(0, 99999), 6, '0', STR_PAD_LEFT);
}
?>