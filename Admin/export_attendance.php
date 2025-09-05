<?php
include('../server.php');
require '../vendor/autoload.php'; 

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

$spreadsheet = new Spreadsheet();
$spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

// Sanitize school year query
$schoolYearQuery = "SELECT school_year_id, school_year FROM tbl_school_year WHERE school_year_status = 'active' LIMIT 1";
$schoolYearResult = mysqli_query($conn, $schoolYearQuery);
if (!$schoolYearResult || mysqli_num_rows($schoolYearResult) === 0) {
    die('No active school year found.');
}

$schoolYearRow = mysqli_fetch_assoc($schoolYearResult);
$activeSchoolYear = $schoolYearRow['school_year'];
$activeSchoolYearId = (int)$schoolYearRow['school_year_id'];

// Sanitize batch query
$query = "SELECT DISTINCT b.batch_id, b.batch_number, s.exam_date, s.exam_start_time, s.exam_end_time
          FROM tbl_batch b
          JOIN tbl_schedule s ON b.batch_id = s.batch_id
          JOIN tbl_examinee e ON e.batch_id = b.batch_id
          WHERE b.school_year_id = $activeSchoolYearId
          AND e.estatus = 'approved'";

$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {
    while ($schedule = mysqli_fetch_assoc($result)) {
        $batchId = (int)$schedule['batch_id'];
        $batchNumber = (int)$schedule['batch_number'];
        $examDate = !empty($schedule['exam_date']) ? date('F j, Y', strtotime($schedule['exam_date'])) : '';
        $startTime = !empty($schedule['exam_start_time']) ? date('g:i A', strtotime($schedule['exam_start_time'])) : '';
        $endTime = !empty($schedule['exam_end_time']) ? date('g:i A', strtotime($schedule['exam_end_time'])) : '';

        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle("Batch $batchNumber");

        $drawing = new Drawing();
        $drawing->setName('ISU Logo');
        $drawing->setPath('../images/isulogo.png'); 
        $drawing->setHeight(120);
        $drawing->setCoordinates('D3');
        $drawing->setOffsetX(10);
        $drawing->setOffsetY(10);
        $drawing->setWorksheet($sheet);

        $centerAlignment = [
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ];

        $headers = [
            ['F1', 'Republic of the Philippines', false],
            ['F2', 'ISABELA STATE UNIVERSITY', true],
            ['F3', 'City of Ilagan, Isabela', false],
            ['F5', 'GUIDANCE & COUNSELING CENTER', true],
            ['F6', 'LOGBOOK', false],
            ['F7', 'COLLEGE ADMISSION TEST', true],
            ['F8', "Academic Year, $activeSchoolYear", false],
        ];

        foreach ($headers as [$cell, $text, $isBold]) {
            $sheet->mergeCells("$cell:H" . substr($cell, 1))->setCellValue($cell, $text);
            $sheet->getStyle($cell)->applyFromArray($centerAlignment);
            $sheet->getStyle($cell)->getFont()->setSize(13)->setBold($isBold);
        }

        $sheet->mergeCells('F9:H9')->setCellValue('F9', "SCHEDULE: $examDate $startTime - $endTime");
        $sheet->getStyle('F9')->applyFromArray($centerAlignment);
        $sheet->getStyle('F9')->getFont()->setSize(10)->setBold(true);

        $columnHeaders = ['NO.', 'Full Name', 'COURSE: FIRST PREFERENCE', 'COURSE: SECOND PREFERENCE', 'TRACK/STRAND TAKEN', 'ENROLMENT STATUS', 'SCHOOL LAST ATTENDED', 'COMPLETE ADDRESS', 'SEX', 'Contact Number', 'Signature', 'Received Test Result Signature Date'];
        $sheet->fromArray($columnHeaders, null, 'A11');

        // Set column widths for tighter fit
        $columnWidths = [5, 25, 20, 20, 15, 15, 20, 25, 8, 18, 15, 20];
        $columns = range('A', 'L');
        foreach ($columns as $i => $col) {
            $sheet->getColumnDimension($col)->setWidth($columnWidths[$i]);
        }

        // Apply wrap text and vertical center alignment to header row
        $sheet->getStyle('A11:L11')->applyFromArray([
            'alignment' => [
                'wrapText' => true,
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN]
            ],
            'font' => ['size' => 10, 'bold' => true]
        ]);

        // Set header row height
        $sheet->getRowDimension(11)->setRowHeight(40); // enough for 2 lines

        // Sanitize examinee query
        $safeBatchId = (int)$batchId;
        $examineeQuery = "SELECT e.lname, e.fname, e.mname, 
            c1.course_name AS first_pref, 
            c2.course_name AS second_pref, 
            st.strand_name, 
            e.enrollment_status, 
            e.lschool_attended, 
            e.home_address, 
            e.sex, 
            e.contact_number 
            FROM tbl_examinee e
            LEFT JOIN tbl_course c1 ON e.first_preference = c1.course_id
            LEFT JOIN tbl_course c2 ON e.second_preference = c2.course_id
            LEFT JOIN tbl_strand st ON e.strand_id = st.strand_id
            WHERE e.batch_id = $safeBatchId
            AND e.estatus = 'approved'
            ORDER BY e.lname ASC";

        $examineeResult = mysqli_query($conn, $examineeQuery);

        if ($examineeResult && mysqli_num_rows($examineeResult) > 0) {
            $rowNumber = 12;
            $index = 1;
            while ($examinee = mysqli_fetch_assoc($examineeResult)) {
                // No htmlspecialchars to allow names like O'Brien
                $fullName = "{$examinee['lname']}, {$examinee['fname']} {$examinee['mname']}";
                $data = [
                    $index,
                    $fullName,
                    $examinee['first_pref'],
                    $examinee['second_pref'],
                    $examinee['strand_name'],
                    $examinee['enrollment_status'],
                    $examinee['lschool_attended'],
                    $examinee['home_address'],
                    $examinee['sex'],
                    $examinee['contact_number'],
                    '',
                    ''
                ];
                $sheet->fromArray($data, null, "A$rowNumber");
                $sheet->getStyle("A$rowNumber:L$rowNumber")->applyFromArray([
                    'alignment' => ['wrapText' => true, 'horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'font' => ['size' => 10]
                ]);
                $rowNumber++;
                $index++;
            }
        }

        $sheet->getHeaderFooter()
            ->setOddHeader('')
            ->setOddFooter("&LISUI-Gui-LCT-043d\nEffectivity: January 03, 2017\nRevision: 0");

        // Page setup
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(0);

        $sheet->getSheetView()->setView(\PhpOffice\PhpSpreadsheet\Worksheet\SheetView::SHEETVIEW_PAGE_LAYOUT);
    }
}

$spreadsheet->removeSheetByIndex(0);

$filename = "Attendance_List.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit();
?>
