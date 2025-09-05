<?php
session_start();
include('../server.php');

// Initialize session security
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id();
    $_SESSION['initiated'] = true;
}

// Check authentication
if (!isset($_SESSION['id_no'])) {
    header("Location: index");
    exit();
}

// Get admin info
$admin_id_no = $_SESSION['id_no'];
$admin_name = '';

$sql = "SELECT name FROM tbl_admin WHERE id_no = '".mysqli_real_escape_string($conn, $admin_id_no)."'";
$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    $admin_name = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
}

date_default_timezone_set('Asia/Manila');

// Sanitize filtering options
$selectedSchoolYear = isset($_POST['school_year']) ? mysqli_real_escape_string($conn, $_POST['school_year']) : null;
$enrollment_status = isset($_POST['enrollment_status']) ? mysqli_real_escape_string($conn, $_POST['enrollment_status']) : '';
$batch_number = isset($_POST['batch_number']) ? mysqli_real_escape_string($conn, $_POST['batch_number']) : '';

// Get current active school year
if (!$selectedSchoolYear) {
    $sql = "SELECT school_year FROM tbl_school_year WHERE school_year_status = 'active' LIMIT 1";
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $selectedSchoolYear = htmlspecialchars($row['school_year'], ENT_QUOTES, 'UTF-8');
    }
}

$subjectColumns = [];

// Fetch subject columns
$sql = "SELECT DISTINCT subject_name FROM tbl_subject ORDER BY subject_name";
$result = mysqli_query($conn, $sql);
while ($subjectRow = mysqli_fetch_assoc($result)) {
    $subjectColumns[] = htmlspecialchars($subjectRow['subject_name'], ENT_QUOTES, 'UTF-8');
}

// Fetch batch numbers
$sql = "SELECT DISTINCT batch_number FROM tbl_batch ORDER BY batch_number DESC";
$batchResult = mysqli_query($conn, $sql);

$query = "SELECT 
        e.examinee_id, 
        CONCAT(e.lname, ', ', e.fname, ' ', e.mname) AS full_name, 
        e.sex,
        e.enrollment_status,
        c1.course_name AS first_preference,
        c2.course_name AS second_preference,
        e.lrn,
        e.school_address, 
        e.home_address, 
        e.contact_number,
        IFNULL(SUM(ts.score), 0) AS total_score, 
        GROUP_CONCAT(CONCAT(sub.subject_name, ': ', IFNULL(ts.score, 0)) ORDER BY sub.subject_name SEPARATOR ', ') AS subject_scores,
        sc.remarks,
        ee.datetime_completed,
        ee.minutes_taking_exam,
        sy.school_year,
        sch.exam_date,
        sch.exam_start_time
    FROM 
        tbl_examinee e
    INNER JOIN 
        tbl_examinee_exam ee ON e.examinee_id = ee.examinee_id
    JOIN 
        exam_schedules ex ON ee.exam_schedule_id = ex.exam_schedule_id
    INNER JOIN 
        tbl_score sc ON e.examinee_id = sc.examinee_id
    INNER JOIN 
        tbl_batch b ON e.batch_id = b.batch_id
    LEFT JOIN 
        tbl_course c1 ON e.first_preference = c1.course_id
    LEFT JOIN 
        tbl_course c2 ON e.second_preference = c2.course_id
    CROSS JOIN 
        tbl_subject sub  
    LEFT JOIN 
        tbl_subject_score ts 
        ON sc.exam_schedule_id = ts.exam_schedule_id 
        AND sc.examinee_id = ts.examinee_id 
        AND ts.subject_id = sub.subject_id 
    LEFT JOIN 
        tbl_schedule sch ON ex.schedule_id = sch.schedule_id
    LEFT JOIN 
        tbl_school_year sy ON b.school_year_id = sy.school_year_id
    WHERE 
        ee.datetime_completed IS NOT NULL";

// Prepare conditions
$conditions = [];
$params = [];

