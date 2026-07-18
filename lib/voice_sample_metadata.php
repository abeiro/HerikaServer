<?php

if (!function_exists('chim_voice_sample_decode_metadata')) {
    function chim_voice_sample_decode_metadata(array $post, array $query): array
    {
        $rawMetadata = trim(strval($post['metadata'] ?? ''));
        if ($rawMetadata !== '') {
            $metadata = json_decode($rawMetadata, true);
            if (!is_array($metadata)) {
                throw new InvalidArgumentException('Invalid voice sample metadata JSON');
            }

            $schema = trim(strval($metadata['schema'] ?? ''));
            if ($schema !== 'chim.voice_sample.v1') {
                throw new InvalidArgumentException('Unsupported voice sample metadata schema');
            }

            $actorName = trim(strval($metadata['actor_name'] ?? ''));
            $sourcePath = trim(strval($metadata['original_name'] ?? ''));
            if ($actorName === '' || $sourcePath === '') {
                throw new InvalidArgumentException('Voice sample metadata requires actor_name and original_name');
            }

            return [
                'actor_name' => $actorName,
                'original_name' => $sourcePath,
                'reference_text' => trim(strval($metadata['reference_text'] ?? '')),
                'game' => trim(strval($metadata['game'] ?? 'skyrim')) ?: 'skyrim',
                'protocol' => 'multipart_json',
            ];
        }

        $actorName = trim(strval($query['codename'] ?? ''));
        $sourcePath = trim(strval($query['oname'] ?? ''));
        if ($actorName === '' || $sourcePath === '') {
            throw new InvalidArgumentException('Missing legacy voice sample metadata');
        }

        return [
            'actor_name' => $actorName,
            'original_name' => $sourcePath,
            'reference_text' => trim(strval($query['reference_text'] ?? '')),
            'game' => 'skyrim',
            'protocol' => 'legacy_query',
        ];
    }
}

if (!function_exists('chim_voice_sample_metadata_path')) {
    function chim_voice_sample_metadata_path(string $wavPath): string
    {
        return preg_replace('/\.wav$/i', '.json', $wavPath) ?: ($wavPath . '.json');
    }
}

if (!function_exists('chim_voice_sample_build_metadata')) {
    function chim_voice_sample_build_metadata(string $wavPath, string $voiceId, array $metadata): array
    {
        if (!is_file($wavPath)) {
            throw new InvalidArgumentException('Voice sample WAV not found');
        }

        return [
            'schema' => 'chim.voice_sample.metadata.v1',
            'voice_id' => $voiceId,
            'actor_name' => strval($metadata['actor_name'] ?? ''),
            'source' => strval($metadata['original_name'] ?? ''),
            'reference_text' => strval($metadata['reference_text'] ?? ''),
            'game' => strval($metadata['game'] ?? 'skyrim') ?: 'skyrim',
            'sha256' => hash_file('sha256', $wavPath) ?: '',
            'bytes' => intval(filesize($wavPath)),
            'updated_at' => gmdate('c'),
        ];
    }
}

if (!function_exists('chim_voice_sample_replace_file')) {
    function chim_voice_sample_replace_file(string $source, string $target): bool
    {
        $targetDirectory = dirname($target);
        if (!is_dir($targetDirectory) && !@mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
            return false;
        }

        try {
            $suffix = bin2hex(random_bytes(6));
        } catch (Throwable $_e) {
            $suffix = str_replace('.', '', uniqid('', true));
        }
        $temporary = $target . '.tmp.' . $suffix;
        if (!@copy($source, $temporary) || !is_file($temporary) || filesize($temporary) <= 44) {
            @unlink($temporary);
            return false;
        }

        if (@rename($temporary, $target)) {
            return true;
        }
        if (is_file($target) && !@unlink($target)) {
            @unlink($temporary);
            return false;
        }

        $replaced = @rename($temporary, $target);
        if (!$replaced) {
            @unlink($temporary);
        }
        return $replaced;
    }
}

if (!function_exists('chim_voice_sample_write_metadata')) {
    function chim_voice_sample_write_metadata(string $wavPath, string $voiceId, array $metadata): bool
    {
        $payload = chim_voice_sample_build_metadata($wavPath, $voiceId, $metadata);
        $json = json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        if (!is_string($json)) {
            return false;
        }

        $target = chim_voice_sample_metadata_path($wavPath);
        try {
            $suffix = bin2hex(random_bytes(6));
        } catch (Throwable $_e) {
            $suffix = str_replace('.', '', uniqid('', true));
        }
        $temporary = $target . '.tmp.' . $suffix;
        if (@file_put_contents($temporary, $json . PHP_EOL, LOCK_EX) === false) {
            @unlink($temporary);
            return false;
        }

        if (@rename($temporary, $target)) {
            return true;
        }
        if (is_file($target) && !@unlink($target)) {
            @unlink($temporary);
            return false;
        }

        $written = @rename($temporary, $target);
        if (!$written) {
            @unlink($temporary);
        }
        return $written;
    }
}

if (!function_exists('chim_voice_sample_read_metadata')) {
    function chim_voice_sample_read_metadata(string $voiceId, string $voicesDirectory): array
    {
        $normalizedVoiceId = basename(str_replace('\\', '/', trim($voiceId)));
        if ($normalizedVoiceId === '' || $normalizedVoiceId !== trim($voiceId)) {
            return [];
        }

        $path = rtrim($voicesDirectory, '/\\') . DIRECTORY_SEPARATOR . $normalizedVoiceId . '.json';
        $decoded = is_file($path) ? json_decode(strval(@file_get_contents($path)), true) : [];
        if (!is_array($decoded) || ($decoded['schema'] ?? '') !== 'chim.voice_sample.metadata.v1') {
            return [];
        }
        return $decoded;
    }
}
