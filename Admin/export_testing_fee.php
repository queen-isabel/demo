<?php 
session_start();
include('../server.php');

// Verify session
if (!isset($_SESSION['id_no'])) {
    header("Location: index.php");
    exit();
}

require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Font;

$sql = "
    SELECT e.lname, e.fname, e.mname, e.sex, e.birthday, 
        c.course_name AS first_preference, e.enrollment_status, 
        e.email, e.contact_number
    FROM tbl_examinee e
    JOIN tbl_batch b ON e.batch_id = b.batch_id
    JOIN tbl_school_year sy ON b.school_year_id = sy.school_year_id
    JOIN tbl_course c ON e.first_preference = c.course_id
    JOIN tbl_release_result rr ON e.batch_id = rr.batch_id
    JOIN tbl_examinee_exam ee ON e.examinee_id = ee.examinee_id
    WHERE sy.school_year_status = 'active' 
    AND rr.release_status = 'released'
    AND ee.status = 'completed'
    ORDER BY e.lname ASC
";

$result = mysqli_query($conn, $sql);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$spreadsheet->getDefaultStyle()->getFont()->setName('Times New Roman')->setSize(11);

// Header
$sheet->mergeCells('A1:L1')->setCellValue('A1', 'Republic of the Philippines');
$sheet->mergeCells('A2:L2')->setCellValue('A2', 'ISABELA STATE UNIVERSITY');
$sheet->getStyle('A2')->getFont()->setBold(true)->setItalic(true);
$sheet->mergeCells('A3:L3')->setCellValue('A3', 'Echague, Isabela');
$sheet->getStyle('A3')->getFont()->setItalic(true);
$sheet->mergeCells('A4:L4')->setCellValue('A4', 'FREE HIGHER EDUCATION BILLING DETAILS');
$sheet->getStyle('A4')->getFont()->setBold(true);
$sheet->getStyle('A1:L4')->getAlignment()->setHorizontal('center');

$sheet->setCellValue('J5', 'Free HE Billing Details Reference Number: ');
$sheet->setCellValue('J6', 'Date: ' . date('m/d/Y'));

$sheet->mergeCells('A8:L8')->setCellValue('A8', 'ADMISSION FEES (Based on Section 7, Rule II of the IRR of RA 10931');
$sheet->getStyle('A8')->getFont()->setBold(true)->setSize(14);

// Column headers
$headers = [
    'A' => 'Sequence Number',
    'B' => 'Last Name',
    'C' => 'Given Name',
    'D' => 'Middle Initial',
    'E' => 'Sex at Birth (M/F)',
    'F' => 'Birthdate (mm/dd/yyyy)',
    'G' => 'Degree Program',
    'H' => 'Year Level',
    'I' => 'Email Address',
    'J' => 'Phone Number',
    'K' => 'Entrance/Admission Fees*',
    'L' => 'Remarks (Passed or Failed)'
];

foreach ($headers as $col => $header) {
    $sheet->setCellValue($col . '9', $header);
    $sheet->getStyle($col . '9')->getAlignment()->setHorizontal('center')->setVertical('center')->setWrapText(true);
}
$sheet->getStyle('A9:L9')->getFont()->setBold(true);

$sheet->setCellValue('B10', 'ILAGAN CAMPUS');
$sheet->getStyle('B10')->getAlignment()->setHorizontal('left');
$sheet->getStyle('B10')->getFont()->setBold(true);
$sheet->getStyle('A10:L10')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD3D3D3');

$sheet->setCellValue('A11', '*To be filled once the fee is particular to admissions/entrance Examination');
$sheet->getStyle('A11')->getFont()->setBold(true)->setItalic(true);

// Values
$entrance_fee = 100;
$remarks = 'PASSED';
$rowNum = 12;
$sequence = 1;

while ($row = mysqli_fetch_assoc($result)) {
    $lname = $row['lname'];
    $fname = $row['fname'];
    $mname = $row['mname'];
    $sex = $row['sex'];
    $birthday_raw = $row['birthday'];

    $birthday = '';
    if (!empty($birthday_raw)) {
        $dateObj = DateTime::createFromFormat('Y-m-d', $birthday_raw);
        if ($dateObj) {
            $birthday = $dateObj->format('m/d/Y');
        }
    }

    $first_preference = $row['first_preference'];
    $enrollment_status = $row['enrollment_status'];
    $email = $row['email'];
    $contact_number = $row['contact_number'];

    // Fill in data row
    $sheet->setCellValue('A' . $rowNum, $sequence);
    $sheet->setCellValueExplicit('B' . $rowNum, $lname, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValueExplicit('C' . $rowNum, $fname, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValueExplicit('D' . $rowNum, $mname, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('E' . $rowNum, $sex);
    $sheet->setCellValue('F' . $rowNum, $birthday);
    $sheet->setCellValue('G' . $rowNum, $first_preference);
    $sheet->setCellValue('H' . $rowNum, $enrollment_status);
    $sheet->setCellValue('I' . $rowNum, $email);
    $sheet->setCellValue('J' . $rowNum, $contact_number);
    $sheet->setCellValue('K' . $rowNum, $entrance_fee);
    $sheet->setCellValue('L' . $rowNum, $remarks);

    $sequence++;
    $rowNum++;
}

// Apply borders
$highestRow = $sheet->getHighestRow();
$sheet->getStyle('A8:L' . $highestRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// Output
$filename = 'testing_fee_' . date('Y-m-d') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

mysqli_close($conn);
exit();
?>