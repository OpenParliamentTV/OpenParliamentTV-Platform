<?php
include_once(__DIR__ . '/../../../../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"], "requestPage", $pageType);

if ($auth["meta"]["requestStatus"] != "success") {

    $alertText = $auth["errors"][0]["detail"];
    include_once (__DIR__."/../../login/page.php");

} else {

    include_once(__DIR__ . '/../../../header.php');


?>
<main class="container-fluid subpage">
	<div class="row">
		<?php include_once(__DIR__ . '/../sidebar.php'); ?>
		<div class="sidebar-content">
			<div class="row" style="position: relative; z-index: 1">
				<div class="col-12 mainContainer">
					<h2><?php echo L::manageEntities; ?></h2>
					<div id="entityAddSuccess" style="position: relative; display:none;" class="pt-5 pb-5 contentContainer">
						<h3>Entity successfully added</h3>
						<button class="btn btn-sm input-group-text entitiesToggleDetailsAndTable mb-3" style="position: absolute; top:0px; right:0px;">
							<span class="icon-cancel"></span>
							<span class="d-none d-md-inline">Back to Table</span>
						</button>
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
					<div id="entityAddDiv" style="position:relative; display: none;" class="pt-5 pb-5 contentContainer">
						<h3>Add Entity</h3>
						<button class="btn btn-sm input-group-text entitiesToggleDetailsAndTable mb-3" style="position: absolute; top:0px; right:0px;">
							<span class="icon-cancel"></span>
							<span class="d-none d-md-inline">Back to table</span>
						</button>
						<div class="row" id="searchEntityReturn" style="display: none">
						</div>
						<div class="row">
							<div class="col-12">
								<form id="entityAddForm" method="post">
									<input name="a" value="entityAdd" type="hidden">
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
									<div class="form-group formItem formItemTypePerson formItemTypeOrganisation formItemTypeDocument formItemTypeTerm">
										<label for="id">Wikidata ID</label>
										<input type="text" class="form-control" name="id">
									</div>
									<div class="form-group formItem formItemTypeTerm">
										<label for="type">Type</label>
										<select class="form-control" name="type">
											<option value="">..TODO..</option>
											<option value="otherTerm">Other Term</option>
										</select>
									</div>
									<div class="form-group formItem formItemTypeDocument">
										<label for="type">Type</label>
										<select class="form-control" name="type">
											<option value="">..TODO..</option>
											<option value="officialDocument">officialDocument</option>
											<option value="legalDocument">legalDocument</option>
											<option value="otherDocument">Other Document</option>
										</select>
									</div>
									<div class="form-group formItem formItemTypePerson">
										<label for="type">Type</label>
										<select class="form-control" name="type">
											<option value="">..TODO..</option>
											<option value="memberOfParliament">Member Of Parliament</option>
											<option value="person">Person</option>
										</select>
									</div>
									<div class="form-group formItem formItemTypeOrganisation">
										<label for="type">Type</label>
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
									<div class="form-group formItem formItemTypePerson formItemTypeOrganisation formItemTypeDocument formItemTypeTerm" style="position: relative">
										<label for="label">Label</label>
										<input type="text" class="form-control" name="label">
									</div>
									<div class="form-group formItem formItemTypePerson">
										<label for="firstName">First Name</label>
										<input type="text" class="form-control" name="firstName">
									</div>
									<div class="form-group formItem formItemTypePerson">
										<label for="lastName">Last Name</label>
										<input type="text" class="form-control" name="lastName">
									</div>
									<div class="form-group formItem formItemTypePerson">
										<label for="degree">Degree</label>
										<input type="text" class="form-control" name="degree">
									</div>
									<div class="form-group formItem formItemTypePerson formItemTypeOrganisation formItemTypeDocument formItemTypeTerm">
										<label for="labelAlternative[]">Alternative Label</label> <button class="labelAlternativeAdd btn" type="button"><span class="icon-plus"></span></button>
										<div>
										</div>
									</div>
									<div class="form-group formItem formItemTypePerson">
										<label for="birthdate">Date Of Birth</label>
										<input type="text" class="form-control" name="birthdate" placeholder="YYYY-MM-DD">
									</div>
									<div class="form-group formItem formItemTypePerson">
										<label for="gender">Gender</label>
										<select class="form-control" name="gender">
											<option value="">..TODO..</option>
											<option value="female">Female</option>
											<option value="male">Male</option>
										</select>
									</div>
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
											require_once (__DIR__."/../../../../api/v1/modules/organisation.php");
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
											require_once (__DIR__."/../../../../api/v1/modules/organisation.php");
											$factions = organisationSearch(array("type"=>"faction"));
											foreach ($factions["data"] as $faction) {
												echo '<option value="'.$faction["id"].'">'.$faction["attributes"]["label"].'</option>';
											}
											?>
										</select>
									</div>
									<div class="form-group form-dyncontent formItem formItemTypePerson">
										<label for="socialMediaIDsLabel[]">Social Medias</label> <button class="socialMediaIDsAdd btn" type="button"><span class="icon-plus"></span></button>
										<div>
										</div>
									</div>
									<div class="form-group formItem formItemTypeOrganisation">
										<label for="color">Color</label>
										<input type="text" class="form-control" name="color">
									</div>
									<div class="form-group formItem formItemTypePerson formItemTypeOrganisation formItemTypeDocument formItemTypeTerm">
										<label for="additionalinformation">Additional Informations (JSON)</label>
										<textarea class="form-control" name="additionalinformation" placeholder='{"abgeordnetenwatchID":""}'></textarea>
									</div>
									<div id="entityAddReturn"></div>
									<div>
										<button class="btn btn-outline-secondary" id="entityAddFormCancelBtn"><span class="icon-trash-empty"></span> Cancel</button>
										<button class="btn btn-outline-primary" id="entityAddFormSubmitlBtn" type="submit"><span class="icon-upload"></span> Add Entity</button>
									</div>
								</form>
							</div>
						</div>
						<button class="btn btn-sm input-group-text entitiesToggleDetailsAndTable mt-3" style="position: absolute; bottom:0px; right:0px;">
							<span class="icon-cancel"></span><span class="d-none d-md-inline">Back to table</span>
						</button>
					</div>
					<div id="entitiesDetailsDiv" class="contentContainer" style="display: none">
						<h3>Entity Suggestion Details</h3>
						<button class="btn btn-sm input-group-text entitiesToggleDetailsAndTable float-right mb-3"><span class="icon-cancel"></span><span class="d-none d-md-inline">Back to table</span></button>
						<table class="col-12 table-striped table-hover" id="entitiesDetails" data-toggle="table">
							<thead>
								<tr>
									<th data-field="0">Label</th>
									<th data-field="1">Value</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td>External ID</td>
									<td id="entitiesDetailsExternalID"></td>
								</tr>
								<tr>
									<td>Label</td>
									<td id="entitiesDetailsLabel"></td>
								</tr>
								<tr>
									<td>NEL Type</td>
									<td id="entitiesDetailsType"></td>
								</tr>
								<tr>
									<td>Content</td>
									<td id="entitiesDetailsContent"></td>
								</tr>
								<tr>
									<td>Media Count</td>
									<td id="entitiesDetailsMediaCount"></td>
								</tr>
								<tr>
									<td>Was found in</td>
									<td id="entitiesDetailsContext"></td>
								</tr>
							</tbody>
						</table>
						<button class="btn btn-sm input-group-text entitiesToggleDetailsAndTable float-right mt-3"><span class="icon-cancel"></span><span class="d-none d-md-inline">Back to table</span></button>
					</div>
					<div id="entitiesDiv" class="contentContainer">
						<h3>Entitysuggestions</h3>
						<button class="btn btn-sm input-group-text entityaddform mb-3" style="position: absolute; top:0px; right:0px;">
							<span class="icon-plus"></span>
							<span class="d-none d-md-inline">Add Entity</span>
						</button>
						<table class="table table-striped table-hover"
							   id="entitiesTable">
							<thead>
							<tr>
								<th scope="col" data-sortable="true">Label</th>
								<th scope="col" data-sortable="true">ID</th>
								<th scope="col" data-sortable="true">Count</th>
								<th scope="col">Action</th>
							</tr>
							</thead>
							<tbody>
							</tbody>
						</table><br><br><br>
					</div>
				</div>
			</div>
		</div>
	</div>
</main>

    <link type="text/css" rel="stylesheet" href="<?= $config["dir"]["root"] ?>/content/pages/api/client/jquery.json-view.min.css?v=<?= $config["version"] ?>" media="all">
    <script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/api/client/jquery.json-view.min.js?v=<?= $config["version"] ?>"></script>
    <script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/api/client/highlight.min.js?v=<?= $config["version"] ?>"></script>
    <script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/api/client/apiResult.js?v=<?= $config["version"] ?>"></script>

    <script type="application/javascript">
        $(function() {


            $('#entitiesTable').bootstrapTable({
                url: config["dir"]["root"] + "/server/ajaxServer.php?a=entitysuggestionGetTable",
                pagination: true,
                sidePagination: "server",
                dataField: "rows",
                totalField: "total",
                search: true,
                serverSort: true,
                columns: [
                    {
                        field: "EntitysuggestionLabel",
                        title: "Label",
                    },
                    {
                        field: "EntitysuggestionExternalID",
                        title: "WikidataID",
                        formatter: function(value, row) {

                            return '<a href="https://www.wikidata.org/wiki/'+value+'" target="_blank">'+value+' </a>';

                        }
                    },
                    {
                        field: "EntitysuggestionCount",
                        title: "Affected Sessions"
                    },
                    {
                        field: "EntitysuggestionID",
                        title: "Action",
                        formatter: function(value, row) {
                            return "<span class='entitysuggestiondetails icon-popup btn btn-outline-secondary btn-sm' data-id='"+value+"'></span>\n" +
                                "                                            <a href='"+config["dir"]["root"]+"/manage/data/entities/new?wikidataID="+row["EntitysuggestionExternalID"]+"&entitySuggestionID="+row["EntitysuggestionID"]+"' target='_blank' class='icon-plus btn btn-outline-secondary btn-sm' data-id='"+row["EntitysuggestionID"]+"'></a>"
                        }
                    }
                ]
            });


            /**
             *
             *
             * GENERIC
             *
             *
             */

            $('#entityAddForm').ajaxForm({
                url: "<?= $config["dir"]["root"] ?>/server/ajaxServer.php",
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

                        if ("EntitysuggestionItem" in ret) {
                            $("#reimportSessions").data("entitysuggestionid", ret["EntitysuggestionItem"]["EntitysuggestionItemID"]);
                            if (("sessions" in ret) && (Object.keys(ret["sessions"]).length > 0)) {
                                for (let parliament in ret["sessions"]) {
                                    let sessioncontent = "";
                                    for (let session in ret["sessions"][parliament]) {
                                        sessioncontent += "<div class='sessionFilesDiv'>" + session + " | File exists: " + ret["sessions"][parliament][session]["fileExists"] + "<input type='hidden' name='files[" + parliament + "][]' class='reimportfile' value='" + session + "'>";
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



            $(".mainContainer").on("click", ".labelAlternativeRemove", function() {
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



            $(".mainContainer").on("click", ".socialMediaIDsRemove", function() {
                $(this).parent().remove();
            });



            $(".entitiesToggleDetailsAndTable").on("click", function() {

                $(".contentContainer").not("#entitiesDiv").slideUp();
                $("#entitiesDiv").slideDown();

            });



            $(".mainContainer").on("change", "select[name='entityType']", function() {
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



            $(".mainContainer").on("click", ".entityaddform", function() {

                $(".contentContainer").not("#entityAddDiv").slideUp();
                $("#entityAddDiv").slideDown();
                $("#entityAddForm .formItem").hide();
                $("#entityAddForm")[0].reset();
                $("#entityAddForm .formItem input, #entityAddForm .formItem textarea, #entityAddForm .formItem select").prop("disabled",true);

                if ($(this).data("id")) {

                    $.ajax({
                        url: config["dir"]["root"] + "/server/ajaxServer.php",
                        data: {"a":"entitysuggestionGet","id":$(this).data("id")},
                        success: function(ret) {
                            if (ret["success"] == "true") {
                                $("input[name='id']").val(ret["return"]["EntitysuggestionExternalID"]);
                                $("input[name='label']").val(ret["return"]["EntitysuggestionLabel"]);
                                switch (ret["return"]["EntitysuggestionType"]) {
                                    case "ORG":
                                        $("select[name='entityType']").val("organisation").trigger("change");
                                    break;

                                    case "PERSON":
                                        $("select[name='entityType']").val("person").trigger("change");
                                    break;

                                    case "DOC":
                                        $("select[name='entityType']").val("document").trigger("change");
                                    break;

                                    case "TERM":
                                    default:
                                        $("select[name='entityType']").val("term").trigger("change");
                                    break;
                                }
                                $(".contentContainer").not("#entityAddDiv").slideUp();
                                $("#entityAddDiv").slideDown();
                            }
                        }
                    });
                }
            })



            $(".mainContainer").on("click", ".entitysuggestiondetails",function() {

                       $.ajax({
                           url: config["dir"]["root"] + "/server/ajaxServer.php",
                           data: {"a":"entitysuggestionGet","id":$(this).data("id")},
                           success: function(ret) {
                               if (ret["success"] == "true") {
                                   let wikiIDRegex = new RegExp("Q[0-9]+");
                                   $("#entitiesDetailsExternalID").html((wikiIDRegex.test(ret["return"]["EntitysuggestionExternalID"]) ? '<a href="https://www.wikidata.org/wiki/'+ret["return"]["EntitysuggestionExternalID"]+'" target="_blank">'+ret["return"]["EntitysuggestionExternalID"]+'</a>' : ret["return"]["EntitysuggestionExternalID"]));
                                   $("#entitiesDetailsLabel").html(ret["return"]["EntitysuggestionLabel"]);
                                   $("#entitiesDetailsType").html(ret["return"]["EntitysuggestionType"]);
                                   $("#entitiesDetailsMediaCount").html(Object.keys(ret["return"]["EntitysuggestionContext"]).length);
                                   //$("#entitiesDetailsContent").html(ret["return"]["EntitysuggestionContent"]);
                                   $("#entitiesDetailsContent").empty();
                                   $("#entitiesDetailsContent").jsonView(ret["return"]["EntitysuggestionContent"]);

                                   $("#entitiesDetailsContext").html("");
                                   for (let item in ret["return"]["EntitysuggestionContext"]) {
                                       $("#entitiesDetailsContext").append('<a href="<?=$config["dir"]["root"]?>/media/'+item+'" target="_blank">'+item+'</a><br>');
                           }
                           $(".contentContainer").not("#entitiesDetailsDiv").slideUp();
                           $("#entitiesDetailsDiv").slideDown();
                       }
                   }
               })
            });



            $("#reimportSessionsButton").on("click", function() {

                $("#reimportSessionForm").ajaxForm({
                    url:"<?= $config["dir"]["root"] ?>/server/ajaxServer.php",
                    data:{"a":"reimportSessions"}
                }) //TODO success

            });
        })
    </script>
    <style type="text/css">
        .formItem {
            display: none;
        }
        #entitiesDetailsContent, #searchPersonLabelReturn div {
            background: #fafafa;
            color: #986801;
            max-height: 300px;
            overflow: auto;
            margin-bottom: 20px;
        }
        #entitiesDetailsContent .b,#searchPersonLabelReturn div .b {
            color: #383a42;
        }
        #entitiesDetailsContent li > span:not(.num):not(.null):not(.q):not(.block),
        #entitiesDetailsContent .str, #entitiesDetailsContent a,
        #searchPersonLabelReturn li > span:not(.num):not(.null):not(.q):not(.block),
        #searchPersonLabelReturn .str, #searchPersonLabelReturn a {
            color: #50a14f;
        }
    </style>
<?php

    include_once(__DIR__ . '/../../../footer.php');

}
?>