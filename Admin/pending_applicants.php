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

$sql = "SELECT name FROM tbl_admin WHERE id_no = '" . $admin_id_no . "'";
$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    $admin_name = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
}

// Define requirements directory
$requirementsDir = '../examinee_registration/requirements/';

// Get the records including exam status
$sql = "SELECT 
    e.examinee_id, e.unique_code, e.lname, e.fname, e.mname, 
    s.strand_name, 
    b.batch_number, 
    e.enrollment_status,  
    MAX(ee.status) AS status, 
    fc.course_name AS first_course_name, 
    sc.course_name AS second_course_name, 
    r.grade11_1stsem AS grade11_1stsem,
    r.grade11_2ndsem AS grade11_2ndsem,
    r.grade12_1stsem AS grade12_1stsem,
    r.grade12_2ndsem AS grade12_2ndsem,
    r.tor AS tor,
    e.examinee_status,
    e.lschool_attended,
    e.lrn,
    e.school_address,
    e.home_address,
    e.sex,
    e.zipcode,
    e.birthday,
    e.email,
    e.contact_number,
    e.estatus
FROM tbl_examinee e
LEFT JOIN tbl_strand s ON e.strand_id = s.strand_id
LEFT JOIN tbl_batch b ON e.batch_id = b.batch_id
LEFT JOIN tbl_examinee_exam ee ON e.examinee_id = ee.examinee_id
LEFT JOIN tbl_course fc ON e.first_preference = fc.course_id
LEFT JOIN tbl_course sc ON e.second_preference = sc.course_id
LEFT JOIN tbl_requirements r ON e.examinee_id = r.examinee_id
INNER JOIN tbl_schedule sch ON sch.batch_id = e.batch_id
INNER JOIN tbl_school_year sy ON sy.school_year_id = b.school_year_id
WHERE sy.school_year_status = 'active' AND e.estatus = 'pending'
GROUP BY e.examinee_id";

$result = mysqli_query($conn, $sql);

// Check for query error
if (!$result) {
    die("Database query failed: " . mysqli_error($conn));
}

// Function to check if file exists in requirements folder
function fileExistsInRequirements($filename, $requirementsDir) {
    if (empty($filename)) return false;
    return file_exists($requirementsDir . $filename);
}

// Function to get file content from requirements folder
function getFileContent($filename, $requirementsDir) {
    if (empty($filename)) return null;
    $filepath = $requirementsDir . $filename;
    if (file_exists($filepath)) {
        return file_get_contents($filepath);
    }
    return null;
}

