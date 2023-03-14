<?php
include_once(__DIR__ . '/../../../../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"], "requestPage", $pageType);

if ($auth["meta"]["requestStatus"] != "success") {

    $alertText = $auth["errors"][0]["detail"];
    include_once (__DIR__."/../../login/page.php");

} else {

    include_once(__DIR__ . '/../../../header.php');


?>
<main class="container subpage">
	<div class="row" style="position: relative; z-index: 1">
		<div class="col-12">
			<h2><?php echo L::manageData; ?></h2>
			<div class="card mb-3">
				<div class="card-body">
					<a href="<?= $config["dir"]["root"] ?>/manage/data/media/new" class="btn btn-outline-success btn-sm mr-1">New Media Item</a>
					<button type="button" id="runCronUpdater" class="btn btn-outline-success btn-sm mr-1">Run cronUpdater</button>
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
				<li class="nav-item">
					<a class="nav-link" id="terms-tab" data-toggle="tab" href="#ads" role="tab" aria-controls="terms" aria-selected="false">Update from ADS</a>
				</li>
			</ul>
			<div class="tab-content">
				<div class="tab-pane fade show active" id="media" role="tabpanel" aria-labelledby="media-tab">
                    <!--Tabelle Media, <a href="../media/8757">Beispiel Link</a>, <a href="./data/media/8757">Manage / Edit Link</a>-->
                    <a href="./data/media/irregularities">Show irregularities</a>
                </div>
				<div class="tab-pane fade" id="people" role="tabpanel" aria-labelledby="people-tab">
                    <a href="<?=$config["dir"]["root"]?>/manage/data/person/overview" target="_self">Overview</a><br><br>
                    Tabelle Person, <a href="../person/8757">Beispiel Link</a>, <a href="./data/person/8757">Manage / Edit Link</a>
                    <button type="button" class="btn btn-outline-success btn-sm mr-1 additionalDataServiceButton" data-type="person">Run additionalDataService for person</button>
                    <button type="button" class="btn btn-outline-success btn-sm mr-1 additionalDataServiceButton" data-type="memberOfParliament">Run additionalDataService for memberOfParliament</button>
                </div>
				<div class="tab-pane fade" id="documents" role="tabpanel" aria-labelledby="documents-tab">
                    Tabelle Document, <a href="../document/8757">Beispiel Link</a>, <a href="./data/document/8757">Manage / Edit Link</a>
                    <button type="button" class="btn btn-outline-success btn-sm mr-1 additionalDataServiceButton" data-type="legalDocument">Run additionalDataService for legalDocument</button>
                    <button type="button" class="btn btn-outline-success btn-sm mr-1 additionalDataServiceButton" data-type="officialDocument">Run additionalDataService for officialDocument</button>
                </div>
				<div class="tab-pane fade" id="organisations" role="tabpanel" aria-labelledby="organisations-tab">
                    <button type="button" class="btn btn-outline-success btn-sm mr-1 additionalDataServiceButton" data-type="organisation">Run additionalDataService for organisation</button>
					Tabelle Organisation, <a href="../organisation/8757">Beispiel Link</a>, <a href="./data/organisation/8757">Manage / Edit Link</a>


				</div>
				<div class="tab-pane fade" id="terms" role="tabpanel" aria-labelledby="terms-tab">
                    <button type="button" class="btn btn-outline-success btn-sm mr-1 additionalDataServiceButton" data-type="term">Run additionalDataService for term</button>
                    Tabelle Term, <a href="../term/8757">Beispiel Link</a>, <a href="./data/term/8757">Manage / Edit Link</a>
                </div>
				<div class="tab-pane fade" id="ads" role="tabpanel" aria-labelledby="terms-tab">
                    <form method="post" id="runAdditionalDataServiceForSpecificEntitiesForm">
                        <input type="hidden" name="a" value="runAdditionalDataServiceForSpecificEntities">
                        <div class="form-group formItem formItemTypePerson">
                            <label for="party">Language</label>
                            <select class="form-control" name="language">
                                <option value="de"selected>German (de)</option>
                            </select>
                        </div>


                        <div class="col-12">
                            <button class="labelEntityADSadd btn" type="button"><span class="icon-plus"></span></button>
                            <div></div>
                        <div>
                            <button class="btn btn-outline-primary" type="submit"><span class="icon-upload"></span> Run ADS for specific Entities</button>
                    </form>
                </div>
			</div>
		</div>
	</div>
</main>

    <div class="modal fade" id="successRunCronDialog" tabindex="-1" role="dialog" aria-labelledby="successRunCronDialogLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="successRunCronDialogLabel">Run cronUpdater</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    The cronUpdater should run in background now
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-dismiss="modal">Okay</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="successRunAdditionalDataService" tabindex="-1" role="dialog" aria-labelledby="successRunCronDialogLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="successRunCronDialogLabel">Run ADS for <span class="adc-type"></span></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    The additionalDataService for type <span class="adc-type"></span> should run now in background.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-dismiss="modal">Okay</button>
                </div>
            </div>
        </div>
    </div>

<script type="text/javascript">
    $(function() {
        $("#runCronUpdater").on("click", function() {
            $.ajax({
                url:"<?= $config["dir"]["root"] ?>/server/ajaxServer.php",
                dataType:"json",
                data:{"a":"runCronUpdater"},
                method:"post",
                success: function(ret) {

                    $('#successRunCronDialog').modal('show');

                }
            })
        })

        $(".container").on("click",".additionalDataServiceButton", function() {
            $.ajax({
                url:"<?= $config["dir"]["root"] ?>/server/ajaxServer.php",
                dataType:"json",
                data:{"a":"runAdditionalDataService", "type": $(this).data("type")},
                tmpType: $(this).data("type"),
                method:"post",
                success: function(ret) {
                    //TODO: Check for success return parameter
                    $(".adc-type").html(this.tmpType);
                    $('#successRunAdditionalDataService').modal('show');

                }
            })
        });

        $("#ads").on("click", ".labelEntityADSadd", function (){
            $(this).next("div").append('' +
                '<div class="row" style="position: relative">' +
                '   <div class="col-6">' +
                '       <div class="form-group">\n' +
                '                <label for="entityType">Entity Type</label>\n' +
                '                <select class="form-control" name="type[]">\n' +
                '                    <option value="" disabled selected>Choose Entity Type ..</option>\n' +
                '                    <option value="person">Person</option>\n' +
                '                    <option value="memberOfParliament">Member of Parliament</option>\n' +
                '                    <option value="organisation">Organisation</option>\n' +
                '                    <option value="officialDocument">officialDocument</option>\n' +
                '                    <option value="legalDocument">legalDocument</option>\n' +
                '                    <option value="term">Term</option>\n' +
                '                </select>\n' +
                '       </div>' +
                '   </div>\n' +
                '   <div class="col-6">\n' +
                '        <div class="form-group">\n' +
                '            <label for="ids">Wikidata ID</label>\n' +
                '            <input type="text" class="form-control" name="ids[]">\n' +
                '        </div>\n' +
                '   </div>' +
                '   <button class="buttonRemoveADSentity btn" style="position: absolute;bottom:16px;right:0px;" type="button">' +
                '       <span class="icon-cancel-circled"></span>' +
                '   </button>' +
                '</div>');

        });

        $("#ads").on("click", ".buttonRemoveADSentity", function() {
            $(this).parent().remove();
        });

        $("#runAdditionalDataServiceForSpecificEntitiesForm").ajaxForm({
            url: config.dir.root +"/server/ajaxServer.php",
            dataType: "json",
            success: function(ret) {
                console.log(ret);
            }
        });

    })
</script>
<?php

    include_once(__DIR__ . '/../../../footer.php');

}
?>