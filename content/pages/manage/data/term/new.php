<?php include_once(__DIR__ . '/../../../../header.php'); ?>
	<main class="container subpage">
		<div class="row" style="position: relative; z-index: 1">
			<div class="col-12">
				<h2>Add New Term</h2>
				<form action="" method="post" id="importTermForm">
					<input type="hidden" name="a" value="importTerm">
					<div class="row">
						<div class="col-12">
							<div class="card h-100">
								<div class="card-header">Term</div>
								<div class="card-body">
									<div class="form-group">
										<label for="type">Type</label>
										<input type="text" class="form-control" id="type"  name="type" value="">
									</div>
									<div class="form-group">
										<label for="wikidataID">WikidataID *</label>
										<input type="text" class="form-control" id="wikidataID"  name="wikidataID" value="">
									</div>
									<div class="form-group">
										<label for="label">Label *</label>
										<input type="text" class="form-control" id="label"  name="label" value="">
									</div>
									<div class="form-group">
										<label for="labelAlternative">Alternative Label</label>
										<input type="text" class="form-control" id="labelAlternative"  name="labelAlternative" value="">
									</div>
									<div class="form-group">
										<label for="abstract">Abstract *</label>
										<textarea class="form-control" id="abstract"  name="abstract"></textarea>
									</div>
									<div class="form-group">
										<label for="thumbnailURI">Thumbnail URI</label>
										<input type="text" class="form-control" id="thumbnailURI"  name="thumbnailURI" value="">
									</div>
									<div class="form-group">
										<label for="embedURI">Embed URI</label>
										<input type="text" class="form-control" id="embedURI"  name="embedURI" value="">
									</div>
									<div class="form-group">
										<label for="sourceURI">Source URI *</label>
										<input type="text" class="form-control" id="sourceURI"  name="sourceURI" value="">
									</div>
									<label for="updateIfExisting">Update if Item already exists</label>
									<select class="form-control mb-3" name="updateIfExisting">
										<option value="false" selected>No</option>
										<option value="true">Yes</option>
									</select>
								</div>
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-12 mb-4">
							<button type="submit" class="btn btn-outline-primary">Add Term</button>
						</div>
					</div>

				</form>

				<script type="text/javascript">
					$("#importTermForm").ajaxForm({
						url:"../../../server/ajaxServer.php"
					});
				</script>
			</div>
		</div>
	</main>
<?php include_once(__DIR__ . '/../../../../footer.php'); ?>