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


    // Define upload directory and allowed image extensions
    $uploadDir = 'uploads/';
    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    // Function to display choices (text or image)
    function displayChoice($choice) {
        global $uploadDir, $imageExtensions;
        $extension = strtolower(pathinfo($choice, PATHINFO_EXTENSION));

        if (in_array($extension, $imageExtensions) && file_exists($uploadDir . $choice)) {
            return "<img src='{$uploadDir}{$choice}' alt='Choice Image' style='width: 100px; height: auto;'>";
        } else {
            $formattedChoice = formatMathExpressions(htmlspecialchars($choice));
            return "<span class='mathjax' data-math='{$formattedChoice}'>{$formattedChoice}</span>";
        }
    }

    // Function to handle choice uploads
        function handleChoiceUpload($fileInput, $textInput, $existingChoice) {
        global $uploadDir, $imageExtensions;

        if (isset($_FILES[$fileInput]) && $_FILES[$fileInput]['error'] === UPLOAD_ERR_OK) {
            $fileName = basename($_FILES[$fileInput]['name']);
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if (in_array($fileExtension, $imageExtensions)) {
                $targetPath = $uploadDir . $fileName;
                if (move_uploaded_file($_FILES[$fileInput]['tmp_name'], $targetPath)) {
                    return $fileName;
                }
            }
        }

        // Change this line to explicitly check for null/empty string instead of empty()
        if ($textInput !== null && $textInput !== '') {
            return $textInput;
        }

        return $existingChoice;
    }

    // Function to format math expressions using MathJax
    function formatMathExpressions($text) {
        $text = preg_replace_callback('/√\(\s*\(\s*([^\/]+)\s*\/\s*([^)]+)\s*\)\s*\)/', function ($matches) {
            $numerator = preg_replace('/(\w+)\^(\d+)/', '$1^{\2}', $matches[1]);
            $denominator = preg_replace('/(\w+)\^(\d+)/', '$1^{\2}', $matches[2]);
            return '\\(\\sqrt{\\frac{' . $numerator . '}{' . $denominator . '}}\\)';
        }, $text);

        $text = preg_replace_callback('/(?<!\d)(\d+|\w+)\s*\/\s*(\d+|\w+)(?!\d)/', function ($matches) {
            return '\\(\\frac{' . $matches[1] . '}{' . $matches[2] . '}\\)';
        }, $text);

        $text = preg_replace('/(\w+)\^(\d+)/', '\\($1^{$2}\\)', $text);
        $text = preg_replace('/√(\w+)/', '\\(\\sqrt{$1}\\)', $text);
        $text = preg_replace('/sqrt\((.*?)\)/', '\\(\\sqrt{$1}\\)', $text);

        return $text;
    }

    // Handle duplicate questions
    if (!empty($_GET['status']) && $_GET['status'] == 'duplicate' && !empty($_SESSION['duplicate_questions'])) {
        $duplicateQuestions = $_SESSION['duplicate_questions'];
        unset($_SESSION['duplicate_questions']);

        $message = "The following questions already exist and were not imported:\n";
        foreach ($duplicateQuestions as $dupQuestion) {
            $message .= "- " . htmlspecialchars($dupQuestion) . "\n";
        }
    }

    // Fetch questions
    $sql = "SELECT 
            q.question_id, 
            s.subject_name, 
            q.question, 
            q.question_image, 
            q.correct_answer, 
            GROUP_CONCAT(c.choices ORDER BY c.choice_number SEPARATOR '<br>') AS all_choices
        FROM tbl_questions q
        JOIN tbl_subject s ON q.subject_id = s.subject_id
        JOIN tbl_choices c ON q.question_id = c.question_id
        GROUP BY q.question_id";
    $result = mysqli_query($conn, $sql);

    // Function to get correct answer text
    function getCorrectAnswerText($row) {
        global $uploadDir, $imageExtensions;

        $correctChoiceKey = 'choice' . $row['correct_answer'];
        $correctChoice = $row[$correctChoiceKey] ?? 'Not Specified';

        if (!$correctChoice) {
            return 'Not Specified';
        }

        $extension = strtolower(pathinfo($correctChoice, PATHINFO_EXTENSION));

        if (in_array($extension, $imageExtensions)) {
            return "<img src='{$uploadDir}{$correctChoice}' alt='Correct Answer' style='max-width: 100px; height: auto; display: block;'>";
        }

        $formattedAnswer = formatMathExpressions($correctChoice);
        return "<span class='mathjax' data-math='{$formattedAnswer}'>{$formattedAnswer}</span>";
    }

    // Handle status messages
    $statusType = '';
    $statusMsg = '';
    if (!empty($_GET['status'])) {
        switch ($_GET['status']) {
            case 'succ':
                $statusType = 'alert-success';
                $statusMsg = 'Question data has been imported successfully.';
                break;
            case 'err':
                $statusType = 'alert-danger';
                $statusMsg = 'Something went wrong, please try again.';
                break;
            case 'invalid_file':
                $statusType = 'alert-danger';
                $statusMsg = 'Please upload a valid Excel file.';
                break;
        }
    }

    // Handle add question POST request
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_GET['action']) && $_GET['action'] == 'add') {
        $subject_id = mysqli_real_escape_string($conn, $_POST['subject_id']);
        $question = mysqli_real_escape_string($conn, $_POST['question']);
        $correct_answer = mysqli_real_escape_string($conn, $_POST['correct_answer']);
        
        // Handle question image upload
        $question_image = null;
        if (isset($_FILES['question_image']) && $_FILES['question_image']['error'] === UPLOAD_ERR_OK) {
            $question_image = mysqli_real_escape_string($conn, $_FILES['question_image']['name']);
            $target_path = $uploadDir . basename($question_image);
            move_uploaded_file($_FILES['question_image']['tmp_name'], $target_path);
        }
        
        // Insert question
        $sql = "INSERT INTO tbl_questions (subject_id, question, question_image, correct_answer) 
                VALUES ('$subject_id', '$question', " . ($question_image ? "'$question_image'" : "NULL") . ", '$correct_answer')";
        
        if (mysqli_query($conn, $sql)) {
            $question_id = mysqli_insert_id($conn);
            
            // Insert choices
            for ($i = 1; $i <= 8; $i++) {
                $choice_text = '';
                $choice_key = 'choice' . $i;
                
                // Handle choice text or image
                if (isset($_POST[$choice_key]) && $_POST[$choice_key] !== '') {
                    $choice_text = mysqli_real_escape_string($conn, $_POST[$choice_key]);
                } elseif (isset($_FILES[$choice_key . '_image']) && $_FILES[$choice_key . '_image']['error'] === UPLOAD_ERR_OK) {
                    $choice_text = mysqli_real_escape_string($conn, $_FILES[$choice_key . '_image']['name']);
                    $target_path = $uploadDir . basename($choice_text);
                    move_uploaded_file($_FILES[$choice_key . '_image']['tmp_name'], $target_path);
                }
                
                // Change this to check for !== '' instead of !empty()
                if ($choice_text !== '') {
                    $sql = "INSERT INTO tbl_choices (question_id, choice_number, choices) 
                            VALUES ('$question_id', '$i', '$choice_text')";
                    mysqli_query($conn, $sql);
                }
            }
            
            $_SESSION['message'] = 'Question added successfully!';
            $_SESSION['msg_type'] = 'success';
        } else {
            $_SESSION['message'] = 'Error adding question: ' . mysqli_error($conn);
            $_SESSION['msg_type'] = 'error';
        }
        
        header("Location: questions");
        exit();
    }

    // Handle POST requests
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (isset($_GET['action']) && $_GET['action'] == 'edit') {
            $questionID = (int) $_POST['question_id'];
            $newQuestion = mysqli_real_escape_string($conn, $_POST['question']);
            $correctAnswer = mysqli_real_escape_string($conn, $_POST['correct_answer']);

            // Update question image if provided
            if (isset($_FILES['replace_image']) && $_FILES['replace_image']['error'] == UPLOAD_ERR_OK) {
                $imageName = mysqli_real_escape_string($conn, $_FILES['replace_image']['name']);
                $targetPath = $uploadDir . $imageName;
                move_uploaded_file($_FILES['replace_image']['tmp_name'], $targetPath);

                $sql = "UPDATE tbl_questions SET question_image = '$imageName' WHERE question_id = '$questionID'";
                mysqli_query($conn, $sql);
            }

            // Update the question
            $sql = "UPDATE tbl_questions SET question = '$newQuestion', correct_answer = '$correctAnswer' WHERE question_id = '$questionID'";
            mysqli_query($conn, $sql);

            // Clear old choices
            $sql = "DELETE FROM tbl_choices WHERE question_id = '$questionID'";
            mysqli_query($conn, $sql);

            // Re-insert updated choices
            for ($i = 1; $i <= 8; $i++) {
                $choiceText = isset($_POST["choice$i"]) ? $_POST["choice$i"] : '';
                
                // Change this to check for !== '' instead of empty()
                if ($choiceText !== '') {
                    $choiceText = mysqli_real_escape_string($conn, $choiceText);
                }

                if (isset($_FILES["choice{$i}_image"]) && $_FILES["choice{$i}_image"]["error"] === UPLOAD_ERR_OK) {
                    $fileName = mysqli_real_escape_string($conn, $_FILES["choice{$i}_image"]["name"]);
                    $targetPath = $uploadDir . $fileName;

                    if (move_uploaded_file($_FILES["choice{$i}_image"]["tmp_name"], $targetPath)) {
                        $choiceText = $fileName;
                    }
                }

                // Change this to check for !== '' instead of !empty()
                if ($choiceText !== '') {
                    $sql = "INSERT INTO tbl_choices (question_id, choice_number, choices) VALUES ('$questionID', '$i', '$choiceText')";
                    mysqli_query($conn, $sql);
                }
            }

            $_SESSION['message'] = 'Question Successfully Updated!';
            $_SESSION['msg_type'] = 'success';
            header("Location: questions.php");
            exit();
        } elseif ($_GET['action'] == 'delete') {
            $questionID = (int) $_POST['question_id'];

            // First check if question is used in any exams
            $checkSql = "SELECT COUNT(*) AS count FROM tbl_exam_questions WHERE question_id = '$questionID'";
            $checkResult = mysqli_query($conn, $checkSql);
            $row = mysqli_fetch_assoc($checkResult);
            
            if ($row['count'] > 0) {
                // Question is used in exams, can't delete
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Cannot delete question because it is being used in exams.'
                ]);
                exit();
            }

            // If not used in exams, proceed with deletion
            $sql = "DELETE FROM tbl_choices WHERE question_id = '$questionID'";
            mysqli_query($conn, $sql);

            // Delete the question
            $sql = "DELETE FROM tbl_questions WHERE question_id = '$questionID'";
            if (mysqli_query($conn, $sql)) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Question and its associated choices were successfully deleted!'
                ]);
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Error deleting question: ' . mysqli_error($conn)
                ]);
            }
            exit();
        }
    }

    $subjectQuery = "SELECT * FROM tbl_subject";
    $subjectResult = mysqli_query($conn, $subjectQuery);

    $subject = [];

    // Fetch rows if available
    if (mysqli_num_rows($subjectResult) > 0) {
        while ($row = mysqli_fetch_assoc($subjectResult)) {
            $subject[] = $row;
        }
    }
