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
                    <h2><?= L::manageMedia; ?></h2>
                    <div class="card mb-3">
                        <div class="card-body">
                            <a href="<?= $config["dir"]["root"] ?>/manage/media/new" class="btn btn-outline-success btn-sm me-1"><span class="icon-plus"></span><?= L::manageMediaNew; ?></a>
                        </div>
                    </div>
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="all-media-tab" data-bs-toggle="tab" data-bs-target="#all-media" role="tab" aria-controls="all-media" aria-selected="true">All Media</a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane bg-white fade show active" id="all-media" role="tabpanel" aria-labelledby="all-media-tab">
                            <?php 
                                // Include the filter bar component with only the filter container
                                $showSearchBar = false;
                                $showParliamentFilter = false;
                                $showToggleButton = false;
                                $showFactionChart = false;
                                $showDateRange = true;
                                $showSearchSuggestions = true;
                                include_once(__DIR__ . '/../../../components/search.filterbar.php'); 
                            ?>
                            <div id="speechListContainer" class="col">
                                <div class="resultWrapper"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
    #filterbar {
        margin-top: 0px !important;
        padding-top: 0px !important;
    }
    .filterContainer {
        display: block !important;
    }
    .searchContainer {
        display: none !important;
    }
</style>

<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/search/client/search.js"></script>

<?php
include_once(__DIR__ . '/../../../footer.php');
}
?>