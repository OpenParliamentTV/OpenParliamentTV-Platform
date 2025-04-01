<?php

include_once(__DIR__ . '/../../../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"], "requestPage", $pageType);

if ($auth["meta"]["requestStatus"] != "success") {

    $alertText = $auth["errors"][0]["detail"];
    include_once (__DIR__."/../login/page.php");

} else {

include_once(__DIR__ . '/../../header.php');
?>
<main class="container subpage">
	<div class="row" style="position: relative; z-index: 1">
		<div class="col-12">
			<h2><?php echo L::about; ?></h2>
		</div>
	</div>
	<div class="row">
		<div class="col-12 col-lg-8">
			<p>Ce moteur de recherche et cette plateforme vidéo interactive pour les débats parlementaires font partie du <a href="https://openparliament.tv">projet Open Parliament TV</a>. Notre objectif est de <b>rendre les débats parlementaires plus transparents et accessibles</b>.</p>
			<p>Avec Open Parliament TV, nous développons les processus, outils et interfaces utilisateur nécessaires pour faciliter de nouvelles façons d'expérimenter les discours politiques. Au cœur de notre approche, nous <b>synchronisons</b> les <b>enregistrements vidéo</b> avec les <b>procès-verbaux</b>. C'est ainsi que nous pouvons fournir une recherche en texte intégral pour les vidéos.</p>
			<p>En connectant l'enregistrement vidéo avec le texte du procès-verbal, nous pouvons enrichir les vidéos avec</p>
			<ul>
				<li><b>des transcriptions interactives</b> <br>(cliquez sur une phrase > sautez au moment correspondant dans la vidéo)</li>
				<li><b>des annotations contextuelles</b> <br>(affichage de documents pertinents à certains moments)</li>
				<li>des moyens améliorés de <b>participation</b> <br>(discuter, citer et partager des segments vidéo spécifiques)</li>
			</ul>
			<p>Avec Open Parliament TV, nous fournissons un outil aux citoyens et aux journalistes qui simplifie considérablement la <b>recherche</b>, le <b>partage</b> et la <b>citation</b> d'extraits vidéo des discours parlementaires. Basé sur des termes individuels ou des fragments de phrases, les extraits vidéo pertinents peuvent être trouvés en quelques secondes, lus puis intégrés comme citation dans d'autres plateformes.</p>
			<p>Outre la recherche en texte intégral, Open Parliament TV inclut des <b>points d'entrée supplémentaires</b> dans les débats, comme la recherche de discours via la page de profil d'un groupe parlementaire ou le visionnage de tous les discours dans lesquels un document ou une loi spécifique est mentionné. À l'avenir, nous souhaitons étendre ces fonctionnalités avec des analyses semi-automatisées des procès-verbaux pléniers.</p>
			<a href="https://openparliament.tv" target="_blank" class="btn btn-primary btn-sm d-block"><span class="icon-right-open-big me-1"></span> En savoir plus sur la vision, la mission, la stratégie et les domaines d'application d'Open Parliament TV</a>
			<hr>
			<h3>Open Data</h3>
			<p>Toutes les données sur Open Parliament TV peuvent être demandées via notre <b>API Open Data</b> : </p>
			<ul>
				<li><a href="api">Documentation API</a></li>
			</ul>
			<hr>
			<h3>Open Source</h3>
			<p>Open Parliament TV est un <b>projet Open Source non commercial</b>. Tous les composants du projet sont publiés sous des <b>licences libres</b> sur Github : </p>
			<ul>
				<li><a href="https://github.com/OpenParliamentTV/OpenParliamentTV-Architecture" target="_blank">OpenParliamentTV-Architecture</a></li>
				<li><a href="https://github.com/OpenParliamentTV/OpenParliamentTV-Platform" target="_blank">OpenParliamentTV-Platform</a></li>
				<li><a href="https://github.com/OpenParliamentTV/OpenParliamentTV-Parsers" target="_blank">OpenParliamentTV-Parsers</a></li>
				<li><a href="https://github.com/OpenParliamentTV/OpenParliamentTV-NEL" target="_blank">OpenParliamentTV-NEL</a></li>
				<li><a href="https://github.com/OpenParliamentTV/OpenParliamentTV-Alignment" target="_blank">OpenParliamentTV-Alignment</a></li>
			</ul>
			<hr>
			<h3>FAQ</h3>
			<p>Les questions sur le projet, nos données ou les spécifications techniques sont répondues dans nos <a href="https://openparliament.tv/faq">Questions fréquentes</a>.</p>
		</div>
		<div class="col-12 col-lg-4">
			<hr class="d-block d-lg-none">
			<h3>Contact & Demandes</h3>
			<p>Joscha Jäger, Fondateur & Chef de projet<br>
			Mail : joscha.jaeger [AT] openparliament.tv<br>
			Twitter : <a href="https://twitter.com/OpenParlTV" target="_blank">@OpenParlTV</a></p>
		</div>
	</div>
</main>
    <?php

}

include_once(__DIR__ . '/../../footer.php');

?> 