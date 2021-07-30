<?php
include_once(__DIR__ . '/../../../../../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"], "requestPage", $pageType);

if ($auth["meta"]["requestStatus"] != "success") {

    $alertText = $auth["errors"][0]["detail"];
    include_once (__DIR__."/../../../login/page.php");

} else {


include_once(__DIR__ . '/../../../../header.php');
include_once(__DIR__ . '/../../../../../modules/get/functions.get.organisation.php');
$data = getOrganisation(false,false,$_REQUEST["id"]);
//print_r($data);
//TODO: Check if success is false
?>
	<main class="container subpage">
		<div class="row" style="position: relative; z-index: 1">
			<div class="col-12">
				<h2>Manage Detail Organisation</h2>
				<form action="" method="post" id="editOrganisationForm">
					<input type="hidden" name="a" value="importOrganisation">
					<div class="row">
						<div class="col-12">
							<div class="card h-100">
								<div class="card-header">Organisation</div>
								<div class="card-body">
									<div class="form-group">
										<label for="type">Type</label>
										<input type="text" class="form-control" id="type"  name="type" value="<?=$data["data"][0]["OrganisationType"]?>">
									</div>
									<div class="form-group">
										<label for="wikidataID">WikidataID</label>
										<input type="text" class="form-control" id="wikidataID"  name="wikidataID" value="<?=$data["data"][0]["OrganisationWikidataID"]?>" readonly>
									</div>
									<div class="form-group">
										<label for="label">Label *</label>
										<input type="text" class="form-control" id="label"  name="label" value="<?=$data["data"][0]["OrganisationLabel"]?>">
									</div>
									<div class="form-group">
										<label for="labelAlternative">Alternative Label</label>
										<input type="text" class="form-control" id="labelAlternative"  name="labelAlternative" value="<?=$data["data"][0]["OrganisationLabelAlternative"]?>">
									</div>
									<div class="form-group">
										<label for="abstract">Abstract</label>
										<textarea class="form-control" id="abstract"  name="abstract"><?=$data["data"][0]["OrganisationAbstract"]?></textarea>
									</div>
									<div class="form-group">
										<label for="thumbnailURI">Thumbnail URI</label>
										<input type="text" class="form-control" id="thumbnailURI"  name="thumbnailURI" value="<?=$data["data"][0]["OrganisationThumbnailURI"]?>">
									</div>
									<div class="form-group">
										<label for="embedURI">Embed URI</label>
										<input type="text" class="form-control" id="embedURI"  name="embedURI" value="<?=$data["data"][0]["OrganisationEmbedURI"]?>">
									</div>
									<div class="form-group">
										<label for="websiteURI">Website URI *</label>
										<input type="text" class="form-control" id="websiteURI"  name="websiteURI" value="<?=$data["data"][0]["OrganisationWebsiteURI"]?>">
									</div>
									<div class="form-group">
										<label for="socialMediaURIs">Social Media URIs (JSON)</label>
										<textarea class="form-control" id="socialMediaURIs"  name="socialMediaURIs"><?=$data["data"][0]["OrganisationSocialMediaURIs"]?></textarea>
									</div>
									<div class="form-group">
										<label for="color">Color</label>
										<input type="color" class="form-control" id="color"  name="color" value="<?=$data["data"][0]["OrganisationColor"]?>">
									</div>
									<input type="hidden" name="updateIfExisting" value="true">
								</div>
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-12 mb-4">
							<button type="submit" class="btn btn-outline-primary">Save Organisation</button>
						</div>
					</div>

				</form>

				<script type="text/javascript">
					$("#editOrganisationForm").ajaxForm({
						url:"../../../server/ajaxServer.php"
					});
				</script>
			</div>
		</div>
	</main>
<?php
    include_once(__DIR__ . '/../../../../footer.php');
}
?>