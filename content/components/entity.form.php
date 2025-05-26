<?php
// Component: entity-form.php

//TODO: Check if fine to access this without auth check. But should be fine since all API actions are then checked anyway. 

require_once(__DIR__ . '/../../api/v1/api.php'); 

?>
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
                    <button type="button" class="btn btn-sm input-group-text entitiesToggleDetailsAndTable mb-3" id="reimportSessionsButton">
                        <span class="icon-plus"></span>
                        <span class="d-none d-md-inline">Yes</span>
                    </button>
                    TODO: Checkbox to remove EntitySuggestion
                </form>
            </div>
        </div>
    </div>
</div>
<div id="entityAddDiv" class="contentContainer">
    <form id="entityAddForm" method="post">
        <input name="action" value="addItem" type="hidden">
        <input name="sourceEntitySuggestionID" value="" type="hidden">

        <!-- Wikidata ID Row (Moved to top) -->
        <div class="row mb-4">
            <div class="col-12">
                 <div class="form-group">
                    <label for="id">Paste Wikidata ID</label>
                    <input type="text" class="form-control" name="id" placeholder="Wikidata ID (e.g. Q12345)">
                </div>
            </div>
        </div>

        <!-- Entity Type Radio Buttons -->
        <div class="row">

            <!-- Person -->
            <div class="col-3 mb-3 text-center entity-type-selection-item">
                <label for="itemTypePerson" class="entity-type-label d-block">
                    <span class="icon-type-person entity-type-icon d-block mb-1"></span>
                    <span class="entity-type-text d-block">Person</span>
                </label>
                <input class="form-check-input entity-type-radio mt-2" type="radio" name="itemType" id="itemTypePerson" value="person">
            </div>

            <!-- Organisation -->
            <div class="col-3 mb-3 text-center entity-type-selection-item">
                <label for="itemTypeOrganisation" class="entity-type-label d-block">
                    <span class="icon-type-organisation entity-type-icon d-block mb-1"></span>
                    <span class="entity-type-text d-block">Organisation</span>
                </label>
                <input class="form-check-input entity-type-radio mt-2" type="radio" name="itemType" id="itemTypeOrganisation" value="organisation">
            </div>

            <!-- Document -->
            <div class="col-3 mb-3 text-center entity-type-selection-item">
                <label for="itemTypeDocument" class="entity-type-label d-block">
                    <span class="icon-type-document entity-type-icon d-block mb-1"></span>
                    <span class="entity-type-text d-block">Document</span>
                </label>
                <input class="form-check-input entity-type-radio mt-2" type="radio" name="itemType" id="itemTypeDocument" value="document">
            </div>

            <!-- Term -->
            <div class="col-3 mb-3 text-center entity-type-selection-item">
                <label for="itemTypeTerm" class="entity-type-label d-block">
                    <span class="icon-type-term entity-type-icon d-block mb-1"></span>
                    <span class="entity-type-text d-block">Term</span>
                </label>
                <input class="form-check-input entity-type-radio mt-2" type="radio" name="itemType" id="itemTypeTerm" value="term">
            </div>
        </div>

        <!-- Subtype Selects Row -->
        <div class="row mb-4">
            <div class="col-12 col-lg-3">
                <div id="subtypeContainerPerson" class="form-group subtype-container" style="display: none;">
                    <select class="form-select" name="type" id="typePerson">
                        <option value="" disabled selected>Choose Subtype ..</option>
                        <option value="memberOfParliament">Member Of Parliament</option>
                        <option value="person">Person</option>
                    </select>
                </div>
            </div>
            <div class="col-12 col-lg-3">
                <div id="subtypeContainerOrganisation" class="form-group subtype-container" style="display: none;">
                    <select class="form-select" name="type" id="typeOrganisation">
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
            <div class="col-12 col-lg-3">
                <div id="subtypeContainerDocument" class="form-group subtype-container" style="display: none;">
                    <select class="form-select" name="type" id="typeDocument">
                        <option value="" disabled selected>Choose Subtype ..</option>
                        <option value="officialDocument">officialDocument</option>
                        <option value="legalDocument">legalDocument</option>
                        <option value="otherDocument">Other Document</option>
                    </select>
                </div>
            </div>
            <div class="col-12 col-lg-3">
                <div id="subtypeContainerTerm" class="form-group subtype-container" style="display: none;">
                    <select class="form-select" name="type" id="typeTerm">
                        <option value="" disabled selected>Choose Subtype ..</option>
                        <option value="otherTerm">Other Term</option>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12">
                <div id="getAdditionalInfoError" class="alert alert-danger" style="display: none; margin-bottom: 10px;"></div>
                <button type="button" class="btn btn-primary w-100 py-3" id="getAdditionalInfo" disabled><span class="icon-magic"></span> Get data and auto-fill form fields</button>
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
                    <select class="form-select" name="gender">
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
                    <select class="form-select" name="party">
                        <option value="" disabled selected>Choose Party ..</option>
                        <?php
                        // This require_once might be better at the top of the file if other PHP logic needs it.
                        require_once (__DIR__."/../../api/v1/modules/organisation.php");
                        $partyie = organisationSearch(array("type"=>"party"));
                        foreach ($partyie["data"] as $party) {
                            echo '<option value="'.$party["id"].'">'.$party["attributes"]["label"].'</option>';
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group formItem formItemTypePerson">
                    <label for="faction">Faction</label>
                    <select class="form-select" name="faction">
                        <option value="" disabled selected>Choose Faction ..</option>
                        <?php
                        // This require_once might be better at the top of the file if other PHP logic needs it.
                        // require_once (__DIR__."/../../api/v1/modules/organisation.php");
                        $factionie = organisationSearch(array("type"=>"faction"));
                        foreach ($factionie["data"] as $key => $value) {
                            echo "<option value=\"".$value["OrganisationID"] ."\">" . $value["OrganisationLabel"] . "</option>";
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
                    <button class="btn btn-outline-primary rounded-pill" id="entityAddFormSubmitBtn" type="submit" disabled style="display: none;"><span class="icon-upload"></span> Add Entity</button>
                </div>
            </div>
        </div>
    </form>
</div>

<style>
	.form-item {
		display: none;
	}
	.entity-type-icon {
		font-size: 1.8em;
		line-height: 1;
	}
	.entity-type-label {
		cursor: pointer;
	}
</style>

<script type="text/javascript">
// All script logic is now contained within this $(function(){}) for the specific form instance.
	$(function() {

		const $form = $('#entityAddForm'); 
        if (!$form.length) {
            return; 
        }
        const $getAdditionalInfoBtn = $form.find("#getAdditionalInfo");
        const $getAdditionalInfoError = $form.find("#getAdditionalInfoError");
        const $submitBtn = $form.find("#entityAddFormSubmitBtn");

        // --- Functions scoped to this form instance ---
        function updateGetAdditionalInfoButtonState() {
            const wikidataID = $form.find('input[name="id"]').val().trim();
            const entityType = $form.find("input[name='itemType']:checked").val();
            let subType = "";

            if (entityType) {
                switch (entityType) {
                    case "person":
                        subType = $form.find("#typePerson:not(:disabled)").val();
                        break;
                    case "organisation":
                        subType = $form.find("#typeOrganisation:not(:disabled)").val();
                        break;
                    case "document":
                        subType = $form.find("#typeDocument:not(:disabled)").val();
                        break;
                    case "term":
                        subType = $form.find("#typeTerm:not(:disabled)").val();
                        break;
                }
            }
            
            if (wikidataID && entityType && subType) {
                $getAdditionalInfoBtn.prop("disabled", false);
            } else {
                $getAdditionalInfoBtn.prop("disabled", true);
            }
        }

        function initializeFormDisplay() {
            // Determine the selected entity type from radio buttons
            const selectedType = $form.find("input[name='itemType']:checked").val();

            // Hide all subtype containers and disable their selects initially
            $form.find(".subtype-container").hide().find("select").prop("disabled", true);

            let tempItem = ".not"; // CSS class selector for specific form items

            if (selectedType) {
                // Show the corresponding subtype container and enable its select
                switch (selectedType) {
                    case "person":
                        $("#subtypeContainerPerson").show().find("select").prop("disabled", false);
                        tempItem = ".formItemTypePerson";
                        break;
                    case "organisation":
                        $("#subtypeContainerOrganisation").show().find("select").prop("disabled", false);
                        tempItem = ".formItemTypeOrganisation";
                        break;
                    case "document":
                        $("#subtypeContainerDocument").show().find("select").prop("disabled", false);
                        tempItem = ".formItemTypeDocument";
                        break;
                    case "term":
                        $("#subtypeContainerTerm").show().find("select").prop("disabled", false);
                        tempItem = ".formItemTypeTerm";
                        break;
                }
            }

            // General form item visibility based on selectedType
            $form.find(".formItem").hide().find("input, textarea, select").prop("disabled", true);

            if (selectedType) {
                // Show common items
                const $commonItems = $form.find(".formItemTypePerson.formItemTypeOrganisation.formItemTypeDocument.formItemTypeTerm");
                $commonItems.show().find("input, textarea, select").prop("disabled", false);
                
                // Show type-specific items
                const $specificItems = $form.find(tempItem);
                $specificItems.show().find("input, textarea, select").prop("disabled", false);
                
                // Ensure only the active subtype select (handled above by enabling only one) is considered for submission.
                // The old logic for disabling other subtype selects by name 'type' is now handled by the subtype container logic.
            } else {
                 // If no type is selected, ensure all specific and common form items are hidden and disabled.
                 $form.find(".formItem[class*='formItemType']").hide().find("input, textarea, select").prop("disabled",true);
            }
            updateGetAdditionalInfoButtonState(); // Check button state on init/change
        }

        function resetForm() {
            // Uncheck radio buttons
            $form.find('input[name="itemType"]').prop('checked', false);
            
            // Clear text inputs, textareas (excluding special ones)
            $form.find('input[type="text"]').not('[name="id"], [name="sourceEntitySuggestionID"]').val('');
            $form.find('textarea').val('');
            
            // Reset select elements (excluding special ones, subtype selects are handled by initializeFormDisplay)
            $form.find('select').not('[name="typePerson"], [name="typeOrganisation"], [name="typeDocument"], [name="typeTerm"]').val(''); // Reset general selects
            
            // Clear dynamically added alternative labels and social media IDs
            $form.find('button.socialMediaIDsRemove, button.labelAlternativeRemove').parent().remove();
            
            // Call initializeFormDisplay to reset visibility based on no type selected (hides subtype containers)
            initializeFormDisplay(); 
            $form.find("#entityAddReturn").empty();
            $getAdditionalInfoBtn.removeClass('btn-success').prop('disabled', true); // Reset button state
            $getAdditionalInfoError.hide().empty(); // Hide error message
            $submitBtn.prop('disabled', true); // Disable submit button
        }

        // --- Initialization logic for this instance ---
        const initialWikidataID = $form.data('wikidata-id');
        const initialEntitySuggestionID = $form.data('entity-suggestion-id');

        if (initialWikidataID) {
            $form.find('input[name="id"]').val(initialWikidataID);
        }
        if (initialEntitySuggestionID) {
            $form.find('input[name="sourceEntitySuggestionID"]').val(initialEntitySuggestionID);
        }
        
        // Initial call to set up form display based on any pre-selected radio or default state
        initializeFormDisplay(); 

		$form.ajaxForm({
			url: config.dir.root +"/api/v1/",
			dataType: "json",
			success: function (ret) {

				$("#entityAddReturn").empty();
				$("#entityAddForm input, #entityAddForm select, #entityAddForm textarea").css("border", ""); 

				if (ret["meta"]["requestStatus"] != "success") {
					for (let error in ret["errors"]) {
						$("#entityAddReturn").append('<div>' + ret["errors"][error]["title"] + '</div>');
                        if (ret["errors"][error]["meta"] && ret["errors"][error]["meta"]["domSelector"]) {
                            $(ret["errors"][error]["meta"]["domSelector"]).css("border", "1px solid red");
                        } else if ("label" in ret["errors"][error]) { 
							$("[name='" + ret["errors"][error]["label"] + "']").css("border", "1px solid red");
						}
					}
				} else {

					$("#affectedSessions").empty();

                    $("#entityAddDiv").slideUp(); 
					$("#entityAddSuccess").slideDown(); 

					if (ret.meta && "EntitysuggestionItem" in ret.meta && ret.meta.EntitysuggestionItem && typeof ret.meta.EntitysuggestionItem === 'object' && ret.meta.EntitysuggestionItem.EntitysuggestionID) {
                        $("#reimportSessionForm").data("entitysuggestionid", ret.meta.EntitysuggestionItem.EntitysuggestionID);

						if (ret.meta && "affectedSessions" in ret.meta && (Object.keys(ret.meta.affectedSessions).length > 0)) {
							for (let parliament in ret.meta.affectedSessions) {
								let sessioncontent = "";
								for (let session in ret.meta.affectedSessions[parliament]) {
									sessioncontent += "<div class='sessionFilesDiv'>" + session + " | File exists: " + ret.meta.affectedSessions[parliament][session]["fileExists"] + "<input type='hidden' name='files[" + parliament + "][]' class='reimportfile' value='" + session + "'>";
								}
                                $("#entityAddSuccess #affectedSessions").append("<div class='parlamentDiv'><h4>Parlament " + parliament + "</h4>" + sessioncontent + "</div>");
							}
							$("#entityAddSuccess #affectedSessions_true").show();
							$("#entityAddSuccess #affectedSessions_false").hide();
						} else {
							$("#entityAddSuccess #affectedSessions_false").show();
							$("#entityAddSuccess #affectedSessions_true").hide();
						}

					} else {
						$("#entityAddSuccess #affectedSessions_false").show();
						$("#entityAddSuccess #affectedSessions_true").hide();
					}
                    

				}
			}
		});

        
        $("body").on("click", "#reimportSessionsButton", function() { 
            let entitySuggestionID = $("#reimportSessionForm").data("entitysuggestionid");
            let filesData = $("#reimportSessionForm").serialize(); 

            let apiPayload = {
                action: "import",
                itemType: "reimport-sessions",
                EntitysuggestionID: entitySuggestionID 
            };
            
            let payloadString = $.param(apiPayload) + "&" + filesData;

            $.ajax({
                url: config.dir.root + "/api/v1/",
                type: "POST",
                dataType: "json",
                data: payloadString,
                success: function(response) {
                    let message = "Reimport process finished.";
                    if (response.meta && response.meta.requestStatus === "success") {
                        if (response.meta.summary) {
                            message = response.meta.summary;
                        } else if (response.data && response.data.copied && response.data.failed && response.data.skipped) {
                            message = response.data.copied.length + " file(s) re-imported, " + 
                                      response.data.failed.length + " failed, " + 
                                      response.data.skipped.length + " skipped.";
                        }
                        alert("Reimport successful: " + message);
                    } else {
                        message = "Reimport failed.";
                        if (response.errors && response.errors.length > 0) {
                            message += " " + response.errors[0].title + (response.errors[0].detail ? (": " + response.errors[0].detail) : "");
                        }
                        alert(message);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert("An error occurred during reimport: " + textStatus + " - " + errorThrown);
                }
            });
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
				'                                           <input type="text" class="form-control" name="socialMediaIDsValue[]" placeholder="Value (name)">' +
				'                                        </div>\n' +
				'                                        <button class="socialMediaIDsRemove btn" style="position: absolute;top:0px;right:0px;" type="button">\n' +
				'                                            <span class="icon-cancel-circled"></span>\n' +
				'                                        </button>\n' +
				'                                    </div>');
		});

		$("body").on("click", ".socialMediaIDsRemove", function() {
			$(this).parent().remove();
		});

        // Listen for changes on relevant fields to update button state
        $form.find('input[name="id"], input[name="itemType"], #typePerson, #typeOrganisation, #typeDocument, #typeTerm').on("change keyup", function() {
            updateGetAdditionalInfoButtonState();
            // If user changes criteria, reset success state of getAdditionalInfoBtn and disable submit
            $getAdditionalInfoBtn.removeClass('btn-success');
            $submitBtn.prop('disabled', true);
        });

		// Listen for changes on radio buttons for itemType
		$form.find("input[name='itemType']").on("change", function() {
            initializeFormDisplay(); 
		});

		$form.find("#getAdditionalInfo").click(function(evt) {
			
            $getAdditionalInfoBtn.addClass('working').removeClass('btn-success').prop('disabled', true);
            $getAdditionalInfoError.hide().empty();
            $submitBtn.prop('disabled', true); // Keep submit disabled until success

			// resetForm(); // Decided against full reset, user might want to keep Wikidata ID

			// Get entityType from the checked radio button
			let entityType = $form.find("input[name='itemType']:checked").val();
			// Get subType from the currently visible and enabled select within its container
            let subType = "";
            if (entityType === "person") {
                subType = $form.find("#typePerson:not(:disabled)").val();
            } else if (entityType === "organisation") {
                subType = $form.find("#typeOrganisation:not(:disabled)").val();
            } else if (entityType === "document") {
                subType = $form.find("#typeDocument:not(:disabled)").val();
            } else if (entityType === "term") {
                subType = $form.find("#typeTerm:not(:disabled)").val();
            }

			let serviceType = entityType;
			if (subType == "memberOfParliament" || subType == "officialDocument" || subType == "legalDocument") {
                serviceType = subType;
			}

			let wikidataID = $form.find('input[name="id"]').val();

            if (!entityType) { alert("Please select an Entity Type first."); return; }
            // Subtype is optional for fetching additional info, but Wikidata ID is mandatory
            if (!wikidataID) { alert("Please enter a Wikidata ID first."); return; }


			$.ajax({
				url:config.dir.root+"/server/ajaxServer.php", 
				data: {
					"a": "entityGetFromAdditionalDataService",
					"type": serviceType,
					"wikidataID": wikidataID
				},
                dataType: "json", 
                complete: function() {
                    $getAdditionalInfoBtn.removeClass('working');
                    // Re-enable button based on current form validity, in case user wants to try again after fixing input
                    updateGetAdditionalInfoButtonState(); 
                },
				success: function(result) {
					if (result && result.data) { 
                        $getAdditionalInfoBtn.addClass('btn-success');
                        $submitBtn.prop('disabled', false); // Enable submit on success

                        if (result.data.label) $form.find('input[name="label"]').val(result.data.label);
                        if (result.data.firstName) $form.find('input[name="firstName"]').val(result.data.firstName);
                        if (result.data.lastName) $form.find('input[name="lastName"]').val(result.data.lastName);
                        if (result.data.degree) $form.find('input[name="degree"]').val(result.data.degree);
                        if (result.data.birthDate) $form.find('input[name="birthdate"]').val(result.data.birthDate);
                        if (result.data.abstract) $form.find('textarea[name="abstract"]').val(result.data.abstract);
                        if (result.data.thumbnailURI) $form.find('input[name="thumbnailuri"]').val(result.data.thumbnailURI);
                        if (result.data.thumbnailCreator) $form.find('input[name="thumbnailcreator"]').val(result.data.thumbnailCreator);
                        if (result.data.thumbnailLicense) $form.find('input[name="thumbnaillicense"]').val(result.data.thumbnailLicense);
                        if (result.data.sourceURI) $form.find('input[name="sourceuri"]').val(result.data.sourceURI); 
                        if (result.data.embedURI) $form.find('input[name="embeduri"]').val(result.data.embedURI);
                        if (result.data.websiteURI) $form.find('input[name="websiteuri"]').val(result.data.websiteURI);
                        if (result.data.additionalInformation) $form.find('textarea[name="additionalinformation"]').val(JSON.stringify(result.data.additionalInformation));

                        if (result.data.gender) $form.find('select[name="gender"]').val(result.data.gender);
                        if (result.data.partyID) $form.find('select[name="party"]').val(result.data.partyID);
                        if (result.data.factionID) $form.find('select[name="faction"]').val(result.data.factionID);

                        $form.find('button.labelAlternativeAdd').parent().find("div:first").empty();
                        if (result.data.labelAlternative && Array.isArray(result.data.labelAlternative)) {
                            for (var i = 0; i < result.data.labelAlternative.length; i++) {
                                $form.find('button.labelAlternativeAdd').parent().find("div:first").append('<span style="position: relative">' +
                                    '<input type="text" class="form-control" name="labelAlternative[]" value="'+ result.data.labelAlternative[i] +'"/>' +
                                    '<button class="labelAlternativeRemove btn" style="position: absolute;top:0px;right:0px;" type="button">' +
                                    '<span class="icon-cancel-circled"></span>' +
                                    '</button></span>');
                            }
                        }
                        
                        $form.find('button.socialMediaIDsAdd').parent().find("div:first").empty();
                        if (result.data.socialMediaIDs && Array.isArray(result.data.socialMediaIDs)) {
                            for (var i = 0; i < result.data.socialMediaIDs.length; i++) { 
                                $form.find('button.socialMediaIDsAdd').parent().find("div:first").append('<div style="position: relative" class="form-row">\n' +
                        '            <div class="col">' +
                        '               <input type="text" class="form-control" name="socialMediaIDsLabel[]" placeholder="Label (e.g. facebook)" value="'+ result.data.socialMediaIDs[i].label +'"/>' +
                        '            </div>\n' +
                        '            <div class="col">' +
                        '               <input type="text" class="form-control" name="socialMediaIDsValue[]" placeholder="Value (name)" value="'+ result.data.socialMediaIDs[i].id +'"/>\n' +
                        '            </div>\n' +
                        '            <button class="socialMediaIDsRemove btn" style="position: absolute;top:0px;right:0px;" type="button">\n' +
                        '                <span class="icon-cancel-circled"></span>\n' +
                        '            </button>\n' +
                        '        </div>');
                            }
                        }
                    } else if (result && result.text) { 
                         $getAdditionalInfoError.text("Could not fetch additional data: " + result.text).show();
                         $getAdditionalInfoBtn.removeClass('btn-success'); // Ensure not success
                    } else {
                        $getAdditionalInfoError.text("Could not fetch additional data. Response was unexpected.").show();
                        $getAdditionalInfoBtn.removeClass('btn-success'); // Ensure not success
                    }
				},
                error: function(jqXHR, textStatus, errorThrown) {
                    $getAdditionalInfoError.text("Error fetching additional data: " + textStatus + " - " + errorThrown).show();
                    $getAdditionalInfoBtn.removeClass('btn-success'); // Ensure not success
                }
			});

		});

	});
</script>

</div> 