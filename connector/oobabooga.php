<?php


/* oobabooga/text-generation-webui
 *
 * Documentation:   https://github.com/oobabooga/text-generation-webui/blob/main/api-examples/api-example-chat-stream.py
 * Code credits to:      https://github.com/paragi/PHP-websocket-client/blob/master/src/Client.php
 *
 */

class connector
{
    public $primary_handler;
    public $name;

    private $mustClose;
    private $internalBuffer;

    public function __construct()
    {
        $this->name="oobabooga";
        $this->mustClose=false;

    }


    private function stub_openssl_random_pseudo_bytes($length)
    {
        // For testing or non-security-critical purposes, generate pseudo-random bytes.
        $bytes = '';
        for ($i = 0; $i < $length; $i++) {
            $bytes .= chr(mt_rand(0, 255));
        }

        // Set $cryptoStrong to false since this is not cryptographically secure.

        return $bytes;
    }

    private function write($data, bool $final = true, bool $binary = true)
    {
        // Assemble header: FINal 0x80 | Mode (0x02 binary, 0x01 text)
        if ($binary) {
            $header = chr(($final ? 0x80 : 0) | 0x02); // 0x02 binary mode
        } else {
            $header = chr(($final ? 0x80 : 0) | 0x01); // 0x01 text mode
        }

        /* Mask 0x80 | payload length (0-125) */
        if (strlen($data) < 126) {
            $header .= chr(0x80 | strlen($data));
        } elseif (strlen($data) < 0xFFFF) {
            $header .= chr(0x80 | 126) . pack("n", strlen($data));
        } else {
            $header .= chr(0x80 | 127) . pack("N", 0) . pack("N", strlen($data));
        }

        // Add mask
        $mask = pack("N", rand(1, 0x7FFFFFFF));
        $header .= $mask;

        // Mask application data.
        for ($i = 0; $i < strlen($data); $i++) {
            $data[$i] = chr(ord($data[$i]) ^ ord($mask[$i % 4]));
        }

        $written = fwrite($this->primary_handler, $header . $data);
        if (false === $written) {
            die('Unable to write to websocket');
        }

        return $written;
    }


    private function _close()
    {

        $closeFrame = "\x88\x80\x00\x00\x00\x00";
        fwrite($this->primary_handler, $closeFrame);
        fclose($this->primary_handler);
    }

    private function read(&$error_string = null)
    {

        $data="";

        $header = fread($this->primary_handler, 2);
        if (!$header) {
            $error_string = "Reading header from websocket failed.";
            die($error_string);
        }

        $opcode = ord($header[0]) & 0x0F;
        $final = ord($header[0]) & 0x80;
        $masked = ord($header[1]) & 0x80;
        $payload_len = ord($header[1]) & 0x7F;

        // Get payload length extensions
        $ext_len = 0;
        if ($payload_len >= 0x7E) {
            $ext_len = 2;
            if ($payload_len == 0x7F) {
                $ext_len = 8;
            }
            $header = fread($this->primary_handler, $ext_len);
            if (!$header) {
                $error_string = "Reading header extension from websocket failed.";
                die($error_string);
            }

            // Set extented paylod length
            $payload_len = 0;
            for ($i = 0; $i < $ext_len; $i++) {
                $payload_len += ord($header[$i]) << ($ext_len - $i - 1) * 8;
            }
        }

        // Get Mask key
        if ($masked) {
            $mask = fread($this->primary_handler, 4);
            if (!$mask) {
                $error_string = "Reading header mask from websocket failed.";
                die($error_string);
            }
        }

        // Get payload
        $frame_data = '';
        while ($payload_len > 0) {
            $frame = fread($this->primary_handler, $payload_len);
            if (!$frame) {
                $error_string = "Reading payload from websocket failed.";
                throw new ConnectionException($error_string);
            }
            $payload_len -= strlen($frame);
            $frame_data .= $frame;
        }

        // Handle ping requests (sort of) send pong and continue to read
        if ($opcode == 9) {
            // Assamble header: FINal 0x80 | Opcode 0x0A + Mask on 0x80 with zero payload
            fwrite($this->primary_handler, chr(0x8A) . chr(0x80) . pack("N", rand(1, 0x7FFFFFFF)));


            // Close
        } elseif ($opcode == 8) {
            $this->mustClose=true;

            // 0 = continuation frame, 1 = text frame, 2 = binary frame
        } elseif ($opcode < 3) {
            // Unmask data
            $data_len = strlen($frame_data);
            if ($masked) {
                for ($i = 0; $i < $data_len; $i++) {
                    $data .= $frame_data[$i] ^ $mask[$i % 4];
                }
            } else {
                $data .= $frame_data;
            }
        } else {

        }

        return $data;
    }

