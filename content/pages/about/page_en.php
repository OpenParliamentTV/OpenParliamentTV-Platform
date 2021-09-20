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
			<p><img src="<?= $config["dir"]["root"] ?>/content/client/images/optv-logo.png" style="float: right; width: 200px; margin-left: 20px;">Plenary debates are the publicly visible outcome of controversial internal debates, hearings, negotiations and analyses. Videos of the respective speeches are the “spectacular” parts of politics, which find their way into newsrooms, social media feeds and Late Night Shows. But the way <b>video contents</b> are currently published is largely based upon sharing <b>short key moments</b>, with a clear <b>lack of contextual information</b> (full speech, relevant original documents, additional materials, other speeches on the same subject, public discourse, fact checks, etc.). </p>
			<p>This leaves us in a situation where video clips are used to share short moments, but as soon as the speeches are used for in-depth analyses, fact checks, learning or longform reporting, we rely solely on the text-based transcripts and quotes.</p>
			<hr>
			<h3>What is Open Parliament TV?</h3>
			<p>With Open Parliament TV we develop the workflows, tools and user interfaces required to facilitate new ways of experiencing political speeches. At the core we <b>synchronise</b> the <b>video recordings</b> with the <b>plenary proceedings</b>. This is how we can provide a full text search for the videos.</p>
			<p>By connecting the video recording with the proccedings text we can additionally enrich the videos with</p>
			<ul>
				<li><b>interactive transcripts</b> <br>(click on a sentence &gt; jump to point of time in the video)</li>
				<li><b>context-related annotations</b> <br>(display relevant documents at certain points of time)</li>
				<li>improved means of <b>participation</b> <br>(discuss, cite and share specific video segments)</li>
			</ul>
			<p>With Open Parliament TV journalists receive a tool, which significantly simplifies <b>finding</b>, <b>sharing</b> and <b>citing</b> video snippets from parliamentary speeches. Based on single terms or sentence fragments, the relevant video snippets can be found in split seconds, played and then embedded as a quote in other platforms.</p>
			<p>Besides the full text search, Open Parliament TV includes <b>additional entry points</b> into the debates, like finding speeches via the profile page of a faction or watching all speeches in which a specific document or law is mentioned. In the future we want to extend these functionalities with semi-automated analysis of the plenary proceedings.</p>
			<hr>
			<h3>Goal</h3>
			<p>We want to fundamentally change the way people interact with video-based publications of parliamentary debates. Our goal is to make debates in parliament more <b>transparent</b>, <b>accessible</b> and <b>understandable</b>.</p>
			<p>Starting with speeches in the German Bundestag, we have created a system of <b>interoperable components</b>, which allow a seamless transfer to regional parliaments, city councils, EU parliament sessions or additional national parliaments. This portability is a key component of the project and has been taken into account from the very beginning.</p>
			<p>In the long term, Open Parliament TV shall contribute to making political debates <b>accessible beyond the boundaries of single parliaments or countries</b>:</p>
			<a href="https://openparliament.tv/proposal" target="_blank" class="btn btn-primary btn-sm"><span class="icon-doc-text"></span>Open Parliament TV - Open Source Project Proposal</a>
		</div>
		<div class="col-12 col-lg-4">
			<hr class="d-block d-lg-none">
			<h3>Contact & Requests</h3>
			<p>Joscha Jäger, Project Lead<br>
			Mail: joscha.jaeger [AT] openparliament.tv<br>
			Twitter: <a href="https://twitter.com/OpenParlTV" target="_blank">@OpenParlTV</a></p>
			<hr>
			<h3>Open Data</h3>
			<p>All Data on Open Parliament TV can be requested via our <b>Open Data API</b>: </p>
			<ul>
				<li><a href="api">API Documentation</a></li>
			</ul>
			<hr>
			<h3>Open Source</h3>
			<p>Open Parliament TV is a <b>non-commercial Open Source project</b>. All project components are published under <b>open licenses</b> at Github: </p>
			<div class="alert alert-warning py-1 px-2">The repositories will be set public on the official launch date (20. October)</div>
			<ul>
				<li><a href="https://github.com/OpenParliamentTV/OpenParliamentTV-Architecture" target="_blank">OpenParliamentTV-Architecture</a></li>
				<li><a href="https://github.com/OpenParliamentTV/OpenParliamentTV-Platform" target="_blank">OpenParliamentTV-Platform</a></li>
				<li><a href="https://github.com/OpenParliamentTV/OpenParliamentTV-Parsers" target="_blank">OpenParliamentTV-Parsers</a></li>
				<li><a href="https://github.com/OpenParliamentTV/OpenParliamentTV-NEL" target="_blank">OpenParliamentTV-NEL</a></li>
				<li><a href="https://github.com/OpenParliamentTV/OpenParliamentTV-Alignment" target="_blank">OpenParliamentTV-Alignment</a></li>
			</ul>
			<!--
			<hr>
			<h3>FAQ</h3>
			<p>Questions about the project, our data or technical specifications are answered in our <a href="faq">Frequently Asked Questions</a>.</p>
			-->
		</div>
	</div>
	<hr>
	<div class="row">
		<div class="col-12">
			<h2>Team</h2>
			<div class="relationshipsList row row-cols-1 row-cols-sm-2 row-cols-lg-3">
				<div class="entityPreview col" data-type="person">
					<div class="entityContainer">
						<div class="linkWrapper">
								<div class="thumbnailContainer">
								<div class="rounded-circle">
									<img src="<?= $config["dir"]["root"] ?>/content/client/images/team/joscha.jpg" alt="Joscha Jäger">
								</div>
							</div>
							<div>
								<div class="entityTitle">Joscha Jäger</div>
								<div>Creative Technologist</div>
								<div><span class="icon-angle-right"></span>Project Lead</div>
							</div>
						</div>
					</div>
				</div>
				<div class="entityPreview col" data-type="person">
					<div class="entityContainer">
						<div class="linkWrapper">
								<div class="thumbnailContainer">
								<div class="rounded-circle">
									<img src="<?= $config["dir"]["root"] ?>/content/client/images/team/alexa.jpg" alt="Alexa Steinbrück">
								</div>
							</div>
							<div>
								<div class="entityTitle">Alexa Steinbrück</div>
								<div>Data Scientist</div>
								<div><span class="icon-angle-right"></span>Developer: Wikidata Integration & NEL</div>
							</div>
						</div>
					</div>
				</div>
				<div class="entityPreview col" data-type="person">
					<div class="entityContainer">
						<div class="linkWrapper">
								<div class="thumbnailContainer">
								<div class="rounded-circle">
									<img src="" alt="...">
								</div>
							</div>
							<div>
								<div class="entityTitle">Michael Morgenstern</div>
								<div>Developer & Designer</div>
								<div><span class="icon-angle-right"></span>Platform Development & -Architecture</div>
							</div>
						</div>
					</div>
				</div>
				<div class="entityPreview col" data-type="person">
					<div class="entityContainer">
						<div class="linkWrapper">
								<div class="thumbnailContainer">
								<div class="rounded-circle">
									<img src="<?= $config["dir"]["root"] ?>/content/client/images/team/olivier.jpg" alt="Olivier Aubert">
								</div>
							</div>
							<div>
								<div class="entityTitle">Olivier Aubert</div>
								<div>Research Engineer</div>
								<div><span class="icon-angle-right"></span>Developer: Data Models & -Workflows</div>
							</div>
						</div>
					</div>
				</div>
				<div class="entityPreview col" data-type="person">
					<div class="entityContainer">
						<div class="linkWrapper">
								<div class="thumbnailContainer">
								<div class="rounded-circle">
									<img src="<?= $config["dir"]["root"] ?>/content/client/images/team/philo.jpg" alt="Philo van Kemenade">
								</div>
							</div>
							<div>
								<div class="entityTitle">Philo van Kemenade</div>
								<div>Creative Technologist</div>
								<div><span class="icon-angle-right"></span>Advisor: Architecture & Collaborations</div>
							</div>
						</div>
					</div>
				</div>
				<!--
				<div class="entityPreview col" data-type="person">
					<div class="entityContainer">
						<div class="linkWrapper">
								<div class="thumbnailContainer">
								<div class="rounded-circle">
									<img src="" alt="...">
								</div>
							</div>
							<div>
								<div class="entityTitle">Liliana Melgar Estrada</div>
								<div>Information Scientist</div>
								<div><span class="icon-angle-right"></span>Advisor: Academic Collaborations</div>
							</div>
						</div>
					</div>
				</div>
				-->
			</div>
		</div>
	</div>
	<hr>
	<div class="row mb-4">
		<div class="col-12">
			<div>The basis for Open Parliament TV has been developed in a 6-month project by Boris Hekele and Joscha Jäger together with <a href="https://abgeordnetenwatch.de" target="_blank">abgeordnetenwatch.de</a>, funded via <a href="https://demokratie.io" target="_blank">demokratie.io</a>:</div>
			<div class="partnerLogos" style="background: #fff; padding: 30px 10px 0px 10px; margin-bottom: 10px;">
				<img src="<?= $config["dir"]["root"] ?>/content/client/images/logos/demokratie-io.png" style="height: 49px; margin-top: -9px;">
				<img src="<?= $config["dir"]["root"] ?>/content/client/images/logos/bmfsfj-dl.png" style="height: 90px; margin-top: -20px; margin-right: 10px;">
				<img src="<?= $config["dir"]["root"] ?>/content/client/images/logos/robert-bosch-stiftung.png" style="height: 40px; margin-top: -4px;">
				<div class="clearfix"></div>
			</div>
		</div>
	</div>
</main>
    <?php

}

include_once(__DIR__ . '/../../footer.php');

?>