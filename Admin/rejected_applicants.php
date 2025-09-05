<?php
session_start(); 
include('../server.php'); 

// Initialize session security
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

$sql = "SELECT name FROM tbl_admin WHERE id_no = " . $admin_id_no;
$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    $admin_name = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
}

  // Get the records including exam status
  $query = "SELECT tbl_examinee.*, 
      tbl_strand.strand_name, 
      tbl_batch.batch_number, 
      tbl_examinee.enrollment_status,  
      MAX(tbl_examinee_exam.status) AS status, 
      MAX(first_course.course_name) AS first_course_name, 
      MAX(second_course.course_name) AS second_course_name, 
      tbl_requirements.grade11_1stsem, 
      tbl_requirements.grade11_2ndsem, 
      tbl_requirements.grade12_1stsem, 
      tbl_requirements.grade12_2ndsem, 
      tbl_requirements.tor,
      tbl_examinee.examinee_status,
      tbl_rejection_reason.reasons
  FROM tbl_examinee
  INNER JOIN tbl_rejection_reason ON tbl_examinee.examinee_id = tbl_rejection_reason.examinee_id
  LEFT JOIN tbl_strand ON tbl_examinee.strand_id = tbl_strand.strand_id
  LEFT JOIN tbl_batch ON tbl_examinee.batch_id = tbl_batch.batch_id
  LEFT JOIN tbl_examinee_exam ON tbl_examinee.examinee_id = tbl_examinee_exam.examinee_id
  LEFT JOIN tbl_course AS first_course ON tbl_examinee.first_preference = first_course.course_id
  LEFT JOIN tbl_course AS second_course ON tbl_examinee.second_preference = second_course.course_id
  LEFT JOIN tbl_requirements ON tbl_examinee.examinee_id = tbl_requirements.examinee_id
  INNER JOIN tbl_schedule ON tbl_schedule.batch_id = tbl_examinee.batch_id
  INNER JOIN tbl_school_year ON tbl_school_year.school_year_id = tbl_batch.school_year_id
  WHERE tbl_school_year.school_year_status = 'active' AND tbl_examinee.estatus = 'rejected'
  GROUP BY tbl_examinee.examinee_id";

  $result = mysqli_query($conn, $query);
  if (!$result) {
      die("Query failed: " . mysqli_error($conn));
  }

  // Fetch records helper function
  function fetchRecords($conn, $query) {
      $result = mysqli_query($conn, $query);
      $records = [];
      if ($result && mysqli_num_rows($result) > 0) {
          while ($row = mysqli_fetch_assoc($result)) {
              $records[] = $row;
          }
      }
      return $records;
  }

  // Fetch courses, examinees, strand, and batch details
  $courses = fetchRecords($conn, "SELECT * FROM tbl_course");
  $examinees = fetchRecords($conn, "SELECT tbl_examinee.*, tbl_strand.strand_name FROM tbl_examinee INNER JOIN tbl_strand ON tbl_examinee.strand_id = tbl_strand.strand_id");
  $strand = fetchRecords($conn, "SELECT * FROM tbl_strand");
  $batch = fetchRecords($conn, "SELECT * FROM tbl_batch");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<title>Rejected Applicants | College Admission Test</title>
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
          <h5 class="m-b-10">Rejected Applicants</h5>
        </div>
        <ul class="breadcrumb">
          <li class="breadcrumb-item"><a href="dashboard.php" style="text-decoration: none;">Home</a></li>
          <li class="breadcrumb-item"><a href="javascript: void(0)" style="text-decoration: none;">Examinee</a></li>
          <li class="breadcrumb-item" aria-current="page">Rejected Applicants</li>
        </ul>
      </div>
    </div>
  </div>
</div>

      <!-- [ Main Content ] -->
      <div class="row">
        <div class="col-sm-12">
          <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center" style="background-color: #053a1c; color: #fff; height: 60px">
          <h5 class="mb-0">Rejected Applicants</h5>
</div>
      
<div class="card-body">
      
