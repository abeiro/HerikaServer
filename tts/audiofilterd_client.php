<?php

/**
 * Send audio to the local audiofilterd daemon and write the processed result
 * to disk.
 *
 * @param string $sourceBinaryData       Raw audio bytes (e.g. WAV content).
 * @param array  $filters                Array of filter descriptors. Each
 *                                        element must contain a 'type' key and
 *                                        effect-specific parameters. See
 *                                        SUPPORTED_EFFECTS.md for allowed
 *                                        effect names and parameters.
 * @param string $outputFilenameWithPath Path where the processed audio will
 *                                        be written.
 * @param string $socketPath             Optional Unix socket path for the
 *                                        daemon. Defaults to /tmp/audiofilterd.sock.
 *
 * @return bool True on success.
 *
 * @throws RuntimeException If communication with the daemon fails, the daemon
 *                          reports an error, or the output cannot be written.
 */
function processAudio(
    string $sourceBinaryData,
    array $filters,
    string $outputFilenameWithPath,
    string $socketPath = '/tmp/audiofilterd.sock'
): bool {
    $request = json_encode([
        'audio_base64' => base64_encode($sourceBinaryData),
        'filters' => $filters,
    ], JSON_UNESCAPED_SLASHES);

    if ($request === false) {
        throw new RuntimeException('Failed to encode request JSON');
    }

    $socket = @stream_socket_client('unix://' . $socketPath, $errno, $errstr, 5);
    if ($socket === false) {
        throw new RuntimeException(
            "Failed to connect to daemon: {$errstr} ({$errno})"
        );
    }

    fwrite($socket, $request);
    stream_socket_shutdown($socket, STREAM_SHUT_WR);

    $responseJson = stream_get_contents($socket);
    fclose($socket);

    if ($responseJson === false || $responseJson === '') {
        throw new RuntimeException('Empty response from daemon');
    }

    $response = json_decode($responseJson, true);
    if (!is_array($response)) {
        throw new RuntimeException('Invalid JSON response from daemon');
    }

    $error = $response['error'] ?? ['code' => -1, 'message' => 'missing error field'];
    if (($error['code'] ?? -1) !== 0) {
        throw new RuntimeException(
            "Daemon error {$error['code']}: {$error['message']}"
        );
    }

    $outputBase64 = $response['audio_base64'] ?? null;
    if (!is_string($outputBase64)) {
        throw new RuntimeException('Missing output audio in daemon response');
    }

    $outputAudio = base64_decode($outputBase64, true);
    if ($outputAudio === false) {
        throw new RuntimeException('Failed to decode output audio');
    }

    if (@file_put_contents($outputFilenameWithPath, $outputAudio) === false) {
        throw new RuntimeException(
            "Failed to write output file: {$outputFilenameWithPath}"
        );
    }

    return true;
}

error_log("[AUDIOFILTERD] Loaded audiofilterd_client.php");
// Keep the original command-line behaviour for quick testing.
if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === realpath(__FILE__)) {
    if ($argc < 2) {
        fwrite(STDERR, "Usage: php sample_client.php <input.wav> [output.wav] [trim_ms]\n");
        exit(1);
    }

    $inputPath = $argv[1];
    $outputPath = $argv[2] ?? 'output.wav';
    $trimMs = isset($argv[3]) ? (float)$argv[3] : 250.0;

    if ($trimMs < 0) {
        fwrite(STDERR, "trim_ms must be >= 0\n");
        exit(1);
    }

    $audio = @file_get_contents($inputPath);
    if ($audio === false) {
        fwrite(STDERR, "Failed to read input file: {$inputPath}\n");
        exit(1);
    }

    $filters = [
        [
            'type' => 'trim_start',
            'milliseconds' => $trimMs,
        ],
    ];

    try {
        processAudio($audio, $filters, $outputPath);
        fwrite(STDOUT, "Wrote {$outputPath} using trim_start={$trimMs}ms\n");
    } catch (RuntimeException $e) {
        fwrite(STDERR, $e->getMessage() . "\n");
        exit(1);
    }
}