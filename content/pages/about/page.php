<?php

include_once(__DIR__ . '/../../../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"], "requestPage", $pageType);

if ($auth["meta"]["requestStatus"] != "success") {

    $alertText = $auth["errors"][0]["detail"];
    include_once (__DIR__."/../login/page.php");

} else {

    include_once(include_custom(realpath(__DIR__ . '/../../header.php'),false));
    ?>
    <main class="container subpage">
        <div class="row" style="position: relative; z-index: 1">
            <div class="col-12">
                <h2><?= L::about(); ?></h2>
            </div>
        </div>
        <div class="row">
            <div class="col-12 col-lg-8">
                <ul>
                    <li><a href="https://github.com/OpenParliamentTV/OpenParliamentTV-Architecture" target="_blank">OpenParliamentTV-Architecture</a></li>
                    <li><a href="https://github.com/OpenParliamentTV/OpenParliamentTV-Platform" target="_blank">OpenParliamentTV-Platform</a></li>
                    <li><a href="https://github.com/OpenParliamentTV/OpenParliamentTV-Parsers" target="_blank">OpenParliamentTV-Parsers</a></li>
                    <li><a href="https://github.com/OpenParliamentTV/OpenParliamentTV-NEL" target="_blank">OpenParliamentTV-NEL</a></li>
                    <li><a href="https://github.com/OpenParliamentTV/OpenParliamentTV-Alignment" target="_blank">OpenParliamentTV-Alignment</a></li>
                </ul>
            </div>
        </div>
    </main>
    <?php

}

include_custom(realpath(__DIR__ . '/../../footer.php'));

?>