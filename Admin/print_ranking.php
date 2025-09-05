<?php
    session_start();
    include('../server.php');
    require('../fpdf/fpdf.php');

    // Validate request method and required POST data
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['generate_report'])) {
        die("Invalid request method");
    }

    // Sanitize inputs using real_escape_string
    $topNumber = isset($_POST['top_number']) ? (int)mysqli_real_escape_string($conn, $_POST['top_number']) : 10;
    if ($topNumber < 1 || $topNumber > 1000) {
        $topNumber = 10;
    }

    $enrollmentStatus = isset($_POST['enrollment_status']) ? mysqli_real_escape_string($conn, $_POST['enrollment_status']) : '';
    $enrollmentStatus = !empty($enrollmentStatus) ? strtoupper($enrollmentStatus) : '';

    $schoolYearId = isset($_POST['school_year_id']) ? (int)mysqli_real_escape_string($conn, $_POST['school_year_id']) : 0;
    if ($schoolYearId <= 0) {
        die("Invalid school year ID");
    }

    try {
        //Ranking query
        $query = "SELECT 
                CONCAT(e.lname, ', ', e.fname, ' ', e.mname) AS full_name,
                s.total_score,
                c1.course_name AS first_preference,
                c2.course_name AS second_preference,
                e.enrollment_status
            FROM tbl_examinee e
            INNER JOIN tbl_score s ON e.examinee_id = s.examinee_id
            LEFT JOIN tbl_course c1 ON e.first_preference = c1.course_id
            LEFT JOIN tbl_course c2 ON e.second_preference = c2.course_id
            INNER JOIN tbl_batch b ON e.batch_id = b.batch_id
            WHERE b.school_year_id = $schoolYearId";
        
        if (!empty($enrollmentStatus)) {
            $query .= " AND UPPER(e.enrollment_status) = '" . mysqli_real_escape_string($conn, $enrollmentStatus) . "'";
        }
        
        $query .= " ORDER BY s.total_score DESC LIMIT $topNumber";
        
        $rankResult = mysqli_query($conn, $query);
        
        if (!$rankResult) {
            die("Error executing query: " . mysqli_error($conn));
        }

        // Fetch school year name
        function fetchSchoolYearName($conn, $schoolYearId) {
            $query = "SELECT school_year FROM tbl_school_year WHERE school_year_id = $schoolYearId";
            $result = mysqli_query($conn, $query);
            
            if (!$result || mysqli_num_rows($result) === 0) {
                return "Unknown";
            }
            
            $row = mysqli_fetch_assoc($result);
            return htmlspecialchars($row['school_year']); // kept in output
        }

        $schoolYearName = fetchSchoolYearName($conn, $schoolYearId);

        // Generate PDF
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'College Admission Test - Ranking', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 5, "School Year: " . $schoolYearName, 0, 1, 'C');
        $pdf->Ln(2);

        // PDF Table Headers
        $pdf->SetFont('Arial', 'B', 6);
        $pdf->Cell(7, 10, 'Rank', 1);
        $pdf->Cell(40, 10, 'Examinee Name', 1);
        $pdf->Cell(60, 10, 'First Preference', 1);
        $pdf->Cell(55, 10, 'Second Preference', 1);
        $pdf->Cell(20, 10, 'Enrollment Status', 1);
        $pdf->Cell(10, 10, 'Score', 1);
        $pdf->Ln();

        // PDF Table Content
        $pdf->SetFont('Arial', '', 5);
        $rank = 0;
        $prevScore = null;
        $actualRank = 0;

        while ($row = mysqli_fetch_assoc($rankResult)) {
            if ($row['total_score'] !== $prevScore) {
                $rank = $actualRank + 1;
            }
            $actualRank++;

            // Output sanitization kept for PDF generation (as requested)
            $fullName = htmlspecialchars($row['full_name'], ENT_QUOTES, 'UTF-8');
            $firstPref = htmlspecialchars($row['first_preference'], ENT_QUOTES, 'UTF-8');
            $secondPref = htmlspecialchars($row['second_preference'], ENT_QUOTES, 'UTF-8');
            $enrollStatus = htmlspecialchars($row['enrollment_status'], ENT_QUOTES, 'UTF-8');
            $totalScore = htmlspecialchars($row['total_score'], ENT_QUOTES, 'UTF-8');

            $pdf->Cell(7, 10, $rank, 1);
            $pdf->Cell(40, 10, $fullName, 1);
            $pdf->Cell(60, 10, $firstPref, 1);
            $pdf->Cell(55, 10, $secondPref, 1);
            $pdf->Cell(20, 10, $enrollStatus, 1);
            $pdf->Cell(10, 10, $totalScore, 1);
            $pdf->Ln();

            $prevScore = $row['total_score'];
        }

        $pdf->Output();
        exit();

    } catch (Exception $e) {
        die("Database error: " . $e->getMessage());
    }
?>