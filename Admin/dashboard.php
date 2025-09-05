<?php
session_start();
include('../server.php');

// Check authentication
if (!isset($_SESSION['id_no'])) {
    header("Location: index");
    exit();
}

// Get admin info
$admin_name = '';
if (isset($_SESSION['id_no'])) {
    $admin_id = htmlspecialchars(trim($_SESSION['id_no']), ENT_QUOTES, 'UTF-8');
    
    $stmt = $conn->prepare("SELECT name FROM tbl_admin WHERE id_no = ?");
    $stmt->bind_param("s", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $admin_name = htmlspecialchars(trim($row['name']), ENT_QUOTES, 'UTF-8');
    }
    $stmt->close();
}

$loginSuccess = isset($_GET['login']) && $_GET['login'] === 'success';

// Performance level data
$pieChartData = [];
$stmt = $conn->prepare("SELECT
        CASE 
            WHEN s.total_score >= 102 THEN 'Superior'
            WHEN s.total_score BETWEEN 79 AND 101 THEN 'Above Average'
            WHEN s.total_score BETWEEN 56 AND 78 THEN 'Average'
            WHEN s.total_score BETWEEN 33 AND 55 THEN 'Below Average'
            WHEN s.total_score BETWEEN 10 AND 32 THEN 'Low'
            ELSE 'Extremely Low'
        END AS performance_level,
        COUNT(*) AS count
    FROM tbl_score s
    JOIN tbl_examinee e ON s.examinee_id = e.examinee_id
    JOIN tbl_batch b ON e.batch_id = b.batch_id
    JOIN tbl_school_year sy ON b.school_year_id = sy.school_year_id
    WHERE sy.school_year_status = 'active'
    GROUP BY performance_level
");

$stmt->execute();
$performanceResult = $stmt->get_result();

if ($performanceResult) {
    while ($row = $performanceResult->fetch_assoc()) {
        $pieChartData[$row['performance_level']] = $row['count'];
    }
} else {
    echo '<div class="error-message">Error fetching performance levels: ' . $conn->error . '</div>';
}
$stmt->close();

// Scores data
$scoresData = [];
$stmt = $conn->prepare("SELECT e.sex, s.total_score, COUNT(*) as count 
    FROM tbl_score s 
    JOIN tbl_examinee e ON s.examinee_id = e.examinee_id
    JOIN tbl_batch b ON e.batch_id = b.batch_id
    JOIN tbl_school_year sy ON b.school_year_id = sy.school_year_id
    WHERE sy.school_year_status = 'active' 
    GROUP BY e.sex, s.total_score
");

$stmt->execute();
$scoresResult = $stmt->get_result();

if ($scoresResult) {
    while ($row = $scoresResult->fetch_assoc()) {
        $scoresData[$row['sex']][] = [
            'total_score' => $row['total_score'],
            'count' => $row['count']
        ];
    }
} else {
    echo '<div class="error-message">Error fetching scores: ' . $conn->error . '</div>';
}
$stmt->close();

$maleScores = array_column($scoresData['MALE'] ?? [], 'count');
$femaleScores = array_column($scoresData['FEMALE'] ?? [], 'count');
$totalScores = array_unique(array_merge(
    array_column($scoresData['MALE'] ?? [], 'total_score'),
    array_column($scoresData['FEMALE'] ?? [], 'total_score')
));
sort($totalScores);
$labels = array_values($totalScores);
$maleCount = array_fill(0, count($labels), 0);
$femaleCount = array_fill(0, count($labels), 0);

foreach ($scoresData as $gender => $data) {
    foreach ($data as $scoreData) {
        $index = array_search($scoreData['total_score'], $labels);
        if ($index !== false) {
            if ($gender === 'MALE') {
                $maleCount[$index] = $scoreData['count'];
            } elseif ($gender === 'FEMALE') {
                $femaleCount[$index] = $scoreData['count'];
            }
        }
    }
}

// Gender data
$genderData = [];
$stmt = $conn->prepare("SELECT c.course_name,
    SUM(CASE WHEN e.sex = 'MALE' THEN 1 ELSE 0 END) AS total_male,
    SUM(CASE WHEN e.sex = 'FEMALE' THEN 1 ELSE 0 END) AS total_female
    FROM tbl_examinee e
    JOIN tbl_examinee_exam ee ON e.examinee_id = ee.examinee_id
    JOIN tbl_course c ON e.first_preference = c.course_id
    JOIN tbl_batch b ON e.batch_id = b.batch_id
    JOIN tbl_school_year sy ON b.school_year_id = sy.school_year_id
    WHERE ee.status = 'completed'
    AND sy.school_year_status = 'active'
    GROUP BY c.course_name"
);

$stmt->execute();
$genderResult = $stmt->get_result();

if ($genderResult) {
    while ($row = $genderResult->fetch_assoc()) {
        $genderData[] = $row;
    }
} else {
    echo '<div class="error-message">Error fetching gender data: ' . $conn->error . '</div>';
}
$stmt->close();

// Second course gender data
$secondCourseGenderData = [];
$stmt = $conn->prepare("SELECT c.course_name,
    SUM(CASE WHEN e.sex = 'MALE' THEN 1 ELSE 0 END) AS total_male,
    SUM(CASE WHEN e.sex = 'FEMALE' THEN 1 ELSE 0 END) AS total_female
    FROM tbl_examinee e
    JOIN tbl_examinee_exam ee ON e.examinee_id = ee.examinee_id
    JOIN tbl_course c ON e.second_preference = c.course_id
    JOIN tbl_batch b ON e.batch_id = b.batch_id
    JOIN tbl_school_year sy ON b.school_year_id = sy.school_year_id
    WHERE ee.status = 'completed'
    AND sy.school_year_status = 'active'
    GROUP BY c.course_name"
);

$stmt->execute();
$secondCourseGenderResult = $stmt->get_result();

if ($secondCourseGenderResult) {
    while ($row = $secondCourseGenderResult->fetch_assoc()) {
        $secondCourseGenderData[] = $row;
    }
} else {
    echo '<div class="error-message">Error fetching gender data for second course: ' . $conn->error . '</div>';
}
$stmt->close();

// Course abbreviations mapping - will convert any variation to the same abbreviation
$courseAbbreviations = [
    'BACHELOR OF SCIENCE IN NURSING' => 'BSN',
    'BACHELOR OF SCIENCE IN INFORMATION TECHNOLOGY' => 'BSIT',
    'BACHELOR OF SCIENCE IN INDUSTRIAL TECHNOLOGY' => 'BSIndTech',
    'BACHELOR OF SCIENCE IN CIVIL ENGINEERING' => 'BSCE',
    'BACHELOR OF SCIENCE IN ELECTRICAL ENGINEERING' => 'BSEE',
    'BACHELOR OF SCIENCE IN ARCHITECTURE' => 'BSArch',
    'BACHELOR OF TECHNOLOGY AND LIVELIHOOD EDUCATION' => 'BTLED',
    'BACHELOR OF TECHNICAL VOCATIONAL TEACHER EDUCATION' => 'BTVTED',
    'BACHELOR OF SCIENCE IN MIDWIFERY' => 'BSMid',
    'BACHELOR OF SECONDARY EDUCATION' => 'BSEd',
    'BACHELOR OF PHYSICAL EDUCATION' => 'BPEd',
    'BACHELOR OF SCIENCE IN PSYCHOLOGY' => 'BSPsych',
    'DIPLOMA IN MIDWIFERY' => 'DM'
];

// Function to get abbreviation regardless of case formatting
function getCourseAbbreviation($fullName, $abbreviations) {
    // Convert input to uppercase for case-insensitive comparison
    $upperName = strtoupper(trim($fullName));
    
    // Check if any key matches when both are uppercase
    foreach ($abbreviations as $key => $abbr) {
        if (strtoupper(trim($key)) === $upperName) {
            return $abbr;
        }
    }
    
    // If no match found, return the original name
    return $fullName;
}

// Abbreviate first preference courses
// Apply abbreviations directly to course_name keys
foreach ($genderData as &$item) {
    $item['course_name'] = getCourseAbbreviation($item['course_name'], $courseAbbreviations);
}
unset($item); // best practice after modifying references

foreach ($secondCourseGenderData as &$item) {
    $item['course_name'] = getCourseAbbreviation($item['course_name'], $courseAbbreviations);
}
unset($item);

// Extract abbreviated names after updating
$firstCourseNames = array_column($genderData, 'course_name');
$secondCourseNames = array_column($secondCourseGenderData, 'course_name');


$firstMaleCounts = array_column($genderData, 'total_male');
$firstFemaleCounts = array_column($genderData, 'total_female');
$secondMaleCounts = array_column($secondCourseGenderData, 'total_male');
$secondFemaleCounts = array_column($secondCourseGenderData, 'total_female');

$pieChartDataAvailable = !empty($pieChartData);
$firstGenderDataAvailable = !empty($genderData);
$secondGenderDataAvailable = !empty($secondCourseGenderData);
$scoreDataAvailable = !empty($labels);
?>

<!DOCTYPE html>
  <html lang="en">

  <head>
  <title>Dashboard | College Admission Test</title>
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
      .overview-boxes {
          display: flex;
          justify-content: space-between;
          flex-wrap: wrap; 
          gap: 20px;
          padding: 20px; 
      }

      .box {
          width: calc(25% - 15px); 
          height: 110px;
          box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
          display: flex;
          align-items: center;
          justify-content: space-between;
          padding: 20px;
          border-radius: 8px; 
          background-color: #f8f9fa; 
          transition: transform 0.3s; 
      }

      .box:hover {
          transform: scale(1.05); 
      }

      .box .text .batch {
          text-align: left;
          color: #4e73df;
          text-transform: uppercase;
      }

      .box .text .exam {
          text-align: left;
          color: #1cc88a;
          text-transform: uppercase;
      }

      .box .text .examinee {
          text-align: left;
          color: #36b9cc;
          text-transform: uppercase;
      }

      .box .text .proctor {
          text-align: left;
          color: #f6c23e;
          text-transform: uppercase;
      }

      .box .text h3 {
          font-size: 24px;
          margin-bottom: 10px;
      }

      .box .text p {
          font-size: 12px;
      }

      .box .icon {
          font-size: 3rem;
          color: #ccc;
          padding-left: 20px;
      }

      .box-batch {
          border-left: 4px solid #4e73df;
      }

      .box-exam {
          border-left: 4px solid #1cc88a;
      }

      .box-examinee {
          border-left: 4px solid #36b9cc;
      }

      .box-proctor {
          border-left: 4px solid #f6c23e;
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

  <div class="row">
    <div class="overview-boxes">

      <!-- Total Batch -->
      <div class="box box-batch">
        <div class="text">
          <?php
          $status = isset($_GET['status']) ? $_GET['status'] : 'active'; 

          $schoolyear_status = mysqli_real_escape_string($conn, $status);
          
          $batchCountQuery = "SELECT COUNT(*) as total FROM tbl_batch 
            INNER JOIN tbl_school_year ON tbl_batch.school_year_id = tbl_school_year.school_year_id 
            WHERE tbl_school_year.school_year_status = '$schoolyear_status'";
          
          $batchCountResult = $conn->query($batchCountQuery);
          $batchCount = ($batchCountResult) ? $batchCountResult->fetch_assoc()['total'] : 0;
          
          ?>
          <h3><?= $batchCount ?></h3>
          <p class="batch">Total Batch</p>
        </div>
        <div class="icon">
          <i class='bx bx-layer'></i>
        </div>
      </div>

      <!-- Total Exams -->
      <div class="box box-exam">
        <div class="text">
          <?php
          $status = isset($_GET['status']) ? $_GET['status'] : 'active'; 

          $schoolyear_status = mysqli_real_escape_string($conn, $status);
          
          $examCountQuery = "SELECT COUNT(*) as total FROM exam_schedules 
            INNER JOIN exams ON exam_schedules.exam_id = exams.exam_id 
            INNER JOIN tbl_school_year ON exams.school_year_id = tbl_school_year.school_year_id 
            WHERE tbl_school_year.school_year_status = '$schoolyear_status'";
          $examCountResult = $conn->query($examCountQuery);
          $examCount = ($examCountResult) ? $examCountResult->fetch_assoc()['total'] : 0;
          ?>
          <h3><?= $examCount ?></h3>
          <p class="exam">Total Exams</p>
        </div>
        <div class="icon">
          <i class='bx bx-detail'></i>
        </div>
      </div>

      <!-- Total Examinee -->
      <div class="box box-examinee">
        <div class="text">
          <?php
          $status = isset($_GET['status']) ? $_GET['status'] : 'active'; 

          // Sanitize input
          $schoolyear_status = mysqli_real_escape_string($conn, $status);
          
          $studentCountQuery = "SELECT COUNT(*) as total FROM tbl_examinee 
            INNER JOIN tbl_batch ON tbl_examinee.batch_id = tbl_batch.batch_id 
            INNER JOIN tbl_school_year ON tbl_batch.school_year_id = tbl_school_year.school_year_id 
            WHERE tbl_school_year.school_year_status = '$schoolyear_status'";
          $studentCountResult = $conn->query($studentCountQuery);
          $studentCount = ($studentCountResult) ? $studentCountResult->fetch_assoc()['total'] : 0;
          ?>
          <h3><?= $studentCount ?></h3>
          <p class="examinee">Total Examinee</p>
        </div>
        <div class="icon">
          <i class='bx bxs-group'></i>
        </div>
      </div>

      <!-- Total Proctor -->
      <div class="box box-proctor">
        <div class="text">
          <?php
          $proctorCountQuery = "SELECT COUNT(*) as total FROM tbl_proctor";
          $proctorCountResult = $conn->query($proctorCountQuery);
          $proctorCount = ($proctorCountResult) ? $proctorCountResult->fetch_assoc()['total'] : 0;
          ?>
          <h3><?= $proctorCount ?></h3>
          <p class="proctor">Total Proctor</p>
        </div>
        <div class="icon">
          <i class='bx bxs-user'></i>
        </div>
      </div>

    </div>
  </div>


    <!-- Export Button -->
  <div class="my-2 text-end">
    <form action="export_db.php" method="post">
      <button type="submit" class="btn btn-primary">
        <i class="fa-solid fa-download me-1"></i> Export Database
      </button>
    </form>
  </div>


      <!-- Charts Section -->
  <div class="row g-4">
    <div class="col-lg-5 col-md-6">
      <div class="card shadow" style="height: 500px;"> 
      <div class="card-header text-white d-flex justify-content-between align-items-center py-3" style="background-color: #116736;">
            <h6 class="mb-0">EXAMINEE PERFORMANCE LEVEL</h6>
        </div>
        <div class="card-body" style="height: calc(100% - 56px);">
          <canvas id="piechart_3d" style="height: 100%; width: 100%;"></canvas>
        </div>
      </div>
    </div>

   <!-- Chart Container -->
<div class="col-lg-7 col-md-6">
  <div class="card shadow" style="height: 500px;"> 
    <div class="card-header text-white d-flex justify-content-between align-items-center py-3" style="background-color: #116736;">
      <h6 class="mb-0">EXAMINEE SCORE</h6>
    </div>
    <div class="card-body" style="height: calc(100% - 56px);">
      <canvas id="examineescoreChart" style="height: 100%; width: 100%;"></canvas>
    </div>
  </div>
</div>
  </div>

  <div class="row g-4 mt-0">
    <div class="col-lg-12">
      <div class="card shadow" style="height: 500px;"> 
      <div class="card-header text-white d-flex justify-content-between align-items-center py-3" style="background-color: #116736;">
          <h6 class="mb-0">GENDER DISTRIBUTION OF FIRST COURSE PREFERENCE</h6>
        </div>
        <div class="card-body" style="height: calc(100% - 56px);"> 
          <canvas id="columnchart_material" style="height: 100%; width: 100%;"></canvas>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4 mt-0">
    <div class="col-lg-12">
      <div class="card shadow" style="height: 500px;"> 
      <div class="card-header text-white d-flex justify-content-between align-items-center py-3" style="background-color: #116736;">
          <h6 class="mb-0">GENDER DISTRIBUTION OF SECOND COURSE PREFERENCE</h6>
        </div>
        <div class="card-body" style="height: calc(100% - 56px);">
          <canvas id="genderChartSecond" style="height: 100%; width: 100%;"></canvas>
        </div>
      </div>
    </div>
  </div>

  <?php if ($loginSuccess): ?>
    <script>
      const Toast = Swal.mixin({
          toast: true,
          position: 'top-end',
          showConfirmButton: false,
          timer: 5000,
          timerProgressBar: true,
          didOpen: (toast) => {
              toast.onmouseenter = Swal.stopTimer;
              toast.onmouseleave = Swal.resumeTimer;
          }
      });

      Toast.fire({
          icon: 'success',
          title: 'Signed in successfully'
      });

      if (window.history.replaceState) {
          const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
          window.history.replaceState({path: cleanUrl}, '', cleanUrl);
      }
  </script>
  <?php endif; ?>

  <script>
  document.addEventListener("DOMContentLoaded", function() {
      const chartOptions = {
          responsive: true,
          maintainAspectRatio: false, 
          plugins: {
              legend: { position: "top" }
          }
      };

      // Pie Chart for Examinee Performance Level
      const ctxPie = document.getElementById("piechart_3d").getContext("2d");
      new Chart(ctxPie, {
          type: "pie",
          data: {
              labels: <?= json_encode(array_keys($pieChartData)) ?>,
              datasets: [{
                  label: "Performance Level",
                  data: <?= json_encode(array_values($pieChartData)) ?>,
                  backgroundColor: ["#0496ff", "#d90429", "#ffd60a", "#1a7431", "#7b2cbf", "#48cae4"]
              }]
          },
          options: {
              ...chartOptions,
              plugins: {
                  legend: { position: "top" },
                  tooltip: {
                      callbacks: {
                          label: function(context) {
                              const label = context.label || '';
                              const value = context.raw || 0;
                              const total = context.dataset.data.reduce((a, b) => a + b, 0);
                              const percentage = Math.round((value / total) * 100);
                              return `${label}: ${value} (${percentage}%)`;
                          }
                      }
                  },
                  datalabels: {
                      formatter: (value, ctx) => {
                          const dataArr = ctx.chart.data.datasets[0].data;
                          const sum = dataArr.reduce((a, b) => a + b, 0);
                          const percentage = (value * 100 / sum).toFixed(1) + '%';
                          return percentage;
                      },
                      color: '#fff',
                      font: {
                          weight: 'bold',
                          size: 14
                      }
                  }
              }
          },
          plugins: [ChartDataLabels]
      });

      // Function to calculate max value for y-axis with padding
      function calculateMaxValue(values) {
          const max = Math.max(...values);
          return Math.ceil(max / 10) * 10 + 10;
      }

      // Bar Chart for Examinee Score
      const maxScoreCount = Math.max(
  ...<?= json_encode($maleCount) ?>, 
  ...<?= json_encode($femaleCount) ?>
);

     const ctxScore = document.getElementById("examineescoreChart").getContext("2d");
new Chart(ctxScore, {
  type: "bar",
  data: {
    labels: <?= json_encode($labels) ?>,
    datasets: [
      {
        label: "Male",
        data: <?= json_encode($maleCount) ?>,
        backgroundColor: "#0466c8"
      },
      {
        label: "Female",
        data: <?= json_encode($femaleCount) ?>,
        backgroundColor: "#ff0054"
      }
    ]
  },
  options: {
    responsive: true,
   
    scales: {
      x: {
        title: { display: true, text: "Total Score" },
        stacked: false,
        ticks: { autoSkip: false }
      },
      y: {
        beginAtZero: true,
        max: maxScoreCount,
        title: { display: true, text: "Number of Examinees" },
        ticks: {
          stepSize: 10,
          callback: function(value) {
            return value;
          }
        }
      }
    }
  }
});


      // Bar Chart for Gender Distribution (First Course Preference)
      const maxFirstCourse = calculateMaxValue([...<?= json_encode($firstMaleCounts) ?>, ...<?= json_encode($firstFemaleCounts) ?>]);
      const ctxGenderFirst = document.getElementById("columnchart_material").getContext("2d");
      new Chart(ctxGenderFirst, {
          type: "bar",
          data: {
              labels: <?= json_encode($firstCourseNames) ?>,
              datasets: [
                  {
                      label: "Male",
                      data: <?= json_encode($firstMaleCounts) ?>,
                      backgroundColor: "#0466c8"
                  },
                  {
                      label: "Female",
                      data: <?= json_encode($firstFemaleCounts) ?>,
                      backgroundColor: "#ff0054"
                  }
              ]
          },
          options: {
              ...chartOptions,
              scales: {
                  x: {
                    title: {
                        display: true,
                        text: "Course"  
                    }
                },
                  y: {
                      title: { display: true, text: "Number of Examinees" },
                      beginAtZero: true,
                      max: maxFirstCourse,
                      ticks: {
                          stepSize: 10,
                          callback: function(value) {
                              return value;
                          }
                      }
                  }
              }
          }
      });

      // Bar Chart for Gender Distribution (Second Course Preference)
      const maxSecondCourse = calculateMaxValue([...<?= json_encode($secondMaleCounts) ?>, ...<?= json_encode($secondFemaleCounts) ?>]);
      const ctxGenderSecond = document.getElementById("genderChartSecond").getContext("2d");
      new Chart(ctxGenderSecond, {
          type: "bar",
          data: {
              labels: <?= json_encode($secondCourseNames) ?>,
              datasets: [
                  {
                      label: "Male",
                      data: <?= json_encode($secondMaleCounts) ?>,
                      backgroundColor: "#0466c8"
                  },
                  {
                      label: "Female",
                      data: <?= json_encode($secondFemaleCounts) ?>,
                      backgroundColor: "#ff0054"
                  }
              ]
          },
          options: {
              ...chartOptions,
               scales: {
                x: {
                    title: {
                        display: true,
                        text: "Course"  
                    }
                },
                y: {
                    title: { 
                        display: true, 
                        text: "Number of Examinees" 
                    },
                    beginAtZero: true,
                    max: maxSecondCourse,
                    ticks: {
                        stepSize: 10,
                        callback: function(value) {
                            return value;
                        }
                    }
                }
            }
        }
    });
  });

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
  </script>
      
  <script src="script.js"></script>
  </body>
</html>