# DAPNET Coverage

## Requirements
* PHP 5.4.0+
* CURL (also for PHP)
* ImageMagick

## Installation
Create a `config.php` and copy the following code into it:
```php
<?php

// ---------- CONFIGURATION ----------
define("DAPNET_URL", "http://hampager.de:8080");
define("DAPNET_AUTH", "Basic >>base64Of(user:passwd)<<");
define("LOCAL_FILE", "./local_data.json");
define("PATH_TO_SIMULATION", "/path/to/hamnet_propagation/HamNet_Simulation");
define("PATH_TO_SIMFILES", "/path/to/dapnet_propagation/");
define("DEFAULT_RESOLUTION", 30);
define("DEFAULT_FREQUENCY", 440);
define("DEFAULT_ANTENNA_TYPE", "Omni");
define("DEFAULT_CABLE_LOSS", 0);
define("DEFAULT_ELEVATION_ANGLE", 0);
define("DEFAULT_HEIGHT_RECEIVER", 3);
define("DEFAULT_GAIN_RECEIVER", 0);
define("DEFAULT_THREADS", 22);
// -----------------------------------
```

Next edit the lines relevant to you (e.g. authentication) and save the file.

## Run
```bash
php run.php
```

### Lockfile
The script creates a `.lock`-file to prevent it from running multiple times.
You may need to remove it manually when you abort the script's execution.