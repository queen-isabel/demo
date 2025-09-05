<?php
    session_start();
    require '../server.php';
    require '../vendor/autoload.php';

    use PhpOffice\PhpSpreadsheet\Spreadsheet;
    use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
    use PhpOffice\PhpSpreadsheet\Style\Border;
    use PhpOffice\PhpSpreadsheet\Style\Alignment;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        ob_end_clean();

           // Fetch subjects
        $subjectColumns = [];
        $stmt = $conn->prepare("SELECT subject_name FROM tbl_subject");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($subjectRow = $result->fetch_assoc()) {
            $subjectColumns[] = htmlspecialchars($subjectRow['subject_name'], ENT_QUOTES, 'UTF-8');
        }
        $stmt->close();

        $scoreQuery = "SELECT 
                e.examinee_id, 
                CONCAT(e.lname, ', ', e.fname, ' ', e.mname) AS full_name, 
                e.contact_number, 
                e.sex, 
                e.enrollment_status, 
                s.strand_name, 
                e.lschool_attended, 
                e.home_address, 
                c1.course_name AS first_preference, 
                c2.course_name AS second_preference, 
                sc.total_score, 
                GROUP_CONCAT(CONCAT(sub.subject_name, ': ', IFNULL(ts.score, 0)) ORDER BY sub.subject_name SEPARATOR ', ') AS subject_scores, 
                sc.remarks, 
                sched.exam_date, 
                b.batch_number, 
                p.proctor_name
            FROM tbl_examinee e
            LEFT JOIN tbl_strand s ON e.strand_id = s.strand_id
            LEFT JOIN tbl_score sc ON e.examinee_id = sc.examinee_id
            LEFT JOIN tbl_subject sub ON sub.subject_id IS NOT NULL
            LEFT JOIN tbl_subject_score ts ON sc.exam_schedule_id = ts.exam_schedule_id 
                AND sc.examinee_id = ts.examinee_id 
                AND sub.subject_id = ts.subject_id
            LEFT JOIN tbl_course c1 ON e.first_preference = c1.course_id
            LEFT JOIN tbl_course c2 ON e.second_preference = c2.course_id
            LEFT JOIN tbl_batch b ON e.batch_id = b.batch_id
            LEFT JOIN tbl_schedule sched ON b.batch_id = sched.batch_id
            LEFT JOIN exam_schedules es ON sc.exam_schedule_id = es.exam_schedule_id
            LEFT JOIN tbl_proctor p ON es.proctor_id = p.proctor_id
            LEFT JOIN tbl_school_year sy ON b.school_year_id = sy.school_year_id
            JOIN tbl_release_result rr ON e.batch_id = rr.batch_id
            WHERE sc.exam_schedule_id IS NOT NULL
            AND sy.school_year_status = 'active' AND rr.release_status = 'released'
            GROUP BY e.examinee_id
            ORDER BY sc.total_score DESC";

        $scoreResult = mysqli_query($conn, $scoreQuery);
        
           if (!$scoreResult) {
            die("Score Query Error: " . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8'));
        }

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);
        $sheetMaster = $spreadsheet->createSheet(0);
        $sheetMaster->setTitle('RESULT MASTERLIST');
        setSheetHeaders($sheetMaster, $subjectColumns, 'COLLEGE ADMISSION TEST RESULT');

        $freshmanSheets = [];
        $transfereeSheet = null;
        $rowMaster = 4;
        $freshmanRowCounters = [];
        $transfereeRowCounter = 4;
        $noMaster = 1;
        $noTransferee = 1;
        $noFreshman = [];

       if (mysqli_num_rows($scoreResult) > 0) {
        while ($row = mysqli_fetch_assoc($scoreResult)) {
            // Use raw data (no htmlspecialchars) for Excel
            $full_name = $row['full_name'];
            $contact_number = $row['contact_number'];
            $sex = ucfirst($row['sex']);
            $enrollment_status = ucfirst($row['enrollment_status']);
            $strand_name = $row['strand_name'];
            $lschool_attended = $row['lschool_attended'];
            $home_address = $row['home_address'];
            $first_preference = $row['first_preference'];
            $second_preference = $row['second_preference'];
            $total_score = $row['total_score'];
            $remarks = $row['remarks'];
            $exam_date = $row['exam_date'];
            $batch_number = $row['batch_number'];
            $proctor_name = $row['proctor_name'];

            $dataRow = [
                $noMaster++,
                $full_name,
                $exam_date,
                $contact_number,
                $first_preference,
                $second_preference,
                $strand_name,
                $enrollment_status,
                $lschool_attended,
                $home_address,
                $sex
            ];

                $subjectScores = explode(', ', $row['subject_scores']);
                foreach ($subjectColumns as $subject) {
                    $scoreFound = false;
                    foreach ($subjectScores as $subjectScore) {
                        $parts = explode(': ', $subjectScore);
                        if ($parts[0] === $subject) {
                            $dataRow[] = htmlspecialchars($parts[1], ENT_QUOTES, 'UTF-8');
                            $scoreFound = true;
                            break;
                        }
                    }
                    if (!$scoreFound) {
                        $dataRow[] = "-";
                    }
                }

                $dataRow[] = $total_score;
                $dataRow[] = $remarks;
                $sheetMaster->fromArray($dataRow, NULL, 'A' . $rowMaster);
                $sheetMaster->getStyle('A' . $rowMaster . ':' . chr(65 + count($dataRow) - 1) . $rowMaster)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => ['wrapText' => true],
                    'font' => ['size' => 7]
                ]);
                $rowMaster++;

                if (strtolower($row['enrollment_status']) == 'freshmen') {
                    $batchNumber = 'BATCH ' . $batch_number;

                    if (!isset($freshmanSheets[$batchNumber])) {
                        $freshmanSheets[$batchNumber] = $spreadsheet->createSheet();
                        $freshmanSheets[$batchNumber]->setTitle($batchNumber);
                        setSheetHeaders($freshmanSheets[$batchNumber], $subjectColumns, 'COLLEGE ADMISSION TEST RESULT ' . $batchNumber);
                        $freshmanRowCounters[$batchNumber] = 4;
                        $noFreshman[$batchNumber] = 1;
                    }

                    $dataRow[0] = $noFreshman[$batchNumber]++;
                    $dataRow[2] = $exam_date;
                    $freshmanSheets[$batchNumber]->fromArray($dataRow, NULL, 'A' . $freshmanRowCounters[$batchNumber]);
                    $freshmanSheets[$batchNumber]->getStyle('A' . $freshmanRowCounters[$batchNumber] . ':' . chr(65 + count($dataRow) - 1) . $freshmanRowCounters[$batchNumber])->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        'alignment' => ['wrapText' => true],
                        'font' => ['size' => 7]
                    ]);
                    $freshmanRowCounters[$batchNumber]++;
                }

                if (strtolower($row['enrollment_status']) == 'transferee') {
                    if (!$transfereeSheet) {
                        $transfereeSheet = $spreadsheet->createSheet();
                        $transfereeSheet->setTitle('TRANSFEREE');
                        setSheetHeaders($transfereeSheet, $subjectColumns, 'COLLEGE ADMISSION TEST RESULT TRANSFEREE');
                    }
                    $dataRow[0] = $noTransferee++;
                    $transfereeSheet->fromArray($dataRow, NULL, 'A' . $transfereeRowCounter);
                    $transfereeSheet->getStyle('A' . $transfereeRowCounter . ':' . chr(65 + count($dataRow) - 1) . $transfereeRowCounter)->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                        'alignment' => ['wrapText' => true],
                        'font' => ['size' => 7]
                    ]);
                    $transfereeRowCounter++;
                }
            }
        } else {
            $sheetMaster->setCellValue('A4', 'No records found.');
        }

        // Set secure headers
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="college_admission_test_result.xlsx"');
        header('Cache-Control: max-age=0, must-revalidate');
        header('Pragma: public');
        header('Expires: 0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit();
    } else {
        http_response_code(405);
        die("Invalid request method.");
    }

    function setSheetHeaders($sheet, $subjectColumns, $headerTitle) {
        $gwaColumns = [
            "GWA (GR 11 - 1ST SEM)", "GWA (GR 11 - 2ND SEM)", 
            "GWA (GR 12 - 1ST SEM)", "GWA (GR 12 - 2ND SEM)", "TOTAL", "GWA"
        ];

        $headers = array_merge([
            "No.", "Full Name", "Date of Exam", "Contact Number", "First Course Preference", 
            "Second Course Preference", "Track/Strand Taken", "Enrollment Status", 
            "School Last Attended", "Complete Address", "Sex"
        ], $subjectColumns, ["Total Score", "Remarks"], $gwaColumns);

        $lastCol = chr(65 + count($headers) - 1); 

        $sheet->setCellValue('A1', htmlspecialchars($headerTitle, ENT_QUOTES, 'UTF-8'));
        $sheet->mergeCells("A1:$lastCol" . "1");
        $sheet->getStyle("A1")->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle("A1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->getRowDimension(2)->setRowHeight(10);
        $sheet->fromArray($headers, NULL, 'A3');
        $sheet->getStyle("A3:$lastCol" . "3")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER, 
                'vertical' => Alignment::VERTICAL_CENTER, 
                'wrapText' => true
            ],
            'font' => ['bold' => true, 'size' => 7]
        ]);
    }
?>