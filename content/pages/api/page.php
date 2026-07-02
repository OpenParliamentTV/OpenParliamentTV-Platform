<?php defined('OPTV') or die(); ?>
<?php $this->layout('layout/default') ?>
<?php
// Collect the GitHub data repositories used by this instance (one per configured
// parliament), deduped and stripped of the trailing ".git". Fall back to the
// Open Parliament TV organisation when none are configured.
$dataRepos = [];
foreach (($config["parliament"] ?? []) as $pCfg) {
    $repo = trim($pCfg["git"]["repository"] ?? "");
    if ($repo !== "") {
        $repo = preg_replace('/\.git$/', '', $repo);
        $dataRepos[$repo] = $repo;
    }
}
if (empty($dataRepos)) {
    $dataRepos = ["https://github.com/OpenParliamentTV/"];
}
$openapiURL = $config["dir"]["root"] . "/api/openapi.yaml";
?>

<main class="container subpage">
	<div class="row" style="position: relative; z-index: 1">
		<div class="col-12">
			<h2>API <?= L::documentation(); ?></h2>
			<div class="alert bg-white">
				<div class="mb-2">All data on Open Parliament TV can be requested via our <b>Open Data API</b>. Recently we are overrun by automated traffic from AI tools and bots, sometimes millions of requests per day of people wanting to scrape our entire dataset. To keep the service usable for all users, anonymous requests are now <b>rate-limited</b>. If you need unlimited requests, please get in touch so we can issue you an <b>API key</b>.</div>
				<div class="mb-0">Scraping our entire dataset via the API is <b>completely unnecessary</b>, as <b>all raw speech data is published as JSON files</b> in GitHub repositories: 
					<?php $i = 0; foreach ($dataRepos as $repoURL): $i++; ?><a href="<?= hAttr($repoURL) ?>" target="_blank"><?= h(preg_replace('#^https?://github\.com/#', '', $repoURL)) ?></a><?= ($i < count($dataRepos) ? ", " : "") ?><?php endforeach; ?>.
				</div>
			</div>
			<ul class="nav nav-tabs" role="tablist">
				<li class="nav-item">
					<a class="nav-link active" id="search-tab" data-bs-toggle="tab" data-bs-target="#search" role="tab" aria-controls="search" aria-selected="true"><span class="nav-item-label"><?= L::search(); ?></span></a>
				</li>
				<li class="nav-item">
					<a class="nav-link" id="entities-tab" data-bs-toggle="tab" data-bs-target="#entities" role="tab" aria-controls="entities" aria-selected="true"><span class="nav-item-label"><?= L::entities(); ?></span></a></li>
					<li class="nav-item"><a class="nav-link" id="formats-tab" data-bs-toggle="tab" data-bs-target="#formats" role="tab" aria-controls="formats" aria-selected="true"><span class="nav-item-label">IIIF / WebVTT</span></a>
				</li>
				<li class="nav-item d-none">
					<a class="nav-link" id="statistics-tab" data-bs-toggle="tab" data-bs-target="#statistics" role="tab" aria-controls="statistics" aria-selected="true"><span class="nav-item-label">Statistics</span></a>
				</li>
				<li class="nav-item">
					<a class="nav-link" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" role="tab" aria-controls="general" aria-selected="true"><span class="nav-item-label">Specification v1.0</span></a>
				</li>
			</ul>
			<div class="tab-content">
				<div class="tab-pane fade bg-white show active" id="search" role="tabpanel" aria-labelledby="search-tab">
					<ul class="nav nav-tabs" role="tablist">
						<li class="nav-item">
							<a class="nav-link active" id="media-tab" data-bs-toggle="tab" data-bs-target="#media" role="tab" aria-controls="media" aria-selected="true"><span class="nav-item-label"><span class="icon-hypervideo me-1"></span> <?= L::speeches(); ?></span></a>
						</li>
						<li class="nav-item">
							<a class="nav-link" id="people-tab" data-bs-toggle="tab" data-bs-target="#people" role="tab" aria-controls="people" aria-selected="true"><span class="nav-item-label"><span class="icon-type-person"></span> <?= L::personPlural(); ?></span></a>
						</li>
						<li class="nav-item">
							<a class="nav-link" id="organisations-tab" data-bs-toggle="tab" data-bs-target="#organisations" role="tab" aria-controls="organisations" aria-selected="true"><span class="nav-item-label"><span class="icon-type-organisation"></span> <?= L::organisations(); ?></span></a>
						</li>
						<li class="nav-item">
							<a class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" role="tab" aria-controls="documents" aria-selected="true"><span class="nav-item-label"><span class="icon-type-document"></span> <?= L::documents(); ?></span></a>
						</li>
						<li class="nav-item">
							<a class="nav-link" id="terms-tab" data-bs-toggle="tab" data-bs-target="#terms" role="tab" aria-controls="terms" aria-selected="true"><span class="nav-item-label"><span class="icon-type-term"></span> <?= L::terms(); ?></span></a>
						</li>
					</ul>
					<div class="tab-content">
						<div class="tab-pane fade bg-white show active" id="media" role="tabpanel" aria-labelledby="media-tab">
							<h3>Endpoint</h3>
							<code>/api/v1/search/media?</code>
							<hr>
							<h3><?= L::example(); ?> Request</h3>
							<div>(<?= L::speeches(); ?> containing the query "Rente" by Faction SPD from 11.04.2018 until today)</div>
							<div class="apiExampleContainer">
								<div class="input-group">
									<span class="input-group-text">URI</span>
									<input type="text" class="apiURI form-control" value="<?= $config["dir"]["root"]; ?>/api/v1/search/media?q=Rente&factionID[]=Q2207512&dateFrom=2018-04-11" readonly>
									<button class="apiRequestButton btn btn-sm input-group-text"><span class="icon-right-open-big"></span><span class="d-none d-md-inline"><?= L::showResult(); ?></span></button>
								</div>
								<div class="apiResultContainer"></div>
							</div>
							<hr>
							<h3>Parameters</h3>
							<div class="table-responsive-lg">
								<table class="table table-sm table-striped">
									<thead>
										<tr>
											<th>Parameter</th>
											<th>Validation</th>
											<th>Matches</th>
											<th>Type</th>
										</tr>
									</thead>
									<tbody>
										<tr>
											<td>q</td>
											<td>min 3 chars</td>
											<td>Full Text Search</td>
											<td>String</td>
										</tr>
										<tr>
											<td>parliament</td>
											<td>min 2 chars</td>
											<td></td>
											<td>String</td>
										</tr>
										<tr>
											<td>electoralPeriod</td>
											<td>min 1 char</td>
											<td>electoralPeriod.data.attributes.number</td>
											<td>String</td>
										</tr>
										<tr>
											<td>electoralPeriodID</td>
											<td>min 1 char</td>
											<td>electoralPeriod.data.id</td>
											<td>String</td>
										</tr>
										<tr>
											<td>sessionID</td>
											<td>min 1 char</td>
											<td>session.data.id</td>
											<td>String</td>
										</tr>
										<tr>
											<td>sessionNumber</td>
											<td>min 1 char</td>
											<td>session.data.attributes.number</td>
											<td>String</td>
										</tr>
										<tr>
											<td>dateFrom</td>
											<td>date in ISO format (ex. "2017-10-28")</td>
											<td>dateStart</td>
											<td>String</td>
										</tr>
										<tr>
											<td>dateTo</td>
											<td>date in ISO format (ex. "2017-12-22")</td>
											<td>dateStart</td>
											<td>String</td>
										</tr>
										<tr>
											<td>party</td>
											<td>min 1 char</td>
											<td>people.data.attributes.party.labelAlternative</td>
											<td>String OR Array</td>
										</tr>
										<tr>
											<td>partyID</td>
											<td>min 1 char</td>
											<td>organisations.data.id</td>
											<td>String OR Array</td>
										</tr>
										<tr>
											<td>faction</td>
											<td>min 1 char</td>
											<td>people.data.attributes.faction.labelAlternative</td>
											<td>String OR Array</td>
										</tr>
										<tr>
											<td>factionID</td>
											<td>min 1 char</td>
											<td>organisations.data.id</td>
											<td>String OR Array</td>
										</tr>
										<tr>
											<td>person</td>
											<td>min 3 chars</td>
											<td>people.data.attributes.label</td>
											<td>String</td>
										</tr>
										<tr>
											<td>personID</td>
											<td>Wikidata ID RegEx</td>
											<td>people.data.id</td>
											<td>String OR Array</td>
										</tr>
										<tr>
											<td>abgeordnetenwatchID</td>
											<td>min 1 char</td>
											<td>people.data.attributes.additionalInformation.abgeordnetenwatchID</td>
											<td>String</td>
										</tr>
										<tr>
											<td>organisationID</td>
											<td>Wikidata ID RegEx</td>
											<td>people.data.attributes.party.id, people.data.attributes.faction.id</td>
											<td>String</td>
										</tr>
										<tr>
											<td>context</td>
											<td>min 3 chars</td>
											<td>people.data.attributes.context, organisations.data.attributes.context</td>
											<td>String</td>
										</tr>
										<tr>
											<td>agendaItemID</td>
											<td>min 2 chars</td>
											<td>agendaItem.data.id</td>
											<td>String</td>
										</tr>
										<tr>
											<td>documentID</td>
											<td>min 1 char</td>
											<td>documents.data.id</td>
											<td>String</td>
										</tr>
										<tr>
											<td>termID</td>
											<td>min 1 char</td>
											<td>terms.data.id</td>
											<td>String</td>
										</tr>
										<tr>
											<td>id</td>
											<td>min 4 chars</td>
											<td>id</td>
											<td>String</td>
										</tr>
										<tr>
											<td>agendaItemTitle</td>
											<td>min 3 chars</td>
											<td>agendaItem.data.attributes.title</td>
											<td>String</td>
										</tr>
										<tr>
											<td>aligned</td>
											<td>boolean</td>
											<td>Filter by alignment status</td>
											<td>Boolean</td>
										</tr>
										<tr>
											<td>fragDenStaatID</td>
											<td>min 1 char</td>
											<td>FragDenStaat ID</td>
											<td>String</td>
										</tr>
										<tr>
											<td>numberOfTexts</td>
											<td>integer</td>
											<td>Number of text segments</td>
											<td>Integer</td>
										</tr>
										<tr>
											<td>organisation</td>
											<td>min 3 chars</td>
											<td>Organisation name</td>
											<td>String</td>
										</tr>
										<tr>
											<td>personOriginID</td>
											<td>min 1 char</td>
											<td>Person origin identifier</td>
											<td>String</td>
										</tr>
										<tr>
											<td>procedureID</td>
											<td>min 1 char</td>
											<td>Procedure identifier</td>
											<td>String</td>
										</tr>
										<tr>
											<td>limit</td>
											<td>integer</td>
											<td>Maximum number of results to return</td>
											<td>Integer</td>
										</tr>
										<tr>
											<td>page</td>
											<td>integer</td>
											<td>Page number for pagination</td>
											<td>Integer</td>
										</tr>
										<tr>
											<td>sort</td>
											<td>date-asc, date-desc, topic-asc, topic-desc, duration-asc, duration-desc, changed-asc, changed-desc</td>
											<td>Sort results by date, duration, or last changed timestamp (defaults to relevance)</td>
											<td>String</td>
										</tr>
										<tr>
											<td>fields</td>
											<td>comma-separated values</td>
											<td>Return only specified fields (e.g., "id" for ID-only results)</td>
											<td>String</td>
										</tr>
									</tbody>
								</table>
							</div>
						</div>
						<div class="tab-pane fade bg-white" id="people" role="tabpanel" aria-labelledby="people-tab">
							<h3>Endpoint</h3>
							<code>/api/v1/search/people?</code>
							<hr>
							<h3><?= L::example(); ?> Request</h3>
							<div>(<?= L::personPlural(); ?> with name "Michael" in the Party "SPD")</div>
							<div class="apiExampleContainer">
								<div class="input-group">
									<span class="input-group-text">URI</span>
									<input type="text" class="apiURI form-control" value="<?= $config["dir"]["root"]; ?>/api/v1/search/people?name=Michael&party=SPD" readonly>
									<button class="apiRequestButton btn btn-sm input-group-text"><span class="icon-right-open-big"></span><span class="d-none d-md-inline"><?= L::showResult(); ?></span></button>
								</div>
								<div class="apiResultContainer"></div>
							</div>
							<hr>
							<h3>Parameters</h3>
							<div class="table-responsive-lg">
								<table class="table table-sm table-striped">
									<thead>
										<tr>
											<th>Parameter</th>
											<th>Validation</th>
											<th>Matches</th>
											<th>Type</th>
										</tr>
									</thead>
									<tbody>
										<tr>
											<td>name</td>
											<td>min 3 chars</td>
											<td>label, firstName, lastName</td>
											<td>String</td>
										</tr>
										<tr>
											<td>type</td>
											<td>"memberOfParliament", "unknown"</td>
											<td>type</td>
											<td>String</td>
										</tr>
										<tr>
											<td>party</td>
											<td>min 1 char</td>
											<td>organisation.label, organisation.labelAlternative</td>
											<td>String OR Array</td>
										</tr>
										<tr>
											<td>partyID</td>
											<td>Wikidata ID RegEx</td>
											<td>partyOrganisationID</td>
											<td>String OR Array</td>
										</tr>
										<tr>
											<td>faction</td>
											<td>min 1 char</td>
											<td>organisation.label, organisation.labelAlternative</td>
											<td>String OR Array</td>
										</tr>
										<tr>
											<td>factionID</td>
											<td>Wikidata ID RegEx</td>
											<td>factionOrganisationID</td>
											<td>String OR Array</td>
										</tr>
										<tr>
											<td>organisationID</td>
											<td>Wikidata ID RegEx</td>
											<td>factionOrganisationID, partyOrganisationID</td>
											<td>String</td>
										</tr>
										<tr>
											<td>degree</td>
											<td>min 1 char</td>
											<td>degree</td>
											<td>String</td>
										</tr>
										<tr>
											<td>gender</td>
											<td>"male", "female", "nonbinary", "bi", "queer"</td>
											<td>gender</td>
											<td>String</td>
										</tr>
										<tr>
											<td>originID</td>
											<td>min 1 char</td>
											<td>originID</td>
											<td>String</td>
										</tr>
										<tr>
											<td>abgeordnetenwatchID</td>
											<td>min 1 char</td>
											<td>additionalInformation.abgeordnetenwatchID</td>
											<td>String</td>
										</tr>
										<tr>
											<td>fragDenStaatID</td>
											<td>min 1 char</td>
											<td>FragDenStaat ID</td>
											<td>String</td>
										</tr>
									</tbody>
								</table>
							</div>
						</div>
						<div class="tab-pane fade bg-white" id="organisations" role="tabpanel" aria-labelledby="organisations-tab">
							<div class="alert alert-info">Please note that in the Open Parliament TV data model <b>parties</b> and <b>factions</b> as well as other types of bodies like <b>companies</b> or <b>NGOs</b> are all "organisations". They can be <b>filtered</b> via the "type" parameter (eg. type=faction or type=party).</div>
							<hr>
							<h3>Endpoint</h3>
							<code>/api/v1/search/organisations?</code>
							<hr>
							<h3><?= L::example(); ?> Request</h3>
							<div>(<?= L::organisations(); ?> with name "Linke")</div>
							<div class="apiExampleContainer">
								<div class="input-group">
									<span class="input-group-text">URI</span>
									<input type="text" class="apiURI form-control" value="<?= $config["dir"]["root"]; ?>/api/v1/search/organisations?name=Linke" readonly>
									<button class="apiRequestButton btn btn-sm input-group-text"><span class="icon-right-open-big"></span><span class="d-none d-md-inline"><?= L::showResult(); ?></span></button>
								</div>
								<div class="apiResultContainer"></div>
							</div>
							<hr>
							<h3>Parameters</h3>
							<table class="table table-sm table-striped">
								<thead>
									<tr>
										<th>Parameter</th>
										<th>Validation</th>
										<th>Matches</th>
										<th>Type</th>
									</tr>
								</thead>
								<tbody>
									<tr>
										<td>name</td>
										<td>min 3 chars</td>
										<td>label, labelAlternative, abstract</td>
										<td>String OR Array</td>
									</tr>
									<tr>
										<td>type</td>
										<td>min 2 chars</td>
										<td>type</td>
										<td>String</td>
									</tr>
								</tbody>
							</table>
							<hr>
						</div>
						<div class="tab-pane fade bg-white" id="documents" role="tabpanel" aria-labelledby="documents-tab">
							<div class="alert alert-info">Please note that documents can be official documents ("Drucksachen") as well as law texts and potentially other types of documents, sometimes applying to a specific parliament, sometimes generic, sometimes having a Wikidata ID, sometimes not. This is why the <b>document ID is not based on the official document number</b> or the Wikidata ID but an internal incremental ID. To <b>filter documents of a certain type</b>, use the "type" parameter (eg. type=officialDocument or type=legalDocument).</div>
							<hr>
							<h3>Endpoint</h3>
							<code>/api/v1/search/documents?</code>
							<hr>
							<h3><?= L::example(); ?> Request</h3>
							<div>(<?= L::documents(); ?> with label "19/5412", returns "Drucksache 19/5412" as well as documents where "19/5412" is mentioned in the titles or abstact)</div>
							<div class="apiExampleContainer">
								<div class="input-group">
									<span class="input-group-text">URI</span>
									<input type="text" class="apiURI form-control" value="<?= $config["dir"]["root"]; ?>/api/v1/search/documents?label=19/5412" readonly>
									<button class="apiRequestButton btn btn-sm input-group-text"><span class="icon-right-open-big"></span><span class="d-none d-md-inline"><?= L::showResult(); ?></span></button>
								</div>
								<div class="apiResultContainer"></div>
							</div>
							<hr>
							<h3>Parameters</h3>
							<table class="table table-sm table-striped">
								<thead>
									<tr>
										<th>Parameter</th>
										<th>Validation</th>
										<th>Matches</th>
										<th>Type</th>
									</tr>
								</thead>
								<tbody>
									<tr>
										<td>label</td>
										<td>min 3 chars</td>
										<td>label, labelAlternative, abstract</td>
										<td>String OR Array</td>
									</tr>
									<tr>
										<td>type</td>
										<td>min 2 chars</td>
										<td>type</td>
										<td>String</td>
									</tr>
									<tr>
										<td>wikidataID</td>
										<td>Wikidata ID RegEx</td>
										<td>wikidataID</td>
										<td>String</td>
									</tr>
								</tbody>
							</table>
							<hr>
						</div>
						<div class="tab-pane fade bg-white" id="terms" role="tabpanel" aria-labelledby="terms-tab">
							<h3>Endpoint</h3>
							<code>/api/v1/search/terms?</code>
							<hr>
							<h3><?= L::example(); ?> Request</h3>
							<div>(<?= L::terms(); ?> with label "digital")</div>
							<div class="apiExampleContainer">
								<div class="input-group">
									<span class="input-group-text">URI</span>
									<input type="text" class="apiURI form-control" value="<?= $config["dir"]["root"]; ?>/api/v1/search/terms?label=digital" readonly>
									<button class="apiRequestButton btn btn-sm input-group-text"><span class="icon-right-open-big"></span><span class="d-none d-md-inline"><?= L::showResult(); ?></span></button>
								</div>
								<div class="apiResultContainer"></div>
							</div>
							<hr>
							<h3>Parameters</h3>
							<table class="table table-sm table-striped">
								<thead>
									<tr>
										<th>Parameter</th>
										<th>Validation</th>
										<th>Matches</th>
										<th>Type</th>
									</tr>
								</thead>
								<tbody>
									<tr>
										<td>label</td>
										<td>min 3 chars</td>
										<td>label, labelAlternative</td>
										<td>String OR Array</td>
									</tr>
									<tr>
										<td>type</td>
										<td>min 2 chars</td>
										<td>type</td>
										<td>String</td>
									</tr>
									<tr>
										<td>wikidataID</td>
										<td>Wikidata ID RegEx</td>
										<td>wikidataID</td>
										<td>String</td>
									</tr>
								</tbody>
							</table>
							<hr>
						</div>
					</div>
				</div>
				<div class="tab-pane fade bg-white" id="entities" role="tabpanel" aria-labelledby="entities-tab">
					<div class="alert alert-info">Entity URIs are <b>based on the respective platform URL</b> and can be formed by simply adding <b>/api/v1</b> before the entity part. <br><br>
					  <b><?= L::example(); ?></b>:<br>
					  <a target="_blank" href="<?= $config["dir"]["root"] ?>/person/Q567"><?= $config["dir"]["root"] ?>/person/Q567</a><br>
					  <a target="_blank" href="<?= $config["dir"]["root"] ?>/api/v1/person/Q567"><?= $config["dir"]["root"] ?>/api/v1/person/Q567</a></div>
					<hr>
					<h3><span class="icon-hypervideo me-1"></span> GET <?= L::speech(); ?></h3>
					<p class="mb-2"><b>Media IDs</b> contain info about the parliament, electoral period and session. You should however not try to guess those IDs (eg. based on the order of speeches). This might work in some cases, it will not in many others.</p>
					<div><b>Endpoint</b>: <code>/api/v1/media/ID</code></div>
					<div class="mb-2"><b><?= L::example(); ?></b>: <?= L::speech(); ?></div>
					<div class="apiExampleContainer">
						<div class="input-group">
							<span class="input-group-text">URI</span>
							<input type="text" class="apiURI form-control" value="<?= $config["dir"]["root"]; ?>/api/v1/media/DE-0190061003" readonly>
							<button class="apiRequestButton btn btn-sm"><span class="icon-right-open-big"></span><span class="d-none d-md-inline"><?= L::showResult(); ?></span></button>
						</div>
						<div class="apiResultContainer"></div>
					</div>
					<hr>
					<h3><span class="icon-type-person"></span> GET <?= L::personSingular(); ?></h3>
					<p class="mb-2"><b>Person IDs</b> are <b>always a Wikidata ID</b>.</p>
					<div><b>Endpoint</b>: <code>/api/v1/person/ID</code></div>
					<div class="mb-2"><b><?= L::example(); ?></b>: Angela Merkel (Wikidata ID Q567)</div>
					<div class="apiExampleContainer">
						<div class="input-group">
							<span class="input-group-text">URI</span>
						<input type="text" class="apiURI form-control" value="<?= $config["dir"]["root"]; ?>/api/v1/person/Q567" readonly>
							<button class="apiRequestButton btn btn-sm"><span class="icon-right-open-big"></span><span class="d-none d-md-inline"><?= L::showResult(); ?></span></button>
						</div>
						<div class="apiResultContainer"></div>
					</div>
					<hr>
					<h3><span class="icon-type-organisation"></span> GET <?= L::organisation(); ?></h3>
					<p class="mb-2"><b><?= L::organisation(); ?> IDs</b> are <b>always a Wikidata ID</b>.</p>
					<div><b>Endpoint</b>: <code>/api/v1/organisation/ID</code></div>
					<div class="mb-2"><b><?= L::example(); ?></b>: <?= L::faction(); ?> BÜNDNIS 90/DIE GRÜNEN (Wikidata ID Q1007353)</div>
					<div class="apiExampleContainer">
						<div class="input-group">
							<span class="input-group-text">URI</span>
						<input type="text" class="apiURI form-control" value="<?= $config["dir"]["root"]; ?>/api/v1/organisation/Q1007353" readonly>
							<button class="apiRequestButton btn btn-sm"><span class="icon-right-open-big"></span><span class="d-none d-md-inline"><?= L::showResult(); ?></span></button>
						</div>
						<div class="apiResultContainer"></div>
					</div>
					<hr>
					<h3><span class="icon-type-document"></span> GET <?= L::document(); ?></h3>
					<p class="mb-2"><b><?= L::document(); ?> IDs</b> are internal incremental IDs and contain no reference to the document numbers (like "Drucksache 19/1234"). The rationale for this is that documents can be official documents as well as law texts and potentially other types of documents, sometimes applying to a specific parliament, sometimes generic, sometimes having a Wikidata ID, sometimes not. If you want to get a document by its official document number, you can use the document search. </p>
					<div><b>Endpoint</b>: <code>/api/v1/document/ID</code></div>
					<div class="mb-2"><b><?= L::example(); ?></b>: Drucksache 19/1184</div>
					<div class="apiExampleContainer">
						<div class="input-group">
							<span class="input-group-text">URI</span>
							<input type="text" class="apiURI form-control" value="<?= $config["dir"]["root"]; ?>/api/v1/document/14" readonly>
							<button class="apiRequestButton btn btn-sm"><span class="icon-right-open-big"></span><span class="d-none d-md-inline"><?= L::showResult(); ?></span></button>
						</div>
						<div class="apiResultContainer"></div>
					</div>
					<hr>
					<h3><span class="icon-type-term"></span> GET <?= L::term(); ?></h3>
					<p class="mb-2"><b><?= L::term(); ?> IDs</b> are <b>always a Wikidata ID</b>.</p>
					<div><b>Endpoint</b>: <code>/api/v1/term/ID</code></div>
					<div class="apiExampleContainer">
						<div class="input-group">
							<span class="input-group-text">URI</span>
							<input type="text" class="apiURI form-control" value="<?= $config["dir"]["root"]; ?>/api/v1/term/Q4394526" readonly>
							<button class="apiRequestButton btn btn-sm"><span class="icon-right-open-big"></span><span class="d-none d-md-inline"><?= L::showResult(); ?></span></button>
						</div>
						<div class="apiResultContainer"></div>
					</div>
					<hr>
					<h3><span class="icon-check"></span> GET <?= L::electoralPeriod(); ?></h3>
					<p class="mb-2"><b><?= L::electoralPeriod(); ?> IDs</b> can safely be referenced by the parliament shortcode plus the respective number. </p>
					<div><b>Endpoint</b>: <code>/api/v1/electoralPeriod/ID</code></div>
					<div class="mb-2"><b><?= L::example(); ?></b>: <?= L::electoralPeriod(); ?> 19 of the German Bundestag (ID: "DE-019")</div>
					<div class="apiExampleContainer">
						<div class="input-group">
							<span class="input-group-text">URI</span>
						<input type="text" class="apiURI form-control" value="<?= $config["dir"]["root"]; ?>/api/v1/electoralPeriod/DE-019" readonly>
							<button class="apiRequestButton btn btn-sm"><span class="icon-right-open-big"></span><span class="d-none d-md-inline"><?= L::showResult(); ?></span></button>
						</div>
						<div class="apiResultContainer"></div>
					</div>
					<hr>
					<h3><span class="icon-group"></span> GET <?= L::session(); ?></h3>
					<p class="mb-2"><b><?= L::session(); ?> IDs</b> can safely be referenced by the parliament shortcode plus the respective numbers. </p>
					<div><b>Endpoint</b>: <code>/api/v1/session/ID</code></div>
					<div class="mb-2"><b><?= L::example(); ?></b>: <?= L::session(); ?> 61 in <?= L::electoralPeriod(); ?> 19 of the German Bundestag (ID: "DE-0190061")</div>
					<div class="apiExampleContainer">
						<div class="input-group">
							<span class="input-group-text">URI</span>
						<input type="text" class="apiURI form-control" value="<?= $config["dir"]["root"]; ?>/api/v1/session/DE-0190061" readonly>
							<button class="apiRequestButton btn btn-sm"><span class="icon-right-open-big"></span><span class="d-none d-md-inline"><?= L::showResult(); ?></span></button>
						</div>
						<div class="apiResultContainer"></div>
					</div>
					<hr>
					<h3><span class="icon-list-numbered"></span> GET <?= L::agendaItem(); ?></h3>
					<p class="mb-2"><b><?= L::agendaItem(); ?> IDs</b> are built of the parliament shortcode and an incremental ID. You should not try to guess those IDs (eg. based on the order of agenda items). This might work in some cases, it will not in many others.</p>
					<div><b>Endpoint</b>: <code>/api/v1/agendaItem/ID</code></div>
					<div class="mb-2"><b><?= L::example(); ?></b>: <?= L::agendaItem(); ?> "Gesetzliche Rentenversicherung" in <?= L::session(); ?> 61 in <?= L::electoralPeriod(); ?> 19 of the German Bundestag (ID: "DE-454")</div>
					<div class="apiExampleContainer">
						<div class="input-group">
							<span class="input-group-text">URI</span>
						<input type="text" class="apiURI form-control" value="<?= $config["dir"]["root"]; ?>/api/v1/agendaItem/DE-454" readonly>
							<button class="apiRequestButton btn btn-sm"><span class="icon-right-open-big"></span><span class="d-none d-md-inline"><?= L::showResult(); ?></span></button>
						</div>
						<div class="apiResultContainer"></div>
					</div>
				</div>
				<div class="tab-pane fade bg-white" id="formats" role="tabpanel" aria-labelledby="formats-tab">
						<div class="alert alert-info">Many endpoints are available in open, standardised formats. <b>IIIF</b> (International Image Interoperability Framework, <a href="https://iiif.io/api/presentation/3.0/" target="_blank">Presentation API 3.0</a>) manifests and collections open in viewers like <a href="https://projectmirador.org/" target="_blank">Mirador</a> or <a href="https://universalviewer.io/" target="_blank">Universal Viewer</a>. <b>WebVTT</b> provides timed transcripts for HTML5 <code>&lt;track&gt;</code> elements and media players. IIIF resources are served as JSON-LD (<code>application/ld+json</code>); both are CORS-enabled.</div>
						<hr>
						<h3><span class="icon-doc-text"></span> WebVTT Transcript</h3>
						<p class="mb-2">Timed transcript of a speech as a WebVTT file (<code>text/vtt</code>). Optional query parameters: <code>type</code> (e.g. <code>proceedings</code>, <code>generated</code>) and <code>lang</code> (e.g. <code>de</code>). Without them the best available transcript is returned.</p>
						<div><b>Endpoint</b>: <code>/api/v1/media/ID/transcript.vtt</code></div>
						<div class="apiExampleContainer">
							<div class="input-group">
								<span class="input-group-text">URI</span>
								<input type="text" class="form-control" value="<?= $config["dir"]["root"]; ?>/api/v1/media/DE-0190061003/transcript.vtt" readonly>
								<a href="<?= $config["dir"]["root"]; ?>/api/v1/media/DE-0190061003/transcript.vtt" target="_blank" class="btn btn-sm input-group-text"><span class="icon-right-open-big"></span><span class="d-none d-md-inline"><?= L::showResult(); ?></span></a>
							</div>
						</div>
						<hr>
						<h3><span class="icon-hypervideo me-1"></span> IIIF Manifest - <?= L::speech(); ?></h3>
						<p class="mb-2">IIIF Presentation 3.0 Manifest for a single speech (video, transcript annotations, named-entity annotations with time codes). Request via the <code>format=iiif</code> query parameter (or an <code>Accept: application/ld+json</code> header) on the media endpoint.</p>
						<div><b>Endpoint</b>: <code>/api/v1/media/ID?format=iiif</code></div>
						<div class="apiExampleContainer">
							<div class="input-group">
								<span class="input-group-text">URI</span>
								<input type="text" class="form-control" value="<?= $config["dir"]["root"]; ?>/api/v1/media/DE-0190061003?format=iiif" readonly>
								<a href="<?= $config["dir"]["root"]; ?>/api/v1/media/DE-0190061003?format=iiif" target="_blank" class="btn btn-sm input-group-text"><span class="icon-right-open-big"></span><span class="d-none d-md-inline"><?= L::showResult(); ?></span></a>
							</div>
						</div>
						<hr>
						<h3><span class="icon-group"></span> IIIF Manifest - <?= L::session(); ?></h3>
						<p class="mb-2">IIIF Manifest for a whole session: one Canvas per public speech, grouped into Ranges by agenda item.</p>
						<div><b>Endpoint</b>: <code>/api/v1/session/ID?format=iiif</code></div>
						<div class="apiExampleContainer">
							<div class="input-group">
								<span class="input-group-text">URI</span>
								<input type="text" class="form-control" value="<?= $config["dir"]["root"]; ?>/api/v1/session/DE-0190061?format=iiif" readonly>
								<a href="<?= $config["dir"]["root"]; ?>/api/v1/session/DE-0190061?format=iiif" target="_blank" class="btn btn-sm input-group-text"><span class="icon-right-open-big"></span><span class="d-none d-md-inline"><?= L::showResult(); ?></span></a>
							</div>
						</div>
						<hr>
						<h3><span class="icon-database"></span> IIIF Collection - Parliament / <?= L::electoralPeriod(); ?></h3>
						<p class="mb-2">IIIF Collection for a whole parliament (its electoral periods as sub-collections), or for a single electoral period (its sessions as Manifest references).</p>
						<div><b>Endpoint</b>: <code>/api/v1/iiif/collection/PARLIAMENT</code> and <code>/api/v1/iiif/collection/PARLIAMENT/ELECTORALPERIOD</code></div>
						<div class="apiExampleContainer">
							<div class="input-group">
								<span class="input-group-text">URI</span>
								<input type="text" class="form-control" value="<?= $config["dir"]["root"]; ?>/api/v1/iiif/collection/DE" readonly>
								<a href="<?= $config["dir"]["root"]; ?>/api/v1/iiif/collection/DE" target="_blank" class="btn btn-sm input-group-text"><span class="icon-right-open-big"></span><span class="d-none d-md-inline"><?= L::showResult(); ?></span></a>
							</div>
						</div>
						<hr>
						<h3><span class="icon-database"></span> IIIF Collection - <?= L::search(); ?></h3>
						<p class="mb-2">A IIIF Collection built from a media search: its items are Manifest references to the matching speeches. Accepts the same query parameters as <code>/api/v1/search/media</code> (see the Search tab), including the per-entity <code>~context</code> suffix.</p>
						<div><b>Endpoint</b>: <code>/api/v1/iiif/search?PARAMETERS</code></div>
						<div class="apiExampleContainer">
							<div class="input-group">
								<span class="input-group-text">URI</span>
								<input type="text" class="form-control" value="<?= $config["dir"]["root"]; ?>/api/v1/iiif/search?personID=Q567" readonly>
								<a href="<?= $config["dir"]["root"]; ?>/api/v1/iiif/search?personID=Q567" target="_blank" class="btn btn-sm input-group-text"><span class="icon-right-open-big"></span><span class="d-none d-md-inline"><?= L::showResult(); ?></span></a>
							</div>
						</div>
					</div>
					<div class="tab-pane fade bg-white" id="statistics" role="tabpanel" aria-labelledby="statistics-tab">
					<div class="alert alert-info">Statistics endpoints provide aggregated insights into parliamentary data, including speaker activity, word frequency analysis, entity relationships, and political discourse patterns. </div>
					<ul class="nav nav-tabs" role="tablist">
						<li class="nav-item">
							<a class="nav-link active" id="general-stats-tab" data-bs-toggle="tab" data-bs-target="#general-stats" role="tab" aria-controls="general-stats" aria-selected="true"><span class="nav-item-label"><span class="icon-chart-bar me-1"></span> General Statistics</span></a>
						</li>
						<li class="nav-item">
							<a class="nav-link" id="entity-stats-tab" data-bs-toggle="tab" data-bs-target="#entity-stats" role="tab" aria-controls="entity-stats" aria-selected="true"><span class="nav-item-label"><span class="icon-type-person me-1"></span> Entity Statistics</span></a>
						</li>
						<li class="nav-item">
							<a class="nav-link" id="word-trends-tab" data-bs-toggle="tab" data-bs-target="#word-trends" role="tab" aria-controls="word-trends" aria-selected="true"><span class="nav-item-label"><span class="icon-chart-line me-1"></span> Word Trends</span></a>
						</li>
						<li class="nav-item">
							<a class="nav-link" id="entity-counts-tab" data-bs-toggle="tab" data-bs-target="#entity-counts" role="tab" aria-controls="entity-counts" aria-selected="true"><span class="nav-item-label"><span class="icon-database me-1"></span> Entity Counts</span></a>
						</li>
					</ul>
					<div class="tab-content">
						<div class="tab-pane fade bg-white show active" id="general-stats" role="tabpanel" aria-labelledby="general-stats-tab">
							<h3>Endpoint</h3>
							<code>/api/v1/statistics/general</code>
							<hr>
							<h3><?= L::example(); ?> Request</h3>
							<div>(General statistics showing speeches, speakers, speaking time, and vocabulary across all parliaments)</div>
							<div class="apiExampleContainer">
								<div class="input-group">
									<span class="input-group-text">URI</span>
									<input type="text" class="apiURI form-control" value="<?= $config["dir"]["root"]; ?>/api/v1/statistics/general" readonly>
									<button class="apiRequestButton btn btn-sm input-group-text"><span class="icon-right-open-big"></span><span class="d-none d-md-inline"><?= L::showResult(); ?></span></button>
								</div>
								<div class="apiResultContainer"></div>
							</div>
							<hr>
							<h3>Parameters</h3>
							<div class="table-responsive-lg">
								<table class="table table-sm table-striped">
									<thead>
										<tr>
											<th>Parameter</th>
											<th>Validation</th>
											<th>Description</th>
											<th>Type</th>
										</tr>
									</thead>
									<tbody>
										<tr>
											<td>parliament</td>
											<td>Optional, defaults to "de"</td>
											<td>Parliament code for multi-parliament support</td>
											<td>String</td>
										</tr>
									</tbody>
								</table>
							</div>
						</div>
						<div class="tab-pane fade bg-white" id="entity-stats" role="tabpanel" aria-labelledby="entity-stats-tab">
							<div class="alert alert-info">For person entities, this endpoint automatically includes speaker vocabulary statistics (total words, unique words, and top words with usage frequency).</div>
							<h3>Endpoint</h3>
							<code>/api/v1/statistics/entity</code>
							<hr>
							<h3><?= L::example(); ?> Request</h3>
							<div>(Statistics for person Angela Merkel, showing associations, trends, and vocabulary)</div>
							<div class="apiExampleContainer">
								<div class="input-group">
									<span class="input-group-text">URI</span>
									<input type="text" class="apiURI form-control" value="<?= $config["dir"]["root"]; ?>/api/v1/statistics/entity?entityType=person&entityID=Q567" readonly>
									<button class="apiRequestButton btn btn-sm input-group-text"><span class="icon-right-open-big"></span><span class="d-none d-md-inline"><?= L::showResult(); ?></span></button>
								</div>
								<div class="apiResultContainer"></div>
							</div>
							<hr>
							<h3>Parameters</h3>
							<div class="table-responsive-lg">
								<table class="table table-sm table-striped">
									<thead>
										<tr>
											<th>Parameter</th>
											<th>Validation</th>
											<th>Description</th>
											<th>Type</th>
										</tr>
									</thead>
									<tbody>
										<tr>
											<td>entityType</td>
											<td>Required: person, organisation, document, term</td>
											<td>Type of entity to analyze</td>
											<td>String</td>
										</tr>
										<tr>
											<td>entityID</td>
											<td>Required: Wikidata ID RegEx or internal ID</td>
											<td>ID of specific entity to analyze</td>
											<td>String</td>
										</tr>
									</tbody>
								</table>
							</div>
						</div>
						<div class="tab-pane fade bg-white" id="word-trends" role="tabpanel" aria-labelledby="word-trends-tab">
							<h3>Endpoint</h3>
							<code>/api/v1/statistics/word-trends</code>
							<hr>
							<h3><?= L::example(); ?> Request</h3>
							<div>(Word trends for "heute" and "wollen" from 2020 to 2024, optionally filtered by faction)</div>
							<div class="apiExampleContainer">
								<div class="input-group">
									<span class="input-group-text">URI</span>
									<input type="text" class="apiURI form-control" value="<?= $config["dir"]["root"]; ?>/api/v1/statistics/word-trends?words[]=heute&words[]=wollen&startDate=2020-01-01&endDate=2024-12-31&factions[]=Q2207512" readonly>
									<button class="apiRequestButton btn btn-sm input-group-text"><span class="icon-right-open-big"></span><span class="d-none d-md-inline"><?= L::showResult(); ?></span></button>
								</div>
								<div class="apiResultContainer"></div>
							</div>
							<hr>
							<h3>Parameters</h3>
							<div class="table-responsive-lg">
								<table class="table table-sm table-striped">
									<thead>
										<tr>
											<th>Parameter</th>
											<th>Validation</th>
											<th>Description</th>
											<th>Type</th>
										</tr>
									</thead>
									<tbody>
										<tr>
											<td>words[]</td>
											<td>Required: array of words</td>
											<td>Words to track over time</td>
											<td>Array</td>
										</tr>
										<tr>
											<td>startDate</td>
											<td>Optional, defaults to '2020-01-01'</td>
											<td>Start date in YYYY-MM-DD format</td>
											<td>String</td>
										</tr>
										<tr>
											<td>endDate</td>
											<td>Optional, defaults to current date</td>
											<td>End date in YYYY-MM-DD format</td>
											<td>String</td>
										</tr>
										<tr>
											<td>parliament</td>
											<td>Optional, defaults to 'de'</td>
											<td>Parliament code for multi-parliament support</td>
											<td>String</td>
										</tr>
										<tr>
											<td>factions[]</td>
											<td>Optional, array of Wikidata IDs</td>
											<td>Filter word trends by specific political factions</td>
											<td>Array</td>
										</tr>
										<tr>
											<td>separateByFaction</td>
											<td>Optional, boolean</td>
											<td>Separate results by faction when true</td>
											<td>Boolean</td>
										</tr>
									</tbody>
								</table>
							</div>
						</div>
						<div class="tab-pane fade bg-white" id="entity-counts" role="tabpanel" aria-labelledby="entity-counts-tab">
							<h3>Endpoint</h3>
							<code>/api/v1/statistics/entity-counts</code>
							<hr>
							<h3><?= L::example(); ?> Request</h3>
							<div>(Get counts of all entity types in the database)</div>
							<div class="apiExampleContainer">
								<div class="input-group">
									<span class="input-group-text">URI</span>
									<input type="text" class="apiURI form-control" value="<?= $config["dir"]["root"]; ?>/api/v1/statistics/entity-counts" readonly>
									<button class="apiRequestButton btn btn-sm input-group-text"><span class="icon-right-open-big"></span><span class="d-none d-md-inline"><?= L::showResult(); ?></span></button>
								</div>
								<div class="apiResultContainer"></div>
							</div>
							<hr>
							<h3>Parameters</h3>
							<div class="table-responsive-lg">
								<table class="table table-sm table-striped">
									<thead>
										<tr>
											<th>Parameter</th>
											<th>Validation</th>
											<th>Description</th>
											<th>Type</th>
										</tr>
									</thead>
									<tbody>
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
				<div class="tab-pane fade bg-white" id="general" role="tabpanel" aria-labelledby="general-tab">
					<div class="alert alert-info">The API structure is based on the <a href="https://jsonapi.org/format/">JSON:API Specification</a>. <br>A machine-readable <b>OpenAPI 3.0</b> description of the API is available at <a href="<?= hAttr($openapiURL) ?>" class="fw-bolder" target="_blank"><?= h($openapiURL) ?></a>. Browse it rendered in an external viewer: <a class="fw-bolder" href="https://redocly.github.io/redoc/?url=<?= hAttr(urlencode($openapiURL)) ?>" target="_blank">Redoc</a> or <a class="fw-bolder" href="https://editor.swagger.io/?url=<?= hAttr(urlencode($openapiURL)) ?>" target="_blank">Swagger Editor</a>.</div>
					<hr>
					<h3>Paths</h3>
					<p><strong>API <?= L::documentation(); ?></strong><br><code>/api</code></p>
					<p><strong>API Endpoint Base URL</strong><br><code>/api/v1</code></p>
					<hr>
					<h3>API Responses</h3>
					<p>Responses <strong>MUST</strong> include the following properties for any request (GET <strong>and</strong> POST):</p>
