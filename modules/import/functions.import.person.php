<?php


require_once(__DIR__."/../../config.php");
require_once(__DIR__."/../utilities/safemysql.class.php");
//require_once(__DIR__."/../utilities/uniqueFreeString.php");
require_once(__DIR__."/functions.conflicts.php");

function importPerson($type = NULL,
							$wikidataID = false,
							$label = false,
							$firstName = NULL,
							$lastName  = NULL,
							$degree  = NULL,
							$birthDate  = NULL,
							$gender  = NULL,
							$abstract  = NULL,
							$thumbnailURI = NULL,
							$embedURI = NULL,
							$websiteURI = NULL,
							$originID = NULL,
							$partyOrganisationID = NULL,
							$factionOrganisationID = NULL,
							$socialMediaURIs = NULL,
							$additionalInformation = NULL,
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

	if ($errorcount > 0) {
		$return["success"] = "false";
		$return["text"] = "Missing required fields";
		return $return;
	}

	$dbentry = $dbPlatform->getRow("SELECT * FROM " . $config["platform"]["sql"]["tbl"]["Person"] . " WHERE PersonWikidataID = ?s LIMIT 1", $wikidataID);

	if ($dbentry["personWikidataID"]) {

		if ($updateIfExisting) {

			try {
				$dbPlatform->query("UPDATE
					" . $config["platform"]["sql"]["tbl"]["Person"] . "
					SET
						PersonType=?s,
						PersonLabel=?s,
						PersonFirstName=?s,
						PersonLastName=?s,
						PersonDegree=?s,
						PersonBirthDate=?s,
						PersonGender=?s,
						PersonAbstract=?s,
						PersonThumbnailURI=?s,
						PersonEmbedURI=?s,
						PersonWebsiteURI=?s,
						PersonOriginID=?s,
						PersonPartyOrganisationID=?s,
						PersonFactionOrganisationID=?s,
						PersonSocialMediaURIs=?s,
						PersonAdditionalInformation=?s
					WHERE
						PersonID=?i",
					$type,
					$label,
					$firstName,
					$lastName,
					$degree,
					$birthDate,
					$gender,
					$abstract,
					$thumbnailURI,
					$embedURI,
					$websiteURI,
					$originID,
					$partyOrganisationID,
					$factionOrganisationID,
					$socialMediaURIs,
					$additionalInformation,
					$dbentry["PersonID"]
				);
			} catch (Exception $e) {
				$return["success"] = "false";
				$return["text"] = "Could not update existing item. Error:".$e->getMessage();
				return $return;
			}
			$return["success"] = "true";
			$return["text"] = "Item has been updated";
			$return["item"]["PersonID"] = $dbentry["PersonID"];
			$return["item"]["WikidataID"] = $dbentry["PersonWikidataID"];
			return $return;

		} else {
			$return["success"] = "false";
			$return["text"] = "Item already exists";
			$return["item"]["PersonID"] = $dbentry["PersonID"];
			$return["item"]["WikidataID"] = $dbentry["PersonWikidataID"];
			return $return;
		}

	} else {

		try {

			$dbPlatform->query("INSERT INTO
					" . $config["platform"]["sql"]["tbl"]["Person"] . "
					SET
						PersonType=?s,
						PersonWikidataID=?s,
						PersonLabel=?s,
						PersonFirstName=?s,
						PersonLastName=?s,
						PersonDegree=?s,
						PersonBirthDate=?s,
						PersonGender=?s,
						PersonAbstract=?s,
						PersonThumbnailURI=?s,
						PersonEmbedURI=?s,
						PersonWebsiteURI=?s,
						PersonOriginID=?s,
						PersonPartyOrganisationID=?s,
						PersonFactionOrganisationID=?s,
						PersonSocialMediaURIs=?s,
						PersonAdditionalInformation=?s",
				$type,
				$wikidataID,
				$label,
				$firstName,
				$lastName,
				$degree,
				$birthDate,
				$gender,
				$abstract,
				$thumbnailURI,
				$embedURI,
				$websiteURI,
				$originID,
				$partyOrganisationID,
				$factionOrganisationID,
				$socialMediaURIs,
				$additionalInformation
			);

		} catch (Exception $e) {

			$return["success"] = "false";
			$return["text"] = "Could not add item. Error:".$e->getMessage();
			return $return;

		}

		$dbentry = $dbPlatform->getRow("SELECT * FROM " . $config["platform"]["sql"]["tbl"]["Person"] . " WHERE PersonWikidataID = ?s LIMIT 1", $wikidataID);
		$return["success"] = "true";
		$return["text"] = "Item has been added";
		$return["item"]["PersonID"] = $dbentry["PersonID"];
		$return["item"]["WikidataID"] = $dbentry["PersonWikidataID"];
		return $return;

	}

}

?>