<table id="examineeTable" class="table table-bordered table-striped">                        
    <thead>
        <tr>
            <th>Unique Code</th>
            <th>Last Name</th>
            <th>First Name</th>
            <th>Middle Name</th>
            <th>Strand</th>
            <th>First Course Preference</th>
            <th>Second Course Preference</th>
            <th>Enrollment Status</th>
            <th>Last School Attended</th>
            <th>Learners Reference Number</th>
            <th>School Address</th>
            <th>Home Address</th>
            <th>Sex</th>
            <th>Zip Code</th>
            <th>Birthday</th>
            <th>Email</th>
            <th>Batch Number</th>
            <th>Contact Number</th>
            <th>Examinee Status</th>
            <th>Grade 11 1st Sem</th>
            <th>Grade 11 2nd Sem</th>
            <th>Grade 12 1st Sem</th>
            <th>Grade 12 2nd Sem</th>
            <th>TOR/Certificate of Grades</th>
            <th>Status</th>
            <th>Reason</th>
        </tr>
    </thead>
    <tbody>
    <?php
    if (mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row["unique_code"], ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row["lname"], ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row["fname"], ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row["mname"], ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row["strand_name"], ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row["first_course_name"], ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row["second_course_name"], ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row["enrollment_status"], ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row["lschool_attended"], ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row["lrn"], ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row["school_address"], ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row["home_address"], ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row["sex"], ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row["zipcode"], ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row["birthday"], ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row["email"], ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row["batch_number"], ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row["contact_number"], ENT_QUOTES, 'UTF-8') . "</td>";
            echo "<td>" . htmlspecialchars($row["examinee_status"], ENT_QUOTES, 'UTF-8') . "</td>";

            // Check examinee status
            $status = htmlspecialchars($row["examinee_status"], ENT_QUOTES, 'UTF-8');
            $grade11_1stsem_disabled = $grade11_2ndsem_disabled = $grade12_1stsem_disabled = $grade12_2ndsem_disabled = $tor_disabled = '';

            // Based on status, disable/enable buttons
            if ($status == 'SHS GRADUATING STUDENT') {
                $grade12_2ndsem_disabled = $tor_disabled = 'disabled';
            } elseif ($status == 'SHS GRADUATE') {
                $grade11_1stsem_disabled = $grade11_2ndsem_disabled = $tor_disabled = 'disabled';
            } elseif ($status == 'TRANSFEREE') {
                $grade11_1stsem_disabled = $grade11_2ndsem_disabled = $grade12_1stsem_disabled = $grade12_2ndsem_disabled = 'disabled';
            }

            // View buttons for each requirement
            echo "<td><button class='btn btn-primary' onclick='viewFile(" . (int)$row['examinee_id'] . ", \"grade11_1stsem\")' " . $grade11_1stsem_disabled . ">View</button></td>";
            echo "<td><button class='btn btn-primary' onclick='viewFile(" . (int)$row['examinee_id'] . ", \"grade11_2ndsem\")' " . $grade11_2ndsem_disabled . ">View</button></td>";
            echo "<td><button class='btn btn-primary' onclick='viewFile(" . (int)$row['examinee_id'] . ", \"grade12_1stsem\")' " . $grade12_1stsem_disabled . ">View</button></td>";
            echo "<td><button class='btn btn-primary' onclick='viewFile(" . (int)$row['examinee_id'] . ", \"grade12_2ndsem\")' " . $grade12_2ndsem_disabled . ">View</button></td>";
            echo "<td><button class='btn btn-primary' onclick='viewFile(" . (int)$row['examinee_id'] . ", \"tor\")' " . $tor_disabled . ">View TOR</button></td>";
            echo "<td>" . htmlspecialchars($row["estatus"], ENT_QUOTES, 'UTF-8') . "</td>";

            // Reason
            $reasons = explode(',', $row["reasons"]);
            echo "<td><ul>";
            foreach ($reasons as $reason) {
                echo "<li>" . htmlspecialchars(trim($reason), ENT_QUOTES, 'UTF-8') . "</li>";
            }
            echo "</ul></td>";
            echo "</tr>";
        }
    }
    ?>
    </tbody>
</table>
</div>

<!-- Modal for File Preview -->
<div class="modal fade" id="fileModal" tabindex="-1" aria-labelledby="fileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="fileModalLabel">File Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
            <div class="modal-body">
                <iframe id="filePreview" style="width: 100%; height: 80vh;" frameborder="0"></iframe>
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
    function formToggle(ID) {
        var element = document.getElementById(ID);
        if (element.style.display === "none") {
            element.style.display = "block";
        } else {
            element.style.display = "none";
        }
    }

    $(document).ready(function () {
    // Initialize DataTable
    var table = $('.table').DataTable({
        scrollY: '50vh',  
        scrollX: true,        
        scrollCollapse: true,  
        paging: true,  
        fixedHeader: true,
        pageLength: 100,
        lengthChange: false, 
        info: true, 
        ordering: true, 
        searching: true,
        autoWidth: false, 
    });

    // Create the batch filter dropdown with a label
var batchFilterHtml = `
    <select id="batchFilter" class="form-select form-select-sm" style="width: auto; display: inline-block; margin-right: 20px;">
          <option value="" disabled selected>Filter Batch Number</option>
    <option value="">All Batches</option>
        <?php
        $batches = mysqli_query($conn, "
        SELECT DISTINCT b.batch_id, b.batch_number
        FROM tbl_batch b
        INNER JOIN tbl_school_year sy ON b.school_year_id = sy.school_year_id
        WHERE sy.school_year_status = 'active'
    ");
    
    
    while ($batch = mysqli_fetch_assoc($batches)) {
        echo "<option value='" . htmlspecialchars($batch['batch_number'], ENT_QUOTES, 'UTF-8') . "'>Batch " . htmlspecialchars($batch['batch_number'], ENT_QUOTES, 'UTF-8') . "</option>";
    }
        ?>
    </select>`;

    // Append the batch filter dropdown to the DataTables search container
    $('#examineeTable_filter').prepend(batchFilterHtml);

    // Filter the table based on the selected batch
    $('#batchFilter').on('change', function () {
        var batchValue = $(this).val();
        table.column(16).search(batchValue).draw(); // Adjust the column index if necessary
    });
});


    <?php if (isset($_SESSION['message'])): ?>
        Swal.fire({
            title: "<?php echo ($_SESSION['msg_type'] == 'success') ? 'Success!' : 'Oops!' ?>",
            text: "<?php echo htmlspecialchars($_SESSION['message'], ENT_QUOTES, 'UTF-8'); ?>",
            icon: "<?php echo htmlspecialchars($_SESSION['msg_type'], ENT_QUOTES, 'UTF-8'); ?>",
            confirmButtonText: "OK"
        });
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    function viewFile(examineeId, fileType) {
        const fileUrl = `view_file?examinee_id=${examineeId}&file_type=${encodeURIComponent(fileType)}`;
        document.getElementById('filePreview').src = fileUrl;
        document.getElementById('fileModalLabel').innerText = `Viewing ${fileType.replace('_', ' ')}`;
        $('#fileModal').modal('show');
    }

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
                window.location.href = this.href;
            }
        });
    });
</script>

<script src="script.js"></script>

</body>
</html>