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
        $admin_name = $row['name']; // Removed htmlspecialchars here to preserve formatting
    }

    // Fetch active school year 
    $sql = "SELECT school_year_id, school_year FROM tbl_school_year WHERE school_year_status = 'active' LIMIT 1";
    $school_year_result = mysqli_query($conn, $sql);

    if (!$school_year_result) die("Error fetching active school year: " . mysqli_error($conn));

    $school_year = '';
    $school_year_text = '';
    if ($row = mysqli_fetch_assoc($school_year_result)) {
        $school_year = (int)$row['school_year_id'];
        $school_year_text = $row['school_year']; // Removed htmlspecialchars here
    }

    // Fetch completed examinees
    $school_year_escaped = mysqli_real_escape_string($conn, $school_year);
    $sql = "SELECT DISTINCT ee.examinee_id
            FROM tbl_examinee_exam ee
            JOIN exam_schedules es ON ee.exam_schedule_id = es.exam_schedule_id
            JOIN tbl_schedule s ON es.schedule_id = s.schedule_id
            JOIN tbl_batch b ON s.batch_id = b.batch_id
            WHERE ee.status = 'completed' AND b.school_year_id = '" . $school_year_escaped . "'";
    $completed_result = mysqli_query($conn, $sql);

    if (!$completed_result) die("Error fetching completed examinees: " . mysqli_error($conn));

    $completed_examinees = [];
    while ($row = mysqli_fetch_assoc($completed_result)) {
        $completed_examinees[] = (int)$row['examinee_id'];
    }

    $total_examinees = count($completed_examinees);

    // Fetch responses
    $sql = "SELECT ea.question_id, ea.selected_choice, q.correct_answer
            FROM tbl_examinee_answers ea
            JOIN tbl_questions q ON ea.question_id = q.question_id
            JOIN tbl_examinee_exam ee ON ea.examinee_exam_id = ee.id
            JOIN tbl_examinee e ON ee.examinee_id = e.examinee_id
            JOIN tbl_batch b ON e.batch_id = b.batch_id
            WHERE b.school_year_id = '" . $school_year_escaped . "' AND ee.status = 'completed'";
    $result = mysqli_query($conn, $sql);

    if (!$result) die("Error fetching data: " . mysqli_error($conn));

    $responses = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $responses[] = [
            'question_id' => (int)$row['question_id'],
            'selected_choice' => $row['selected_choice'], // Removed htmlspecialchars
            'correct_answer' => $row['correct_answer'] // Removed htmlspecialchars
        ];
    }

    // Calculate question correctness
    $question_correct = [];
    foreach ($responses as $response) {
        $question_id = $response['question_id'];
        $selected = $response['selected_choice'];
        $correct = $response['correct_answer'];

        if (!isset($question_correct[$question_id])) {
            $question_correct[$question_id] = 0;
        }
        if ($selected == $correct) {
            $question_correct[$question_id]++;
        }
    }

    // Fetch all questions 
    $sql = "SELECT * FROM tbl_questions";
    $all_questions_result = mysqli_query($conn, $sql);

    if (!$all_questions_result) die("Error fetching questions: " . mysqli_error($conn));

    $all_questions = [];
    while ($row = mysqli_fetch_assoc($all_questions_result)) {
        $all_questions[] = [
            'question_id' => (int)$row['question_id'],
            'question' => $row['question'] // Removed htmlspecialchars to preserve formatting
        ];
    }

    // Function to safely convert exponents
    function convertExponents($text) {
        // First decode any HTML entities
        $text = html_entity_decode($text);
        
        // Convert exponents
        $text = preg_replace_callback('/([a-zA-Z0-9])\^(\d+)/', function ($matches) {
            return $matches[1] . '<sup>' . $matches[2] . '</sup>';
        }, $text);
        
        // Preserve bold tags
        $text = str_replace(['&lt;strong&gt;', '&lt;/strong&gt;'], ['<strong>', '</strong>'], $text);
        
        return $text;
    }

    // Function to determine remark color
    $remark_color = function ($percent) {
        if ($percent >= 85) {
            return ['Easy', 'background-color: #008000; color: white;'];
        } elseif ($percent >= 50) {
            return ['Average', 'background-color: #f5cc00; color: white;'];
        } else {
            return ['Difficult / Least Mastered', 'background-color: #FF0000; color: white;'];
        }
    };
