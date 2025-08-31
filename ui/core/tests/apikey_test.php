<?php
$enginePath = __DIR__ . "/../../../";
require_once($enginePath . "conf/conf.php");

header('Content-Type: text/html; charset=utf-8');

$provider = $_GET['provider'] ?? $_POST['provider'] ?? '';
$apiKey = $_POST['api_key'] ?? '';

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES); }
function clip($s, $len=220){ $s = (string)$s; return (strlen($s) > $len) ? (substr($s,0,$len).'…') : $s; }

echo "<div style='font-family: system-ui, sans-serif; color:#e9efff; padding:14px;'>";
echo "<div style='display:flex; align-items:center; gap:10px; margin-bottom:10px;'>";
echo "<div style='font-weight:600; font-size:18px;'>API Key Test</div>";
echo "<div style='opacity:0.85; font-size:13px;'>".h(strtoupper($provider))."</div>";
echo "</div>";

if ($apiKey === ''){
    echo "<div style='color:#ffb862'>No API key provided.</div>";
    exit;
}

try {
    $ch = curl_init();
    if (!$ch) throw new Exception('Failed to init curl');
    $url = '';
    $headers = [ 'Content-Type: application/json' ];
    $payload = [];
    if ($provider === 'openrouter'){
        $url = 'https://openrouter.ai/api/v1/chat/completions';
        $headers[] = 'Authorization: Bearer ' . $apiKey;
        // Optional hints recommended by OpenRouter (won't break if omitted)
        $headers[] = 'HTTP-Referer: http://localhost';
        $headers[] = 'X-Title: CHIM';
        $payload = [
            'model' => 'openrouter/auto',
            'messages' => [ ['role'=>'user','content'=>'ping'] ],
            'max_tokens' => 1
        ];
    } else if ($provider === 'openai'){
        $url = 'https://api.openai.com/v1/chat/completions';
        $headers[] = 'Authorization: Bearer ' . $apiKey;
        $payload = [
            'model' => 'gpt-4o-mini',
            'messages' => [ ['role'=>'user','content'=>'ping'] ],
            'max_tokens' => 1
        ];
    } else {
        throw new Exception('Unsupported provider');
    }
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);
    $resp = curl_exec($ch);
    if ($resp === false){
        throw new Exception('cURL error: '.curl_error($ch));
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = json_decode($resp, true);
    $ok = ($code >= 200 && $code < 300) && is_array($json) && (isset($json['choices']) || isset($json['id']));

    // Detect quota/credits conditions
    $errMsg = '';
    $isQuota = false;
    if (!$ok) {
        if (is_array($json)){
            if (isset($json['error']) && is_array($json['error'])) {
                $errMsg = $json['error']['message'] ?? ($json['error']['code'] ?? '');
            } else if (isset($json['message'])) {
                $errMsg = $json['message'];
            }
        }
        $msgL = strtolower((string)$errMsg);
        if (in_array($code, [402, 429], true) ||
            strpos($msgL, 'quota') !== false ||
            strpos($msgL, 'credit') !== false ||
            strpos($msgL, 'payment required') !== false ||
            strpos($msgL, 'billing') !== false ||
            strpos($msgL, 'rate limit') !== false) {
            $isQuota = true;
        }
    }

    if ($ok) {
        echo "<div style='display:flex; align-items:center; gap:8px; color:#6dd19c; font-weight:600;'>";
        echo "<span style='font-size:18px;'>✔</span><span>Key is valid</span>";
        echo "</div>";
        echo "<div style='margin-top:6px; font-size:12px; color:#9fb1c9;'>HTTP ".h($code)." • Provider reachable</div>";
    } else if ($isQuota) {
        echo "<div style='display:flex; align-items:center; gap:8px; color:#ffb862; font-weight:600;'>";
        echo "<span style='font-size:18px;'>⚠</span><span>Key valid, but no credits/quota</span>";
        echo "</div>";
        echo "<div style='margin-top:6px; font-size:12px; color:#ffb862;'>HTTP ".h($code)."</div>";
        if ($errMsg !== '') echo "<div style='margin-top:10px; font-size:13px; color:#c9d1e1;'>".h($errMsg)."</div>";
    } else {
        if ($errMsg === '') $errMsg = clip($resp);
        echo "<div style='display:flex; align-items:center; gap:8px; color:#ff6b6b; font-weight:600;'>";
        echo "<span style='font-size:18px;'>✖</span><span>Key failed</span>";
        echo "</div>";
        echo "<div style='margin-top:6px; font-size:12px; color:#ffb862;'>HTTP ".h($code)."</div>";
        if ($errMsg !== '') echo "<div style='margin-top:10px; font-size:13px; color:#c9d1e1;'>".h($errMsg)."</div>";
    }
} catch (Throwable $e){
    echo "<div style='display:flex; align-items:center; gap:8px; color:#ff6b6b; font-weight:600;'>";
    echo "<span style='font-size:18px;'>✖</span><span>Test error</span>";
    echo "</div>";
    echo "<div style='margin-top:6px; font-size:13px; color:#c9d1e1;'>".h($e->getMessage())."</div>";
}

echo "</div>";
?>


