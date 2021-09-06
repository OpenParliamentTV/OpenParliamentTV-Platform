<?php include_once(__DIR__ . '/../../header.php'); ?>
<main class="container subpage">
	<div class="row" style="position: relative; z-index: 1">
		<div class="col-12">
			<h2><?php echo L::releaseNotes; ?></h2>
			<h3><?php echo L::versionCurrent; ?>: <br><b>Public Beta</b></h3>
			<p class="mb-1">The current version is publicly accessible but might still contain faulty data, sample texts and buttons that don't work. Most modules have been tested on different devices and in recent browsers. The final tests are however still pending. Additionally, we can't guarantee accessibility in this version. </p>
			<div class="alert alert-warning py-1 px-2">Although all pages can be shared and embedded, it is possible that specific URLs and parameters are subject to changes until the public launch. </div>
			<hr>
			<h3><?php echo L::versionNext; ?>: <br><b>Public Launch</b> <span class="icon-clock"></span> 20. October 2021</h3>
			<p>On 20. October 2021 — a few days before the inaugural meeting of the 20. German Bundestag — the Open Parliament TV platform will be accessible and usable with all functionalities. </p>
			<hr>
			<!--
			<h3><?php echo L::versionPrevious; ?>:</h3>
			-->
		</div>
	</div>
</main>
<?php include_once(__DIR__ . '/../../footer.php'); ?>