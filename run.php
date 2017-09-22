<?php

// ---------- DO NOT REMOVE ----------
echo "------------------------\n   DAPNET Propagation   \n------------------------\n\n";
if (php_sapi_name() !== "cli") die("[CRIT] This script must be run from the command line. Aborting...\n");
if (php_uname("s") !== "Linux") die("[CRIT] This script must be run on a Linux system. Aborting...\n");
if (file_exists("./.lock")) die ("[CRIT] This script is already running. Aborting...\n");
touch("./.lock");
// -----------------------------------

// ---------- CONFIGURATION ----------
require_once("./config.php");
// -----------------------------------

// ---------- GET SERVER DATA ----------
$serverResult = loadServerData();
if (!$serverResult) die("[CRIT] Unable to get data from server. Aborting...\n");
// -------------------------------------

// ---------- GET LOCAL DATA ----------
$localResult = loadLocalData($serverResult);
// ------------------------------------

// ---------- CHECK PARAMETERS ----------
if (count($_SERVER["argv"]) >= 2) {
	if ($_SERVER["argv"][1] === "--force") {
		$localResult["firstRun"] = true;

		if (count($_SERVER["argv"]) == 3) {
			$localResult["forceTransmitter"] = $_SERVER["argv"][2];
		}
	}
}
// --------------------------------------

// ---------- COMPARE SERVER AND LOCAL DATA ----------
compareAndRun($serverResult, $localResult);
// ---------------------------------------------------

// ---------- DO NOT REMOVE ----------
unlink("./.lock");
echo "\n----------\n   DONE   \n----------\n";
// -----------------------------------

// load transmitter data from DAPNET Core via CURL
function loadServerData() {
	$ch = curl_init(DAPNET_URL . '/transmitters');
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		'Authorization: ' . DAPNET_AUTH
	));
	return curl_exec($ch);
}

// load transmitters' "lastUpdate" value from local file
function loadLocalData($data) {
	$outputData = json_decode(@file_get_contents(LOCAL_FILE), true);
	$outputData["firstRun"] = false;
	$outputData["forceTransmitter"] = false;
	if (!$outputData) {
		$outputData = array();

		echo "[WARN] No " . LOCAL_FILE . " found. This seems to be the first run...\n";
		$outputData["firstRun"] = true;

		foreach (json_decode($data, true) as &$transmitter) {
			$outputData[$transmitter["name"]] = NULL;
		}
	}
	return $outputData;
}

