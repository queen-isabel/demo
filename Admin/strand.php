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

// Fetch Strand
$sql = "SELECT strand_id, strand_name FROM tbl_strand";
$result = mysqli_query($conn, $sql);

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_GET['action'])) {
    // Sanitize action parameter
    $action = mysqli_real_escape_string($conn, $_GET['action']);
    
    try {
        if ($action === 'edit') {
            // Validate and sanitize input
            $strandID = (int) $_POST['strand_id'];
            $strandName = mysqli_real_escape_string($conn, $_POST['strand_name']);
            
            if (empty($strandName)) {
                throw new Exception("Strand name cannot be empty");
            }

            // Check for duplicate
            $checkSql = "SELECT strand_id FROM tbl_strand WHERE LOWER(strand_name) = LOWER('$strandName') AND strand_id != $strandID";
            $checkResult = mysqli_query($conn, $checkSql);
            
            if (mysqli_num_rows($checkResult) > 0) {
                $_SESSION['message'] = 'Strand name already exists!';
                $_SESSION['msg_type'] = 'error';
                header("Location: strand");
                exit();
            }

            // Update strand
            $updateSql = "UPDATE tbl_strand SET strand_name = '$strandName' WHERE strand_id = $strandID";
           if (mysqli_query($conn, $updateSql)) {
        $_SESSION['message'] = 'Strand Successfully Updated!';
        $_SESSION['msg_type'] = 'success';
    } else {
        $_SESSION['message'] = 'Error updating strand: ' . mysqli_error($conn);
        $_SESSION['msg_type'] = 'error';
    }

            header("Location: strand");
            exit();

        } elseif ($action === 'delete') {
            // Validate input
            $strandID = (int) $_POST['strand_id'];

            // Check if strand has examinees
            $checkSql = "SELECT COUNT(*) AS count FROM tbl_examinee WHERE strand_id = $strandID";
            $checkResult = mysqli_query($conn, $checkSql);
            $count = mysqli_fetch_assoc($checkResult)['count'];

            if ($count > 0) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'This strand is associated with an examinee and cannot be deleted.'
                ]);
                exit();
            }

            // Delete strand
            $deleteSql = "DELETE FROM tbl_strand WHERE strand_id = $strandID";
            
            if (mysqli_query($conn, $deleteSql)) {
                echo json_encode(['status' => 'success', 'message' => 'Strand successfully deleted!']);
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Error deleting strand: ' . mysqli_error($conn)
                ]);
            }
            exit();

        } elseif ($action === 'add') {
            // Sanitize and validate input
            $strandName = mysqli_real_escape_string($conn, $_POST['strand_name']);

            if (empty($strandName)) {
                $_SESSION['message'] = 'Strand name cannot be empty!';
                $_SESSION['msg_type'] = 'error';
                header("Location: strand");
                exit();
            }
            
            // Check for duplicate
            $checkSql = "SELECT strand_id FROM tbl_strand WHERE LOWER(strand_name) = LOWER('$strandName')";
            $checkResult = mysqli_query($conn, $checkSql);
            
            if (mysqli_num_rows($checkResult) > 0) {
                $_SESSION['message'] = 'Strand name already exists!';
                $_SESSION['msg_type'] = 'error';
                header("Location: strand");
                exit();
            }
            
            // Insert new strand
            $insertSql = "INSERT INTO tbl_strand (strand_name) VALUES ('$strandName')";
            
            if (mysqli_query($conn, $insertSql)) {
                $_SESSION['message'] = 'Strand Successfully Added!';
                $_SESSION['msg_type'] = 'success';
            } else {
                $_SESSION['message'] = 'Error adding strand: ' . mysqli_error($conn);
                $_SESSION['msg_type'] = 'error';
            }
            
            header("Location: strand");
            exit();
        }
        
    } catch (Exception $e) {
        $_SESSION['message'] = 'A system error occurred: ' . $e->getMessage();
        $_SESSION['msg_type'] = 'error';
        header("Location: strand");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title>Strand Management | College Admission Test</title>
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
    <!-- [ Main Content ] -->
    <div class="pc-container">
        <div class="pc-content">
            <div class="page-header">
                <div class="page-block">
                    <div class="row align-items-center">
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h5 class="m-b-10">Strand</h5>
                            </div>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php" style="text-decoration: none;">Home</a></li>
                                <li class="breadcrumb-item"><a href="javascript: void(0)" style="text-decoration: none;">Administration</a></li>
                                <li class="breadcrumb-item" aria-current="page">Strand</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center" style="background-color: #053a1c; color: #fff;">
                            <h5 class="mb-0">Manage Strand</h5>
                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addStrandModal">
                                <i class="fas fa-plus-circle me-1"></i> Add New Strand
                            </button>
                        </div>

                        <div class="card-body">
                            <table id="strandTable" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th class="text-center">Strand Name</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if ($result && mysqli_num_rows($result) > 0) {
                                        while ($row = mysqli_fetch_assoc($result)) {
                                            echo "<tr>";
                                            echo "<td class='text-center'>" . htmlspecialchars($row["strand_name"], ENT_QUOTES, 'UTF-8') . "</td>";
                                            echo "<td class='text-center' style='text-align: right;'> 
                                                <button type='button' class='btn btn-warning editBtn' data-bs-toggle='modal' data-bs-target='#editStrandModal'
                                                    data-id='" . (int)$row["strand_id"] . "' data-strand_name='" . htmlspecialchars($row["strand_name"], ENT_QUOTES, 'UTF-8') . "'>
                                                    <i class='fas fa-edit'></i> Edit
                                                </button>
                                                <button type='button' class='btn btn-danger deleteBtn' data-bs-toggle='modal' data-bs-target='#deleteStrandModal'
                                                    data-id='" . (int)$row["strand_id"] . "'>
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
                    
                    <!-- Add Strand Modal -->
                    <div class="modal fade" id="addStrandModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form id="addStrandForm" action="strand?action=add" method="POST">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Add Strand</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <label class="form-label">Strand Name</label>
                                        <input type="text" name="strand_name" id="strand_name" class="form-control" required
                                              maxlength="60" 
                                              oninput="validateStrandName(this)">
                                        <small class="text-muted">Must be 2-60 characters</small>
                                        <div class="invalid-feedback">Please enter a valid strand name (2-60 characters)</div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary">Add</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Strand Modal -->
                    <div class="modal fade" id="editStrandModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form id="editStrandForm" action="strand?action=edit" method="POST">
                                    <input type="hidden" name="action" value="edit">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit Strand</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="strand_id" id="edit_strand_id">
                                        <label class="form-label">Strand Name</label>
                                        <input type="text" name="strand_name" id="edit_strand_name" class="form-control" required
                                              maxlength="60"
                                              oninput="validateStrandName(this)">
                                        <small class="text-muted">Must be 2-60 characters</small>
                                        <div class="invalid-feedback">Please enter a valid strand name (2-60 characters)</div>
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
        </div>
    </div>

    <script>
      function validateStrandName(input) {
          if (input.value.length < 2 || input.value.length > 60) {
              input.classList.add('is-invalid');
          } else {
              input.classList.remove('is-invalid');
          }
      }

      $(document).ready(function () {
        var table = $('#strandTable').DataTable({
          scrollCollapse: true,
          paging: true,
          fixedHeader: true,
          lengthChange: true,
          info: true,
          ordering: true,
          lengthMenu: [5, 10, 25, 50]
        });

        $('#strandTable').on('click', '.editBtn', function () {
          $('#edit_strand_id').val($(this).data('id'));
          $('#edit_strand_name').val($(this).data('strand_name'));
        });

        $('#strandTable').on('click', '.deleteBtn', function () {
          const strandID = $(this).data('id');
          Swal.fire({
            title: 'Are you sure?',
            text: 'This will permanently delete the strand.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
          }).then((result) => {
            if (result.isConfirmed) {
              $.post('strand?action=delete', { strand_id: strandID }, function (response) {
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