// Fetch records helper function
function fetchRecords($conn, $table, $condition = '') {
    $query = "SELECT * FROM " . mysqli_real_escape_string($conn, $table);
    if (!empty($condition)) {
        $query .= " WHERE " . $condition;
    }
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
$courses = fetchRecords($conn, "tbl_course");
$examinees = fetchRecords($conn, "tbl_examinee INNER JOIN tbl_strand ON tbl_examinee.strand_id = tbl_strand.strand_id");
$strand = fetchRecords($conn, "tbl_strand");
$batch = fetchRecords($conn, "tbl_batch");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<title>Pending Applicants | College Admission Test</title>
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
        
        .modal.fixed-width .modal-dialog {
            max-width: 1100px;
            width: 1100px;
            margin: auto;
        }

        .modal-body iframe {
            height: 80vh;
            width: 100%;
        }
        
        /* Add this to ensure table is responsive */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
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
          <h5 class="m-b-10">Pending Applicants</h5>
        </div>
        <ul class="breadcrumb">
          <li class="breadcrumb-item"><a href="dashboard.php" style="text-decoration: none;">Home</a></li>
          <li class="breadcrumb-item"><a href="javascript: void(0)" style="text-decoration: none;">Examinee</a></li>
          <li class="breadcrumb-item" aria-current="page">Pending Applicants</li>
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
                <h5 class="mb-0">Pending Applicants</h5>
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
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($result && mysqli_num_rows($result) > 0) {
                                while ($row = mysqli_fetch_assoc($result)) {
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row["unique_code"] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
                                    echo "<td>" . htmlspecialchars($row["lname"] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
                                    echo "<td>" . htmlspecialchars($row["fname"] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
                                    echo "<td>" . htmlspecialchars($row["mname"] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
                                    echo "<td>" . htmlspecialchars($row["strand_name"] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
                                    echo "<td>" . htmlspecialchars($row["first_course_name"] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
                                    echo "<td>" . htmlspecialchars($row["second_course_name"] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
                                    echo "<td>" . htmlspecialchars($row["enrollment_status"] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
                                    echo "<td>" . htmlspecialchars($row["lschool_attended"] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
                                    echo "<td>" . htmlspecialchars($row["lrn"] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
                                    echo "<td>" . htmlspecialchars($row["school_address"] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
                                    echo "<td>" . htmlspecialchars($row["home_address"] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
                                    echo "<td>" . htmlspecialchars($row["sex"] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
                                    echo "<td>" . htmlspecialchars($row["zipcode"] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
                                    echo "<td>" . htmlspecialchars($row["birthday"] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
                                    echo "<td>" . htmlspecialchars($row["email"] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
                                    echo "<td>" . htmlspecialchars($row["batch_number"] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
                                    echo "<td>" . htmlspecialchars($row["contact_number"] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
                                    echo "<td>" . htmlspecialchars($row["examinee_status"] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";

                                    // Check examinee status
                                    $status = $row["examinee_status"] ?? '';
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
                                    echo "<td><button class='btn btn-primary' onclick='viewFile(" . (int)($row['examinee_id'] ?? 0) . ", \"grade11_1stsem\")' " . $grade11_1stsem_disabled . ">View</button></td>";
                                    echo "<td><button class='btn btn-primary' onclick='viewFile(" . (int)($row['examinee_id'] ?? 0) . ", \"grade11_2ndsem\")' " . $grade11_2ndsem_disabled . ">View</button></td>";
                                    echo "<td><button class='btn btn-primary' onclick='viewFile(" . (int)($row['examinee_id'] ?? 0) . ", \"grade12_1stsem\")' " . $grade12_1stsem_disabled . ">View</button></td>";
                                    echo "<td><button class='btn btn-primary' onclick='viewFile(" . (int)($row['examinee_id'] ?? 0) . ", \"grade12_2ndsem\")' " . $grade12_2ndsem_disabled . ">View</button></td>";
                                    echo "<td><button class='btn btn-primary' onclick='viewFile(" . (int)($row['examinee_id'] ?? 0) . ", \"tor\")' " . $tor_disabled . ">View</button></td>";
                                    echo "<td>" . htmlspecialchars($row["estatus"] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";

                                    // Approve/Reject buttons
                                    echo "<td>
                                    <div class='d-flex gap-2'>
                                        <form method='POST' action='update_status?action=approve'>
                                            <input type='hidden' name='examinee_id' value='" . (int)($row["examinee_id"] ?? 0) . "'>
                                            <button type='submit' class='btn btn-primary' name='action' value='approve' " . (($row["status"] ?? '') === 'approved' ? 'disabled' : '') . "><i class='fa-solid fa-circle-check'></i>Approve</button>
                                        </form>
                                        <form method='POST' action='update_status'>
                                            <input type='hidden' name='examinee_id' value='" . (int)($row["examinee_id"] ?? 0) . "'>
                                            <button type='button' class='btn btn-danger reject-btn' 
                                                data-examinee-id='" . (int)($row["examinee_id"] ?? 0) . "' 
                                                data-examinee-email='" . htmlspecialchars($row["email"] ?? '', ENT_QUOTES, 'UTF-8') . "'
                                                data-examinee-status='" . htmlspecialchars($row['examinee_status'] ?? '', ENT_QUOTES, 'UTF-8') . "' 
                                                data-bs-toggle='modal' data-bs-target='#rejectModal'>
                                                <i class='fa-solid fa-circle-xmark'></i>Reject
                                            </button>
                                        </form>
                                    </div>
                                  </td>";
                            
                                    echo "</tr>";
                                }
                            } 
                            ?>
                        </tbody>
                    </table>
            </div>

            <!-- Reject Modal -->
            <div class="modal fade" id="rejectModal" tabindex="-1" role="dialog" aria-labelledby="rejectModalLabel" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <form id="rejectForm" method="POST" action="update_status">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Reject Applicant</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" name="examinee_id" id="rejectExamineeId">
                                <input type="hidden" name="action" value="reject">
                                <div class="form-group">
                                    <label for="rejectionReasons">Reason for Rejection</label>
                                    <div id="rejection-reasons"></div> 
                                </div>
                                <div class="form-group mt-3">
                                    <label for="rejectionReason">Additional Notes (optional)</label>
                                    <textarea class="form-control" name="additional_reason" id="rejectionReason" rows="3" placeholder="Write additional details if needed..."></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Reject</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        
        <!-- Modal for File Preview -->
        <div class="modal fade fixed-width" id="fileModal" tabindex="-1" aria-labelledby="fileModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="fileModalLabel">File Preview</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <iframe id="filePreview" frameborder="0" style="display: none;"></iframe>
                        <img id="imagePreview" style="display: none; max-width: 50%; max-height: 50vh; object-fit: contain; margin: auto; display: block;" />
                        <div id="noFileMessage" style="display: none; text-align: center; padding: 20px;">
                            No file available for preview
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).on('click', '.reject-btn', function () {
        const examineeId = $(this).data('examinee-id');
        const examineeStatus = $(this).data('examinee-status');
        $('#rejectExamineeId').val(examineeId);

        let reasonsHtml = '';

        // Based on examinee status, show the appropriate rejection reasons
        if (examineeStatus === 'SHS GRADUATING STUDENT') {
            reasonsHtml = `
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="reasons[]" value="No Grade 11 1st Sem Report Card" id="reason1">
                    <label class="form-check-label" for="reason1">No Grade 11 1st Sem Report Card</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="reasons[]" value="No Grade 11 2nd Sem Report Card" id="reason2">
                    <label class="form-check-label" for="reason2">No Grade 11 2nd Sem Report Card</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="reasons[]" value="No Grade 12 1st Sem Report Card" id="reason3">
                    <label class="form-check-label" for="reason3">No Grade 12 1st Sem Report Card</label>
                </div>`;
        } else if (examineeStatus === 'SHS GRADUATE') {
            reasonsHtml = `
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="reasons[]" value="No Grade 12 1st Sem Report Card" id="reason4">
                    <label class="form-check-label" for="reason4">No Grade 12 1st Sem Report Card</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="reasons[]" value="No Grade 12 2nd Sem Report Card" id="reason5">
                    <label class="form-check-label" for="reason5">No Grade 12 2nd Sem Report Card</label>
                </div>`;
        } else if (examineeStatus === 'TRANSFEREE') {
            reasonsHtml = `
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="reasons[]" value="No TOR/Certificate of Grades" id="reason6">
                    <label class="form-check-label" for="reason6">No TOR/Certificate of Grades</label>
                </div>`;
        }

        // Update the modal with the correct reasons
        $('#rejection-reasons').html(reasonsHtml);
    });

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
            $batches = $conn->query("
            SELECT DISTINCT b.batch_id, b.batch_number
            FROM tbl_batch b
            INNER JOIN tbl_school_year sy ON b.school_year_id = sy.school_year_id
            WHERE sy.school_year_status = 'active'
        ");
        
        if ($batches && $batches->num_rows > 0) {
            while ($batch = $batches->fetch_assoc()) {
                echo "<option value='" . $batch['batch_number'] . "'>Batch " . $batch['batch_number'] . "</option>";
            }
        }
            ?>
        </select>`;

        // Append the batch filter dropdown to the DataTables search container
        $('#examineeTable_filter').prepend(batchFilterHtml);

        // Filter the table based on the selected batch
        $('#batchFilter').on('change', function () {
            var batchValue = $(this).val();
            table.column(16).search(batchValue).draw();
        });
    });

    // SweetAlert for success and error messages
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
    // Hide all preview elements first
    $('#imagePreview').hide();
    $('#filePreview').hide();
    $('#noFileMessage').hide();

    // Show loading state
    $('#fileModalLabel').text(`Loading ${fileType.replace('_', ' ')}...`);
    
    // Set the file URL
    const fileUrl = `view_file.php?examinee_id=${examineeId}&file_type=${fileType}`;
    
    // Check if it's likely an image based on common image extensions
    const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
    const isImage = imageExtensions.some(ext => fileUrl.toLowerCase().includes(ext));
    
    if (isImage) {
        $('#imagePreview').attr('src', fileUrl).show();
    } else {
        $('#filePreview').attr('src', fileUrl).show();
    }
    
    $('#fileModalLabel').text(`Viewing ${fileType.replace('_', ' ')}`);
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
                    window.location.href = $(this).attr('href');
                }
            });
        });
</script>

<script>
// This script will be included in the main file to handle file viewing
$(document).ready(function() {
    // Function to handle file viewing
    window.viewFile = function(examineeId, fileType) {
        // Hide all preview elements first
        $('#imagePreview').hide();
        $('#filePreview').hide();
        $('#noFileMessage').hide();

        // Show loading state
        $('#fileModalLabel').text(`Loading ${fileType.replace('_', ' ')}...`);
        
        // Make AJAX call to check file
        $.ajax({
            url: window.location.href, // Post to same page
            type: 'POST',
            data: {
                action: 'view_file',
                examinee_id: examineeId,
                file_type: fileType
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    if (response.is_image) {
                        $('#imagePreview').attr('src', response.file_url).show();
                    } else {
                        $('#filePreview').attr('src', response.file_url).show();
                    }
                    $('#fileModalLabel').text(`Viewing ${fileType.replace('_', ' ')}`);
                } else {
                    $('#noFileMessage').show();
                    $('#fileModalLabel').text('File Not Found');
                }
            },
            error: function() {
                $('#noFileMessage').show();
                $('#fileModalLabel').text('Error Loading File');
            }
        });

        $('#fileModal').modal('show');
    };

    // Handle the file view request
    if (isset($_POST['action']) && $_POST['action'] === 'view_file') {
        $examineeId = (int)$_POST['examinee_id'];
        $fileType = $_POST['file_type'];
        
        // Whitelist allowed file types
        $allowedFiles = ['grade11_1stsem', 'grade11_2ndsem', 'grade12_1stsem', 'grade12_2ndsem', 'tor'];
        if (!in_array($fileType, $allowedFiles)) {
            die(json_encode(['success' => false, 'message' => 'Invalid file type']));
        }

        // Get the filename from database
        $query = "SELECT $fileType FROM tbl_requirements WHERE examinee_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $examineeId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if ($row && !empty($row[$fileType])) {
            $filename = $row[$fileType];
            $filepath = '../examinee_registration/requirements/' . $filename;
            
            if (file_exists($filepath)) {
                // Check if it's an image
                $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $isImage = in_array($extension, $imageExtensions);
                
                // Return the file URL and type
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'file_url' => $filepath,
                    'is_image' => $isImage
                ]);
                exit();
            }
        }
        
        // If we get here, file wasn't found
        header('Content-Type: application/json');
        echo json_encode(['success' => false]);
        exit();
    }
});
</script>
<script src="script.js"></script>
</body>
</html>