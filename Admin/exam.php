<?php
session_start();
include('../server.php');

// Function to get active school year
function getActiveSchoolYear($conn) {
    $query = "SELECT school_year_id, school_year FROM tbl_school_year WHERE school_year_status = 'active' LIMIT 1";
    $result = mysqli_query($conn, $query);
    if ($row = mysqli_fetch_assoc($result)) {
        return $row['school_year_id'];
    }
    return null;
}

$admin_name = '';
if (isset($_SESSION['id_no'])) {
    // Sanitize session input
    $id_no = mysqli_real_escape_string($conn, $_SESSION['id_no']);
    $query = "SELECT name FROM tbl_admin WHERE id_no = '$id_no'";
    $result = mysqli_query($conn, $query);
    if ($row = mysqli_fetch_assoc($result)) {
        $admin_name = $row['name'];
    }
}

// Function to fetch and store random questions
function fetchAndStoreRandomQuestions($scheduleID, $conn) {
    // Sanitize input
    $scheduleID = mysqli_real_escape_string($conn, $scheduleID);
    
    // Get active school year ID
    $active_school_year_id = getActiveSchoolYear($conn);

    // Get batch_id and school_year_id of the given schedule
    $checkQuery = "SELECT s.batch_id, b.school_year_id 
                   FROM tbl_schedule s 
                   JOIN tbl_batch b ON s.batch_id = b.batch_id 
                   WHERE s.schedule_id = '$scheduleID'";
    $result = mysqli_query($conn, $checkQuery);
    if ($row = mysqli_fetch_assoc($result)) {
        $batchID = $row['batch_id'];
        $schedule_school_year_id = $row['school_year_id'];
    }

    // Check if the schedule belongs to the active school year
    if ($schedule_school_year_id != $active_school_year_id) {
        return;
    }

    // Loop through all subjects
    $subjectsQuery = "SELECT DISTINCT subject_id FROM tbl_questions";
    $subjectsResult = mysqli_query($conn, $subjectsQuery);

    while ($subjectRow = mysqli_fetch_assoc($subjectsResult)) {
        $subjectID = $subjectRow['subject_id'];

        // Get questions for the subject
        $questionsQuery = "SELECT question_id FROM tbl_questions WHERE subject_id = '$subjectID' ORDER BY RAND() LIMIT 20";
        $questionsResult = mysqli_query($conn, $questionsQuery);

        // Insert each question into tbl_exam_questions
        while ($questionRow = mysqli_fetch_assoc($questionsResult)) {
            $questionID = $questionRow['question_id'];
            $insertQuery = "INSERT INTO tbl_exam_questions (schedule_id, question_id, subject_id) VALUES ('$scheduleID', '$questionID', '$subjectID')";
            mysqli_query($conn, $insertQuery);
        }
    }
}

// Function to fetch available batches
function fetchAvailableBatches($conn) {
    $active_school_year_id = getActiveSchoolYear($conn);
    if (!$active_school_year_id) {
        return [];
    }

    $sql = "
    SELECT b.batch_id, b.batch_number
    FROM tbl_batch b
    INNER JOIN tbl_schedule s ON b.batch_id = s.batch_id
    WHERE b.school_year_id = '$active_school_year_id'
    AND NOT EXISTS (
        SELECT 1
        FROM exam_schedules es
        WHERE es.schedule_id = s.schedule_id
    )
    GROUP BY b.batch_id
    ";
    
    $result = mysqli_query($conn, $sql);
    
    $batches = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $batches[] = $row;
    }
    return $batches;
}

// Function to fetch all proctors
function fetchAllProctors($conn) {
    $sql = "SELECT proctor_id, proctor_name FROM tbl_proctor";
    $result = mysqli_query($conn, $sql);
    $proctors = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $proctors[] = $row;
    }
    return $proctors;
}

$batches = fetchAvailableBatches($conn);
$proctors = fetchAllProctors($conn);

