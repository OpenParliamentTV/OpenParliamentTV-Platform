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
			<p><img src="<?= $config["dir"]["root"] ?>/content/client/images/optv-logo.png" style="float: right; width: 200px; margin-left: 20px;">Plenardebatten sind das öffentlich sichtbare Ergebnis kontroverser interner Diskussionen, Anhörungen, Verhandlungen und Analysen. Videos der entsprechenden Reden sind der „spektakuläre“ Teil der Politik. Sie finden ihren Weg in Nachrichtensendungen, soziale Medien und Fernsehshows. Die veröffentlichten <b>Videoinhalte</b> basieren jedoch meist auf <b>kurzen, prägnanten Momenten ohne Kontextinformationen</b> (wie einem Verweis auf die gesamte Rede, relevante Dokumente, Zusatzmaterialien oder Fakten-Checks).</p>
			<p>Sobald Redeinhalte für Analysen, Fact Checking Formate oder ausführliche Reportagen verwendet werden, verlassen wir uns auf die textbasierten Transkripte und Plenarprotokolle. Im besten Fall ergänzt durch einen Link zum Video der entsprechenden Rede.</p>
			<hr>
			<h3>Was ist Open Parliament TV?</h3>
			<p>Wir entwickeln mit Open Parliament TV die Abläufe und Werkzeuge, welche für einen zeitgemäßen Umgang mit den Inhalten des Parlamentsfernsehens nötig sind. Im Kern <b>synchronisieren </b>wir die<b> Videoaufzeichnungen </b>mit den<b> Plenarprotokollen.</b> Hierüber können wir die Videos zunächst über eine Volltextsuche zugänglich machen.</p>
			<div class="my-3 px-0">
				<img class="img-fluid" src="<?= $config["dir"]["root"] ?>/content/client/images/thumbnail.png" style="border: 2px solid var(--primary-bg-color);" alt="Open Parliament TV Screenshot">
			</div>
			<p>Durch die Verknüpfung von Videoaufzeichnung und Protokolltext können wir die Reden zusätzlich</p>
			<ul>
				<li>um <b>interaktive Transkripte</b> erweitern <br>(Klick auf einen Satz &gt; Sprung zu Zeitpunkt im Video)</li>
				<li>mit <b>kontext-basierten Annotationen</b> verknüpfen <br>(Anzeige relevanter Dokumente zu bestimmten Zeitpunkten)</li>
				<li>mit <b>neuen Beteiligungsmöglichkeiten</b> besser zugänglich machen <br>(zitieren, einbinden und teilen ausgewählter Videosegmente im Redekontext)</li>
			</ul>
			<p>Journalist:innen erhalten mit Open Parliament TV ein Werkzeug, welches das <b>Auffinden</b>, <b>Teilen</b> und <b>Zitieren</b> von Videoausschnitten aus Parlamentsreden enorm erleichtert. So lassen sich basierend auf einzelnen Schlüsselwörtern oder Satzbausteinen in Sekundenbruchteilen die entsprechenden Ausschnitte finden, abspielen und dann als Zitat in andere Plattformen einbinden.</p>
			<p>Neben der Volltextsuche ermöglicht Open Parliament TV noch viele <b>weitere Einstiege in die Debatten</b>. So lassen sich Redebeiträge beispielsweise von Profilseiten der Fraktionen abrufen oder es ist möglich alle Reden anzusehen, in denen eine bestimmte Drucksache oder ein bestimmtes Gesetz erwähnt wird. Diese Funktionalitäten möchten wir zukünftig noch ausbauen und um semi-automatisierte Analysen der Plenarprotokolle ergänzen. </p>
			<hr>
			<h3>Projektziel</h3>
			<p>Ziel des Open Parliament TV Projektes ist es, fundamental und langfristig zu ändern, wie Menschen mit videobasierten Veröffentlichungen politischer Debatten umgehen. Wir möchten Debatten in den Parlamenten <b>transparenter</b>, <b>zugänglicher</b> und <b>besser verständlich</b> machen.</p>
			<p>Ausgehend von Bundestagsreden haben wir ein System <b>interoperabler</b> und gut dokumentierter <b>Einzelkomponenten</b> geschaffen, welches eine <b>Übertragbarkeit</b> auf Landesparlamente, Stadtratssitzungen, Sitzungen des EU Parlaments sowie auf weitere nationale Parlamente ermöglicht. Die Übertragbarkeit der Projektbausteine ist eine zentrale Komponente des Projekts und wurde von Beginn an berücksichtigt.</p>
			<!--
			<div class="my-3 px-0">
				<img class="img-fluid" src="<?= $config["dir"]["root"] ?>/content/client/images/data-ingest.png" style="border: 2px solid var(--primary-bg-color);" alt="Open Parliament TV Data Ingest">
			</div>
			-->
			<p>Langfristig soll Open Parliament TV dazu beitragen, politische Debatten <b>über verschiedene Ebenen und verschiedene Länder</b> hinweg interparlamentar zugänglich zu machen:</p>
			<a href="https://openparliament.tv/proposal" target="_blank" class="btn btn-primary btn-sm"><span class="icon-doc-text"></span>Open Parliament TV - Open Source Project Proposal (auf Englisch)</a>
		</div>
		<div class="col-12 col-lg-4">
			<hr class="d-block d-lg-none">
			<h3>Kontakt & Anfragen</h3>
			<p>Joscha Jäger, Projektleiter<br>
			Mail: joscha.jaeger [AT] openparliament.tv<br>
			Twitter: <a href="https://twitter.com/OpenParlTV" target="_blank">@OpenParlTV</a></p>
			<hr>
			<h3>Open Data</h3>
			<p>Alle Daten auf Open Parliament TV können über unsere <b>Open Data API</b> abgefragt werden: </p>
			<ul>
				<li><a href="api">API Dokumentation</a></li>
			</ul>
			<hr>
			<h3>Open Source</h3>
			<p>Open Parliament TV ist ein <b>nicht-kommerzielles Open Source Projekt</b>. Alle Projektbausteine stehen unter <b>freien Lizenzen</b> und sind auf Github zu finden: </p>
			<div class="alert alert-warning py-1 px-2">Die Repositories werden zum offiziellen Launch am 20. Oktober öffentlich geschaltet</div>
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
			<p>Fragen zum Projekt, zu unserer Datengrundlage und zu technischen Einzelheiten beantworten wir in unseren <a href="faq">Frequently Asked Questions</a>.</p>
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
								<div><span class="icon-angle-right"></span>Projektleiter</div>
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
								<div><span class="icon-angle-right"></span>Entwicklerin: Wikidata Integration & NEL</div>
							</div>
						</div>
					</div>
				</div>
				<div class="entityPreview col" data-type="person">
					<div class="entityContainer">
						<div class="linkWrapper">
								<div class="thumbnailContainer">
								<div class="rounded-circle">
									<img src="<?= $config["dir"]["root"] ?>/content/client/images/team/michael.jpg" alt="Michael Morgenstern">
								</div>
							</div>
							<div>
								<div class="entityTitle">Michael Morgenstern</div>
								<div>Creative Technologist</div>
								<div><span class="icon-angle-right"></span>Plattform-Entwicklung & -Architektur</div>
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
								<div><span class="icon-angle-right"></span>Entwickler: Datenmodelle & -Workflows</div>
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
								<div><span class="icon-angle-right"></span>Berater: Architektur & Kooperationen</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<hr>
	<div class="row mb-4">
		<div class="col-12">
			<div>Die Grundlage von Open Parliament TV wurde in einem 6-monatigen Projekt von Boris Hekele und Joscha Jäger zusammen mit <a href="https://abgeordnetenwatch.de" target="_blank">abgeordnetenwatch.de</a> im Rahmen der <a href="https://demokratie.io" target="_blank">demokratie.io</a> Förderung geschaffen:</div>
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