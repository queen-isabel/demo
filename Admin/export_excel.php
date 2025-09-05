<?php
require '../vendor/autoload.php';
include_once '../server.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\RichText\RichText;

session_start();

// 1. Get active school year
$sql = "SELECT school_year_id FROM tbl_school_year WHERE school_year_status = 'active' LIMIT 1";
$result = mysqli_query($conn, $sql);
$school_year = '';
if ($row = mysqli_fetch_assoc($result)) {
    $school_year = (int)$row['school_year_id'];
}
mysqli_free_result($result);

// 2. Get completed examinees
$school_year_escaped = mysqli_real_escape_string($conn, $school_year);
$sql = "SELECT DISTINCT ee.examinee_id
        FROM tbl_examinee_exam ee
        JOIN exam_schedules es ON ee.exam_schedule_id = es.exam_schedule_id
        JOIN tbl_schedule s ON es.schedule_id = s.schedule_id
        JOIN tbl_batch b ON s.batch_id = b.batch_id
        WHERE ee.status = 'completed' AND b.school_year_id = '$school_year_escaped'";
$result = mysqli_query($conn, $sql);

$completed_examinees = [];
while ($row = mysqli_fetch_assoc($result)) {
    $completed_examinees[] = (int)$row['examinee_id'];
}
$total_examinees = count($completed_examinees);
mysqli_free_result($result);

// 3. Get examinee answers
$sql = "SELECT ea.question_id, ea.selected_choice, q.correct_answer
        FROM tbl_examinee_answers ea
        JOIN tbl_questions q ON ea.question_id = q.question_id
        JOIN tbl_examinee_exam ee ON ea.examinee_exam_id = ee.id
        JOIN tbl_examinee e ON ee.examinee_id = e.examinee_id
        JOIN tbl_batch b ON e.batch_id = b.batch_id
        WHERE b.school_year_id = '$school_year_escaped' AND ee.status = 'completed'";
$result = mysqli_query($conn, $sql);

$responses = [];
while ($row = mysqli_fetch_assoc($result)) {
    $responses[] = [
        'question_id' => (int)$row['question_id'],
        'selected_choice' => $row['selected_choice'],
        'correct_answer' => $row['correct_answer']
    ];
}
mysqli_free_result($result);

// 4. Calculate question correctness
$question_correct = [];
foreach ($responses as $response) {
    $qid = (int)$response['question_id'];
    $selected = $response['selected_choice'];
    $correct = $response['correct_answer'];
    if (!isset($question_correct[$qid])) $question_correct[$qid] = 0;
    if ($selected == $correct) $question_correct[$qid]++;
}

// 5. Get all questions
$sql = "SELECT question_id, question FROM tbl_questions";
$result = mysqli_query($conn, $sql);

$all_questions = [];
while ($row = mysqli_fetch_assoc($result)) {
    $all_questions[] = [
        'question_id' => (int)$row['question_id'],
        'question' => $row['question'] 
    ];
}
mysqli_free_result($result);

// Function to process HTML and create rich text
function htmlToRichText($html, $sheet) {
    $richText = new RichText();
    $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
    
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<div>' . $html . '</div>');
    libxml_clear_errors();
    
    $body = $dom->getElementsByTagName('div')->item(0);
    
    foreach ($body->childNodes as $node) {
        if ($node->nodeType === XML_TEXT_NODE) {
            $richText->createText($node->nodeValue);
        } elseif ($node->nodeType === XML_ELEMENT_NODE) {
            $tag = strtolower($node->nodeName);
            $text = $node->textContent;
            
            if ($tag === 'b' || $tag === 'strong') {
                $boldRun = $richText->createTextRun($text);
                $boldRun->getFont()->setBold(true);
            } elseif ($tag === 'i' || $tag === 'em') {
                $italicRun = $richText->createTextRun($text);
                $italicRun->getFont()->setItalic(true);
            } elseif ($tag === 'u') {
                $underlineRun = $richText->createTextRun($text);
                $underlineRun->getFont()->setUnderline(true);
            } else {
                $richText->createText($text);
            }
        }
    }
    
    return $richText;
}

// Create Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set column widths
$sheet->getColumnDimension('A')->setWidth(60);
$sheet->getColumnDimension('B')->setWidth(20);
$sheet->getColumnDimension('C')->setWidth(15);
$sheet->getColumnDimension('D')->setWidth(25);

// Header with styling
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => [
        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
        'startColor' => ['rgb' => '4F81BD']
    ]
];

$sheet->setCellValue('A1', 'Question')->getStyle('A1')->applyFromArray($headerStyle);
$sheet->setCellValue('B1', 'No. of Correct Responses')->getStyle('B1')->applyFromArray($headerStyle);
$sheet->setCellValue('C1', 'Percentage')->getStyle('C1')->applyFromArray($headerStyle);
$sheet->setCellValue('D1', 'Remarks')->getStyle('D1')->applyFromArray($headerStyle);

// Fill Data
$row = 2;
foreach ($all_questions as $question) {
    $id = (int)$question['question_id'];
    $questionText = $question['question'];
    $correct = isset($question_correct[$id]) ? (int)$question_correct[$id] : 0;
    $percent = $total_examinees > 0 ? ($correct / $total_examinees) * 100 : 0;

    if ($percent >= 85) {
        $remark = "Easy";
    } elseif ($percent >= 50) {
        $remark = "Average";
    } else {
        $remark = "Difficult / Least Mastered";
    }

    // Convert HTML to RichText
    $richText = htmlToRichText($questionText, $sheet);
    
    $sheet->setCellValue("A$row", $richText);
    $sheet->setCellValue("B$row", $correct);
    $sheet->setCellValue("C$row", number_format($percent, 2) . "%");
    $sheet->setCellValue("D$row", $remark);

    // Apply alternating row colors for better readability
    if ($row % 2 == 0) {
        $sheet->getStyle("A$row:D$row")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFEFEFEF');
    }

    $row++;
}

// Freeze the header row
$sheet->freezePane('A2');

// Download the file with sanitized filename
$filename = "Item_Analysis_" . date('Ymd_His') . ".xlsx";
$safe_filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment;filename=\"$safe_filename\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>