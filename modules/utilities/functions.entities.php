<?php
require_once(__DIR__."/../../config.php");
require_once(__DIR__."/safemysql.class.php");
require_once(__DIR__."/functions.conflicts.php");

function flattenEntityJSON($array, $prefix = '') {
    $flat = array();
    $sep = ".";
    
    if (!is_array($array)) $array = (array)$array;
    
    foreach($array as $key => $value) {
        
        if ($key == 'type' || 
            $key == 'relationships' || 
            $key == 'gender' || 
            $key == 'socialMediaIDs') {
            continue;
        }

        //$_key = ltrim($prefix.$sep.$key, ".");
        $_key = $key;
        
        if (is_array($value) || is_object($value)) {
            // Iterate this one too
            $flat = array_merge($flat, flattenEntityJSON($value, $_key));
        } else {
            $flat[$_key] = $value;
        }
    }
    
    return $flat;
}

function getMainSpeakerFromPeopleArray($annotationsArray, $peopleArray) {

    foreach ($annotationsArray as $annotation) {
        if ($annotation["attributes"]["context"] == "main-speaker") {
            foreach ($peopleArray as $person) {
                if ($person["id"] == $annotation["id"]) {
                    $mainSpeaker = $person;
                }
            }
        }
    }

    if (!isset($mainSpeaker)) {
        //TODO
        //$mainSpeaker = $peopleArray[0];
        foreach ($annotationsArray as $annotation) {
            if (preg_match("~president~",$annotation["attributes"]["context"])) {
                foreach ($peopleArray as $person) {
                    if ($person["id"] == $annotation["id"]) {
                        $mainSpeaker = $person;
                    }
                }
            }
        }
    }
    if (!isset($mainSpeaker)) {
        $mainSpeaker = array("PersonLabel"=>"TODO");
    }
    
    return $mainSpeaker;
}

function getMainFactionFromOrganisationsArray($annotationsArray, $organisationsArray) {

    foreach ($annotationsArray as $annotation) {
        if ($annotation["attributes"]["context"] == "main-speaker-faction") {
            foreach ($organisationsArray as $organisation) {
                if ($organisation["id"] == $annotation["id"]) {
                    $mainFaction = $organisation;
                }
            }
        }
    }

    if (!isset($mainFaction)) {
        //$mainFaction = $organisationsArray[0];
        $mainFaction = null;
    }

    return $mainFaction;
}

/**
 * @param $EntitysuggestionExternalID
 * @param $EntitysuggestionType
 * @param $EntitysuggestionLabel
 * @param $EntitysuggestionContent
 * @param $EntitysuggestionContext
 * @param false $dbPlatform
 */

