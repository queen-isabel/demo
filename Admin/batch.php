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

// Get batch records
$query = "SELECT b.batch_id, b.batch_number, s.school_year 
          FROM tbl_batch b
          INNER JOIN tbl_school_year s ON b.school_year_id = s.school_year_id
          WHERE s.school_year_status = 'active'";
$result = mysqli_query($conn, $query);

// Get active school year
$activeSchoolYearQuery = "SELECT * FROM tbl_school_year WHERE school_year_status = 'active' LIMIT 1";
$activeSchoolYearResult = $conn->query($activeSchoolYearQuery);
$activeSchoolYear = $activeSchoolYearResult ? $activeSchoolYearResult->fetch_assoc() : null;
$schoolYearId = $activeSchoolYear ? $activeSchoolYear['school_year_id'] : null;
$schoolYear = $activeSchoolYear ? $activeSchoolYear['school_year'] : 'No active school year';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_GET['action'])) {
        $action = mysqli_real_escape_string($conn, $_GET['action']);

        if ($action === 'edit') {
            $batchID = (int) $_POST['batch_id'];
            $batch_number = mysqli_real_escape_string($conn, $_POST['batch_number']);

            // Check for duplicate
            $duplicateCheckQuery = "SELECT * FROM tbl_batch WHERE LOWER(batch_number) = LOWER('$batch_number') AND batch_id != '$batchID'";
            $duplicateCheckResult = $conn->query($duplicateCheckQuery);
            
            if ($duplicateCheckResult->num_rows > 0) {
                $_SESSION['message'] = 'Batch number already exists!';
                $_SESSION['msg_type'] = 'error';
                header("Location: batch");
                exit();
            }

            // Update batch
            $updateBatchQuery = "UPDATE tbl_batch SET batch_number = '$batch_number' WHERE batch_id = '$batchID'";
            
            if ($conn->query($updateBatchQuery)) {
                $_SESSION['message'] = 'Batch Successfully Updated!';
                $_SESSION['msg_type'] = 'success';
            } else {
                $_SESSION['message'] = 'Error updating batch: ' . $conn->error;
                $_SESSION['msg_type'] = 'error';
            }
            
            header("Location: batch");
            exit();

        } elseif ($action === 'delete') {
            $batchID = (int) $_POST['batch_id'];
        
            // First check if batch is associated with any schedule
            $checkScheduleQuery = "SELECT COUNT(*) FROM tbl_schedule WHERE batch_id = '$batchID'";
            $scheduleCount = $conn->query($checkScheduleQuery)->fetch_row()[0];
        
            if ($scheduleCount > 0) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'This batch is associated with a schedule and cannot be deleted.'
                ]);
                exit();
            }
        
            // Check total batches
            $totalBatchesQuery = "SELECT COUNT(*) FROM tbl_batch";
            $totalBatches = $conn->query($totalBatchesQuery)->fetch_row()[0];
            
            if ($totalBatches == 1) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'You cannot delete the only batch. At least one batch is required.'
                ]);
                exit();
            }
        
            // Delete batch
            $deleteBatchQuery = "DELETE FROM tbl_batch WHERE batch_id = '$batchID'";
            
            if ($conn->query($deleteBatchQuery)) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Batch successfully deleted!'
                ]);
            } else {
                if ($conn->errno == 1451) {
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'This batch is associated with an examinee and cannot be deleted.'
                    ]);
                } else {
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Error deleting batch: ' . $conn->error
                    ]);
                }
            }
            exit();

        } elseif ($action === 'add') {
            // Check if there's an active school year
            if (!$activeSchoolYear) {
                $_SESSION['message'] = 'Cannot add batch - no active school year!';
                $_SESSION['msg_type'] = 'error';
                header("Location: batch");
                exit();
            }
        
            $batch_number = mysqli_real_escape_string($conn, $_POST['batch_number']);
            $safeSchoolYearId = mysqli_real_escape_string($conn, $schoolYearId);
        
            // Check for duplicate
            $duplicateCheckQuery = "SELECT * FROM tbl_batch 
                                   WHERE LOWER(batch_number) = LOWER('$batch_number') 
                                   AND school_year_id = '$safeSchoolYearId'";
            $duplicateCheckResult = $conn->query($duplicateCheckQuery);
            
            if ($duplicateCheckResult->num_rows > 0) {
                $_SESSION['message'] = 'Batch number already exists for the same school year!';
                $_SESSION['msg_type'] = 'error';
                header("Location: batch");
                exit();
            }
        
            // Add batch
            $addBatchQuery = "INSERT INTO tbl_batch (batch_number, school_year_id) VALUES ('$batch_number', '$safeSchoolYearId')";
            
            if ($conn->query($addBatchQuery)) {
                $_SESSION['message'] = 'Batch Successfully Added!';
                $_SESSION['msg_type'] = 'success';
            } else {
                $_SESSION['message'] = 'Error adding batch: ' . $conn->error;
                $_SESSION['msg_type'] = 'error';
            }
        
            header("Location: batch");
            exit();
        }
    }
}
?>

