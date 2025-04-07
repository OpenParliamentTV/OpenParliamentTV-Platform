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
                <div class="col-12">
                    <h2><?= L::manageData; ?></h2>
                    <div class="card mb-3">
                        <div class="card-body">
                            <div>
                                <a href="./data/media/irregularities">Show media irregularities</a>
                            </div>
                            <form method="post" id="runAdditionalDataServiceForSpecificEntitiesForm">
                                <input type="hidden" name="a" value="runAdditionalDataServiceForSpecificEntities">
                                <div class="form-group formItem formItemTypePerson">
                                    <label for="party">Language</label>
                                    <select class="form-control" name="language">
                                        <option value="de" selected>German (de)</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <button class="labelEntityADSadd btn" type="button"><span class="icon-plus"></span></button>
                                    <div></div>
                                </div>
                                <div>
                                    <button class="btn btn-outline-primary" type="submit"><span class="icon-upload"></span> Run ADS for specific Entities</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script type="text/javascript">
    $(function() {
        
        $("main").on("click", ".labelEntityADSadd", function (){
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

        $("main").on("click", ".buttonRemoveADSentity", function() {
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