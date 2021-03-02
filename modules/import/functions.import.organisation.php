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
							$websiteURI = NULL,
							$socialMediaURIs = NULL,
							$color = NULL,
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

	if (!$websiteURI) {
		$tmparray["key"] = "websiteURI";
		$tmparray["msg"] = "Field required";
		$return["errors"][] = $tmparray;
		$errorcount++;
	}

	if ($errorcount > 0) {
		$return["success"] = "false";
		$return["text"] = "Missing required fields";
		return $return;
	}

	$dbentry = $dbPlatform->getRow("SELECT * FROM " . $config["platform"]["sql"]["tbl"]["Organisation"] . " WHERE OrganisationWikidataID = ?s LIMIT 1", $wikidataID);

	if ($dbentry["OrganisationWikidataID"]) {

		if ($updateIfExisting) {

			try {
				$dbPlatform->query("UPDATE
					" . $config["platform"]["sql"]["tbl"]["Organisation"] . "
					SET
						OrganisationType=?s,
						OrganisationLabel=?s,
						OrganisationLabelAlternative=?s,
						OrganisationAbstract=?s,
						OrganisationThumbnailURI=?s,
						OrganisationEmbedURI=?s,
						OrganisationWebsiteURI=?s,
						OrganisationSocialMediaURIs=?s,
						OrganisationColor=?s
					WHERE
						OrganisationID=?i",
					$type,
					$label,
					$labelAlternative,
					$abstract,
					$thumbnailURI,
					$embedURI,
					$websiteURI,
					$socialMediaURIs,
					$color,
					$dbentry["OrganisationID"]
				);
			} catch (Exception $e) {
				$return["success"] = "false";
				$return["text"] = "Could not update existing item. Error:".$e->getMessage();
				return $return;
			}
			$return["success"] = "true";
			$return["text"] = "Item has been updated";
			$return["item"]["OrganisationID"] = $dbentry["OrganisationID"];
			$return["item"]["WikidataID"] = $dbentry["OrganisationWikidataID"];
			return $return;

		} else {
			$return["success"] = "false";
			$return["text"] = "Item already exists";
			$return["item"]["OrganisationID"] = $dbentry["OrganisationID"];
			$return["item"]["WikidataID"] = $dbentry["OrganisationWikidataID"];
			return $return;
		}

	} else {

		try {

			$dbPlatform->query("INSERT INTO
					" . $config["platform"]["sql"]["tbl"]["Organisation"] . "
					SET
						OrganisationType=?s,
						OrganisationWikidataID=?s,
						OrganisationLabel=?s,
						OrganisationLabelAlternative=?s,
						OrganisationAbstract=?s,
						OrganisationThumbnailURI=?s,
						OrganisationEmbedURI=?s,
						OrganisationWebsiteURI=?s,
						OrganisationSocialMediaURIs=?s,
						OrganisationColor=?s",
				$type,
				$wikidataID,
				$label,
				$labelAlternative,
				$abstract,
				$thumbnailURI,
				$embedURI,
				$websiteURI,
				$socialMediaURIs,
				$color
			);

		} catch (Exception $e) {

			$return["success"] = "false";
			$return["text"] = "Could not add item. Error:".$e->getMessage();
			return $return;

		}

		$dbentry = $dbPlatform->getRow("SELECT * FROM " . $config["platform"]["sql"]["tbl"]["Organisation"] . " WHERE OrganisationWikidataID = ?s LIMIT 1", $wikidataID);
		$return["success"] = "true";
		$return["text"] = "Item has been added";
		$return["item"]["OrganisationID"] = $dbentry["OrganisationID"];
		$return["item"]["WikidataID"] = $dbentry["OrganisationWikidataID"];
		return $return;

	}

}

?>