<!DOCTYPE html>
  <html lang="en">
  <head>
    <title>Batch Management | College Admission Test</title>
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
            <a href="javascript:void(0)" class="pc-head-link ms-0" id="sidebar-hide">
              <i class="ti ti-menu-2"></i>
            </a>
          </li>
          <li class="pc-h-item pc-sidebar-popup">
            <ahref="javascript:void(0)" class="pc-head-link ms-0" id="mobile-collapse">
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
    </div>
  </header>
    <div class="pc-container">
      <div class="pc-content">
        <div class="page-header">
    <div class="page-block">
      <div class="row align-items-center">
        <div class="col-md-12">
          <div class="page-header-title">
            <h5 class="m-b-10">Batch</h5>
          </div>
          <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php" style="text-decoration: none;">Home</a></li>
            <li class="breadcrumb-item"><a href="javascript: void(0)" style="text-decoration: none;">Exam Management</a></li>
            <li class="breadcrumb-item" aria-current="page">Batch</li>
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
      <h5 class="mb-0">Manage Batch</h5>
      <?php if ($activeSchoolYear): ?>
          <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addBatchModal">
              <i class="fas fa-plus-circle me-1"></i> Add New Batch
          </button>
      <?php else: ?>
          <button class="btn btn-secondary" disabled title="No active school year">
              <i class="fas fa-plus-circle me-1"></i> Add New Batch
          </button>
      <?php endif; ?>
  </div>

              <div class="card-body">
              <table id="batchTable" class="table table-bordered table-striped">
          <thead>
            <tr>
              <th class='text-center'>Batch Number</th>
              <th class='text-center'>School Year</th>
              <th class='text-center'>Action</th>
            </tr>
          </thead>
          <tbody>
      <?php
      if ($result->num_rows > 0) {
          while ($row = $result->fetch_assoc()) {
              echo "<tr>";
              echo "<td class='text-center'>" . $row["batch_number"] . "</td>";
              echo "<td class='text-center'>" . $row["school_year"] . "</td>";
              echo "<td  class='text-center' style='text-align: right;'> 
                    <button type='button' class='btn btn-warning editBtn' data-bs-toggle='modal' data-bs-target='#editBatchModal'
      data-id='" . $row["batch_id"] . "' data-batch_number='" . $row["batch_number"] . "'>
      <i class='fas fa-edit'></i> Edit
  </button>

  <button type='button' class='btn btn-danger delete' data-bs-toggle='modal' data-bs-target='#deleteBatchModal'
      data-id='" . $row["batch_id"] . "'>
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

    <!-- Add Batch Modal -->
    <div class="modal fade" id="addBatchModal" tabindex="-1" aria-labelledby="addBatchModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
        <form method="post" action="batch?action=add">
            <div class="modal-header">
              <h5 class="modal-title" id="addBatchModalLabel">Add Batch</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">School Year</label>
              <input type="text" id="school_year" class="form-control" value="<?php echo $schoolYear; ?>" readonly>
            </div>
          <div class="mb-3">
      <label class="form-label">Batch Number</label>
      <input type="number" id="batch_number" class="form-control" name="batch_number" 
            required min="1" step="1" oninput="validateNumber(this)">
      <div class="invalid-feedback">Please enter a valid positive whole number</div>
  </div>
    </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Add Batch Number</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Edit Batch Modal -->
    <div class="modal fade" id="editBatchModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
        <form action="batch?action=edit" method="POST">
    <input type="hidden" name="action" value="edit">
    <input type="hidden" name="batch_id" id="edit_batch_id">
    <div class="modal-header">
      <h5 class="modal-title">Edit Batch</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
    <div class="modal-body">
    <div class="mb-3">
      <label class="form-label">Active School Year</label>
      <input type="text" class="form-control" id="edit_school_year" value="<?php echo $schoolYear; ?>" readonly />
      </div>
    <div class="mb-3">
      <label class="form-label">Batch Number</label>
      <input type="number" class="form-control" id="edit_batch_number" name="batch_number" 
            required min="1" step="1" oninput="validateNumber(this)">
      <div class="invalid-feedback">Please enter a valid positive whole number</div>
  </div>
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
      </div>
    </div>

    <script>
      $(document).ready(function () {
        var table = $('.table').DataTable({
          scrollCollapse: true, 
          paging: true, 
          fixedHeader: true,  
          lengthChange: true, 
          info: true, 
          ordering: true,  
          lengthMenu: [5, 10, 25, 50]
        });
      });

      $(document).ready(function () {
        $('[data-toggle="tooltip"]').tooltip();

          <?php if (isset($_SESSION['message'])): ?>
            Swal.fire({
              title: "<?php echo ($_SESSION['msg_type'] == 'success') ? 'Success!' : 'Oops!' ?>",
              text: "<?php echo $_SESSION['message']; ?>",
              icon: "<?php echo $_SESSION['msg_type']; ?>",
              confirmButtonText: "OK"
            });
          <?php unset($_SESSION['message']); ?>
          <?php endif; ?>

          $(document).ready(function () {
            $(document).on('click', '.delete', function () {
              var batchID = $(this).data('id'); 

              Swal.fire({
                title: "Are you sure?",
                text: "You won't be able to revert this!",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Yes, delete it!"
              }).then((result) => {
                if (result.isConfirmed) {
                  $.ajax({
                    url: 'batch?action=delete',
                    type: 'POST',
                    data: { batch_id: batchID },
                    dataType: 'json',
                    success: function (response) {
                    if (response.status === 'success') {
                      Swal.fire(
                        "Deleted!",
                        response.message,
                        "success"
                      ).then(() => {
                      location.reload(); 
                    });
                  } else if (response.status === 'partial') {
                      Swal.fire(
                  "Partial Success",
                  response.message,
                  "warning"
              ).then(() => {
                  location.reload(); 
              });
          } else {
              Swal.fire(
                  "Error!",
                  response.message,
                  "error"
              );
          }
      },
      error: function (jqXHR, textStatus, errorThrown) {
          console.error("AJAX Error: ", textStatus, errorThrown);
          Swal.fire(
              "Error!",
              "There was a problem deleting the batch.",
              "error"
          );
      }
  });

                          }
                      });
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
  });
  $('#editBatchModal').on('show.bs.modal', function (event) {
      var button = $(event.relatedTarget); 
      var batchId = button.data('id');
      var batchNumber = button.data('batch_number');

      var modal = $(this);
      modal.find('#edit_batch_id').val(batchId);
      modal.find('#edit_batch_number').val(batchNumber);
  });

      
  });
        // Update the validateNumber function
  function validateNumber(input) {
      // Remove any non-digit characters
      input.value = input.value.replace(/[^0-9]/g, '');
      
      // Ensure it's a positive whole number
      if (input.value.trim() === "" || parseInt(input.value) <= 0) {
          input.setCustomValidity("Please enter a valid positive whole number.");
      } else {
          input.setCustomValidity("");
      }
  }

  // Add this to prevent form submission with invalid numbers
  $('form').on('submit', function(event) {
      let isValid = true;
      
      $(this).find('input[oninput="validateNumber(this)"]').each(function() {
          if (this.value.trim() === "" || parseInt(this.value) <= 0) {
              isValid = false;
              $(this).addClass('is-invalid');
          } else {
              $(this).removeClass('is-invalid');
          }
      });
      
      if (!isValid) {
          event.preventDefault();
          Swal.fire({
              icon: 'error',
              title: 'Invalid Input',
              text: 'Please enter valid positive whole numbers only.',
              confirmButtonText: 'Okay'
          });
      }
  });
  </script>

    <script src="script.js"></script>

  </body>
</html>