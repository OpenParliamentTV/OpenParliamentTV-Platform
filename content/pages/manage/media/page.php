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
                    <h2><?php echo L::manageMedia; ?></h2>
                    <div class="card mb-3">
                        <div class="card-body">
                            <a href="<?= $config["dir"]["root"] ?>/manage/media/new" class="btn btn-outline-success btn-sm me-1"><span class="icon-plus"></span><?php echo L::manageMediaNew; ?></a>
                        </div>
                    </div>
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="all-media-tab" data-bs-toggle="tab" data-bs-target="#all-media" role="tab" aria-controls="all-media" aria-selected="true">All Media</a>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane bg-white fade show active" id="all-media" role="tabpanel" aria-labelledby="all-media-tab">
                            <table id="manageMediaOverviewTable" 
                                   data-toggle="table" 
                                   data-classes="table table-striped"
                                   data-search="true"
                                   data-pagination="true"
                                   data-page-size="25"
                                   data-sortable="true"
                                   data-show-export="true"
                                   data-export-types="['csv', 'excel', 'txt', 'json']"
                                   data-locale="<?php echo $lang; ?>"
                                   class="table">
                                <thead>
                                    <tr>
                                        <th data-field="mediaId" data-sortable="true">Media ID</th>
                                        <th data-field="date" data-sortable="true">Date</th>
                                        <th data-field="title" data-sortable="true">Title</th>
                                        <th data-field="speaker" data-sortable="true">Main Speaker</th>
                                        <th data-field="aligned" data-sortable="true">Aligned</th>
                                        <th data-field="public" data-sortable="true">Public</th>
                                        <th data-field="actions" data-sortable="false">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    require_once(__DIR__ . '/../../../../modules/utilities/safemysql.class.php');

                                    try {
                                        $dbp = new SafeMySQL(array(
                                            'host' => $config["parliament"]["DE"]["sql"]["access"]["host"],
                                            'user' => $config["parliament"]["DE"]["sql"]["access"]["user"],
                                            'pass' => $config["parliament"]["DE"]["sql"]["access"]["passwd"],
                                            'db' => $config["parliament"]["DE"]["sql"]["db"]
                                        ));

                                        $db = new SafeMySQL(array(
                                            'host' => $config["platform"]["sql"]["access"]["host"],
                                            'user' => $config["platform"]["sql"]["access"]["user"],
                                            'pass' => $config["platform"]["sql"]["access"]["passwd"],
                                            'db' => $config["platform"]["sql"]["db"]
                                        ));

                                        $mediaItems = $dbp->getAll("
                                            SELECT 
                                                m.MediaID,
                                                m.MediaDateStart,
                                                m.MediaAligned,
                                                m.MediaPublic,
                                                ai.AgendaItemTitle,
                                                GROUP_CONCAT(DISTINCT CASE 
                                                    WHEN a.AnnotationType = 'person' AND a.AnnotationContext = 'main-speaker' 
                                                    THEN a.AnnotationResourceID 
                                                    END) as MainSpeakerID
                                            FROM ?n AS m
                                            LEFT JOIN ?n AS ai ON m.MediaAgendaItemID = ai.AgendaItemID
                                            LEFT JOIN ?n AS a ON m.MediaID = a.AnnotationMediaID
                                            GROUP BY m.MediaID
                                            ORDER BY m.MediaDateStart DESC",
                                            $config["parliament"]["DE"]["sql"]["tbl"]["Media"],
                                            $config["parliament"]["DE"]["sql"]["tbl"]["AgendaItem"],
                                            $config["parliament"]["DE"]["sql"]["tbl"]["Annotation"]
                                        );

                                        foreach ($mediaItems as $item) {
                                            $speakerName = 'No speaker';
                                            if ($item["MainSpeakerID"]) {
                                                $speaker = $db->getRow("SELECT PersonLabel FROM ?n WHERE PersonID = ?s", 
                                                    $config["platform"]["sql"]["tbl"]["Person"], 
                                                    $item["MainSpeakerID"]
                                                );
                                                if ($speaker) {
                                                    $speakerName = $speaker["PersonLabel"];
                                                }
                                            }

                                            echo '<tr>';
                                            echo '<td>' . htmlspecialchars($item["MediaID"]) . '</td>';
                                            echo '<td>' . date('Y-m-d', strtotime($item["MediaDateStart"])) . '</td>';
                                            echo '<td>' . htmlspecialchars($item["AgendaItemTitle"]) . '</td>';
                                            echo '<td>' . htmlspecialchars($speakerName) . '</td>';
                                            echo '<td>' . ($item["MediaAligned"] ? 'Yes' : 'No') . '</td>';
                                            echo '<td>' . ($item["MediaPublic"] ? 'Yes' : 'No') . '</td>';
                                            echo '<td>
                                                    <div class="list-group list-group-horizontal">
                                                        <a class="list-group-item list-group-item-action" title="'. L::view .'" href="'. $config["dir"]["root"] .'/media/' . $item["MediaID"] . '">
                                                            <span class="icon-eye"></span>
                                                        </a>
                                                        <a class="list-group-item list-group-item-action" title="'. L::edit .'" href="'. $config["dir"]["root"] .'/manage/media/' . $item["MediaID"] . '">
                                                            <span class="icon-pencil"></span>
                                                        </a>
                                                    </div>
                                                </td>';
                                            echo '</tr>';
                                        }
                                    } catch (Exception $e) {
                                        echo '<tr><td colspan="6" class="text-center text-danger">Error loading media data: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
include_once(__DIR__ . '/../../../footer.php');
}
?>