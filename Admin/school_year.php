<?php
session_start();
include('../server.php');

$admin_name = '';
if (isset($_SESSION['id_no'])) {
    $admin_id_no = mysqli_real_escape_string($conn, $_SESSION['id_no']);
    
    // Get admin name using real_escape_string
    $sql = "SELECT name FROM tbl_admin WHERE id_no = '$admin_id_no'";
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $admin_name = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
    }
}

// Get all records without pagination
$sql = "SELECT * FROM tbl_school_year ORDER BY school_year_id DESC";
$result = mysqli_query($conn, $sql);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_GET['action'])) {
    if ($_GET['action'] == 'deactivate') {
        $school_yearID = mysqli_real_escape_string($conn, $_POST['school_year_id']);
        
        // First, check if this is the only active school year
        $sql = "SELECT COUNT(*) FROM tbl_school_year WHERE school_year_status = 'active' AND school_year_id != '$school_yearID'";
        $result = mysqli_query($conn, $sql);
        $activeRow = mysqli_fetch_row($result);
        $otherActiveCount = (int)$activeRow[0];
    
        if ($otherActiveCount == 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Cannot deactivate. There must be at least one active school year.'
            ]);
            exit();
        }
    
        // Update the status to inactive
        $sql = "UPDATE tbl_school_year SET school_year_status = 'inactive' WHERE school_year_id = '$school_yearID'";
    
        if (mysqli_query($conn, $sql)) {
            echo json_encode([
                'status' => 'success',
                'message' => 'School Year marked as inactive successfully.'
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Error updating school year status: ' . htmlspecialchars(mysqli_error($conn), ENT_QUOTES, 'UTF-8')
            ]);
        }
        exit();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_GET['action'])) {
        $action = mysqli_real_escape_string($conn, $_GET['action']);
        
        if ($action == 'edit') {
            $school_yearID = mysqli_real_escape_string($conn, $_POST['school_year_id']);
            $school_year = isset($_POST['school_year']) 
                ? mysqli_real_escape_string($conn, $_POST['school_year'])
                : '';

            // Split the school year into start and end year
            $years = explode('-', $school_year);
            if (count($years) == 2 && $years[1] == $years[0] + 1) {
                // Proceed with the update
                $sql = "UPDATE tbl_school_year SET school_year = '$school_year' WHERE school_year_id = '$school_yearID'";

                if (mysqli_query($conn, $sql)) {
                    $_SESSION['message'] = 'School Year Successfully Updated!';
                    $_SESSION['msg_type'] = 'success';
                    $_SESSION['action'] = 'edit';
                } else {
                    $_SESSION['message'] = 'Error updating school year: ' . htmlspecialchars(mysqli_error($conn), ENT_QUOTES, 'UTF-8');
                    $_SESSION['msg_type'] = 'error';
                    $_SESSION['action'] = 'edit';
                }
            } else {
                $_SESSION['message'] = 'The end year must be exactly one year after the start year.';
                $_SESSION['msg_type'] = 'error';
                $_SESSION['action'] = 'edit';
            }
        } elseif ($action == 'delete') {
            $school_yearID = mysqli_real_escape_string($conn, $_POST['school_year_id']);

            // Check if there's only one school_year left
            $sql = "SELECT COUNT(*) FROM tbl_school_year";
            $result = mysqli_query($conn, $sql);
            $totalRecordsRow = mysqli_fetch_row($result);
            $totalRecords = (int)$totalRecordsRow[0];

            if ($totalRecords == 1) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'You cannot delete the only school year. At least one school year is required.'
                ]);
                exit();
            }

            // Attempt to delete the school year
            $sql = "DELETE FROM tbl_school_year WHERE school_year_id = '$school_yearID'";
            
            if (mysqli_query($conn, $sql)) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'School Year successfully deleted!'
                ]);
            } else {
                // Check if the error is due to a foreign key constraint
                if (mysqli_errno($conn) == 1451) {
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'This school year is associated with an exam and cannot be deleted.'
                    ]);
                } else {
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Error deleting school year: ' . htmlspecialchars(mysqli_error($conn), ENT_QUOTES, 'UTF-8')
                    ]);
                }
            }
            exit();  
        } elseif ($action == 'add') {
            $school_year = isset($_POST['school_year']) 
                ? mysqli_real_escape_string($conn, $_POST['school_year'])
                : '';

            // Check if the school year already exists
            $sql = "SELECT COUNT(*) FROM tbl_school_year WHERE school_year = '$school_year'";
            $result = mysqli_query($conn, $sql);
            $duplicateRow = mysqli_fetch_row($result);

            if ($duplicateRow[0] > 0) {
                // School year already exists
                $_SESSION['message'] = 'The school year already exists.';
                $_SESSION['msg_type'] = 'error';
                $_SESSION['action'] = 'add';
            } else {
                // Split the school year into start and end year
                $years = explode('-', $school_year);
                if (count($years) == 2 && $years[1] == $years[0] + 1) {
                    // Proceed with the query
                    $sql = "INSERT INTO tbl_school_year (school_year) VALUES ('$school_year')";
                    
                    if (mysqli_query($conn, $sql)) {
                        $_SESSION['message'] = 'School Year Successfully Added!';
                        $_SESSION['msg_type'] = 'success';
                        $_SESSION['action'] = 'add';
                    } else {
                        $_SESSION['message'] = 'Error adding school year: ' . htmlspecialchars(mysqli_error($conn), ENT_QUOTES, 'UTF-8');
                        $_SESSION['msg_type'] = 'error';
                        $_SESSION['action'] = 'add';
                    }
                } else {
                    $_SESSION['message'] = 'The end year must be exactly one year after the start year.';
                    $_SESSION['msg_type'] = 'error';
                    $_SESSION['action'] = 'add';
                }
            }
        }
    }
    header("Location: school_year");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <title>School Year Management | College Admission Test</title>
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

            .pc-link.active, .pc-link:hover {
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
    <div class="pc-container">
        <div class="pc-content">
        <div class="page-header">
    <div class="page-block">
        <div class="row align-items-center">
        <div class="col-md-12">
            <div class="page-header-title">
            <h5 class="m-b-10">School Year</h5>
            </div>
            <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php" style="text-decoration: none;">Home</a></li>
            <li class="breadcrumb-item"><a href="javascript: void(0)" style="text-decoration: none;">Exam Management</a></li>
            <li class="breadcrumb-item" aria-current="page">School Year</li>
            </ul>
        </div>
        </div>
    </div>
    </div>
        <!-- [ Main Content ] -->
        <div class="row">
            <div class="col-sm-12">
            <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center" style="background-color: #053a1c; color: #fff; height: 60px;">
            <h5 class="mb-0">Manage School Year</h5>
    <button class="btn btn-success " data-bs-toggle="modal" data-bs-target="#addSchoolYearModal">
        <i class="fas fa-plus-circle me-1"></i> Add New School Year
    </button>
    </div>

    <div class="card-body">
        <table id="schoolyearTable" class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th class="text-center">School Year</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($result) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <?php 
                            $isActive = $row['school_year_status'] === 'active'; 
                            $buttonClass = $isActive ? '' : 'disabled';
                            $buttonAttr = $isActive ? '' : 'disabled';
                        ?>
                        <tr>
                            <td class='text-center'><?php echo htmlspecialchars($row["school_year"], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class='text-center'>
                                <span class="badge bg-<?php echo $isActive ? 'success' : 'danger'; ?>">
                                    <?php echo ucfirst($row['school_year_status']); ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-warning btn-sm edit <?php echo htmlspecialchars($buttonClass, ENT_QUOTES, 'UTF-8'); ?>" 
                                        data-bs-toggle="modal" data-bs-target="#editSchoolYearModal"
                                        data-id="<?php echo htmlspecialchars($row["school_year_id"], ENT_QUOTES, 'UTF-8'); ?>" 
                                        data-school_year="<?php echo htmlspecialchars($row["school_year"], ENT_QUOTES, 'UTF-8'); ?>" 
                                        <?php echo htmlspecialchars($buttonAttr, ENT_QUOTES, 'UTF-8'); ?>>
                                        <i class='fas fa-edit'></i> Edit
                                </button>
                                <button type="button" class="btn btn-danger btn-sm delete <?php echo htmlspecialchars($buttonClass, ENT_QUOTES, 'UTF-8'); ?>" 
                                        data-id="<?php echo htmlspecialchars($row["school_year_id"], ENT_QUOTES, 'UTF-8'); ?>" 
                                        <?php echo htmlspecialchars($buttonAttr, ENT_QUOTES, 'UTF-8'); ?>>
                                        <i class='fas fa-trash-alt'></i> Delete
                                </button>
                                <button type="button" class="btn btn-info btn-sm deactivate <?php echo htmlspecialchars($buttonClass, ENT_QUOTES, 'UTF-8'); ?>" 
                                        data-id="<?php echo htmlspecialchars($row["school_year_id"], ENT_QUOTES, 'UTF-8'); ?>" 
                                        <?php echo htmlspecialchars($buttonAttr, ENT_QUOTES, 'UTF-8'); ?>>
                                        <i class="fa-regular fa-circle-xmark"></i> Mark as Inactive
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    </main>

    <!-- Add Modal -->
    <div id="addSchoolYearModal" class="modal fade">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="school_year?action=add" onsubmit="return validateSchoolYear()">
                    <div class="modal-header">
                        <h4 class="modal-title">Add School Year</h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="school_year">School Year</label>
                        <input type="text" class="form-control" id="school_year" name="school_year" required />
                    </div>
                </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add New School Year</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editSchoolYearModal" class="modal fade" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="school_year?action=edit">
                    <div class="modal-header">
                        <h4 class="modal-title">Edit School Year</h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                    <div class="mb-3">
                        <input type="hidden" id="edit_school_year_id" name="school_year_id" />
                        <div class="form-group">
                            <label for="school_year">School Year</label>
                            <input type="text" class="form-control" id="edit_school_year" name="school_year" required />
                        </div>
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
    </div>
            </div>
            </div>
        </div>
        </div>
    </div>

    <script src="script.js"></script>

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

        // Populate Edit Modal with data
        $('.edit').click(function () {
        var school_yearID = $(this).data('id');
        var school_year = $(this).data('school_year');
        var isActive = !$(this).hasClass('disabled');
        
        if (!isActive) {
            Swal.fire('Error', 'Cannot edit an inactive school year', 'error');
            return;
        }
        
        $('#edit_school_year_id').val(school_yearID);
        $('#edit_school_year').val(school_year);
    });
        // Populate Delete Modal with data
        $('.delete').click(function () {
            var school_yearID = $(this).data('id');
            $('#delete_school_year_id').val(school_yearID);
        });


        // SweetAlert for success and error messages
    <?php if (isset($_SESSION['message'])): ?>
        Swal.fire({
            title: "<?php echo ($_SESSION['msg_type'] === 'success') ? 'Success!' : 'Error'; ?>",
            text: "<?php echo $_SESSION['message']; ?>",
            icon: "<?php echo $_SESSION['msg_type']; ?>",
            confirmButtonText: "OK"
        }).then((result) => {
            <?php if ($_SESSION['msg_type'] === 'error'): ?>
            <?php if ($_SESSION['action'] === 'edit'): ?>
                $('#editSchoolYearModal').modal('show');
            <?php else: ?>
                $('#addSchoolYearModal').modal('show');
            <?php endif; ?>
            <?php endif; ?>
        });
        <?php unset($_SESSION['message'], $_SESSION['msg_type'], $_SESSION['action']); ?>
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

            // Handle Delete with SweetAlert
            $(document).on('click', '.delete', function () {
        var school_yearID = $(this).data('id');

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
                    url: 'school_year?action=delete',
                    type: 'POST',
                    data: { school_year_id: school_yearID },
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
                        } else {
                            Swal.fire(
                                "Oops...",
                                response.message,
                                "error"
                            );
                        }
                    },
                });
            }
        });
    });
    });
    $(document).ready(function() {
        $(".deactivate").on('click', function() {
            var schoolYearID = $(this).data('id');
            Swal.fire({
                title: 'Are you sure?',
                text: "You want to mark this school year as inactive.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, deactivate it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        type: 'POST',
                        url: 'school_year?action=deactivate',
                        data: { school_year_id: schoolYearID },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                Swal.fire('Success!', response.message, 'success').then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire('Error', response.message, 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('Error', 'Something went wrong. Please try again.', 'error');
                        }
                    });
                }
            });
        });
    });

    function validateSchoolYear() {
        var schoolYearInput = document.getElementById("school_year").value;

        // Check if the input consists of numbers only and if it follows the format "YYYY-YYYY"
        var regex = /^\d{4}-\d{4}$/;
        if (!regex.test(schoolYearInput)) {
            Swal.fire({
                title: 'Error',
                text: 'Please enter a valid school year in the format YYYY-YYYY.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return false;
        }

        var years = schoolYearInput.split('-');
        if (parseInt(years[1]) !== parseInt(years[0]) + 1) {
            Swal.fire({
                title: 'Error',
                text: 'The end year must be exactly one year after the start year.',
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return false;
        }
        return true;
    }

    </script>

    </body>
</html>