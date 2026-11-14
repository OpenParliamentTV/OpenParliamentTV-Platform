<?php
// NOTE: direct-access AJAX endpoint (fetched by the manage/entities page to
// populate the add/edit dialog) — must NOT have the OPTV guard; it bootstraps
// session/config itself below.
// Component: entity-form.php

//TODO: Check if fine to access this without auth check. But should be fine since all API actions are then checked anyway.

require_once(__DIR__ . "/../../bootstrap.php"); // session, config, headers, i18n, view globals
require_once(__DIR__ . '/../../api/v1/api.php');

?>
<div id="entityAddDiv" class="contentContainer">
    <form id="entityAddForm" method="post">
        <input name="action" value="addItem" type="hidden">
        <input name="sourceEntitySuggestionID" value="" type="hidden">

        <!-- Wikidata ID Row (Moved to top) -->
        <div class="row mb-4">
            <div class="col-12">
                 <div class="form-group">
                    <label for="id"><?= L::pasteWikidataID(); ?></label>
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
                    <span class="entity-type-text d-block"><?= L::personSingular(); ?></span>
                </label>
                <input class="form-check-input entity-type-radio mt-2" type="radio" name="itemType" id="itemTypePerson" value="person">
            </div>

            <!-- Organisation -->
            <div class="col-3 mb-3 text-center entity-type-selection-item">
                <label for="itemTypeOrganisation" class="entity-type-label d-block">
                    <span class="icon-type-organisation entity-type-icon d-block mb-1"></span>
                    <span class="entity-type-text d-block"><?= L::organisation(); ?></span>
                </label>
                <input class="form-check-input entity-type-radio mt-2" type="radio" name="itemType" id="itemTypeOrganisation" value="organisation">
            </div>

            <!-- Document -->
            <div class="col-3 mb-3 text-center entity-type-selection-item">
                <label for="itemTypeDocument" class="entity-type-label d-block">
                    <span class="icon-type-document entity-type-icon d-block mb-1"></span>
                    <span class="entity-type-text d-block"><?= L::document(); ?></span>
                </label>
                <input class="form-check-input entity-type-radio mt-2" type="radio" name="itemType" id="itemTypeDocument" value="document">
            </div>

            <!-- Term -->
            <div class="col-3 mb-3 text-center entity-type-selection-item">
                <label for="itemTypeTerm" class="entity-type-label d-block">
                    <span class="icon-type-term entity-type-icon d-block mb-1"></span>
                    <span class="entity-type-text d-block"><?= L::term(); ?></span>
                </label>
                <input class="form-check-input entity-type-radio mt-2" type="radio" name="itemType" id="itemTypeTerm" value="term">
            </div>
        </div>

        <!-- Subtype Selects Row -->
        <div class="row mb-4">
            <div class="col-12 col-lg-3">
                <div id="subtypeContainerPerson" class="form-group subtype-container" style="display: none;">
                    <select class="form-select" name="type" id="typePerson">
                        <option value="" disabled selected><?= L::selectType(); ?> ..</option>
                        <?php foreach ($config["entityTypes"]["person"] as $subtype): ?>
                            <option value="<?= hAttr($subtype) ?>"><?= h($subtype) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-12 col-lg-3">
                <div id="subtypeContainerOrganisation" class="form-group subtype-container" style="display: none;">
                    <select class="form-select" name="type" id="typeOrganisation">
                        <option value="" disabled selected><?= L::selectType(); ?> ..</option>
                        <?php foreach ($config["entityTypes"]["organisation"] as $subtype): ?>
                            <option value="<?= hAttr($subtype) ?>"><?= h($subtype) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-12 col-lg-3">
                <div id="subtypeContainerDocument" class="form-group subtype-container" style="display: none;">
                    <select class="form-select" name="type" id="typeDocument">
                        <option value="" disabled selected><?= L::selectType(); ?> ..</option>
                        <?php foreach ($config["entityTypes"]["document"] as $subtype): ?>
                            <option value="<?= hAttr($subtype) ?>"><?= h($subtype) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-12 col-lg-3">
                <div id="subtypeContainerTerm" class="form-group subtype-container" style="display: none;">
                    <select class="form-select" name="type" id="typeTerm">
                        <option value="" disabled selected><?= L::selectType(); ?> ..</option>
                        <?php foreach ($config["entityTypes"]["term"] as $subtype): ?>
                            <option value="<?= hAttr($subtype) ?>"><?= h($subtype) ?></option>
                        <?php endforeach; ?>
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
                <div id="formHintPartyFaction" class="formHint alert alert-warning d-none mt-3 mb-0 p-2"><?= L::messageSelectPartyFactionPerson(); ?>:</div>
                <div class="row mt-3">
                    <div class="col-6">
                        <div class="form-group formItem formItemTypePerson d-none">
                            <label for="party"><?= L::party(); ?></label>
                            <select class="form-select" name="party">
                                <option value="" disabled selected><?= L::selectParty(); ?> ..</option>
                                <?php
                                require_once (__DIR__."/../../api/v1/modules/organisation.php");
                                $partyie = organisationSearch(array("type"=>"party"));
                                foreach ($partyie["data"] as $party) {
                                    echo '<option value="'.hAttr($party["id"]).'">'.h($party["attributes"]["label"]).'</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group formItem formItemTypePerson d-none">
                            <label for="faction"><?= L::faction(); ?></label>
                            <select class="form-select" name="faction">
                                <option value="" disabled selected><?= L::selectFaction(); ?> ..</option>
                                <?php
                                $factionie = organisationSearch(array("type"=>"faction"));
                                foreach ($factionie["data"] as $faction) {
                                    echo '<option value="'.hAttr($faction["id"]).'">'.h($faction["attributes"]["label"]).'</option>';
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
                <div id="affectedSessionsContainer"></div>
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

        // Dynamic list rows, built as elements so that a quote in a Wikidata label cannot break out of the value attribute
        function labelAlternativeRow(value) {
            return $('<span style="position: relative"></span>')
                .append($('<input type="text" class="form-control" name="labelAlternative[]">').val(value || ''))
                .append('<button class="labelAlternativeRemove btn" style="position: absolute;top:0px;right:0px;" type="button"><span class="icon-cancel-circled"></span></button>');
        }

        function socialMediaRow(label, id) {
            return $('<div style="position: relative" class="form-row"></div>')
                .append($('<div class="col"></div>').append($('<input type="text" class="form-control" name="socialMediaIDsLabel[]" placeholder="Label (e.g. facebook)">').val(label || '')))
                .append($('<div class="col"></div>').append($('<input type="text" class="form-control" name="socialMediaIDsValue[]" placeholder="Value (name)">').val(id || '')))
                .append('<button class="socialMediaIDsRemove btn" style="position: absolute;top:0px;right:0px;" type="button"><span class="icon-cancel-circled"></span></button>');
        }

        // The inputs the preview depends on. Their key changes exactly when a new preview is needed.
        function readPreviewInputs() {
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

            return {
                wikidataID: wikidataID,
                entityType: entityType,
                subType: subType,
                key: [wikidataID, entityType || "", subType || ""].join("|")
            };
        }

        let lastPreviewKey = null;
        let previewTimer = null;

        // Debounced: typing an ID fires once per character, and every prefix of a Wikidata ID is itself a valid ID.
        function fetchAdditionalInfo() {
            clearTimeout(previewTimer);
            previewTimer = setTimeout(runFetchAdditionalInfo, 300);
        }

        function runFetchAdditionalInfo() {
            const inputs = readPreviewInputs();
            if (inputs.key === lastPreviewKey) {
                return;
            }
            lastPreviewKey = inputs.key;

            // A response for inputs that have changed since its request was sent is dropped
            const isCurrent = function() {
                return readPreviewInputs().key === inputs.key;
            };

            const wikidataID = inputs.wikidataID;
            const entityType = inputs.entityType;
            const subType = inputs.subType;

            if (wikidataID && entityType && subType) {
                $getAdditionalInfoError.addClass('d-none').empty();
                $previewContainer.removeClass('d-none');
                $loadingIndicator.removeClass('d-none');
                $('#modalAddEntitySubmitBtn').prop('disabled', true);

                let serviceType = entityType;
                if (subType == "memberOfParliament" || subType == "officialDocument" || subType == "legalDocument") {
                    serviceType = subType;
                }

                $.ajax({
                    url: config.dir.root+"/api/v1/",
                    data: {
                        "action": "externalData",
                        "itemType": "get-info",
                        "type": serviceType,
                        "wikidataID": wikidataID
                    },
                    dataType: "json",
                    complete: function() {
                        if (isCurrent()) {
                            $loadingIndicator.addClass('d-none');
                        }
                    },
                    success: function(response) {
                        if (!isCurrent()) {
                            return;
                        }
                        if (response && response.meta && response.meta.requestStatus === 'success' && response.data) { 
                            $('#modalAddEntitySubmitBtn').prop('disabled', false);

                            // Transform the data into the format expected by entity-preview-ads.php
                            const entityData = {
                                data: {
                                    type: entityType,
                                    id: wikidataID,
                                    attributes: response.data
                                }
                            };

                            // Load the preview component
                            $loadingIndicator.removeClass('d-none');
                            $.ajax({
                                url: config.dir.root + '/content/components/entity-preview-ads.php',
                                method: 'POST',
                                data: { entity: JSON.stringify(entityData) },
                                success: function(html) {
                                    if (!isCurrent()) {
                                        return;
                                    }
                                    $previewContainer.html(html);
                                    $previewContainer.append($loadingIndicator);
                                },
                                complete: function() {
                                    if (isCurrent()) {
                                        $loadingIndicator.addClass('d-none');
                                    }
                                }
                            });

                            // Populate form fields. Every field is overwritten, so nothing from a previous preview survives.
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
                                let value = response.data[dataField];
                                if (formField === 'additionalinformation') {
                                    value = value ? JSON.stringify(value) : '';
                                }
                                // Keep a prefilled label (conflicts page) when the response has none
                                if (formField === 'label' && !value) {
                                    return;
                                }
                                $form.find(`input[name="${formField}"], textarea[name="${formField}"]`).val(value || '');
                            });

                            // Handle select fields
                            $form.find('select[name="gender"]').val(response.data.gender || '');
                            $form.find('select[name="party"]').val(response.data.partyID || '');
                            $form.find('select[name="faction"]').val(response.data.factionID || '');

                            // Show/hide party and faction selects based on preview data
                            if (response.data.type === "person" || response.data.type === "memberOfParliament") {
                                $form.find("select[name='party']").closest(".formItem").removeClass("d-none");
                                $form.find("select[name='faction']").closest(".formItem").removeClass("d-none");
                                $form.find(".formHint#formHintPartyFaction").removeClass("d-none");
                            } else {
                                $form.find("select[name='party']").closest(".formItem").addClass("d-none");
                                $form.find("select[name='faction']").closest(".formItem").addClass("d-none");
                                $form.find(".formHint#formHintPartyFaction").addClass("d-none");
                            }

                            // Check for affected sessions using the API endpoint
                            $.ajax({
                                url: config.dir.root + "/api/v1/",
                                data: {
                                    action: "getItemsFromDB",
                                    itemType: "entitySuggestion",
                                    id: wikidataID,
                                    idType: "external"
                                },
                                dataType: "json",
                                success: function(sessionsResult) {
                                    if (!isCurrent()) {
                                        return;
                                    }
                                    const $container = $("#affectedSessionsContainer");
                                    $container.empty(); // Clear previous content

                                    let speechCount = 0;
                                    if (sessionsResult && sessionsResult.data) {
                                        // Prioritize EntitysuggestionCount if available
                                        if (typeof sessionsResult.data.EntitysuggestionCount !== 'undefined') {
                                            speechCount = parseInt(sessionsResult.data.EntitysuggestionCount, 10);
                                        } 
                                        // Fallback to context length if EntitysuggestionCount is not present (should be rare if count is maintained)
                                        else if (sessionsResult.data.EntitysuggestionContext) {
                                            const context = sessionsResult.data.EntitysuggestionContext;
                                            speechCount = Object.keys(context).length;
                                        }
                                    }

                                    if (speechCount > 0) {
                                        let html = '<div class="mt-3">';
                                        html += '  <div class="form-check mb-2">';
                                        html += '    <input class="form-check-input" type="checkbox" id="reimportSessionsCheckbox" checked>';
                                        html += '    <label class="form-check-label" for="reimportSessionsCheckbox">' + localizedLabels.reimportAffectedSessions + ' (' + speechCount + ' ' + localizedLabels.speeches + ')</label>';
                                        html += '  </div>';
                                        html += '</div>';
                                        $container.html(html);
                                    } else {
                                        $container.html('<div class="mt-3 text-muted"><span class="icon-info-circled"></span> ' + localizedLabels.messageNoSessionsAffected + '</div>');
                                    }
                                },
                                error: function() {
                                    if (!isCurrent()) {
                                        return;
                                    }
                                    $("#affectedSessionsContainer").html('<div class="mt-3 text-danger">Error checking affected sessions.</div>');
                                }
                            });

                            // Handle alternative labels
                            const $labelAlternativeList = $form.find('button.labelAlternativeAdd').parent().find("div:first").empty();
                            if (Array.isArray(response.data.labelAlternative)) {
                                response.data.labelAlternative.forEach(label => {
                                    $labelAlternativeList.append(labelAlternativeRow(label));
                                });
                            }
                            
                            // Handle social media IDs
                            const $socialMediaList = $form.find('button.socialMediaIDsAdd').parent().find("div:first").empty();
                            if (Array.isArray(response.data.socialMediaIDs)) {
                                response.data.socialMediaIDs.forEach(socialMedia => {
                                    $socialMediaList.append(socialMediaRow(socialMedia.label, socialMedia.id));
                                });
                            }
                        } else {
                            // Not a usable preview: allow the same inputs to be fetched again
                            lastPreviewKey = null;
                            let message = "Could not fetch additional data. Response was unexpected.";
                            if (response && response.meta && response.meta.message) {
                                message = "Could not fetch additional data: " + response.meta.message;
                            } else if (response && response.errors && response.errors.length > 0) {
                                message = "Could not fetch additional data: " + (response.errors[0].detail || response.errors[0].title || "Unknown error.");
                            }
                            $getAdditionalInfoError.text(message).removeClass('d-none');
                            $('#modalAddEntitySubmitBtn').prop('disabled', true);
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        if (!isCurrent()) {
                            return;
                        }
                        lastPreviewKey = null;
                        $getAdditionalInfoError.text("Error fetching additional data: " + textStatus + " - " + errorThrown).removeClass('d-none');
                        $('#modalAddEntitySubmitBtn').prop('disabled', true);
                    }
                });
            } else {
                $previewContainer.addClass('d-none');
                $('#modalAddEntitySubmitBtn').prop('disabled', true);
            }
        }

        // Initial button state
        $('#modalAddEntitySubmitBtn').prop('disabled', true);

        function initializeFormDisplay() {
            // Determine the selected entity type from radio buttons
            const selectedType = $form.find("input[name='itemType']:checked").val();

            // Hide all subtype containers and disable their selects initially
            $form.find(".subtype-container").hide().find("select").prop("disabled", true);

            let tempItem = ".not"; // CSS class selector for specific form items

            // Hide all form items
            $form.find(".formItem").addClass("d-none");
            $form.find(".formHint").addClass("d-none");            

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

        // Reduce the ID field to the bare ID (also from a pasted Wikidata URL), so the preview and the submit use the same value
        $form.find('input[name="id"]').on("input change", function() {
            const value = $(this).val();
            const match = value.match(/[QP]\d+/i);
            const normalized = match ? match[0].toUpperCase() : value.trim();
            if (normalized !== value) {
                $(this).val(normalized);
            }
        });

        // Listen for changes on relevant fields to update preview
        $form.find('input[name="id"], #typePerson, #typeOrganisation, #typeDocument, #typeTerm').on("input change", function() {
            fetchAdditionalInfo();
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
            $('#modalAddEntitySubmitBtn').prop('disabled', true);
        }

        // --- Initialization logic for this instance ---
        const initialWikidataID = $form.data('wikidata-id');
        const initialEntitySuggestionID = $form.data('entity-suggestion-id');
        const initialEntityLabel = $form.data('entity-label');

        if (initialWikidataID) {
            $form.find('input[name="id"]').val(initialWikidataID);
        }
        if (initialEntitySuggestionID) {
            $form.find('input[name="sourceEntitySuggestionID"]').val(initialEntitySuggestionID);
        }
        // No Wikidata ID known (e.g. opened from a conflict): prefill the label
        // and link a Wikidata search for it next to the ID field.
        if (initialEntityLabel) {
            $form.find('input[name="label"]').val(initialEntityLabel);
            if (!initialWikidataID) {
                const searchUrl = 'https://www.wikidata.org/w/index.php?search=' + encodeURIComponent(initialEntityLabel);
                $('<div class="form-text"><a href="' + searchUrl + '" target="_blank">' +
                    localizedLabels.searchWikidataForLabel.replace('{label}', $('<span>').text(initialEntityLabel).html()) +
                    ' <span class="icon-link-ext"></span></a></div>')
                    .insertAfter($form.find('input[name="id"]'));
            }
        }
        
        // Initial call to set up form display based on any pre-selected radio or default state
        initializeFormDisplay(); 

		$form.ajaxForm({
			url: config.dir.root +"/api/v1/",
			dataType: "json",
            beforeSubmit: function(formData, jqForm, options) {
                // Add reimportAffectedSessions to the form data
                const reimportCheckbox = $("#reimportSessionsCheckbox");
                let reimportAffectedSessions = false;
                if (reimportCheckbox.length > 0 && reimportCheckbox.is(":checked")) {
                    reimportAffectedSessions = true;
                }
                formData.push({ name: "reimportAffectedSessions", value: reimportAffectedSessions });
                // sourceEntitySuggestionID is already part of the form as a hidden input

                // The rows post two parallel arrays; the server stores socialMediaIDs as [{label, id}].
                // Spliced in place: the form plugin submits this very array.
                const socialLabels = [];
                const socialValues = [];
                for (let i = formData.length - 1; i >= 0; i--) {
                    const name = formData[i].name;
                    if (name === "socialMediaIDsLabel[]" || name === "socialMediaIDsValue[]") {
                        (name === "socialMediaIDsLabel[]" ? socialLabels : socialValues).unshift(String(formData[i].value).trim());
                        formData.splice(i, 1);
                    }
                }
                let socialIndex = 0;
                socialLabels.forEach(function(label, i) {
                    const id = socialValues[i] || "";
                    if (label || id) {
                        formData.push({ name: "socialMediaIDs[" + socialIndex + "][label]", value: label });
                        formData.push({ name: "socialMediaIDs[" + socialIndex + "][id]", value: id });
                        socialIndex++;
                    }
                });

                // No second submit while this one is in flight
                $('#modalAddEntitySubmitBtn').prop('disabled', true);
            },
			success: function (ret) {
				// console.log('[Form AJAX Success Callback] Raw response (ret):', JSON.parse(JSON.stringify(ret))); // DEBUG REMOVED
                $("#entityAddReturn").empty().removeClass('alert-success alert-danger alert-warning').addClass('d-none');
				$("#entityAddForm input, #entityAddForm select, #entityAddForm textarea").css("border", "");

				if (ret && ret.meta && ret.meta.requestStatus === "success") {
                    // The entity is added. A failed re-import or suggestion removal is reported, but the dialog is not offered for a retry: that would only hit the duplicate check.
                    const warnings = [];
                    if (ret.meta.reimportStatus === 'error') {
                        warnings.push("<div>Error re-importing affected sessions: " + (ret.meta.reimportSummary || "Please check logs.") + "</div>");
                    }
                    if (ret.meta.suggestionDeleteStatus === 'error') {
                        warnings.push("<div>Note: Entity was added, but failed to automatically remove the original suggestion.</div>");
                    }
                    if (warnings.length > 0) {
                        $("#entityAddReturn").html(warnings.join("")).removeClass('d-none').addClass('alert-warning');
                        $('#modalAddEntitySubmitBtn').prop('disabled', true);
                        if (typeof $ !== 'undefined' && $.fn.bootstrapTable) {
                            $('.bootstrap-table .table').bootstrapTable('refresh');
                        }
                        return;
                    }

                    const submittedSourceSuggestionID = $form.find('input[name="sourceEntitySuggestionID"]').val();
                    // Check if sourceEntitySuggestionIDUsed is present in meta and true, otherwise fallback to checking the form field
                    const wasFromSuggestion = (ret.meta.sourceEntitySuggestionIDUsed === true || ret.meta.sourceEntitySuggestionIDUsed === 'true') 
                                              || (!ret.meta.hasOwnProperty('sourceEntitySuggestionIDUsed') && submittedSourceSuggestionID && submittedSourceSuggestionID !== "");

                    resetForm(); 
                    const $modal = $form.closest('.modal');
                    if ($modal.length) {
                        $modal.modal('hide');
                    }

                    if (wasFromSuggestion) {
                        // Action originated from a suggestion (typically on entitySuggestions/page.php)
                        // The primary visual feedback on this page is deleting the suggestion row.
                        const suggestionIdToAnimate = ret.meta.sourceEntitySuggestionIDUsed || submittedSourceSuggestionID;
                        
                        if (suggestionIdToAnimate) {
                            animateBootstrapTableRow('entitiesTable', suggestionIdToAnimate, 'delete', 1000)
                                .then(() => {
                                    // console.log('Delete animation for suggestion ' + suggestionIdToAnimate + ' complete.');
                                    // After suggestion is removed by animation (which includes removeByUniqueId),
                                    // refresh all tables on the page to ensure overall consistency.
                                    if (typeof $ !== 'undefined' && $.fn.bootstrapTable) {
                                        $('.bootstrap-table .table').bootstrapTable('refresh');
                                    }
                                })
                                .catch(error => {
                                    console.error('Error during suggestion delete animation:', error);
                                    // Fallback: Still refresh all tables.
                                    if (typeof $ !== 'undefined' && $.fn.bootstrapTable) {
                                        $('.bootstrap-table .table').bootstrapTable('refresh');
                                    }
                                });
                        } else {
                             console.warn('Source suggestion ID not found for deletion animation.');
                             // Fallback: Refresh all tables if ID is missing.
                             if (typeof $ !== 'undefined' && $.fn.bootstrapTable) {
                                $('.bootstrap-table .table').bootstrapTable('refresh');
                            }
                        }
                    } else {
                        // Direct add action (typically on entities/page.php)
                        try {
                            // console.log('[Form Success] Direct Add: Entering direct add block. Response data:', ret.data); // DEBUG REMOVED
                            const newItemId = ret.data.id; 
                            const itemType = ret.data.type;  
                            let targetTableId = null;

                            // console.log('[Form Success] Direct Add: newItemId:', newItemId, 'itemType:', itemType); // DEBUG REMOVED

                            switch (itemType) {
                                case 'person': targetTableId = 'peopleTable'; break;
                                case 'organisation': targetTableId = 'organisationsTable'; break;
                                case 'document': targetTableId = 'documentsTable'; break;
                                case 'term': targetTableId = 'termsTable'; break;
                            }
                            // console.log('[Form Success] Direct Add: Determined targetTableId:', targetTableId); // DEBUG REMOVED

                            if (targetTableId && newItemId) {
                                const $targetTable = $('#' + targetTableId);
                                if ($targetTable.length) {
                                    // console.log('[Form Success] Direct Add: Target table found:', targetTableId, 'New Item ID:', newItemId); // DEBUG REMOVED
                                    $targetTable.one('load-success.bs.table post-body.bs.table', function (e) {
                                        // console.log('[Form Success] Direct Add: Table event fired:', e.type, 'for table:', targetTableId, 'Searching for ID:', newItemId); // DEBUG REMOVED
                                        $targetTable.off('load-success.bs.table post-body.bs.table'); 
                                        animateBootstrapTableRow(targetTableId, newItemId, 'success', 2000)
                                            .then(() => {
                                                // console.log('[Form Success] Direct Add: Success animation PROMISE RESOLVED for new entity', newItemId, 'on table', targetTableId); // DEBUG REMOVED
                                            })
                                            .catch(error => {
                                                console.error('[Form Success] Direct Add: Error during new entity success animation:', error);
                                            });
                                    });
                                    // console.log('[Form Success] Direct Add: Refreshing table:', targetTableId); // DEBUG REMOVED
                                    $targetTable.bootstrapTable('refresh');
                                } else {
                                    console.warn('[Form Success] Direct Add: Target table ' + targetTableId + ' not found for success animation.');
                                    if (typeof $ !== 'undefined' && $.fn.bootstrapTable) {
                                        $('.bootstrap-table .table').bootstrapTable('refresh');
                                    }
                                }
                            } else {
                                console.warn('[Form Success] Direct Add: Target table ID or new item ID was null/undefined. newItemId:', newItemId, 'targetTableId:', targetTableId);
                                if (typeof $ !== 'undefined' && $.fn.bootstrapTable) {
                                    $('.bootstrap-table .table').bootstrapTable('refresh');
                                }
                            }
                        } catch (e) {
                            console.error('[Form Success] Direct Add: Error in direct add block:', e);
                            // Fallback refresh just in case
                            if (typeof $ !== 'undefined' && $.fn.bootstrapTable) {
                                $('.bootstrap-table .table').bootstrapTable('refresh');
                            }
                        }
                    }
				} else {
                    // Nothing was written: the inputs can be corrected and submitted again
                    $('#modalAddEntitySubmitBtn').prop('disabled', false);
                    $("#entityAddReturn").removeClass('d-none alert-success').addClass('alert-danger');
                    const errorMessages = [];

                    if (ret && ret.errors && Array.isArray(ret.errors)) {
                        ret.errors.forEach(function(error) {
                            errorMessages.push($('<div>').text(error.title));
                            // The detail is what names the field (e.g. "Das Feld 'sourceuri' ist erforderlich.")
                            if (error.detail && error.detail !== error.title) {
                                errorMessages.push($('<div class="small">').text(error.detail));
                            }
                            if (error.meta && error.meta.domSelector) {
                                $(error.meta.domSelector).css("border", "1px solid red");
                            } else if (error.label) {
                                $("[name='" + error.label + "']").css("border", "1px solid red");
                            }
                        });
                    } else {
                        // Generic error if the structure is not as expected
                        errorMessages.push($('<div>').text(localizedLabels.messageErrorGeneric));
                    }

                    if (errorMessages.length === 0) {
                        errorMessages.push($('<div>').text("An unknown error occurred."));
                    }
                    $("#entityAddReturn").empty().append(errorMessages).removeClass("d-none");
                }
			},
            error: function(jqXHR, textStatus, errorThrown) {
                // Handle AJAX call failure
                $('#modalAddEntitySubmitBtn').prop('disabled', false);
                $("#entityAddReturn").empty().removeClass('d-none alert-success alert-warning').addClass('alert-danger');
                $("#entityAddReturn").html("<div>Communication error with the server: " + textStatus + " - " + errorThrown + "</div>").removeClass("d-none");
            }
		});

		$(".labelAlternativeAdd").on("click", function() {
			$(this).parent().find("div:first").append(labelAlternativeRow(''));
		});


		$("body").on("click", ".labelAlternativeRemove", function() {
			$(this).parent().remove();
		});

		$(".socialMediaIDsAdd").on("click", function() {
			$(this).parent().find("div:first").append(socialMediaRow('', ''));
		});

		$("body").on("click", ".socialMediaIDsRemove", function() {
			$(this).parent().remove();
		});

	});
</script>

</div> 