// Function to distribute examinees
function distributeExaminees($examScheduleIDs, $batchID, $conn) {
    $batchID = mysqli_real_escape_string($conn, $batchID);
    $active_school_year_id = getActiveSchoolYear($conn);

    $examineeQuery = "
    SELECT e.examinee_id, e.lname 
    FROM tbl_examinee e
    INNER JOIN tbl_batch b ON e.batch_id = b.batch_id
    WHERE e.batch_id = '$batchID' 
      AND b.school_year_id = '$active_school_year_id' 
      AND e.estatus = 'approved' 
    ORDER BY e.lname ASC
    ";
    
    $result = mysqli_query($conn, $examineeQuery);
    $examinees = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $examinees[] = $row;
    }

    if (empty($examinees) || empty($examScheduleIDs)) return;

    $groupSizes = array_fill(0, count($examScheduleIDs), floor(count($examinees) / count($examScheduleIDs)));
    for ($i = 0; $i < count($examinees) % count($examScheduleIDs); $i++) {
        $groupSizes[$i]++;
    }

    $index = 0;
    foreach ($examScheduleIDs as $scheduleIndex => $examScheduleID) {
        $examScheduleID = mysqli_real_escape_string($conn, $examScheduleID);
        
        for ($i = 0; $i < $groupSizes[$scheduleIndex]; $i++) {
            if ($index >= count($examinees)) break;
            $examineeID = $examinees[$index++]['examinee_id'];
            $examineeID = mysqli_real_escape_string($conn, $examineeID);

            $checkQuery = "SELECT 1 FROM examinee_schedules WHERE exam_schedule_id = '$examScheduleID' AND examinee_id = '$examineeID'";
            $checkResult = mysqli_query($conn, $checkQuery);
            if (mysqli_num_rows($checkResult) > 0) continue;

            $assignQuery = "INSERT INTO examinee_schedules (exam_schedule_id, examinee_id) VALUES ('$examScheduleID', '$examineeID')";
            mysqli_query($conn, $assignQuery);
        }
    }
}

// Function to auto distribute new examinees
function autoDistributeNewExaminees($conn) {
    $active_school_year = getActiveSchoolYear($conn);
    
    $batchQuery = "SELECT DISTINCT tbl_batch.batch_id 
                   FROM tbl_examinee 
                   INNER JOIN tbl_batch ON tbl_examinee.batch_id = tbl_batch.batch_id
                   INNER JOIN tbl_school_year ON tbl_batch.school_year_id = tbl_school_year.school_year_id
                   WHERE tbl_examinee.estatus = 'approved' AND tbl_batch.school_year_id = '$active_school_year'";
    
    $batchResult = mysqli_query($conn, $batchQuery);

    if (mysqli_num_rows($batchResult) > 0) {
        while ($batchRow = mysqli_fetch_assoc($batchResult)) {
            $batchID = $batchRow['batch_id'];
            
            $scheduleQuery = "SELECT exam_schedule_id FROM exam_schedules 
                              WHERE schedule_id IN (SELECT schedule_id FROM tbl_schedule WHERE batch_id = '$batchID')";
            $scheduleResult = mysqli_query($conn, $scheduleQuery);

            $examScheduleIDs = [];
            while ($scheduleRow = mysqli_fetch_assoc($scheduleResult)) {
                $examScheduleIDs[] = $scheduleRow['exam_schedule_id'];
            }

            if (count($examScheduleIDs) === 0) continue;

            $examineeQuery = "SELECT examinee_id FROM tbl_examinee 
                              WHERE batch_id = '$batchID' AND estatus = 'approved' 
                              AND examinee_id NOT IN (SELECT examinee_id FROM examinee_schedules)";
            $examineeResult = mysqli_query($conn, $examineeQuery);

            $examinees = [];
            while ($row = mysqli_fetch_assoc($examineeResult)) {
                $examinees[] = $row['examinee_id'];
            }

            if (count($examinees) === 0) continue;

            $index = 0;
            foreach ($examinees as $examineeID) {
                $examineeID = mysqli_real_escape_string($conn, $examineeID);
                $scheduleID = $examScheduleIDs[$index % count($examScheduleIDs)];
                $scheduleID = mysqli_real_escape_string($conn, $scheduleID);
                
                $assignQuery = "INSERT INTO examinee_schedules (exam_schedule_id, examinee_id) VALUES ('$scheduleID', '$examineeID')";
                mysqli_query($conn, $assignQuery);

                $index++;
            }
        }
    }
}

