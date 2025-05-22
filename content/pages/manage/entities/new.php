<?php
include_once(__DIR__ . '/../../../../modules/utilities/auth.php');
$auth = auth($_SESSION["userdata"]["id"], "requestPage", $pageType);

if ($auth["meta"]["requestStatus"] != "success") {
    $alertText = $auth["errors"][0]["detail"];
    include_once (__DIR__."/../../login/page.php");

} else {
    include_once(__DIR__ . '/../../../header.php');
    include_once(__DIR__ . '/../../../../api/v1/api.php');
?>
<main class="container-fluid subpage">
	<div class="row">
		<?php include_once(__DIR__ . '/../sidebar.php'); ?>
		<div class="sidebar-content">
			<div class="row" style="position: relative; z-index: 1">
				<div class="col-12">
					<h2>Add New Entity</h2>
					<div id="entityAddSuccess" style="display:none;" class="pb-5 contentContainer">
						<h3>Entity successfully added</h3>
						<div class="row">
							<div class="col-12">
								Entity has been added.<br>
								<div id="affectedSessions_false">There seem to be no sessions affected.</div>
								<div id="affectedSessions_true">
									Do you want to re-import these sessions?<br>
									<form id="reimportSessionForm" method="post">
										<div id="affectedSessions"></div>
										<button class="btn btn-sm input-group-text entitiesToggleDetailsAndTable mb-3" id="reimportSessionsButton">
											<span class="icon-plus"></span>
											<span class="d-none d-md-inline">Yes</span>
										</button>
										TODO: Checkbox to remove EntitySuggestion
									</form>
								</div>
							</div>
						</div>
					</div>
					<div id="entityAddDiv" class="mt-4 contentContainer">
						<form id="entityAddForm" method="post">
							<div class="row">
								<div class="col-12 col-lg-4">
									<input name="action" value="addItem" type="hidden">
									<input name="sourceEntitySuggestionExternalID" value="" type="hidden">
									<div class="form-group">
										<label for="itemType">Entity Type</label>
										<select class="form-control" name="itemType">
											<option value="" disabled selected>Choose Entity Type ..</option>
											<option value="person">Person</option>
											<option value="organisation">Organisation</option>
											<option value="document">Document</option>
											<option value="term">Term</option>
										</select>
									</div>
								</div>
								<div class="col-12 col-lg-4">
									<div class="form-group">
										<label for="id">Wikidata ID</label>
										<input type="text" class="form-control" name="id">
									</div>
								</div>
								<div class="col-12 col-lg-4">
									<div class="form-group formItem formItemTypeTerm">
										<label for="type">Subtype</label>
										<select class="form-control" name="type">
											<option value="" disabled selected>Choose Subtype ..</option>
											<option value="otherTerm">Other Term</option>
										</select>
									</div>
									<div class="form-group formItem formItemTypeDocument">
										<label for="type">Subtype</label>
										<select class="form-control" name="type">
											<option value="" disabled selected>Choose Subtype ..</option>
											<option value="officialDocument">officialDocument</option>
											<option value="legalDocument">legalDocument</option>
											<option value="otherDocument">Other Document</option>
										</select>
									</div>
									<div class="form-group formItem formItemTypePerson">
										<label for="type">Subtype</label>
										<select class="form-control" name="type">
											<option value="" disabled selected>Choose Subtype ..</option>
											<option value="memberOfParliament">Member Of Parliament</option>
											<option value="person">Person</option>
										</select>
									</div>
									<div class="form-group formItem formItemTypeOrganisation">
										<label for="type">Subtype</label>
										<select class="form-control" name="type">
											<option value="" disabled selected>Choose Subtype ..</option>
											<option value="party">Party</option>
											<option value="faction">Faction</option>
											<option value="government">Government</option>
											<option value="company">Company</option>
											<option value="ngo">NGO</option>
											<option value="otherOrganisation">Other Organisation</option>
										</select>
									</div>
								</div>
							</div>
							<div class="row">
								<div class="col-12">
									<button type="button" class="btn btn-primary w-100 py-3" id="getAdditionalInfo"><span class="icon-magic"></span> Get data and auto-fill form fields</button>
								</div>
							</div>
							<hr class="my-4">
							<div class="row">
								<div class="col-12">
									<div class="alert alert-warning text-center">No manual editing below this line</div>
									<div class="form-group formItem formItemTypePerson formItemTypeOrganisation formItemTypeDocument formItemTypeTerm" style="position: relative">
										<label for="label">Label</label>
										<input type="text" class="form-control" name="label">
									</div>
									<div class="form-group formItem formItemTypePerson formItemTypeOrganisation formItemTypeDocument formItemTypeTerm">
										<label for="labelAlternative[]">Alternative Labels</label> <button class="labelAlternativeAdd btn" type="button"><span class="icon-plus"></span></button>
										<div>
										</div>
									</div>
								</div>
							</div>
							<div class="row">
								<div class="col-12 col-lg-6">
									<div class="form-group formItem formItemTypePerson">
										<label for="firstName">First Name</label>
										<input type="text" class="form-control" name="firstName">
									</div>
								</div>
								<div class="col-12 col-lg-6">
									<div class="form-group formItem formItemTypePerson">
										<label for="lastName">Last Name</label>
										<input type="text" class="form-control" name="lastName">
									</div>
								</div>
							</div>
							<div class="row">
								<div class="col-12 col-lg-4">
									<div class="form-group formItem formItemTypePerson">
										<label for="degree">Degree</label>
										<input type="text" class="form-control" name="degree">
									</div>
								</div>
								<div class="col-12 col-lg-4">
									<div class="form-group formItem formItemTypePerson">
										<label for="birthdate">Date Of Birth</label>
										<input type="text" class="form-control" name="birthdate" placeholder="YYYY-MM-DD">
									</div>
								</div>
								<div class="col-12 col-lg-4">
									<div class="form-group formItem formItemTypePerson">
										<label for="gender">Gender</label>
										<select class="form-control" name="gender">
											<option value="" disabled selected>Choose Gender ..</option>
											<option value="female">Female</option>
											<option value="male">Male</option>
											<option value="non-binary">Non-Binary</option>
										</select>
									</div>
								</div>
							</div>
							<div class="row">
								<div class="col-12">
									<div class="form-group formItem formItemTypePerson formItemTypeOrganisation formItemTypeDocument formItemTypeTerm">
										<label for="abstract">Abstract</label>
										<textarea class="form-control" name="abstract"></textarea>
									</div>
									<div class="form-group formItem formItemTypePerson formItemTypeOrganisation formItemTypeDocument formItemTypeTerm">
										<label for="thumbnailuri">Thumbnail URI</label>
										<input type="text" class="form-control" name="thumbnailuri">
									</div>
									<div class="form-group formItem formItemTypePerson formItemTypeOrganisation formItemTypeDocument formItemTypeTerm">
										<label for="thumbnailcreator">Thumbnail Creator</label>
										<input type="text" class="form-control" name="thumbnailcreator">
									</div>
									<div class="form-group formItem formItemTypePerson formItemTypeOrganisation formItemTypeDocument formItemTypeTerm">
										<label for="thumbnaillicense">Thumbnail License</label>
										<input type="text" class="form-control" name="thumbnaillicense">
									</div>
									<div class="form-group formItem formItemTypeDocument">
										<label for="sourceuri">Source URI</label>
										<input type="text" class="form-control" name="sourceuri">
									</div>
									<div class="form-group formItem formItemTypePerson formItemTypeOrganisation formItemTypeDocument formItemTypeTerm">
										<label for="embeduri">Embed URI</label>
										<input type="text" class="form-control" name="embeduri">
									</div>
									<div class="form-group formItem formItemTypePerson formItemTypeOrganisation formItemTypeTerm">
										<label for="websiteuri">Website URI</label>
										<input type="text" class="form-control" name="websiteuri">
									</div>
									<div class="form-group formItem formItemTypePerson">
										<label for="originid">Original ID</label>
										<input type="text" class="form-control" name="originid">
									</div>
									<div class="form-group formItem formItemTypePerson">
										<label for="party">Party</label>
										<select class="form-control" name="party">
											<option value="" disabled selected>Choose Party ..</option>
											<?php
											require_once (__DIR__."/../../../../api/v1/modules/organisation.php");
											$partyie = organisationSearch(array("type"=>"party"));
											foreach ($partyie["data"] as $party) {
												echo '<option value="'.$party["id"].'">'.$party["attributes"]["label"].'</option>';
											}
											?>
										</select>
									</div>
									<div class="form-group formItem formItemTypePerson">
										<label for="faction">Faction</label>
										<select class="form-control" name="faction">
											<option value="" disabled selected>Choose Faction ..</option>
											<?php
											require_once (__DIR__."/../../../../api/v1/modules/organisation.php");
											$factions = organisationSearch(array("type"=>"faction"));
											foreach ($factions["data"] as $faction) {
												echo '<option value="'.$faction["id"].'">'.$faction["attributes"]["label"].'</option>';
											}
											?>
										</select>
									</div>
									<div class="form-group form-dyncontent formItem formItemTypePerson formItemTypeOrganisation">
										<label for="socialMediaIDsLabel[]">Social Media Accounts</label> <button class="socialMediaIDsAdd btn" type="button"><span class="icon-plus"></span></button>
										<div>
										</div>
									</div>
									<div class="form-group formItem formItemTypeOrganisation">
										<label for="color">Color</label>
										<input type="text" class="form-control" name="color">
									</div>
									<div class="form-group formItem formItemTypePerson formItemTypeOrganisation formItemTypeDocument formItemTypeTerm">
										<label for="additionalinformation">Additional Information (JSON)</label>
										<textarea class="form-control" name="additionalinformation" placeholder='{"abgeordnetenwatchID":""}'></textarea>
									</div>
									<div id="entityAddReturn"></div>
									<div>
										<button class="btn btn-outline-secondary rounded-pill" id="entityAddFormCancelBtn"><span class="icon-trash-empty"></span> Cancel</button>
										<button class="btn btn-outline-primary rounded-pill" id="entityAddFormSubmitBtn" type="submit"><span class="icon-upload"></span> Add Entity</button>
									</div>
								</div>
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
</main>

<style>
	.form-item {
		display: none;
	}
</style>

<script type="text/javascript">
	$(function() {
		
		// Fill in wikidataID in case we got it in the url (eg. "?wikidataID=Q567")
		let queryWikidataID = getQueryVariable('wikidataID');
		if (queryWikidataID) {
			$('input[name="id"]').val(queryWikidataID);
		}

		// Fill in entitySuggestionID in case we got it in the url
		let queryEntitySuggestionID = getQueryVariable('entitySuggestionID');
		if (queryEntitySuggestionID) {
			$('input[name="sourceEntitySuggestionExternalID"]').val(queryEntitySuggestionID);
		}

		$('#entityAddForm').ajaxForm({
			url: config.dir.root +"/api/v1/",
			dataType: "json",
			success: function (ret) {

				$("#entityAddReturn").empty();
				$("input, select, textarea").css("border", "");

				if (ret["meta"]["requestStatus"] != "success") {
					for (let error in ret["errors"]) {
						$("#entityAddReturn").append('<div>' + ret["errors"][error]["title"] + '</div>');
						if ("label" in ret["errors"][error]) {
							$("[name='" + ret["errors"][error]["label"] + "']").css("border", "1px solid red");
						}
					}
				} else {

					$("#affectedSessions").empty();

					$(".contentContainer").not("#entityAddSuccess").slideUp();
					$("#entityAddSuccess").slideDown();

					if (ret.meta && "EntitysuggestionItem" in ret.meta) {
						$("#reimportSessions").data("entitysuggestionid", ret.meta.EntitysuggestionItem["EntitysuggestionItemID"]);
						if (ret.meta && "affectedSessions" in ret.meta && (Object.keys(ret.meta.affectedSessions).length > 0)) {
							for (let parliament in ret.meta.affectedSessions) {
								let sessioncontent = "";
								for (let session in ret.meta.affectedSessions[parliament]) {
									sessioncontent += "<div class='sessionFilesDiv'>" + session + " | File exists: " + ret.meta.affectedSessions[parliament][session]["fileExists"] + "<input type='hidden' name='files[" + parliament + "][]' class='reimportfile' value='" + session + "'>";
								}
								$("#affectedSessions").append("<div class='parlamentDiv><h4>Parlament " + parliament + "</h4>" + sessioncontent + "</div>");
							}
							$("#affectedSessions_true").show();
							$("#affectedSessions_false").hide();
						} else {
							$("#affectedSessions_false").show();
							$("#affectedSessions_true").hide();
						}

					} else {
						$("#affectedSessions_false").show();
						$("#affectedSessions_true").hide();
					}

				}
			}
		});

		$(".labelAlternativeAdd").on("click", function() {
			$(this).parent().find("div:first").append('<span style="position: relative">' +
				'<input type="text" class="form-control" name="labelAlternative[]">' +
				'<button class="labelAlternativeRemove btn" style="position: absolute;top:0px;right:0px;" type="button">' +
				'<span class="icon-cancel-circled"></span>' +
				'</button></span>');
		});


		$("body").on("click", ".labelAlternativeRemove", function() {
			$(this).parent().remove();
		});

		$(".socialMediaIDsAdd").on("click", function() {
			$(this).parent().find("div:first").append('<div style="position: relative" class="form-row">\n' +
				'                                        <div class="col">' +
				'                                           <input type="text" class="form-control" name="socialMediaIDsLabel[]" placeholder="Label (e.g. facebook)">' +
				'                                        </div>\n' +
				'                                        <div class="col">' +
				'                                           <input type="text" class="form-control" name="socialMediaIDsValue[]" placeholder="Value (name)">\n' +
				'                                        </div>\n' +
				'                                        <button class="socialMediaIDsRemove btn" style="position: absolute;top:0px;right:0px;" type="button">\n' +
				'                                            <span class="icon-cancel-circled"></span>\n' +
				'                                        </button>\n' +
				'                                    </div>');
		});

		$("body").on("click", ".socialMediaIDsRemove", function() {
			$(this).parent().remove();
		});

		$("body").on("change", "select[name='itemType']", function() {
			let tempItem = "";
			switch ($(this).val()) {
				case "organisation":
					tempItem=".formItemTypeOrganisation";
				break;
				case "person":
					tempItem=".formItemTypePerson";
				break;
				case "term":
					tempItem=".formItemTypeTerm";
				break;
				case "document":
					tempItem=".formItemTypeDocument";
				break;
				default:
					tempItem=".not";
				break;
			}
			$("#entityAddForm .formItem input, #entityAddForm .formItem textarea, #entityAddForm .formItem select").prop("disabled",true);
			$(".formItem").slideUp(function() {
				$(tempItem +" input, "+tempItem+" textarea, "+tempItem+" select").prop("disabled",false);
				$(tempItem).slideDown();
			});

		});

		$("#getAdditionalInfo").click(function(evt) {
			
			resetForm();

			let entityType = $("select[name='itemType']").val();
			let subType = $("select[name='type']:not(:disabled)").val();

			let serviceType = entityType;
			if (subType == "memberOfParliament" || subType == "officialDocument" || subType == "legalDocument") {
				serviceType = subType;
			}

			let wikidataID = $("input[name='id']").val();

			$.ajax({
				url:config.dir.root+"/server/ajaxServer.php",
				data: {
					"a": "entityGetFromAdditionalDataService",
					"type": serviceType,
					"wikidataID": wikidataID
				},
				success: function(result) {
					
					$("input[name='label']").val(result.data.label);
					$("input[name='firstName']").val(result.data.firstName);
					$("input[name='lastName']").val(result.data.lastName);
					$("input[name='degree']").val(result.data.degree);
					$("input[name='birthdate']").val(result.data.birthDate);
					$("textarea[name='abstract']").val(result.data.abstract);
					$("input[name='thumbnailuri']").val(result.data.thumbnailURI);
					$("input[name='thumbnailcreator']").val(result.data.thumbnailCreator);
					$("input[name='thumbnaillicense']").val(result.data.thumbnailLicense);
					$("input[name='sourceuri']").val(result.data.sourceURI);
					$("input[name='embeduri']").val(result.data.embedURI);
					$("input[name='websiteuri']").val(result.data.websiteURI);
					//$("input[name='originid']").val(result.data.originID);
					$("textarea[name='additionalinformation']").val(JSON.stringify(result.data.additionalInformation));

					$("select[name='gender']").val(result.data.gender);
					$("select[name='party']").val(result.data.partyID);
					$("select[name='faction']").val(result.data.factionID);

					for (var i = 0; i < result.data.labelAlternative.length; i++) {
						$("button.labelAlternativeAdd").next("div").append('<span style="position: relative">' +
							'<input type="text" class="form-control" name="labelAlternative[]" value="'+ result.data.labelAlternative[i] +'">' +
							'<button class="labelAlternativeRemove btn" style="position: absolute;top:0px;right:0px;" type="button">' +
							'<span class="icon-cancel-circled"></span>' +
							'</button></span>');
					}

					for (var i = result.data.socialMediaIDs.length - 1; i >= 0; i--) {
						$("button.socialMediaIDsAdd").next("div").append('<div style="position: relative" class="form-row">\n' +
				'            <div class="col">' +
				'               <input type="text" class="form-control" name="socialMediaIDsLabel[]" placeholder="Label (e.g. facebook)" value="'+ result.data.socialMediaIDs[i].label +'">' +
				'            </div>\n' +
				'            <div class="col">' +
				'               <input type="text" class="form-control" name="socialMediaIDsValue[]" placeholder="Value (name)" value="'+ result.data.socialMediaIDs[i].id +'">\n' +
				'            </div>\n' +
				'            <button class="socialMediaIDsRemove btn" style="position: absolute;top:0px;right:0px;" type="button">\n' +
				'                <span class="icon-cancel-circled"></span>\n' +
				'            </button>\n' +
				'        </div>');
					}
					
				}
			});

		});


	});

	function resetForm() {
		$('button.socialMediaIDsRemove, button.labelAlternativeRemove').parent().remove();
		$('input[type="text"]:not([name="id"]), textarea, select:not([name="itemType"]):not([name="type"])').val('');
	}
</script>

<?php
    include_once(__DIR__ . '/../../../footer.php');
}
?>