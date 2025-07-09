<pre>
<?php
function getBaseUrlForSpeech(): string {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
            $host = $_SERVER['SERVER_ADDR'];
            $port = $_SERVER['SERVER_PORT'];


            return $protocol . $host .  ':' . $port;
        }

echo getBaseUrlForSpeech().PHP_EOL;
print_r($_SERVER);

?>
