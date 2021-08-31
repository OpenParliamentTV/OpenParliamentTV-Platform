<?php include_once(__DIR__ . '/../../header.php'); ?>
<main class="container subpage">
	<div class="row" style="position: relative; z-index: 1">
		<div class="col-12">
			<h2>FAQ</h2>
			<div class="accordion my-3" id="accordion">
				<div class="card">
					<div class="card-header" id="q1">
						<button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#a1" aria-expanded="false" aria-controls="a1">Warum?<span class="icon-down-open-big"></span></button>
					</div>
					<div id="a1" class="collapse" aria-labelledby="q1" data-parent="#accordion">
						<div class="card-body">Some placeholder content for the first accordion panel. This panel is shown by default, thanks to the <code>.show</code> class.</div>
					</div>
				</div>
				<div class="card">
					<div class="card-header" id="q2">
						<h2 class="mb-0">
							<button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#a2" aria-expanded="false" aria-controls="a2">Wat?<span class="icon-down-open-big"></span></button>
						</h2>
					</div>
					<div id="a2" class="collapse" aria-labelledby="q2" data-parent="#accordion">
						<div class="card-body">Some placeholder content for the second accordion panel. This panel is hidden by default.</div>
					</div>
				</div>
			</div>
			<div class="alert alert-info">Frage nicht beantwortet? Kontaktiere uns über ...</div>
		</div>
	</div>
</main>
<?php include_once(__DIR__ . '/../../footer.php'); ?>