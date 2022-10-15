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
			<p>This search engine and interactive video platform for parliamentary debates is part of the <a href="https://openparliament.tv">Open Parliament TV project</a>. Our goal is to <b>make parliamentary debates more transparent and accessible</b>. </p>
			<p>With Open Parliament TV we develop the workflows, tools and user interfaces required to facilitate new ways of experiencing political speeches. At the core we <b>synchronise</b> the <b>video recordings</b> with the <b>text proceedings</b>. This is how we can provide a full text search for the videos.</p>
			<p>By connecting the video recording with the proccedings text we can additionally enrich the videos with</p>
			<ul>
				<li><b>interactive transcripts</b> <br>(click on a sentence &gt; jump to point of time in the video)</li>
				<li><b>context-related annotations</b> <br>(display relevant documents at certain points of time)</li>
				<li>improved means of <b>participation</b> <br>(discuss, cite and share specific video segments)</li>
			</ul>
			<p>With Open Parliament TV we provide a tool for citizens and journalists, which significantly simplifies <b>finding</b>, <b>sharing</b> and <b>citing</b> video snippets from parliamentary speeches. Based on single terms or sentence fragments, the relevant video snippets can be found in split seconds, played and then embedded as a quote in other platforms.</p>
			<p>Besides the full text search, Open Parliament TV includes <b>additional entry points</b> into the debates, like finding speeches via the profile page of a faction or watching all speeches in which a specific document or law is mentioned. In the future we want to extend these functionalities with semi-automated analysis of the plenary proceedings.</p>
			<a href="https://openparliament.tv" target="_blank" class="btn btn-primary btn-sm d-block"><span class="icon-right-open-big mr-1"></span> More about the vision, mission, strategy and application areas of Open Parliament TV</a>
			<hr>
			<h3>Open Data</h3>
			<p>All Data on Open Parliament TV can be requested via our <b>Open Data API</b>: </p>
			<ul>
				<li><a href="api">API Documentation</a></li>
			</ul>
			<hr>
			<h3>Open Source</h3>
			<p>Open Parliament TV is a <b>non-commercial Open Source project</b>. All project components are published under <b>open licenses</b> at Github: </p>
			<ul>
				<li><a href="https://github.com/OpenParliamentTV/OpenParliamentTV-Architecture" target="_blank">OpenParliamentTV-Architecture</a></li>
				<li><a href="https://github.com/OpenParliamentTV/OpenParliamentTV-Platform" target="_blank">OpenParliamentTV-Platform</a></li>
				<li><a href="https://github.com/OpenParliamentTV/OpenParliamentTV-Parsers" target="_blank">OpenParliamentTV-Parsers</a></li>
				<li><a href="https://github.com/OpenParliamentTV/OpenParliamentTV-NEL" target="_blank">OpenParliamentTV-NEL</a></li>
				<li><a href="https://github.com/OpenParliamentTV/OpenParliamentTV-Alignment" target="_blank">OpenParliamentTV-Alignment</a></li>
			</ul>
			<hr>
			<h3>FAQ</h3>
			<p>Questions about the project, our data or technical specifications are answered in our <a href="https://openparliament.tv/faq">Frequently Asked Questions</a>.</p>
		</div>
		<div class="col-12 col-lg-4">
			<hr class="d-block d-lg-none">
			<h3>Contact & Requests</h3>
			<p>Joscha Jäger, Founder & Project Lead<br>
			Mail: joscha.jaeger [AT] openparliament.tv<br>
			Twitter: <a href="https://twitter.com/OpenParlTV" target="_blank">@OpenParlTV</a></p>
		</div>
	</div>
</main>
    <?php

}

include_once(__DIR__ . '/../../footer.php');

?>