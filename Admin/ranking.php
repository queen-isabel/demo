<?php
session_start();
include('../server.php');
require('../fpdf/fpdf.php');

// Regenerate session ID to prevent session fixation
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id();
    $_SESSION['initiated'] = true;
}

// Check authentication
if (!isset($_SESSION['id_no']) || empty($_SESSION['id_no'])) {
    header("Location: index");
    exit();
}

// Get admin info 
$admin_id_no = mysqli_real_escape_string($conn, $_SESSION['id_no']);
$admin_name = '';

$sql = "SELECT name FROM tbl_admin WHERE id_no = '$admin_id_no'";
$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    $admin_name = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
}

$enrollmentStatus = '';

if (isset($_POST['generate_report'])) {
    $topNumber = (int)$_POST['top_number'];
    $enrollmentStatus = isset($_POST['enrollment_status']) 
        ? mysqli_real_escape_string($conn, strtoupper($_POST['enrollment_status']))
        : '';
    $schoolYearId = (int)$_POST['school_year_id'];

    if ($topNumber <= 0) {
        die("Invalid top number value");
    }

    // Rank Query 
    $rankQuery = "SELECT 
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
    WHERE b.school_year_id = '$schoolYearId'";

    if (!empty($enrollmentStatus)) {
        $rankQuery .= " AND UPPER(e.enrollment_status) = '$enrollmentStatus'";
    }

    $rankQuery .= " ORDER BY s.total_score DESC LIMIT $topNumber";

    $rankResult = mysqli_query($conn, $rankQuery);
    
    if (!$rankResult) {
        die("Error fetching rankings: " . mysqli_error($conn));
    }

    // Fetch school year name
    function fetchSchoolYearName($conn, $schoolYearId) {
        $schoolYearId = (int) $_POST ['$schoolYearId'];
        $result = "SELECT school_year FROM tbl_school_year WHERE school_year_id = '$schoolYearId'";
        $row = mysqli_query($conn, $result);
        return isset($row['school_year']) ? htmlspecialchars($row['school_year']) : "Unknown School Year";
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

    $pdf->SetFont('Arial', 'B', 6);
    $pdf->Cell(7, 10, 'Rank', 1);
    $pdf->Cell(40, 10, 'Examinee Name', 1);
    $pdf->Cell(60, 10, 'First Preference', 1);
    $pdf->Cell(55, 10, 'Second Preference', 1);
    $pdf->Cell(20, 10, 'Enrollment Status', 1);
    $pdf->Cell(10, 10, 'Score', 1);
    $pdf->Ln();

    $pdf->SetFont('Arial', '', 5);
    $rank = 0;
    $prevScore = null;
    $actualRank = 0;
    
    while ($row = mysqli_fetch_assoc($rankResult)) {
        if ($row['total_score'] !== $prevScore) {
            $rank = $actualRank + 1;
        }
        $actualRank++;
    
        $pdf->Cell(7, 10, $rank, 1);
        $pdf->Cell(40, 10, htmlspecialchars($row['full_name']), 1);
        $pdf->Cell(60, 10, htmlspecialchars($row['first_preference']), 1);
        $pdf->Cell(55, 10, htmlspecialchars($row['second_preference']), 1);
        $pdf->Cell(20, 10, htmlspecialchars($row['enrollment_status']), 1);
        $pdf->Cell(10, 10, htmlspecialchars($row['total_score']), 1);
        $pdf->Ln();
    
        $prevScore = $row['total_score'];
    }

    $pdf->Output();
    exit();
}

$topNumber = isset($_POST['top_number']) ? (int)$_POST['top_number'] : 10;
$activeSchoolYearId = getActiveSchoolYearId($conn);
$selectedSchoolYearId = isset($_POST['school_year_id']) 
    ? (int)$_POST['school_year_id']
    : $activeSchoolYearId;

// ranking query
$rankQuery = "SELECT 
    CONCAT(e.lname, ', ', e.fname, ' ', e.mname) AS full_name,
    s.total_score,
    c1.course_name AS first_preference,
    c2.course_name AS second_preference,
    e.enrollment_status
FROM tbl_examinee e
INNER JOIN tbl_score s ON e.examinee_id = s.examinee_id
INNER JOIN tbl_batch b ON e.batch_id = b.batch_id
LEFT JOIN tbl_course c1 ON e.first_preference = c1.course_id
LEFT JOIN tbl_course c2 ON e.second_preference = c2.course_id
WHERE b.school_year_id = '$selectedSchoolYearId'
ORDER BY s.total_score DESC LIMIT $topNumber";

$rankResult = mysqli_query($conn, $rankQuery);

if (!$rankResult) {
    die("Error fetching rankings: " . mysqli_error($conn));
}

// Fetch all school years
function fetchSchoolYears($conn) {
    $sql = "SELECT school_year_id, school_year FROM tbl_school_year ORDER BY school_year DESC";
    $result = mysqli_query($conn, $sql);
    
    $schoolYears = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $schoolYears[] = [
            'school_year_id' => (int)$row['school_year_id'],
            'school_year' => htmlspecialchars($row['school_year'], ENT_QUOTES, 'UTF-8')
        ];
    }
    return $schoolYears;
}

