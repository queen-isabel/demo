<?php
session_start();
include('../server.php');
require('../fpdf/fpdf.php');

// Start output buffering
ob_start();

// Validate session and permissions
if (!isset($_SESSION['id_no'])) {
    header("Location: index.php");
    exit();
}

// Validate and sanitize input
if (isset($_POST['examinee_id'])) {
    $examinee_id = mysqli_real_escape_string($conn, $_POST['examinee_id']);
    if (!is_numeric($examinee_id)) {
        die("Invalid examinee ID");
    }

    // Initialize $subjectColumns
    $subjectColumns = [];

    // Fetch distinct subjects with real_escape_string
    $subjectQuery = "SELECT DISTINCT subject_name FROM tbl_subject ORDER BY subject_name";
    $subjectResult = mysqli_query($conn, $subjectQuery);
    
    if (!$subjectResult) {
        die("Error in subject query: " . mysqli_error($conn));
    }

    if (mysqli_num_rows($subjectResult) > 0) {
        while ($subjectRow = mysqli_fetch_assoc($subjectResult)) {
            $subjectColumns[] = $subjectRow['subject_name'];
        }
    }

    // Fetch examinee data with real_escape_string
    $scoreQuery = "SELECT 
    e.examinee_id, 
    CONCAT(e.lname, ', ', e.fname, ' ', e.mname) AS full_name, 
    c1.course_name AS first_preference, 
    c2.course_name AS second_preference, 
    st.strand_name AS strand_name,  
    e.lschool_attended, 
    e.lrn,
    e.school_address, 
    e.home_address, 
    e.sex, 
    e.birthday, 
    e.email, 
    e.contact_number,
    e.enrollment_status, 
    IFNULL(SUM(ts.score), 0) AS total_score, 
    GROUP_CONCAT(CONCAT(sub.subject_name, ': ', IFNULL(ts.score, 0)) ORDER BY sub.subject_name SEPARATOR ', ') AS subject_scores,
    MAX(sc.remarks) AS remarks,  -- Changed to use aggregation function
    MAX(ee.datetime_completed) AS datetime_completed,  -- Changed to use aggregation function
    MAX(ee.minutes_taking_exam) AS minutes_taking_exam,  -- Changed to use aggregation function
    MAX(sch.exam_date) AS exam_date,  -- Changed to use aggregation function
    MAX(sch.exam_start_time) AS exam_start_time,  -- Changed to use aggregation function
    MAX(sch.exam_end_time) AS exam_end_time  -- Changed to use aggregation function
FROM 
    tbl_examinee e
INNER JOIN tbl_examinee_exam ee ON e.examinee_id = ee.examinee_id
JOIN exam_schedules ex ON ee.exam_schedule_id = ex.exam_schedule_id
INNER JOIN tbl_score sc ON e.examinee_id = sc.examinee_id
INNER JOIN tbl_batch b ON e.batch_id = b.batch_id
LEFT JOIN tbl_course c1 ON e.first_preference = c1.course_id
LEFT JOIN tbl_course c2 ON e.second_preference = c2.course_id
LEFT JOIN tbl_subject sub ON 1=1
LEFT JOIN tbl_subject_score ts 
    ON sc.exam_schedule_id = ts.exam_schedule_id 
    AND sc.examinee_id = ts.examinee_id 
    AND ts.subject_id = sub.subject_id
LEFT JOIN tbl_schedule sch ON ex.schedule_id = sch.schedule_id
LEFT JOIN tbl_school_year sy ON b.school_year_id = sy.school_year_id
LEFT JOIN tbl_strand st ON e.strand_id = st.strand_id
WHERE e.examinee_id = '$examinee_id'
GROUP BY 
    e.examinee_id, 
    e.lname, e.fname, e.mname,  -- Added these as they're used in CONCAT
    c1.course_name, 
    c2.course_name, 
    st.strand_name,  
    e.lschool_attended, 
    e.lrn,
    e.school_address, 
    e.home_address, 
    e.sex, 
    e.birthday, 
    e.email, 
    e.contact_number,
    e.enrollment_status";

    $scoreResult = mysqli_query($conn, $scoreQuery);
    
    if (!$scoreResult) {
        die("Error fetching examinee data: " . mysqli_error($conn));
    }

    if (mysqli_num_rows($scoreResult) > 0) {
        generatePDF($scoreResult, $subjectColumns);
    }
}

