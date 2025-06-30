<pre>
<?php
function getBaseUrlForSpeech(): string {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
            $host = $_SERVER['SERVER_ADDR'];
            $port = $_SERVER['SERVER_PORT'];

            $port = 8081; // Seems this is not being autodetected

            // Check if the port is non-standard for the protocol
            $isDefaultPort = ($protocol === "http://" && $port == 80) || ($protocol === "https://" && $port == 443);

            return $protocol . $host . ($isDefaultPort ? '' : ':' . $port);
        }

echo getBaseUrlForSpeech();
print_r($_SERVER);

?>
