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

    $sql = "SELECT name FROM tbl_admin WHERE id_no = '$admin_id_no'";
    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $admin_name = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
    }

    // Fetch subjects 
    $sql = "SELECT subject_id, subject_name FROM tbl_subject";
    $result = mysqli_query($conn, $sql);

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_GET['action'])) {
        // Get action parameter
        $action = $_GET['action'];
        
        try {
            if ($action == 'edit') {
                // Get input
                $subjectID = (int) $_POST['subject_id'];
                $subjectName = mysqli_real_escape_string($conn, $_POST['subject_name']);

                // Validate length
                if (strlen($subjectName) > 25) {
                    $_SESSION['message'] = 'Subject name must be 25 characters or less';
                    $_SESSION['msg_type'] = 'error';
                    header("Location: subject");
                    exit();
                }

                // Check for duplicates 
                $checkSql = "SELECT subject_id FROM tbl_subject WHERE LOWER(subject_name) = LOWER('$subjectName') AND subject_id != '$subjectID'";
                $checkResult = mysqli_query($conn, $checkSql);
                
                if (mysqli_num_rows($checkResult) > 0) {
                    $_SESSION['message'] = 'Subject name already exists!';
                    $_SESSION['msg_type'] = 'error';
                    header("Location: subject");
                    exit();
                }

                // Update subject 
                $updateSql = "UPDATE tbl_subject SET subject_name = '$subjectName' WHERE subject_id = '$subjectID'";
                
                if (mysqli_query($conn, $updateSql)) {
                    $_SESSION['message'] = 'Subject Successfully Updated!';
                    $_SESSION['msg_type'] = 'success';
                } else {
                    $_SESSION['message'] = 'Error updating subject: ' . mysqli_error($conn);
                    $_SESSION['msg_type'] = 'error';
                }
                header("Location: subject");
                exit();

            } elseif ($action == 'delete') {
                // Get input
                $subjectID = (int) $_POST['subject_id'];

                // Check if the subject is associated with any questions 
                $checkSql = "SELECT COUNT(*) AS count FROM tbl_questions WHERE subject_id = '$subjectID'";
                $checkResult = mysqli_query($conn, $checkSql);
                $row = mysqli_fetch_assoc($checkResult);
                $count = $row['count'];

                if ($count > 0) {
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'This subject is associated with questions and cannot be deleted.'
                    ]);
                    exit();
                }

                // Delete subject 
                $deleteSql = "DELETE FROM tbl_subject WHERE subject_id = '$subjectID'";
                
                if (mysqli_query($conn, $deleteSql)) {
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Subject successfully deleted!'
                    ]);
                } else {
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Error deleting subject: ' . mysqli_error($conn)
                    ]);
                }
                exit();

            } elseif ($action == 'add') {
                // Get input
                $subjectName = mysqli_real_escape_string($conn, $_POST['subject_name']);

                // Validate length
                if (strlen($subjectName) > 25) {
                    $_SESSION['message'] = 'Subject name must be 25 characters or less';
                    $_SESSION['msg_type'] = 'error';
                    header("Location: subject");
                    exit();
                }

                // Check for duplicates 
                $checkSql = "SELECT subject_id FROM tbl_subject WHERE LOWER(subject_name) = LOWER('$subjectName')";
                $checkResult = mysqli_query($conn, $checkSql);
                
                if (mysqli_num_rows($checkResult) > 0) {
                    $_SESSION['message'] = 'Subject name already exists!';
                    $_SESSION['msg_type'] = 'error';
                    header("Location: subject");
                    exit();
                }
                
                // Insert new subject 
                $insertSql = "INSERT INTO tbl_subject (subject_name) VALUES ('$subjectName')";
                
                if (mysqli_query($conn, $insertSql)) {
                    $_SESSION['message'] = 'Subject Successfully Added!';
                    $_SESSION['msg_type'] = 'success';
                } else {
                    $_SESSION['message'] = 'Error adding subject: ' . mysqli_error($conn);
                    $_SESSION['msg_type'] = 'error';
                }
                header("Location: subject");
                exit();
            }
            
        } catch (Exception $e) {
            $_SESSION['message'] = 'A system error occurred';
            $_SESSION['msg_type'] = 'error';
            header("Location: subject");
            exit();
        }
    }
?>

