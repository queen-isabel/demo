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

// Get form inputs
$schoolYearId = $_POST['school_year'] ?? 0;
$enrollmentStatus = $_POST['enrollment_status'] ?? null;

$scheduleCount = 0;

if ($schoolYearId && $enrollmentStatus) {
    $schoolYearId = mysqli_real_escape_string($conn, $schoolYearId);
    $enrollmentStatus = mysqli_real_escape_string($conn, $enrollmentStatus);
    
    $query = "SELECT COUNT(*) AS schedule_count FROM tbl_schedule 
              JOIN tbl_examinee ON tbl_schedule.batch_id = tbl_examinee.batch_id
              WHERE school_year_id = '$schoolYearId' AND tbl_examinee.enrollment_status = '$enrollmentStatus'";
    $result = mysqli_query($conn, $query);

    if ($result && $row = mysqli_fetch_assoc($result)) {
        $scheduleCount = $row['schedule_count'];
    }
}

// Fetch active school year
$query = "SELECT school_year_id, school_year FROM tbl_school_year WHERE school_year_status = 'active' LIMIT 1";
$result = mysqli_query($conn, $query);
$activeSchoolYear = $result ? mysqli_fetch_assoc($result) : null;

$activeSchoolYearId = $activeSchoolYear ? $activeSchoolYear['school_year_id'] : null;
$activeSchoolYearName = $activeSchoolYear ? $activeSchoolYear['school_year'] : 'No active school year';

function getExamStatus($conn, $scheduleId) {
    $scheduleId = mysqli_real_escape_string($conn, $scheduleId);
    $query = "SELECT exam_status FROM exam_schedules WHERE schedule_id = '$scheduleId'";
    $result = mysqli_query($conn, $query);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        return $row['exam_status'];
    }
    return null;
}

function isHolidayOrWeekend($date) {
    $holidays = [
        '01-01', '06-12', '11-30', '12-25', '12-30'
    ];

    if (in_array(date('l', strtotime($date)), ['Friday', 'Saturday', 'Sunday'])) {
        return true;
    }

    if (in_array(date('m-d', strtotime($date)), $holidays)) {
        return true;
    }

    return false;
}

function fetchAllBatches($conn) {
    $query = "SELECT 
                s.schedule_id, 
                b.batch_number, 
                s.exam_date, 
                s.exam_start_time, 
                s.exam_end_time 
              FROM tbl_schedule s
              JOIN tbl_batch b ON s.batch_id = b.batch_id
              JOIN tbl_school_year sy ON b.school_year_id = sy.school_year_id
              WHERE sy.school_year_status = 'active'";
    return mysqli_query($conn, $query);
}

function isExamStarted($conn, $scheduleId) {
    $scheduleId = mysqli_real_escape_string($conn, $scheduleId);
    $query = "SELECT 1 FROM exam_schedules WHERE schedule_id = '$scheduleId' AND exam_status = 'started' LIMIT 1";
    $result = mysqli_query($conn, $query);
    return mysqli_num_rows($result) > 0;
}

function formatTime($time) {
    $dateTime = DateTime::createFromFormat('H:i:s', $time);
    return $dateTime ? $dateTime->format('h:i A') : $time;
}

if (isset($_POST['create_schedule'])) {
    $requiredFields = ['batch_number', 'exam_date', 'start_exam_time', 'end_exam_time'];
    $missingFields = array_diff($requiredFields, array_keys($_POST));
    
    if (!empty($missingFields)) {
        $_SESSION['message'] = "All fields are required!";
        $_SESSION['msg_type'] = "error";
    } else {
        $batchNumber = $_POST['batch_number'];
        $examDate = $_POST['exam_date'];
        $startTime = $_POST['start_exam_time'];
        $endTime = $_POST['end_exam_time'];

        $message = createExamSchedule($conn, $batchNumber, $schoolYearId, $examDate, $startTime, $endTime);
        
        // Set the message and redirect immediately
        $_SESSION['message'] = $message;
        $_SESSION['msg_type'] = (strpos($message, "successfully") !== false) ? "success" : "error";
        header("Location: ".$_SERVER['PHP_SELF']); 
        exit(); 
    }
}

