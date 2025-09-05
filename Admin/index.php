<?php
    include('../server.php');
    session_start();

    // Handle session timeout
    if (isset($_GET['timeout'])) {
        $timeout = mysqli_real_escape_string($conn, $_GET['timeout']);
        if ($timeout === '1') {
            session_unset();
            session_destroy();
            setcookie(session_name(), '', time() - 3600, '/');
        }
    }

    $error = '';
    $email_error = false;
    $password_error = false;
    $email = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Sanitize and safely retrieve form inputs
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $password = mysqli_real_escape_string($conn, $_POST['password']);

        if (!empty($email) && !empty($password)) {
            $sql = "SELECT id_no, name, email, password FROM tbl_admin WHERE email = '$email'";
            $result = mysqli_query($conn, $sql);

            if ($result && mysqli_num_rows($result) > 0) {
                $row = mysqli_fetch_assoc($result);
                $hashed_password = $row['password'];

                if (password_verify($password, $hashed_password)) {
                    $_SESSION['id_no'] = $row['id_no'];
                    $_SESSION['name'] = $row['name'];
                    $_SESSION['email'] = $row['email'];
                    $_SESSION['LAST_ACTIVITY'] = time();

                    header("Location: dashboard");
                    exit();
                } else {
                    $password_error = true;
                }
            } else {
                $email_error = true;
            }
        } else {
            if (empty($email)) $email_error = true;
            if (empty($password)) $password_error = true;
        }
    }
?>

