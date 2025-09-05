<?php
session_start();
include('../server.php');

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/phpmailer/phpmailer/src/Exception.php';
require '../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require '../vendor/phpmailer/phpmailer/src/SMTP.php';

$error = $success = $showResetCodeForm = $showNewPasswordForm = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Initialize statement variable
    $stmt = null;
    
    // Step 1: Request to send reset code
    if (isset($_POST['email']) && !$showNewPasswordForm) {
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';

        try {
            // Check if email exists
            $sql = "SELECT id_no FROM tbl_admin WHERE email = ?";
            $stmt = $conn->prepare($sql);

            if ($stmt === false) {
                throw new Exception('MySQL prepare error: ' . $conn->error);
            }

            $stmt->bind_param("s", $email);

            if (!$stmt->execute()) {
                throw new Exception('Execute error: ' . $stmt->error);
            }

            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                // Generate a 6-digit code
                $code = sprintf('%06d', mt_rand(1, 999999));
                
                // Close the current statement before preparing a new one
                if ($stmt) {
                    $stmt->close();
                    $stmt = null;
                }

                // Store the 6-digit code in the database with a 5-minute expiration
                $insertCode = "UPDATE tbl_admin SET reset_code = ?, code_expiration = DATE_ADD(NOW(), INTERVAL 5 MINUTE) WHERE email = ?";
                $stmt = $conn->prepare($insertCode);

                if ($stmt === false) {
                    throw new Exception('MySQL prepare error: ' . $conn->error);
                }

                $stmt->bind_param("ss", $code, $email);
                if (!$stmt->execute()) {
                    throw new Exception('Execute error: ' . $stmt->error);
                }

                // Send the 6-digit code email using PHPMailer
                $to = $email;
                $subject = "Password Reset Code";
                $message = "Your password reset code is: $code. The code will expire in 5 minutes.";

                if (sendPasswordResetEmail($to, $subject, $message)) {
                    $success = "A password reset code has been sent to your email.";
                    $showResetCodeForm = true;
                } else {
                    $error = "Failed to send reset code. Please try again.";
                }
            } else {
                $error = "No account found with that email.";
            }
        } catch (Exception $e) {
            $error = "An error occurred: " . $e->getMessage();
        } finally {
            if ($stmt) {
                $stmt->close();
            }
        }
    }

    // Step 2: Validate reset code
    if (isset($_POST['reset_code']) && !$showNewPasswordForm) {
        $reset_code = $_POST['reset_code'];
        $stmt = null;

        try {
            // Check if reset code exists and has not expired
            $sql = "SELECT reset_code, code_expiration FROM tbl_admin WHERE reset_code = ?";
            $stmt = $conn->prepare($sql);
            
            if ($stmt === false) {
                throw new Exception('MySQL prepare error: ' . $conn->error);
            }

            $stmt->bind_param("s", $reset_code);
            
            if (!$stmt->execute()) {
                throw new Exception('Execute error: ' . $stmt->error);
            }

            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                $stmt->bind_result($stored_code, $expiration);
                $stmt->fetch();

                // Check if the reset code has expired
                if (new DateTime() > new DateTime($expiration)) {
                    $error = "The reset code has expired.";
                    $showResetCodeForm = true;
                } else {
                    $success = "Reset code is valid. Please enter your new password.";
                    $showResetCodeForm = false;
                    $showNewPasswordForm = true;
                }
            } else {
                $error = "Invalid reset code.";
                $showResetCodeForm = true;
            }
        } catch (Exception $e) {
            $error = "An error occurred: " . $e->getMessage();
        } finally {
            if ($stmt) {
                $stmt->close();
            }
        }
    }

    // Step 3: Update password
    if (isset($_POST['new_password']) && isset($_POST['confirm_password']) && isset($_POST['reset_code'])) {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        $reset_code = $_POST['reset_code'];
        $stmt = null;

        try {
            // Check if passwords match
            if ($new_password !== $confirm_password) {
                $error = "Passwords do not match.";
            } else {
                // Hash the new password
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

                // Update the password in the database
                $updatePassword = "UPDATE tbl_admin SET password = ?, reset_code = NULL, code_expiration = NULL WHERE reset_code = ?";
                $stmt = $conn->prepare($updatePassword);
                
                if ($stmt === false) {
                    throw new Exception('MySQL prepare error: ' . $conn->error);
                }

                $stmt->bind_param("ss", $hashed_password, $reset_code);
                
                if ($stmt->execute()) {
                    $success = "Your password has been successfully updated.";
                    header("Location: index.php");
                    exit();
                } else {
                    throw new Exception('Failed to update password.');
                }
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        } finally {
            if ($stmt) {
                $stmt->close();
            }
        }
    }
}

