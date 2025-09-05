<?php
require('../server.php');
require_once '../vendor/autoload.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;

$phpWord = new PhpWord();
$sectionStyle = [
    'marginTop' => 360,
    'marginBottom' => 360,
    'marginLeft' => 600,
    'marginRight' => 600,
    'pageSizeH' => 16900,
    'pageSizeW' => 12240,
];

// Get batches with examinees using prepared statement
$batchQuery = "
    SELECT DISTINCT b.batch_number, s.exam_date, s.exam_start_time, s.exam_end_time
    FROM tbl_batch b
    INNER JOIN tbl_school_year sy ON b.school_year_id = sy.school_year_id
    INNER JOIN tbl_examinee e ON b.batch_id = e.batch_id
    LEFT JOIN tbl_schedule s ON b.batch_id = s.batch_id
    WHERE sy.school_year_status = 'active'
      AND e.estatus = 'approved'
    ORDER BY b.batch_number
";

$batchStmt = $conn->prepare($batchQuery);
$batchStmt->execute();
$batchResult = $batchStmt->get_result();

if (!$batchResult || $batchResult->num_rows === 0) {
    die('No batch data with examinees found.');
}

while ($batch = $batchResult->fetch_assoc()) {
    $section = $phpWord->addSection($sectionStyle);

    $singleLineSpacing = [
        'spaceBefore' => 0,
        'spaceAfter' => 0,
        'lineSpacing' => 1.0
    ];

    $header = $section->addHeader();
    $headerTable = $header->addTable([
        'alignment' => Jc::CENTER,
        'cellMarginLeft' => 0,
        'cellMarginRight' => 0,
        'cellSpacing' => 0,
    ]);

    $headerTable->addRow();
    $headerTable->addCell(600)->addImage('../images/isulogo.png', [
        'width' => 50, 'height' => 50,
        'wrappingStyle' => 'square',
        'alignment' => Jc::CENTER
    ]);

    $textCell = $headerTable->addCell(4000);
    $textCell->addText('Republic of The Philippines', ['name' => 'Times New Roman', 'size' => 11], ['alignment' => Jc::CENTER]);
    $textCell->addText('ISABELA STATE UNIVERSITY', ['name' => 'Times New Roman', 'size' => 11, 'bold' => true], ['alignment' => Jc::CENTER]);
    $textCell->addText('City of Ilagan', ['name' => 'Times New Roman', 'size' => 11], ['alignment' => Jc::CENTER]);

    $headerTable->addCell(600)->addImage('../images/guidance-logo.png', [
        'width' => 50, 'height' => 50,
        'wrappingStyle' => 'square',
        'alignment' => Jc::CENTER
    ]);

    $header->addText('___________________________________________________________________', [], ['alignment' => Jc::CENTER]);

    // Sanitize batch data
    $batchNumber = (int)$batch['batch_number'];
    $examDate = !empty($batch['exam_date']) ? date("F j, Y", strtotime($batch['exam_date'])) : '';
    $startTime = !empty($batch['exam_start_time']) ? date("g:i A", strtotime($batch['exam_start_time'])) : '';
    $endTime = !empty($batch['exam_end_time']) ? date("g:i A", strtotime($batch['exam_end_time'])) : '';

    // Escape output for Word document
    $safeExamDate = htmlspecialchars(strtoupper($examDate), ENT_QUOTES, 'UTF-8');
    $safeStartTime = htmlspecialchars($startTime, ENT_QUOTES, 'UTF-8');
    $safeEndTime = htmlspecialchars($endTime, ENT_QUOTES, 'UTF-8');

    $section->addText("FINAL LIST OF QUALIFIED EXAMINEES", ['bold' => true, 'size' => 10, 'name' => 'Aptos'], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
    $section->addText("FOR $safeExamDate $safeStartTime - $safeEndTime", ['bold' => true, 'size' => 10, 'name' => 'Aptos'], ['alignment' => Jc::CENTER, 'spaceAfter' => 0]);
    $section->addText("BATCH $batchNumber", ['bold' => true, 'size' => 10, 'name' => 'Aptos'], ['alignment' => Jc::CENTER, 'spaceAfter' => 100]);

    // Get examinees for this batch using prepared statement
    $examineeQuery = "
        SELECT e.lname, e.fname, e.mname
        FROM tbl_examinee e
        INNER JOIN tbl_batch b ON e.batch_id = b.batch_id
        INNER JOIN tbl_school_year sy ON b.school_year_id = sy.school_year_id
        WHERE b.batch_number = ?
          AND sy.school_year_status = 'active'
          AND e.estatus = 'approved'
        ORDER BY e.lname
    ";
    
    $examineeStmt = $conn->prepare($examineeQuery);
    $examineeStmt->bind_param("i", $batchNumber);
    $examineeStmt->execute();
    $examineeResult = $examineeStmt->get_result();
    
    if (!$examineeResult || $examineeResult->num_rows === 0) {
        $examineeStmt->close();
        continue;
    }

    $examinees = [];
    $index = 1;
    while ($row = $examineeResult->fetch_assoc()) {
        // Sanitize names
        $lname = htmlspecialchars(trim($row['lname']), ENT_QUOTES, 'UTF-8');
        $fname = htmlspecialchars(trim($row['fname']), ENT_QUOTES, 'UTF-8');
        $mname = htmlspecialchars(trim($row['mname']), ENT_QUOTES, 'UTF-8');
        
        $formattedName = ucwords(strtolower("$lname, $fname $mname"));
        $examinees[] = [$index, $formattedName];
        $index++;
    }
    $examineeStmt->close();

    $examinees = array_slice($examinees, 0, 104);

    $phpWord->addTableStyle('ExamineeTableNoBorders', [
        'alignment' => Jc::CENTER,
        'borderSize' => 0,
        'cellMargin' => 0,
        'cellMarginTop' => 0,
        'cellMarginBottom' => 0,
        'cellMarginLeft' => 50,
        'cellMarginRight' => 50,
    ]);

    if (count($examinees) > 0) {
        $table = $section->addTable('ExamineeTableNoBorders');
        $textStyle8 = ['name' => 'Aptos', 'size' => 8];
        $paraStyleTight = ['alignment' => Jc::LEFT, 'lineSpacing' => 1.0, 'spaceBefore' => 0, 'spaceAfter' => 0];

        for ($i = 0; $i < 52; $i++) {
            $left = $examinees[$i] ?? ['', ''];
            $right = $examinees[$i + 52] ?? ['', ''];

            $table->addRow(200, ['exactHeight' => true]);
            $leftCellStyle = ($left[0] === '') ? ['borderSize' => 0, 'borderColor' => 'FFFFFF'] : [];
            $rightCellStyle = ($right[0] === '') ? ['borderSize' => 0, 'borderColor' => 'FFFFFF'] : [];

            $table->addCell(500, $leftCellStyle)->addText($left[0], $textStyle8, $paraStyleTight);
            $table->addCell(3500, $leftCellStyle)->addText($left[1], $textStyle8, $paraStyleTight);
            $table->addCell(200, ['borderSize' => 0, 'borderColor' => 'FFFFFF'])->addText('', [], $paraStyleTight);
            $table->addCell(500, $rightCellStyle)->addText($right[0], $textStyle8, $paraStyleTight);
            $table->addCell(3500, $rightCellStyle)->addText($right[1], $textStyle8, $paraStyleTight);
        }
    }
}
$batchStmt->close();

$fileName = "Final_List_All_Batches_" . date('Y-m-d') . ".docx";
$safeFileName = htmlspecialchars($fileName, ENT_QUOTES, 'UTF-8');

header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $safeFileName . '"');
header('Cache-Control: max-age=0');

$phpWordWriter = IOFactory::createWriter($phpWord, 'Word2007');
$phpWordWriter->save('php://output');
exit();
?>