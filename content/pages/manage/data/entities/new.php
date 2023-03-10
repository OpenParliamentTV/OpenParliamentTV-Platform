<?php
include_once(__DIR__ . '/../../../../../modules/utilities/auth.php');
$auth = auth($_SESSION["userdata"]["id"], "requestPage", $pageType);

if ($auth["meta"]["requestStatus"] != "success") {
    $alertText = $auth["errors"][0]["detail"];
    include_once (__DIR__."/../../../login/page.php");

} else {
    include_once(__DIR__ . '/../../../../header.php');
    include_once(__DIR__ . '/../../../../../api/v1/api.php');
?>
<main class="container subpage">
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
	                        <input name="a" value="entityAddTest" type="hidden">
	                        <input name="entitysuggestionid" value="" type="hidden">
	                        <div class="form-group">
	                            <label for="entityType">Entity-Type</label>
	                            <select class="form-control" name="entityType">
	                                <option value="person">Person</option>
	                                <option value="organisation">Organisation</option>
	                                <option value="document">Document</option>
	                                <option value="term">Term</option>
	                            </select>
	                        </div>
	                    </div>
	                    <div class="col-12 col-lg-4">
	                        <div class="form-group formItem formItemTypePerson formItemTypeOrganisation formItemTypeDocument formItemTypeTerm">
	                            <label for="id">Wikidata ID</label>
	                            <input type="text" class="form-control" name="id">
	                        </div>
	                    </div>
	                    <div class="col-12 col-lg-4">
	                        <div class="form-group formItem formItemTypeTerm">
	                            <label for="type">Subtype</label>
	                            <select class="form-control" name="type">
	                                <option value="">..TODO..</option>
	                                <option value="otherTerm">Other Term</option>
	                            </select>
	                        </div>
	                        <div class="form-group formItem formItemTypeDocument">
	                            <label for="type">Subtype</label>
	                            <select class="form-control" name="type">
	                                <option value="">..TODO..</option>
	                                <option value="officialDocument">officialDocument</option>
	                                <option value="legalDocument">legalDocument</option>
	                                <option value="otherDocument">Other Document</option>
	                            </select>
	                        </div>
	                        <div class="form-group formItem formItemTypePerson">
	                            <label for="type">Subtype</label>
	                            <select class="form-control" name="type">
	                                <option value="">..TODO..</option>
	                                <option value="memberOfParliament">Member Of Parliament</option>
	                                <option value="person">Person</option>
	                            </select>
	                        </div>
	                        <div class="form-group formItem formItemTypeOrganisation">
	                            <label for="type">Subtype</label>
	                            <select class="form-control" name="type">
	                                <option value="">Please Select</option>
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
	                                <option value="">..TODO..</option>
	                                <option value="female">Female</option>
	                                <option value="male">Male</option>
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
	                        <div class="form-group formItem formItemTypeDocument formItemTypeTerm">
	                            <label for="sourceuri">Source URI</label>
	                            <input type="text" class="form-control" name="sourceuri">
	                        </div>
	                        <div class="form-group formItem formItemTypePerson formItemTypeOrganisation formItemTypeDocument formItemTypeTerm">
	                            <label for="embeduri">Embed URI</label>
	                            <input type="text" class="form-control" name="embeduri">
	                        </div>
	                        <div class="form-group formItem formItemTypePerson formItemTypeOrganisation">
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

	                                <?php

	                                require_once (__DIR__."/../../../../../api/v1/modules/organisation.php");
	                                $partyie = organisationSearch(array("type"=>"party"));
	                                foreach ($partyie["data"] as $party) {
	                                    echo '<option value="'.$party["id"].'">'.$party["attributes"]["label"].'</option>';
	                                }
	                                ?>

	                            </select>
	                        </div>

	                        <div class="form-group formItem formItemTypePerson">
	                            <label for="party">Faction</label>
	                            <select class="form-control" name="party">

	                                <?php

	                                require_once (__DIR__."/../../../../../api/v1/modules/organisation.php");

	                                $factions = organisationSearch(array("type"=>"faction"));
	                                foreach ($factions["data"] as $faction) {
	                                    echo '<option value="'.$faction["id"].'">'.$faction["attributes"]["label"].'</option>';
	                                }


	                                ?>

	                            </select>
	                        </div>
	                        <div class="form-group form-dyncontent formItem formItemTypePerson">
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
	                            <button class="btn btn-outline-secondary" id="entityAddFormCancelBtn"><span class="icon-trash-empty"></span> Cancel</button>
	                            <button class="btn btn-outline-primary" id="entityAddFormSubmitBtn" type="submit"><span class="icon-upload"></span> Add Entity</button>
	                        </div>  
	                    </div>
	                </div>
                </form>
            </div>
	</div>
</main>
<link rel="stylesheet" href="<?= $config["dir"]["root"] ?>/content/pages/manage/data/entities/client/entity.new.css?v=<?= $config["version"] ?>">
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/manage/data/entities/client/entity.new.js?v=<?= $config["version"] ?>"></script>
<?php
    include_once(__DIR__ . '/../../../../footer.php');
}
?>