$schoolYears = fetchSchoolYears($conn);

// Fetch exam name
$schoolYearIdEscaped = mysqli_real_escape_string($conn, $selectedSchoolYearId);
$examSql = "SELECT e.exam_name
            FROM exams e
            INNER JOIN exam_schedules es ON e.exam_id = es.exam_id
            INNER JOIN tbl_schedule s ON es.schedule_id = s.schedule_id
            INNER JOIN tbl_batch b ON s.batch_id = b.batch_id
            INNER JOIN tbl_school_year sy ON b.school_year_id = sy.school_year_id
            WHERE sy.school_year_id = '$schoolYearIdEscaped'
            LIMIT 1";
$examResult = mysqli_query($conn, $examSql);

$examName = 'Exam Name Not Found';
if ($examResult && mysqli_num_rows($examResult) > 0) {
    $examRow = mysqli_fetch_assoc($examResult);
    $examName = htmlspecialchars($examRow['exam_name'], ENT_QUOTES, 'UTF-8');
}

// Helper function to fetch active school year
function getActiveSchoolYearId($conn) {
    $sql = "SELECT school_year_id FROM tbl_school_year WHERE school_year_status = 'active' LIMIT 1";
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return (int)$row['school_year_id'];
    }
    return null;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
<title>Ranking | College Admission Test</title>
<link rel="icon" type="image/png" href="../images/isulogo.png" />

  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">

  <?php include 'links.php'; ?>


  <style>
    .pc-sidebar .pc-navbar .pc-link {
  text-decoration: none !important;
}
.pc-header .pc-head-link {
  cursor: pointer;
  text-decoration: none !important;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}
.pc-link.active,
.pc-link:hover {
  background-color: #042d16 !important;
  color: white !important;
}

  </style>
</head>

<body data-pc-preset="preset-1" data-pc-direction="ltr" data-pc-theme="light">
<div class="loader-bg">
  <div class="loader-track">
    <div class="loader-fill"></div>
  </div>
</div>
 <!-- [ Sidebar Menu ]  -->
 <?php include 'navbar.php'; ?>

<header class="pc-header">
  <div class="header-wrapper">
<div class="me-auto pc-mob-drp">
  <ul class="list-unstyled">
    <li class="pc-h-item pc-sidebar-collapse">
      <a  href="javascript:void(0)" class="pc-head-link ms-0" id="sidebar-hide">
        <i class="ti ti-menu-2"></i>
      </a>
    </li>
    <li class="pc-h-item pc-sidebar-popup">
      <a  href="javascript:void(0)" class="pc-head-link ms-0" id="mobile-collapse">
        <i class="ti ti-menu-2"></i>
      </a>
    </li>

  </ul>
</div>
<div class="ms-auto">
  <ul class="list-unstyled">
    <li class="dropdown pc-h-item header-user-profile">
      <a
        class="pc-head-link dropdown-toggle arrow-none me-0"
        data-bs-toggle="dropdown"
        href="#"
        role="button"
        aria-haspopup="false"
        data-bs-auto-close="outside"
        aria-expanded="false"
      >
        <img src="../assets/images/user/avatar-2.jpg" alt="user-image" class="user-avtar">
        <span style="white-space: normal; word-break: break-word;"><?= htmlspecialchars($admin_name) ?></span>
      </a>
      <div class="dropdown-menu dropdown-user-profile dropdown-menu-end pc-h-dropdown">
        <div class="dropdown-header">
          <div class="d-flex mb-1 align-items-center">
            <div class="flex-shrink-0">
              <img src="../assets/images/user/avatar-2.jpg" alt="user-image" class="user-avtar wid-35">
            </div>
            <div class="flex-grow-1 ms-3" style="min-width: 0;">
              <h6 class="mb-1 mb-0" style="white-space: normal; word-break: break-word;"><?= htmlspecialchars($admin_name) ?></h6>
            </div>
            <a href="index.php" class="pc-head-link bg-transparent logout-btn">
              <i class="ti ti-power text-danger"></i>
            </a>
          </div>
        </div>
      </div>
    </li>
  </ul>
