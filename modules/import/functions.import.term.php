<?php


require_once(__DIR__."/../../config.php");
require_once(__DIR__."/../utilities/safemysql.class.php");
//require_once(__DIR__."/../utilities/uniqueFreeString.php");
require_once(__DIR__."/functions.conflicts.php");

function importOrganisation($type = NULL,
							$wikidataID = false,
							$label = false,
							$labelAlternative = NULL,
							$abstract  = NULL,
							$thumbnailURI = NULL,
							$embedURI = NULL,
							$sourceURI = NULL,
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

	//TODO: Validate Wikidata-Syntax
	if (!$wikidataID) {
		$tmparray["key"] = "wikidataID";
		$tmparray["msg"] = "Field required";
		$return["errors"][] = $tmparray;
		$errorcount++;
	}

	if (!$label) {
		$tmparray["key"] = "label";
		$tmparray["msg"] = "Field required";
		$return["errors"][] = $tmparray;
		$errorcount++;
	}

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

	if ($errorcount > 0) {
		$return["success"] = "false";
		$return["text"] = "Missing required fields";
		return $return;
	}

	$dbentry = $dbPlatform->getRow("SELECT * FROM " . $config["platform"]["sql"]["tbl"]["Term"] . " WHERE TermWikidataID = ?s LIMIT 1", $wikidataID);

	if ($dbentry["TermWikidataID"]) {

		if ($updateIfExisting) {

			try {
				$dbPlatform->query("UPDATE
					" . $config["platform"]["sql"]["tbl"]["Term"] . "
					SET
						TermType=?s,
						TermLabel=?s,
						TermWikidataID=?s,
						TermLabelAlternative=?s,
						TermAbstract=?s,
						TermThumbnailURI=?s,
						TermEmbedURI=?s,
						TermSourceURI=?s
					WHERE
						TermID=?i",
					$type,
					$label,
					$wikidataID,
					$labelAlternative,
					$abstract,
					$thumbnailURI,
					$embedURI,
					$sourceURI,
					$dbentry["TermID"]
				);
			} catch (Exception $e) {
				$return["success"] = "false";
				$return["text"] = "Could not update existing item. Error:".$e->getMessage();
				return $return;
			}
			$return["success"] = "true";
			$return["text"] = "Item has been updated";
			$return["item"]["TermID"] = $dbentry["TermID"];
			$return["item"]["WikidataID"] = $dbentry["TermWikidataID"];
			return $return;

		} else {
			$return["success"] = "false";
			$return["text"] = "Item already exists";
			$return["item"]["TermID"] = $dbentry["TermID"];
			$return["item"]["WikidataID"] = $dbentry["TermWikidataID"];
			return $return;
		}

	} else {

		try {
			$dbPlatform->query("INSERT INTO
					" . $config["platform"]["sql"]["tbl"]["Term"] . "
					SET
						TermType=?s,
						TermLabel=?s,
						TermWikidataID=?s,
						TermLabelAlternative=?s,
						TermAbstract=?s,
						TermThumbnailURI=?s,
						TermEmbedURI=?s,
						TermSourceURI=?s",
				$type,
				$label,
				$wikidataID,
				$labelAlternative,
				$abstract,
				$thumbnailURI,
				$embedURI,
				$sourceURI
			);
		} catch (Exception $e) {
			$return["success"] = "false";
			$return["text"] = "Could not add item. Error:".$e->getMessage();
			return $return;
		}
		$dbentry = $dbPlatform->getRow("SELECT * FROM " . $config["platform"]["sql"]["tbl"]["Term"] . " WHERE TermWikidataID = ?s LIMIT 1", $wikidataID);
		$return["success"] = "true";
		$return["text"] = "Item has been added";
		$return["item"]["TermID"] = $dbentry["TermID"];
		$return["item"]["WikidataID"] = $dbentry["TermWikidataID"];
		return $return;

	}

}

?>