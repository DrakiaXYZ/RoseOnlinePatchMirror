<?php
	if (isset($_SERVER['HTTP_USER_AGENT'])) {
		die("This application can only be run from the command prompt.");
	}

	require('functions.php');
	require('twitter.php');

	$file = "patch.xml";
	$lBase = "../";
	$pBase = $lBase."patch0.base/";
	$rBase = "http://patch.roseonlinegame.com/roseonline/evopatch/";
	$str = file_get_contents($rBase.$file);
	$Updated = false;
	$DLQueue = array();
	$MaxDLSlots = 10;

	// Setup base, used incase of file loss.
	@mkdir($lBase, 0777, true);
	@mkdir($pBase, 0777, true);
	if (!file_exists('Version')) {
		$fh = fopen('Version', 'w');
		fwrite($fh, '0');
		fclose($fh);
	}
	
	$xml = xml2array($str);

	// Get the patch root
	$PatchRoot = $xml[0][0]['value'];

	// Check executable file version.
	$RootFiles = $xml[0][1];
	$Zip = new ZipArchive();
	for ($i = 0; $i < count($xml[0][1]) - 1; $i++) {
		$Entry = $xml[0][1][$i]['attributes'];
		if ($Entry['compressed'] != "True") continue;
		// Fetch entry data
		$Name = $Entry['name'];
		$CRC = $Entry['crc'];
		$Dir = $Entry['dir'];
		$Archive = $Entry['file'];
		$lFile = $lBase.$Dir."/".$Archive;
		$rFile = $rBase.$Dir."/".$Archive;
		
		// Check local file, DL is required.
		$Download = false;
		if ($Zip->open($lFile) === true) {
			$File = $Zip->statName($Name, ZIPARCHIVE::FL_NOCASE);
			if ($File === false || $File['crc'] != $CRC)
				$Download = true;
			$Zip->close();
		} else
			$Download = true;

		if ($Download) {
			@mkdir($lBase.$Dir, 0777, true);
			print("Adding <".$Archive."> to download queue.\n");
			DLQueue($DLQueue, $rFile, $lBase.$Dir, $MaxDLSlots);
			$Updated = true;
		}
	}
	// wait for the queue to finish before moving on.
	DLQueueWait($DLQueue);

	// Check if new version.
	$Patches = $xml[0][2];
	$LatestVersion = 0;
	$LatestFile = '';
	for ($i = 0; $i < count($Patches) - 1; $i++) {
		$Entry = $Patches[$i]['attributes'];
		if ($Entry['version'] > $LatestVersion) {
			$LatestVersion = $Entry['version'];
			$LatestFile = $Entry['file'];
		}
	}
	$patchFolder = "patch0.".$LatestVersion;
	// Check if up to date.
	if ($LatestVersion > file_get_contents('Version')) {
		// Get latest patchlist, extract, parse with PatchParse.
		DLQueue($DLQueue, $PatchRoot.$LatestFile, $lBase, $MaxDLSlots);
		DLQueueWait($DLQueue);
		if ($Zip->open($lBase.$LatestFile) === true) {
			$Zip->extractTo(".");
			$Zip->close();
		} else {
			print "Error opening patchlist ZIP. Must exit.\n";
			die();
		}
		system("./PatchParse ".substr($LatestFile, 0, strlen($LatestFile) - 4));

		$fh = fopen("patchlist_php", "r");
		while (!feof($fh)) {
			$Download = false;
			$line = trim(fgets($fh));
			if ($line == "") continue;
			$line = explode("\t", $line);
			
			// Check if file exists/check files CRC
			if (!file_exists($pBase.$line[2]))
				$Download = true;
			else if ($Zip->open($pBase.$line[2]) === false)
				$Download = true;
			else {
				$File = $Zip->statIndex(0);
				if ($File === false || dechex((float)$File['crc']) != dechex((float)$line[3]))
					$Download = true;
			}

			// If required, add file to download queue.
			if ($Download) {
				print("Adding ".$line[0]." to download queue as <".$line[2].">.\n");
				DLQueue($Queue, $rBase.$line[1]."/".$line[2], $pBase, $MaxDLSlots);
			}
		}
		fclose($fh);
		$Updated = true;
	}
	
	echo "Ver: ".$LatestVersion." File: ".$LatestFile."\n";
	DLQueueWait($DLQueue);
	
	if ($Updated) {
		exec("ln -s patch0.base patch0.".$LatestVersion);
		$str = str_replace('http://patch.roseonlinegame.com/roseonline/evopatch/', 'http://www.zeropoke.net/rosepatch/', $str);
		$fh = fopen($lBase."patch.xml", "w");
		fwrite($fh, $str);
		fclose($fh);
		$fh = fopen("Version", "w");
		fwrite($fh, $LatestVersion);
		fclose($fh);
	}

?>
