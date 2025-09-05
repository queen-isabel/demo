<?php
session_start();
include_once '../server.php';

if (!isset($_SESSION['id_no'])) {
    header("Location: index");
    exit();
}

// Fetch admin name
$admin_name = '';
if (isset($_SESSION['id_no'])) {
    $admin_id_no = mysqli_real_escape_string($conn, $_SESSION['id_no']);
    $adminSql = "SELECT name FROM tbl_admin WHERE id_no = '$admin_id_no'";
    $adminResult = mysqli_query($conn, $adminSql);

    if ($adminResult && mysqli_num_rows($adminResult) > 0) {
        $adminRow = mysqli_fetch_assoc($adminResult);
        $admin_name = htmlspecialchars($adminRow['name'], ENT_QUOTES, 'UTF-8');
    }
}

$query = "SELECT 
            tbl_examinee.*, 
            tbl_strand.strand_name, 
            tbl_batch.batch_number, 
            tbl_examinee.enrollment_status,  
            MAX(tbl_examinee_exam.status) AS examinee_exam_status,
            MAX(exam_schedules.exam_status) AS exam_status, 
            MAX(first_course.course_name) AS first_course_name, 
            MAX(second_course.course_name) AS second_course_name
          FROM tbl_examinee
          LEFT JOIN tbl_strand ON tbl_examinee.strand_id = tbl_strand.strand_id
          LEFT JOIN tbl_batch ON tbl_examinee.batch_id = tbl_batch.batch_id
          LEFT JOIN tbl_examinee_exam ON tbl_examinee.examinee_id = tbl_examinee_exam.examinee_id
          LEFT JOIN tbl_course AS first_course ON tbl_examinee.first_preference = first_course.course_id
          LEFT JOIN tbl_course AS second_course ON tbl_examinee.second_preference = second_course.course_id
          LEFT JOIN examinee_schedules ON tbl_examinee.examinee_id = examinee_schedules.examinee_id
          LEFT JOIN exam_schedules ON examinee_schedules.exam_schedule_id = exam_schedules.exam_schedule_id
          LEFT JOIN tbl_schedule ON tbl_schedule.schedule_id = exam_schedules.schedule_id
          INNER JOIN tbl_school_year ON tbl_school_year.school_year_id = tbl_batch.school_year_id
          WHERE tbl_school_year.school_year_status = 'active'
          AND tbl_examinee.estatus = 'approved'  
          GROUP BY tbl_examinee.examinee_id";

$result = mysqli_query($conn, $query);

// Check query success
if (!$result) {
    die("Query failed: " . mysqli_error($conn));
}

// Helper function to fetch records
function fetchRecords($conn, $sql) {
    $result = mysqli_query($conn, $sql);
    $records = [];

    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $records[] = $row;
        }
    }

    return $records;
}

// Fetch additional records
$courseSql = "SELECT * FROM tbl_course";
$examineeSql = "SELECT tbl_examinee.*, tbl_strand.strand_name FROM tbl_examinee INNER JOIN tbl_strand ON tbl_examinee.strand_id = tbl_strand.strand_id";
$strandSql = "SELECT * FROM tbl_strand";