function reportEntitySuggestion($entitysuggestionExternalID, $entitysuggestionType, $entitysuggestionLabel, $entitysuggestionContent, $entitysuggestionContext,$dbPlatform = false) {

    global $config;

    if (!$dbPlatform) {
        $dbPlatform = new SafeMySQL(array(
            'host'	=> $config["platform"]["sql"]["access"]["host"],
            'user'	=> $config["platform"]["sql"]["access"]["user"],
            'pass'	=> $config["platform"]["sql"]["access"]["passwd"],
            'db'	=> $config["platform"]["sql"]["db"]
        ));
    }

    if (!$entitysuggestionExternalID) {

        $reportArray["type"] = $entitysuggestionType;
        $reportArray["label"] = $entitysuggestionLabel;
        $reportArray["content"] = $entitysuggestionContent;
        $reportArray["context"] = $entitysuggestionContext;

        reportConflict("Entitysuggestion", "Suggestion had no ID", "", "", json_encode($reportArray,JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $dbPlatform);
        return;
    }

    $exists = $dbPlatform->getRow("SELECT * FROM ?n WHERE EntitysuggestionExternalID = ?s", $config["platform"]["sql"]["tbl"]["Entitysuggestion"], $entitysuggestionExternalID);

    if ($exists) {

        $context = json_decode($exists["EntitysuggestionContext"],true);
        $context[$entitysuggestionContext] = $entitysuggestionContext;
        $context = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        try {
            $dbPlatform->query("UPDATE ?n SET EntitysuggestionContext = ?s WHERE EntitysuggestionID = ?i",$config["platform"]["sql"]["tbl"]["Entitysuggestion"], $context, $exists["EntitysuggestionID"]);
        } catch (exception $e) {
            echo $e;
        }
    } else {

        $context[$entitysuggestionContext] = $entitysuggestionContext;

        try {
            $dbPlatform->query("INSERT INTO ?n SET
                                    EntitysuggestionExternalID = ?s,
                                    EntitysuggestionType = ?s,
                                    EntitysuggestionLabel = ?s,
                                    EntitysuggestionContent = ?s,
                                    EntitysuggestionContext = ?s",
                $config["platform"]["sql"]["tbl"]["Entitysuggestion"],
                $entitysuggestionExternalID,
                $entitysuggestionType,
                $entitysuggestionLabel,
                $entitysuggestionContent,
                json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } catch (exception $e) {
            echo $e;
        }

    }
    return true;

}

function getEntitySuggestion($id, $idType="internal", $type="", $db=false) {

    global $config;

    if (!$db) {
        try {

            $db = new SafeMySQL(array(
                'host'	=> $config["platform"]["sql"]["access"]["host"],
                'user'	=> $config["platform"]["sql"]["access"]["user"],
                'pass'	=> $config["platform"]["sql"]["access"]["passwd"],
                'db'	=> $config["platform"]["sql"]["db"]
            ));

        } catch (exception $e) {

            $return["meta"]["requestStatus"] = "error";
            $return["errors"] = array();
            $errorarray["status"] = "503";
            $errorarray["code"] = "1";
            $errorarray["title"] = "Database connection error";
            $errorarray["detail"] = "Connecting to platform database failed";
            array_push($return["errors"], $errorarray);
            return $return;

        }
    }
    if ($idType == "internal") {
        $item = $db->getRow("SELECT * FROM ?n WHERE EntitysuggestionID = ?i", $config["platform"]["sql"]["tbl"]["Entitysuggestion"], $id);
    } elseif ($idType == "external") {
        if ($type) {
            $conditionType = $db->parse(" AND EntitysuggestionType=?s", $type);
        }
        $item = $db->getRow("SELECT * FROM ?n WHERE EntitysuggestionExternalID = ?s".$conditionType, $config["platform"]["sql"]["tbl"]["Entitysuggestion"], $id);
    }

    $item["EntitysuggestionContext"] = json_decode($item["EntitysuggestionContext"],true);

    return $item;

}


function getEntitySuggestionTable($id = "all", $limit = 0, $offset = 0, $search = false, $sort = false, $order = false, $getCount = false, $db = false) {

    global $config;

    if (!$db) {
        $db = new SafeMySQL(array(
            'host'	=> $config["platform"]["sql"]["access"]["host"],
            'user'	=> $config["platform"]["sql"]["access"]["user"],
            'pass'	=> $config["platform"]["sql"]["access"]["passwd"],
            'db'	=> $config["platform"]["sql"]["db"]
        ));
    }

    $queryPart = "";

    if ($id == "all") {
        $queryPart .= "1";
    } else {
        $queryPart .= $db->parse("EntitysuggestionExternalID=?s",$id);
    }


    if (!empty($search)) {

        $queryPart .= $db->parse(" AND (EntitysuggestionLabel LIKE ?s OR EntitysuggestionExternalID LIKE ?s)", "%".$search."%","%".$search."%");

    }

    if (!empty($sort)) {

        $queryPart .= $db->parse(" ORDER BY ?n ".$order, $sort);

    }


    if ($limit != 0) {

        $queryPart .= $db->parse(" LIMIT ?i, ?i",$offset,$limit);

    }

    if ($getCount == true) {

        $return["total"] = $db->getOne("SELECT COUNT(EntitysuggestionID) as count FROM  " . $config["platform"]["sql"]["tbl"]["Entitysuggestion"]);
        $return["rows"] = $db->getAll("SELECT *, JSON_LENGTH(EntitysuggestionContext) as EntitysuggestionCount FROM " . $config["platform"]["sql"]["tbl"]["Entitysuggestion"]." WHERE ?p", $queryPart);

    } else {
        $return = $db->getAll("SELECT *, JSON_LENGTH(EntitysuggestionContext) as EntitysuggestionCount FROM " . $config["platform"]["sql"]["tbl"]["Entitysuggestion"]." WHERE ?p", $queryPart);
    }


    return $return;

}

?>