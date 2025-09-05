<?php
require('../server.php');
require('../fpdf/fpdf.php');

class PDF extends FPDF
{
    function Header() {
        $this->Image('../images/isulogo.png', 50, 10, 18);
        $this->Image('../images/guidance-logo.png', 140, 10, 18);

        $this->SetFont('Times', '', 11);
        $this->Cell(0, 5, 'Republic of The Philippines', 0, 1, 'C');
        $this->SetFont('Times', 'B', 11);
        $this->Cell(0, 5, 'ISABELA STATE UNIVERSITY', 0, 1, 'C');
        $this->SetFont('Times', '', 11);
        $this->Cell(0, 5, 'City of Ilagan', 0, 1, 'C');

        $this->Ln(1);
        $this->SetLineWidth(0.7);
        $this->Line(10, 30, 200, 30);
        $this->Line(10, 31, 200, 31);

        $this->Ln(9);
    }

    function ExamHeader($batchNumber, $examDate, $startTime, $endTime) {
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(0, 7, 'FINAL LIST OF QUALIFIED EXAMINEES', 0, 1, 'C');
        $this->Cell(0, 7, "FOR $examDate $startTime - $endTime", 0, 1, 'C');
        $this->Cell(0, 7, "BATCH $batchNumber", 0, 1, 'C');
        $this->Ln(2);
    }

    function TableRow($index1, $code1, $name1, $index2 = "", $code2 = "", $name2 = "") {
        $this->SetFont('Arial', '', 8);
        $rowHeight = 4.4;

        $this->Cell(10, $rowHeight, $index1, 1, 0, 'C');
        $this->Cell(20, $rowHeight, $code1, 1, 0, 'C');
        $this->Cell(60, $rowHeight, $name1, 1, 0, 'L');
        $this->Cell(10, $rowHeight, '', 0, 0);

        if (!empty($index2) && !empty($name2)) {
            $this->Cell(10, $rowHeight, $index2, 1, 0, 'C');
            $this->Cell(20, $rowHeight, $code2, 1, 0, 'C');
            $this->Cell(60, $rowHeight, $name2, 1, 1, 'L');
        } else {
            $this->Cell(85, $rowHeight, '', 0, 1);
        }
    }
}

$pdf = new PDF();
$pdf->SetAutoPageBreak(true, 10);

$activeStatus = 'active';
$batchStmt = $conn->prepare(
    "SELECT b.batch_number, s.exam_date, s.exam_start_time, s.exam_end_time
     FROM tbl_batch b
     INNER JOIN tbl_school_year sy ON b.school_year_id = sy.school_year_id
     LEFT JOIN tbl_schedule s ON b.batch_id = s.batch_id
     WHERE sy.school_year_status = ?
     ORDER BY b.batch_number"
);
$batchStmt->bind_param("s", $activeStatus);
$batchStmt->execute();
$batchResult = $batchStmt->get_result();

if ($batchResult->num_rows === 0) {
    die('No batch data found.');
}

$estatus = 'approved';

while ($batch = $batchResult->fetch_assoc()) {
    $batchNumber = $batch['batch_number'];
    $examDate = strtoupper(date("F j, Y", strtotime($batch['exam_date'])));
    $startTime = date("g:i A", strtotime($batch['exam_start_time']));
    $endTime = date("g:i A", strtotime($batch['exam_end_time']));

    $examineeStmt = $conn->prepare(
        "SELECT e.lname, e.fname, e.mname, e.unique_code
         FROM tbl_examinee e
         INNER JOIN tbl_batch b ON e.batch_id = b.batch_id
         INNER JOIN tbl_school_year sy ON b.school_year_id = sy.school_year_id
         WHERE b.batch_number = ? AND sy.school_year_status = ? AND e.estatus = ?
         ORDER BY e.lname"
    );
    $examineeStmt->bind_param("sss", $batchNumber, $activeStatus, $estatus);
    $examineeStmt->execute();
    $examineeResult = $examineeStmt->get_result();

    if ($examineeResult->num_rows === 0) {
        continue;
    }

    $examinees = [];
    $index = 1;
    while ($row = $examineeResult->fetch_assoc()) {
        $uniqueCode = $row['unique_code'];
        $formattedName = ucwords(strtolower("{$row['lname']}, {$row['fname']} {$row['mname']}"));
        $fullName = mb_convert_encoding($formattedName, 'ISO-8859-1', 'UTF-8');
        $examinees[] = [$index, $uniqueCode, $fullName];
        $index++;
    }

    $chunks = array_chunk($examinees, 104);
    foreach ($chunks as $chunk) {
        $firstCol = array_slice($chunk, 0, 52);
        $secondCol = array_slice($chunk, 52);

        $pdf->AddPage();
        $pdf->ExamHeader($batchNumber, $examDate, $startTime, $endTime);

        $rows = max(count($firstCol), count($secondCol));
        for ($i = 0; $i < $rows; $i++) {
            $index1 = $firstCol[$i][0] ?? '';
            $code1 = $firstCol[$i][1] ?? '';
            $name1 = $firstCol[$i][2] ?? '';

            $index2 = $secondCol[$i][0] ?? '';
            $code2 = $secondCol[$i][1] ?? '';
            $name2 = $secondCol[$i][2] ?? '';

            $pdf->TableRow($index1, $code1, $name1, $index2, $code2, $name2);
        }
    }
    $examineeStmt->close();
}
$batchStmt->close();

$pdf->Output('D', "Final_List_All_Batches_" . date('Y-m-d') . ".pdf");
exit();
?>
