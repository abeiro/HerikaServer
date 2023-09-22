<?php



function TkGetCostPerThousandInputTokens($CURRENT_MODEL)
{
    $costPerThousandTokens = 0;
    if ($CURRENT_MODEL == 'gpt-3.5-turbo') {
        $costPerThousandTokens = 0.0015;
    } elseif ($CURRENT_MODEL == 'gpt-3.5-turbo-16k') {
        $costPerThousandTokens = 0.003;
    } elseif ($CURRENT_MODEL == 'gpt-3.5-turbo-0613') {
        $costPerThousandTokens = 0.0015;
    } elseif ($CURRENT_MODEL == 'gpt-3.5-turbo-16k-0613') {
        $costPerThousandTokens = 0.003;
    } elseif ($CURRENT_MODEL == 'gpt-4') {
        $costPerThousandTokens = 0.03;
    } elseif ($CURRENT_MODEL == 'gpt-4-0613') {
        $costPerThousandTokens = 0.03;
    } elseif ($CURRENT_MODEL == 'gpt-4-32k') {
        $costPerThousandTokens = 0.06;
    } elseif ($CURRENT_MODEL == 'gpt-4-32k-0613') {
        $costPerThousandTokens = 0.06;
    } else {
        error_log("Cannot tokenize - unrecognized model {$CURRENT_MODEL}");
        $costPerThousandTokens = 0; // model unknown
    }

    return $costPerThousandTokens;
}

function TkGetCostPerThousandOutputTokens($CURRENT_MODEL)
{
    $costPerThousandTokens = 0;
    if ($CURRENT_MODEL == 'gpt-3.5-turbo') {
        $costPerThousandTokens = 0.002;
    } elseif ($CURRENT_MODEL == 'gpt-3.5-turbo-16k') {
        $costPerThousandTokens = 0.004;
    } elseif ($CURRENT_MODEL == 'gpt-3.5-turbo-0613') {
        $costPerThousandTokens = 0.002;
    } elseif ($CURRENT_MODEL == 'gpt-3.5-turbo-16k-0613') {
        $costPerThousandTokens = 0.004;
    } elseif ($CURRENT_MODEL == 'gpt-4') {
        $costPerThousandTokens = 0.06;
    } elseif ($CURRENT_MODEL == 'gpt-4-0613') {
        $costPerThousandTokens = 0.06;
    } elseif ($CURRENT_MODEL == 'gpt-4-32k') {
        $costPerThousandTokens = 0.12;
    } elseif ($CURRENT_MODEL == 'gpt-4-32k-0613') {
        $costPerThousandTokens = 0.12;
    } else {
        error_log("Cannot tokenize - unrecognized model {$CURRENT_MODEL}");
        $costPerThousandTokens = 0; // model unknown
    }

    return $costPerThousandTokens;
}

function TkTokenizePrompt($jsonEncodedData, $CURRENT_MODEL)
{

    global $db;

    $costPerThousandTokens = TkGetCostPerThousandOutputTokens($CURRENT_MODEL);
    // connect to local Python server servicing tokenizing requests
    $tokenizer_url = 'http://172.16.1.128:8090';
    $tokenizer_headers = array(
        'http' => array(
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => $jsonEncodedData,
            'timeout' => 2
        )
    );
    $tokenizer_context = stream_context_create($tokenizer_headers);
    $tokenizer_buffer = file_get_contents($tokenizer_url, false, $tokenizer_context);
    if ($tokenizer_buffer !== false) {
        $tokenizer_buffer = trim($tokenizer_buffer);
        if (ctype_digit($tokenizer_buffer)) { // make sure the response from tokenizer is a number (num of tokens)
            $numTokens = intval($tokenizer_buffer);
            $cost = $numTokens * $costPerThousandTokens * 0.001;
            $db->insert_and_calc_totals(
                'openai_token_count',
                array(
                    'input_tokens' => $tokenizer_buffer,
                    'output_tokens' => '0',
                    'cost_USD' => $cost,
                    'localts' => time(),
                    'datetime' => date("Y-m-d H:i:s"),
                    'model' => $CURRENT_MODEL
                )
            );
        }
    } else {
        error_log("error: tokenizer buf false");
    }


}

function TkTokenizeResponse($numOutputTokens, $CURRENT_MODEL)
{
    global $db;

    if (isset($CURRENT_MODEL)) {
        $costPerThousandTokens = TkGetCostPerThousandOutputTokens($CURRENT_MODEL);
        $cost = $numOutputTokens * $costPerThousandTokens * 0.001;
        $db->insert_and_calc_totals(
            'openai_token_count',
            array(
                'input_tokens' => '0',
                'output_tokens' => $numOutputTokens,
                'cost_USD' => $cost,
                'localts' => time(),
                'datetime' => date("Y-m-d H:i:s"),
                'model' => $CURRENT_MODEL
            )
        );
    }
}
