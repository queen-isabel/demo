<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav class="pc-sidebar" style="background-color: #053a1c;">
<div class="navbar-wrapper">
  <div class="m-header d-flex justify-content-center align-items-center text-white">
    <img src="../assets/images/cat.svg" alt="Logo" style="width: 350px; height: 350px; margin-top: 35px;">
  </div>
</div>



    <div class="navbar-content">
      <ul class="pc-navbar">
        <li class="pc-item">
          <a href="dashboard.php"
             class="pc-link text-white <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <span class="pc-micon"><i class="ti ti-dashboard"></i></span>
            <span class="pc-mtext">Dashboard</span>
          </a>
        </li>
        <li class="pc-item pc-caption">
          <label>Exam Management</label>
          <i class="ti ti-brand-chrome"></i>
        </li>
        <li class="pc-item">
          <a href="batch.php"
             class="pc-link text-white <?php echo ($current_page == 'batch.php') ? 'active' : ''; ?>">
            <span class="pc-micon"><i class="ti ti-bookmarks"></i></span>
            <span class="pc-mtext">Batch</span>
          </a>
        </li>
        <li class="pc-item">
          <a href="proctor.php"
             class="pc-link text-white <?php echo ($current_page == 'proctor.php') ? 'active' : ''; ?>">
            <span class="pc-micon"><i class="ti ti-user"></i></span>
            <span class="pc-mtext">Proctor</span>
          </a>
        </li>
        <li class="pc-item">
          <a href="exam.php"
             class="pc-link text-white <?php echo ($current_page == 'exam.php') ? 'active' : ''; ?>">
            <span class="pc-micon"><i class="ti ti-file"></i></span>
            <span class="pc-mtext">Exam</span>
          </a>
        </li>
        <li class="pc-item">
          <a href="questions.php"
             class="pc-link text-white <?php echo ($current_page == 'questions.php') ? 'active' : ''; ?>">
            <span class="pc-micon"><i class="ti ti-clipboard"></i></span>
            <span class="pc-mtext">Questions</span>
          </a>
        </li>
        <li class="pc-item">
          <a href="schedule.php"
             class="pc-link text-white <?php echo ($current_page == 'schedule.php') ? 'active' : ''; ?>">
            <span class="pc-micon"><i class="ti ti-calendar-event"></i></span>
            <span class="pc-mtext">Schedule</span>
          </a>
        </li>
        <li class="pc-item">
          <a href="school_year.php"
             class="pc-link text-white <?php echo ($current_page == 'school_year.php') ? 'active' : ''; ?>">
            <span class="pc-micon"><i class="ti ti-calendar-time"></i></span>
            <span class="pc-mtext">School Year</span>
          </a>
        </li>
        <li class="pc-item pc-caption">
          <label>Examinees</label>
          <i class="ti ti-brand-chrome"></i>
        </li>
        <li class="pc-item">
          <a href="qualified_examinee.php"
             class="pc-link text-white <?php echo ($current_page == 'qualified_examinee.php') ? 'active' : ''; ?>">
            <span class="pc-micon"><i class="ti ti-user-check"></i></span>
            <span class="pc-mtext">Qualified Examinee</span>
          </a>
        </li>
        <li class="pc-item">
  <a href="pending_applicants.php"
     class="pc-link text-white <?php echo ($current_page == 'pending_applicants.php') ? 'active' : ''; ?>">
    <span class="pc-micon"><i class="ti ti-user-question"></i></span>
    <span class="pc-mtext">Pending Applicants</span>
  </a>
</li>
        <li class="pc-item">
          <a href="rejected_applicants.php"
             class="pc-link text-white <?php echo ($current_page == 'rejected_applicants.php') ? 'active' : ''; ?>">
            <span class="pc-micon"><i class="ti ti-user-x"></i></span>
            <span class="pc-mtext">Rejected Applicants</span>
          </a>
        </li>
        <li class="pc-item pc-caption">
          <label>Reports</label>
          <i class="ti ti-brand-chrome"></i>
        </li>
        <li class="pc-item">
          <a href="reports.php"
             class="pc-link text-white <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
            <span class="pc-micon"><i class="ti ti-report"></i></span>
            <span class="pc-mtext">Reports</span>
          </a>
        </li>
        <li class="pc-item">
          <a href="ranking.php"
             class="pc-link text-white <?php echo ($current_page == 'ranking.php') ? 'active' : ''; ?>">
            <span class="pc-micon"><i class="ti ti-trophy"></i></span>
            <span class="pc-mtext">Ranking</span>
          </a>
        </li>
        <li class="pc-item">
          <a href="item_analysis.php"
             class="pc-link text-white <?php echo ($current_page == 'item_analysis.php') ? 'active' : ''; ?>">
            <span class="pc-micon"><i class="ti ti-chart-bar"></i></span>
            <span class="pc-mtext">Item Analysis</span>
          </a>
        </li>
        <li class="pc-item pc-caption">
          <label>Other</label>
          <i class="ti ti-brand-chrome"></i>
        </li>
        <li class="pc-item pc-hasmenu">
          <a href="#!" class="pc-link text-white "><span class="pc-micon"><i class="ti ti-menu"></i></span><span class="pc-mtext">Administration
              </span><span class="pc-arrow"><i data-feather="chevron-right"></i></span></a>
          <ul class="pc-submenu">
            <li class="pc-item"><a href="strand"
             class="pc-link text-white <?php echo ($current_page == 'strand.php') ? 'active' : ''; ?>">
            <span class="pc-micon"><i class="ti ti-book"></i></span>
            <span class="pc-mtext">Strand</span>
          </a></li>
            <li class="pc-item"><a href="subject"
             class="pc-link text-white <?php echo ($current_page == 'subject.php') ? 'active' : ''; ?>">
            <span class="pc-micon"><i class="ti ti-ruler-2-off"></i></span>
            <span class="pc-mtext">Subject</span>
          </a></li>
            <li class="pc-item"><a href="course"
             class="pc-link text-white <?php echo ($current_page == 'course.php') ? 'active' : ''; ?>">
            <span class="pc-micon"><i class="ti ti-school"></i></span>
            <span class="pc-mtext">Course</span>
          </a></li>

            
          </ul>
         
        </li>
      </ul>
    </div>
  </div>
</nav>
