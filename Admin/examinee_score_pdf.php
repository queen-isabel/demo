<?php
    require('../fpdf/fpdf.php');
    require '../server.php';

    class PDF extends FPDF {
        function Header() {
            $this->Image('../images/isulogo.png', 100, 5, 10);
            $this->SetFont('Times', '', 10);
            $this->Ln(5);
            $this->Cell(0, 5, 'Republic of the Philippines', 0, 1, 'C');
            $this->SetFont('Times', 'B', 12);
            $this->Cell(0, 5, 'Isabela State University', 0, 1, 'C');
            $this->SetFont('Times', '', 10);
            $this->Cell(0, 5, 'Ilagan Campus', 0, 1, 'C');
            $this->Cell(0, 5, 'City of Ilagan, Isabela', 0, 1, 'C');
            $this->Ln(5);

            $this->SetFont('Arial', '', 8);
            $this->Cell(0, 5, 'GUIDANCE AND COUNSELING OFFICE', 0, 1, 'C');
            $this->Cell(0, 5, 'ENTRANCE TEST RESULT', 0, 1, 'C');

            // Fetch Exam Date, Batch, and Proctor
            global $conn;
            $query = "SELECT sch.exam_date, b.batch_number, p.proctor_name
                    FROM exam_schedules ex
                    JOIN tbl_schedule sch ON ex.schedule_id = sch.schedule_id
                    JOIN tbl_batch b ON sch.batch_id = b.batch_id
                    JOIN tbl_proctor p ON ex.proctor_id = p.proctor_id
                    JOIN tbl_school_year sy ON b.school_year_id = sy.school_year_id
                    WHERE sy.school_year_status = 'active'
                    LIMIT 1";
            $result = mysqli_query($conn, $query);
            $exam_info = mysqli_fetch_assoc($result);

            $exam_date = isset($exam_info['exam_date']) ? htmlspecialchars($exam_info['exam_date']) : 'N/A';
            $batch_number = isset($exam_info['batch_number']) ? htmlspecialchars($exam_info['batch_number']) : 'N/A';

            // Display Exam Date, Batch, and Proctor
            $this->Cell(0, 5, '' . date('F d, Y', strtotime($exam_date)), 0, 1, 'C');
            $this->Cell(0, 5, 'BATCH ' . $batch_number, 0, 1, 'C');
            $this->Ln(3);
        }

        function Footer() {
            $this->SetY(-15);
            $this->SetFont('ArialNarrow', '', 7);
            $this->Cell(0, 3, 'ISUI-Gui-CaR-034', 0, 1, 'L');
            $this->Cell(0, 3, 'Effectivity:January 3, 2017', 0, 1, 'L');
            $this->Cell(0, 3, 'Revision:0', 0, 1, 'L');
        }
    }

    $pdf = new PDF();
    $pdf->AddPage();
    $pdf->AddFont('ArialNarrow', '', 'arialnarrow.php');
    $pdf->AddFont('arialnarrow', 'B', 'arialnarrowbold.php');

    // Fetch subjects
    $subjects = [];
    $query = "SELECT subject_id, subject_name FROM tbl_subject ORDER BY subject_id";
    $result = mysqli_query($conn, $query);
    while ($sub = mysqli_fetch_assoc($result)) {
        $subjects[$sub['subject_id']] = strtoupper(htmlspecialchars($sub['subject_name']));
    }

    // Table Header
    $pdf->SetFont('ArialNarrow', '', 7);
    $headerHeight = 10;
    $pdf->Cell(8, $headerHeight, '', 1, 0, 'C');
    $pdf->Cell(45, $headerHeight, 'NAME', 1, 0, 'C');

    foreach ($subjects as $subject_name) {
        $xPos = $pdf->GetX();
        $yPos = $pdf->GetY();

        if (strlen($subject_name) > 10) {
            $pdf->MultiCell(17, 5, wordwrap($subject_name, 8, "\n"), 1, 'C');
            $pdf->SetXY($xPos + 17, $yPos);
        } else {
            $pdf->Cell(17, $headerHeight, $subject_name, 1, 0, 'C');
        }
    }

    $pdf->SetTextColor(255, 0, 0); 
    $pdf->Cell(14, $headerHeight, 'RAW SCORE', 1, 0, 'C');
    $pdf->Cell(22, $headerHeight, 'REMARKS', 1, 1, 'C');

    // Fetch examinee data
    $query = "SELECT e.examinee_id, e.lname, e.fname, e.mname, s.total_score, s.remarks
            FROM tbl_examinee e
            JOIN examinee_schedules es ON e.examinee_id = es.examinee_id
            JOIN tbl_batch b ON e.batch_id = b.batch_id
            JOIN tbl_score s ON e.examinee_id = s.examinee_id
            JOIN tbl_school_year sy ON b.school_year_id = sy.school_year_id
            WHERE sy.school_year_status = 'active'
            ORDER BY e.lname ASC, e.fname ASC, e.mname ASC";
    $result = mysqli_query($conn, $query);

    $count = 1;
    while ($row = mysqli_fetch_assoc($result)) {
        // Sanitize and format name
        $lname = htmlspecialchars($row['lname']);
        $fname = htmlspecialchars($row['fname']);
        $mname = htmlspecialchars($row['mname']);
        
        $middle_initial = !empty($mname) ? strtoupper(substr($mname, 0, 1)) . '.' : '';
        $full_name = strtoupper($lname) . ', ' . strtoupper($fname) . ' ' . $middle_initial;

        // Fetch subject scores
        $scores = array_fill_keys(array_keys($subjects), 0);
        $examinee_id = (int)$row['examinee_id'];
        $score_query = "SELECT subject_id, score FROM tbl_subject_score 
                    WHERE examinee_id = '" . mysqli_real_escape_string($conn, $examinee_id) . "'";
        $score_result = mysqli_query($conn, $score_query);
        
        while ($score_row = mysqli_fetch_assoc($score_result)) {
            $scores[$score_row['subject_id']] = (int)$score_row['score'];
        }
        
        // Output row
        $pdf->SetFont('ArialNarrow', '', 7);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(8, 7, $count++, 1, 0, 'C');
        $pdf->Cell(45, 7, $full_name, 1);
        
        foreach ($scores as $subject_score) {
            $pdf->Cell(17, 7, $subject_score, 1, 0, 'C');
        }
        
        $pdf->SetTextColor(255, 0, 0);
        $pdf->Cell(14, 7, (int)$row['total_score'], 1, 0, 'C');
        $pdf->Cell(22, 7, htmlspecialchars($row['remarks']), 1, 0, 'C');
        $pdf->Ln();
        $pdf->SetTextColor(0, 0, 0);
    }

    $pdf->Output();
?>