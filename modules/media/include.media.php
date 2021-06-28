<?php

    $useragent=$_SERVER['HTTP_USER_AGENT'];
	$isMobile = (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i',$useragent)||preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i',substr($useragent,0,4)));


	$paramStr = "";
	$allowedParams = array_intersect_key($_REQUEST,array_flip(array("q","name","party","electoralPeriod","timefrom","timeto","gender","degree","aw_uuid","speakerID","sessionNumber", "playresults", "page", "sort")));
	$paramCount = 1;
	foreach ($allowedParams as $k=>$v) {
	    if ($paramCount == 1) {
			$paramPrefix = "?";
		} else {
			$paramPrefix = "&";
		}
	    if (is_array($v)) {
	        foreach ($v as $i) {
	            $paramStr .= $paramPrefix.$k."[]=".$i;
	        }
	    } else {
	        $paramStr .= $paramPrefix.$k."=".$_REQUEST[$k];
	    }
	    $paramCount++;

	}

	$isResult = (strlen($paramStr) > 2) ? true : false;

	$result = searchSpeeches($_REQUEST);

	$result_items = $result["hits"]["hits"];

	/*
	echo '<pre>';
	print_r($result);
	echo '</pre>';
	*/

	$speechID = $_REQUEST['id'];
	$autoplayResults = boolval($_REQUEST['playresults']);

	foreach ($result_items as $index=>$result_item) {
	    if ($result_item["_id"] == $speechID) {
	        $speech = $result_item;
	        $speechIndex = $index;
	        break;
	    }
	}

	$prevResult = ($speechIndex > 0) ? array_values(array_slice($result_items, $speechIndex-1, 1))[0] : null;
	$nextResult = ($speechIndex < count($result_items)) ? array_values(array_slice($result_items, $speechIndex+1, 1))[0] : null;

	$prevSpeech = json_decode(getPrevDocument($speech["_source"]["attributes"]["timestamp"]), true);

	/*
	echo '<pre>';
	print_r($prevSpeech);
	echo '</pre>';
	*/

	//$prevDate = gmdate("d.m.Y", strtotime($prevSpeech["_source"]["meta"]["date"]));

	$nextSpeech = json_decode(getNextDocument($speech["_source"]["attributes"]["timestamp"]), true);

	/*
	echo '<pre>';
	print_r($nextSpeech);
	echo '</pre>';
	*/
	//$nextDate = gmdate("d.m.Y", strtotime($nextSpeech["_source"]["meta"]["date"]));

	/*
	echo '<pre>';
	print_r($speech);
	echo '</pre>';
	*/

	$pathPeriod = sprintf('%02d',(int) $speech["_source"]["meta"]['electoralPeriod']);
	$pathSession = sprintf('%03d',(int) $speech["_source"]["meta"]['sessionNumber']);

	/*
	$speechTOPTitle = "";
	foreach ($speech["agendaItemThirdTitle"] as $t) {
	    $speechTOPTitle .= '<div title=\"'.$t.'\">'.$t.'</div>';
	}
	*/


	$speechTOPTitle = $speech["_source"]["meta"]["agendaItemSecondTitle"];

	$formattedDate = date("d.m.Y", strtotime($speech["_source"]["meta"]["date"]));

	$speechTitleShort = 'Redebeitrag '.$speech["_source"]["meta"]['speakerDegree'].' '.$speech["_source"]["meta"]['speakerFirstName'].' '.$speech["_source"]["meta"]['speakerLastName'].', '.$speech["_source"]["meta"]['speakerParty'].'  ('.$formattedDate.')';

	$speechTitle = '<div class="speechMeta">'.$formattedDate.' | Wahlperiode '.$speech["_source"]["meta"]['electoralPeriod'].' | Sitzung '.$speech["_source"]["meta"]['sessionNumber'].' | '.$speech["_source"]["meta"]['agendaItemTitle'].'</div>'.$speech["_source"]["meta"]['speakerDegree'].' '.$speech["_source"]["meta"]['speakerFirstName'].' '.$speech["_source"]["meta"]['speakerLastName'].' <span class="partyIndicator" data-party="'.$speech["_source"]["meta"]['speakerParty'].'">'.$speech["_source"]["meta"]['speakerParty'].'</span><div class=\"speechTOPs\">'.$speechTOPTitle.'</div>';

	$mediaSource = 'https://static.p.core.cdn.streamfarm.net/1000153copo/ondemand/145293313/'.$speech["_source"]["meta"]['mediaID'].'/'.$speech["_source"]["meta"]['mediaID'].'_h264_720_400_2000kb_baseline_de_2192.mp4';

	//$htmlSource = $pathPeriod.$pathSession.'-Rede-'.$speechID.'.html';

	$annotationSource = 'data/'.$pathPeriod.'/'.$pathSession.'/'.$pathPeriod.$pathSession.'-Rede-'.$speechID.'_annotations.json';

	$htmlContents = (isset($speech["highlight"])) ? $speech["highlight"]["content"][0] : $speech["_source"]["content"];

	$htmlContents = addPartyIndicators($htmlContents);

	$escapedHtmlContents = addslashes(str_replace(array("\r", "\n"), "", $htmlContents));

	$documents = $speech["_source"]["meta"]['documents'];
	$documentURLs = array();

	foreach ($documents as $docNumber) {

	    $docArray = str_split(str_replace('/', '', $docNumber));

	    $docPeriod = $docArray[0].$docArray[1];

	    if (count($docArray) == 3) {
	        $docSession = sprintf('%03d',(int) 0);
	        $docNumber = sprintf('%02d',(int) $docArray[2]);
	    } elseif (count($docArray) == 4) {
	        $docSession = sprintf('%03d',(int) 0);
	        $docNumber = sprintf('%02d',(int) $docArray[2].$docArray[3]);
	    } elseif (count($docArray) == 5) {
	        $docSession = sprintf('%03d',(int) $docArray[2]);
	        $docNumber = sprintf('%02d',(int) $docArray[3].$docArray[4]);
	    } else {
	        $docSession = sprintf('%03d',(int) $docArray[2].$docArray[3]);
	        $docNumber = sprintf('%02d',(int) $docArray[4].$docArray[5]);
	    }
	    
	    array_push($documentURLs, 'https://dip21.bundestag.de/dip21/btd/'.$docPeriod.'/'.$docSession.'/'.$docPeriod.$docSession.$docNumber.'.pdf');
	    //echo 'https://dip21.bundestag.de/dip21/btd/'.$docPeriod.'/'.$docSession.'/'.$docPeriod.$docSession.$docNumber.'.pdf <br><br>';

	}

	$documentURLs = array_reverse($documentURLs);

?>