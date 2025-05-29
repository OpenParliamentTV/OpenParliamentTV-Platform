<?php
// Component: entity-form.php

//TODO: Check if fine to access this without auth check. But should be fine since all API actions are then checked anyway. 

require_once(__DIR__ . '/../../api/v1/api.php'); 
require_once(__DIR__."/../../modules/utilities/language.php");

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
                <div id="getAdditionalInfoError" class="alert alert-danger d-none mb-3"></div>
                <div id="entityPreviewContainer" class="p-2 border rounded d-none position-relative" style="min-height: 100px;">
                    <div class="loadingIndicator d-none">
                        <div class="workingSpinner"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="form-group formItem formItemTypePerson formItemTypeOrganisation formItemTypeDocument formItemTypeTerm d-none" style="position: relative">
                    <label for="label">Label</label>
                    <input type="text" class="form-control" name="label">
                </div>
                <div class="form-group formItem formItemTypePerson formItemTypeOrganisation formItemTypeDocument formItemTypeTerm d-none">
                    <label for="labelAlternative[]">Alternative Labels</label> <button class="labelAlternativeAdd btn" type="button"><span class="icon-plus"></span></button>
                    <div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-12 col-lg-6">
                <div class="form-group formItem formItemTypePerson d-none">
                    <label for="firstName">First Name</label>
                    <input type="text" class="form-control" name="firstName">
                </div>
            </div>
            <div class="col-12 col-lg-6">
                <div class="form-group formItem formItemTypePerson d-none">
                    <label for="lastName">Last Name</label>
                    <input type="text" class="form-control" name="lastName">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-12 col-lg-4">
                <div class="form-group formItem formItemTypePerson d-none">
                    <label for="degree">Degree</label>
                    <input type="text" class="form-control" name="degree">
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="form-group formItem formItemTypePerson d-none">
                    <label for="birthdate">Date Of Birth</label>
                    <input type="text" class="form-control" name="birthdate" placeholder="YYYY-MM-DD">
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="form-group formItem formItemTypePerson d-none">
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
                <div class="form-group formItem formItemTypePerson formItemTypeOrganisation formItemTypeDocument formItemTypeTerm d-none">
                    <label for="abstract">Abstract</label>
                    <textarea class="form-control" name="abstract"></textarea>
                </div>
                <div class="form-group formItem formItemTypePerson formItemTypeOrganisation formItemTypeDocument formItemTypeTerm d-none">
                    <label for="thumbnailuri">Thumbnail URI</label>
                    <input type="text" class="form-control" name="thumbnailuri">
                </div>
                <div class="form-group formItem formItemTypePerson formItemTypeOrganisation formItemTypeDocument formItemTypeTerm d-none">
                    <label for="thumbnailcreator">Thumbnail Creator</label>
                    <input type="text" class="form-control" name="thumbnailcreator">
                </div>
                <div class="form-group formItem formItemTypePerson formItemTypeOrganisation formItemTypeDocument formItemTypeTerm d-none">
                    <label for="thumbnaillicense">Thumbnail License</label>
                    <input type="text" class="form-control" name="thumbnaillicense">
                </div>
                <div class="form-group formItem formItemTypeDocument d-none">
                    <label for="sourceuri">Source URI</label>
                    <input type="text" class="form-control" name="sourceuri">
                </div>
                <div class="form-group formItem formItemTypePerson formItemTypeOrganisation formItemTypeDocument formItemTypeTerm d-none">
                    <label for="embeduri">Embed URI</label>
                    <input type="text" class="form-control" name="embeduri">
                </div>
                <div class="form-group formItem formItemTypePerson formItemTypeOrganisation formItemTypeTerm d-none">
                    <label for="websiteuri">Website URI</label>
                    <input type="text" class="form-control" name="websiteuri">
                </div>
                <div class="form-group formItem formItemTypePerson d-none">
                    <label for="originid">Original ID</label>
                    <input type="text" class="form-control" name="originid">
                </div>
                <div class="row mt-3">
                    <div class="col-6">
                        <div class="form-group formItem formItemTypePerson d-none">
                            <label for="party">Party</label>
                            <select class="form-select" name="party">
                                <option value="" disabled selected>Choose Party ..</option>
                                <?php
                                require_once (__DIR__."/../../api/v1/modules/organisation.php");
                                $partyie = organisationSearch(array("type"=>"party"));
                                foreach ($partyie["data"] as $party) {
                                    echo '<option value="'.$party["id"].'">'.$party["attributes"]["label"].'</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group formItem formItemTypePerson d-none">
                            <label for="faction">Faction</label>
                            <select class="form-select" name="faction">
                                <option value="" disabled selected>Choose Faction ..</option>
                                <?php
                                $factionie = organisationSearch(array("type"=>"faction"));
                                foreach ($factionie["data"] as $faction) {
                                    echo '<option value="'.$faction["id"].'">'.$faction["attributes"]["label"].'</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-group form-dyncontent formItem formItemTypePerson formItemTypeOrganisation d-none">
                    <label for="socialMediaIDsLabel[]">Social Media Accounts</label> <button class="socialMediaIDsAdd btn" type="button"><span class="icon-plus"></span></button>
                    <div>
                    </div>
                </div>
                <div class="form-group formItem formItemTypeOrganisation d-none">
                    <label for="color">Color</label>
                    <input type="text" class="form-control" name="color">
                </div>
                <div class="form-group formItem formItemTypePerson formItemTypeOrganisation formItemTypeDocument formItemTypeTerm d-none">
                    <label for="additionalinformation">Additional Information (JSON)</label>
                    <textarea class="form-control" name="additionalinformation" placeholder='{"abgeordnetenwatchID":""}'></textarea>
                </div>
                <div id="entityAddReturn" class="alert d-none mt-3 mb-0"></div>
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
        const $getAdditionalInfoError = $form.find("#getAdditionalInfoError");
        const $previewContainer = $form.find("#entityPreviewContainer");
        const $loadingIndicator = $previewContainer.find(".loadingIndicator");

        // --- Functions scoped to this form instance ---
        function fetchAdditionalInfo() {
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
                $getAdditionalInfoError.addClass('d-none').empty();
                $previewContainer.removeClass('d-none');
                $loadingIndicator.removeClass('d-none');
                $('#modalAddEntitySubmitBtnEntitiesPage').prop('disabled', true);

                let serviceType = entityType;
                if (subType == "memberOfParliament" || subType == "officialDocument" || subType == "legalDocument") {
                    serviceType = subType;
                }

                $.ajax({
                    url: config.dir.root+"/server/ajaxServer.php", 
                    data: {
                        "a": "entityGetFromAdditionalDataService",
                        "type": serviceType,
                        "wikidataID": wikidataID
                    },
                    dataType: "json",
                    complete: function() {
                        $loadingIndicator.addClass('d-none');
                    },
                    success: function(result) {
                        if (result && result.data) { 
                            $('#modalAddEntitySubmitBtnEntitiesPage').prop('disabled', false);

                            // Transform the data into the format expected by entity.preview.ads.php
                            const entityData = {
                                data: {
                                    type: entityType,
                                    id: wikidataID,
                                    attributes: result.data
                                }
                            };

                            // Load the preview component
                            $loadingIndicator.removeClass('d-none');
                            $.ajax({
                                url: config.dir.root + '/content/components/entity.preview.ads.php',
                                method: 'POST',
                                data: { entity: JSON.stringify(entityData) },
                                success: function(html) {
                                    $previewContainer.html(html);
                                    $previewContainer.append($loadingIndicator);
                                },
                                complete: function() {
                                    $loadingIndicator.addClass('d-none');
                                }
                            });

                            // Populate form fields
                            const fieldMappings = {
                                'label': 'label',
                                'firstName': 'firstName',
                                'lastName': 'lastName',
                                'degree': 'degree',
                                'birthdate': 'birthDate',
                                'abstract': 'abstract',
                                'thumbnailuri': 'thumbnailURI',
                                'thumbnailcreator': 'thumbnailCreator',
                                'thumbnaillicense': 'thumbnailLicense',
                                'sourceuri': 'sourceURI',
                                'embeduri': 'embedURI',
                                'websiteuri': 'websiteURI',
                                'additionalinformation': 'additionalInformation'
                            };

                            Object.entries(fieldMappings).forEach(([formField, dataField]) => {
                                if (result.data[dataField]) {
                                    if (formField === 'additionalinformation') {
                                        $form.find(`textarea[name="${formField}"]`).val(JSON.stringify(result.data[dataField]));
                                    } else {
                                        $form.find(`input[name="${formField}"], textarea[name="${formField}"]`).val(result.data[dataField]);
                                    }
                                }
                            });

                            // Handle select fields
                            if (result.data.gender) $form.find('select[name="gender"]').val(result.data.gender);
                            if (result.data.partyID) $form.find('select[name="party"]').val(result.data.partyID);
                            if (result.data.factionID) $form.find('select[name="faction"]').val(result.data.factionID);

                            // Handle alternative labels
                            $form.find('button.labelAlternativeAdd').parent().find("div:first").empty();
                            if (result.data.labelAlternative && Array.isArray(result.data.labelAlternative)) {
                                result.data.labelAlternative.forEach(label => {
                                    $form.find('button.labelAlternativeAdd').parent().find("div:first").append(
                                        '<span style="position: relative">' +
                                        '<input type="text" class="form-control" name="labelAlternative[]" value="'+ label +'"/>' +
                                        '<button class="labelAlternativeRemove btn" style="position: absolute;top:0px;right:0px;" type="button">' +
                                        '<span class="icon-cancel-circled"></span>' +
                                        '</button></span>'
                                    );
                                });
                            }
                            
                            // Handle social media IDs
                            $form.find('button.socialMediaIDsAdd').parent().find("div:first").empty();
                            if (result.data.socialMediaIDs && Array.isArray(result.data.socialMediaIDs)) {
                                result.data.socialMediaIDs.forEach(socialMedia => {
                                    $form.find('button.socialMediaIDsAdd').parent().find("div:first").append(
                                        '<div style="position: relative" class="form-row">' +
                                        '<div class="col">' +
                                        '<input type="text" class="form-control" name="socialMediaIDsLabel[]" placeholder="Label (e.g. facebook)" value="'+ socialMedia.label +'"/>' +
                                        '</div>' +
                                        '<div class="col">' +
                                        '<input type="text" class="form-control" name="socialMediaIDsValue[]" placeholder="Value (name)" value="'+ socialMedia.id +'"/>' +
                                        '</div>' +
                                        '<button class="socialMediaIDsRemove btn" style="position: absolute;top:0px;right:0px;" type="button">' +
                                        '<span class="icon-cancel-circled"></span>' +
                                        '</button>' +
                                        '</div>'
                                    );
                                });
                            }
                        } else if (result && result.text) { 
                            $getAdditionalInfoError.text("Could not fetch additional data: " + result.text).removeClass('d-none');
                            $('#modalAddEntitySubmitBtnEntitiesPage').prop('disabled', true);
                        } else {
                            $getAdditionalInfoError.text("Could not fetch additional data. Response was unexpected.").removeClass('d-none');
                            $('#modalAddEntitySubmitBtnEntitiesPage').prop('disabled', true);
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        $getAdditionalInfoError.text("Error fetching additional data: " + textStatus + " - " + errorThrown).removeClass('d-none');
                        $('#modalAddEntitySubmitBtnEntitiesPage').prop('disabled', true);
                    }
                });
            } else {
                $previewContainer.addClass('d-none');
                $('#modalAddEntitySubmitBtnEntitiesPage').prop('disabled', true);
            }
        }

        // Initial button state
        $('#modalAddEntitySubmitBtnEntitiesPage').prop('disabled', true);

        function initializeFormDisplay() {
            // Determine the selected entity type from radio buttons
            const selectedType = $form.find("input[name='itemType']:checked").val();

            // Hide all subtype containers and disable their selects initially
            $form.find(".subtype-container").hide().find("select").prop("disabled", true);

            let tempItem = ".not"; // CSS class selector for specific form items

            // Hide all form items below warning line
            $form.find(".formItem").addClass("d-none");

            if (selectedType) {
                // Show the corresponding subtype container and enable its select
                switch (selectedType) {
                    case "person":
                        $("#subtypeContainerPerson").show().find("select").prop("disabled", false);
                        tempItem = ".formItemTypePerson";
                        // Show only party and faction selects for person type
                        $form.find("select[name='party']").closest(".formItem").removeClass("d-none");
                        $form.find("select[name='faction']").closest(".formItem").removeClass("d-none");
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

            // Only enable/disable inputs, don't change visibility
            $form.find(".formItem").find("input, textarea, select").prop("disabled", true);

            if (selectedType) {
                // Enable common items
                const $commonItems = $form.find(".formItemTypePerson.formItemTypeOrganisation.formItemTypeDocument.formItemTypeTerm");
                $commonItems.find("input, textarea, select").prop("disabled", false);
                
                // Enable type-specific items
                const $specificItems = $form.find(tempItem);
                $specificItems.find("input, textarea, select").prop("disabled", false);
            }
            
            fetchAdditionalInfo(); // Add this line to trigger preview fetch when type changes
        }

        // Listen for changes on radio buttons for itemType
        $form.find("input[name='itemType']").on("change", function() {
            initializeFormDisplay(); 
        });

        // Listen for changes on relevant fields to update preview
        $form.find('input[name="id"], #typePerson, #typeOrganisation, #typeDocument, #typeTerm').on("change keyup", function() {
            fetchAdditionalInfo();
        });

        // Also enable button when form is valid
        $form.on('change keyup', 'input, select, textarea', function() {
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

            $('#modalAddEntitySubmitBtnEntitiesPage').prop('disabled', !(wikidataID && entityType && subType));
        });

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
            $form.find("#entityAddReturn").empty().addClass('d-none');
            $('#modalAddEntitySubmitBtnEntitiesPage').prop('disabled', true);
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

				$("#entityAddReturn").empty().addClass('d-none');
				$("#entityAddForm input, #entityAddForm select, #entityAddForm textarea").css("border", ""); 

				if (ret["meta"]["requestStatus"] != "success") {
                    $("#entityAddReturn").removeClass('d-none alert-success').addClass('alert-danger');
					for (let error in ret["errors"]) {
						$("#entityAddReturn").append('<div>' + ret["errors"][error]["title"] + '</div>');
                        if (ret["errors"][error]["meta"] && ret["errors"][error]["meta"]["domSelector"]) {
                            $(ret["errors"][error]["meta"]["domSelector"]).css("border", "1px solid red");
                        } else if ("label" in ret["errors"][error]) { 
							$("[name='" + ret["errors"][error]["label"] + "']").css("border", "1px solid red");
						}
					}
				} else {
                    $("#entityAddReturn").removeClass('d-none alert-danger').addClass('alert-success');
                    $("#entityAddReturn").text("Entity successfully added");

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

	});
</script>

</div> 