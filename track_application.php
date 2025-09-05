<?php
include('server.php');
require('fpdf/fpdf.php');

// Initialize variables
$error = '';
$status = '';
$unique_code = '';
$examinee_data = null;

// Handle PDF download request
if (isset($_GET['download'])) {
    $examinee_id = intval($_GET['examinee_id']);
    $examinee_id = mysqli_real_escape_string($conn, $examinee_id);

    $query = "SELECT e.*, c.course_name AS first_preference_name, c2.course_name AS second_preference_name, 
          b.batch_number, tsch.exam_date, tsch.exam_start_time, tsch.exam_end_time,
          e.enrollment_status, e.examinee_status
          FROM tbl_examinee e 
          LEFT JOIN tbl_course c ON e.first_preference = c.course_id 
          LEFT JOIN tbl_course c2 ON e.second_preference = c2.course_id 
          LEFT JOIN tbl_batch b ON e.batch_id = b.batch_id 
          LEFT JOIN tbl_schedule tsch ON b.batch_id = tsch.batch_id 
          WHERE e.examinee_id = '$examinee_id'";
    
    $result = mysqli_query($conn, $query);
    $examinee = mysqli_fetch_assoc($result);

    if ($examinee) {
        $pdf = new FPDF('P', 'mm', 'Legal');
        $pdf->AddPage();
        $pdf->SetMargins(12, 12, 12);
        $pdf->SetFont('Arial', '', 12);
        $pdf->AddFont('ArialNarrow', '', 'arialnarrow.php');
        $pdf->AddFont('ArialNarrow', 'B', 'arialnarrowbold.php');

        $imagePath = 'images/isulogo.png';
        $imagePath1 = 'images/osaslogo.png';

        $borderX = 8; 
        $borderY = 6; 
        $borderWidth = 23; 
        $borderHeight = 7; 

        $pdf->SetDrawColor(0, 0, 0); 
        $pdf->SetLineWidth(0); 
        $pdf->Rect($borderX, $borderY, $borderWidth, $borderHeight); 

        $pdf->SetFont('Times', '', 9);
        $pdf->Cell(0, 0, 'GC Form 1', 0, 1, 'L'); 

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

        $pdf->Image($imagePath, 55, $pdf->GetY() - 20, 15); 
        $pdf->Image($imagePath1, 140, $pdf->GetY() - 20, 15);

        $baseY = $pdf->GetY();
        $x = 158;
        $y = $baseY + 2;
        $borderWidth = 50;
        $borderHeight = 50;

        $pdf->SetDrawColor(0, 0, 0);
        $pdf->Rect($x, $y, $borderWidth, $borderHeight);

        $pdf->SetFont('Times', 'B', 20);
        $pdf->SetXY($x, $y + 10);
        $pdf->Cell($borderWidth, 8, 'Place 2x2', 0, 1, 'C');
        $pdf->SetX($x);
        $pdf->Cell($borderWidth, 8, 'picture', 0, 1, 'C');
        $pdf->SetX($x);
        $pdf->Cell($borderWidth, 8, 'here', 0, 1, 'C');

        $pdf->SetXY(12, $y);
        $pdf->SetFont('ArialNarrow', '', 12);
        $pdf->Cell(11, 5, 'Name:', 0, 0);
        $pdf->Cell(60, 5, '_______________________________________________________________', 0, 1);
        $pdf->Cell(0, 5, '                  Last Name                           First Name                     Middle Name', 0, 1);

        $pdf->Cell(40, 5, 'Course: First Preference:', 0, 0);
        $pdf->Cell(60, 5, '___________________________________________________', 0, 1); 

        $pdf->Cell(32, 5, 'Second Preference:', 0, 0);
        $pdf->SetFont('ArialNarrow', '', 7);
        $pdf->Cell(60, 5, '________________________________________________', 0, 0); 

        $pdf->SetFont('ArialNarrow', '', 12);
        $pdf->Cell(35, 5, '  Track/Strand Taken:', 0, 0); 
        $pdf->SetFont('ArialNarrow', '', 10);
        $pdf->Cell(60, 5, '_________', 0, 1); 

        $pdf->SetFont('ArialNarrow', '', 12);
        $pdf->Cell(30, 5, 'Enrollment Status:', 0, 0);
        $pdf->Cell(0, 5, '( ) Freshman    ( ) Transferee    ( ) Second Course    ( ) Special Student', 0, 1);

        $pdf->SetX(24);
        $pdf->Cell(0, 5, '  Others: ______________________________________________', 0, 1);

        $pdf->Cell(35, 5, 'School Last Attended:', 0, 0);
        $pdf->Cell(60, 5, '__________________________________________________', 0, 1); 

        $pdf->Cell(46, 5, 'Learners Reference Number:', 0, 0);
        $pdf->Cell(60, 5, '____________________________________________', 0, 1); 

        $pdf->Cell(27, 5, 'School Address:', 0, 0);
        $pdf->Cell(60, 5, '______________________________________________________', 0, 1); 

        $pdf->Cell(25, 5, 'Home Address:', 0, 0);
        $pdf->Cell(60, 5, '_______________________________________________________', 0, 1); 

        $pdf->SetFont('ArialNarrow', '', 12);
        $pdf->Cell(10, 5, 'Sex:', 0, 0);
        $pdf->Cell(35, 5, '( ) Male    ( ) Female', 0, 0);
        $pdf->Cell(15, 5, 'Birthday:', 0, 0);
        $pdf->Cell(25, 5, '___________', 0, 0); 
        $pdf->Cell(25, 5, 'Email Address:', 0, 0);
        $pdf->Cell(40, 5, '____________________', 0, 0);
        $pdf->Cell(16, 5, 'Contact #:', 0, 0);
        $pdf->Cell(0, 5, '____________', 0, 1);

        $pdf->Ln(3);
        $pdf->Cell(0, 5, '________________________________________', 0, 1, 'C');
        $pdf->Cell(0, 5, 'Student Signature Over Printed Name', 0, 1, 'C');

        $pdf->Ln(3);
        $pdf->SetFont('ArialNarrow', 'B', 12);
        $pdf->Cell(0, 5, 'Entrance Test Schedule: (for testing personnel only)', 0, 1);

        $pdf->SetFont('ArialNarrow', '', 12);
        $formattedDate = !empty($examinee['exam_date']) ? date('F j, Y', strtotime($examinee['exam_date'])) : 'To be announced';
        $timeOfExamination = (!empty($examinee['exam_start_time']) && !empty($examinee['exam_end_time'])) ? date('h:i A', strtotime($examinee['exam_start_time'])) . " - " . date('h:i A', strtotime($examinee['exam_end_time'])) : 'To be announced';

        $pdf->Cell(35, 5, 'Date of Examination:', 0, 0);
        $pdf->Cell(60, 5, '___________________', 0, 0);
        $pdf->SetXY($pdf->GetX() - 60, $pdf->GetY());
        $pdf->Cell(40, 5, $formattedDate, 0, 0);
        $pdf->Cell(10, 5, 'Time:', 0, 0);
        $pdf->Cell(60, 5, '_____________________', 0, 0);
        $pdf->SetXY($pdf->GetX() - 60, $pdf->GetY());
        $pdf->Cell(0, 5, $timeOfExamination, 0, 1);

        $pdf->Cell(12, 5, 'Venue:', 0, 0);
        $pdf->Cell(0, 5, '_______________________________  OR No. ___________________    Issued by. ___________________', 0, 1);
        $pdf->Ln(5);

        $pdf->SetFont('ArialNarrow', '', 7);
        $pdf->Cell(0, 3, 'ISU - Gui - EEF - 081', 0, 1, 'L'); 
        $pdf->Cell(0, 3, 'Effective: August 01, 2018', 0, 1, 'L'); 
        $pdf->Cell(0, 3, 'Revision: 0', 0, 1, 'L'); 

        $pdf->SetY($pdf->GetY() + 3);
        $pdf->SetLineWidth(0.2);
        $xStart = 10;
        $xEnd = 200;
        $yPos = $pdf->GetY();

        for ($i = $xStart; $i <= $xEnd; $i += 4) {
            $pdf->Line($i, $yPos, $i + 2, $yPos);
        }

        $pdf->Output('Application_Form.pdf', 'D');
        exit();
    }
}


