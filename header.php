<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Office Management System'; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<!-- MDB CSS -->
	<!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/7.3.0/mdb.min.css"/> -->

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
	
	<style>
	  /* Scrollable area with hidden scrollbar */
	  .timesheet-scroll {
		max-height: 220px;
		overflow-y: auto;
		overflow-x: hidden;
		scrollbar-width: none;              /* Firefox */
	  }

	  .timesheet-scroll::-webkit-scrollbar { /* Chrome, Edge, Safari */
		display: none;
	  }

	  /* Make table header sticky inside scroll container */
	  .timesheet-scroll thead th {
		position: sticky;
		top: 0;
		z-index: 2;
		background-color: #f8f9fa; /* same as .table-light */
	  }
	</style>



</head>
<body style="padding-top : 70px; padding-bottom : 70px;">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 fixed-top">
  <div class="container-fluid">
    <a class="navbar-brand" href="dashboard.php">Office Management</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <?php if (!empty($_SESSION['user_id'])): ?>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
		  <li class="nav-item"><a class="nav-link" href="manage_users.php">Users</a></li>
          <li class="nav-item"><a class="nav-link" href="projects.php">Projects</a></li>
        <?php endif; ?>

        <?php if (isset($_SESSION['role']) && ($_SESSION['role'] === 'manager' || $_SESSION['role'] === 'admin')): ?>
          <li class="nav-item"><a class="nav-link" href="tasks.php">Tasks</a></li>
          <li class="nav-item"><a class="nav-link" href="report_tasks.php">Task Report</a></li>
        <?php endif; ?>
		
		<?php if ($_SESSION['role'] === 'admin'): ?>
		  <li class="nav-item">
			<a class="nav-link" href="admin_daily_log.php">Daily Work Log</a>
		  </li>
		<?php endif; ?>


        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'employee'): ?>
			<li class="nav-item"><a class="nav-link" href="timesheets.php">Timesheets</a></li>
		<?php endif; ?>

      </ul>
      <span class="navbar-text me-3">
        Logged in as: <?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?>
        (<?php echo htmlspecialchars($_SESSION['role'] ?? ''); ?>)
      </span>
      <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
    </div>
    <?php endif; ?>
  </div>
</nav>
<div class="container mb-4">
