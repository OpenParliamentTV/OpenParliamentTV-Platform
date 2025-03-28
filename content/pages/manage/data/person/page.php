<?php
include_once(__DIR__ . '/../../../../../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"], "requestPage", $pageType);

if ($auth["meta"]["requestStatus"] != "success") {

    $alertText = $auth["errors"][0]["detail"];
    include_once (__DIR__."/../../../login/page.php");

} else {

include_once(__DIR__ . '/../../../../header.php'); ?>
<main class="container-fluid subpage">
    <div class="row">
        <?php include_once(__DIR__ . '/../../sidebar.php'); ?>
        <div class="sidebar-content">
            <div class="row" style="position: relative; z-index: 1">
                <div class="col-12">
                    <h2>Manage Detail Person</h2>
                    <table id="entitiesTable"></table>
                </div>
            </div>
        </div>
    </div>
</main>

    <script type="text/javascript">

        $(function(){

            $('#entitiesTable').bootstrapTable({
                url: config["dir"]["root"] + "/api/v1/?action=getOverview&itemType=person",
                pagination: true,
                sidePagination: "server",
                dataField: "rows",
                totalField: "total",
                search: true,
                serverSort: true,
                columns: [
                    {
                        field: "PersonLabel",
                        title: "Name",
                        sortable: true
                    },
                    {
                        field: "PersonID",
                        title: "ID",
                        sortable: true
                    },
                    {
                        field: "PersonGender",
                        title: "Gender",
                        sortable: true
                    },
                    {
                        field: "PersonPartyOrganisationID",
                        title: "Party",
                        sortable: true,
                        formatter: function(value, row) {

                            return row["PartyLabel"]+" ("+value+")"

                        }
                    },
                    {
                        field: "PersonFactionOrganisationID",
                        title: "Party",
                        sortable: true,
                        formatter: function(value, row) {

                            return row["FactionLabel"]+" ("+value+")"

                        }
                    },
                    {
                        field: "PersonID",
                        title: "Action",
                        sortable: false,
                        formatter: function(value, row) {

                            return "<a href='"+config["dir"]["root"]+"/manage/data/person/"+value+"' target='_blank' class='icon-pencil btn btn-outline-secondary btn-sm' data-id='"+value+"'></a>";

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