$courses = fetchRecords($conn, $courseSql);
$examinees = fetchRecords($conn, $examineeSql);
$strand = fetchRecords($conn, $strandSql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<title>Qualified Examinee | College Admission Test</title>
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
  <div class="pc-container">
    <div class="pc-content">
      <div class="page-header">
  <div class="page-block">
    <div class="row align-items-center">
      <div class="col-md-12">
        <div class="page-header-title">
          <h5 class="m-b-10">Qualified Examinee</h5>
        </div>
        <ul class="breadcrumb">
          <li class="breadcrumb-item"><a href="dashboard.php" style="text-decoration: none;">Home</a></li>
          <li class="breadcrumb-item"><a href="javascript: void(0)" style="text-decoration: none;">Examinee</a></li>
          <li class="breadcrumb-item" aria-current="page">Qualified Examinee</li>
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
          <h5 class="mb-0">Qualified Examinee</h5>
  <div class="d-flex gap-2">
    <a href="javascript:void(0);" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#importModal">
    <i class="fa-solid fa-upload"></i><span> Import Examinees</span>
    </a>

    <a href="javascript:void(0);" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#exportModal">
        <i class="fa-solid fa-file-export"></i> Export Options
    </a>
</div>

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
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if ($result->num_rows > 0) {
                                        while ($row = $result->fetch_assoc()) {
                                            echo "<tr>";
                                            echo "<td>" . $row["unique_code"] . "</td>";                                  
        
                                            echo "<td>" . $row["lname"] . "</td>";
                                            echo "<td>" . $row["fname"] . "</td>";
                                            echo "<td>" . $row["mname"] . "</td>";
                                            echo "<td>" . $row["strand_name"] . "</td>";
                                            echo "<td>" . $row["first_course_name"] . "</td>";
                                            echo "<td>" . $row["second_course_name"] . "</td>";
                                            echo "<td>" . $row["enrollment_status"] . "</td>";
                                            echo "<td>" . $row["lschool_attended"] . "</td>";
                                            echo "<td>" . $row["lrn"] . "</td>";
                                            echo "<td>" . $row["school_address"] . "</td>";
                                            echo "<td>" . $row["home_address"] . "</td>";
                                            echo "<td>" . $row["sex"] . "</td>";
                                            echo "<td>" . $row["zipcode"] . "</td>";
                                            echo "<td>" . $row["birthday"] . "</td>";
                                            echo "<td>" . $row["email"] . "</td>";
                                            echo "<td>" . $row["batch_number"] . "</td>";
                                            echo "<td>" . $row["contact_number"] . "</td>";
                                            echo "<td>" . $row["examinee_status"] . "</td>";

                                             // Check if the examinee has completed the exam
                                             $isExamCompleted = $row["examinee_exam_status"] === 'completed';
                                             $moveButtonDisabled = $isExamCompleted ? 'disabled' : '';
                                             
                                             echo "<td>
                                             <button class='btn btn-primary move' data-bs-toggle='modal' data-bs-target='#moveExamineeModal' data-id='" . $row["examinee_id"] . "' $moveButtonDisabled>
                                               <i class='ti ti-arrows-move me-1'></i>Move
                                             </button>
                                           </td>";
                                     
echo "</tr>";


                                        }
                                    } 
                                    ?>
                                    

                                </tbody>


                            </table>
                        </div>

<!-- Export Options Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportModalLabel">Export Options</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <!-- View Examinee per Proctor Button -->
                <a href="view_examinees" class="btn btn-info w-100 mb-2 text-white">
                    <i class="fa-solid fa-user-group"></i> View Examinee per Proctor
                </a>
                
                <!-- Existing Export Options -->
                <a href="export_examinee" class="btn btn-warning w-100 mb-2">
                    <i class="fa-solid fa-clipboard-list"></i> Export Examinee with Code
                </a>

                <a href="export_final_list" class="btn btn-success w-100 mb-2">
                    <i class="fa-solid fa-file-excel"></i> Export Final List of Examinees
                </a>

                <a href="export_attendance" class="btn btn-primary w-100">
                    <i class="fa-solid fa-clipboard-list"></i> Export Attendance
                </a>
            </div>
        </div>
    </div>
</div>

                        <!-- Modal for Importing Excel File -->
<div id="importModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import Examinees from Excel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">

<div class="d-grid gap-2">
                <form action="importData" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <input type="file" name="file" class="form-control" accept=".xls, .xlsx" required>
                    </div>
                   
                <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" name="importSubmit"><i class="fa-solid fa-file-import"></i> Import Examinee</button>
                    </div>
            </form>
            </div>
        </div>
    </div>
</div>
                                </div>

                     
<!--Move Examinee Modal-->
                        <div id="moveExamineeModal" class="modal fade">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="moveExamineeForm" method="post" action="move_examinee">
                <div class="modal-header">
                    <h4 class="modal-title">Move Examinee to New Batch</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                <div class="modal-body">
                    <input type="hidden" id="move_examinee_id" name="examinee_id">
                    <div class="form-group">
                        <label for="new_batch_id">Select New Batch</label>
                        <select class="form-control" id="new_batch_id" name="batch_id" required>
                            <!-- Options will be populated dynamically -->
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <input type="submit" class="btn btn-primary" value="Move Examinee" />
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
        function formToggle(ID) {
            var element = document.getElementById(ID);
            if (element.style.display === "none") {
                element.style.display = "block";
            } else {
                element.style.display = "none";
            }
        }

$('.edit').click(function () {
    var examineeID = $(this).data('id');
    var batch_number = $(this).data('batch_number'); 
    var lname = $(this).data('lname');
    var fname = $(this).data('fname');
    var mname = $(this).data('mname');
    var first_preference = $(this).data('first_preference');
    var second_preference = $(this).data('second_preference');
    var enrollment_status = $(this).data('enrollment_status');
    var strand_id = $(this).data('strand_id');
    var lschool_attended = $(this).data('lschool_attended');
    var lrn = $(this).data('lrn');
    var school_address = $(this).data('school_address');
    var home_address = $(this).data('home_address');
    var sex = $(this).data('sex');
    var birthday = $(this).data('birthday');
    var email = $(this).data('email');
    var contact_number = $(this).data('contact_number');

    console.log("Batch Number:", batch_number);
    console.log("Examinee ID:", examineeID);

    // Populate modal fields
    $('#edit_examinee_id').val(examineeID);
    $('#edit_batch_number').val(batch_number); 
    $('#edit_lname').val(lname);
    $('#edit_fname').val(fname);
    $('#edit_mname').val(mname);
    $('#edit_first_course_preference').val(first_preference);
    $('#edit_second_preference').val(second_preference);
    $('#edit_enrollment_status').val(enrollment_status);
    $('#edit_strand_id').val(strand_id);
    $('#edit_lschool_attended').val(lschool_attended);
    $('#edit_lrn').val(lrn);
    $('#edit_school_address').val(school_address);
    $('#edit_home_address').val(home_address);
    $('#edit_sex').val(sex);
    $('#edit_birthday').val(birthday);
    $('#edit_email').val(email);
    $('#edit_contact_number').val(contact_number);
});


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
    
    
    while ($batch = $batches->fetch_assoc()) {
        echo "<option value='" . $batch['batch_number'] . "'>Batch " . $batch['batch_number'] . "</option>";
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
                    text: "<?php echo $_SESSION['message']; ?>",
                    icon: "<?php echo $_SESSION['msg_type']; ?>",
                    confirmButtonText: "OK"
                });
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>

            $(document).ready(function () {
               $('#moveExamineeModal').on('show.bs.modal', function (event) {
    var button = $(event.relatedTarget);
    var examineeID = button.data('id');
    
    $('#move_examinee_id').val(examineeID);
    $('#new_batch_id').html('<option value="" disabled selected>Loading batches...</option>');
    
    // Debugging: Log before AJAX call
    console.log("Attempting to fetch batches...");
    
    $.ajax({
        url: 'move_examinee', // Make sure path is correct
        type: 'POST',
        dataType: 'json',
        success: function(response) {
            console.log("AJAX Success Response:", response);
            
            if (response.status === 'success') {
                var options = '<option value="" disabled selected>Select Batch</option>';
                $.each(response.batches, function(index, batch) {
                    options += `<option value="${batch.batch_id}">Batch ${batch.batch_number}</option>`;
                });
                $('#new_batch_id').html(options);
            } else {
                $('#new_batch_id').html('<option value="" disabled selected>Error: ' + response.message + '</option>');
            }
        },
        error: function(xhr, status, error) {
            console.error("AJAX Error:", status, error, xhr.responseText);
            $('#new_batch_id').html('<option value="" disabled selected>Error loading batches</option>');
        }
    });
});


    // AJAX to move examinee to new batch
    $('#moveExamineeForm').on('submit', function (e) {
    e.preventDefault();
    var examineeID = $('#move_examinee_id').val();
    var newBatchID = $('#new_batch_id').val();

    console.log("Examinee ID: ", examineeID);
    console.log("New Batch ID: ", newBatchID);

    if (!examineeID || !newBatchID) {
        alert("Examinee ID or Batch ID is missing!");
        return;
    }

    $.ajax({
        url: 'move_examinee',
        type: 'POST',
        data: {
            examinee_id: examineeID,
            batch_id: newBatchID
        },
        dataType: 'json',
        success: function (response) {
            console.log("Response from server: ", response);
            if (response.status === 'success') {
                Swal.fire('Success', response.message, 'success').then(() => location.reload());
            } else {
                Swal.fire('Error', response.message, 'error');
            }
        },
        error: function (xhr, status, error) {
            console.error("AJAX Error: ", xhr.responseText);
            Swal.fire('Error', 'An error occurred while moving the examinee.', 'error');
        }
    });
});

});


$(document).ready(function () {
        $('.view-image').on('click', function () {
            var examineeName = $(this).data('name');
            var imageURL = $(this).data('image'); 
            console.log("Examinee Name: " + examineeName + ", Image URL: " + imageURL);

            $('#examineeName').text(examineeName + "'s Image");

            $('#examineeImage').attr('src', imageURL);
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
    </script>

<script src="script.js"></script>

</body>
</html>