?>

<!DOCTYPE html>
    <html lang="en">
    <head>
        <title>Question Management| College Admission Test</title>
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
                        <a class="pc-head-link dropdown-toggle arrow-none me-0"
                            data-bs-toggle="dropdown"
                            href="#"
                            role="button"
                            aria-haspopup="false"
                            data-bs-auto-close="outside"
                            aria-expanded="false"
                        >
                            <img src="../assets/images/user/avatar-2.jpg" alt="user-image" class="user-avtar">
                            <span style="white-space: normal; word-break: break-word;"><?php echo htmlspecialchars($admin_name); ?></span>
                        </a>
                        <div class="dropdown-menu dropdown-user-profile dropdown-menu-end pc-h-dropdown">
                            <div class="dropdown-header">
                                <div class="d-flex mb-1 align-items-center">
                                    <div class="flex-shrink-0">
                                        <img src="../assets/images/user/avatar-2.jpg" alt="user-image" class="user-avtar wid-35">
                                    </div>
                                    <div class="flex-grow-1 ms-3" style="min-width: 0;">
                                        <h6 class="mb-1 mb-0" style="white-space: normal; word-break: break-word;"><?php echo htmlspecialchars($admin_name); ?></h6>
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
                                <h5 class="m-b-10">Questions</h5>
                            </div>
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php" style="text-decoration: none;">Home</a></li>
                                <li class="breadcrumb-item"><a href="javascript: void(0)" style="text-decoration: none;">Exam Management</a></li>
                                <li class="breadcrumb-item" aria-current="page">Questions</li>
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
                        <h5 class="mb-0">Manage Questions</h5>
                        <div>
                            <!-- Add Questions Button -->
                           <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addQuestionModal">
              <i class="fas fa-plus-circle me-1"></i> Add New Question
          </button>
                            <!-- Import Button -->
                            <a href="javascript:void(0);" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#importModal">
                                <i class="fa-solid fa-upload"></i><span> Import Questions</span>
                            </a>
                        </div>
                    </div>

                <!-- Display status message -->
                <?php if(!empty($statusMsg)){ ?>
                    <div class="col-xs-12 p-3">
                        <div class="alert <?php echo $statusType; ?>"><?php echo htmlspecialchars($statusMsg); ?></div>
                    </div>
                <?php } ?>

                <!-- Questions Table -->
                <div class="card-body">
                    <table id="questionTable" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Question</th>
                                <th>Image</th> 
                                <th>Choices</th>
                                <th>Correct Answer</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
                                    <td><?php echo formatMathExpressions(htmlspecialchars_decode($row['question'])); ?></td>
                                    <td><?php if (!empty($row['question_image'])): ?>
                                        <img src="uploads/<?php echo htmlspecialchars($row['question_image']); ?>" 
                                            alt="Question Image" 
                                            style="max-width: 100%; max-height: 200px; width: auto; height: auto; object-fit: contain;">
                                    <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="choice-container">
                                            <?php
                                                $choices = explode('<br>', $row['all_choices']);
                                                $imageCount = 0;
                                                foreach ($choices as $choice) {
                                                    if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $choice)) {
                                                        echo "<div class='choice-item'><img src='{$uploadDir}" . htmlspecialchars($choice) . "' 
                                                            alt='Choice Image' 
                                                            style='max-width: 100px; max-height: 100px; width: auto; height: auto; object-fit: contain;'></div>";                                                        $imageCount++;
                                                                                                            } else {
                                                        $formattedChoice = formatMathExpressions($choice);
                                                        echo "<div class='choice-item'><span class='mathjax' data-math='" . htmlspecialchars($formattedChoice) . "'>{$formattedChoice}</span></div>";            
                                                    }
                                                    if ($imageCount % 2 === 0) echo "<div class='clearfix'></div>";
                                                }
                                            ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                            $correctAnswerIndex = (int)$row['correct_answer']; 
                                            $choicesArray = explode('<br>', $row['all_choices']); 

                                            if (isset($choicesArray[$correctAnswerIndex - 1])) {
                                                $correctAnswer = $choicesArray[$correctAnswerIndex - 1];

                                                // If image
                                                if (preg_match('/\.(jpg|jpeg|png|gif)$/i', $correctAnswer)) {
                                                    echo "<img src='uploads/" . htmlspecialchars($correctAnswer) . "' 
                                                        alt='Correct Answer' 
                                                        style='max-width: 100%; max-height: 200px; width: auto; height: auto; object-fit: contain; display: block;'>";                                                } else {
                                                   $formattedAnswer = formatMathExpressions($correctAnswer);
                                                    echo "<span class='mathjax' data-math='" . htmlspecialchars($formattedAnswer) . "'>{$formattedAnswer}</span>";
                                                }
                                            } else {
                                                echo 'Not Specified';
                                            }
                                        ?>
                                    </td>
                                    <td class="action-column">
                                        <div style="display: flex; justify-content: center; gap: 5px;">
                                            <!-- Edit Button -->
                                            <button type="button" class="btn btn-warning edit" data-bs-toggle="modal" data-bs-target="#editQuestionModal"
                                                data-id="<?php echo htmlspecialchars($row['question_id']); ?>"
                                                data-subject_name="<?php echo htmlspecialchars($row['subject_name']); ?>"
                                                data-question="<?php echo htmlspecialchars($row['question']); ?>"
                                                data-correct_answer="<?php echo htmlspecialchars($row['correct_answer']); ?>"
                                                data-image="<?php echo !empty($row['question_image']) ? 'uploads/' . htmlspecialchars($row['question_image']) : ''; ?>">
                                                <i class='fas fa-edit'></i>Edit
                                            </button>

                                            <!-- Delete Button -->
                                            <button type="button" class="btn btn-danger delete" data-toggle="modal" data-target='#deleteQuestionModal'
                                                data-id="<?php echo htmlspecialchars($row['question_id']); ?>">
                                                <i class='fas fa-trash-alt'></i> Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>    
            </div>
        </div>

                    <!-- Add Question Modal -->
            <div class="modal fade" id="addQuestionModal" tabindex="-1" aria-labelledby="addQuestionModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addQuestionModalLabel">Add New Question</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form action="questions?action=add" method="POST" enctype="multipart/form-data">
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label for="add_subject_id" class="form-label">Subject</label>
                                        <select class="form-select" id="add_subject_id" name="subject_id" required>
                                            <option value="">Select Subject</option>
                                            <?php foreach ($subject as $sub): ?>
                                                <option value="<?php echo htmlspecialchars($sub['subject_id']); ?>">
                                                    <?php echo htmlspecialchars($sub['subject_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-12 mb-3">
                                        <label for="add_question" class="form-label">Question</label>
                                        <textarea class="form-control" id="add_question" name="question" rows="3" required></textarea>
                                    </div>
                                    
                                    <div class="col-md-12 mb-3">
                                        <label for="add_question_image" class="form-label">Question Image (Optional)</label>
                                        <input type="file" class="form-control" id="add_question_image" name="question_image" accept="image/*">
                                        <div id="questionImagePreview" class="mt-2"></div>
                                    </div>
                                    
                                    <div class="col-md-12">
                                        <h6>Choices</h6>
                                        <?php for ($i = 1; $i <= 8; $i++): ?>
                                            <div class="row mb-3">
                                                <div class="col-md-8">
                                                    <label for="add_choice<?php echo $i; ?>" class="form-label">Choice <?php echo $i; ?></label>
                                                    <input type="text" class="form-control" id="add_choice<?php echo $i; ?>" name="choice<?php echo $i; ?>">
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="add_choice<?php echo $i; ?>_image" class="form-label">Or Upload Image</label>
                                                    <input type="file" class="form-control" id="add_choice<?php echo $i; ?>_image" name="choice<?php echo $i; ?>_image" accept="image/*">
                                                    <div id="choice<?php echo $i; ?>Preview" class="mt-2"></div>
                                                </div>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                    
                                    <div class="col-md-12 mb-3">
                                        <label for="add_correct_answer" class="form-label">Correct Answer</label>
                                        <select class="form-select" id="add_correct_answer" name="correct_answer" required>
                                            <option value="">Select Correct Answer</option>
                                            <?php for ($i = 1; $i <= 8; $i++): ?>
                                                <option value="<?php echo $i; ?>">Choice <?php echo $i; ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Add Question</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

                            <!-- Import Modal -->
                            <div id="importModal" class="modal fade" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Import Questions from Excel</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <form action="importQuestion" method="POST" enctype="multipart/form-data">
                                                    <label for="file" class="form-label">Upload Excel File</label>
                                                    <input type="file" name="file" class="form-control" accept=".xls, .xlsx" required>
                        
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary" name="importQuestion"><i class="fa-solid fa-file-import"></i> Import Questions</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Edit Modal -->
                            <div id="editQuestionModal" class="modal fade" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="post" action="questions?action=edit" enctype="multipart/form-data">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Question</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" id="edit_question_id" name="question_id" />
                                                <input type="hidden" id="edit_subject_id" name="subject_id" />

                                                <div class="form-group">
                                                    <label for="edit_subject_name">Subject:</label>
                                                    <input type="text" id="edit_subject_name" name="subject_name" class="form-control" disabled />
                                                </div>

                                                <div class="form-group">
                                                    <label for="edit_question">Question</label>
                                                    <textarea class="form-control" id="edit_question" name="question" rows="3" required></textarea>
                                                </div>

                                                <div id="image_container" class="form-group" style="display: none;">
                                                    <label for="current_image">Image:</label>
                                                    <div id="current_image_container">
                                                        <img id="current_image" src="" alt="Question Image" style="max-width: 100%;" />
                                                        <input type="file" id="replace_image" name="replace_image" accept="image/*" style="display: none;" />
                                                    </div>
                                                </div>

                                                <!-- Dynamic Choices Container -->
                                                <div id="choices_container" class="form-group"></div>

                                                <div class="form-group">
                                                    <label for="edit_correct_answer">Correct Answer</label>
                                                    <input type="text" class="form-control" id="edit_correct_answer" name="correct_answer" required />
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
            function formToggle(id) {
                var form = document.getElementById(id);
                form.style.display = (form.style.display === "none") ? "block" : "none";
            }

            let isEditing = false;

            function displayChoice(choice, previewElementId, inputElementId) {
            const uploadDir = 'uploads/';
            const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            const extension = choice.split('.').pop().toLowerCase();
            const previewElement = $('#' + previewElementId);
            const inputElement = $('#' + inputElementId);

            if (imageExtensions.includes(extension)) {
                previewElement.html(`
                    <img src="${uploadDir + choice}" 
                    alt="Choice Image" 
                    style="max-width: 100%; height: auto; cursor: ${isEditing ? 'pointer' : 'default'};" 
                    ${isEditing ? `onclick="triggerFileInput('${inputElementId}')"` : ''}>
                `);
                    inputElement.hide();
            } else {
                previewElement.empty();
                inputElement.val(choice).show();
            }

                MathJax.typesetPromise();
            }
                    
            // Update the preview functions to use contain
            function previewAndUpdateImage(event, previewId) {
                const file = event.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        $(`#${previewId}`).html(`
                            <img src="${e.target.result}" 
                                style="max-width: 100%; max-height: 200px; object-fit: contain;">
                        `);
                    };
                    reader.readAsDataURL(file);
                }
            }

            function isImageChoice(choice) {
                return choice.match(/\.(jpg|jpeg|png|gif|webp)$/i);
            }

            function updateChoicePreview(choice, previewId, inputId, fileInputId) {
                if (isImageChoice(choice)) {
                    $('#' + previewId).html(`<img src="uploads/${choice}" style="max-width: 100px; cursor: pointer;" onclick="$('#${fileInputId}').click();">`);
                    $('#' + inputId).hide();
                } else {
                    $('#' + previewId).empty();
                    $('#' + inputId).val(choice).show();
                }

                $('#' + fileInputId).off('change').on('change', function(event) {
                    previewAndUpdateImage(event, previewId);
                });
            }
                    
