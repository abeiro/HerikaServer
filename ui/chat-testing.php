<?php

session_start();

// Get the relative web path from document root to our application
$scriptPath = $_SERVER['SCRIPT_NAME'];
$webRoot = dirname(dirname($scriptPath)); // Go up two levels from the script location
if ($webRoot == '/') $webRoot = '';
$webRoot = rtrim($webRoot, '/');

$enginePath =__DIR__.DIRECTORY_SEPARATOR."..".DIRECTORY_SEPARATOR;

require_once($enginePath."conf".DIRECTORY_SEPARATOR."conf.php");
require_once($enginePath."lib".DIRECTORY_SEPARATOR."logger.php");
require_once($enginePath."lib".DIRECTORY_SEPARATOR."{$GLOBALS["DBDRIVER"]}.class.php");

require_once(__DIR__.DIRECTORY_SEPARATOR."profile_loader.php");

$TITLE = "💬 CHIM Chat Testing";

ob_start();

include(__DIR__.DIRECTORY_SEPARATOR."tmpl/head.html");
?>
<link rel="stylesheet" href="<?php echo $webRoot; ?>/ui/css/main.css">
<?php

$debugPaneLink = false;
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/navbar.php");

$db=new sql();
// loads the last ts and game_ts from database so chat-testing is continuing the conversation from skyrim
$lastEventLogRowId=$db->fetchAll("select *  from eventlog order by rowid desc LIMIT 1 OFFSET 0")[0]["rowid"];
$maxTimestamps=$db->fetchAll("select max(gamets)+1 as gamets,max(ts)+1 as ts  from eventlog where rowid={$lastEventLogRowId}");
$ts = $maxTimestamps[0]["ts"]+1;
$gamets = $maxTimestamps[0]["gamets"]+1;

