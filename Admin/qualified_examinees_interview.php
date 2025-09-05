<?php
include('../server.php');
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Font;

$courses = [
    'Bachelor of Physical Education' => 'BPED',
    'Bachelor of Science in Architecture' => 'BSARCH',
    'Bachelor of Science in Civil Engineering' => 'BSCE',
    'Bachelor of Science in Electrical Engineering' => 'BSEE',
    'Bachelor of Science in Industrial Technology' => 'BSINDT',
    'Bachelor of Science in Information Technology' => 'BSINFOTECH',
    'Bachelor of Science in Midwifery' => 'BSM',
    'Bachelor of Science in Nursing' => 'BSN',
    'Bachelor of Science in Psychology' => 'BSPSYCH',
    'Bachelor of Secondary Education' => 'BSED',
    'Bachelor of Technical Vocational Teacher Education' => 'BTVTED',
    'Bachelor of Technology and Livelihood Education' => 'BTLED',
    'Diploma in Midwifery' => 'DM'
];

$zipFilename = tempnam(sys_get_temp_dir(), 'examinees_') . '.zip';
$zip = new ZipArchive();
if ($zip->open($zipFilename, ZipArchive::CREATE) !== TRUE) {
    die("Cannot create zip file");
}

foreach ($courses as $courseName => $courseCode) {
    $escapedCourseName = mysqli_real_escape_string($conn, $courseName);
    
    $sql = "SELECT 
                e.examinee_id, 
                e.lname, 
                e.fname, 
                e.mname, 
                e.contact_number, 
                e.second_preference,
                e.enrollment_status, 
                e.lschool_attended, 
                s.strand_name,
                c2.course_name as second_course_name, 
                DATE(ee.datetime_completed) as exam_date,
                CONCAT(e.lname, ', ', e.fname, ' ', e.mname) AS full_name,
                MAX(CASE WHEN sub.subject_name = 'English' THEN ss.score ELSE 0 END) as English,
                MAX(CASE WHEN sub.subject_name = 'Science' THEN ss.score ELSE 0 END) as Science,
                MAX(CASE WHEN sub.subject_name = 'Math' THEN ss.score ELSE 0 END) as Math,
                MAX(CASE WHEN sub.subject_name = 'Social Science' THEN ss.score ELSE 0 END) as `Social Science`,
                MAX(CASE WHEN sub.subject_name = 'Filipino' THEN ss.score ELSE 0 END) as Filipino,
                MAX(CASE WHEN sub.subject_name = 'Abstract Reasoning' THEN ss.score ELSE 0 END) as `Abstract Reasoning`
            FROM tbl_examinee e
            INNER JOIN tbl_examinee_exam ee ON e.examinee_id = ee.examinee_id
            INNER JOIN tbl_subject_score ss ON e.examinee_id = ss.examinee_id
            INNER JOIN tbl_subject sub ON ss.subject_id = sub.subject_id
            INNER JOIN tbl_course c ON e.first_preference = c.course_id
            LEFT JOIN tbl_course c2 ON e.second_preference = c2.course_id
            INNER JOIN tbl_strand s ON e.strand_id = s.strand_id
            WHERE c.course_name = '$escapedCourseName'
            AND ee.status = 'completed'
            AND e.contact_number IS NOT NULL
            AND e.second_preference IS NOT NULL
            AND e.enrollment_status IS NOT NULL
            AND e.lschool_attended IS NOT NULL
            AND ee.datetime_completed IS NOT NULL
            GROUP BY 
                e.examinee_id,
                e.lname,
                e.fname,
                e.mname,
                e.contact_number,
                e.second_preference,
                e.enrollment_status,
                e.lschool_attended,
                s.strand_name,
                c2.course_name,
                ee.datetime_completed
        ";

    $result = mysqli_query($conn, $sql);
    if (!$result) {
        die("Query failed: " . mysqli_error($conn));
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $headers = ['#', 'LAST NAME', 'FIRST NAME', 'MIDDLE NAME', 'FULL NAME', 'DATE OF EXAM', 'DATE OF INTERVIEW',
                'CONTACT NUMBER', 'COURSE: FIRST PREFERENCE', 'COURSE: SECOND PREFERENCE', 'TRACK/STRAND TAKEN',
                'STATUS', 'SCHOOL LAST ATTENDED', 'ENGLISH', 'SCIENCE', 'MATH', 'SOCIAL SCIENCE', 'FILIPINO',
                'ABSTRACT REASONING', 'CAT', '50%', 'GWA', '25%', 'INTERVIEW', '25%', 'TOTAL', 'RANK'];

    $sheet->fromArray($headers, null, 'A1');
    $sheet->getStyle('A1:AA1')->getFont()->setName('Arial Narrow')->setSize(12)->setBold(true);

    $row = 2;
    $counter = 0;
    $batchSize = 1000;
    $allRows = [];

    while ($examinee = mysqli_fetch_assoc($result)) {
        $counter++;
        $batchNumber = intval(($counter - 1) / $batchSize) + 1;

        $cat = $examinee['English'] + $examinee['Science'] + $examinee['Math'] +
            $examinee['Social Science'] + $examinee['Filipino'] + $examinee['Abstract Reasoning'];

        $data = [
            $counter,
            $examinee['lname'],
            $examinee['fname'],
            $examinee['mname'],
            $examinee['full_name'],
            $examinee['exam_date'],
            '',
            $examinee['contact_number'],
            $courseName,
            $examinee['second_course_name'],
            $examinee['strand_name'],
            $examinee['enrollment_status'],
            $examinee['lschool_attended'],
            $examinee['English'],
            $examinee['Science'],
            $examinee['Math'],
            $examinee['Social Science'],
            $examinee['Filipino'],
            $examinee['Abstract Reasoning'],
            $cat,
            '', '', '', '', '', '', ''
        ];

        $allRows[] = $data;
    }

    foreach ($allRows as $dataRow) {
        $sheet->fromArray([$dataRow], null, "A{$row}");
        $sheet->getStyle("A{$row}:Z{$row}")->getFont()->setName('Arial Narrow')->setSize(12);

        $sheet->setCellValue("U{$row}", "=T{$row}*0.50");
        $sheet->setCellValue("V{$row}", "");
        $sheet->setCellValue("W{$row}", "=V{$row}*0.25");
        $sheet->setCellValue("X{$row}", "");
        $sheet->setCellValue("Y{$row}", "=X{$row}*0.25");
        $sheet->setCellValue("Z{$row}", "=U{$row}+W{$row}+Y{$row}");

        $row++;
    }

    $lastRow = $row - 1;
    // Apply correct RANK formula
    for ($i = 2; $i <= $lastRow; $i++) {
        $sheet->setCellValue("AA{$i}", "=RANK(Z{$i},Z\$2:Z\${$lastRow},0)");
    }

    $tempFile = tempnam(sys_get_temp_dir(), 'excel_') . '.xlsx';
    $writer = new Xlsx($spreadsheet);
    $writer->save($tempFile);
    $zip->addFile($tempFile, "Qualified_Examinees_{$courseCode}.xlsx");
}

$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="Qualified_Examinees_All_Courses.zip"');
header('Content-Length: ' . filesize($zipFilename));
header('Pragma: no-cache');
header('Expires: 0');
readfile($zipFilename);
unlink($zipFilename);
exit;
?>