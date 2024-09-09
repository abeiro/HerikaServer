<?php

function fuzToWav($fuzFileName)
{
    if (file_exists($fuzFileName)) {
        // Output Folder path
        $sOutputPath = dirname($fuzFileName);

        // Convert the *.fuz file to *.xwm
        $sOutputXwmFile = basename($fuzFileName, '.fuz') . '.wav';

        // Store the new full path for the xwm
        $sOutputXwm = $sOutputPath . DIRECTORY_SEPARATOR . $sOutputXwmFile;

        // Extract the xwm data from the fuz data file
        if (!file_exists($sOutputXwm) || true) {
            // fuz(e) file header format
            // 4 bytes = FUZE magic header
            // 4 bytes = unknown/unused, I suspect some kind of version number.
            // 4 bytes = Lip data size. Can be 0 or larger.
            // lip data (if Lip data size is larger than 0)
            // xwm data

            // Open Fuz file
            $fuzFile = fopen($fuzFileName, 'rb');

            // Get Fuze "Magic" header
            $fuzMagic = fread($fuzFile, 4);

            if ($fuzMagic === 'FUZE') {
                // Skip 4 offset bytes we don't need in the Fuz header
                fseek($fuzFile, 4, SEEK_CUR);

                // Read 4 bytes that contain the lip data size
                $fuzLipSizeData = fread($fuzFile, 4);
                $fuzLipSize = unpack('V', $fuzLipSizeData)[1];

                echo "Generating $sOutputXwmFile...\n";

                // if the Lip data size is larger than 0, the fuz file contains lip data.
                // we skip this data if needed.
                if ($fuzLipSize > 0) {
                    fseek($fuzFile, $fuzLipSize, SEEK_CUR);
                }

                // extract and write the xwm data stream to a temporary file
                $fuzFileSize = filesize($fuzFileName);
                $xwmDataLen = $fuzFileSize - $fuzLipSize - 12;

                $xwmData = fread($fuzFile, $xwmDataLen);

                $tmpXwmFile = tempnam(sys_get_temp_dir(), 'xwm');
                file_put_contents($tmpXwmFile, $xwmData);

                // Use ffmpeg to specify input format and convert the file
                
				$command = "ffmpeg -y -f xwma -i $tmpXwmFile -ar 24000 -ac 1 -sample_fmt s16 $sOutputXwm";

                exec($command, $output, $returnVar);

                if ($returnVar === 0) {
                    echo "Fuze Decode: success, xwm data written.\n";
                } else {
                    echo "Fuze Decode: failed, ffmpeg error.\n";
                    echo implode("\n", $output);
                }

                // Remove the temporary file
                unlink($tmpXwmFile);
            } else {
                echo "Fuze Decode: failed.\n";
                echo "$fuzFileName not a fuze file format!\n";
            }

            // Close file
            if ($fuzFile) {
                fclose($fuzFile);
            }
            return $sOutputXwm;
        } else {
            return "";
        }
        
    }
     return "";
}


function xwmToWav($fuzFileName)
{
    if (file_exists($fuzFileName)) {
        // Output Folder path
        $sOutputPath = dirname($fuzFileName);

        // Convert the *.fuz file to *.xwm
        $sOutputXwmFile = basename($fuzFileName, '.fuz') . '.wav';

        // Store the new full path for the xwm
        $sOutputXwm = $sOutputPath . DIRECTORY_SEPARATOR . $sOutputXwmFile;

        // Extract the xwm data from the fuz data file
        if (!file_exists($sOutputXwm) || true) {
          	$command = "ffmpeg -y -f xwma -i $fuzFileName -ar 24000 -ac 1 -sample_fmt s16 $sOutputXwm";

            exec($command, $output, $returnVar);
            
            return $sOutputXwm;
			
        } else {
            return "";
        }
        
    }
     return "";
}

function wavToWav($fuzFileName)
{
    if (file_exists($fuzFileName)) {
        // Output Folder path
        $sOutputPath = dirname($fuzFileName);

        // Convert the *.fuz file to *.xwm
        $sOutputXwmFile = basename($fuzFileName, '.fuz') . '_.wav';

        // Store the new full path for the xwm
        $sOutputXwm = $sOutputPath . DIRECTORY_SEPARATOR . $sOutputXwmFile;

        // Extract the xwm data from the fuz data file
        if (!file_exists($sOutputXwm) || true) {
          	$command = "ffmpeg -y -f wav -i $fuzFileName -ar 24000 -ac 1 -sample_fmt s16 $sOutputXwm";

            exec($command, $output, $returnVar);
            
            return $sOutputXwm;
			
        } else {
            return "";
        }
        
    }
     return "";
}

// Example usage

?>





