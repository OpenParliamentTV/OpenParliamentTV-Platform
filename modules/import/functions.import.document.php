<?php


require_once(__DIR__."/../../config.php");
require_once(__DIR__."/../utilities/safemysql.class.php");
//require_once(__DIR__."/../utilities/uniqueFreeString.php");
require_once(__DIR__."/functions.conflicts.php");

function importDocument(	$type = NULL,
							$wikidataID = false,
							$label = false,
							$labelAlternative = NULL,
							$abstract  = NULL,
							$thumbnailURI = NULL,
							$sourceURI = NULL,
							$embedURI = NULL,
							$parliament = NULL,
							$documentIssue = NULL,
							$updateIfExisting = false,
							$dbPlatform = false) {

	global $config;

	if (!$dbPlatform) {
		$dbPlatform = new SafeMySQL(array(
			'host'	=> $config["platform"]["sql"]["access"]["host"],
			'user'	=> $config["platform"]["sql"]["access"]["user"],
			'pass'	=> $config["platform"]["sql"]["access"]["passwd"],
			'db'	=> $config["platform"]["sql"]["db"]
		));
	}

	if (!$dbPlatform) {
		$return["success"] = "false";
		$return["text"] = "Not connectable to Platform Database";
		return $return;
	}

	$errorcount = 0;

	if (!$type) {
		$tmparray["key"] = "type";
		$tmparray["msg"] = "Field required";
		$return["errors"][] = $tmparray;
		$errorcount++;
	}


	if (!$abstract) {
		$tmparray["key"] = "abstract";
		$tmparray["msg"] = "Field required";
		$return["errors"][] = $tmparray;
		$errorcount++;
	}

	if (!$sourceURI) {
		$tmparray["key"] = "sourceURI";
		$tmparray["msg"] = "Field required";
		$return["errors"][] = $tmparray;
		$errorcount++;
	}

	if (!$parliament) {
		$tmparray["key"] = "parliament";
		$tmparray["msg"] = "Field required";
		$return["errors"][] = $tmparray;
		$errorcount++;
	}

	if ($errorcount > 0) {
		$return["success"] = "false";
		$return["text"] = "Missing required fields";
		return $return;
	}

	$dbentry = $dbPlatform->getRow("SELECT * FROM " . $config["platform"]["sql"]["tbl"]["Document"] . " WHERE DocumentSourceURI = ?s LIMIT 1", $sourceURI);

	if ($dbentry["DocumentSourceURI"]) {

		if ($updateIfExisting) {

			try {
				$dbPlatform->query("UPDATE
					" . $config["platform"]["sql"]["tbl"]["Document"] . "
					SET
						DocumentType=?s,
						DocumentWikidataID=?s,
						DocumentLabel=?s,
						DocumentLabelAlternative=?s,
						DocumentAbstract=?s,
						DocumentThumbnailURI=?s,
						DocumentEmbedURI=?s,
						DocumentParliament=?s
						DocumentIssue=?s
					WHERE
						DocumentID=?i",
					$type,
					$wikidataID,
					$label,
					$labelAlternative,
					$abstract,
					$thumbnailURI,
					$embedURI,
					$parliament,
					$documentIssue,
					$dbentry["DocumentID"]
				);
			} catch (Exception $e) {
				$return["success"] = "false";
				$return["text"] = "Could not update existing item. Error:".$e->getMessage();
				return $return;
			}
			$return["success"] = "true";
			$return["text"] = "Item has been updated";
			$return["item"]["OrganisationID"] = $dbentry["DocumentID"];
			$return["item"]["SourceURI"] = $dbentry["DocumentSourceURI"];
			$return["item"]["WikidataID"] = $dbentry["DocumentWikidataID"];
			return $return;

		} else {
			$return["success"] = "false";
			$return["text"] = "Item already exists";
			$return["item"]["OrganisationID"] = $dbentry["DocumentID"];
			$return["item"]["SourceURI"] = $dbentry["DocumentSourceURI"];
			$return["item"]["WikidataID"] = $dbentry["DocumentWikidataID"];
			return $return;
		}

	} else {

		try {

			$dbPlatform->query("INSERT INTO
					" . $config["platform"]["sql"]["tbl"]["Document"] . "
					SET
						DocumentType=?s,
						DocumentWikidataID=?s,
						DocumentLabel=?s,
						DocumentLabelAlternative=?s,
						DocumentAbstract=?s,
						DocumentThumbnailURI=?s,
						DocumentSourceURI=?s,
						DocumentEmbedURI=?s,
						DocumentParliament=?s,
						DocumentIssue=?s",
				$type,
				$wikidataID,
				$label,
				$labelAlternative,
				$abstract,
				$thumbnailURI,
				$sourceURI,
				$embedURI,
				$parliament,
				$documentIssue
			);

		} catch (Exception $e) {

			$return["success"] = "false";
			$return["text"] = "Could not add item. Error:".$e->getMessage();
			return $return;

		}

		$dbentry = $dbPlatform->getRow("SELECT * FROM " . $config["platform"]["sql"]["tbl"]["Document"] . " WHERE DocumentSourceURI = ?s LIMIT 1", $sourceURI);
		$return["success"] = "true";
		$return["text"] = "Item has been added";
		$return["item"]["OrganisationID"] = $dbentry["OrganisationID"];
		$return["item"]["WikidataID"] = $dbentry["OrganisationWikidataID"];
		return $return;

	}

}

?>