$('.edit').click(function () { 
    isEditing = true;

    const questionID = $(this).data('id');
    const subjectName = $(this).data('subject_name');
    const questionHtml = $(this).attr('data-question'); 
    const correctAnswer = $(this).data('correct_answer');
    const questionImage = $(this).data('image');

    // Store original HTML
    $('#edit_question').data('original-html', questionHtml);
    
    // Create a temporary div to parse HTML
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = questionHtml;
    
    // Extract text content without tags for editing
    const questionText = tempDiv.textContent || tempDiv.innerText || '';
    
    // Find all strong elements and store their text and positions
    const boldElements = tempDiv.querySelectorAll('strong');
    const boldData = Array.from(boldElements).map(el => {
        return {
            text: el.textContent,
            start: el.textContent ? questionText.indexOf(el.textContent) : -1
        };
    }).filter(item => item.start !== -1);
    
    $('#edit_question_id').val(questionID);
    $('#edit_subject_name').val(subjectName);
    $('#edit_question').val(questionText);
    $('#edit_correct_answer').val(correctAnswer);

    // Store bold data
    $('#edit_question').data('bold-data', boldData);

    // Store original HTML and bold words
    $('#edit_question').data('original-html', questionHtml);

    // Rest of your edit modal code...
    $('#choices_container').empty();

    // Fetch choices dynamically
    $.ajax({
        url: 'fetch_choices',
        type: 'POST',
        data: { question_id: questionID },
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                response.choices.forEach((choice, index) => {
                    const choiceId = `edit_choice${index + 1}`;
                    const fileInputId = `choice${index + 1}_image`;
                    const previewId = `choice${index + 1}_preview`;

                    let choiceHtml = `
                        <div class="form-group">
                            <label>Choice ${index + 1}</label>
                            <input type="text" class="form-control" id="${choiceId}" name="choice${index + 1}" value="${choice.text}" />
                            <div id="${previewId}" class="mt-2"></div>
                            <input type="file" id="${fileInputId}" name="choice${index + 1}_image" accept="image/*" style="display: none;">
                        </div>
                    `;

                    $('#choices_container').append(choiceHtml);
                    updateChoicePreview(choice.text, previewId, choiceId, fileInputId);
                });
            } else {
                $('#choices_container').html('<p class="text-danger">No choices found.</p>');
            }
        }
    });

    // Handle question image
    if (questionImage) {
        $('#current_image').attr('src', questionImage).show();
        $('#image_container').show();
    } else {
        $('#current_image').hide();
        $('#image_container').hide();
    }

    MathJax.typesetPromise();
});

