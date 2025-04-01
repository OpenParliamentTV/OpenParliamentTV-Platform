<?php
error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);

$relatedPeopleHTML = "";
$relatedPeopleHTMLNER = "";
$tmpCount = 0;
foreach ($speech["relationships"]["people"]["data"] as $relationshipKey=>$relationshipItem) {
    $tmpPeople["NER"] = array();
    $tmpPeople["speaker"] = array();
    foreach ($speech["annotations"]["data"] as $annotation) {
        if ($annotation["id"] == $relationshipItem["id"]) {
            $speech["relationships"]["people"]["data"][$relationshipKey]["annotations"][] = $annotation;

            if ($annotation["attributes"]["context"] != "NER") {
                if (in_array($annotation["id"],$tmpPeople["speaker"])) {
                    continue;
                }
                $tmpPeople["speaker"][] = $annotation["id"];
                $relationshipItem["attributes"]["context"] = $annotation["attributes"]["context"];

                ob_start();
                include __DIR__."../../../components/entity.preview.small.php";
                $entityHTML = ob_get_clean();

                $relatedPeopleHTML .= $entityHTML;

            } else if ($config["display"]["ner"]) {
                if (in_array($annotation["id"],$tmpPeople["NER"])) {
                    continue;
                }
                $tmpCount = countNERfrequency($speech["annotations"]["data"],$annotation["id"]);
                $tmpPeople["NER"][] = $annotation["id"];

                ob_start();
                include __DIR__."../../../components/entity.preview.small.php";
                $entityHTML = ob_get_clean();

                $relatedPeopleHTMLNER .= $entityHTML;
            }
        }
    }
}

$tmpCount = 0;
$relatedOrganisationsHTML = "";
$relatedOrganisationsHTMLNER = "";
if (isset($speech["relationships"]["organisations"]["data"]) && count($speech["relationships"]["organisations"]["data"]) > 0) {

    foreach ($speech["relationships"]["organisations"]["data"] as $relationshipItem) {

        $tmpOrganisations["NER"] = array();
        $tmpOrganisations["default"] = array();

        foreach ($speech["annotations"]["data"] as $annotation) {

            if ($annotation["id"] == $relationshipItem["id"]) {

                if ($annotation["attributes"]["context"] != "NER") {

                    if (in_array($annotation["id"],$tmpOrganisations["default"])) {
                        continue;
                    }

                    $tmpOrganisations["default"][] = $annotation["id"];

                    ob_start();
                    include __DIR__."../../../components/entity.preview.small.php";
                    $entityHTML = ob_get_clean();

                    $relatedOrganisationsHTML .= $entityHTML;

                } else if ($config["display"]["ner"]) {

                    if (in_array($annotation["id"],$tmpOrganisations["NER"])) {
                        continue;
                    }
                    $tmpCount = countNERfrequency($speech["annotations"]["data"],$annotation["id"]);
                    $tmpOrganisations["NER"][] = $annotation["id"];

                    ob_start();
                    include __DIR__."../../../components/entity.preview.small.php";
                    $entityHTML = ob_get_clean();

                    $relatedOrganisationsHTMLNER .= $entityHTML;

                }
            }
        }
    }

}

$tmpCount = 0;
$relatedDocumentsHTML = "";
$relatedDocumentsHTMLNER = "";
if (isset($speech["relationships"]["documents"]["data"]) && count($speech["relationships"]["documents"]["data"]) > 0) {

    foreach ($speech["relationships"]["documents"]["data"] as $relationshipItem) {

        $tmpDocuments["NER"] = array();
        $tmpDocuments["default"] = array();

        foreach ($speech["annotations"]["data"] as $annotation) {

            if ($annotation["id"] == $relationshipItem["id"]) {

                if ($annotation["attributes"]["context"] != "NER") {

                    if (in_array($annotation["id"],$tmpDocuments["default"])) {
                        continue;
                    }

                    $tmpDocuments["default"][] = $annotation["id"];

                    ob_start();
                    include __DIR__."../../../components/entity.preview.small.php";
                    $entityHTML = ob_get_clean();

                    $relatedDocumentsHTML .= $entityHTML;

                } else if ($config["display"]["ner"]) {

                    if (in_array($annotation["id"],$tmpDocuments["NER"])) {
                        continue;
                    }
                    $tmpCount = countNERfrequency($speech["annotations"]["data"],$annotation["id"]);
                    $tmpDocuments["NER"][] = $annotation["id"];

                    ob_start();
                    include __DIR__."../../../components/entity.preview.small.php";
                    $entityHTML = ob_get_clean();

                    $relatedDocumentsHTMLNER .= $entityHTML;

                }
            }
        }
    }

}

