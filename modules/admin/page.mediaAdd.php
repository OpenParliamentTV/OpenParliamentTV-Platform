<?php
require_once(__DIR__."/../../config.php");
?>
<form action="" method="post">
	<input type="hidden" name="a" value="mediaAdd">
	<div class="row">
		<div class="col-6 mb-4">
			<div class="card h-100">
				<div class="card-header">Affiliation</div>
				<div class="card-body">
					<label for="parliament">Parliament</label>
					<select class="form-control mb-3" name="parliament">
						<?php
						foreach($config["parliament"] as $k=>$v) {
							echo '<option value="'.$k.'">'.$v["label"].'</option>';
						}
						?>
					</select>
					<div class="form-group">
						<label for="electoralPeriod">Electoral Period</label>
						<input type="number" class="form-control" id="electoralPeriod"  name="electoralPeriod" value="">
					</div>
					<div class="form-group">
						<label for="sessionNumber">Session Number</label>
						<input type="number" class="form-control" id="sessionNumber"  name="sessionNumber" value="">
					</div>
					<div class="form-group">
						<label for="dateStart">Date Start</label>
						<input type="date" class="form-control" id="dateStart"  name="dateStart" value="">
					</div>
					<div class="form-group">
						<label for="dateEnd">Date End</label>
						<input type="date" class="form-control" id="dateEnd"  name="dateEnd" value="">
					</div>
					<div class="form-group">
						<label for="agendaItemTitle">Agenda Title</label>
						<input type="text" class="form-control" id="agendaItemTitle"  name="agendaItemTitle" value="">
					</div>
					<div class="form-group">
						<label for="agendaItemSecondTitle">Agenda Second Title</label>
						<input type="text" class="form-control" id="agendaItemSecondTitle"  name="agendaItemSecondTitle" value="">
					</div>
				</div>
			</div>
		</div>
		<div class="col-6 mb-4">
			<div class="card mb-4 h-100">
				<div class="card-header">Speaker</div>
				<div class="card-body">
					<div class="form-group">
						<label for="speakerID">Speaker ID</label>
						<input type="text" class="form-control" id="speakerID"  name="speakerID" value="">
					</div>
					<div class="form-group">
						<label for="speakerFirstName">Speaker First Name</label>
						<input type="text" class="form-control" id="speakerFirstName" name="speakerFirstName" value="">
					</div>
					<div class="form-group">
						<label for="speakerLastName">Speaker Last Name</label>
						<input type="text" class="form-control" id="speakerLastName" name="speakerLastName" value="">
					</div>
					<div class="form-group">
						<label for="speakerDegree">Speaker Degree</label>
						<input type="text" class="form-control" id="speakerDegree" name="speakerDegree" value="">
					</div>
					<div class="form-group">
						<label for="speakerParty">Speaker Party</label>
						<input type="text" class="form-control" id="speakerParty" name="speakerParty" value="">
					</div>
					<div class="form-group">
						<label for="speakerRole">Speaker Role</label>
						<input type="text" class="form-control" id="speakerRole" name="speakerRole" value="">
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-6 mb-4">
			<div class="card h-100">
				<div class="card-header">Media</div>
				<div class="card-body">
					<div class="form-group">
						<label for="id">OriginalID</label>
						<input type="text" class="form-control" id="id"  name="id" value="">
					</div>
					<div class="form-group">
						<label for="mediaID">mediaID</label>
						<input type="text" class="form-control" id="mediaID"  name="mediaID" value="">
					</div>
					<div class="form-group">
						<label for="mediaURL">Media URL</label>
						<input type="url" class="form-control" id="mediaURL"  name="mediaURL" value="">
					</div>
					<div class="form-group">
						<label for="mediaOriginalURL">Media Original URL</label>
						<input type="url" class="form-control" id="mediaOriginalURL"  name="mediaOriginalURL" value="">
					</div>
					<div class="form-group">
						<label for="duration">Duration (seconds)</label>
						<input type="number" class="form-control" id="duration"  name="duration" value="">
					</div>
					<div class="form-group">
						<label for="content">Media Content</label>
						<textarea class="form-control" id="content"  name="content"></textarea>
					</div>
				</div>
			</div>
		</div>

		<div class="col-6 mb-4">
			<div class="card h-100">
				<div class="card-header">Annotations</div>
				<div class="card-body">
					WIP // TODO
				</div>
			</div>
		</div>
	</div>

</form>