</div>
</header>
  <div class="pc-container">
    <div class="pc-content">
      <div class="page-header">
  <div class="page-block">
    <div class="row align-items-center">
      <div class="col-md-12">
        <div class="page-header-title">
          <h5 class="m-b-10">Ranking</h5>
        </div>
        <ul class="breadcrumb">
          <li class="breadcrumb-item"><a href="dashboard.php" style="text-decoration: none;">Home</a></li>
          <li class="breadcrumb-item"><a href="javascript: void(0)" style="text-decoration: none;">Reports</a></li>
          <li class="breadcrumb-item" aria-current="page">Ranking</li>
        </ul>
      </div>
    </div>
  </div>
</div>
      <div class="row">
        <div class="col-sm-12">
          <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center" style="background-color: #053a1c; color: #fff; height: 60px;">
          <h5 class="mb-0">Examinee Ranking</h5>
</div>
<div class="card-body">

      <div class="d-flex justify-content-between align-items-center mb-3">
  <!-- Left side: Top number and School Year selection -->
  <form method="POST" action="ranking" class="d-flex gap-2">
    <input type="hidden" name="school_year_id" value="<?= htmlspecialchars($selectedSchoolYearId); ?>">
    <select name="top_number" id="top_number" class="form-select" onchange="this.form.submit()">
      <option value="10" <?= $topNumber == 10 ? 'selected' : ''; ?>>Top 10</option>
      <option value="20" <?= $topNumber == 20 ? 'selected' : ''; ?>>Top 20</option>
      <option value="50" <?= $topNumber == 50 ? 'selected' : ''; ?>>Top 50</option>
      <option value="100" <?= $topNumber == 100 ? 'selected' : ''; ?>>Top 100</option>
    </select>

    <select name="school_year_id" id="school_year_id" class="form-select" onchange="this.form.submit()">
      <option value="<?= htmlspecialchars($activeSchoolYearId); ?>" <?= $selectedSchoolYearId == $activeSchoolYearId ? 'selected' : ''; ?>>
        Current School Year
      </option>
      <?php foreach ($schoolYears as $schoolYear): ?>
        <option value="<?= htmlspecialchars($schoolYear['school_year_id']); ?>" <?= $schoolYear['school_year_id'] == $selectedSchoolYearId ? 'selected' : ''; ?>>
          <?= htmlspecialchars($schoolYear['school_year']); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </form>

  <!-- Right side: Print button -->
  <form method="POST" action="print_ranking" target="_blank">
  <input type="hidden" name="top_number" value="<?= htmlspecialchars($topNumber) ?>">
<input type="hidden" name="enrollment_status" value="<?= htmlspecialchars($enrollmentStatus) ?>">
<input type="hidden" name="school_year_id" value="<?= htmlspecialchars($selectedSchoolYearId) ?>">
    <button type="submit" name="generate_report" class="btn btn-primary">
      <i class="fas fa-print"></i> Print Ranking
    </button>
  </form>
</div>

<div class="card-body">

          <table id="rankingTable" class="table table-bordered table-striped">
          <thead>
                <tr>
                  <th>Rank</th>
                  <th>Examinee Name</th>
                  <th>First Preference</th>
                  <th>Second Preference</th>
                  <th>Enrollment Status</th>
                  <th>Score</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $rank = 0;
                $prevScore = null;
                $actualRank = 0;
                mysqli_data_seek($rankResult, 0);

                while ($row = mysqli_fetch_assoc($rankResult)) {
                  if ($row['total_score'] !== $prevScore) {
                    $rank = $actualRank + 1;
                  }
                  $actualRank++;

                  echo "<tr>";
                  echo "<td>" . htmlspecialchars($rank) . "</td>";
                  echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
                  echo "<td>" . htmlspecialchars($row['first_preference']) . "</td>";
                  echo "<td>" . htmlspecialchars($row['second_preference']) . "</td>";
                  echo "<td>" . htmlspecialchars($row['enrollment_status']) . "</td>";
                  echo "<td>" . htmlspecialchars($row['total_score']) . "</td>";
                  echo "</tr>";

                  $prevScore = $row['total_score'];
                }
                ?>
                </tbody>

            </table>
          </div>
        </div>
        </div>
          </div>
        </div>
      </div>
    </div>
  </div>
 
<script>
     $('.logout-btn').click(function (e) {
            e.preventDefault();

            Swal.fire({
                title: 'Are you sure?',
                text: 'Do you really want to log out?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, log out!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = $(this).attr('href');
                }
            });
        });
</script>

<script src="script.js"></script>


</body>
</html>