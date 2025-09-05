<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>ISU-Ilagan College Admission Portal</title>
    <link rel="icon" type="image/png" href="images/isulogo.png" />
    <link href="assets/css/plugins/bootstrap.min.css" rel="stylesheet" />

    <style>
      :root {
        --primary-color: #116736;
        --secondary-color: #f8f9fa;
        --accent-color: #ff6b35;
      }

      body {
        background-color: var(--secondary-color);
        font-family: "Poppins", sans-serif;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
      }

      .portal-header {
        background: linear-gradient(135deg, var(--primary-color) 0%, #0e5a2b 100%
        );
        color: white;
        border-radius: 0 0 1.5rem 1.5rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        padding: 1.5rem 0;
      }

      .portal-header p.lead {
          font-size: 1rem;
        }

      .portal-card {
        border: none;
        border-radius: 1rem;
        overflow: hidden;
        transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        background-color: white;
        height: 100%;
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.08);
      }

      .portal-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
      }

      .card-icon-container {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        background: var(--primary-color);
      }

      .card-icon {
        font-size: 2rem;
        color: white;
      }

      .btn-portal {
        padding: 0.75rem 1.25rem;
        border-radius: 0.75rem;
        font-weight: 600;
        letter-spacing: 0.5px;
        background-color: var(--primary-color);
        border: none;
        font-size: 0.95rem;
      }

      .btn-portal:hover {
        background-color: #0e5a2b;
        transform: translateY(-2px);
      }

      footer {
        background-color: rgba(0, 0, 0, 0.03);
        margin-top: auto;
        padding: 1.5rem 0;
      }

      .text-gradient {
        background: linear-gradient(
          90deg,
          var(--primary-color) 0%,
          var(--accent-color) 100%
        );
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
      }

      @media (max-width: 768px) {
        .portal-header {
          padding: 1rem 0;
          border-radius: 0 0 1rem 1rem;
        }

        .portal-header h1 {
          font-size: 1.75rem;
          margin-bottom: 0.5rem;
        }

        .portal-header p.lead {
          font-size: 1rem;
        }

        .portal-card {
          margin-bottom: 1rem;
          border-radius: 0.75rem;
        }

        .card-icon-container {
          width: 60px;
          height: 60px;
          margin-bottom: 1rem;
        }

        .card-icon {
          font-size: 1.75rem;
        }

        .btn-portal {
          padding: 0.65rem 1rem;
          font-size: 0.9rem;
        }

        footer p {
          font-size: 0.9rem;
        }

        footer .small {
          font-size: 0.8rem;
        }
      }
    </style>
  </head>
  <body>
    <!-- Header Section -->
    <header class="portal-header py-4 py-md-4 mb-4 mb-md-5">
      <div class="container">
        <div class="row align-items-center">
          <div
            class="col-8 col-md-2 text-center text-md-start mx-auto mx-md-0 mb-3 mb-md-0"
          >
            <img
              src="images/isulogo.png"
              alt="University Logo"
              class="img-fluid rounded-circle bg-white p-2"
              style="width: 70px; height: 70px"
            />
          </div>
          <div class="col-12 col-md-8 text-center">
            <h1 class="display-6 display-md-2 fw-semibold mb-2">
              College Admission Portal
            </h1>
            <h5 class="fw-semibold mb-3">
              Isabela State University - Ilagan Campus
            </h5>
          </div>
          <div class="col-md-2 d-none d-md-block"></div>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="container my-3 my-md-3">
      <div class="row justify-content-center g-3 g-md-4">
<!-- Registration Card -->
<div class="col-12 col-md-6 col-lg-5">
  <div class="portal-card p-3 p-md-4 h-100">
    <div class="card-body text-center py-3 py-md-4">
      <div class="card-icon-container">
        <i class="bi bi-pencil-square card-icon"></i>
      </div>
      <h3 class="h3 h2-md fw-bold mb-2 mb-md-3 text-gradient">
        Register Now
      </h3>
      <p class="text-muted mb-3 mb-md-4">
Complete your admission test application now to secure your spot.
      </p>
      <a
        href="examinee_registration/register_examinee"
        class="btn btn-portal btn-lg text-white px-3 px-md-4 mb-2"
      >
        Register <i class="bi bi-arrow-right ms-2"></i>
      </a>
      <br />
      <a
        href="track_application.php"
        class="btn btn-outline-secondary btn-lg px-3 px-md-4"
      >
        Check Application Status
      </a>
    </div>
  </div>
</div>


        <!-- View Results Card -->
        <div class="col-12 col-md-6 col-lg-5">
          <div class="portal-card p-3 p-md-4 h-100">
            <div class="card-body text-center py-3 py-md-4">
              <div class="card-icon-container">
                <i class="bi bi-graph-up card-icon"></i>
              </div>
              <h3 class="h3 h2-md fw-bold mb-2 mb-md-3 text-gradient">
                View Results
              </h3>
              <p class="text-muted mb-3 mb-md-4">
                Check your admission test results and find out your total score.
              </p>
              <a
                href="examinee_result/login"
                class="btn btn-portal btn-lg text-white px-3 px-md-4"
              >
                Check Results <i class="bi bi-arrow-right ms-2"></i>
              </a>
            </div>
          </div>
        </div>
      </div>
    </main>

    <!-- Footer -->
    <footer class="py-3 py-md-4 mt-4 mt-md-5">
      <div class="container text-center">
        <p class="mb-2">
          Need help? Contact the guidance office at
          <a
            href="mailto:guidance.ilagan@isu.edu.ph"
            class="text-decoration-none"
            >guidance.ilagan@isu.edu.ph</a
          >
        </p>
        <p class="mb-0 text-muted small">
          © <?php echo date('Y'); ?> Isabela State University - Ilagan Campus
        </p>
      </div>
    </footer>

    <link rel="stylesheet" href="css/bootstrap-icons.css" />
    <script src="assets/js/plugins/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="css/css2.css" id="main-font-link" />
  </body>
</html>
