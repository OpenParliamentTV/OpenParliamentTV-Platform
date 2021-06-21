<?php

function array_diff_assoc_recursive($array1, $array2)
{
	foreach($array1 as $key => $value){

		if(is_array($value)){
			if(!isset($array2[$key]))
			{
				$difference[$key] = $value;
			}
			elseif(!is_array($array2[$key]))
			{
				$difference[$key] = $value;
			}
			else
			{
				$new_diff = array_diff_assoc_recursive($value, $array2[$key]);
				if($new_diff != FALSE)
				{
					$difference[$key] = $new_diff;
				}
			}
		}
		elseif((!isset($array2[$key]) || $array2[$key] != $value) && !($array2[$key]===null && $value===null))
		{
			$difference[$key] = $value;
		}
	}
	return !isset($difference) ? 0 : $difference;
}

function arrayRecursiveDiff($aArray1, $aArray2) {
	$aReturn = array();

	foreach ($aArray1 as $mKey => $mValue) {
		if (array_key_exists($mKey, $aArray2)) {
			if (is_array($mValue)) {
				$aRecursiveDiff = arrayRecursiveDiff($mValue, $aArray2[$mKey]);
				if (count($aRecursiveDiff)) { $aReturn[$mKey] = $aRecursiveDiff; }
			} else {
				if ($mValue != $aArray2[$mKey]) {
					$aReturn[$mKey] = $mValue;
				}
			}
		} else {
			$aReturn[$mKey] = $mValue;
		}
	}
	return $aReturn;
}


/**
 * @param string $password
 * @return bool
 * Checks if the given password is weak (returns false) or strong (Uppercase, lowercase, number, specialChar and >8 character)
 */
function passwordStrength($password = "") {

	$uppercase = preg_match('@[A-Z]@', $password);
	$lowercase = preg_match('@[a-z]@', $password);
	$number    = preg_match('@[0-9]@', $password);
	$specialChars = preg_match('@[^\w]@', $password);

	if(!$uppercase || !$lowercase || !$number || !$specialChars || strlen($password) < 8) {

		return false; // Password is weak.

	} else {

		return true; // Password is strong

	}

}

/**
 *
 * eg.:
 *      DE-BY-0190023123
 *          returns
 *      array(
 *          "parliament" => "DE-BY",
 *          "type" => "media"
 *          "id_full" => "DE-BY-0190023123"
 *          "id_part" => "0190023123",
 *          "electoralPeriodNumber" => "019",
 *          "sessionNumber" => "0023",
 *          "mediaNumber" => "123"
 *      );
 *
 * -----
 *
 *      DE-BY-0190023
 *          returns
 *      array(
 *          "parliament" => "DE-BY",
 *          "type" => "session"
 *          "id_full" => "DE-BY-0190023"
 *          "id_part" => "0190023",
 *          "electoralPeriodNumber" => "019",
 *          "sessionNumber" => "0023"
 *      );
 * @param string $stringID
 * @return string
 */
function getInfosFromStringID($stringID = "") {


    if ($stringID == "") {

        return false;

    } else {

        $stringsplit = explode("-",$stringID);

        $id = array_pop($stringsplit);

        $length = strlen($id);

        if (strlen($length) >= 8) {

            $return["type"] = "media";
            $return["id_part"] = $id;
            $return["id_full"] = $stringID;
            $return["electoralPeriodNumber"] = substr($id,0, 3);
            $return["sessionNumber"] = substr($id,3, 4);
            $return["mediaNumber"] = substr($id,7);

        } elseif ($length == 7) {

            $return["type"] = "session";
            $return["id_part"] = $id;
            $return["id_full"] = $stringID;
            $return["electoralPeriodNumber"] = substr($id,0, 3);
            $return["sessionNumber"] = substr($id,3, 4);

        } elseif ($length == 3) {

            $return["type"] = "electoralPeriod";
            $return["id_part"] = $id;
            $return["id_full"] = $stringID;
            $return["electoralPeriodNumber"] = substr($id,0, 3);

        } else {

            $return["type"] = "unknown"; // Maybe AgendaItem
            $return["id_part"] = $id;
            $return["id_full"] = $stringID;

        }

        if (is_array($stringsplit)) {

            $parliament = implode("-",$stringsplit);

        }

        $return["parliament"] = $parliament;

        return $return;
    }

}


?>