// Extract hash from profile filename if it exists
$hash = '';
if (isset($_SESSION["PROFILE"])) {
    $pattern = '/conf_([a-f0-9]+)\.php/';
    if (preg_match($pattern, basename($_SESSION["PROFILE"]), $matches)) {
        $hash = $matches[1];
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="icon" type="image/x-icon" href="<?php echo $webRoot; ?>/ui/images/favicon.ico">
    <meta charset="utf-8">
    <title>Chat Simulation</title>
    <script>
        // MD5 hasher: https://stackoverflow.com/questions/14733374/how-to-generate-an-md5-hash-from-a-string-in-javascript-node-js
        var MD5 = function(d){var r = M(V(Y(X(d),8*d.length)));return r.toLowerCase()};function M(d){for(var _,m="0123456789ABCDEF",f="",r=0;r<d.length;r++)_=d.charCodeAt(r),f+=m.charAt(_>>>4&15)+m.charAt(15&_);return f}function X(d){for(var _=Array(d.length>>2),m=0;m<_.length;m++)_[m]=0;for(m=0;m<8*d.length;m+=8)_[m>>5]|=(255&d.charCodeAt(m/8))<<m%32;return _}function V(d){for(var _="",m=0;m<32*d.length;m+=8)_+=String.fromCharCode(d[m>>5]>>>m%32&255);return _}function Y(d,_){d[_>>5]|=128<<_%32,d[14+(_+64>>>9<<4)]=_;for(var m=1732584193,f=-271733879,r=-1732584194,i=271733878,n=0;n<d.length;n+=16){var h=m,t=f,g=r,e=i;f=md5_ii(f=md5_ii(f=md5_ii(f=md5_ii(f=md5_hh(f=md5_hh(f=md5_hh(f=md5_hh(f=md5_gg(f=md5_gg(f=md5_gg(f=md5_gg(f=md5_ff(f=md5_ff(f=md5_ff(f=md5_ff(f,r=md5_ff(r,i=md5_ff(i,m=md5_ff(m,f,r,i,d[n+0],7,-680876936),f,r,d[n+1],12,-389564586),m,f,d[n+2],17,606105819),i,m,d[n+3],22,-1044525330),r=md5_ff(r,i=md5_ff(i,m=md5_ff(m,f,r,i,d[n+4],7,-176418897),f,r,d[n+5],12,1200080426),m,f,d[n+6],17,-1473231341),i,m,d[n+7],22,-45705983),r=md5_ff(r,i=md5_ff(i,m=md5_ff(m,f,r,i,d[n+8],7,1770035416),f,r,d[n+9],12,-1958414417),m,f,d[n+10],17,-42063),i,m,d[n+11],22,-1990404162),r=md5_ff(r,i=md5_ff(i,m=md5_ff(m,f,r,i,d[n+12],7,1804603682),f,r,d[n+13],12,-40341101),m,f,d[n+14],17,-1502002290),i,m,d[n+15],22,1236535329),r=md5_gg(r,i=md5_gg(i,m=md5_gg(m,f,r,i,d[n+1],5,-165796510),f,r,d[n+6],9,-1069501632),m,f,d[n+11],14,643717713),i,m,d[n+0],20,-373897302),r=md5_gg(r,i=md5_gg(i,m=md5_gg(m,f,r,i,d[n+5],5,-701558691),f,r,d[n+10],9,38016083),m,f,d[n+15],14,-660478335),i,m,d[n+4],20,-405537848),r=md5_gg(r,i=md5_gg(i,m=md5_gg(m,f,r,i,d[n+9],5,568446438),f,r,d[n+14],9,-1019803690),m,f,d[n+3],14,-187363961),i,m,d[n+8],20,1163531501),r=md5_gg(r,i=md5_gg(i,m=md5_gg(m,f,r,i,d[n+13],5,-1444681467),f,r,d[n+2],9,-51403784),m,f,d[n+7],14,1735328473),i,m,d[n+12],20,-1926607734),r=md5_hh(r,i=md5_hh(i,m=md5_hh(m,f,r,i,d[n+5],4,-378558),f,r,d[n+8],11,-2022574463),m,f,d[n+11],16,1839030562),i,m,d[n+14],23,-35309556),r=md5_hh(r,i=md5_hh(i,m=md5_hh(m,f,r,i,d[n+1],4,-1530992060),f,r,d[n+4],11,1272893353),m,f,d[n+7],16,-155497632),i,m,d[n+10],23,-1094730640),r=md5_hh(r,i=md5_hh(i,m=md5_hh(m,f,r,i,d[n+13],4,681279174),f,r,d[n+0],11,-358537222),m,f,d[n+3],16,-722521979),i,m,d[n+6],23,76029189),r=md5_hh(r,i=md5_hh(i,m=md5_hh(m,f,r,i,d[n+9],4,-640364487),f,r,d[n+12],11,-421815835),m,f,d[n+15],16,530742520),i,m,d[n+2],23,-995338651),r=md5_ii(r,i=md5_ii(i,m=md5_ii(m,f,r,i,d[n+0],6,-198630844),f,r,d[n+7],10,1126891415),m,f,d[n+14],15,-1416354905),i,m,d[n+5],21,-57434055),r=md5_ii(r,i=md5_ii(i,m=md5_ii(m,f,r,i,d[n+12],6,1700485571),f,r,d[n+3],10,-1894986606),m,f,d[n+10],15,-1051523),i,m,d[n+1],21,-2054922799),r=md5_ii(r,i=md5_ii(i,m=md5_ii(m,f,r,i,d[n+8],6,1873313359),f,r,d[n+15],10,-30611744),m,f,d[n+6],15,-1560198380),i,m,d[n+13],21,1309151649),r=md5_ii(r,i=md5_ii(i,m=md5_ii(m,f,r,i,d[n+4],6,-145523070),f,r,d[n+11],10,-1120210379),m,f,d[n+2],15,718787259),i,m,d[n+9],21,-343485551),m=safe_add(m,h),f=safe_add(f,t),r=safe_add(r,g),i=safe_add(i,e)}return Array(m,f,r,i)}function md5_cmn(d,_,m,f,r,i){return safe_add(bit_rol(safe_add(safe_add(_,d),safe_add(f,i)),r),m)}function md5_ff(d,_,m,f,r,i,n){return md5_cmn(_&m|~_&f,d,_,r,i,n)}function md5_gg(d,_,m,f,r,i,n){return md5_cmn(_&f|m&~f,d,_,r,i,n)}function md5_hh(d,_,m,f,r,i,n){return md5_cmn(_^m^f,d,_,r,i,n)}function md5_ii(d,_,m,f,r,i,n){return md5_cmn(m^(_|~f),d,_,r,i,n)}function safe_add(d,_){var m=(65535&d)+(65535&_);return(d>>16)+(_>>16)+(m>>16)<<16|65535&m}function bit_rol(d,_){return d<<_|d>>>32-_}
    </script>
    <script>
        var audioQueue = []; // plays voice lines one after the other
        var loading = false;
        var audioPlaying = false;

        // Add event listener for Enter key
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('inputText').addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && !this.disabled) {
                    e.preventDefault(); // Prevent form submission
                    reqSend();
                }
            });

            request('infonpc', `(beings in range:${document.getElementById('herikaName').value})`);
            request('infonpc_close', `${document.getElementById('herikaName').value}/${document.getElementById('playerName').value}`);

            setInterval(() => {
                let currentlyPlaying = isAudioPlaying();
                if (currentlyPlaying && !audioPlaying) {
                    audioPlaying = true;
                    window.dispatchEvent(new Event('audio-start'))
                }
                if (!currentlyPlaying && audioPlaying) {
                    audioPlaying = false;
                    window.dispatchEvent(new Event('audio-end'))
                }
            }, 100);

            initSTT();
        });

        function setLoadingState(newState) {
            if (newState === loading) {
                return;
            }
            loading = newState;
            window.dispatchEvent(new Event((loading) ? 'loading-start' : 'loading-end'));

            const form = document.getElementById('chatForm');
            const input = document.getElementById('inputText');
            const button = document.getElementById('sendButton');
            
            if (loading) {
                form.classList.add('loading');
                input.disabled = true;
                button.disabled = true;
            } else {
                form.classList.remove('loading');
                input.disabled = false;
                button.disabled = false;
                input.focus(); // Return focus to input
            }
        }

        function doesUrlExist(url) {
            return new Promise((resolve, reject) => {
                const http = new XMLHttpRequest();
                http.open('HEAD', url);
                http.onreadystatechange = function() {
                    if (this.readyState === this.DONE) {
                        if (this.status !== 404) {
                            resolve();
                        } else {
                            reject();
                        }
                    }
                };
                http.send();
            });
        }

        function parseReq(inputString) {
            let lines = inputString.split("\n");
            lines.forEach(function(line) {
                let parts = line.split("|"); // actor|action|responseText
                if (parts.length > 2) {
                    let [actor, action, responseText] = parts;
                    if (actor === 'Player') {
                        return; // The player line has already been printed before the request started
                    }
                    let [text, expression, listener, animation, textPhonetic] = responseText.split('/'); // text/expression/listener/animation/textPhonetic
                    let audioUrl = `../soundcache/${MD5(text)}.wav`;
                    audioQueue.push(audioUrl);
                    let audio = `<audio controls><source src="${audioUrl}" type="audio/wav"></audio>`;
                    let newline = `<p class='llm'>${actor}: ${text}<br/>${audio}</p>`;
                    document.getElementById("chatWindow").innerHTML += newline;
                    // remove audio element when no audio was generated
                    let audioSrcEl = document.querySelector(`#chatWindow audio [src="${audioUrl}"]`);
                    doesUrlExist(audioUrl).catch(() => {
                        audioSrcEl.parentElement.remove();
                    });
                }
            });
            setLoadingState(false);
            document.getElementById('inputText').value = '';
            
            // Scroll chat window to bottom
            const chatWindow = document.getElementById('chatWindow');
            chatWindow.scrollTop = chatWindow.scrollHeight;
        }

        /**
         * Takes first item from audio queue and starts playback.
         * Adds event listener to automatically trigger next voice line when current one finishes.
         */
        function playVoiceLines() {
            let audioSrc = audioQueue.shift();
            if (!audioSrc) {
                return;
            }

            let audioSrcEl = document.querySelector(`#chatWindow audio [src="${audioSrc}"]`);
            let audioEl = audioSrcEl.parentElement;
            audioEl.play();
            audioEl.addEventListener('ended', () => {
                playVoiceLines();
            }, {once: true});
        }
        function isAudioPlaying() {
            for (let audio of document.getElementsByTagName('audio')) {
                if (!audio.paused && !audio.ended && 0 < audio.currentTime) {
                    return true;
                }
            }
            return false;
        }

        /**
         * Send text input to chim for processing.
         */
        function reqSend() {
            const input = document.getElementById('inputText');
            if (!input.value.trim()) return; // Don't send empty messages
            
            setLoadingState(true);
            document.getElementById("chatWindow").innerHTML += 
                "<p class='player'>" + 
                document.getElementById('playerName').value + ': ' + 
                input.value + "</p>";

            let ts = parseInt(document.getElementById('ts').value);
            let gamets = parseInt(document.getElementById('gamets').value);
            var profile = document.getElementById('profile').value;
            var xhr = new XMLHttpRequest();

            let urlDataRaw = `inputtext|${ts}|${gamets}|${document.getElementById('playerName').value}: ${input.value}`;
            var urlData = btoa(urlDataRaw);

            // Clear input immediately after sending
            input.value = '';

            if (profile)
                xhr.open('GET', '/HerikaServer/stream.php?DATA=' + urlData + "&profile=" + profile, true);
            else
                xhr.open('GET', '/HerikaServer/stream.php?DATA=' + urlData, true);

            xhr.onload = function() {
                if (xhr.status >= 200 && xhr.status < 300) {
                    parseReq(xhr.responseText);
                    playVoiceLines();
                } else {
                    console.error('Request failed with status code: ' + xhr.status);
                    setLoadingState(false); // Make sure to re-enable on error
                }
            };
            xhr.onerror = function() {
                console.error('Network error occurred');
                setLoadingState(false); // Make sure to re-enable on error
            };
            xhr.send();
            
            // Scroll chat window to bottom
            const chatWindow = document.getElementById('chatWindow');
            chatWindow.scrollTop = chatWindow.scrollHeight;
        }

        /**
         * Sends info to be stored in event log
         * @param type e.g. infonpc_close
         * @param content e.g. (beings in range:Player)
         */
        function request(type, content) {
            setLoadingState(true);
            let ts = parseInt(document.getElementById('ts').value);
            let gamets = parseInt(document.getElementById('gamets').value);
            let urlData = `${type}|${ts}|${gamets}|${content}`;
            fetch('/HerikaServer/comm.php?DATA=' + btoa(urlData))
                .finally(() => setLoadingState(false));
        }

        // Speech recognition
        function initSTT() {
            if (!"webkitSpeechRecognition" in window) {
                console.warn('STT not available');
                return;
            }
            const inputText = document.getElementById('inputText');
            const originalInputTextPlaceholder = inputText.placeholder;
            const sttButton = document.getElementById('stt');
            const sendButton = document.getElementById('sendButton');
            let listening = false; // if the user wants it listening
            let sttRunning = false; // if the stt is actually running (auto disabled during request & audio play)
            let sendTimeoutTime = 1000; //TODO max wait time for more speech before sending
            let sendTimeout;

            const speechRecognizer = new webkitSpeechRecognition();
            speechRecognizer.continuous = true;
            speechRecognizer.interimResults = true;
            speechRecognizer.lang = 'en-US'; //TODO

            // process voice recognition result and send to server
            speechRecognizer.onresult = function(event) {
                let speech = "";
                for (let result of event.results) {
                    speech += result[0].transcript;
                }
                inputText.value = speech;

                // start/restart wait time for sending speech to llm
                clearTimeout(sendTimeout);
                sendTimeout = setTimeout(() => {
                    sendButton.click();
                }, sendTimeoutTime);
            };

            // update ui and state after listening started
            speechRecognizer.addEventListener('start', (event) => {
                console.debug('speech-recognizer-start');
                sttRunning = true;
                // toggle button
                sttButton.classList.remove('btn-primary');
                sttButton.classList.add('btn-save');
                // input text placeholder
                inputText.placeholder = 'Listening...';

            });
            // update ui and state after listening stopped
            speechRecognizer.addEventListener('end', (event) => {
                console.debug('speech-recognizer-end');
                sttRunning = false;
                // toggle button
                sttButton.classList.remove('btn-save');
                sttButton.classList.add('btn-primary');
                // input text placeholder
                inputText.placeholder = originalInputTextPlaceholder;

                autoStartStopSTT();
            });
            // restart listening when no sound was detected. stop on other errors
            speechRecognizer.onerror = function(event) {
                if (event.error === 'no-speech') {
                    return; // still listening
                } else {
                    console.error("Speech recognition error:", event.error);
                    listening = false;
                }
            };

            // STT button
            sttButton.classList.remove('d-none');
            sttButton.addEventListener('click', (event) => {
                listening = !listening;
                // toggle speech recognition
                if (listening) {
                    speechRecognizer.start();
                } else {
                    speechRecognizer.stop();
                }
            });

            // Start / Stop STT when stuff is loading or audio is playing
            const autoStartStopSTT = function() {
                if (sttRunning && (loading || audioPlaying)) {
                    console.debug('stopping because loading or audio playing');
                    speechRecognizer.stop();
                }
                if (listening && !sttRunning && !loading && !audioPlaying) {
                    console.debug('restarting because loading or audio playing is finished');
                    speechRecognizer.start();
                }
            };
            ['loading-start', 'loading-end', 'audio-start', 'audio-end'].forEach((e) => {
                window.addEventListener(e, autoStartStopSTT);
            });
        }
    </script>
    <style>
        /* Override main container styles */
        main {
            padding-bottom: 40px; /* Reduced space for footer */
            padding-left: 10px;
        }
        
        /* Override footer styles */
        footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            height: 20px; /* Reduced footer height */
            background: #031633;
            z-index: 100;
        }

        /* Loading state styles */
        .loading {
            position: relative;
            opacity: 0.7;
            pointer-events: none;
        }

        .loading::after {
            content: "";
            position: absolute;
            width: 20px;
            height: 20px;
            right: 120px; /* Space for send button */
            top: 50%;
            transform: translateY(-50%);
            border: 3px solid #f3f3f3;
            border-top: 3px solid #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: translateY(-50%) rotate(0deg); }
            100% { transform: translateY(-50%) rotate(360deg); }
        }

        /* Chat window styling */
        #chatWindow {
            height: 500px;
            overflow-y: auto;
            background-color: #3a3a3a;
            border: 1px solid #4a4a4a;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 20px;
            scroll-behavior: smooth;
        }

    
        input[type="text"] {
            background-color: #3a3a3a;
            border: 1px solid #4a4a4a;
            color: #f8f9fa;
            padding: 8px 12px;
            border-radius: 5px;
            font-size: 14px;
        }

        input[type="text"]:disabled {
            background-color: #2c2c2c;
            color: #888;
        }

        .btn-primary:disabled {
            background-color: #2c2c2c;
            cursor: not-allowed;
            opacity: 0.7;
        }

        /* Player and LLM chat text classes */
        p.llm {
            color: #00ff7f;
            margin: 1px;
        }
        p.player {
            color: #00bfff;
            margin: 3px 0;
        }

        /* Voice line audio element */
        p.llm audio {
            transform: scale(0.75);
            margin: -10px 0;
        }

        #inputRow {
            display: flex;
        }
        #inputRow input[type=text] {
            margin: 5px 5px 5px 0;
        }
        #inputRow #sendButton {
            margin-right: 0;
        }
    </style>