<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login | College Admission Test</title>
  <link rel="icon" type="image/png" href="../images/isulogo.png" />

        <?php include 'links.php'; ?>
    
        <style>
            :root {
                --isu-green: #116736;
                --isu-dark-green: #0e5a2b;
                --isu-light: #f8f9fa;
            }
            
            body {
                font-family: 'Poppins', sans-serif;
                background-color: var(--isu-light);
                height: 100vh;
                overflow: hidden;
            }
            
            .login-container {
                height: 100vh;
            }
            
            .branding-section {
                background: linear-gradient(135deg, var(--isu-green), var(--isu-dark-green));
                color: white;
                position: relative;
                overflow: hidden;
            }
            
            .branding-section::before {
                content: "";
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="2"><path d="M0 0 L100 100 M100 0 L0 100"/></svg>');
                background-size: 100px 100px;
                opacity: 0.3;
            }
            
            .branding-content {
                position: relative;
                z-index: 1;
            }
            
            .university-logo {
                width: 120px;
                height: 120px;
                object-fit: contain;
                margin-bottom: 1.5rem;
                filter: drop-shadow(0 4px 8px rgba(0,0,0,0.2));
            }
            
            .login-section {
                display: flex;
                align-items: center;
                justify-content: center;
                background-color: white;
            }
            
            .login-card {
                width: 100%;
                max-width: 400px;
                border: none;
                border-radius: 12px;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
                padding: 2.5rem;
            }
            
            .login-title {
                color: var(--isu-green);
                font-weight: 700;
                margin-bottom: 2rem;
                text-align: center;
                position: relative;
            }
            
            .login-title::after {
                content: "";
                display: block;
                width: 60px;
                height: 3px;
                background-color: var(--isu-green);
                margin: 0.5rem auto 0;
            }
            
            .form-control {
                padding: 0.75rem 1rem;
                border-radius: 8px;
                border: 1px solid #e0e0e0;
            }
            
            .form-control:focus {
                border-color: var(--isu-green);
                box-shadow: 0 0 0 0.25rem rgba(17, 103, 54, 0.25);
            }
            
            .input-group-text {
                background-color: transparent;
                border-right: none;
            }
            
            .form-floating>label {
                padding: 0.75rem 1rem;
            }
            
            .btn-isu {
                background-color: var(--isu-green);
                color: white;
                padding: 0.75rem;
                border-radius: 8px;
                font-weight: 600;
                transition: all 0.3s ease;
            }
            
            .btn-isu:hover {
                background-color: var(--isu-dark-green);
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            }
            
            .forgot-password {
                color: var(--isu-green);
                text-decoration: none;
                font-size: 0.9rem;
                transition: color 0.3s ease;
            }
            
            .forgot-password:hover {
                color: var(--isu-dark-green);
                text-decoration: underline;
            }
            
            .password-toggle {
                cursor: pointer;
                color: #6c757d;
                transition: color 0.3s ease;
            }
            
            .password-toggle:hover {
                color: var(--isu-green);
            }
            
            .error-message {
                font-size: 0.85rem;
                color: #dc3545;
            }
            
            .system-name {
                position: absolute;
                top: 20px;
                left: 20px;
                color: white;
                font-weight: 600;
                font-size: 1.2rem;
                z-index: 10;
            }

            input[type="password"]::-ms-reveal,
            input[type="password"]::-ms-clear {
                display: none;
            }

            input[type="password"]::-webkit-contacts-auto-fill-button,
            input[type="password"]::-webkit-credentials-auto-fill-button {
                visibility: hidden;
                display: none !important;
                pointer-events: none;
                position: absolute;
                right: 0;
            }
            
            @media (max-width: 992px) {
                .login-container {
                    overflow-y: auto;
                }
                
                .branding-section {
                    padding: 3rem 0;
                    height: auto;
                }
                
                .login-section {
                    padding: 3rem 0;
                }
                
                .system-name {
                    position: static;
                    text-align: center;
                    margin-bottom: 1.5rem;
                }
            }
        </style>
    </head>
    <body>
        <div class="container-fluid login-container p-0">
            <div class="row g-0 h-100">
                <!-- Left Branding Section -->
                <div class="col-lg-6 d-flex align-items-center justify-content-center branding-section">
                
                    <div class="branding-content text-center px-4">
                        <img src="../images/isulogo.png" alt="ISU Logo" class="university-logo">
                        <h1 class="display-5 fw-bold mb-3">Isabela State University</h1>
                        <p class="lead mb-4">
                            <i class="bi bi-geo-alt-fill me-2"></i>City of Ilagan
                        </p>
                        <div class="d-flex justify-content-center gap-3">
                            <div class="bg-white bg-opacity-25 rounded-pill p-2">
                                <i class="bi bi-mortarboard-fill text-white"></i>
                            </div>
                            <div class="bg-white bg-opacity-25 rounded-pill p-2">
                                <i class="bi bi-book-fill text-white"></i>
                            </div>
                            <div class="bg-white bg-opacity-25 rounded-pill p-2">
                                <i class="bi bi-award-fill text-white"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Login Section -->
                <div class="col-lg-6 login-section">
                    <div class="login-card bg-white">
                        <h2 class="login-title">
                            <i class="bi bi-shield-lock me-2"></i>Admin Login
                        </h2>
                        
                        <form method="POST" action="index">
                            <!-- Email Input -->
                            <div class="mb-4">
                                <div class="form-floating">
                                    <input type="email" class="form-control <?= $email_error ? 'is-invalid' : '' ?>" 
                                        id="email" name="email" placeholder="Email" 
                                        value="<?= htmlspecialchars(stripslashes($email)) ?>" required autocomplete="off">
                                    <label for="email">
                                        <i class="bi bi-envelope-fill me-2 text-secondary"></i>Email Address
                                    </label>
                                </div>
                                <?php if ($email_error) : ?>
                                    <div class="error-message mt-2">
                                        <i class="bi bi-exclamation-circle-fill me-1"></i>Invalid email address
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Password Input -->
                            <div class="mb-3">
                                <div class="form-floating">
                                    <input type="password" class="form-control <?= $password_error ? 'is-invalid' : '' ?>" 
                                        id="password" name="password" placeholder="Password" required>
                                    <label for="password">
                                        Password
                                    </label>
                                    <span class="password-toggle position-absolute end-0 top-50 translate-middle-y me-3" 
                                        id="togglePassword">
                                        <i class="bi bi-eye-fill" id="toggleIcon"></i>
                                    </span>
                                </div>
                                <?php if ($password_error) : ?>
                                    <div class="error-message mt-2">
                                        <i class="bi bi-exclamation-circle-fill me-1"></i>Invalid password
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Forgot Password -->
                            <div class="d-flex justify-content-end align-items-center mb-4">
                                <a href="forgot_password.php" class="forgot-password small">
                                    <i class="bi bi-question-circle-fill me-1"></i>Forgot Password?
                                </a>
                            </div>
                            
                            <!-- Submit Button -->
                            <button type="submit" class="btn btn-isu w-100 mb-3">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Login
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <script>
            // Toggle password visibility
            const togglePassword = document.querySelector('#togglePassword');
            const password = document.querySelector('#password');
            const toggleIcon = document.querySelector('#toggleIcon');
            
            togglePassword.addEventListener('click', function() {
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                toggleIcon.classList.toggle('bi-eye-fill');
                toggleIcon.classList.toggle('bi-eye-slash-fill');
            });
            
            // Prevent back button after logout
            window.onload = function() {
                history.pushState(null, null, location.href);
                history.back();
                history.forward();
                window.onpopstate = function() {
                    history.go(1);
                };
            };
        </script>
    </body>
</html>