// Handle status check request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $unique_code = trim($_POST['unique_code']);
    
    if (empty($unique_code)) {
        $error = "Please enter your unique code.";
    } else {
        $unique_code = mysqli_real_escape_string($conn, $unique_code);
        
$query = "SELECT e.*, c.course_name AS first_preference_name, c2.course_name AS second_preference_name, 
          b.batch_number
          FROM tbl_examinee e 
          LEFT JOIN tbl_course c ON e.first_preference = c.course_id 
          LEFT JOIN tbl_course c2 ON e.second_preference = c2.course_id 
          LEFT JOIN tbl_batch b ON e.batch_id = b.batch_id
          WHERE e.unique_code = '$unique_code'";        
        $result = mysqli_query($conn, $query);
        
        if (mysqli_num_rows($result) === 1) {
            $examinee_data = mysqli_fetch_assoc($result);
            $status = strtolower(trim($examinee_data['estatus']));
            $examinee_status = strtolower(trim($examinee_data['examinee_status'] ?? ''));
            
            // Normalize status values
            if (in_array($status, ['accepted', 'approved', 'accept'])) {
                $status = 'approved';
            } elseif (in_array($status, ['rejected', 'reject', 'denied'])) {
                $status = 'rejected';
                
                // Fetch rejection reasons if status is rejected
                $rejection_query = "SELECT reasons FROM tbl_rejection_reason 
                                    WHERE examinee_id = '".$examinee_data['examinee_id']."'";
                $rejection_result = mysqli_query($conn, $rejection_query);
                $rejection_reasons = array();
                
                while ($row = mysqli_fetch_assoc($rejection_result)) {
                    $rejection_reasons[] = $row['reasons'];
                }
                
                $examinee_data['rejection_reasons'] = $rejection_reasons;
            } else {
                $status = 'pending';
            }
        } else {
            $error = "Invalid unique code. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Status | College Admission Test</title>
    <link rel="icon" type="image/png" href="images/isulogo.png" />
    <!-- Bootstrap 5.3 CSS -->
    <link href="assets/css/plugins/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="css/bootstrap-icons.css">
    <!-- Google Fonts -->
  <?php include 'admin/links.php'; ?>
    <style>
        :root {
            --primary: #116736;
            --primary-light: #e8f5e9;
            --secondary: #f8f9fa;
            --accent: #ff6b35;
            --approved: #28a745;
            --rejected: #dc3545;
            --pending: #ffc107;
            --text-primary: #2d3748;
            --text-secondary: #4a5568;
            --light-gray: #f7fafc;
            --border-radius: 12px;
            --box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        
        body {
            background-color: var(--light-gray);
            font-family: 'Poppins', system-ui, -apple-system, sans-serif;
            color: var(--text-primary);
            line-height: 1.6;
        }
        
        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            transition: var(--transition);
        }
        
        .card-header {
            background-color: var(--primary);
            color: white;
            border-bottom: none;
            padding: 2.5rem 1.5rem;
            position: relative;
            overflow: hidden;
        }
        
        .card-header::before {
            content: "";
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
            transform: rotate(30deg);
        }
        
        .status-card {
            border-radius: var(--border-radius);
            transition: var(--transition);
            background: white;
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .status-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
        }
        
        .status-approved {
            border-left-color: var(--approved);
            background: linear-gradient(to right, rgba(40, 167, 69, 0.03) 0%, white 100%);
        }
        
        .status-rejected {
            border-left-color: var(--rejected);
            background: linear-gradient(to right, rgba(220, 53, 69, 0.03) 0%, white 100%);
        }
        
        .status-pending {
            border-left-color: var(--pending);
            background: linear-gradient(to right, rgba(255, 193, 7, 0.03) 0%, white 100%);
        }

        .btn-primary:active {
    background-color: #0a4122 !important; /* Darker shade of your primary color */
    border-color: #0a4122 !important;
    transform: translateY(1px) !important; /* Slight "pressed" effect */
    box-shadow: none !important; /* Remove shadow when pressed */
}

/* For removing the blue tint completely */
.btn-primary:active, 
.btn-primary:focus {
    background-color: var(--primary) !important;
    border-color: var(--primary) !important;
}
        
        .status-icon {
            font-size: 3rem;
            margin-bottom: 1.5rem;
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            width: 80px;
            height: 80px;
            line-height: 80px;
            border-radius: 50%;
            text-align: center;
        }
        
        .form-control {
            border-radius: var(--border-radius);
            padding: 0.75rem 1.25rem;
            border: 1px solid #e2e8f0;
            font-size: 1rem;
            transition: var(--transition);
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(17, 103, 54, 0.15);
        }
        
        .btn {
            border-radius: var(--border-radius);
            font-weight: 500;
            letter-spacing: 0.5px;
            padding: 0.75rem 1.5rem;
            transition: var(--transition);
        }
        
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background-color: #0d522b;
            border-color: #0d522b;
        }
        
        .btn-lg {
            padding: 1rem 2rem;
            font-size: 1.1rem;
        }
        
        .btn-outline-secondary {
            border: 1px solid #e2e8f0;
             color: #000 !important; 
        }
        
        .btn-outline-secondary:hover {
            background-color: #f8f9fa;
            color: #000 !important; 
        }
        
        .applicant-details {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            padding: 2rem;
        }
        
        .detail-label {
            font-weight: 500;
            color: var(--text-secondary);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }
        
        .detail-value {
            color: var(--text-primary);
            font-size: 1.05rem;
            font-weight: 500;
            margin-bottom: 1.25rem;
        }
        
        .download-btn {
            background-color: var(--primary);
            border: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            transition: var(--transition);
        }
        
        .download-btn:hover {
            background-color: #0d522b;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .logo {
            height: 5rem;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
        }
        
        .status-title {
            font-weight: 600;
            font-size: 1.75rem;
            margin-bottom: 1rem;
        }
        
        .divider {
            height: 1px;
            background: linear-gradient(to right, rgba(0,0,0,0) 0%, rgba(0,0,0,0.1) 50%, rgba(0,0,0,0) 100%);
            margin: 1.5rem 0;
        }
        
        .alert {
            border-radius: var(--border-radius);
        }
        
        .lead {
            font-size: 1.1rem;
            color: var(--text-secondary);
        }
        
        h2, h3, h4 {
            font-weight: 600;
        }
        
        .card-body {
            padding: 2.5rem;
        }
        
        /* Custom focus styles */
        .btn:focus-visible {
            outline: 0;
            box-shadow: 0 0 0 0.25rem rgba(17, 103, 54, 0.5);
        }
        
        /* Animation for status cards */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .status-card {
            animation: fadeInUp 0.6s ease-out forwards;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .logo {
                height: 3.5rem;
            }
            
            .card-header {
                padding: 1.5rem;
            }
            
            .card-body {
                padding: 1.5rem;
            }
            
            .status-icon {
                font-size: 2.5rem;
                width: 70px;
                height: 70px;
                line-height: 70px;
            }
            
            .status-title {
                font-size: 1.5rem;
            }
        }
        
        /* Enhanced typography */
        .text-muted {
            opacity: 0.8;
        }
        
        /* Improved spacing */
        .mb-6 {
            margin-bottom: 4rem;
        }
        
        .py-6 {
            padding-top: 4rem;
            padding-bottom: 4rem;
        }
        
    </style>
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-8">
                <div class="card mb-6">
                    <div class="card-header text-center">
                        <div class="logo-container">
                            <img src="images/isulogo.png" alt="ISU Logo" class="logo img-fluid">
                        </div>
                        <h2 class="mb-2 text-white">Check Your Application Status</h2>
                        <p class="mb-0 text-white-50">Isabela State University - Ilagan Campus</p>
                    </div>
                    
                    <div class="card-body p-4 p-md-5">
                        <?php if (empty($examinee_data)): ?>
                            <!-- Status Check Form -->
                            <form method="POST" action="" class="needs-validation" novalidate>
                                <div class="mb-4">
                                    <label for="unique_code" class="form-label fw-semibold">Enter your Unique Code</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white"><i class="bi bi-key-fill text-primary"></i></span>
                                        <input type="text" class="form-control form-control-lg" id="unique_code" name="unique_code" 
                                               value="<?php echo htmlspecialchars($unique_code); ?>" required
                                               placeholder="Enter your unique code">
                                    </div>
                                    <?php if (!empty($error)): ?>
                                        <div class="alert alert-danger mt-3 mb-0"><?php echo $error; ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="d-grid mt-4">
                                    <button type="submit" class="btn btn-primary btn-lg py-3 fw-semibold">
                                        <i class="bi bi-search me-2"></i> Check Status
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="status-card p-4 
                                <?php 
                                    if ($status == 'approved') echo 'status-approved';
                                    elseif ($status == 'rejected') echo 'status-rejected';
                                    else echo 'status-pending';
                                ?>">
                                <div class="text-center py-3">
                                    <?php if ($status == 'approved'): ?>
                                        <i class="bi bi-check-circle-fill status-icon text-success"></i>
                                        <h3 class="status-title text-success">Application Approved</h3>
                                    <?php elseif ($status == 'rejected'): ?>
                                        <i class="bi bi-x-circle-fill status-icon text-danger"></i>
                                        <h3 class="status-title text-danger">Application Rejected</h3>
                                    <?php else: ?>
                                        <i class="bi bi-hourglass-split status-icon text-warning"></i>
                                        <h3 class="status-title text-warning">Application Pending</h3>
                                    <?php endif; ?>
                                    
                                    <div class="divider my-4"></div>
                                    
                                    <div class="mt-3 px-3">
                                        <?php if ($status == 'approved'): ?>
                                            <p class="lead text-center">Congratulations! Your application to Isabela State University - Ilagan Campus has been approved.</p>
                                            <div class="text-center mt-4">
                                                <a href="?download=1&examinee_id=<?php echo $examinee_data['examinee_id']; ?>" class="btn btn-primary text-white download-btn px-4 py-3 fw-semibold">
                                                    <i class="bi bi-download me-2"></i> Download Application Form
                                                </a>
                                            </div>
                                        <?php elseif ($status == 'rejected'): ?>
                                            <p class="lead text-center">We regret to inform you that your application has not been approved for admission.</p>
                                                <?php if (!empty($examinee_data['rejection_reasons'])): ?>
                                                    <div class="alert alert-light mt-4 text-start">
                                                        
                                                        <strong>Reasons for rejection</strong>
                                                        <div class="mt-2">
                                                            <?php foreach ($examinee_data['rejection_reasons'] as $reason): ?>
                                                                <div class="d-flex align-items-start mb-2">
                                                                    <i class="bi bi-dash-circle me-2 text-danger"></i>
                                                                    <span><?php echo htmlspecialchars($reason); ?></span>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                <?php elseif (!empty($examinee_data['examinee_status'])): ?>
                                                    <div class="alert alert-light mt-4 text-start">
                                                        <i class="bi bi-info-circle me-2"></i>
                                                        <strong>Note:</strong> <?php echo htmlspecialchars($examinee_data['examinee_status']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            <div class="text-center mt-4">
                                                <a href="examinee_registration/register_examinee.php" class="btn btn-outline-primary px-4 py-2">
                                                    <i class="bi bi-arrow-repeat me-2"></i> Re-apply Now
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <p class="lead text-center">Your application is currently being reviewed by our admissions committee.</p>
                                            <p class="text-muted mt-4 text-center">
                                                <i class="bi bi-clock-history me-2"></i> 
                                                Last updated: <?php echo date('F j, Y', strtotime($examinee_data['updated_at'] ?? 'now')); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Application Status Display -->
                            <div class="application-status mt-5">
                                <h4 class="text-center mb-4 fw-semibold">Application Details</h4>
                                
                                <div class="applicant-details">
                                    <div class="row g-4">
                                        <div class="col-md-6">
                                            <div class="mb-4">
                                                <p class="detail-label">Full Name</p>
                                                <p class="detail-value"><?php echo htmlspecialchars($examinee_data['lname'] . ', ' . $examinee_data['fname'] . ' ' . $examinee_data['mname']); ?></p>
                                            </div>
                                            
                                            <div class="mb-4">
                                                <p class="detail-label">Learner's Reference Number</p>
                                                <p class="detail-value"><?php echo htmlspecialchars($examinee_data['lrn']); ?></p>
                                            </div>
                                            
                                            <div class="mb-4">
                                                <p class="detail-label">First Course Preference</p>
                                                <p class="detail-value"><?php echo htmlspecialchars($examinee_data['first_preference_name'] ?? 'Not specified'); ?></p>
                                            </div>
                                             <div class="mb-4">
                                            <p class="detail-label">Enrollment Status</p>
                                            <p class="detail-value"><?php echo htmlspecialchars($examinee_data['enrollment_status'] ?? 'Not specified'); ?></p>
                                        </div>
                                          <div class="mb-4">
    <p class="detail-label">Batch Number</p>
    <p class="detail-value">
        <?php 
        if (!empty($examinee_data['batch_number'])) {
            echo 'Batch ' . htmlspecialchars($examinee_data['batch_number']);
        } else {
            echo 'Not specified';
        }
        ?>
    </p>
</div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-4">
                                                <p class="detail-label">Contact Information</p>
                                                <p class="detail-value">
                                                    <i class="bi bi-telephone me-2"></i><?php echo htmlspecialchars($examinee_data['contact_number']); ?><br>
                                                    <i class="bi bi-envelope me-2"></i><?php echo htmlspecialchars($examinee_data['email']); ?>
                                                </p>
                                            </div>
                                            
                                            <div class="mb-4">
                                                <p class="detail-label">Second Course Preference</p>
                                                <p class="detail-value"><?php echo htmlspecialchars($examinee_data['second_preference_name'] ?? 'Not specified'); ?></p>
                                            </div>
                                            
                                            <div class="mb-4">
                                                <p class="detail-label">School Last Attended</p>
                                                <p class="detail-value"><?php echo htmlspecialchars($examinee_data['lschool_attended'] ?? 'Not specified'); ?></p>
                                            </div>
                                            <div class="mb-4">
                                                <p class="detail-label">Examinee Status</p>
                                                <p class="detail-value"><?php echo htmlspecialchars($examinee_data['examinee_status'] ?? 'Not specified'); ?></p>
                                            </div>
                                    </div>
                                </div>
                                
                                <div class="d-grid mt-5">
                                    <a href="track_application.php" class="btn btn-outline-secondary py-3">
                                        <i class="bi bi-arrow-left me-2"></i> Check Another Application
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                
            </div>
        </div>
    </div>



</body>
</html>