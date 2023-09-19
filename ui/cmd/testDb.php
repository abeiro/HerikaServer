<?php

require_once("lib/sql.class.php");

try{
				
		$testData=array('ts' => "ts",'gamets' =>"gamets",'type' => "type", 'data' => "data",
											 'sess'=>'pending','localts'=>time());
											 
		$db = new sql();
		$db->insert("eventlog",$testData);
		
		$db->delete("eventlog","localts>0");
		
		
		//	$mysql->raw("delete from eventlog where gamets>{$finalParsedData[2]}");
		
	}catch(Exception $e){
		syslog(LOG_WARNING, $e->getMessage());
	}
	
?>