autoDistributeNewExaminees($conn);

try {
    $active_school_year = getActiveSchoolYear($conn);

    if (is_array($active_school_year) && isset($active_school_year['school_year'])) {
        $school_year_value = $active_school_year['school_year'];
    } else {
        $school_year_value = 'No active school year';
    }
    
    // Handle exam creation
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exam_name'], $_POST['exam_direction'], $_POST['hours'], $_POST['minutes'], $_POST['seconds'])) {
        $exam_name = mysqli_real_escape_string($conn, $_POST['exam_name']);
        $exam_direction = mysqli_real_escape_string($conn, $_POST['exam_direction']);
        $hours = sprintf("%02d", (int)$_POST['hours']); 
        $minutes = sprintf("%02d", (int)$_POST['minutes']);
        $seconds = sprintf("%02d", (int)$_POST['seconds']);
        
        $exam_duration = "$hours:$minutes:$seconds"; 

        try {
            if (empty($exam_name) || empty($exam_direction)) {
                throw new Exception("Please fill all fields.");
            }

            $active_school_year = getActiveSchoolYear($conn);
            if (!$active_school_year) {
                throw new Exception("No active school year found.");
            }
            $school_year_id = $active_school_year;

            // Check if an exam already exists for the active school year
            $sql = "SELECT * FROM exams WHERE school_year_id = '$school_year_id'";
            $result = mysqli_query($conn, $sql);

            if (mysqli_num_rows($result) > 0) {
                throw new Exception("Only one exam can be added for the active school year.");
            }

            $sql = "INSERT INTO exams (exam_name, exam_direction, exam_duration, school_year_id) 
                    VALUES ('$exam_name', '$exam_direction', '$exam_duration', '$school_year_id')";
            if (!mysqli_query($conn, $sql)) {
                throw new Exception("Error inserting exam: " . mysqli_error($conn));
            }

            $_SESSION['message'] = "Exam created successfully!";
            $_SESSION['msg_type'] = "success";
            header("Location: exam");
            exit();
        } catch (Exception $e) {
            $_SESSION['message'] = $e->getMessage();
            $_SESSION['msg_type'] = "error";
            header("Location: exam");
            exit();
        }
    }

    // Handle exam update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_exam'])) {
        $exam_id = (int)$_POST['exam_id']; 
        $exam_name = mysqli_real_escape_string($conn, $_POST['exam_name']);
        $exam_direction = mysqli_real_escape_string($conn, $_POST['exam_direction']);
        $exam_duration = mysqli_real_escape_string($conn, $_POST['exam_duration']);

        try {
            if (empty($exam_name) || empty($exam_direction) || empty($exam_duration)) {
                throw new Exception("All fields are required.");
            }

            $sql = "UPDATE exams SET exam_name = '$exam_name', exam_direction = '$exam_direction', 
                    exam_duration = '$exam_duration' WHERE exam_id = '$exam_id'";
            if (!mysqli_query($conn, $sql)) {
                throw new Exception("Error updating exam: " . mysqli_error($conn));
            }

            $_SESSION['message'] = "Exam updated successfully!";
            $_SESSION['msg_type'] = "success";
            header("Location: exam");
            exit();
        } catch (Exception $e) {
            $_SESSION['message'] = $e->getMessage();
            $_SESSION['msg_type'] = "error";
            header("Location: exam");
            exit();
        }
    }

    // Handle exam update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exam_id'], $_POST['exam_name'], $_POST['exam_direction'], $_POST['exam_duration'])) {
        $exam_id = (int)$_POST['exam_id'];
        $exam_name = mysqli_real_escape_string($conn, $_POST['exam_name']);
        $exam_direction = mysqli_real_escape_string($conn, $_POST['exam_direction']);
        $exam_duration = mysqli_real_escape_string($conn, $_POST['exam_duration']);

        try {
            if (empty($exam_name) || empty($exam_direction) || empty($exam_duration)) {
                throw new Exception("All fields are required.");
            }

            if (!preg_match('/^([0-9]{2}):([0-9]{2}):([0-9]{2})$/', $exam_duration)) {
                throw new Exception("Invalid duration format. Use HH:MM:SS.");
            }

            $sql = "UPDATE exams SET exam_name = '$exam_name', exam_direction = '$exam_direction', 
                    exam_duration = '$exam_duration' WHERE exam_id = '$exam_id'";
            if (!mysqli_query($conn, $sql)) {
                throw new Exception("Error updating exam: " . mysqli_error($conn));
            }

            $_SESSION['message'] = "Exam updated successfully!";
            $_SESSION['msg_type'] = "success";
            header("Location: exam");
            exit();
        } catch (Exception $e) {
            $_SESSION['message'] = $e->getMessage();
            $_SESSION['msg_type'] = "error";
            header("Location: exam");
            exit();
        }
    }

    // Handle schedule generation
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action']) && $_POST['action'] === 'generate_schedule') {
            if (!isset($_POST['exam_id']) || empty($_POST['exam_id'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid Exam ID.']);
                exit;
            }

            $exam_id = (int)$_POST['exam_id'];

            // Check for available schedules
            $schedule_sql = "
                SELECT schedule_id 
                FROM tbl_schedule 
                WHERE schedule_id NOT IN (
                    SELECT schedule_id 
                    FROM exam_schedules
                )
            ";
            $schedule_result = mysqli_query($conn, $schedule_sql);

            if (mysqli_num_rows($schedule_result) === 0) {
                echo json_encode(['success' => false, 'message' => 'Please add schedule first.']);
                exit;
            }

            echo json_encode(['success' => true, 'message' => 'Available schedules found.']);
            exit;
        }

        // Handle schedule creation
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'create_schedule') {
            $examID = (int)$_POST['exam_id'];
            $batchID = (int)$_POST['batch_id'];
            $proctorIDs = array_map('intval', $_POST['proctors']); 
        
            if (empty($proctorIDs)) {
                echo 'Please select at least 1 proctor.';
                exit;
            }
        
            $scheduleQuery = "SELECT s.schedule_id 
                FROM tbl_schedule s
                INNER JOIN tbl_batch b ON s.batch_id = b.batch_id
                WHERE s.batch_id = '$batchID' AND b.school_year_id = '$active_school_year'
                LIMIT 1";
            $result = mysqli_query($conn, $scheduleQuery);
        
            if (mysqli_num_rows($result) === 0) {
                echo 'No available schedule for this batch.';
                exit;
            }
        
            $scheduleRow = mysqli_fetch_assoc($result);
            $scheduleID = $scheduleRow['schedule_id'];
            $examScheduleIDs = [];
        
            // Insert schedule for each proctor
            foreach ($proctorIDs as $proctorID) {
                $proctorID = (int)$proctorID; 
                $insertQuery = "INSERT INTO exam_schedules (exam_id, schedule_id, proctor_id, exam_status) 
                               VALUES ('$examID', '$scheduleID', '$proctorID', 'not started')";
                mysqli_query($conn, $insertQuery);
                $examScheduleIDs[] = mysqli_insert_id($conn);
            }
        
            fetchAndStoreRandomQuestions($scheduleID, $conn);
            distributeExaminees($examScheduleIDs, $batchID, $conn);
        
            echo 'Successfully added schedule, assigned shared questions, and distributed examinees!';
            exit;
        }
    }    

    // Fetch existing exams for the active school year
    $exam_sql = "SELECT * FROM exams WHERE school_year_id = '$active_school_year'";
    $exam_results = mysqli_query($conn, $exam_sql);

    // Fetch exam schedules
    $exam_schedule_sql = "SELECT es.exam_schedule_id, es.exam_status, e.exam_name, 
        e.exam_duration, s.exam_date, s.exam_start_time, s.exam_end_time,
        b.batch_number, p.proctor_name
        FROM exam_schedules es
        JOIN exams e ON es.exam_id = e.exam_id
        JOIN tbl_schedule s ON es.schedule_id = s.schedule_id
        JOIN tbl_proctor p ON es.proctor_id = p.proctor_id
        JOIN tbl_batch b ON s.batch_id = b.batch_id
        WHERE e.school_year_id = '$active_school_year'";
    $exam_schedule_results = mysqli_query($conn, $exam_schedule_sql);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<title>Exam Management| College Admission Test</title>
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
          <h5 class="m-b-10">Exam</h5>
        </div>
        <ul class="breadcrumb">
          <li class="breadcrumb-item"><a href="dashboard.php" style="text-decoration: none;">Home</a></li>
          <li class="breadcrumb-item"><a href="javascript: void(0)" style="text-decoration: none;">Exam Management</a></li>
          <li class="breadcrumb-item" aria-current="page">Exam</li>
        </ul>
      </div>
    </div>
  </div>