if (!empty($selectedSchoolYear)) {
    $conditions[] = "sy.school_year = '".mysqli_real_escape_string($conn, $selectedSchoolYear)."'";
}

if (!empty($enrollment_status)) {
    $conditions[] = "e.enrollment_status = '".mysqli_real_escape_string($conn, $enrollment_status)."'";
}

if (!empty($batch_number)) {
    $conditions[] = "b.batch_number = '".mysqli_real_escape_string($conn, $batch_number)."'";
}

if (!empty($conditions)) {
    $query .= " AND " . implode(" AND ", $conditions);
}

$query .= " GROUP BY e.examinee_id";

$scoreResult = mysqli_query($conn, $query);

if (!$scoreResult) {
    die("Error fetching scores: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
<title>Reports | College Admission Test</title>
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

 <!-- [ Sidebar Menu ] -->
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
          <h5 class="m-b-10">Reports</h5>
        </div>
        <ul class="breadcrumb">
          <li class="breadcrumb-item"><a href="dashboard.php" style="text-decoration: none;">Home</a></li>
          <li class="breadcrumb-item"><a href="javascript: void(0)" style="text-decoration: none;">Reports</a></li>
          <li class="breadcrumb-item" aria-current="page">Reports</li>
        </ul>
      </div>
    </div>
  </div>
</div>

      <!-- [ Main Content ]  -->
      <div class="row">
        <div class="col-sm-12">
          <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center" style="background-color: #053a1c; color: #fff; height: 60px;">
          <h5 class="mb-0">Reports</h5>
  <div class="d-flex gap-2">
    <!-- Export Modal Trigger -->
    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#exportModal">
      <i class="fa-solid fa-file-export"></i> Export Options
    </button>

    <!-- Generate PDF Report Button -->
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#releaseResultModal">
      <i class="fas fa-file-pdf"></i> Release Examinee Result
    </button>
  </div>
</div>
<div class="card-body">
  <form method="post" action="reports" class="d-flex gap-2 flex-wrap">
   <select name="school_year" class="form-select w-auto" onchange="this.form.submit()">
      <option value="">All School Years</option>
      <?php
      $schoolYearsQuery = "SELECT school_year FROM tbl_school_year ORDER BY school_year DESC";
      $schoolYearsResult = mysqli_query($conn, $schoolYearsQuery);
      while ($row = mysqli_fetch_assoc($schoolYearsResult)) {
        $selected = ($row['school_year'] == $selectedSchoolYear) ? 'selected' : '';
        echo "<option value='".htmlspecialchars($row['school_year'], ENT_QUOTES, 'UTF-8')."' $selected>".htmlspecialchars($row['school_year'], ENT_QUOTES, 'UTF-8')."</option>";
      }
      ?>
    </select>

   <select name="enrollment_status" class="form-select w-auto" onchange="this.form.submit()">
      <option value="">All Enrollment Status</option>
      <option value="freshmen" <?= $enrollment_status == 'freshmen' ? 'selected' : ''; ?>>Freshmen</option>
      <option value="transferee" <?= $enrollment_status == 'transferee' ? 'selected' : ''; ?>>Transferee</option>
    </select>

    <select name="batch_number" class="form-select w-auto" onchange="this.form.submit()">
      <option value="">All Batch Numbers</option>
      <?php while ($batchRow = mysqli_fetch_assoc($batchResult)): ?>
        <option value="<?= htmlspecialchars($batchRow['batch_number'], ENT_QUOTES, 'UTF-8'); ?>" <?= $batchRow['batch_number'] == $batch_number ? 'selected' : ''; ?>>
          <?= htmlspecialchars($batchRow['batch_number'], ENT_QUOTES, 'UTF-8'); ?>
        </option>
      <?php endwhile; ?>
    </select>
  </form>

      <?php if ($scoreResult && mysqli_num_rows($scoreResult) > 0): ?>
        <div class="show-print-button"></div>
        
        <div class="card-body">
        <table id="reportTable" class="table table-bordered table-striped">
        <thead>
              <tr>
                <th>Examinee Name</th>
                <th>Sex</th>
                <th>First Course Preference</th>
                <th>Second Course Preference</th>
                <th>DateTime Completed</th>
                <th>Duration</th>
                <?php foreach ($subjectColumns as $subject): ?>
                  <th><?php echo htmlspecialchars($subject); ?></th>
                <?php endforeach; ?>
                <th>Total</th>
                <th>Remarks</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($row = mysqli_fetch_assoc($scoreResult)): ?>
                <tr class="exam-row" data-examinee-id="<?php echo htmlspecialchars($row['examinee_id'], ENT_QUOTES, 'UTF-8'); ?>" onclick="viewReport(<?php echo htmlspecialchars($row['examinee_id'], ENT_QUOTES, 'UTF-8'); ?>)">
                  <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                  <td><?php echo htmlspecialchars($row['sex']); ?></td>
                  <td><?php echo htmlspecialchars($row['first_preference']); ?></td>
                  <td><?php echo htmlspecialchars($row['second_preference']); ?></td>
                  <td><?php echo $row['datetime_completed'] ? date('F j, Y, g:i a', strtotime($row['datetime_completed'])) : 'Not Completed'; ?></td>
                  <td>
                    <?php
                    // Get the total minutes from the database
                    $totalMinutes = $row['minutes_taking_exam'];

                    // Calculate hours and remaining minutes
                    $hours = floor($totalMinutes / 60);
                    $minutes = $totalMinutes % 60;

                    // Format the duration
                    if ($hours > 0 && $minutes > 0) {
                      echo "$hours hour and $minutes minutes";
                    } elseif ($hours > 0) {
                      echo "$hours hour";
                    } elseif ($minutes > 0) {
                      echo "$minutes minutes";
                    } else {
                      echo "0 minute";
                    }
                    ?>
                  </td>
                  <?php
                  $subjectScores = explode(', ', $row['subject_scores']);
                  foreach ($subjectColumns as $subject) {
                    $scoreFound = false;
                    foreach ($subjectScores as $subjectScore) {
                      $parts = explode(': ', $subjectScore);
                      if ($parts[0] === $subject) {
                        echo "<td>" . htmlspecialchars($parts[1]) . "</td>";
                        $scoreFound = true;
                        break;
                      }
                    }
                    if (!$scoreFound) {
                      echo "<td>-</td>";
                    }
                  }
                  ?>
                  <td><?php echo htmlspecialchars($row['total_score']); ?></td>
                  <td><?php echo htmlspecialchars($row['remarks']); ?></td>
                  <td>
                    <!-- View Report Button -->
                    <form method="post" action="view_report" target="_blank">
                      <input type="hidden" name="examinee_id" value="<?php echo htmlspecialchars($row['examinee_id'], ENT_QUOTES, 'UTF-8'); ?>">
                      <button type="submit" class="btn btn-primary">View Report</button>
                    </form>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p>No records found.</p>
      <?php endif; ?>
    </div>

<!-- Export Options Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <p class="modal-title" id="exportModalLabel">Export Options</p>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">

        <div class="d-grid gap-2">
          <!-- Export Examinee Score button -->
          <a href="examinee_score_pdf.php" target="_blank" class="btn btn-success">
            <i class="fas fa-file-pdf me-1"></i> Generate Examinee PDF Report
          </a>
           
          <!-- Export Examinees in Excel button -->
          <a href="#" class="btn btn-info" onclick="submitExport();">
            <i class="fa-solid fa-file-excel"></i> Export Examinee Excel Report
          </a>

          <!-- Export Testing Fee button -->
          <a href="export_testing_fee.php" target="_blank" class="btn btn-warning">
            <i class="fa-solid fa-file-excel"></i> Export Testing Fee Excel Report
          </a>

           <!-- Export Qualified Examinees For Interview button-->
           <a href="qualified_examinees_interview.php" class="btn btn-primary">
    <i class="fa-solid fa-file-zipper"></i> Export All Qualified Examinees (ZIP)
</a>        </div>
      </div>
    </div>
  </div>
</div>

<!-- Release Result Modal -->
<div class="modal fade" id="releaseResultModal" tabindex="-1" aria-labelledby="releaseResultLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" action="release_result">
      <div class="modal-content">
        <div class="modal-header">
          <p class="modal-title">Select Batch Number to Release Result</p>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label for="batchSelect" class="form-label">Select Batch Number</label>
            <select class="form-select" id="batchSelect" name="batch_id" required>
              <option value="" disabled selected>-- Choose Batch Number --</option>
              <?php
              // Get the active school year
              $sql = "SELECT school_year_id FROM tbl_school_year WHERE school_year_status = 'active'";
              $result = mysqli_query($conn, $sql);
              $activeSY = mysqli_fetch_assoc($result)['school_year_id'] ?? null;
              
              if ($activeSY) {
                // Prepare query to get batches where all exam schedules are finished and not already released
                $sql = "SELECT b.batch_id, b.batch_number
                  FROM tbl_batch b
                  WHERE b.school_year_id = '".mysqli_real_escape_string($conn, $activeSY)."'
                  AND NOT EXISTS (
                      SELECT 1 FROM tbl_release_result r
                      WHERE r.batch_id = b.batch_id AND r.release_status = 'released'
                  )
                  AND NOT EXISTS (
                      -- Check if there are any exam schedules for this batch that aren't finished
                      SELECT 1 FROM tbl_schedule s
                      JOIN exam_schedules es ON s.schedule_id = es.schedule_id
                      WHERE s.batch_id = b.batch_id 
                      AND es.exam_status != 'finished'
                  )
                  AND EXISTS (
                      -- Make sure the batch has at least one exam schedule
                      SELECT 1 FROM tbl_schedule s
                      JOIN exam_schedules es ON s.schedule_id = es.schedule_id
                      WHERE s.batch_id = b.batch_id
                  )
                  ORDER BY b.batch_number DESC
                ";
                
                $batchResult = mysqli_query($conn, $sql);

                if ($batchResult && mysqli_num_rows($batchResult) > 0) {
                  while ($row = mysqli_fetch_assoc($batchResult)) {
                    $batch_id = htmlspecialchars($row['batch_id'], ENT_QUOTES, 'UTF-8');
                    $batch_number = htmlspecialchars($row['batch_number'], ENT_QUOTES, 'UTF-8');
                    echo '<option value="' . $batch_id . '">Batch ' . $batch_number . '</option>';
                  }
                } else {
                  echo '<option disabled>No batches with all exams finished and ready for release</option>';
                }
              } else {
                echo '<option disabled>No active school year found</option>';
              }
              ?>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Release Result</button>
        </div>
      </div>
    </form>
  </div>
</div>

</div>
            </div>
          </div>
          </div>
        </div>
      </div>
    </div>
  </div>
 
 <script>

function submitExport() {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'export_reports';

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}
    $(document).ready(function () {
        $('.clickable-row').on('click', function () {
            var pdfUrl = $(this).data('pdf'); 
            window.open(pdfUrl, '_blank'); 
        });

      
        $('#reportTable').DataTable({
            "paging": true,
            "searching": true,
            "ordering": true,
            "info": true,
            "autoWidth": false,
            "scrollX": true, 
            "lengthMenu": [10, 25, 50, 100]
        });

    });

    <?php if (isset($_SESSION['message'])): ?>
                Swal.fire({
                    title: "<?php echo ($_SESSION['msg_type'] == 'success') ? 'Success!' : 'Oops!' ?>",
                    text: "<?php echo $_SESSION['message']; ?>",
                    icon: "<?php echo $_SESSION['msg_type']; ?>",
                    confirmButtonText: "OK"
                });
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>
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