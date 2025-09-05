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
$admin_id_no = (int)$_SESSION['id_no'];
$admin_name = '';

$sql = "SELECT name FROM tbl_admin WHERE id_no = '$admin_id_no'";
$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    $admin_name = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); 
}

// Fetch courses
$sql = "SELECT course_id, course_name FROM tbl_course";
$result = mysqli_query($conn, $sql);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_GET['action'])) {
    // Escape action parameter
    $action = mysqli_real_escape_string($conn, $_GET['action']);
    
    try {
        if ($action === 'edit') {
            // Escape input
            $courseID = (int)$_POST['course_id'];
            $courseName = mysqli_real_escape_string($conn, $_POST['course_name']);

            // Check for duplicate 
            $checkSql = "SELECT course_id FROM tbl_course WHERE LOWER(course_name) = LOWER('$courseName') AND course_id != '$courseID'";
            $checkResult = mysqli_query($conn, $checkSql);
            
            if (mysqli_num_rows($checkResult) > 0) {
                $_SESSION['message'] = 'Course name already exists!';
                $_SESSION['msg_type'] = 'error';
                header("Location: course");
                exit();
            }

            // Update course 
            $updateSql = "UPDATE tbl_course SET course_name = '$courseName' WHERE course_id = '$courseID'";
            
            if (mysqli_query($conn, $updateSql)) {
                $_SESSION['message'] = 'Course Successfully Updated!';
                $_SESSION['msg_type'] = 'success';
            } else {
                $_SESSION['message'] = 'Error updating course: ' . mysqli_error($conn);
                $_SESSION['msg_type'] = 'error';
            }

            header("Location: course");
            exit();
        } elseif ($action == 'delete') {
            // Escape input
            $courseID = (int)$_POST['course_id'];

            // Check if course is associated with any examinee
            $checkSql = "SELECT examinee_id FROM tbl_examinee WHERE first_preference = '$courseID' OR second_preference = '$courseID'";
            $checkResult = mysqli_query($conn, $checkSql);
            
            if (mysqli_num_rows($checkResult) > 0) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'This course is associated with an examinee and cannot be deleted.'
                ]);
                exit();
            }

            // Delete course
            $deleteSql = "DELETE FROM tbl_course WHERE course_id = '$courseID'";
            
            if (mysqli_query($conn, $deleteSql)) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Course successfully deleted!'
                ]);
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Error deleting course: ' . mysqli_error($conn)
                ]);
            }
            exit();
        } elseif ($action === 'add') {
            // Escape input
            $courseName = mysqli_real_escape_string($conn, $_POST['course_name']);
            
            // Check for duplicate
            $checkSql = "SELECT course_id FROM tbl_course WHERE LOWER(course_name) = LOWER('$courseName')";
            $checkResult = mysqli_query($conn, $checkSql);
            
            if (mysqli_num_rows($checkResult) > 0) {
                $_SESSION['message'] = 'Course name already exists!';
                $_SESSION['msg_type'] = 'error';
                header("Location: course");
                exit();
            }
            
            // Insert new course 
            $insertSql = "INSERT INTO tbl_course (course_name) VALUES ('$courseName')";
            
            if (mysqli_query($conn, $insertSql)) {
                $_SESSION['message'] = 'Course Successfully Added!';
                $_SESSION['msg_type'] = 'success';
            } else {
                $_SESSION['message'] = 'Error adding course: ' . mysqli_error($conn);
                $_SESSION['msg_type'] = 'error';
            }
            
            header("Location: course");
            exit();
        }
    } catch (Exception $e) {
        $_SESSION['message'] = 'A system error occurred';
        $_SESSION['msg_type'] = 'error';
        header("Location: course");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<title>Course Management | College Admission Test</title>
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
    .invalid-feedback {
        display: none;
        color: #dc3545;
        font-size: 0.875em;
    }
    .is-invalid {
        border-color: #dc3545 !important;
        background-color: #fff5f5;
    }
    .is-invalid:focus {
        box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25);
    }
</style>
</head>

<body data-pc-preset="preset-1" data-pc-direction="ltr" data-pc-theme="light">
<div class="loader-bg">
    <div class="loader-track">
        <div class="loader-fill"></div>
    </div>
</div>

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
                    <a class="pc-head-link dropdown-toggle arrow-none me-0" data-bs-toggle="dropdown" href="#" role="button" aria-haspopup="false" data-bs-auto-close="outside" aria-expanded="false">
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
    </div>
</header>

