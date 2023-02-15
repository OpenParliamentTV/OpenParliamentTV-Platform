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
		<div class="col-12 tablediv">
			<h2><?php echo L::manageEntities; ?></h2>
            <div id="entitiesDetailsDiv" style="display: none">
                <!--<button type="button" class="btn btn-light">Light</button>-->
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
                <div id="entitiesDiv">
                <table class="table table-striped table-hover"
                       id="entitiesTable"
                       data-toggle="table"
                       data-sortable="true"
                       data-pagination="true"
                       data-search="true">

                    <thead>
                    <tr>
                        <th scope="col" data-sortable="true">Type</th>
                        <th scope="col" data-sortable="true">Label</th>
                        <th scope="col" data-sortable="true">ID</th>
                        <th scope="col" data-sortable="true">Count</th>
                        <th scope="col" data-sortable="true">Action</th>
                    </tr>
                    </thead>
                    <tbody>

                <?php

                if (!$db) {
                    $db = new SafeMySQL(array(
                        'host'	=> $config["platform"]["sql"]["access"]["host"],
                        'user'	=> $config["platform"]["sql"]["access"]["user"],
                        'pass'	=> $config["platform"]["sql"]["access"]["passwd"],
                        'db'	=> $config["platform"]["sql"]["db"]
                    ));
                }

                $entitysuggestions = $db->getAll("SELECT *, JSON_LENGTH(EntitysuggestionContext) as EntitysuggestionCount, JSON_EXTRACT(EntitysuggestionContent,'$.type') as EntitysuggestionContentType FROM ?n;", $config["platform"]["sql"]["tbl"]["Entitysuggestion"]);

                foreach ($entitysuggestions as $entity) {
                    echo "
                        <tr>
                                <td>".$entity["EntitysuggestionType"]." ".($entity["EntitysuggestionContentType"] ? "<span class='font-italic'>(".$entity["EntitysuggestionContentType"].")</span>" : "")."</td>
                                <td>".$entity["EntitysuggestionLabel"]."</td>
                                <td>".$entity["EntitysuggestionExternalID"]."</td>
                                <td>".$entity["EntitysuggestionCount"]."</td>
                                <td><span class='entitysuggestiondetails icon-popup btn btn-outline-secondary btn-sm' data-id='".$entity["EntitysuggestionID"]."'></span></td>
                        </tr>";

                }
                ?>
                    </tbody>
                </table><br><br><br>
            </div>


		</div>
	</div>
</main>

    <link type="text/css" rel="stylesheet" href="<?= $config["dir"]["root"] ?>/content/pages/api/client/jquery.json-view.min.css" media="all">
    <script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/api/client/jquery.json-view.min.js"></script>
    <script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/api/client/highlight.min.js"></script>
    <script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/api/client/apiResult.js"></script>

    <script type="application/javascript">
        $(function() {
            $(".entitiesToggleDetailsAndTable").on("click", function() {

                $("#entitiesDetailsDiv").slideUp();
                $("#entitiesDiv").slideDown();
            });
            $(".tablediv").on("click", ".entitysuggestiondetails",function() {

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
                           $("#entitiesDetailsDiv").slideDown();
                           $("#entitiesDiv").slideUp();
                       }
                   }
               })
            });
        })
    </script>
    <style type="text/css">
        #entitiesDetailsContent {
            background: #fafafa;
            color: #986801;
            max-height: 300px;
            overflow: auto;
            margin-bottom: 20px;
        }
        #entitiesDetailsContent .b {
            color: #383a42;
        }
        #entitiesDetailsContent li > span:not(.num):not(.null):not(.q):not(.block),
        #entitiesDetailsContent .str, #entitiesDetailsContent a {
            color: #50a14f;
        }
    </style>
<?php

    include_once(__DIR__ . '/../../../footer.php');

}
?>