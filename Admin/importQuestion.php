<?php
session_start();
include_once '../server.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\RichText\Run;

mysqli_set_charset($conn, 'utf8mb4');

if (isset($_POST['importQuestion'])) {
    $excelMimes = ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];

    if (!empty($_FILES['file']['name']) && in_array($_FILES['file']['type'], $excelMimes)) {
        if (is_uploaded_file($_FILES['file']['tmp_name'])) {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(false);
            $spreadsheet = $reader->load($_FILES['file']['tmp_name']);
            $worksheet = $spreadsheet->getActiveSheet();

            $duplicateQuestions = [];
            $insertedQuestions = 0;

            foreach ($worksheet->getRowIterator() as $rowIndex => $row) {
                if ($rowIndex === 1) continue;

                $cells = $row->getCellIterator();
                $cells->setIterateOnlyExistingCells(false);
                $rowData = [];
                foreach ($cells as $cell) $rowData[] = $cell;

                if (!isset($rowData[0])) continue;

                $subject_name = $rowData[0]->getValue();

                if (!empty($subject_name)) {
                    $stmt = mysqli_prepare($conn, "SELECT subject_id FROM tbl_subject WHERE subject_name = ?");
                    mysqli_stmt_bind_param($stmt, "s", $subject_name);
                    mysqli_stmt_execute($stmt);
                    $subjectResult = mysqli_stmt_get_result($stmt);
                    $subject = mysqli_fetch_assoc($subjectResult);

                    if ($subject) {
                        $subject_id = $subject['subject_id'];

                        $question = isset($rowData[1]) ? handleRichText($rowData[1]) : '';
                        $question_image = isset($rowData[2]) ? handleImageUpload($rowData[2]->getValue()) : '';

                        $choices = [];
                        for ($i = 3; $i <= 10; $i++) {
                            if (isset($rowData[$i])) {
                                $val = $rowData[$i]->getValue();
                                if ($val !== '') $choices[] = $val;
                            }
                        }

                        $correct_answer = (isset($rowData[11])) ? intval($rowData[11]->getValue()) : 0;

                        if (!empty($question) && $correct_answer && count($choices) >= 2) {
                            $stmt = mysqli_prepare($conn, "SELECT question_id, correct_answer FROM tbl_questions WHERE subject_id = ? AND question = ?");
                            mysqli_stmt_bind_param($stmt, "is", $subject_id, $question);
                            mysqli_stmt_execute($stmt);
                            $matchResult = mysqli_stmt_get_result($stmt);

                            $isDuplicate = false;

                            while ($match = mysqli_fetch_assoc($matchResult)) {
                                $qid = $match['question_id'];
                                $existing_correct = $match['correct_answer'];

                                $stmt = mysqli_prepare($conn, "SELECT choice_number, choices FROM tbl_choices WHERE question_id = ?");
                                mysqli_stmt_bind_param($stmt, "i", $qid);
                                mysqli_stmt_execute($stmt);
                                $choiceResult = mysqli_stmt_get_result($stmt);

                                $existingChoices = [];
                                while ($row = mysqli_fetch_assoc($choiceResult)) {
                                    $val = $row['choices'];
                                    $existingChoices[$row['choice_number']] = isImageFile($val) ? basename($val) : $val;
                                }

                                $newChoices = [];
                                foreach ($choices as $index => $choice) {
                                    $val = $choice;
                                    $newChoices[$index + 1] = isImageFile($val) ? basename($val) : $val;
                                }

                                if ($existing_correct == $correct_answer && count($existingChoices) == count($newChoices) && array_diff_assoc($existingChoices, $newChoices) === []) {
                                    $duplicateQuestions[] = $question;
                                    $isDuplicate = true;
                                    break;
                                }
                            }

                            if ($isDuplicate) continue;

                            $stmt = mysqli_prepare($conn, "INSERT INTO tbl_questions (subject_id, question, question_image, correct_answer) VALUES (?, ?, ?, ?)");
                            mysqli_stmt_bind_param($stmt, "issi", $subject_id, $question, $question_image, $correct_answer);
                            mysqli_stmt_execute($stmt);
                            $question_id = mysqli_insert_id($conn);
                            $insertedQuestions++;

                            foreach ($choices as $index => $choice) {
                                $choiceNumber = $index + 1;
                                
                                // Skip only if choice is null or an empty string (but not 0)
                                if ($choice === null || $choice === '') continue;

                                $finalChoice = isImageFile($choice) ? handleImageUpload($choice) : $choice;

                                // Skip only if final choice is null or an empty string (but not 0)
                                if ($finalChoice === null || $finalChoice === '') continue;

                                $stmt = mysqli_prepare($conn, "INSERT INTO tbl_choices (question_id, choice_number, choices) VALUES (?, ?, ?)");
                                mysqli_stmt_bind_param($stmt, "iis", $question_id, $choiceNumber, $finalChoice);
                                mysqli_stmt_execute($stmt);
                            }

                        }
                    }
                }
            }

            if (!empty($duplicateQuestions)) {
                $_SESSION['duplicate_questions'] = $duplicateQuestions;
                $_SESSION['msg_type'] = 'error';
                $_SESSION['message'] = 'Some questions were duplicates and not imported!';
            } elseif ($insertedQuestions > 0) {
                $_SESSION['msg_type'] = 'success';
                $_SESSION['message'] = 'Questions imported successfully!';
            } else {
                $_SESSION['msg_type'] = 'error';
                $_SESSION['message'] = 'No questions were imported!';
            }

            header("Location: ../admin/questions");
            exit();
        }
    }
}

function handleRichText($cell) {
    if ($cell->getValue() instanceof RichText) {
        $text = '';
        foreach ($cell->getValue()->getRichTextElements() as $element) {
            $content = $element->getText();
            $text .= ($element instanceof Run && $element->getFont() && $element->getFont()->getBold()) ? "<b>$content</b>" : $content;
        }
        return $text;
    }
    return $cell->getValue();
}

function handleImageUpload($filePath) {
    // Ensure we have a valid path
    if (empty($filePath) || !is_string($filePath)) {
        return '';
    }

    $uploadDir = 'uploads/';
    
    // Create upload directory if it doesn't exist
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            error_log("Failed to create upload directory: $uploadDir");
            return '';
        }
    }

    // Get just the filename and sanitize it
    $filename = basename($filePath);
    $filename = preg_replace('/[^a-zA-Z0-9\.\-_]/', '', $filename); // Sanitize filename
    
    // Check if this is a valid image file
    if (!isImageFile($filename)) {
        return '';
    }

    $destination = $uploadDir . $filename;
    
    // Check if file already exists in destination
    if (file_exists($destination)) {
        return $filename; 
    }

    if (file_exists($filePath)) {
        if (copy($filePath, $destination)) {
            return $filename;
        }
        error_log("Failed to copy file from $filePath to $destination");
    } else {
        return handleExcelEmbeddedImage($filePath, $uploadDir, $filename);
    }
    
    return '';
}

function handleExcelEmbeddedImage($imageData, $uploadDir, $filename) {
    try {
        // $imageData would actually be the image resource from PhpSpreadsheet
        $destination = $uploadDir . $filename;
        if (file_put_contents($destination, $imageData)) {
            return $filename;
        }
    } catch (Exception $e) {
        error_log("Error saving embedded image: " . $e->getMessage());
    }
    return '';
}

function isImageFile($val) {
    return preg_match('/\.(jpg|jpeg|png|gif|webp|bmp)$/i', $val);
}

?>