<div class="pc-container">
    <div class="pc-content">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h5 class="m-b-10">Course</h5>
                        </div>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php" style="text-decoration: none;">Home</a></li>
                            <li class="breadcrumb-item"><a href="javascript: void(0)" style="text-decoration: none;">Administration</a></li>
                            <li class="breadcrumb-item" aria-current="page">Course</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- [ Main Content ]  -->
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center" style="background-color: #053a1c; color: #fff;">
                        <h5 class="mb-0">Manage Course</h5>
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                            <i class="fas fa-plus-circle me-1"></i> Add New Course
                        </button>
                    </div>

                    <div class="card-body">
                        <table id="courseTable" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th class="text-center">Course Name</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ($result && mysqli_num_rows($result) > 0) {
                                    while ($row = mysqli_fetch_assoc($result)) {
                                        echo "<tr>";
                                        echo "<td class='text-center'>" . htmlspecialchars($row["course_name"], ENT_QUOTES, 'UTF-8') . "</td>";
                                        echo "<td class='text-center' style='text-align: right;'> 
                                            <button type='button' class='btn btn-warning editBtn' data-bs-toggle='modal' data-bs-target='#editCourseModal'
                                                data-id='" . (int)$row["course_id"] . "' data-course_name='" . htmlspecialchars($row["course_name"], ENT_QUOTES, 'UTF-8') . "'>
                                                <i class='fas fa-edit'></i> Edit
                                            </button>
                                            <button type='button' class='btn btn-danger deleteBtn' data-bs-toggle='modal' data-bs-target='#deleteCourseModal'
                                                data-id='" . (int)$row["course_id"] . "'>
                                                <i class='fas fa-trash-alt'></i> Delete
                                            </button>
                                        </td>";
                                        echo "</tr>";
                                    }
                                } 
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Add Course Modal -->
                <div class="modal fade" id="addCourseModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form id="addCourseForm" action="course?action=add" method="POST">
                                <div class="modal-header">
                                    <h5 class="modal-title">Add Course</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <label class="form-label">Course Name</label>
                                    <input type="text" name="course_name" id="course_name" class="form-control" required maxlength="100" oninput="validateCourseName(this)">
                                        <small class="text-muted">Must be 2-100 characters</small>
                                        <div class="invalid-feedback">Please enter a valid strand name (2-100 characters)</div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Add</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Edit Course Modal -->
                <div class="modal fade" id="editCourseModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form id="editCourseForm" action="course?action=edit" method="POST">
                                <input type="hidden" name="action" value="edit">
                                <div class="modal-header">
                                    <h5 class="modal-title">Edit Course</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="course_id" id="edit_course_id">
                                    <label class="form-label">Course Name</label>
                                    <input type="text" name="course_name" id="edit_course_name" class="form-control" required maxlength="100" oninput="validateCourseName(this)">
                                        <small class="text-muted">Must be 2-100 characters</small>
                                        <div class="invalid-feedback">Please enter a valid strand name (2-100 characters)</div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Save</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>

<script>
    function validateCourseName(input) {
          if (input.value.length < 2 || input.value.length > 60) {
              input.classList.add('is-invalid');
          } else {
              input.classList.remove('is-invalid');
          }
      }
$(document).ready(function () {
  // Validate course name length on form submit (2-100 characters)
  $('#addCourseForm').on('submit', function(e) {
    const courseName = $('#course_name').val();
    
    if (courseName.length < 2 || courseName.length > 100) {
      e.preventDefault();
      $('#course_name').addClass('is-invalid');
      $('#course_name_error').show();
    }
  });
  
  // Validate edit form (2-100 characters)
  $('#editCourseForm').on('submit', function(e) {
    const courseName = $('#edit_course_name').val();
    
    if (courseName.length < 2 || courseName.length > 100) {
      e.preventDefault();
      $('#edit_course_name').addClass('is-invalid');
      $('#edit_course_name_error').show();
    }
  });
  
  // Clear validation on input
  $('#course_name, #edit_course_name').on('input', function() {
    const val = $(this).val();
    if (val.length >= 2 && val.length <= 100) {
      $(this).removeClass('is-invalid');
      $(this).next('.invalid-feedback').hide();
    }
  });

  var table = $('#courseTable').DataTable({
    scrollCollapse: true,
    paging: true,
    fixedHeader: true,
    lengthChange: true,
    info: true,
    ordering: true,
    lengthMenu: [5, 10, 25, 50]
  });

  $('#courseTable').on('click', '.editBtn', function () {
    $('#edit_course_id').val($(this).data('id'));
    $('#edit_course_name').val($(this).data('course_name'));
    $('#edit_course_name').removeClass('is-invalid');
    $('#edit_course_name_error').hide();
  });

  // Only use SweetAlert for delete confirmation and success/error messages
  $('#courseTable').on('click', '.deleteBtn', function () {
    const courseID = $(this).data('id');
    Swal.fire({
      title: 'Are you sure?',
      text: 'This will permanently delete the course.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete it!',
      cancelButtonText: 'Cancel'
    }).then((result) => {
      if (result.isConfirmed) {
        $.post('course?action=delete', { course_id: courseID }, function (response) {
          const res = JSON.parse(response);
          Swal.fire(res.status === 'success' ? 'Deleted!' : 'Error', res.message, res.status).then(() => location.reload());
        });
      }
    });
  });

  <?php if (isset($_SESSION['message'])): ?>
    Swal.fire({
      title: '<?= $_SESSION['msg_type'] === 'success' ? 'Success' : 'Error' ?>',
      text: '<?= $_SESSION['message'] ?>',
      icon: '<?= $_SESSION['msg_type'] ?>',
    });
    <?php unset($_SESSION['message'], $_SESSION['msg_type']); ?>
  <?php endif; ?>
});

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