function createExamSchedule($conn, $batchNumber, $schoolYearId, $examDate, $startTime, $endTime) {
    $batchNumber = mysqli_real_escape_string($conn, $batchNumber);
    $examDate = mysqli_real_escape_string($conn, $examDate);
    $startTime = mysqli_real_escape_string($conn, $startTime);
    $endTime = mysqli_real_escape_string($conn, $endTime);
    $schoolYearId = mysqli_real_escape_string($conn, $schoolYearId);

    // Check if batch exists
    $query = "SELECT b.batch_id FROM tbl_batch b
              JOIN tbl_school_year sy ON b.school_year_id = sy.school_year_id
              WHERE b.batch_number = '$batchNumber' AND sy.school_year_status = 'active'";
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) == 0) {
        return "Batch not found or not in an active school year!";
    }

    $batchRow = mysqli_fetch_assoc($result);
    $batchId = $batchRow['batch_id'];

    // Check if schedule exists for this batch
    $checkQuery = "SELECT 1 FROM tbl_schedule WHERE batch_id = '$batchId'";
    $checkResult = mysqli_query($conn, $checkQuery);
    if (mysqli_num_rows($checkResult) > 0) {
        return "Schedule already exists for this batch and school year!";
    }

    // Check for duplicate time slot
    $checkDuplicateQuery = "SELECT 1 FROM tbl_schedule WHERE exam_date = '$examDate' AND exam_start_time = '$startTime' AND exam_end_time = '$endTime'";
    $checkDuplicateResult = mysqli_query($conn, $checkDuplicateQuery);

    if (mysqli_num_rows($checkDuplicateResult) > 0) {
        return "A schedule already exists for this time slot!";
    }

    // If all checks passed, insert the new schedule
    $insertQuery = "INSERT INTO tbl_schedule (batch_id, exam_date, exam_start_time, exam_end_time) 
                    VALUES ('$batchId', '$examDate', '$startTime', '$endTime')";

    if (mysqli_query($conn, $insertQuery)) {
        return "Schedule created successfully!";
    } else {
        return "Error inserting schedule: " . mysqli_error($conn);
    }
}

$batches = fetchAllBatches($conn);
?>

<!DOCTYPE html>
<html lang="en">

<head>
<title>Schedule Management | College Admission Test</title>
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
      <a href="javascript:void(0)" class="pc-head-link ms-0" id="sidebar-hide">
        <i class="ti ti-menu-2"></i>
      </a>
    </li>
    <li class="pc-h-item pc-sidebar-popup">
      <a href="javascript:void(0)" class="pc-head-link ms-0" id="mobile-collapse">
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
          <h5 class="m-b-10">Schedule</h5>
        </div>
        <ul class="breadcrumb">
          <li class="breadcrumb-item"><a href="dashboard.php" style="text-decoration: none;">Home</a></li>
          <li class="breadcrumb-item"><a href="javascript: void(0)" style="text-decoration: none;">Exam Management</a></li>
          <li class="breadcrumb-item" aria-current="page">Schedule</li>
        </ul>
      </div>
    </div>
  </div>
</div>

      <!-- [ Main Content ] -->
      <div class="row">
        <div class="col-sm-12">
          <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center" style="background-color: #053a1c; color: #fff; ">
          <h5 class="mb-0">Manage Schedule</h5>
  <button class="btn btn-success " data-bs-toggle="modal" data-bs-target="#addScheduleModal">
    <i class="fas fa-plus-circle me-1"></i> Add New Schedule
  </button>
</div>

            <div class="card-body">
            <table id="scheduleTable" class="table table-bordered table-striped">
        <thead>
          <tr>
            <th class='text-center'>Batch Number</th>
            <th class='text-center'>Exam Date</th>
            <th class='text-center'>Start Time</th>
            <th class='text-center'>End Time</th>
            <th class='text-center'>Action</th>
          </tr>
        </thead>
        <tbody>
                <?php
if ($batches->num_rows > 0) {
    while ($row = $batches->fetch_assoc()) {
        // Check if any exam with this schedule_id has started
        $isStarted = isExamStarted($conn, $row["schedule_id"]);
        $isDisabled = $isStarted ? "disabled" : "";
        
        // Format the start and end times
        $formattedStartTime = formatTime($row['exam_start_time']);
        $formattedEndTime = formatTime($row['exam_end_time']);

        echo "<tr>";
        echo "<td class='text-center'>" . $row["batch_number"] . "</td>";
        echo "<td class='text-center'>" . $row["exam_date"] . "</td>";
        echo "<td class='text-center'>" . $formattedStartTime . "</td>";
        echo "<td class='text-center'>" . $formattedEndTime . "</td>";
        echo "<td class='text-center'>
        <button class='btn btn-info move' style='color: white;' data-bs-toggle='modal' data-bs-target='#moveExamineeModal' 
            data-id='" . $row["schedule_id"] . "' $isDisabled>
            <i class='fas fa-calendar-alt'></i> Re Schedule
        </button>
      </td>";

        echo "</tr>";    
    }
}
?>

</tbody>
            </table>
            </div>
    </main>

 <!-- Add New Schedule Modal -->