</head>
<body>
    <main class="container">
        <h2>💬 CHIM Chat Testing</h2>
        <h3>Current Character: <b><?php echo $GLOBALS["HERIKA_NAME"]; ?></b><h3>
        <p>This chat is missing most of the context and expression of a conversation in skyrim. Do not use this as an indication of quality.</p>
        <div id='chatWindow'></div>

        <form action='index.php' method='post' id="chatForm">
            <p>Player: <b><?php echo $GLOBALS["PLAYER_NAME"]; ?></b></p>
            <div id="inputRow">
                <input type='text' name='inputText' id='inputText' placeholder="Type your message and press Enter or Send"/>

                <input type="button" id='stt' value='🎤' class="btn-primary d-none">

                <input type='hidden' name='localts'   id='localts'   value='<?php echo time(); ?>' />
                <input type='hidden' name='ts'        id='ts'        value='<?php echo $ts; ?>' />
                <input type='hidden' name='gamets'    id='gamets'    value='<?php echo $gamets; ?>' />
                <input type='hidden' name='playerName' id='playerName' value='<?php echo $GLOBALS["PLAYER_NAME"]; ?>' />
                <input type='hidden' name='herikaName' id='herikaName' value='<?php echo $GLOBALS["HERIKA_NAME"]; ?>' />
                <input type='hidden' name='profile'    id='profile'    value='<?php echo $hash; ?>' />
                <input type='hidden' name='conf'       id='profile'    value='<?php echo $_SESSION["PROFILE"]; ?>' />
                <input type='hidden' name='last_gamets' id='last_gamets' value='<?php echo $gamets; ?>' />
                <input type='button' name='send' id='sendButton' value='Send' onclick='reqSend()' class='btn-primary'/>
            </div>
        </form>
    </main>
</body>
<?php
include(__DIR__.DIRECTORY_SEPARATOR."tmpl/footer.html");

$buffer = ob_get_contents();
ob_end_clean();
$title = $TITLE;
$buffer = preg_replace('/(<title>)(.*?)(<\/title>)/i', '$1' . $title . '$3', $buffer);
echo $buffer;
?>
</html>
