<?php
include_once(__DIR__ . '/../../../../../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"], "requestPage", $pageType);

if ($auth["meta"]["requestStatus"] != "success") {

    $alertText = $auth["errors"][0]["detail"];
    include_once (__DIR__."/../../../login/page.php");

} else {
    include_once(__DIR__ . '/../../../../header.php');
?>
<main class="container-fluid">
    <div class="d-flex">
        <?php include_once(__DIR__ . '/../../sidebar.php'); ?>
        <div class="flex-grow-1" style="padding-top: 30px; padding-bottom: 30px;">
            <div class="row" style="position: relative; z-index: 1">
                <div class="col-12">
                    <h2>Manage Detail Term</h2>
                    <table id="entitiesTable"></table>
                </div>
            </div>
        </div>
    </div>
</main>
    <script type="text/javascript">

        $(function(){

            $('#entitiesTable').bootstrapTable({
                url: config["dir"]["root"] + "/api/v1/?action=getOverview&itemType=term",
                pagination: true,
                sidePagination: "server",
                dataField: "rows",
                totalField: "total",
                search: true,
                serverSort: true,
                columns: [
                    {
                        field: "TermLabel",
                        title: "Name",
                        sortable: true,
                        formatter: function(value, row) {
                            let tmpAltLabel = "";
                            let tmpAltLabels = JSON.parse(row["TermLabelAlternative"]);
                            if (Array.isArray(tmpAltLabels)) {
                                tmpAltLabel = ", "+tmpAltLabels[0];
                            }
                            return value + tmpAltLabel;
                        }
                    },
                    {
                        field: "TermID",
                        title: "ID",
                        sortable: true,
                        formatter: function(value, row) {
                            return "<a href='https://www.wikidata.org/wiki/"+value+"'>"+value+"</a>";
                        }
                    },
                    {
                        field: "TermType",
                        title: "Type",
                        sortable: true
                    },
                    {
                        field: "TermID",
                        title: "Action",
                        sortable: true,
                        formatter: function(value, row) {

                            return "<a href='"+config["dir"]["root"]+"/manage/data/term/"+value+"' target='_blank' class='icon-pencil btn btn-outline-secondary btn-sm' data-id='"+value+"'></a>";

                        }
                    }
                ]
            });


        })

    </script>
<?php
    include_once(__DIR__ . '/../../../../footer.php');

}

?>