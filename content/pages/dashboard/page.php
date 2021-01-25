<?php include_once(__DIR__ . '/../../header.php'); ?>
<main class="container subpage">
	<div class="row">
		<div class="col-12">
			<h2 class="mb-4"><?php echo L::personalSettings; ?></h2>
			<ul>
				<li>
					<a href="./manage/notifications"><?php echo L::notifications; ?></a>
				</li>
				<li>
					<a href="./manage/users/MYUSERID">My User Settings</a>
				</li>
			</ul>
			<hr>
			<h2>Administration</h2>
			<ul>
				<li>
					<a href="./manage/conflicts"><?php echo L::manageConflicts; ?></a>
				</li>
				<li>
					<a href="./manage/data"><?php echo L::manageData; ?></a>
				</li>
				<li>
					<a href="./manage/users"><?php echo L::manageUsers; ?></a>
				</li>
				<li>
					<a href="./manage/import"><?php echo L::data; ?>-Import</a>
				</li>
				<li>
					<a href="./manage/config"><?php echo L::platformSettings; ?></a>
				</li>
			</ul>
		</div>
	</div>
</main>
<?php include_once(__DIR__ . '/../../footer.php'); ?>