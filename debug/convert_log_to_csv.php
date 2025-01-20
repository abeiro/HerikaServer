<?php

function parse_log_to_csv($log_file, $csv_file) {
    $log_content = file_get_contents($log_file);
    if ($log_content === false) {
        die("Error reading log file: " . $log_file);
    }

    $entries = preg_split("/\n(?=\d{4}-\d{2}-\d{2}T)/", $log_content); // Split by timestamps
    $csv_data = [];

    foreach ($entries as $entry) {
        $lines = explode("\n", trim($entry));
        if (count($lines) > 1 && strpos($lines[1], "=") === 0) { // Check if it's an object entry
            array_shift($lines); // Remove the timestamp line
            array_shift($lines); // Remove the first "=" line

			// remove last "=" line
            if (end($lines) === "=") {
                array_pop($lines);
            }

            $object_string = implode("\n", $lines);

            // Attempt to parse the object.
            try {
                $object = eval("return " . $object_string . ";");
                if (is_array($object)) {
                    $json_string = json_encode($object, JSON_PRETTY_PRINT);
                    if ($json_string === false) {
                        error_log("JSON encoding error for object: " . var_export($object, true));
                        $json_string = "JSON Encoding Error"; // Or handle the error differently
                    }
                    $csv_data[] = ["prompt" => $json_string];
                }
            } catch (ParseError $e) {
                // fail silently to avoid spamming the error log
            } catch (Error $e) {
                // fail silently to avoid spamming the error log
            }

        }
    }

    $fp = fopen($csv_file, 'w');
    if ($fp === false) {
        die("Error opening CSV file for writing: " . $csv_file);
    }

    fputcsv($fp, array_keys($csv_data[0])); // Header row
    foreach ($csv_data as $row) {
        fputcsv($fp, $row);
    }

    fclose($fp);
    echo "Successfully parsed log and created CSV file: " . $csv_file . "\n";
}

// Example usage:
$log_file = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."log".DIRECTORY_SEPARATOR.'context_sent_to_llm.log';
$csv_file = __DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR."log".DIRECTORY_SEPARATOR.'context_sent_to_llm.csv';
parse_log_to_csv($log_file, $csv_file);

?>