    public function open($contextData, $customParms)
    {
        $path='/api/extra/generate/stream/';

        $context="";
        $history=[];

        foreach ($contextData as $s_role=>$s_msg) {	// Have to mangle context format

            if (empty(trim($s_msg["content"]))) {
                continue;
            } else {
                // This should be customizable per model
                /*
                if ($s_msg["role"]=="user")
                    $normalizedContext[]="### Instruction: ".$s_msg["content"];
                else if ($s_msg["role"]=="assistant")
                    $normalizedContext[]="### Response: ".$s_msg["content"];
                else if ($s_msg["role"]=="system")
                    $normalizedContext[]=$s_msg["content"];
                */
                $normalizedContext[]=$s_msg["content"];
            }
        }

        foreach ($normalizedContext as $n=>$s_msg) {
            if ($n==(sizeof($normalizedContext)-1)) {
                //$context.="### Instruction: \n".$s_msg.". Write a single reply only.";
                $chat_instruct_command="### Instruction: \n".$s_msg.". Write a single reply only.";
                $GLOBALS["DEBUG_DATA"][]="### Instruction: ".$s_msg."";

            } else {
                $s_msg_p = preg_replace('/^(The Narrator:)(.*)/m', '[Author\'s notes: $2 ]', $s_msg);
                $history[]="$s_msg_p\n";
                $context.="$s_msg_p\n";
                $GLOBALS["DEBUG_DATA"][]=$s_msg_p;
            }

        }

        $chat_instruct_command.="\n### Response:\n";
        $GLOBALS["DEBUG_DATA"][]="\n### Response:";


        $GLOBALS["DEBUG_DATA"]["prompt"]=$context;

        $TEMPERATURE=((isset($GLOBALS["CONNECTOR"][$this->name]["temperature"]) ? $GLOBALS["CONNECTOR"][$this->name]["temperature"] : 0.9)+0);
        $REP_PEN=((isset($GLOBALS["CONNECTOR"][$this->name]["rep_pen"]) ? $GLOBALS["CONNECTOR"][$this->name]["rep_pen"] : 1.12)+0);
        $TOP_P=((isset($GLOBALS["CONNECTOR"][$this->name]["top_p"]) ? $GLOBALS["CONNECTOR"][$this->name]["top_p"] : 0.9)+0);

        $MAX_TOKENS=((isset($GLOBALS["CONNECTOR"][$this->name]["max_tokens"]) ? $GLOBALS["CONNECTOR"][$this->name]["max_tokens"] : 48)+0);

        $stop_sequence=["{$GLOBALS["PLAYER_NAME"]}:","\n{$GLOBALS["PLAYER_NAME"]} ","Author\'s notes","\n"];

        $data = [
            'user_input' => $context.$chat_instruct_command,
            'max_new_tokens' => $MAX_TOKENS,
            'auto_max_new_tokens' => false,
            'max_tokens_second' => 0,
            //'history' => ['internal'=>$history, 'visible'=>$history] ,
            'mode' => 'chat', // Valid options: 'chat', 'chat-instruct', 'instruct'
            //'character' => $GLOBALS["HERIKA_NAME"],
            'instruction_template' => 'Alpaca', // Will get autodetected if unset
            'your_name' => $GLOBALS["PLAYER_NAME"],
            // 'name1' => 'name of user', // Optional
            // 'name2' => 'name of character', // Optional
            //'context' => $context, // Optional
            // 'greeting' => 'greeting', // Optional
            // 'name1_instruct' => 'You', // Optional
            // 'name2_instruct' => 'Assistant', // Optional
            // 'context_instruct' => 'context_instruct', // Optional
            // 'turn_template' => 'turn_template', // Optional
            'regenerate' => false,
            '_continue' => false,
            //'chat_instruct_command' => $chat_instruct_command,
            // Generation params. If 'preset' is set to different than 'None', the values
            // in presets/preset-name.yaml are used instead of the individual numbers.
            'preset' => 'None',
            'do_sample' => true,
            'temperature' => $TEMPERATURE,
            'top_p' => 0.1,
            'typical_p' => 1,
            'epsilon_cutoff' => 0, // In units of 1e-4
            'eta_cutoff' => 0, // In units of 1e-4
            'tfs' => 1,
            'top_a' => 0,
            'repetition_penalty' => $REP_PEN,
            'repetition_penalty_range' => 0,
            'top_k' => 40,
            'min_length' => 0,
            'no_repeat_ngram_size' => 0,
            'num_beams' => 1,
            'penalty_alpha' => 0,
            'length_penalty' => 1,
            'early_stopping' => false,
            'mirostat_mode' => 0,
            'mirostat_tau' => 5,
            'mirostat_eta' => 0.1,
            'guidance_scale' => 1,
            'negative_prompt' => '',
            'seed' => -1,
            'add_bos_token' => true,
            'truncation_length' => 2048,
            'ban_eos_token' => false,
            'custom_token_bans' => '',
            'skip_special_tokens' => true,
            'stopping_strings' => $stop_sequence
        ];
        //}

        if (isset($GLOBALS["FORCE_MAX_TOKENS"])) {
            if ($GLOBALS["FORCE_MAX_TOKENS"]==null) {
                unset($data["max_new_tokens"]);
            } elseif ($customParms["MAX_TOKENS"]) {
                $data["max_new_tokens"]=$GLOBALS["FORCE_MAX_TOKENS"];

            }
        }

        if (isset($customParms["MAX_TOKENS"])) {
            if ($customParms["MAX_TOKENS"]==null) {
                unset($data["max_new_tokens"]);
            } elseif ($customParms["MAX_TOKENS"]) {
                $data["max_new_tokens"]=$customParms["MAX_TOKENS"];
            }
        }



        // ws://0.0.0.0:5005/api/v1/stream

        $host=$GLOBALS["CONNECTOR"][$this->name]["HOST"];
        $port=$GLOBALS["CONNECTOR"][$this->name]["PORT"];
        $path="/api/v1/chat-stream";
        // Open a TCP connection
        $this->primary_handler = fsockopen('tcp://' . $host, $port, $errno, $errstr, 30);

        $flags = STREAM_CLIENT_CONNECT;
        $ctx = stream_context_create();


        // the '@' to silent the Warning Error. Don't get mad, errors are handled below with Exception
        $this->primary_handler = stream_socket_client("tcp://$host:$port", $errno, $errstr, $GLOBALS["HTTP_TIMEOUT"], $flags, $ctx);
        $key = base64_encode($this->stub_openssl_random_pseudo_bytes(16));


        $header = "GET " . $path . " HTTP/1.1\r\n"
           . "Host: $host\r\n"
           . "pragma: no-cache\r\n"
           . "User-Agent: paragi/php-websocket-client-xmodified\r\n"
           . "Upgrade: WebSocket\r\n"
           . "Connection: Upgrade\r\n"
           . "Sec-WebSocket-Key: $key\r\n"
           . "Sec-WebSocket-Version: 13\r\n";

        // Add extra headers
        if (!empty($headers)) {
            foreach ($headers as $h) {
                $header .= $h . "\r\n";
            }
        }

        // Add end of header marker
        $header .= "\r\n";

        $rc = fwrite($this->primary_handler, $header);
        if (!$rc) {
            $error_string = "Unable to send upgrade header to websocket server: $errstr ($errno)";
            die($error_string);
        }

        // Read response into an assotiative array of headers. Fails if upgrade failes.
        $reaponse_header = fread($this->primary_handler, 1024);

        // status code 101 indicates that the WebSocket handshake has completed.
        if (stripos($reaponse_header, ' 101 ') === false || stripos($reaponse_header, 'Sec-WebSocket-Accept: ') === false) {
            $error_string = "Server did not accept to upgrade connection to websocket."
                . $reaponse_header . E_USER_ERROR;
            die($error_string);
        }

        // Send the HTTP request
        if ($this->primary_handler !== false) {
            $jData = json_encode($data);
            $number_bytes_sent = $this->write($jData);


        } else {
            return false;
        }


        return true;

    }


    public function process()
    {
        global $alreadysent;

        $line = $this->read();
        $buffer="";
        $totalBuffer="";

        file_put_contents(__DIR__."/../log/debugStream.log", $line.PHP_EOL, FILE_APPEND);
        $data=json_decode($line, true);

        if (isset($data["history"]["visible"][0])) {
            $textData=html_entity_decode(($data["history"]["visible"][0][1]), ENT_QUOTES, 'UTF-8');
            if (!empty($this->internalBuffer)) {
                $buffer=str_replace($this->internalBuffer, "", $textData);
            } else {
                $buffer=$textData;
            }

            $this->internalBuffer=$textData;
        }


        if ($data["event"]=="stream_end") {
            $this->mustClose=true;
        }


        return $buffer;
    }


    public function close()
    {

        $this->_close();
    }

    public function isDone()
    {
        return $this->mustClose;
    }

    public function processActions()
    {
        return [];
    }


}
