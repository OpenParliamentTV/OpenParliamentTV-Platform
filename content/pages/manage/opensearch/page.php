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
			<h2>OpenSearch</h2>
            <div class="row">
                <div class="mt-2 mb-2 col-6 col-md-4 col-lg-3">
                    <span class="d-block p-4 bg-white text-center btn updateSearchIndex" href="" data-type="specific">Update specific Medias</span>
                </div>
                <div class="mt-2 mb-2 col-6 col-md-4 col-lg-3">
                    <span class="d-block p-4 bg-white text-center btn updateSearchIndex" href="" data-type="all">Update whole Searchindex</span>
                </div>
                <div class="mt-2 mb-2 col-6 col-md-4 col-lg-3">
                    <span class="d-block p-4 bg-white text-center btn" id="deleteSearchIndex" href="">Delete whole Searchindex</span>
                </div>
            </div>
		</div>
	</div>
</main>


    <script type="application/javascript">
        $(function() {

            $(".updateSearchIndex").on("click", function() {
                if ($(this).data("type") == "specific") {
                    //TODO popup with parliaments and textarea for comma separated list of MediaIDs

                    $.ajax({
                        url: config["dir"]["root"] + "/server/ajaxServer.php",
                        method:"POST",
                        data: {"a":"searchIndexUpdate","type":"specific","parliament":"DE","mediaIDs":"DE-0190062070,DE-0190077128"},
                        success: function(ret) {
                            console.log(ret);
                        }
                    })

                } else if ($(this).data("type") == "all") {
                    //TODO Popup with parliaments
                    //TODO Waitingscreen until response (will take some time)
                    $.ajax({
                        url: config["dir"]["root"] + "/server/ajaxServer.php",
                        method:"POST",
                        data: {"a":"searchIndexUpdate","type":"all","parliament":"DE"},
                        success: function(ret) {
                           console.log(ret);
                        }
                    })
                }

            });

            $("#deleteSearchIndex").on("click", function() {
                //TODO Dialog with parliament and if index should be re-initialized (true) or nor (false)
                $.ajax({
                    url: config["dir"]["root"] + "/server/ajaxServer.php",
                    method:"POST",
                    data: {"a":"searchIndexDelete","parliament":"DE","init":"true"},
                    success: function(ret) {
                        console.log(ret);
                    }
                })
            })


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