<?php include_once(__DIR__ . '/../../../header.php'); ?>
<main class="container subpage">
	<div class="row" style="position: relative; z-index: 1">
		<div class="col-12">
			<h2><?php echo L::manageUsers; ?></h2>
			<div class="card mb-3">
				<div class="card-body">
					<a href="<?= $config["dir"]["root"] ?>/register" class="btn btn-outline-success btn-sm mr-1">Register New User</a>
					<a href="#" class="btn btn-primary btn-sm mr-1">Send Invite</a>
					<?php
					if ($config["mode"] == "dev") {
						echo '<a class="btn btn-primary btn-sm mr-1" href="'.$config["dir"]["root"].'/server/ajaxServer.php?a=devAddTestuser" target="_blank">Auto-Add Test Users (admin@admin.com:Admin!!11 test@test.com:User!!11)</a>';
					}
					?>
				</div>
			</div>
			<ul class="nav nav-tabs" role="tablist">
				<li class="nav-item">
					<a class="nav-link active" id="all-users-tab" data-toggle="tab" href="#all-users" role="tab" aria-controls="all-users" aria-selected="true">All Users</a>
				</li>
			</ul>
			<div class="tab-content">
				<div class="tab-pane fade show active" id="all-users" role="tabpanel" aria-labelledby="all-users-tab">
					<table id="manageUsersOverviewTable"></table>
				</div>
			</div>
		</div>
	</div>
</main>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/manage/users/client/users.overview.js"></script>
<?php include_once(__DIR__ . '/../../../footer.php'); ?>