// compare server and local data and run the propagation script if needed
function compareAndRun($serverData, $localData) {
	// create output-directory
	if (!is_dir("./CoverageFiles") && !mkdir("./CoverageFiles", 0744)) die("[CRIT] Unable to create output-directory. Aborting...\n");

	// create log-directory
	if (!is_dir("./logs") && !mkdir("./logs", 0744)) die("[CRIT] Unable to create log-directory. Aborting...\n");

	// check every transmitter and process it
	$decodedServerData = json_decode($serverData, true);

	// if forced transmitter: remove all other transmitters
	foreach($decodedServerData as $key => $transmitter) {
		if ($localData["forceTransmitter"] !== false && $transmitter["name"] !== $localData["forceTransmitter"]) {
			unset($decodedServerData[$key]);
		}
	}

	$currentItem = 0;
	foreach ($decodedServerData as &$transmitter) {
		// prepare progress-string
		$currentItem++;
		$progress = str_pad($currentItem, strlen(count($decodedServerData)), "0", STR_PAD_LEFT) . "/" . count($decodedServerData);

		// get lastUpdate string from server
		$dateServer = strtotime($transmitter["lastUpdate"]);

		// if new transmitter: set local time to now to force processing
		if (!array_key_exists($transmitter["name"], $localData)) {
			$dateLocal = strtotime("now");
		} else {
			$dateLocal = strtotime($localData[$transmitter["name"]]);
		}

		// compare dates if not first run
		// on first run: run script on every transmitter
		if (!$localData["firstRun"] && $dateServer === $dateLocal) {
			echo "[INFO] [" . $progress . "] [SKIP] " . $transmitter["name"] . "\n";
		} else {
			// check for invalid power --> skip
			if ($transmitter["power"] == 0) {
				echo "[INFO] [" . $progress . "] [INVA] " . $transmitter["name"] . "\n";
				file_put_contents("./logs/invalid_power.log", $transmitter["name"] . "\n", FILE_APPEND | LOCK_EX);
				continue;
			}

			// check for invalid height --> warn
			if ($transmitter["antennaAboveGroundLevel"] == 0) {
				echo "[INFO] [" . $progress . "] [WARN] " . $transmitter["name"] . "\n";
				file_put_contents("./logs/invalid_height.log", $transmitter["name"] . "\n", FILE_APPEND | LOCK_EX);
			}

			echo "[INFO] [" . $progress . "] [RUN ] " . $transmitter["name"] . "\n";

			// convert power from W to dBm
			$power = 10 * log10(1000 * $transmitter["power"] / 1);

			// calculate range based on transmitter properties
			$rangehelp = ($power + $transmitter["antennaGainDbi"] + DEFAULT_GAIN_RECEIVER - DEFAULT_CABLE_LOSS + 59.6 - 20 * log10(DEFAULT_FREQUENCY)) / 20;
			$range = ceil(pow(10, $rangehelp) * 1000);
			if ($range > MAX_RANGE) $range = MAX_RANGE;

			// build and call the processing script
			$command = "nice -n 19 " .
				PATH_TO_SIMULATION . " " .
				"-D '" . PATH_TO_SIMFILES . "DEM/' " .
				"-T '" . PATH_TO_SIMFILES . "ASTER/' " .
				"-A '" . PATH_TO_SIMFILES . "Antennafiles/' " .
				"-Image '" . PATH_TO_SIMFILES . "CoverageFiles/' " .
				"-n " . $transmitter["name"] . " " .
				"-N " . $transmitter["latitude"] . " " .
				"-O " . $transmitter["longitude"] . " " .
				"-p " . $power . " " .
				"-ht " . $transmitter["antennaAboveGroundLevel"] . " " .
				"-gt " . $transmitter["antennaGainDbi"] . " " .
				"-r " . $range . " " .
				"-R " . DEFAULT_RESOLUTION . " " .
				"-f " . DEFAULT_FREQUENCY . " " .
				"-ant " . DEFAULT_ANTENNA_TYPE . " " .
				"-c " . DEFAULT_CABLE_LOSS . " " .
				"-az " . $transmitter["antennaDirection"] . " " .
				"-al " . DEFAULT_ELEVATION_ANGLE . " " .
				"-hr " . DEFAULT_HEIGHT_RECEIVER . " " .
				"-gr " . DEFAULT_GAIN_RECEIVER . " " .
				"-th " . DEFAULT_THREADS . " " .
				">> ./logs/" . $transmitter["name"] . ".log";

			exec("echo \"" . $command . "\" > ./logs/" . $transmitter["name"] . ".log");
			exec($command);

			// combine images into one
			$imageSize = getimagesize("./CoverageFiles/" . $transmitter["name"] . "_red.png");
			exec("convert -size " . $imageSize[0] . "x" . $imageSize[1]  . " xc:none " .
				"./CoverageFiles/" . $transmitter["name"] . "_red.png -geometry +0+0 -composite " .
				"./CoverageFiles/" . $transmitter["name"] . "_yellow.png -geometry +0+0 -composite " .
				"./CoverageFiles/" . $transmitter["name"] . "_green.png -geometry +0+0 -composite " .
				"./CoverageFiles/" . $transmitter["name"] . ".png");

			// remove red/yellow/green images
			@unlink("./CoverageFiles/" . $transmitter["name"] . "_red.png");
			@unlink("./CoverageFiles/" . $transmitter["name"] . "_yellow.png");
			@unlink("./CoverageFiles/" . $transmitter["name"] . "_green.png");

			// update local_data.json
			$localData[$transmitter["name"]] = $transmitter["lastUpdate"];
			file_put_contents(LOCAL_FILE, json_encode($localData, JSON_PRETTY_PRINT), LOCK_EX);
		}
	}
}