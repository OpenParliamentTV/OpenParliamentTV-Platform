<?php
include_once(__DIR__ . '/../../../../header.php');
include_once(__DIR__ . '/../../../../../modules/get/functions.get.organisation.php');
$parties = getOrganisation("party");
?>
	<main class="container subpage">
		<div class="row" style="position: relative; z-index: 1">
			<div class="col-12">
				<h2>Add New Person</h2>
				<form action="" method="post" id="importPersonForm">
					<input type="hidden" name="a" value="importPerson">
					<div class="row">
						<div class="col-12">
							<div class="card h-100">
								<div class="card-header">Person</div>
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
										<label for="firstName">First Name</label>
										<input type="text" class="form-control" id="firstName"  name="firstName" value="">
									</div>
									<div class="form-group">
										<label for="lastName">Last Name</label>
										<input type="text" class="form-control" id="lastName"  name="lastName" value="">
									</div>
									<div class="form-group">
										<label for="degree">Degree</label>
										<input type="text" class="form-control" id="degree"  name="degree" value="">
									</div>
									<div class="form-group">
										<label for="birthDate">Birth Date</label>
										<input type="date" class="form-control" id="birthDate"  name="birthDate" value="">
									</div>
									<div class="form-group">
										<label for="gender">Gender</label>
										<input type="text" class="form-control" id="gender"  name="gender" value="">
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
										<label for="websiteURI">Website URI</label>
										<input type="text" class="form-control" id="websiteURI"  name="websiteURI" value="">
									</div>
									<div class="form-group">
										<label for="originID">Thumbnail URI</label>
										<input type="text" class="form-control" id="originID"  name="originID" value="">
									</div>
									<div class="form-group">
										<label for="partyOrganisationID">Party ID</label>
										<!--<input type="text" class="form-control" id="partyOrganisationID"  name="partyOrganisationID" value="">-->
										<select class="form-control mb-4" name="parliament">
											<?php
											foreach($parties["data"] as $k=>$v) {
												echo '<option value="'.$v["OrganisationID"].'">'.$v["OrganisationLabel"].'</option>';
											}
											?>
										</select>
									</div>
									<div class="form-group">
										<label for="factionOrganisationID">Faction ID</label>
										<input type="text" class="form-control" id="factionOrganisationID"  name="factionOrganisationID" value="">
									</div>
									<div class="form-group">
										<label for="socialMediaURIs">Social Media URIs</label>
										<textarea class="form-control" id="socialMediaURIs"  name="socialMediaURIs"></textarea>
									</div>
									<div class="form-group">
										<label for="additionalInformation">Additional Information</label>
										<textarea class="form-control" id="additionalInformation"  name="additionalInformation"></textarea>
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
					$("#importPersonForm").ajaxForm({
						url:"../../../server/ajaxServer.php"
					});
				</script>
			</div>
		</div>
	</main>
<?php include_once(__DIR__ . '/../../../../footer.php'); ?>