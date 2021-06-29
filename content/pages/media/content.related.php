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
        </ul>
        <div class="tab-content">
            <div class="tab-pane fade show active" id="people" role="tabpanel" aria-labelledby="people-tab">
                [CONTENT]
            </div>
            <div class="tab-pane fade" id="organisations" role="tabpanel" aria-labelledby="organisations-tab">
                [CONTENT]
            </div>
            <div class="tab-pane fade" id="documents" role="tabpanel" aria-labelledby="documents-tab">
                [CONTENT]
            </div>
            <div class="tab-pane fade" id="terms" role="tabpanel" aria-labelledby="terms-tab">
                [CONTENT]
            </div>
        </div>
    </div>
</div>