<?php
include_once(__DIR__ . '/../../../../../modules/utilities/auth.php');

$auth = auth($_SESSION["userdata"]["id"], "requestPage", $pageType);

if ($auth["meta"]["requestStatus"] != "success") {

    $alertText = $auth["errors"][0]["detail"];
    include_once (__DIR__."/../../../login/page.php");

} else {


require_once(__DIR__ . '/../../../../../config.php');
include_once(__DIR__ . '/../../../../header.php');

if ($_REQUEST["aTEST"]) {
    echo "<pre>";
    print_r($_REQUEST);
    echo "</pre>";
}
?>
<main class="container subpage">
	<div class="row" style="position: relative; z-index: 1">
		<div class="col-12">
			<h2>Add New Media</h2>
			<form action="" method="post" id="mediaAddForm">
				<input type="hidden" name="a" value="mediaAdd">
				<div class="row">
					<div class="col-6 mb-4">
						<div class="card h-100">
							<div class="card-header">Affiliation</div>
							<div class="card-body">
								<label for="parliament">Parliament</label>
								<select class="form-control mb-3" id="parliament" name="parliament">
									<?php
									foreach($config["parliament"] as $k=>$v) {
										echo '<option value="'.$k.'">'.$v["label"].'</option>';
									}
									?>
								</select>
								<div class="form-group">
									<label for="electoralPeriodNumber">Electoral Period Number</label>
									<input type="number" class="form-control" id="electoralPeriodNumber" name="electoralPeriod[number]" value="">
								</div>
								<div class="form-group">
									<label for="sessionNumber">Session Number</label>
									<input type="number" class="form-control" id="sessionNumber" name="session[number]" value="">
								</div>
								<div class="form-group">
									<label for="sessionDateStart">Session Date Start</label>
									<input type="text" class="form-control" id="sessionDateStart" name="session[dateStart]" value="">
								</div>
								<div class="form-group">
									<label for="sessionDateEnd">Session Date End</label>
									<input type="text" class="form-control" id="sessionDateEnd" name="session[dateEnd]" value="">
								</div>
								<div class="form-group">
									<label for="agendaItemOfficialTitle">AgendaItem Official Title</label>
									<input type="text" class="form-control" id="agendaItemOfficialTitle" name="agendaItem[officialTitle]" value="">
								</div>
								<div class="form-group">
									<label for="agendaItemTitle">AgendaItem Title</label>
									<input type="text" class="form-control" id="agendaItemTitle" name="agendaItem[title]" value="">
								</div>
							</div>
						</div>
					</div>
                    <div class="col-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header">Media</div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="mediaAudioFileURI">mediaAudioFileURI</label>
                                    <input type="text" class="form-control" id="mediaAudioFileURI" name="media[audioFileURI]" value="">
                                </div>
                                <div class="form-group">
                                    <label for="mediaVideoFileURI">mediaVideoFileURI</label>
                                    <input type="text" class="form-control" id="mediaVideoFileURI" name="media[videoFileURI]" value="">
                                </div>
                                <div class="form-group">
                                    <label for="mediaThumbnailURI">mediaThumbnailURI</label>
                                    <input type="text" class="form-control" id="mediaThumbnailURI" name="media[thumbnailURI]" value="">
                                </div>
                                <div class="form-group">
                                    <label for="mediaThumbnailCreator">mediaThumbnailCreator</label>
                                    <input type="text" class="form-control" id="mediaThumbnailCreator" name="media[thumbnailCreator]" value="">
                                </div>
                                <div class="form-group">
                                    <label for="mediaThumbnailLicense">mediaThumbnailLicense</label>
                                    <input type="text" class="form-control" id="mediaThumbnailLicense" name="media[thumbnailLicense]" value="">
                                </div>
                                <div class="form-group">
                                    <label for="mediaDuration">mediaDuration</label>
                                    <input type="text" class="form-control" id="mediaDuration" name="media[duration]" value="">
                                </div>
                                <div class="form-group">
                                    <label for="mediaCreator">mediaCreator</label>
                                    <input type="text" class="form-control" id="mediaCreator" name="media[creator]" value="">
                                </div>
                                <div class="form-group">
                                    <label for="mediaLicense">mediaLicense</label>
                                    <input type="text" class="form-control" id="mediaLicense" name="media[license]" value="">
                                </div>
                                <div class="form-group">
                                    <label for="mediaOriginID">mediaOriginID</label>
                                    <input type="text" class="form-control" id="mediaOriginID" name="media[originID]" value="">
                                </div>
                                <div class="form-group">
                                    <label for="mediaOriginMediaID">mediaOriginMediaID</label>
                                    <input type="text" class="form-control" id="mediaOriginMediaID" name="media[originMediaID]" value="">
                                </div>
                                <div class="form-group">
                                    <label for="mediaSourcePage">mediaSourcePage</label>
                                    <input type="text" class="form-control" id="mediaSourcePage" name="media[sourcePage]" value="">
                                </div>
                                <div class="form-group">
                                    <label for="dateStart">dateStart (MediaDateStart)</label>
                                    <input type="text" class="form-control" id="dateStart" name="dateStart" value="">
                                </div>
                                <div class="form-group">
                                    <label for="dateEnd">dateEnd (MediaDateEnd)</label>
                                    <input type="text" class="form-control" id="dateEnd" name="dateEnd" value="">
                                </div>
                                <div class="form-group">
                                    <label for="mediaAdditionalInformation">mediaAdditionalInformation</label>
                                    <input type="text" class="form-control" id="mediaAdditionalInformation" name="media[additionalInformation]" value="">
                                </div>
                            </div>
                        </div>
                    </div>
				</div>
				<div class="row">
					<div class="col-12 mb-4">
                        <div class="card h-100">
                            <div class="card-header">Text</div>
                            <div class="card-body" id="media-text-body">
                                WIP // TODO
                            </div>
                            <button id="media-text-body-button-add" class="btn button" type="button">add</button>
                        </div>
                    </div>
                </div>
				<div class="row">
					<div class="col-6 mb-4">
						<div class="card h-100">
							<div class="card-header">People</div>
							<div class="card-body" id="media-people-body">
								WIP // TODO
							</div>
                            <button id="media-people-body-button-add" class="btn button" type="button">add</button>
						</div>
					</div>
				<div class="col-6 mb-4">
						<div class="card h-100">
							<div class="card-header">Documents</div>
							<div class="card-body" id="media-documents-body">
								WIP // TODO
							</div>
                            <button id="media-documents-body-button-add" class="btn button" type="button">add</button>
						</div>
					</div>
				</div>
				<div class="row">
					<div class="col-12 mb-4">
						<button type="submit" class="btn btn-outline-primary">Add Media</button>
					</div>
				</div>

			</form>
		</div>
	</div>
</main>
<link rel="stylesheet" href="<?= $config["dir"]["root"] ?>/content/client/css/jquery.typeahead.min.css?v=<?= $config["version"] ?>">
<link rel="stylesheet" href="<?= $config["dir"]["root"] ?>/content/pages/manage/data/media/client/media.new.css?v=<?= $config["version"] ?>">
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/client/js/jquery.typeahead.min.js?v=<?= $config["version"] ?>"></script>
<script type="text/javascript" src="<?= $config["dir"]["root"] ?>/content/pages/manage/data/media/client/media.new.js?v=<?= $config["version"] ?>"></script>
<?php
    include_once(__DIR__ . '/../../../../footer.php');

}
?>