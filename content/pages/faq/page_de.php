<?php include_once(__DIR__ . '/../../header.php'); ?>
<main class="container subpage">
	<div class="row" style="position: relative; z-index: 1">
		<div class="col-12">
			<h2><?= L::faq; ?></h2>
			<div class="accordion my-3" id="accordion">
				<div class="card" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
					<div class="card-header" id="q1">
						<button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#a1" aria-expanded="false" aria-controls="a1" itemprop="name">Wer steht hinter Open Parliament TV?<span class="icon-down-open-big"></span></button>
					</div>
					<div id="a1" class="collapse" aria-labelledby="q1" data-parent="#accordion" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
						<div class="card-body" itemprop="text">Mehr Informationen zu den Menschen hinter dem Projekt finden sich unter <a href="about#team">Über das Projekt > Team</a>. Wir befinden uns im Moment in der Gründungsphase einer gemeinnützigen GmbH, welche in Zukunft Open Parliament TV als nicht-kommerzielles Projekt betreiben und weiterentwickeln soll. <br><br>Mehr unter der Frage: <i>Wie finanziert ihr euch?</i></div>
					</div>
				</div>
				<div class="card" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
					<div class="card-header" id="q2">
						<button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#a2" aria-expanded="false" aria-controls="a2" itemprop="name">Arbeitet ihr mit Parlamenten zusammen?<span class="icon-down-open-big"></span></button>
					</div>
					<div id="a2" class="collapse" aria-labelledby="q2" data-parent="#accordion" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
						<div class="card-body" itemprop="text">Open Parliament TV ist ein unabhängiges Projekt und wird nicht von einem Parlament betrieben. Wir sind jedoch im Austausch mit mehreren Parlamentsverwaltungen und planen in Zukunft auch direkte Kooperationen im Bereich der Publikation von Videoaufzeichnungen. Insbesondere kleinere Parlamente haben oft nicht die Infrastruktur für Mediatheken wie sie der Bundestag betreibt. Hier kann Open Parliament TV eine Chance sein, die Abläufe in den Parlamenten sichtbarer und transparenter zu machen. Die Idee ist, dass Parlamente lediglich Datenschnittstellen zur Verfügung stellen müssen, die Publikation / der Betrieb einer entsprechenden Plattform aber über Open Parliament TV laufen kann. <br><br>Wir sind kein Ersatz für bestehende Mediatheken. Was wir bieten ist eine Meta-Plattform, welche Inhalte über die Grenzen bestehender Angebote hinweg durchsuchbar macht und verknüpft. </div>
					</div>
				</div>
				<div class="card" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
					<div class="card-header" id="q3">
						<button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#a3" aria-expanded="false" aria-controls="a3" itemprop="name">Woher bekommt ihr die Videos?<span class="icon-down-open-big"></span></button>
					</div>
					<div id="a3" class="collapse" aria-labelledby="q3" data-parent="#accordion" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
						<div class="card-body" itemprop="text">Im Falle des Deutschen Bundestages beziehen wir die Videos der Redebeiträge über die RSS Schnittstellen, welche die Bundestags-Mediathek anbietet. Je nach Parlament werden wir diese Informationen immer über offen zugängliche Schnittstellen beziehen oder wo das nicht möglich ist auf politischer Ebene darauf hinarbeiten, dass die Videoaufzeichnungen über offene Schnittstellen verfügbar gemacht werden (müssen). Dies ermöglicht es dann neben uns auch allen anderen Menschen (zivilgesellschaftlichen Initiativen, Journalist:innen, ..) diese Inhalte zu verwenden. <br><br>Für die Videos aus der Bundestags-Mediathek gelten die <a target="_blank" href="https://www.bundestag.de/nutzungsbedingungen">Nutzungsbedingungen des Deutschen Bundestages</a> auf welche wir in jedem Video verweisen (Info-Kästchen rechts oben). </div>
					</div>
				</div>
				<div class="card" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
					<div class="card-header" id="q4">
						<button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#a4" aria-expanded="false" aria-controls="a4" itemprop="name">Woher bekommt ihr die Protokolle und Daten?<span class="icon-down-open-big"></span></button>
					</div>
					<div id="a4" class="collapse" aria-labelledby="q4" data-parent="#accordion" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
						<div class="card-body" itemprop="text">Seit Beginn der 19. Wahlperiode (Oktober 2017) sind die Plenarprotokolle des Bundestages in einem <a href="https://www.bundestag.de/services/opendata" target="_blank">maschinenlesbaren „Open Data“ Format</a> verfügbar. Wir arbeiten für andere Parlamente daran, diese Informationen auch automatisiert aus Plenarprotokollen in PDF-Form auszulesen. In einer perfekten Welt würden jedoch alle Parlamente ihre Dokumente in maschinenlesbaren, international standardisierten Formaten veröffentlichen (diese Standards existieren, sie werden nur nicht genutzt). <br><br>Die Stenografischen Berichte / Plenarprotokolle des Deutschen Bundestages sind als amtliche Dokumente gemeinfrei. <br><br>Die Daten zu den Personen, Fraktionen und Parteien beziehen wir von <a href="https://www.wikidata.org/" target="_blank">Wikidata</a> (einer freien Wissensdatenbank, die jede und jeder bearbeiten kann). Die Daten zu Drucksachen des Deutschen Bundestages fragen wir über die "<a href="https://dip.bundestag.de/%C3%BCber-dip/hilfe/api" target="_blank">DIP API</a>" ab. </div>
					</div>
				</div>
				<div class="card" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
					<div class="card-header" id="q5">
						<button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#a5" aria-expanded="false" aria-controls="a5" itemprop="name">Wie synchronisiert ihr die Videos mit den Protokollen?<span class="icon-down-open-big"></span></button>
					</div>
					<div id="a5" class="collapse" aria-labelledby="q5" data-parent="#accordion" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
						<div class="card-body" itemprop="text">Die Synchronisation selbst (das „Alignment“) basiert im Kern auf der quelloffenen „Forced Alignment“ Software <a href="https://www.readbeyond.it/aeneas/" target="_blank">„Aeneas“</a> von Alberto Pettarin, welche wir für Open Parliament TV angepasst haben. Diese Komponente verwenden wir, um ein zeitbasiertes Transkript zu erstellen (einzelne Textbausteine sind Start- und Endzeitpunkten im Video zugeordnet). <br><br>Das Verfahren welches wir verwenden kommt ursprünglich aus der Musikwissenschaft und basiert auf dem visuellen Vergleich von Audiowellen. D.h. wir wenden kein „Speech-zu-Text“ Verfahren auf Grundlage des Audiosignals an, sondern setzen umgekehrt „Text-to-Speech“ ein, um aus dem Plenarprotokoll eine Audioversion zu erstellen. Die Waveform dieser generierten Audioversion wird dann visuell mit der Waveform des Originaltons verglichen. Dies macht das Verfahren nahezu sprachenunabhängig, da die Pausen, in denen nicht gesprochen wird, für die Synchronisation wichtiger sind als das gesprochene Wort. Daher ist es für uns auch kein Problem, wenn <a href="https://de.openparliament.tv/media/DE-0190018024?t=54.44">Johann Saathoff plötzlich Plattdeutsch spricht</a>. <br><br> Dem Alignment vorgeschaltet sind viele kleine Einzelschritte, über die wir herausfinden, welches Video überhaupt zu welchem Textabschnitt im Protokoll passt. </div>
					</div>
				</div>
				<div class="card" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
					<div class="card-header" id="q6">
						<button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#a6" aria-expanded="false" aria-controls="a6" itemprop="name">Warum sind manche Texte nicht synchronisiert?<span class="icon-down-open-big"></span></button>
					</div>
					<div id="a6" class="collapse" aria-labelledby="q6" data-parent="#accordion" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
						<div class="card-body" itemprop="text">Wir lesen die Inhalte aus den Protokollen automatisiert aus. In seltenen Fällen enthalten die Protokolle menschlich verursachte Datenfehler, welche die Synchronisation durcheinander bringen. In diesem Fall wird dann trotzdem der Text angezeigt, jedoch ohne interaktives Transkript. </div>
					</div>
				</div>
				<div class="card" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
					<div class="card-header" id="q7">
						<button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#a7" aria-expanded="false" aria-controls="a7" itemprop="name">Warum gibt es für manche Reden keine Texte?<span class="icon-down-open-big"></span></button>
					</div>
					<div id="a7" class="collapse" aria-labelledby="q7" data-parent="#accordion" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
						<div class="card-body" itemprop="text">Es kann in Einzelfällen dazu kommen, dass wir nicht automatisiert herausfinden, welcher Textabschnitt im Protokoll dem Videoausschnitt entspricht. Um zu verhindern, dass falsche Texte zu den Videos eingeblendet werden, halten wir diese Texte erstmal zurück und zeigen in diesem Fall nur das Video der Rede an. </div>
					</div>
				</div>
				<div class="card" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
					<div class="card-header" id="q8">
						<button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#a8" aria-expanded="false" aria-controls="a8" itemprop="name">Warum stimmt der Text nicht exakt mit dem gesprochenen Wort überein?<span class="icon-down-open-big"></span></button>
					</div>
					<div id="a8" class="collapse" aria-labelledby="q8" data-parent="#accordion" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
						<div class="card-body" itemprop="text">Wir verwenden die Originaltexte aus den Stenografischen Berichten (den Plenarprotokollen). Diese werden von den Stenograf:innen auf Basis des gesprochenen Wortes im Sitzungsverlauf angefertigt. Die Protokolle sind jedoch keine wortwörtliche Wiedergabe, sondern zur besseren Lesbarkeit um Wortwiederholungen etc. bereinigt. Zudem haben die Abgeordneten nach der Sitzung die Möglichkeit, das Protokoll ihrer Rede nochmals zu korrigieren. All das sorgt dafür, dass die Texte im Protokoll nicht exakt mit dem gesprochenen Wort übereinstimmen. In seltenen Fällen ist das auch der Grund für eine weniger exakte zeitliche Synchonisation. </div>
					</div>
				</div>
				<div class="card" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
					<div class="card-header" id="q9">
						<button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#a9" aria-expanded="false" aria-controls="a9" itemprop="name">Warum machen das nicht die Mediatheken der Parlamente?<span class="icon-down-open-big"></span></button>
					</div>
					<div id="a9" class="collapse" aria-labelledby="q9" data-parent="#accordion" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
						<div class="card-body" itemprop="text">Viele Parlamente haben gar keine eigenen Mediatheken. Wir sind darüber hinaus aber auch der Auffassung, dass eine Aufbereitung der Daten wie wir sie vornehmen nicht Aufgabe der Parlamente ist. Viel wichtiger ist, dass Parlamente die Rohdaten in standardisierter Form über frei zugängliche Schnittstellen der Öffentlichkeit zur Verfügung stellen. </div>
					</div>
				</div>
				<div class="card" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
					<div class="card-header" id="q10">
						<button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#a10" aria-expanded="false" aria-controls="a10" itemprop="name">Wie finanziert ihr euch?<span class="icon-down-open-big"></span></button>
					</div>
					<div id="a10" class="collapse" aria-labelledby="q10" data-parent="#accordion" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
						<div class="card-body" itemprop="text">Open Parliament TV wurde von Juni 2020 bis September 2021 vom Medieninnovationszentrum Babelsberg (MIZ) gefördert. Zukünftig planen wir die Weiterentwicklung über Modellprojekte mit Parlamentsverwaltungen, Forschungsvorhaben und öffentliche Förderung von Demokratie-Projekten zu finanzieren. Der laufende Betrieb soll von institutionellen Nutzer:innen (Parlamente, Fraktionen, Medienpartner:innen, NGOs) und Stiftungen gegenfinanziert werden. <br><br>Alle Inhalte auf Open Parliament TV werden auch zukünftig öffentlich zugänglich bleiben (ebenso wie der Quellcode aller Softwarekomponenten unter freier Lizensierung steht). Wir nehmen eine gemeinnützige Aufgabe wahr, diese muss dauerhaft durch öffentliche Gelder unterstützt werden. </div>
					</div>
				</div>
				<div class="card" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
					<div class="card-header" id="q11">
						<button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#a11" aria-expanded="false" aria-controls="a11" itemprop="name">Ist Open Parliament TV barrierefrei?<span class="icon-down-open-big"></span></button>
					</div>
					<div id="a11" class="collapse" aria-labelledby="q11" data-parent="#accordion" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
						<div class="card-body" itemprop="text">Nein, leider sind unsere Inhalte nur bedingt barrierefrei. Daran arbeiten wir im Moment. Es wird aber noch dauern, bis wir alle Bereiche der Plattform entsprechend überprüft und angepasst haben. <br><br>Uns war wichtig, die Platform früh öffentlich zugänglich zu machen. Dies bedeutet aber leider auch, dass wir diesen sehr wichtigen Baustein nachliefern müssen. </div>
					</div>
				</div>
				<div class="card" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
					<div class="card-header" id="q12">
						<button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#a12" aria-expanded="false" aria-controls="a12" itemprop="name">Wann ist mein Parlament bei euch zu finden?<span class="icon-down-open-big"></span></button>
					</div>
					<div id="a12" class="collapse" aria-labelledby="q12" data-parent="#accordion" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
						<div class="card-body" itemprop="text">Wir sind bereits im engen Austausch mit Parlamentsverwaltungen, „Parliamentary Monitoring Organisations“ und NGOs in mehreren Ländern. Unser Ziel ist es, mit der Unterstützung lokaler Partner:innen schrittweise immer mehr Parlamente in unsere Plattform zu integrieren (von Stadtratssitzungen bis zu Sitzungen des EU Parlaments und zusätzlicher nationaler Parlamente). Sobald wir hierzu Neuigkeiten haben, werden wir das per Twitter (@OpenParlTV) kommunizieren. <br><br>Darüber hinaus ist Open Parliament TV ein dezentral gedachtes Konzept: neben der Integration in unsere Plattform können Menschen unsere frei lizensierten technischen Komponenten auch nutzen, um eigene Open Parliament TV Plattformen zu betreiben. Über Open Data Schnittstellen wären die unterschiedlichen Plattformen dann trotzdem verknüpft und plattformübergreifend durchsuchbar. </div>
					</div>
				</div>
				<div class="card" itemscope itemprop="mainEntity" itemtype="https://schema.org/Question">
					<div class="card-header" id="q13">
						<button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#a13" aria-expanded="false" aria-controls="a13" itemprop="name">Wenn euer Projekt Open Source ist, habt ihr keine Angst dass es jemand klaut?<span class="icon-down-open-big"></span></button>
					</div>
					<div id="a13" class="collapse" aria-labelledby="q13" data-parent="#accordion" itemscope itemprop="acceptedAnswer" itemtype="https://schema.org/Answer">
						<div class="card-body" itemprop="text">Die Erfahrung mit größeren Open Source Projekten zeigt, dass die Menschen, die das ursprünglich mal entwickelt haben, bei einer Weiterverwendung und Anpassung (bspw. durch ein neues Parlament) ganz selten außen vor sind. Bevor sich jemand neu in den Code einarbeitet, macht es einfach viel mehr Sinn, die ursprünglichen Entwickler zu beauftragen. Neben dem Code bringen wir ja auch viel Erfahrung im Aufbau und Betrieb eines solchen Projekts mit. <br><br>Wenn sich dennoch jemand entscheidet, unser Projekt „zu klauen“, dann ist das genauso vorgesehen und wir freuen uns, dass unsere Arbeit offensichtlich sinnvoll war (siehe auch unser - ebenfalls frei lizensiertes - <a href="https://openparliament.tv/proposal" target="_blank">Project Proposal [EN]</a>). Es gibt viele Parlamente auf der Welt, die können wir gar nicht alle selbst abdecken. <br><br>Darüber hinaus ermöglicht die freie Lizensierung aller Bausteine des Projekts eine Verwendung weit über einzelne Parlamente oder Regionen hinaus. Theoretisch kann ein Parlament in Indien, welches Open Parliament TV einsetzen möchte, dies sofort tun. Wir haben eine Lizenz gewählt, die vorsieht, dass Weiterentwicklungen auch wieder frei lizensiert sein müssen, d.h. jede Weiterentwicklung kann auch wieder von allen Anderen genutzt werden. </div>
					</div>
				</div>
			</div>
			<div class="alert alert-info mt-4">Frage nicht beantwortet? Kontaktiere uns gerne jederzeit per Mail unter joscha.jaeger [at] openparliament.tv oder per Twitter: @OpenParlTV.Frage nicht beantwortet? Kontaktiere uns gerne jederzeit per Mail unter joscha.jaeger [at] openparliament.tv oder per Twitter: @OpenParlTV.</div>
		</div>
	</div>
</main>
<?php include_once(__DIR__ . '/../../footer.php'); ?>