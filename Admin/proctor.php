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

// Fetch all proctors
$stmt ="SELECT * FROM tbl_proctor";
$result = mysqli_query($conn, $stmt);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_GET['action'])) {
    $action = mysqli_real_escape_string($conn, $_GET['action']);

    if ($action == 'edit') {
        $proctorID = (int) $_POST['proctor_id'];
        $proctor_name = mysqli_real_escape_string($conn, $_POST['proctor_name']);
        $p_id = mysqli_real_escape_string($conn, $_POST['p_id']);

        // Check for duplicate proctor ID
        $checkStmt = "SELECT proctor_id FROM tbl_proctor WHERE p_id = '$p_id' AND proctor_id != '$proctorID'";
        $result = mysqli_query($conn, $checkStmt);

        if (mysqli_num_rows($result) > 0) {
            $_SESSION['message'] = 'Proctor ID already exists! Please choose a different one.';
            $_SESSION['msg_type'] = 'error';
            header("Location: proctor");
            exit();
        } 

        $updateStmt = "UPDATE tbl_proctor SET proctor_name = '$proctor_name', p_id = '$p_id' WHERE proctor_id = '$proctorID'";
        $result = mysqli_query($conn, $updateStmt);
        
        if ($result) {
            $_SESSION['message'] = 'Proctor Successfully Updated!';
            $_SESSION['msg_type'] = 'success';
        } else {
            $_SESSION['message'] = 'Error updating proctor: ' . mysqli_error($conn);
            $_SESSION['msg_type'] = 'error';
        }

        header("Location: proctor");
        exit();
    } elseif ($action == 'delete') {
        $proctorID = (int) $_POST['proctor_id'];

        $checkSql = "SELECT COUNT(*) AS count FROM exam_schedules WHERE proctor_id = $proctorID";
        $checkResult = mysqli_query($conn, $checkSql);
        $count = mysqli_fetch_assoc($checkResult)['count'];

        if ($count > 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'This proctor is associated with an exam and cannot be deleted.'
            ]);
            exit();
        }

        $deleteStmt = "DELETE FROM tbl_proctor WHERE proctor_id = $proctorID";
        
        if (mysqli_query($conn, $deleteStmt)) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Proctor successfully deleted!'
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Error deleting proctor: ' . mysqli_error($conn)
            ]);
        }
        exit();
    } elseif ($action == 'add') {
        $proctor_name = mysqli_real_escape_string($conn, $_POST['proctor_name']);
        $p_id = mysqli_real_escape_string($conn, $_POST['p_id']);

        // Check for duplicate proctor ID
        $checkStmt = "SELECT * FROM tbl_proctor WHERE p_id = '$p_id'";
        $checkResult = mysqli_query($conn, $checkStmt);

        if (mysqli_num_rows($checkResult) > 0) {
            $_SESSION['message'] = 'Proctor ID already exists!';
            $_SESSION['msg_type'] = 'error';
            header("Location: proctor");
            exit();
        }

        // Insert new proctor        
        $insertSql = "INSERT INTO tbl_proctor (proctor_name, p_id) VALUES ('$proctor_name', '$p_id')";
        if (mysqli_query($conn, $insertSql)) {
            $_SESSION['message'] = 'Proctor Successfully Added!';
            $_SESSION['msg_type'] = 'success';
        } else {
            $_SESSION['message'] = 'Error adding proctor: ' . mysqli_error($conn);
            $_SESSION['msg_type'] = 'error';
        }
        header("Location: proctor");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
<title>Proctor Management | College Admission Test</title>
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
    }
    .is-invalid {
        border-color: #dc3545 !important;
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
                <h5 class="m-b-10">Proctor</h5>
              </div>
              <ul class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php" style="text-decoration: none;">Home</a></li>
                <li class="breadcrumb-item"><a href="javascript: void(0)" style="text-decoration: none;">Exam Management</a></li>
                <li class="breadcrumb-item" aria-current="page">Proctor</li>
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
              <h5 class="mb-0">Manage Proctor</h5>
              <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addProctorModal">
                <i class="fas fa-plus-circle me-1"></i> Add New Proctor
              </button>
            </div>

            <div class="card-body">
              <table id="proctorTable" class="table table-bordered table-striped">
                <thead>
                  <tr>
                    <th class="text-center">Proctor ID</th>
                    <th class="text-center">Proctor Name</th>
                    <th class="text-center">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  if ($result->num_rows > 0) {
                      while ($row = $result->fetch_assoc()) {
                          echo "<tr>";
                          echo "<td class='text-center'>" . htmlspecialchars($row["p_id"], ENT_QUOTES, 'UTF-8') . "</td>"; 
                          echo "<td class='text-center'>" . htmlspecialchars($row["proctor_name"], ENT_QUOTES, 'UTF-8') . "</td>";
                          echo "<td class='text-center'>
                                  <button type='button' class='btn btn-warning editBtn' data-bs-toggle='modal' data-bs-target='#editProctorModal'
                                    data-id='" . (int)$row["proctor_id"] . "' data-p_id='" . htmlspecialchars($row["p_id"], ENT_QUOTES, 'UTF-8') . "' 
                                    data-proctor_name='" . htmlspecialchars($row["proctor_name"], ENT_QUOTES, 'UTF-8') . "'>
                                    <i class='fas fa-edit'></i> Edit
                                  </button>
                                  <button type='button' class='btn btn-danger deleteBtn' data-bs-toggle='modal' data-bs-target='#deleteProctorModal'
                                    data-id='" . (int)$row["proctor_id"] . "'>
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
        </div>
      </div>
    </div>
  </div>

  <!-- Add Proctor Modal -->
  <div class="modal fade" id="addProctorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form id="addProctorForm" action="proctor?action=add" method="POST">
          <div class="modal-header">
            <h5 class="modal-title">Add Proctor</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Proctor ID</label>
              <input type="text" name="p_id" id="p_id" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Proctor Name</label>
              <input type="text" name="proctor_name" id="proctor_name" class="form-control" required >
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Add Proctor</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Edit Proctor Modal -->
  <div class="modal fade" id="editProctorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form id="editProctorForm" action="proctor?action=edit" method="POST">
          <input type="hidden" name="action" value="edit">
          <div class="modal-header">
            <h5 class="modal-title">Edit Proctor</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Proctor ID</label>
              <input type="text" name="p_id" id="edit_p_id" class="form-control" required>
            </div>
            <div class="mb-3">
              <input type="hidden" name="proctor_id" id="edit_proctor_id">
              <label class="form-label">Proctor Name</label>
              <input type="text" name="proctor_name" id="edit_proctor_name" class="form-control" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>

    <script>
    $(document).ready(function () {
        // Initialize DataTable
        $('#proctorTable').DataTable({
            scrollCollapse: true,
            paging: true,
            fixedHeader: true,
            lengthChange: true,
            info: true,
            ordering: true,
            lengthMenu: [5, 10, 25, 50]
        });

        // Fill Edit Modal
        $('#proctorTable').on('click', '.editBtn', function () {
            $('#edit_proctor_id').val($(this).data('id'));
            $('#edit_p_id').val($(this).data('p_id'));
            $('#edit_proctor_name').val($(this).data('proctor_name'));
        });

        // Delete confirmation
        $('#proctorTable').on('click', '.deleteBtn', function () {
            const proctorID = $(this).data('id');
            Swal.fire({
                title: 'Are you sure?',
                text: 'This will permanently delete the proctor.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        type: 'POST',
                        url: 'proctor?action=delete',
                        data: { proctor_id: proctorID },
                        dataType: 'json',
                        success: function(response) {
                            Swal.fire({
                                title: response.status === 'success' ? 'Deleted!' : 'Error',
                                text: response.message,
                                icon: response.status
                            }).then(() => {
                                if (response.status === 'success') {
                                    location.reload();
                                }
                            });
                        },
                        error: function() {
                            Swal.fire('Error', 'An error occurred while processing your request', 'error');
                        }
                    });
                }
            });
        });

        // Display SweetAlert for session messages
        <?php if (isset($_SESSION['message'])): ?>
            Swal.fire({
                title: '<?= $_SESSION['msg_type'] === 'success' ? 'Success' : 'Error' ?>',
                text: '<?= addslashes($_SESSION['message']) ?>',
                icon: '<?= $_SESSION['msg_type'] ?>',
            }).then(() => {
                <?php unset($_SESSION['message']); unset($_SESSION['msg_type']); ?>
            });
        <?php endif; ?>

        // Logout confirmation
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
    });
    </script>

</body>
</html>