<div id="addScheduleModal" class="modal fade">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h4 class="modal-title">Add New Schedule</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                <div class="modal-body">
                
                    <!-- Batch Number Dropdown -->
                    <div class="mb-3">
                        <label for="batch_number">Batch Number</label>
                        <select class="form-control" id="batch_number" name="batch_number" required>
                            <option value="">Select Batch</option>
                            <?php
                            // Fetch available batches for the active school year
                            $batchQuery = "SELECT b.batch_number 
                            FROM tbl_batch b 
                            WHERE b.school_year_id = (SELECT school_year_id FROM tbl_school_year WHERE school_year_status = 'active' LIMIT 1)
                            AND NOT EXISTS (
                                SELECT 1 FROM tbl_schedule s 
                                WHERE s.batch_id = b.batch_id
                            )";
             
                            $batchResult = $conn->query($batchQuery);

                            while ($batchRow = $batchResult->fetch_assoc()) {
                                echo "<option value='{$batchRow['batch_number']}'>{$batchRow['batch_number']}</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <!-- Exam Date -->
                    <div class="mb-3">
                        <label for="exam_date">Exam Date:</label>
                        <input type="date" name="exam_date" id="exam_date" class="form-control" required>
                    </div>

                    <!-- Start Time -->
                    <div class="mb-3">
                        <label for="start_exam_time">Start Time:</label>
                        <input type="time" name="start_exam_time" id="start_exam_time" class="form-control" required>
                    </div>

                    <!-- End Time -->
                    <div class="mb-3">
                        <label for="end_exam_time">End Time:</label>
                        <input type="time" name="end_exam_time" id="end_exam_time" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_schedule" class="btn btn-primary">Create Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="moveExamineeModal" tabindex="-1" aria-labelledby="moveExamineeModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="moveExamineeModalLabel">Reschedule Exam</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="rescheduleForm">
          <input type="hidden" name="schedule_id" value="<?php echo $scheduleId; ?>">
          <div class="mb-3">
            <label for="new_date" class="form-label">New Exam Date</label>
            <input type="date" class="form-control" id="new_date" name="new_date" required>
          </div>
          <div class="mb-3">
            <label for="new_time" class="form-label">New Exam Time</label>
            <input type="time" class="form-control" id="new_time" name="new_time" required>
          </div>
          <input type="hidden" id="schedule_id" name="schedule_id">
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="submit" form="rescheduleForm" class="btn btn-primary">Update Schedule</button>
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
$(document).ready(function() {
    // Toggle examinee options
    $('.examinee-link').click(function() {
        $('.examinee-options').toggle();
        $('.arrow').toggleClass('active');
    });

    var table = $('.table').DataTable({
        scrollCollapse: true, 
        paging: true,  
        fixedHeader: true,  
        lengthChange: true,  
        info: true,  
        ordering: true,  
        lengthMenu: [5, 10, 25, 50]

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
});

$(document).ready(function () {
    // Initialize the modal
    const moveExamineeModal = new bootstrap.Modal(document.getElementById('moveExamineeModal'));
    
    // Set up click handlers for all non-disabled move buttons
    document.querySelectorAll('.move:not([disabled])').forEach(button => {
        button.addEventListener('click', function() {
            const scheduleId = this.getAttribute('data-id');
            document.getElementById('schedule_id').value = scheduleId;
            moveExamineeModal.show();
        });
    });

    // Handle form submission
    $('#rescheduleForm').on('submit', function(e) {
        e.preventDefault();
        
        $.ajax({
            type: 'POST',
            url: 'reschedule_exam',
            data: $(this).serialize(),
            success: function(response) {
                if (response.includes("successfully")) {
                    Swal.fire({
                        title: 'Success!',
                        text: response,
                        icon: 'success'
                    }).then(() => {
                        moveExamineeModal.hide();
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: response,
                        icon: 'error'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to reschedule exam',
                    icon: 'error'
                });
            }
        });
    });
});


$(document).ready(function () {
    $('#school_year, #enrollment_status').change(function () {
        const schoolYearId = $('#school_year').val();
        const enrollmentStatus = $('#enrollment_status').val();

        if (schoolYearId && enrollmentStatus) {
            $.ajax({
                type: 'POST',
                url: 'check_schedule_exists',
                data: { school_year: schoolYearId, enrollment_status: enrollmentStatus },
                dataType: 'json',
                success: function (response) {
                    // Show or hide Start Date based on existence of a schedule
                    if (response.scheduleExists) {
                        $('#start-date-group').hide();
                        $('#exam_date').prop('required', false);
                    } else {
                        $('#start-date-group').show();
                        $('#exam_date').prop('required', true);
                    }
                },
                error: function (error) {
                    console.error('Error fetching schedule status:', error);
                }
            });
        }
    });

    $('.logout-btn').click(function (e) {
            e.preventDefault(); // Prevent the default link behavior

            Swal.fire({
                title: 'Are you sure?',
                text: 'Do you really want to log out?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, log out!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Redirect to the logout page if confirmed
                    window.location.href = $(this).attr('href');
                }
            });
        });
});
</script>

<script src="script.js"></script>
  
</body>
</html>