function generatePDF($scoreResult, $subjectColumns) {
    ob_clean(); // Clean output buffer

    $pdf = new FPDF('P', 'mm', 'Legal');
    $imagePath = '../images/isulogo.png'; 
    $imagePath1 = '../images/osaslogo.png'; 

    // Validate image paths
    if (!file_exists($imagePath) || !file_exists($imagePath1)) {
        die("Required image files not found");
    }

    while ($row = $scoreResult->fetch_assoc()) {
        // Sanitize all data from database
        $row = array_map('htmlspecialchars', $row);

        // Generate two copies
        for ($i = 0; $i < 2; $i++) {
            $baseY = ($i == 0) ? 10 : 180;

            if ($i == 1) {
                $pdf->SetDrawColor(0, 0, 0);
                $pdf->SetLineWidth(0.2);
                for ($x = 10; $x < 205; $x += 5) { 
                    $pdf->Line($x, 172, $x + 3, 172);
                }
            }

            if ($i % 2 == 0) { 
                $pdf->AddPage();
                $pdf->SetMargins(12, 12, 12);
                $pdf->SetAutoPageBreak(true, 10);
                $pdf->AddFont('ArialNarrow', '', 'arialnarrow.php');
                $pdf->AddFont('arialnarrow', 'B', 'arialnarrowbold.php');
            }

            $pdf->SetY($baseY);

            // Add border and form header
            $borderX = 8;
            $borderY = $baseY - 3;
            $borderWidth = 23;
            $borderHeight = 7;

            $pdf->SetDrawColor(0, 0, 0);
            $pdf->SetLineWidth(0);
            $pdf->Rect($borderX, $borderY, $borderWidth, $borderHeight);

            $pdf->SetFont('Times', '', 9);
            $pdf->Cell(0, 0, 'GC Form 1', 0, 1, 'L'); 

            // Title section
            $pdf->SetFont('Times', '', 10);
            $pdf->Cell(0, 4, 'Republic of the Philippines', 0, 1, 'C');
            $pdf->SetFont('Times', 'B', 10);
            $pdf->Cell(0, 4, 'ISABELA STATE UNIVERSITY', 0, 1, 'C');
            $pdf->SetFont('Times', '', 10);
            $pdf->Cell(0, 4, 'Ilagan Campus', 0, 1, 'C');
            $pdf->SetFont('Times', 'B', 10);
            $pdf->Cell(0, 4, 'GUIDANCE & COUNSELING UNIT', 0, 1, 'C');
           
            $pdf->SetFont('ArialNarrow', 'B', 12);
            $pdf->SetXY(6, $pdf->GetY());
            $pdf->Cell(0, 7, 'PLEASE PRINT', 0, 0, 'L');
            
            $pdf->SetFont('Times', 'B', 10);
            $pdf->SetXY(0, $pdf->GetY());
            $pdf->Cell(215, 7, 'ENTRANCE EXAM FORM', 0, 1, 'C');

            // Add logos
            $pdf->Image($imagePath, 55, $pdf->GetY() - 20, 15); 
            $pdf->Image($imagePath1, 140, $pdf->GetY() - 20, 15);

            // Picture box
            $x = 158;
            $y = $baseY + 18;
            $borderWidth = 50;
            $borderHeight = 50;

            $pdf->SetDrawColor(0, 0, 0);
            $pdf->Rect($x, $y, $borderWidth, $borderHeight);

            $pdf->SetFont('Times', 'B', 20);
            $pdf->SetXY($x, $y + 12);
            $pdf->Cell($borderWidth, 8, 'Place 2x2', 0, 1, 'C');
            $pdf->SetX($x);
            $pdf->Cell($borderWidth, 8, 'picture', 0, 1, 'C');
            $pdf->SetX($x);
            $pdf->Cell($borderWidth, 8, 'here', 0, 1, 'C');

            // Examinee details
            $pdf->SetXY(12, $y + 5);
            $pdf->SetFont('ArialNarrow', '', 12);
            $pdf->Cell(11, 5, 'Name:', 0, 0);
            $pdf->Cell(60, 5, '_______________________________________________________________', 0, 0); 
            $pdf->SetXY($pdf->GetX() - 60, $pdf->GetY()); 
            $pdf->Cell(0, 5, $row['full_name'], 0, 1);
            $pdf->Cell(0, 5, '                  Last Name                           First Name                     Middle Name', 0, 1);


        // Print the course first preference label
        $pdf->Cell(40, 5, 'Course: First Preference:', 0, 0);

        $pdf->Cell(60, 5, '___________________________________________________', 0, 0); 
        $pdf->SetXY($pdf->GetX() - 60, $pdf->GetY()); 
        $pdf->Cell(60, 5, $row['first_preference'], 0, 1); 

        // Print the second preference 
        $pdf->Cell(32, 5, 'Second Preference:', 0, 0);
        $pdf->SetFont('ArialNarrow', '', 7);
        $pdf->Cell(60, 5, '________________________________________________', 0, 0); 
        $pdf->SetXY($pdf->GetX() - 60, $pdf->GetY()); 
        $pdf->Cell(55, 5, $row['second_preference'], 0, 0); 
        
        //Strand
        $pdf->SetFont('ArialNarrow', '', 12);
        $strandAbbreviations = [
            'Science Technology Engineering and Mathematics (STEM)' => 'STEM',
            'Accountancy Business Management (ABM)' => 'ABM',
            'Humanities and Social Science (HUMSS)' => 'HUMSS',
            'General Academic Strand (GAS)' => 'GAS',
            'Technical Vocational Livelihood - ICT' => 'TVL-ICT',
            'Technical Vocational Livelihood - HE' => 'TVL-HE',
            'Technical Vocational Livelihood - IA' => 'TVL-IA',
            'Technical Vocational Livelihood (TVL)' => 'TVL'
        ];
        
        $abbreviatedStrand = isset($strandAbbreviations[$row['strand_name']]) ? $strandAbbreviations[$row['strand_name']] : $row['strand_name'];
        
        // Print the Track/Strand Taken label
        $pdf->Cell(35, 5, '  Track/Strand Taken:', 0, 0); 
        $pdf->SetFont('ArialNarrow', '', 10);
        $pdf->Cell(60, 5, '_________', 0, 0); 
        $pdf->SetXY($pdf->GetX() - 60, $pdf->GetY()); 
        $pdf->Cell(0, 5, $abbreviatedStrand, 0, 1); 
        
        // Print the enrollment status
        $pdf->SetFont('ArialNarrow', '', 12);
        $pdf->Cell(30, 5, 'Enrollment Status:', 0, 0);

        $statuses = [
            'freshmen' => '( ) Freshman',
            'transferee' => '( ) Transferee',
            'second course' => '( ) Second Course',
            'special student' => '( ) Special Student'
        ];

        // Convert the enrollment status to lowercase for consistency
        $statusKey = strtolower(trim($row['enrollment_status']));

        // Mark the selected status with (/)
        if (isset($statuses[$statusKey])) {
            $statuses[$statusKey] = str_replace('( )', '(/)', $statuses[$statusKey]);
        }

        // Print all statuses with only the selected one marked
        $pdf->Cell(0, 5, implode('    ', $statuses), 0, 1);


        $pdf->SetX(24); // Adjust this value to move the text to the right
        $pdf->Cell(0, 5, '  Others: ______________________________________________', 0, 1);
        
        // Print the school last attended label
        $pdf->Cell(35, 5, 'School Last Attended:', 0, 0);

        // Create an underline and print the preference value above it
        $pdf->Cell(60, 5, '__________________________________________________', 0, 0); 
        $pdf->SetXY($pdf->GetX() - 60, $pdf->GetY()); 
        $pdf->Cell(60, 5, $row['lschool_attended'], 0, 1); 

        // Print the lrn label
        $pdf->Cell(46, 5, 'Learners Reference Number:', 0, 0);

        // Create an underline and print the preference value above it
        $pdf->Cell(60, 5, '____________________________________________', 0, 0); 
        $pdf->SetXY($pdf->GetX() - 60, $pdf->GetY()); 
        $pdf->Cell(60, 5, $row['lrn'], 0, 1); 

        // Print the school address label
        $pdf->Cell(27, 5, 'School Address:', 0, 0);

        // Create an underline and print the preference value above it
        $pdf->Cell(60, 5, '______________________________________________________', 0, 0); 
        $pdf->SetXY($pdf->GetX() - 60, $pdf->GetY()); 
        $pdf->Cell(60, 5, $row['school_address'], 0, 1); 

        // Print the home address label
        $pdf->Cell(25, 5, 'Home Address:', 0, 0);

        // Create an underline and print the preference value above it
        $pdf->Cell(60, 5, '_______________________________________________________', 0, 0); 
        $pdf->SetXY($pdf->GetX() - 60, $pdf->GetY()); 
        $pdf->Cell(60, 5, $row['home_address'], 0, 1); 

       // Print the sex, birthday, email, and contact number
        $pdf->SetFont('ArialNarrow', '', 12);
        $pdf->Cell(10, 5, 'Sex:', 0, 0);

        // Normalize sex value to lowercase for accurate comparison
        $sex = strtolower($row['sex']) === 'male' ? '(/) Male    ( ) Female' : '( ) Male    (/) Female';
        $pdf->Cell(35, 5, $sex, 0, 0);

         // Format the birthday as "May 1, 2025"
         $formattedBirthday = date('F j, Y', strtotime($row['birthday']));

         // Print the birthday
         $pdf->Cell(15, 5, 'Birthday:', 0, 0);
         $pdf->Cell(60, 5, '___________', 0, 0); 
         $pdf->SetXY($pdf->GetX() - 60, $pdf->GetY()); 
         $pdf->SetFont('ArialNarrow', '', 7); 
         $pdf->Cell(25, 5, $formattedBirthday, 0, 0); 

        // Set font size for the label "Email Address:"
        $pdf->SetFont('ArialNarrow', '', 12); // Keep the label "Email Address:" font size as 12
        $pdf->Cell(25, 5, 'Email Address:', 0, 0);

        // Set font size for the email value
        $pdf->SetFont('ArialNarrow', '', 7); // Change font size to 7 for the email value
        $pdf->Cell(60, 5, '__________________________', 0, 0); 
        $pdf->SetXY($pdf->GetX() - 60, $pdf->GetY()); 
        $pdf->Cell(40, 5, $row['email'], 0, 0); // Print the email value

        // Restore the original font size for the contact number
        $pdf->SetFont('ArialNarrow', '', 12);
        $pdf->Cell(16, 5, 'Contact #:', 0, 0);
        $pdf->Cell(60, 5, '____________', 0, 0); 
        $pdf->SetXY($pdf->GetX() - 60, $pdf->GetY()); 
        $pdf->Cell(0, 5, $row['contact_number'], 0, 1); // Print the email value


        $pdf->Ln(3);
        $pdf->Cell(0, 5, '________________________________________', 0, 1, 'C');
        $pdf->Cell(0, 5, 'Student Signature Over Printed Name', 0, 1, 'C');

        $pdf->Ln(3);

        $pdf->SetFont('ArialNarrow', 'B', 12);
        $pdf->Cell(0, 5, 'Entrance Test Schedule: (for testing personnel only)', 0, 1);

      // Assuming $row contains the examinee_exam data with datetime_completed and datetime_started
        $pdf->SetFont('ArialNarrow', '', 12);

       // Format the date as "May 1, 2025"
$formattedDate = date('F j, Y', strtotime($row['exam_date']));

// Format time as "1:00 PM - 3:00 PM"
$timeOfExamination = date('h:i A', strtotime($row['exam_start_time'])) . " - " . date('h:i A', strtotime($row['exam_end_time']));

// Date of Examination
$pdf->Cell(35, 5, 'Date of Examination:', 0, 0);
$pdf->Cell(60, 5, '___________________', 0, 0); 
$pdf->SetXY($pdf->GetX() - 60, $pdf->GetY()); 
$pdf->Cell(40, 5, $formattedDate, 0, 0); // Display the formatted date

// Time of Examination
$pdf->Cell(10, 5, 'Time:', 0, 0);
$pdf->Cell(60, 5, '_____________________', 0, 0); 
$pdf->SetXY($pdf->GetX() - 60, $pdf->GetY()); 
$pdf->Cell(0, 5, $timeOfExamination, 0, 1); // Display the formatted time


        //Venue
        $pdf->Cell(12, 5, 'Venue:', 0, 0);
        $pdf->Cell(0, 5, '_______________________________  OR No. ___________________    Issued by. ___________________', 0, 1);

        $pdf->Ln(3);
        $pdf->SetFont('ArialNarrow', 'B', 12);
        $pdf->Cell(0, 5, 'Entrance Test Result (for Guidance and Counseling Unit personnel only)', 0, 1);

    // Define the subject shortcuts with custom widths
    $subjectShortcuts = [
        'English' => ['short' => 'Eng', 'width' => 12],    // Width for English
        'Science' => ['short' => 'Sci', 'width' => 12],    // Width for Science
        'Math' => ['short' => 'Math', 'width' => 12],      // Width for Math
        'Social Science' => ['short' => "Soc\nSci", 'width' => 12], // Two-line format for Social Science
        'Filipino' => ['short' => 'Filipino', 'width' => 15], // Width for Filipino
        'Abstract Reasoning' => ['short' => "Abstract\nReasoning", 'width' => 20], // Two-line format for Abstract Reasoning
    ];

    // Result table headers
    $pdf->SetFont('ArialNarrow', '', 12);
    $pdf->SetLineWidth(0); 

    // Header cell with line breaks for two-line format
    foreach ($subjectColumns as $subject) {
        if (isset($subjectShortcuts[$subject])) {
            $shortenedSubject = $subjectShortcuts[$subject]['short'];
            $cellWidth = $subjectShortcuts[$subject]['width'];
        } else {
            $shortenedSubject = $subject; // Fallback to the full name if not defined
            $cellWidth = 15; // Default width for unspecified subjects
        }

        if ($subject === 'Social Science' || $subject === 'Abstract Reasoning') {
            // Use MultiCell for Social Science and Abstract Reasoning to break the line
            $x = $pdf->GetX();
            $y = $pdf->GetY();
            $pdf->MultiCell($cellWidth, 5, $shortenedSubject, 1, 'C');
            $pdf->SetXY($x + $cellWidth, $y);
        } else {
            $pdf->Cell($cellWidth, 10, $shortenedSubject, 1, 0, 'C');
        }
    }

    // Add total score and remarks headers
    $pdf->Cell(25, 10, 'Total Score', 1, 0, 'C');
    $pdf->Cell(65, 10, 'Remarks:', 1, 1, 'L');

    // Result table data
    $pdf->SetFont('ArialNarrow', '', 12);
    $subjectScores = explode(', ', $row['subject_scores']);
    foreach ($subjectColumns as $subject) {
        $scoreFound = false;
        foreach ($subjectScores as $subjectScore) {
            $parts = explode(': ', $subjectScore);
            if ($parts[0] === $subject) {
                // Use the same width as header for consistency
                $cellWidth = isset($subjectShortcuts[$subject]) ? $subjectShortcuts[$subject]['width'] : 5;
                $pdf->Cell($cellWidth, 10, $parts[1], 1, 0, 'C');
                $scoreFound = true;
                break;
            }
        }
        if (!$scoreFound) {
            // Use the same width as header for consistency
            $cellWidth = isset($subjectShortcuts[$subject]) ? $subjectShortcuts[$subject]['width'] : 5;
            $pdf->Cell($cellWidth, 10, '-', 1, 0, 'C');
        }
    }

    // Add total score and remarks data
    $pdf->Cell(25, 10, $row['total_score'], 1, 0, 'C');
    $pdf->Cell(65, 10, $row['remarks'], 1, 1, 'L');




        $pdf->Ln(5);
        $pdf->SetFont('ArialNarrow', '', 12);
        $pdf->Cell(18, 4, 'Issued by:', 0, 0);
        $pdf->Cell(0, 4, '____________________________________________     Date: ___________________', 0, 1);
        $pdf->SetFont('ArialNarrow', '', 10);
        $pdf->Cell(0, 4, '                                           Guidance Counselor', 0, 1);


      // Footer Content
$pdf->SetFont('ArialNarrow', '', 7);
$pdf->SetTextColor(0, 0, 0); // Ensure normal text is black
$pdf->Cell(0, 3, 'ISU - Gui - EEF - 081', 0, 1, 'L'); 
$pdf->Cell(0, 3, 'Effective: August 01, 2018', 0, 1, 'L'); 
$pdf->Cell(0, 3, 'Revision: 0', 0, 1, 'L'); 

// Adjust Y position to move the label UP
$pdf->Ln(-10); // Move 10 units up

// Label the copies
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor(255, 0, 0); // Set red text for label only
$pdf->SetDrawColor(255, 0, 0); // Red border

// Set X position to align to the right
$xPos = $pdf->GetPageWidth() - 60;
$pdf->SetXY($xPos, $pdf->GetY()); // Move label up

if ($i == 0) {
    $pdf->Cell(50, 10, 'CONTROLLED COPY', 1, 0, 'C');
} else {
    $pdf->Cell(50, 10, 'UNCONTROLLED COPY', 1, 0, 'C');
}

// Reset text color back to black for the rest of the document
$pdf->SetTextColor(0, 0, 0);


    }
}

ob_end_clean(); // Clean previous output before sending PDF
$pdf->Output();
}
?>