<!DOCTYPE html>
    <html lang="en">
    <head>
        <title>Subject Management | College Admission Test</title>
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

            <!-- Navbar -->
            <?php include 'navbar.php'; ?>

            <!-- Profile -->
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
                                        <h5 class="m-b-10">Subject</h5>
                                    </div>
                                    <ul class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="dashboard.php" style="text-decoration: none;">Home</a></li>
                                        <li class="breadcrumb-item"><a href="javascript: void(0)" style="text-decoration: none;">Administration</a></li>
                                        <li class="breadcrumb-item" aria-current="page">Subject</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center" style="background-color: #053a1c; color: #fff;">
                                    <h5 class="mb-0">Manage Subject</h5>
                                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                                        <i class="fas fa-plus-circle me-1"></i> Add New Subject
                                    </button>
                                </div>

                                <div class="card-body">
                                    <table id="subjectTable" class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th class="text-center">Subject Name</th>
                                                <th class="text-center">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            if ($result && mysqli_num_rows($result) > 0) {
                                                while ($row = mysqli_fetch_assoc($result)) {
                                                    echo "<tr>";
                                                    echo "<td class='text-center'>" . htmlspecialchars($row["subject_name"] , ENT_QUOTES, 'UTF-8') . "</td>";
                                                    echo "<td class='text-center' style='text-align: right;'> 
                                                        <button type='button' class='btn btn-warning editBtn' data-bs-toggle='modal' data-bs-target='#editSubjectModal'
                                                            data-id='" . (int)$row["subject_id"] . "' data-subject_name='" . htmlspecialchars($row["subject_name"] , ENT_QUOTES, 'UTF-8') . "'>
                                                            <i class='fas fa-edit'></i> Edit
                                                        </button>
                                                        <button type='button' class='btn btn-danger deleteBtn' data-bs-toggle='modal' data-bs-target='#deleteSubjectModal'
                                                            data-id='" . (int)$row["subject_id"] . "'>
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
                            
                            <!-- Add Subject Modal -->
                            <div class="modal fade" id="addSubjectModal" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form id="addSubjectForm" action="subject?action=add" method="POST">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Add Subject</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <label class="form-label">Subject Name</label>
                                                <input type="text" name="subject_name" id="subject_name" class="form-control" required
                                                    maxlength="25" 
                                                    oninput="validateSubjectName(this)">
                                                <small class="text-muted">Must be 2-25 characters</small>
                                                <div class="invalid-feedback">Please enter a valid subject name (2-25 characters)</div>  
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Add</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Edit Subject Modal -->
                            <div class="modal fade" id="editSubjectModal" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form id="editSubjectForm" action="subject?action=edit" method="POST">
                                            <input type="hidden" name="action" value="edit">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Subject</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="subject_id" id="edit_subject_id">
                                                <label class="form-label">Subject Name</label>
                                                <input type="text" name="subject_name" id="edit_subject_name" class="form-control" required
                                                    maxlength="25"
                                                    oninput="validateSubjectName(this)">
                                                <small class="text-muted">Must be 2-25 characters</small>
                                                <div class="invalid-feedback">Please enter a valid subject name (2-25 characters)</div>
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
            function validateSubjectName(input) {
                if (input.value.length < 2 || input.value.length > 60) {
                    input.classList.add('is-invalid');
                } else {
                    input.classList.remove('is-invalid');
                }
            }

            $(document).ready(function () {
                var table = $('#subjectTable').DataTable({
                    scrollCollapse: true,
                    paging: true,
                    fixedHeader: true,
                    lengthChange: true,
                    info: true,
                    ordering: true,
                    lengthMenu: [5, 10, 25, 50]
                });

                $('#subjectTable').on('click', '.editBtn', function () {
                    $('#edit_subject_id').val($(this).data('id'));
                    $('#edit_subject_name').val($(this).data('subject_name'));
                });

                $('#subjectTable').on('click', '.deleteBtn', function () {
                    const subjectID = $(this).data('id');
                    Swal.fire({
                    title: 'Are you sure?',
                    text: 'This will permanently delete the subject.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, delete it!',
                    cancelButtonText: 'Cancel'
                    }).then((result) => {
                    if (result.isConfirmed) {
                        $.post('subject?action=delete', { subject_id: subjectID }, function (response) {
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