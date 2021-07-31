<?php
error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);

if (!function_exists("L")) {
    require_once(__DIR__."/../../../i18n.class.php");
    $i18n = new i18n(__DIR__.'/../../../lang/lang_{LANGUAGE}.json', __DIR__.'/../../../langcache/', 'de');
    $i18n->init();
}
?>
<div class="row">
    <div class="col-12">
        <ul class="nav nav-tabs" role="tablist">
            <!--
            <li class="nav-item">
                <a class="nav-link active" id="people-tab" data-toggle="tab" href="#people" role="tab" aria-controls="people" aria-selected="true"><span class="icon-torso"></span> People</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="organisations-tab" data-toggle="tab" href="#organisations" role="tab" aria-controls="organisations" aria-selected="false"><span class="icon-bank"></span> Organisations</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="documents-tab" data-toggle="tab" href="#documents" role="tab" aria-controls="documents" aria-selected="false"><span class="icon-doc-text"></span> Documents</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="terms-tab" data-toggle="tab" href="#terms" role="tab" aria-controls="terms" aria-selected="false"><span class="icon-tag-1"></span> Terms</a>
            </li>
            -->
            <li class="nav-item ml-auto">
                <a class="nav-link active" id="data-tab" data-toggle="tab" href="#data" role="tab" aria-controls="data" aria-selected="true"><span class="icon-download"></span> Data</a>
            </li>
        </ul>
        <div class="tab-content">
            <!--
            <div class="tab-pane fade show active" id="people" role="tabpanel" aria-labelledby="people-tab">
                <div class="relationshipsList row row-cols-1 row-cols-md-2 row-cols-lg-3">
                <?php 
                foreach ($speech["relationships"]["people"]["data"] as $relationshipItem) {
                ?>
                    <div class="entityPreview col" data-type="<?= $relationshipItem["type"] ?>"><a href="<?= $config["dir"]["root"]."/".$relationshipItem["type"]."/".$relationshipItem["id"] ?>"><?= $relationshipItem["attributes"]["label"] ?></a></div>
                <?php 
                } 
                ?>
                </div>
            </div>
            <div class="tab-pane fade" id="organisations" role="tabpanel" aria-labelledby="organisations-tab">
                <div class="relationshipsList row row-cols-1 row-cols-md-2 row-cols-lg-3">
                <?php 
                foreach ($speech["relationships"]["organisations"]["data"] as $relationshipItem) {
                ?>
                    <div class="entityPreview col" data-type="<?= $relationshipItem["type"] ?>"><a href="<?= $config["dir"]["root"]."/".$relationshipItem["type"]."/".$relationshipItem["id"] ?>"><?= $relationshipItem["attributes"]["label"] ?></a></div>
                <?php 
                } 
                ?>
                </div>
            </div>
            <div class="tab-pane fade" id="documents" role="tabpanel" aria-labelledby="documents-tab">
                <div class="relationshipsList row row-cols-1 row-cols-md-2 row-cols-lg-3">
                <?php 
                foreach ($speech["relationships"]["documents"]["data"] as $relationshipItem) {
                ?>
                    <div class="entityPreview col" data-type="<?= $relationshipItem["type"] ?>"><a href="<?= $config["dir"]["root"]."/".$relationshipItem["type"]."/".$relationshipItem["id"] ?>"><?= $relationshipItem["attributes"]["label"] ?></a></div>
                <?php 
                } 
                ?>
                </div>
            </div>
            <div class="tab-pane fade" id="terms" role="tabpanel" aria-labelledby="terms-tab">
                [CONTENT]
            </div>
            <div class="tab-pane fade  show active bg-white" id="data" role="tabpanel" aria-labelledby="data-tab">
                <table id="dataTable" class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>Key</th>
                            <th>Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        foreach ($flatDataArray as $key => $value) {
                            echo '<tr><td>'.$key.'</td><td>'.$value.'</td><tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            -->
        </div>
    </div>
</div>
<!--
<script type="text/javascript">
    $('#dataTable').bootstrapTable({
        showToggle: false,
        multiToggleDefaults: [],
        search: true,
        searchAlign: 'left',
        buttonsAlign: 'right',
        showExport: true,
        exportDataType: 'basic',
        exportTypes: ['csv', 'excel', 'txt', 'json'],
        exportOptions: {
            htmlContent: true,
            excelstyles: ['mso-data-placement', 'color', 'background-color'],
            fileName: 'Export',
            onCellHtmlData: function(cell, rowIndex, colIndex, htmlData) {
                var cleanedString = cell.html().replace(/<br\s*[\/]?>/gi, "\r\n");
                //htmlData = cleanedString;
                return htmlData;
            }
        },
        sortName: false,
        cardView: false,
        locale: 'de-DE'
    });
</script>
-->