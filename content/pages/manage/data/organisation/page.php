<?php
include_once(__DIR__ . '/../../../../../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"], "requestPage", $pageType);

if ($auth["meta"]["requestStatus"] != "success") {

    $alertText = $auth["errors"][0]["detail"];
    include_once (__DIR__."/../../../login/page.php");

} else {

    include_once(__DIR__ . '/../../../../header.php'); ?>
    <main class="container subpage">
        <div class="row" style="position: relative; z-index: 1">
            <div class="col-12">
                <h2>Manage Detail Organisation</h2>
                <table id="entitiesTable"></table>
            </div>
        </div>
    </main>
    <script type="text/javascript">

        $(function(){

            $('#entitiesTable').bootstrapTable({
                url: config["dir"]["root"] + "/api/v1/?action=getOverview&itemType=organisation",
                pagination: true,
                sidePagination: "server",
                dataField: "rows",
                totalField: "total",
                search: true,
                serverSort: true,
                columns: [
                    {
                        field: "OrganisationLabel",
                        title: "Name",
                        sortable: true,
                        formatter: function(value, row) {
                            let tmpAltLabel = "";
                            let tmpAltLabels = JSON.parse(row["OrganisationLabelAlternative"]);
                            if (Array.isArray(tmpAltLabels)) {
                                tmpAltLabel = ", "+tmpAltLabels[0];
                            }
                            return value + tmpAltLabel;
                        }
                    },
                    {
                        field: "OrganisationID",
                        title: "ID",
                        sortable: true
                    },
                    {
                        field: "OrganisationType",
                        title: "Type",
                        sortable: true
                    }
                ]
            });


        })

    </script>
<?php
    include_once(__DIR__ . '/../../../../footer.php');
}

?>


