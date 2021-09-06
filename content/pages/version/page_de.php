<?php include_once(__DIR__ . '/../../header.php'); ?>
<main class="container subpage">
	<div class="row" style="position: relative; z-index: 1">
		<div class="col-12">
			<h2><?php echo L::releaseNotes; ?></h2>
			<h3><?php echo L::versionCurrent; ?>: <br><b>Public Beta</b></h3>
			<p class="mb-1">Die aktuelle Version ist öffentlich zugänglich, enthält aber z.T. fehlerhafte Daten, Beispieltexte und Bedienelemente die noch nicht funktionieren. Die meisten Module der Plattform wurden auf unterschiedlichen Geräten und in allen gängigen Browsern getestet. Die finalen Tests stehen jedoch noch aus. Zudem kann in dieser Version noch keine Barrierefreiheit gewährleistet werden. </p>
			<div class="alert alert-warning py-1 px-2">Auch wenn alle Seiten bereits geteilt und eingebettet werden können ist es möglich, dass sich einzelne URLs und Parameter bis zum öffentlichen Launch noch ändern.</div>
			<hr>
			<h3><?php echo L::versionNext; ?>: <br><b>Öffentlicher Launch</b> <span class="icon-clock"></span> 20. Oktober 2021</h3>
			<p>Ab 20. Oktober 2021 — kurz vor der konstituierenden Sitzung des 20. Deutschen Bundestages — wird die Open Parliament TV Plattform mit allen Funktionalitäten verfügbar und verwendbar sein. </p>
			<hr>
			<!--
			<h3><?php echo L::versionPrevious; ?>:</h3>
			-->
		</div>
	</div>
</main>
<?php include_once(__DIR__ . '/../../footer.php'); ?>