<pre><code class="lang-yaml">{
  "meta": {
	"api": {
	  "version": "1.0",
	  "documentation": "https://de.openparliament.tv/api",
	  "license": {
		"label": "ODC Open Database License (ODbL) v1.0",
		"link": "https://opendatacommons.org/licenses/odbl/1-0/"
	  }
	},
	"requestStatus": "success" // OR "error"
  },
  "data": [], // {} OR []
  "errors": [], // EITHER "data" OR "errors"
  "links": {
	"self": "https://de.openparliament.tv/api/v1/search/media?q=Rente" // request URL
  }
}</code></pre>
					<p><strong>Successful</strong> requests <strong>MUST</strong> include the following properties:</p>
<pre><code class="lang-yaml">{
  "meta": {
	"api": {
	  "version": "1.0",
	  "documentation": "https://de.openparliament.tv/api",
	  "license": {
		"label": "ODC Open Database License (ODbL) v1.0",
		"link": "https://opendatacommons.org/licenses/odbl/1-0/"
	  }
	},
	"requestStatus": "success"
  },
  "data": {},
  "links": {
	"self": "https://de.openparliament.tv/api/v1/search/media?q=Rente" // request URL
  }
}</code></pre>
					<p><strong>Errors</strong> <strong>MUST</strong> include the following properties:</p>
