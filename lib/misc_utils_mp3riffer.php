<?php 


// This function does not decode mp3 data. Just pack it in a RIFF/WAVE file
function MP3toWav($data, $originalSize) {
  
  // 11labs mp3 defaults
  $channels = 1;
  $sampleRate = 44100;
  $bitrate = 64000;
  $bitsPerSample = ($bitrate / $sampleRate) / $channels;
  
  $fileSize = $originalSize + 96; 
  $outputHandle = fopen('php://memory', 'wb');

  // Write WAV headers
  fwrite($outputHandle, 'RIFF');
  fwrite($outputHandle, pack('V', $fileSize));
  fwrite($outputHandle, 'WAVE');
  fwrite($outputHandle, 'fmt ');
  fwrite($outputHandle, pack('V', 30)); // Size of format chunk
  fwrite($outputHandle, pack('v', 0x0055)); // Format code for non-PCM data (MP3)
  fwrite($outputHandle, pack('v', $channels)); // Number of channels
  fwrite($outputHandle, pack('V', $sampleRate)); // Sample rate
  fwrite($outputHandle, pack('V', $bitrate / 8)); // Byte rate
  fwrite($outputHandle, pack('v', 1152)); // Block align
  fwrite($outputHandle, pack('v', 0)); // Bits per sample

  // Write ExtraParams
  $extraParamSize = 12; // Size of the ExtraParams structure
  $extraParams = pack('vVvvv', 1, 2, 1152, 1, 1393); // Initialize the ExtraParams structure with the provided values
  fwrite($outputHandle, pack('v', $extraParamSize)); // ExtraParamSize field
  fwrite($outputHandle, $extraParams); // ExtraParams structure

  // Write fact chunk
  fwrite($outputHandle, 'fact');
  $factChunk = pack('VV', 4, 42624); // Initialize the fact chunk with the provided values
  fwrite($outputHandle, $factChunk); // fact chunk

  // Write the list chunk
  fwrite($outputHandle, 'LIST');
  $listChunk = pack('V', 26 ); // Initialize the ExtraParams structure with the provided values
  fwrite($outputHandle, $listChunk); // ExtraParams structure
  $infoData=[0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00];
  $infoDataBinary = pack('C*', ...$infoData);

  fwrite($outputHandle, $infoDataBinary); // ExtraParams structure


  // Write the data chunk
  fwrite($outputHandle, 'data');
  fwrite($outputHandle, pack('V', $originalSize)); // Size of data chunk
  fwrite($outputHandle, $data); // MP3 data

  rewind($outputHandle);

  $contents = fread($outputHandle, $originalSize+96+8);

  fclose($outputHandle);

  return $contents;
}

?>
