<?php
    session_start();
    include_once '../server.php';
    require_once '../vendor/autoload.php';
    use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

    // Function to generate a unique code based on batch number
    function generateUniqueCode($batch_number) {
        // Ensure batch_number has 2 digits
        $batch_number = str_pad($batch_number, 2, '0', STR_PAD_LEFT);
        // Generate random number with 6 digits
        return $batch_number . '-' . str_pad(mt_rand(0, 99999), 6, '0', STR_PAD_LEFT);
    }

    if (isset($_POST['importSubmit'])) {
        // Allowed mime types for Excel files
        $excelMimes = ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];

        // Validate if the file is an Excel file
        if (!empty($_FILES['file']['name']) && in_array($_FILES['file']['type'], $excelMimes)) {
            if (is_uploaded_file($_FILES['file']['tmp_name'])) {
                // Load the uploaded Excel file
                $reader = new Xlsx();
                $spreadsheet = $reader->load($_FILES['file']['tmp_name']);
                $worksheet = $spreadsheet->getActiveSheet();
                $worksheet_arr = $worksheet->toArray();

                // Remove the header row from the data
                unset($worksheet_arr[0]);

                // Get the active school year ID
                $schoolYearQuery = "SELECT school_year_id FROM tbl_school_year WHERE school_year_status = 'active' LIMIT 1";
                $schoolYearResult = mysqli_query($conn, $schoolYearQuery);
                
                if ($schoolYearResult && mysqli_num_rows($schoolYearResult) > 0) {
                    $schoolYearRow = mysqli_fetch_assoc($schoolYearResult);
                    $school_year_id = (int)$schoolYearRow['school_year_id'];
                } else {
                    $_SESSION['message'] = 'No active school year found.';
                    header("Location: ../admin/qualified_examinee");
                    exit();
                }

                // Loop through each row in the uploaded Excel file
                foreach ($worksheet_arr as $row) {
                    // Sanitize data using mysqli_real_escape_string
                    $lname = mysqli_real_escape_string($conn, $row[0]);
                    $fname = mysqli_real_escape_string($conn, $row[1]);
                    $mname = mysqli_real_escape_string($conn, $row[2]);
                    $strand_name = mysqli_real_escape_string($conn, $row[3]);
                    $first_preference = mysqli_real_escape_string($conn, $row[4]);
                    $second_preference = mysqli_real_escape_string($conn, $row[5]);
                    $enrollment_status = mysqli_real_escape_string($conn, $row[6]);
                    $lschool_attended = mysqli_real_escape_string($conn, $row[7]);
                    $lrn = mysqli_real_escape_string($conn, $row[8]);
                    $school_address = mysqli_real_escape_string($conn, $row[9]);
                    $home_address = mysqli_real_escape_string($conn, $row[10]);
                    $sex = mysqli_real_escape_string($conn, $row[11]);
                    $birthday = mysqli_real_escape_string($conn, $row[12]);
                    $email = mysqli_real_escape_string($conn, $row[13]);
                    $batch_number = mysqli_real_escape_string($conn, $row[14]);
                    $contact_number = mysqli_real_escape_string($conn, $row[15]);
                    $examinee_status = mysqli_real_escape_string($conn, $row[16]);
                    $zipcode = mysqli_real_escape_string($conn, $row[17]);
                    
                    // Automatically set the examinee_status to 'approved'
                    $estatus = 'approved';

                    // Ensure that required fields are not empty
                    if (!empty($lname) && !empty($fname) && !empty($email)) {
                        // Check if the examinee already exists by email or lrn
                        $checkExamineeQuery = "SELECT 1 FROM tbl_examinee 
                            WHERE lrn = '$lrn' 
                            AND batch_id = (
                                SELECT batch_id FROM tbl_batch 
                                WHERE batch_number = '$batch_number' 
                                AND school_year_id = $school_year_id
                            )";
                        $checkExamineeResult = mysqli_query($conn, $checkExamineeQuery);

                        if (mysqli_num_rows($checkExamineeResult) > 0) {
                            $_SESSION['message'] = 'Duplicate entry';
                            $_SESSION['msg_type'] = 'error';
                            header("Location: ../admin/qualified_examinee");
                            exit();
                        }

                        // Get the strand_id
                        $strandQuery = "SELECT strand_id FROM tbl_strand WHERE strand_name = '$strand_name'";
                        $strandResult = mysqli_query($conn, $strandQuery);
                        
                        if (mysqli_num_rows($strandResult) > 0) {
                            $strandRow = mysqli_fetch_assoc($strandResult);
                            $strand_id = (int)$strandRow['strand_id'];
                        } else {
                            $_SESSION['message'] = "Strand '$strand_name' not found";
                            header("Location: ../admin/qualified_examinee");
                            exit();
                        }

                        // Get the course IDs for first and second preferences
                        $firstPrefQuery = "SELECT course_id FROM tbl_course WHERE course_name = '$first_preference'";
                        $firstPrefResult = mysqli_query($conn, $firstPrefQuery);
                        
                        if (mysqli_num_rows($firstPrefResult) > 0) {
                            $firstPrefRow = mysqli_fetch_assoc($firstPrefResult);
                            $first_preference = (int)$firstPrefRow['course_id'];
                        } else {
                            $_SESSION['message'] = "First preference course '$first_preference' not found";
                            header("Location: ../admin/qualified_examinee");
                            exit();
                        }

                        $secondPrefQuery = "SELECT course_id FROM tbl_course WHERE course_name = '$second_preference'";
                        $secondPrefResult = mysqli_query($conn, $secondPrefQuery);
                        
                        if (mysqli_num_rows($secondPrefResult) > 0) {
                            $secondPrefRow = mysqli_fetch_assoc($secondPrefResult);
                            $second_preference = (int)$secondPrefRow['course_id'];
                        } else {
                            $_SESSION['message'] = "Second preference course '$second_preference' not found";
                            header("Location: ../admin/qualified_examinee");
                            exit();
                        }

                        // Get the batch_id based on batch_number
                        $batchQuery = "SELECT batch_id FROM tbl_batch WHERE batch_number = '$batch_number' AND school_year_id = $school_year_id";
                        $batchResult = mysqli_query($conn, $batchQuery);
                        
                        if (mysqli_num_rows($batchResult) > 0) {
                            $batchRow = mysqli_fetch_assoc($batchResult);
                            $batch_id = (int)$batchRow['batch_id'];
                        } else {
                            $_SESSION['message'] = "Batch number '$batch_number' not found";
                            header("Location: ../admin/qualified_examinee");
                            exit();
                        }

                        // Check batch capacity
                        $checkBatchQuery = "SELECT COUNT(*) as examinee_count FROM tbl_examinee 
                            WHERE batch_id = (
                                SELECT batch_id FROM tbl_batch 
                                WHERE batch_number = '$batch_number' 
                                AND school_year_id = $school_year_id
                            )";
                        $batchCount = mysqli_fetch_assoc(mysqli_query($conn, $checkBatchQuery));

                        if ($batchCount['examinee_count'] >= 100) {
                            $_SESSION['message'] = "Batch $batch_number has already reached the maximum limit of 100 examinees.";
                            header("Location: ../admin/qualified_examinee");
                            exit();
                        }

                        // Generate a unique code
                        $uniqueCode = generateUniqueCode($batch_number);

                        // Insert new examinee
                        $insertQuery = "INSERT INTO tbl_examinee (
                            strand_id, batch_id, unique_code, first_preference, second_preference, 
                            lname, fname, mname, lschool_attended, lrn, school_address, home_address, 
                            sex, birthday, email, contact_number, enrollment_status, examinee_status, 
                            estatus, zipcode
                        ) VALUES (
                            $strand_id, $batch_id, '$uniqueCode', $first_preference, $second_preference,
                            '$lname', '$fname', '$mname', '$lschool_attended', '$lrn', '$school_address',
                            '$home_address', '$sex', '$birthday', '$email', '$contact_number',
                            '$enrollment_status', '$examinee_status', '$estatus', '$zipcode'
                        )";
                        
                        if (!mysqli_query($conn, $insertQuery)) {
                            $_SESSION['message'] = 'Error inserting record: ' . mysqli_error($conn);
                            header("Location: ../admin/qualified_examinee");
                            exit();
                        }
                    }
                }

                $_SESSION['msg_type'] = 'success';
                $_SESSION['message'] = 'Import completed successfully.';
            } else {
                $_SESSION['msg_type'] = 'error';
                $_SESSION['message'] = 'Error: File upload failed.';
            }
        } else {
            $_SESSION['msg_type'] = 'error';
            $_SESSION['message'] = 'Error: Invalid file type.';
        }

        header("Location: ../admin/qualified_examinee");
        exit();
    }
?>