<pre><code class="lang-yaml">{
  "meta": {
	"api": {
	  "version": "1.0",
	  "documentation": "https://de.openparliament.tv/api",
	  "license": {
		"label": "ODC Open Database License (ODbL) v1.0",
		"link": "https://opendatacommons.org/licenses/odbl/1-0/"
	  }
	},
	"requestStatus": "error"
  },
  "errors": [
	{
	  "meta": {
		"domSelector": "" // optional
	  },
	  "status": "422", // HTTP Status   
	  "code": "3", 
	  "title":  "Invalid Attribute",
	  "detail": "First name must contain at least three characters."
	}
  ],
  "links": {
	"self": "https://de.openparliament.tv/api/v1/search/media?q=Rente" // request URL
  }
}</code></pre>
					<hr>
					<h3>Data Objects</h3>
					<p>Data Objects <strong>MUST</strong> always include the properties:  </p>
					<ul>
						<li><strong>id</strong></li>
						<li><strong>type</strong></li>
						<li><strong>attributes</strong> (data item specific properties)</li>
					</ul>
					<p>Additionally Data Objects <strong>CAN</strong> include the properties:</p>
					<ul>
						<li><strong>links</strong> (&quot;self&quot; = link to the respective API request URL)</li>
						<li><strong>relationships</strong> (properties derived from other data items)</li>
					</ul>
					<p>Depending on the context, the <strong>attributes</strong> object can only include a subset of all properties. The full set can then be retrieved via an API request to <strong>links</strong> &gt; <strong>self</strong>.</p>
					<p>This principle <strong>SHOULD</strong> be applied on all levels of the data structure.</p>
					<hr>
					<h3>Examples</h3>
					<p><strong>Example for an Entity Response:</strong></p>
