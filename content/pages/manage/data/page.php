<?php include_once(__DIR__ . '/../../../header.php'); ?>
<main class="container subpage">
	<div class="row" style="position: relative; z-index: 1">
		<div class="col-12">
			<h2><?php echo L::manageData; ?></h2>
			<div class="card mb-3">
				<div class="card-body">
					<a href="<?= $config["dir"]["root"] ?>/manage/data/media/new" class="btn btn-outline-success btn-sm mr-1">New Media Item</a>
					<a href="<?= $config["dir"]["root"] ?>/manage/data/person/new" class="btn btn-outline-success btn-sm mr-1">New Person</a>
					<a href="<?= $config["dir"]["root"] ?>/manage/data/document/new" class="btn btn-outline-success btn-sm mr-1">New Document</a>
					<a href="<?= $config["dir"]["root"] ?>/manage/data/organisation/new" class="btn btn-outline-success btn-sm mr-1">New Organisation</a>
					<a href="<?= $config["dir"]["root"] ?>/manage/data/term/new" class="btn btn-outline-success btn-sm mr-1">New Term</a>
				</div>
			</div>
			<ul class="nav nav-tabs" role="tablist">
				<li class="nav-item">
					<a class="nav-link active" id="media-tab" data-toggle="tab" href="#media" role="tab" aria-controls="media" aria-selected="true">Media</a>
				</li>
				<li class="nav-item">
					<a class="nav-link" id="people-tab" data-toggle="tab" href="#people" role="tab" aria-controls="people" aria-selected="false">People</a>
				</li>
				<li class="nav-item">
					<a class="nav-link" id="documents-tab" data-toggle="tab" href="#documents" role="tab" aria-controls="documents" aria-selected="false">Documents</a>
				</li>
				<li class="nav-item">
					<a class="nav-link" id="organisations-tab" data-toggle="tab" href="#organisations" role="tab" aria-controls="organisations" aria-selected="false">Organisations</a>
				</li>
				<li class="nav-item">
					<a class="nav-link" id="terms-tab" data-toggle="tab" href="#terms" role="tab" aria-controls="terms" aria-selected="false">Terms</a>
				</li>
			</ul>
			<div class="tab-content">
				<div class="tab-pane fade show active" id="media" role="tabpanel" aria-labelledby="media-tab">Tabelle Media, <a href="../../media/8757">Beispiel Link</a>, <a href="./media/8757">Manage / Edit Link</a></div>
				<div class="tab-pane fade" id="people" role="tabpanel" aria-labelledby="people-tab">Tabelle Person, <a href="../../person/8757">Beispiel Link</a>, <a href="./person/8757">Manage / Edit Link</a></div>
				<div class="tab-pane fade" id="documents" role="tabpanel" aria-labelledby="documents-tab">Tabelle Document, <a href="../../document/8757">Beispiel Link</a>, <a href="./document/8757">Manage / Edit Link</a></div>
				<div class="tab-pane fade" id="organisations" role="tabpanel" aria-labelledby="organisations-tab">Tabelle Organisation, <a href="../../organisation/8757">Beispiel Link</a>, <a href="./organisation/8757">Manage / Edit Link</a></div>
				<div class="tab-pane fade" id="terms" role="tabpanel" aria-labelledby="terms-tab">Tabelle Term, <a href="../../term/8757">Beispiel Link</a>, <a href="./term/8757">Manage / Edit Link</a></div>
			</div>
		</div>
	</div>
</main>
<?php include_once(__DIR__ . '/../../../footer.php'); ?>