// When saving the edited question
$('form').on('submit', function (event) {
    const editedText = $('#edit_question').val();
    const boldData = $('#edit_question').data('bold-data');
    
    // Reconstruct the HTML with bold formatting
    let formattedText = editedText;
    
    if (boldData && boldData.length > 0) {
        // Sort by start position (descending) to avoid offset issues
        boldData.sort((a, b) => b.start - a.start);
        
        // Apply bold tags
        boldData.forEach(item => {
            const escapedText = escapeRegExp(item.text);
            const regex = new RegExp(escapedText, 'g');
            formattedText = formattedText.replace(regex, `<strong>${item.text}</strong>`);
        });
    }
    
    // Update the textarea with formatted text before submission
    $('#edit_question').val(formattedText);
});

// Helper function to escape regex special characters
function escapeRegExp(string) {
    return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}


            $(document).ready(function() {
             

                 var table = $('#questionTable').DataTable({
          scrollCollapse: true,
          paging: true,
          fixedHeader: true,
          lengthChange: true,
          info: true,
          ordering: true,
          lengthMenu: [25, 50, 100]
        });

                
            });

            document.addEventListener("DOMContentLoaded", function() {
                MathJax.typesetPromise();
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
                var questionID = $(this).data('id'); 

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
                            url: 'questions?action=delete',
                            type: 'POST',
                            data: { question_id: questionID },
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

            $('form').on('submit', function (event) {
                var correctAnswer = $('#edit_correct_answer').val();

                var originalQuestion = $('.edit').attr('data-question');
                var editedQuestion = $('#edit_question').val();

                // Ensure <strong> tags are kept when saving
                if (originalQuestion.includes('<strong>')) {
                    editedQuestion = originalQuestion.replace(/<strong>(.*?)<\/strong>/g, '<strong>$1</strong>');
                    $('#edit_question').val(editedQuestion);
                }
            });

            // Preview question image
            document.getElementById('add_question_image').addEventListener('change', function(e) {
                const preview = document.getElementById('questionImagePreview');
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
            preview.innerHTML = `
                <img src="${e.target.result}" 
                    style="max-width: 100%; max-height: 300px; object-fit: contain;">
            `;        }
                    reader.readAsDataURL(this.files[0]);
                } else {
                    preview.innerHTML = '';
                }
            });

            // Preview choice images
            <?php for ($i = 1; $i <= 8; $i++): ?>
                document.getElementById('add_choice<?php echo $i; ?>_image').addEventListener('change', function(e) {
                    const preview = document.getElementById('choice<?php echo $i; ?>Preview');
                    if (this.files && this.files[0]) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                preview.innerHTML = `
                    <img src="${e.target.result}" 
                        style="max-width: 100%; max-height: 100px; object-fit: contain;">
                `;        }
                        reader.readAsDataURL(this.files[0]);
                    } else {
                        preview.innerHTML = '';
                    }
                });
            <?php endfor; ?>
        </script>

        <script src="script.js"></script>

    </body>
</html>