</div>
      <div class="row">
        <div class="col-sm-12">
          <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center" style="background-color: #053a1c; color: #fff; height: 60px;">
          <h5 class="mb-0">Manage Exam</h5>
  <button class="btn btn-success " data-bs-toggle="modal" data-bs-target="#addExamModal">
    <i class="fas fa-plus-circle me-1"></i> Add New Exam
  </button>
</div>

            <div class="card-body">
            <table id="examsTable" class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>Exam Name</th>
                <th>Exam Direction</th>
                <th>Exam Duration</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($exam_results->num_rows > 0): ?>
                <?php while ($exam = $exam_results->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($exam['exam_name']); ?></td>
                        <td><?php echo htmlspecialchars($exam['exam_direction']); ?></td>
                        <td>
    <?php 
        $duration = new DateTime($exam['exam_duration']);
        $hours = (int)$duration->format('H');
        $minutes = (int)$duration->format('i');
        $formatted_duration = '';

        if ($hours > 0) {
            $formatted_duration .= $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ';
        }

        if ($minutes > 0) {
            if ($hours > 0) {
                $formatted_duration .= 'and ';
            }
            $formatted_duration .= $minutes . ' minute' . ($minutes > 1 ? 's' : '');
        }

        echo trim($formatted_duration ? $formatted_duration : ''); 
    ?>
</td>
<td>
  <input type="hidden" class="exam-id" value="<?php echo $exam['exam_id']; ?>">
  <div class="d-flex gap-2">
    <button class="btn btn-primary add-schedule-btn" data-exam-id="<?php echo $exam['exam_id']; ?>">
      <i class="fa-solid fa-circle-plus"></i> Add Schedule
    </button>                        
    <button class="btn btn-warning edit-exam-btn"
            data-exam-id="<?php echo $exam['exam_id']; ?>"
            data-exam-name="<?php echo $exam['exam_name']; ?>"
            data-exam-direction="<?php echo $exam['exam_direction']; ?>"
            data-exam-duration="<?php echo $exam['exam_duration']; ?>">
      <i class="fas fa-edit"></i> Edit
    </button>
  </div>
</td>

                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4">No exams available for the active school year.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

                <!-- Exam List Table -->
                <table id="examTable" class="table table-bordered table-striped">
                <thead>
        <tr>
            <th>Exam Name</th>
            <th>Exam Duration</th>
            <th>Exam Date</th>
            <th>Start Time</th>
            <th>End Time</th>
            <th>Batch Number</th>
            <th>Proctor Name</th>
            <th>Exam Status</th>
        </tr>
    </thead>
    <tbody>
    <?php
    while ($row = $exam_schedule_results->fetch_assoc()) {
        // Determine status and corresponding Bootstrap class
        $statusText = htmlspecialchars($row['exam_status']);
        $statusClass = '';
        
        switch ($row['exam_status']) {
            case 'not started':
                $statusClass = 'secondary';  // Gray
                break;
            case 'started':
                $statusClass = 'warning';    // Blue
                $statusText = 'In Progress';
                break;
            case 'finished':
                $statusClass = 'success';    // Green
                $statusText = 'Completed';
                break;
            default:
                $statusClass = 'warning';    // Yellow (for unknown statuses)
        }

        echo "<tr>
                <td>" . htmlspecialchars($row['exam_name']) . "</td>
                <td>" . htmlspecialchars($row['exam_duration']) . "</td>
                <td>" . htmlspecialchars($row['exam_date']) . "</td>
                <td>" . date("g:i A", strtotime($row['exam_start_time'])) . "</td>
                <td>" . date("g:i A", strtotime($row['exam_end_time'])) . "</td>
                <td>" . htmlspecialchars($row['batch_number']) . "</td>
                <td>" . htmlspecialchars($row['proctor_name']) . "</td>
                <td>
                    <span class='badge bg-$statusClass'>$statusText</span>
                </td>
              </tr>";
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
            </main>

<!-- Add Exam Modal -->
<div id="addExamModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <p class="modal-title">Add New Exam</p>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="exam" method="post" id="addExamForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Exam Name</label>
                        <input type="text" class="form-control" name="exam_name" id="exam_name" required>
                        <div id="examNameError" class="text-danger small mt-1" style="display:none;"></div>
                    </div>
                    <div class="mb-3">
                        <label>Exam Direction</label>
                        <textarea class="form-control" name="exam_direction" id="exam_direction" rows="10" required></textarea>
                        <div id="examDirectionError" class="text-danger small mt-1" style="display:none;"></div>
                    </div>
                    <div class="mb-3">
                        <label>Duration (HH:MM:SS)</label>
                        <div style="display: flex; gap: 5px;">
                            <select class="form-control" name="hours" required>
                                <?php for ($i = 0; $i <= 23; $i++): ?>
                                    <option value="<?php echo sprintf("%02d", $i); ?>"><?php echo sprintf("%02d", $i); ?></option>
                                <?php endfor; ?>
                            </select>
                            <select class="form-control" name="minutes" required>
                                <?php for ($i = 0; $i < 60; $i++): ?>
                                    <option value="<?php echo sprintf("%02d", $i); ?>"><?php echo sprintf("%02d", $i); ?></option>
                                <?php endfor; ?>
                            </select>
                            <select class="form-control" name="seconds" required>
                                <?php for ($i = 0; $i < 60; $i++): ?>
                                    <option value="<?php echo sprintf("%02d", $i); ?>"><?php echo sprintf("%02d", $i); ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add New Exam</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Add Exam Schedule Modal -->
<div id="addScheduleModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Exam Schedule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addScheduleForm">
                <div class="modal-body">
                <div class="mb-3">
                    <input type="hidden" id="exam_id" name="exam_id">

                        <label for="batchSelect">Select Batch</label>
                        <select id="batchSelect" name="batch_id" class="form-control" required>
                            <option value="" disabled>Select Batch</option>
                            <?php foreach ($batches as $batch): ?>
                                <option value="<?php echo $batch['batch_id']; ?>">
                                    Batch <?php echo htmlspecialchars($batch['batch_number']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Select Proctors</label>
                        <div>
                            <?php foreach ($proctors as $proctor): ?>
                                <div>
                                    <input type="checkbox" name="proctors[]" value="<?php echo $proctor['proctor_id']; ?>">
                                    <?php echo htmlspecialchars($proctor['proctor_name']); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Exam Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Exam Modal -->
<div id="editExamModal" class="modal fade" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editExamModalLabel">Edit Exam</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editExamForm" method="POST">
                    <input type="hidden" name="exam_id" id="edit_exam_id">
                    
                    <div class="mb-3">
                        <label for="edit_exam_name">Exam Name</label>
                        <input type="text" class="form-control" name="exam_name" id="edit_exam_name" required>
                        <div id="editExamNameError" class="text-danger small mt-1" style="display:none;"></div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_exam_direction">Exam Direction</label>
                        <textarea class="form-control" name="exam_direction" id="edit_exam_direction" rows="15" required></textarea>
                        <div id="editExamDirectionError" class="text-danger small mt-1" style="display:none;"></div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_exam_duration">Exam Duration (HH:MM:SS)</label>
                        <input type="text" class="form-control" name="exam_duration" id="edit_exam_duration" required pattern="^([0-9]{2}):([0-9]{2}):([0-9]{2})$">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" form="editExamForm">Save Changes</button>
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
 

    <script src="script.js"></script>
    <script>
     $(document).ready(function () {
            var table = $('#examTable').DataTable({
        scrollCollapse: true, 
        paging: true,  
        fixedHeader: true,  
        lengthChange: true, 
        info: true,  
        ordering: true, 
        lengthMenu: [10, 25, 50, 100]

    });

    
    var table = $('#exam').DataTable({
        scrollCollapse: true, 
        paging: true,  
        fixedHeader: true,  
        lengthChange: true, 
        info: true,  
        ordering: true, 
        lengthMenu: [10, 25, 50, 100]

    });
});

$(document).ready(function () {
        $(".edit-exam-btn").click(function () {
            var examId = $(this).data("exam-id");
            var examName = $(this).data("exam-name");
            var examDirection = $(this).data("exam-direction");
            var examDuration = $(this).data("exam-duration");

            $("#edit_exam_id").val(examId);
            $("#edit_exam_name").val(examName);
            $("#edit_exam_direction").val(examDirection);
            $("#edit_exam_duration").val(examDuration);

            $("#editExamModal").modal("show");
        });
    });
    
$(document).ready(function () {
    $(".add-schedule-btn").click(function () {
        var examId = $(this).data('exam-id');
        $('#exam_id').val(examId); 
        $('#addScheduleModal').modal('show');
    });

    $('#addScheduleForm').submit(function (e) {
        e.preventDefault();
        
        const selectedProctors = $('input[name="proctors[]"]:checked');
        if (selectedProctors.length === 0) {
            Swal.fire('Error', 'Please select at least 1 proctor.', 'error');
            return;
        }

        $.ajax({
            url: 'exam',
            method: 'POST',
            data: $(this).serialize() + '&action=create_schedule',
            success: function (response) {
                Swal.fire('Success', response, 'success').then(() => {
                    location.reload();
                });
            },
            error: function () {
                Swal.fire('Error', 'Failed to create schedule.', 'error');
            }
        });
    });


    // Handle logout button click
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

    <?php if (isset($_SESSION['message'])): ?>
        Swal.fire({
            title: "<?php echo $_SESSION['msg_type'] === 'success' ? 'Success!' : 'Error!'; ?>",
            text: "<?php echo $_SESSION['message']; ?>",
            icon: "<?php echo $_SESSION['msg_type']; ?>",
            confirmButtonText: 'OK'
        }).then(() => {
            <?php unset($_SESSION['message'], $_SESSION['msg_type']); // Clear the session message ?>
        });
    <?php endif; ?>
});


$('.log_out a').click(function (e) {
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
</body>
</html>