?>

<!DOCTYPE html>
    <html lang="en">
    <head>
        <title>Item Analysis | College Admission Test</title>
        <link rel="icon" type="image/png" href="../images/isulogo.png" />
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">

        <?php include 'links.php'; ?>

        <link rel="stylesheet" href="../vendor/fortawesome/font-awesome/css/all.min.css">
        <link rel="stylesheet" href="../css/bootstrap.min.css" />
        <script src="../js/jquery.min.js"></script>
        <script src="../js/bootstrap.min.js"></script>
        <script src="../js/sweetalert2@11.js"></script>

        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="../css/jquery.dataTables.min.css">
        <script src="../js/jquery.dataTables.min.js"></script>

        <!-- Add MathJax support -->
        <script src="https://polyfill.io/v3/polyfill.min.js?features=es6"></script>
        <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>

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
            
            /* Add styles for math expressions */
            .math-expression {
                font-family: "Times New Roman", Times, serif;
                font-style: italic;
            }
            
            /* Preserve formatting in question cells */
            .question-col {
                white-space: pre-wrap;
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

        <!-- [ Header Topbar ] -->
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
                            <span style="white-space: normal; word-break: break-word;"><?= $admin_name ?></span>
                        </a>
                        <div class="dropdown-menu dropdown-user-profile dropdown-menu-end pc-h-dropdown">
                            <div class="dropdown-header">
                            <div class="d-flex mb-1 align-items-center">
                                <div class="flex-shrink-0">
                                <img src="../assets/images/user/avatar-2.jpg" alt="user-image" class="user-avtar wid-35">
                                </div>
                                <div class="flex-grow-1 ms-3" style="min-width: 0;">
                                <h6 class="mb-1 mb-0" style="white-space: normal; word-break: break-word;"><?= $admin_name ?></h6>
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

        <!-- [ Main Content ]  -->
        <div class="pc-container">
            <div class="pc-content">
                <div class="row">
                    <div class="main-content">
                        <div class="table-responsive">
                            <div class="table-wrapper">
                                <?php if (empty($all_questions) || empty($completed_examinees)): ?>
                                    <div style="text-align: center; font-size: 12px; margin-top: 20px;">
                                        <strong>No records found</strong>
                                    </div>
                                <?php else: ?>
                                
                                    <!-- Title Section -->
                                    <div style="text-align: center; margin-bottom: 20px;">
                                        <h2 style="font-weight: bold;">Item Analysis</h2>
                                        <p><?php echo $school_year_text; ?></p>
                                        <p class="text-center">Total Examinees: <strong><?php echo $total_examinees; ?></strong></p>
                                    </div>

                                    <!-- Export to Excel Button -->
                                    <div style="display: flex; justify-content: flex-end; margin-bottom: 5px;">
                                        <form action="export_excel.php" method="POST">
                                            <button type="submit" class="btn btn-primary">
                                            <i class="ti ti-file-export"></i> Export to Excel
                                            </button>
                                        </form>
                                    </div>

                                    <!-- Table -->
                                    <table class="table table-bordered table-striped">
                                        <thead style="background-color: #116736; color: white;">
                                            <tr>
                                                <th>Question</th>
                                                <th>No. of Correct Responses</th>
                                                <th>Percentage of Correct Responses</th>
                                                <th>Remarks</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($all_questions as $question): 
                                                $id = $question['question_id'];
                                                $text = $question['question'];
                                                $correct = $question_correct[$id] ?? 0;
                                                $percent = $total_examinees > 0 ? ($correct / $total_examinees) * 100 : 0;
                                                list($remark, $style) = $remark_color($percent);
                                            ?>
                                            <tr>
                                                <td class="question-col"><?php echo convertExponents($text); ?></td>
                                                <td class="text-center"><?php echo $correct; ?></td>
                                                <td class="text-center" ><?php echo number_format($percent, 2); ?>%</td>
                                                <td class="text-center" style="<?php echo $style; ?>"><?php echo $remark; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    
        <script>
            $(document).ready(function () {
                $('[data-toggle="tooltip"]').tooltip();

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
                
                // Process MathJax after page load
                MathJax.typesetPromise();
            });
        </script>

        <script src="script.js"></script>

    </body>
</html>