<?php

function conf_loader_load() {
	$localPath=__DIR__.DIRECTORY_SEPARATOR;
	$rootPath=__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

	$confSchema=json_decode(file_get_contents($localPath."conf_schema.json"),true);

	$langFolders=scandir($rootPath."lang".DIRECTORY_SEPARATOR);
	foreach ($langFolders as $n=>$folder)
		if (!is_dir($rootPath."lang".DIRECTORY_SEPARATOR.$folder))
			unset($langFolders[$n]);
		else if (strpos($folder,".")===0)
			unset($langFolders[$n]);

	$langFolders[]="";	// Default empty always
	
	$confSchema["CORE_LANG"]["values"]=array_values($langFolders);
	
	foreach ($confSchema as $name=>$definition) {
		if (isset($definition["type"])) {
			$definition["currentValue"]=$GLOBALS[$name];
			$confMap[$name]=$definition;
		}
		else {
			if (is_array($definition)) {
				foreach ($definition as $name2=>$definition2) {
					if (isset($definition2["type"])) {
						$definition2["currentValue"]=$GLOBALS[$name][$name2];
						$confMap["$name $name2"]=$definition2;
					}
					else if (is_array($definition2)) {
						foreach ($definition2 as $name3=>$definition3) {
							if (isset($definition3["type"])) {
								$definition3["currentValue"]=$GLOBALS[$name][$name2][$name3];
								$confMap["$name $name2 $name3"]=$definition3;
							}
						
						}
					}
						
				}
			}
			
		}
	}

	return $confMap;
}


function conf_loader_load_titles() {
	$localPath=__DIR__.DIRECTORY_SEPARATOR;
	$rootPath=__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

	$confSchema=json_decode(file_get_contents($localPath."conf_schema.json"),true);

	foreach ($confSchema as $name=>$definition) {
		if (isset($definition["_title"])) {
			$titleMap[$name]=$definition["_title"];
		}
		
		if (is_array($definition)) {
			foreach ($definition as $name2=>$definition2) {
				if (isset($definition2["_title"])) {
					$titleMap["$name $name2"]=$definition2["_title"];
				}
				if (is_array($definition2)) {
					foreach ($definition2 as $name3=>$definition3) {
						if (isset($definition3["_title"])) {
							$titleMap["$name $name2 $name3"]=$definition3["_title"];
						}
					
					}
				}
			}
		}
	}

	return $titleMap;
}

function conf_loader_load_schema() {
	$localPath=__DIR__.DIRECTORY_SEPARATOR;
	$rootPath=__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

	$confSchema=json_decode(file_get_contents($localPath."conf_schema.json"),true);

	foreach ($confSchema as $name=>$definition) {
		if (isset($definition["type"])) {
			$confMap[$name]=$definition;
		}
		else {
			if (is_array($definition)) {
				foreach ($definition as $name2=>$definition2) {
					if (isset($definition2["type"])) {
						$confMap["$name $name2"]=$definition2;
					}
					else if (is_array($definition2)) {
						foreach ($definition2 as $name3=>$definition3) {
							if (isset($definition3["type"])) {
								$confMap["$name $name2 $name3"]=$definition3;
							}
						
						}
					}
						
				}
			}
			
		}
	}

	return $confMap;
}

function conf_loader_list_api_keys() {
	$localPath=__DIR__.DIRECTORY_SEPARATOR;
	$rootPath=__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

	$confS=json_decode(file_get_contents($localPath."apikey.json"),true);

	return array_keys[$confS];
}


function _ak($code) {
	$localPath=__DIR__.DIRECTORY_SEPARATOR;
	$rootPath=__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

	$confS=json_decode(file_get_contents($localPath."apikey.json"),true);

	return $confS[$code];
}

?>