<pre><code class="lang-yaml">{
  "meta": {
	"api": {
	  "version": "1.0",
	  "documentation": "https://de.openparliament.tv/api",
	  "license": {
		"label": "ODC Open Database License (ODbL) v1.0",
		"link": "https://opendatacommons.org/licenses/odbl/1-0/"
	  }
	},
	"requestStatus": "success"
  },
  "data": {
	"type": "media",
	"id": "DE-198765837",
	"attributes": {},
	"relationships": {
	  "documents": {
		"data": [
		  {
			"type": "document",
			"id": "201",
			"attributes": {},
			"links": {
			  "self": "https://de.openparliament.tv/api/v1/document/201"
			}
		  }
		],
		"links": {
		  "self": "https://de.openparliament.tv/api/v1/searchAnnotations?mediaID=DE-198765837&type=document"
		}
	  }
	},
	"links": {
	  "self": "https://de.openparliament.tv/api/v1/media/DE-198765837"
	}
  }
}</code></pre>
					<hr>
					<p><strong>Example for a Search Response:</strong></p>
<pre><code class="lang-yaml">{
  "meta": {
	"api": {
	  "version": "1.0",
	  "documentation": "https://de.openparliament.tv/api",
	  "license": {
		"label": "ODC Open Database License (ODbL) v1.0",
		"link": "https://opendatacommons.org/licenses/odbl/1-0/"
	  }
	},
	"requestStatus": "success",
	"results": {
	  "count": 25,
	  "total": 128,
	  "rangeStart": 51,
	  "rangeEnd": 75,
	  "maxScore": 4.7654785 /* float or null */
	}
  },
  "data": [],
  "links": {
	"self": "https://de.openparliament.tv/api/v1/search/people?party=CDU&page[number]=3&page[size]=25",
	"first": "https://de.openparliament.tv/api/v1/search/people?party=CDU&page[number]=1&page[size]=25",
	"prev": "https://de.openparliament.tv/api/v1/search/people?party=CDU&page[number]=2&page[size]=25",
	"next": "https://de.openparliament.tv/api/v1/search/people?party=CDU&page[number]=4&page[size]=25",
	"last": "https://de.openparliament.tv/api/v1/search/people?party=CDU&page[number]=13&page[size]=25"
  }
}</code></pre>
				</div>
			</div>
		</div>
	</div>
</main>
<style type="text/css">
	h3 > span {
		font-size: 11px;
		vertical-align: top;
		margin-top: 3px;
		display: inline-block;
		width: 16px;
	}
	.apiResultContainer {
		background: #fafafa;
		color: #986801;
		max-height: 300px;
		overflow: auto;
		margin-bottom: 20px;
	}
	.apiResultContainer .b {
		color: #383a42;
	}
	.apiResultContainer li > span:not(.num):not(.null):not(.q):not(.block),
	.apiResultContainer .str, .apiResultContainer a {
		color: #50a14f;
	}
</style>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/api/client/apiResult.js?v=<?= $config["version"] ?>"></script>