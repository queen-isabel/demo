<?php
require('../fpdf/fpdf.php');
include('../server.php');

class PDF extends FPDF {
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

        $this->Ln(12);
    }

    function ExamHeader($batch_number, $exam_date, $start_time, $end_time, $proctor_name) {
        $batch_number = htmlspecialchars(trim($batch_number), ENT_QUOTES, 'UTF-8');
        $exam_date = htmlspecialchars(trim($exam_date), ENT_QUOTES, 'UTF-8');
        $start_time = htmlspecialchars(trim($start_time), ENT_QUOTES, 'UTF-8');
        $end_time = htmlspecialchars(trim($end_time), ENT_QUOTES, 'UTF-8');
        $proctor_name = htmlspecialchars(trim($proctor_name), ENT_QUOTES, 'UTF-8');

        $this->SetFont('Arial', 'B', 9);
        $this->Cell(0, 7, "BATCH " . strtoupper($batch_number), 0, 1, 'C');
        $this->Cell(0, 7, strtoupper("$exam_date $start_time - $end_time"), 0, 1, 'C');
        $this->Cell(0, 7, "PROCTOR: " . strtoupper($proctor_name), 0, 1, 'C');
        $this->Ln(2);
    }

    function TableRow($index1, $name1, $index2 = "", $name2 = "") {
        $this->SetFont('Arial', '', 8);

        $name1 = mb_convert_encoding(trim($name1), 'ISO-8859-1', 'UTF-8');
        $name2 = mb_convert_encoding(trim($name2), 'ISO-8859-1', 'UTF-8');

        $this->Cell(10, 5, htmlspecialchars(trim($index1), ENT_QUOTES, 'UTF-8'), 1, 0, 'C');
        $this->Cell(75, 5, $name1, 1, 0, 'L');
        $this->Cell(10, 5, '', 0, 0); 

        if (!empty($index2) && !empty($name2)) {
            $this->Cell(10, 5, htmlspecialchars(trim($index2), ENT_QUOTES, 'UTF-8'), 1, 0, 'C');
            $this->Cell(75, 5, $name2, 1, 1, 'L');
        } else {
            $this->Cell(85, 5, '', 0, 1); 
        }
    }
}

$pdf = new PDF();
$pdf->AliasNbPages('{nb}');
$pdf->SetAutoPageBreak(true, 10);

$stmt = $conn->prepare("
    SELECT 
        b.batch_number, 
        s.exam_date, 
        s.exam_start_time, 
        s.exam_end_time, 
        p.proctor_name, 
        e.lname, 
        e.fname, 
        e.mname
    FROM 
        examinee_schedules es 
    JOIN 
        exam_schedules ex ON es.exam_schedule_id = ex.exam_schedule_id 
    JOIN 
        tbl_proctor p ON ex.proctor_id = p.proctor_id 
    JOIN 
        tbl_schedule s ON ex.schedule_id = s.schedule_id 
    JOIN 
        tbl_batch b ON s.batch_id = b.batch_id 
    JOIN 
        tbl_examinee e ON es.examinee_id = e.examinee_id 
    JOIN 
        tbl_school_year sy ON b.school_year_id = sy.school_year_id 
    WHERE 
        sy.school_year_status = 'active'
    ORDER BY 
        b.batch_number, s.exam_date, s.exam_start_time, p.proctor_name, e.lname ASC
");

$stmt->execute();
$result = $stmt->get_result();

$currentExam = "";
$examinees = [];

function outputExaminees($pdf, $examinees, $maxPerPage = 88) {
    $chunks = array_chunk($examinees, $maxPerPage);
    foreach ($chunks as $i => $pageData) {
        $firstCol = array_slice($pageData, 0, 44);
        $secondCol = array_slice($pageData, 44);

        $rows = max(count($firstCol), count($secondCol));

        for ($j = 0; $j < $rows; $j++) {
            $num1 = isset($firstCol[$j][0]) ? $firstCol[$j][0] : '';
            $name1 = isset($firstCol[$j][1]) ? $firstCol[$j][1] : '';
            $num2 = isset($secondCol[$j][0]) ? $secondCol[$j][0] : '';
            $name2 = isset($secondCol[$j][1]) ? $secondCol[$j][1] : '';
            $pdf->TableRow($num1, $name1, $num2, $name2);
        }
    }
}

$count = 0;
while ($row = $result->fetch_assoc()) {
    $batch_number = htmlspecialchars(trim($row['batch_number']), ENT_QUOTES, 'UTF-8');
    $exam_date = htmlspecialchars(trim(date("F d, Y", strtotime($row['exam_date']))), ENT_QUOTES, 'UTF-8');
    $start_time = htmlspecialchars(trim(date("g:i A", strtotime($row['exam_start_time']))), ENT_QUOTES, 'UTF-8');
    $end_time = htmlspecialchars(trim(date("g:i A", strtotime($row['exam_end_time']))), ENT_QUOTES, 'UTF-8');
    $proctor_name = htmlspecialchars(trim($row['proctor_name']), ENT_QUOTES, 'UTF-8');
    
    // Do NOT htmlspecialchars() the name here
    $lname = trim($row['lname']);
    $fname = trim($row['fname']);
    $mname = trim($row['mname']);

    $newExam = "$batch_number|$exam_date|$start_time|$proctor_name";

    if ($newExam !== $currentExam) {
        if (!empty($examinees)) {
            outputExaminees($pdf, $examinees);
        }

        $pdf->AddPage();
        $pdf->ExamHeader($batch_number, $exam_date, $start_time, $end_time, $proctor_name);
        $examinees = [];
        $count = 0;
        $currentExam = $newExam;
    }

    $examinees[] = [++$count, "$lname, $fname $mname"];
}

if (!empty($examinees)) {
    outputExaminees($pdf, $examinees);
}

$stmt->close();
$pdf->Output();
?>
