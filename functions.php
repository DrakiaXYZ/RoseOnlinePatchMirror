<?php
/**
 * xml2array based on comments at: http://php.net/manual/en/function.xml-parse.php
**/
function xml2array(&$contents) 
{ 
    $parser = xml_parser_create(''); 
    if(!$parser) 
        return false; 

    xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, 'UTF-8'); 
    xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0); 
    xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1); 
    xml_parse_into_struct($parser, trim($contents), $xml_values); 
    xml_parser_free($parser); 
    if (!$xml_values) 
        return array(); 
    
    $xml_array = array(); 
    $last_tag_ar =& $xml_array; 
    $parents = array(); 
    $last_counter_in_tag = array(1=>0); 
    foreach ($xml_values as $data) 
    { 
        switch($data['type']) 
        { 
            case 'open': 
                $last_counter_in_tag[$data['level']+1] = 0; 
                $new_tag = array('name' => $data['tag']); 
                if(isset($data['attributes'])) 
                    $new_tag['attributes'] = $data['attributes']; 
                if(isset($data['value']) && trim($data['value'])) 
                    $new_tag['value'] = trim($data['value']); 
                $last_tag_ar[$last_counter_in_tag[$data['level']]] = $new_tag; 
                $parents[$data['level']] =& $last_tag_ar; 
                $last_tag_ar =& $last_tag_ar[$last_counter_in_tag[$data['level']]++]; 
                break; 
            case 'complete': 
                $new_tag = array('name' => $data['tag']); 
                if(isset($data['attributes'])) 
                    $new_tag['attributes'] = $data['attributes']; 
                if(isset($data['value']) && trim($data['value'])) 
                    $new_tag['value'] = trim($data['value']); 

                $last_count = count($last_tag_ar)-1; 
                $last_tag_ar[$last_counter_in_tag[$data['level']]++] = $new_tag; 
                break; 
            case 'close': 
                $last_tag_ar =& $parents[$data['level']]; 
                break; 
            default: 
                break; 
        }; 
    } 
    return $xml_array; 
}

function PsExecute($command, $timeout = 60, $sleep = 2) { 
	// First, execute the process, get the process ID 

	$pid = PsExec($command); 

	if( $pid === false ) 
		return false; 

	$cur = 0; 
	// Second, loop for $timeout seconds checking if process is running 
	while( $cur < $timeout ) { 
		sleep($sleep); 
		$cur += $sleep; 
		// If process is no longer running, return true; 

		echo "\n ---- $cur ------ \n"; 

		if( !PsExists($pid) ) 
			return true; // Process must have exited, success! 
	} 

	// If process is still running after timeout, kill the process and return false 
	PsKill($pid); 
	return false; 
} 

function PsExec($commandJob) { 

	$command = $commandJob.' > /dev/null 2>&1 & echo $!'; 
	exec($command ,$op); 
	$pid = (int)$op[0]; 

	if($pid!="") return $pid; 

	return false; 
} 

function PsExists($pid) { 

	exec("ps ax | grep $pid 2>&1", $output); 

	while( list(,$row) = each($output) ) { 

		$row_array = explode(" ", $row); 
		$check_pid = $row_array[0]; 

		if($pid == $check_pid) { 
			return true; 
		} 

	} 

	return false; 
}

function PsKill($pid) { 
	exec("kill -9 $pid", $output); 
}

function DLQueueClean(&$Queue) {
	foreach ($Queue as $Key => $Value) {
		if (!PsExists($Value)) {
			unset($Queue[$Key]);
		}
	}
}

function DLQueue(&$Queue, $Url, $Output, $Max) {
	if (!is_array($Queue)) {
		$Queue = array();
	}
//	print "Adding $Url -> $Output to Queue.\n";
	while (count($Queue) >= $Max) {
		sleep(1);
		DLQueueClean($Queue);
	}
	$Pid = PsExec("wget -q -t 0 ".$Url." -P ".$Output);
	array_push($Queue, $Pid);

	return $Pid;
}

function DLQueueWait(&$Queue) {
	if (!is_array($Queue)) return;

	while (count($Queue) > 0) {
		usleep(500000);
		DLQueueClean($Queue);
	}

}
?>
