<?php

exit;
//HELPER FILE for fixing some things

require_once (__DIR__."/../config.php");
require_once(__DIR__."/../modules/utilities/safemysql.class.php");


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
    $errorarray["status"] = "503"; //TODO CODE
    $errorarray["code"] = "1";
    $errorarray["title"] = L::messageErrorNoDatabaseConnectionTitle;
    $errorarray["detail"] = L::messageErrorNoDatabaseConnectionDetail;
    array_push($return["errors"], $errorarray);
    return $return;

}

$persons = json_decode(file_get_contents(__DIR__."/wikidataDumps/DE/people.json"),true);
//print_r($persons);
foreach ($persons as $person) {

    $db->query("UPDATE ?n SET PersonThumbnailCreator=?s, PersonThumbnailLicense=?s, PersonGender=?s WHERE PersonID=?s",$config["platform"]["sql"]["tbl"]["Person"],$person["thumbnailCreator"],$person["thumbnailLicense"],$person["gender"],$person["id"]);
    echo "<br><br>";

}

?>