$tmpCount = 0;
$relatedTermsHTML = "";
$relatedTermsHTMLNER = "";
if (isset($speech["relationships"]["terms"]["data"]) && count($speech["relationships"]["terms"]["data"]) > 0) {

    foreach ($speech["relationships"]["terms"]["data"] as $relationshipItem) {

        $tmpTerms["NER"] = array();
        $tmpTerms["default"] = array();

        foreach ($speech["annotations"]["data"] as $annotation) {

            if ($annotation["id"] == $relationshipItem["id"]) {

                if ($annotation["attributes"]["context"] != "NER") {

                    if (in_array($annotation["id"],$tmpTerms["default"])) {
                        continue;
                    }

                    $tmpTerms["default"][] = $annotation["id"];

                    ob_start();
                    include __DIR__."../../../components/entity.preview.small.php";
                    $entityHTML = ob_get_clean();

                    $relatedTermsHTML .= $entityHTML;

                } else if ($config["display"]["ner"]) {

                    if (in_array($annotation["id"],$tmpTerms["NER"])) {
                        continue;
                    }
                    $tmpCount = countNERfrequency($speech["annotations"]["data"],$annotation["id"]);
                    $tmpTerms["NER"][] = $annotation["id"];

                    ob_start();
                    include __DIR__."../../../components/entity.preview.small.php";
                    $entityHTML = ob_get_clean();

                    $relatedTermsHTMLNER .= $entityHTML;

                }
            }
        }
    }

}

$nerPanel = '';

if ($config["display"]["ner"]) {
    $nerPanel = 
    '<hr>
    <div class="relationshipsCategoryHeader">'.L::automaticallyDetected.' <a class="alert ms-1 px-1 py-0 alert-warning" data-bs-toggle="modal" data-bs-target="#nerModal" href="#" style="font-weight: lighter;"><span class="icon-attention me-1"></span><u>beta</u></a></div>
    <div class="relationshipsList row row-cols-1 row-cols-sm-2 row-cols-md-1 row-cols-lg-2">
        '.$relatedPeopleHTMLNER.' '.$relatedOrganisationsHTMLNER.' '.$relatedDocumentsHTMLNER.' '.$relatedTermsHTMLNER.' 
    </div>';
}

?>
<div class="tab-content">
    <div class="tab-pane timebasedTab fade show active" id="proceedings" role="tabpanel">
        <?php if (isset($textContentsHTML)) { ?>
            <?= $textContentsHTML ?>
        <?php } else { ?>
            <div class="container h-100" style="user-select: none;">
                <div class="row h-100 align-items-center text-center">
                    <div class="col"><?= L::messageNoProceedings ?></div>
                </div>
            </div>
        <?php } ?>
    </div>
    <div class="tab-pane fade" id="relationships" role="tabpanel">
        <div class="relationshipsCategoryHeader"><?= L::personPlural ?></div>
        <div class="relationshipsList row row-cols-1 row-cols-sm-2 row-cols-md-1 row-cols-lg-2"><?= $relatedPeopleHTML ?></div>
        <hr>
        <div class="relationshipsCategoryHeader"><?= L::documents ?></div>
        <div class="relationshipsList row row-cols-1 row-cols-sm-2 row-cols-md-1 row-cols-lg-2"><?= $relatedDocumentsHTML ?></div>
        <?= $nerPanel ?>
    </div>
    <div class="tab-pane fade" id="data" role="tabpanel">
        <div class="alert alert-info" role="alert">
            <div class="mb-1"><?php echo L::messageOpenData; ?>: <a class="text-reset" href="<?= $config["dir"]["root"] ?>/api"><?= $config["dir"]["root"] ?>/api</a></div>
        </div>
        <div class="input-group">
            <span class="input-group-text me-0">API URL</span>
            <input id="apiLink" class="form-control m-0" style="border-width: 1px;" type="text" value="<?= $speech["links"]["self"] ?>">
            <a href="<?= $speech["links"]["self"] ?>" target="_blank" class="btn btn-sm input-group-text">
                <span class="icon-right-open-big"></span>
                <span class="d-none d-md-inline"><?php echo L::showResult; ?></span>
            </a>
        </div>
        <hr>
        <div class="relationshipsCategoryHeader"><?= L::data ?></div>
        <table class="table table-striped table-bordered">
            <tbody>
                <tr>
                    <td><?= L::source ?></td>
                    <td><?= $speech["attributes"]["creator"] ?>, <?php echo html_entity_decode($speech["attributes"]["license"]); ?></td>
                </tr>
                <tr>
                    <td><?= L::citeAs ?></td>
                    <td><?= $speech["attributes"]["creator"] ?> via <?= L::brand ?></td>
                </tr>
                <tr>
                    <td><?= L::retrievedFrom ?></td>
                    <td><a href="<?= $speech["attributes"]["sourcePage"] ?>" target="_blank"><?= $speech["attributes"]["sourcePage"] ?></a></td>
                </tr>
            </tbody>
        </table>
        <table class="table table-striped table-bordered">
            <tbody>
                <tr>
                    <td><?= L::electoralPeriod ?></td>
                    <td><a href="<?= $config["dir"]["root"] ?>/electoralPeriod/<?= $speech["relationships"]["electoralPeriod"]["data"]["id"] ?>"><?= $speech["relationships"]["electoralPeriod"]["data"]["attributes"]["number"] ?></a></td>
                </tr>
                <tr>
                    <td><?= L::session ?></td>
                    <td><a href="<?= $config["dir"]["root"] ?>/session/<?= $speech["relationships"]["session"]["data"]["id"] ?>"><?= $speech["relationships"]["session"]["data"]["attributes"]["number"] ?></a></td>
                </tr>
                <tr>
                    <td><?= L::agendaItem ?></td>
                    <td><a href="<?= $config["dir"]["root"] ?>/agendaItem/<?= $speech["attributes"]["parliament"] ?>-<?= $speech["relationships"]["agendaItem"]["data"]["id"] ?>"><?= $speech["relationships"]["agendaItem"]["data"]["attributes"]["title"] ?></a></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>