// Function to send password reset email
function sendPasswordResetEmail($to, $subject, $message)
{
    $mail = new PHPMailer(true);
    try {
        // SMTP settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'cadmission02@gmail.com';
        $mail->Password = 'ywqv bavx fhsc joqf';  // Use a proper SMTP password or app-specific password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Sender and recipient
        $mail->setFrom('cadmission02@gmail.com', 'College Admission');
        $mail->addAddress($to);

        // Email content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;

        return $mail->send();
    } catch (Exception $e) {
        error_log("Failed to send email to $to: " . $e->getMessage());
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../images/isulogo.png" />
        <title>Forgot Password | College Admission Test</title>
           <?php include 'links.php'; ?>

    <style>
        :root {
            --primary-color: #116736;
            --primary-dark: #0e5a2b;
            --primary-light: #e8f5e9;
            --error-color: #e53935;
            --success-color: #43a047;
            --text-color: #333;
            --light-gray: #f5f5f5;
            --border-radius: 8px;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: var(--text-color);
        }

        .auth-container {
            width: 100%;
            max-width: 420px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            transition: var(--transition);
        }

        .auth-header {
            background-color: var(--primary-color);
            color: white;
            padding: 1.5rem;
            text-align: center;
        }

        .auth-header h2 {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .auth-header p {
            font-size: 0.875rem;
            opacity: 0.9;
        }

        .auth-body {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            font-size: 0.875rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-size: 0.9375rem;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(17, 103, 54, 0.2);
        }

        .btn {
            display: inline-block;
            width: 100%;
            padding: 0.75rem;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn:active {
            transform: translateY(0);
        }

        .auth-footer {
            text-align: center;
            padding: 1rem 2rem 2rem;
            font-size: 0.875rem;
        }

        .auth-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }

        .auth-footer a:hover {
            text-decoration: underline;
        }

        .alert {
            padding: 0.75rem 1rem;
            margin-bottom: 1.25rem;
            border-radius: var(--border-radius);
            font-size: 0.875rem;
        }

        .alert-error {
            background-color: #ffebee;
            color: var(--error-color);
            border-left: 4px solid var(--error-color);
        }

        .alert-success {
            background-color: #e8f5e9;
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }

        .input-icon {
            position: relative;
        }

        .input-icon i {
            position: absolute;
            top: 50%;
            right: 1rem;
            transform: translateY(-50%);
            color: #999;
            cursor: pointer;
        }

        .logo {
            width: 80px;
            margin-bottom: 1rem;
        }

        @media (max-width: 480px) {
            .auth-container {
                margin: 1rem;
                max-width: 100%;
            }
            
            .auth-body {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <img src="../images/isulogo.png" alt="ISU Logo" class="logo">
            <h2>Forgot Password</h2>
            <p>Enter your email to receive a reset code</p>
        </div>

        <div class="auth-body">
            <!-- Step 1: Request to send reset code -->
            <?php if (!$showResetCodeForm && !$showNewPasswordForm) : ?>
                <form method="POST">
                    <?php if (!empty($error)) : ?>
                        <div class="alert alert-error"><?= $error ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success)) : ?>
                        <div class="alert alert-success"><?= $success ?></div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" name="email" id="email" class="form-control" placeholder="Enter your email" required>
                    </div>

                    <button type="submit" class="btn">Send Reset Code</button>
                </form>
            <?php endif; ?>

            <!-- Step 2: Enter reset code -->
            <?php if ($showResetCodeForm) : ?>
                <form method="POST">
                    <?php if (!empty($error)) : ?>
                        <div class="alert alert-error"><?= $error ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success)) : ?>
                        <div class="alert alert-success"><?= $success ?></div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="reset_code">Reset Code</label>
                        <input type="text" name="reset_code" id="reset_code" class="form-control" placeholder="Enter 6-digit code" required>
                    </div>

                    <button type="submit" class="btn">Verify Code</button>
                </form>
            <?php endif; ?>

            <!-- Step 3: Enter new password -->
            <?php if ($showNewPasswordForm) : ?>
                <form method="POST">
                    <?php if (!empty($error)) : ?>
                        <div class="alert alert-error"><?= $error ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success)) : ?>
                        <div class="alert alert-success"><?= $success ?></div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <div class="input-icon">
                            <input type="password" name="new_password" id="new_password" class="form-control" placeholder="Enter new password" required>
                            <i class="fas fa-eye" id="toggleNewPassword"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <div class="input-icon">
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Confirm new password" required>
                            <i class="fas fa-eye" id="toggleConfirmPassword"></i>
                        </div>
                    </div>

                    <input type="hidden" name="reset_code" value="<?= $_POST['reset_code'] ?>">

                    <button type="submit" class="btn">Reset Password</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="auth-footer">
            <a href="index.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
        </div>
    </div>

    <script>
        // Toggle password visibility
        document.getElementById('toggleNewPassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('new_password');
            const icon = this;
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('confirm_password');
            const icon = this;
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        // Show SweetAlert for success messages
        <?php if (!empty($success) && !$showNewPasswordForm) : ?>
            Swal.fire({
                title: 'Success!',
                text: '<?= $success ?>',
                icon: 'success',
                confirmButtonColor: '#116736'
            });
        <?php endif; ?>

        // Show SweetAlert for error messages
        <?php if (!empty($error)) : ?>
            Swal.fire({
                title: 'Error!',
                text: '<?= $error ?>',
                icon: 'error',
                confirmButtonColor: '#116736'
            });
        <?php endif; ?>
    </script>
</body>
</html>