<?php

// Administrative web-only endpoint. Do not expose merge writes through the wildcard-CORS game API.
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, ['GET', 'POST'], true)) {
        http_response_code(405);
        throw new InvalidArgumentException('Unsupported request method');
    }
    if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') {
        http_response_code(403);
        throw new InvalidArgumentException('Open NPC Manager on this server to merge profiles');
    }
    if ($method === 'POST') {
        if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 4096) {
            throw new InvalidArgumentException('Request too large');
        }
        $input = json_decode(file_get_contents('php://input', false, null, 0, 4097), true);
        if (!is_array($input) || empty($_SESSION['npc_merge_csrf']) ||
            !hash_equals($_SESSION['npc_merge_csrf'], (string)($input['csrf_token'] ?? ''))) {
            http_response_code(403);
            throw new InvalidArgumentException('Reload NPC Manager before merging profiles');
        }
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $expectedOrigin = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://')
            . ($_SERVER['HTTP_HOST'] ?? '');
        if ($origin !== '' && $origin !== $expectedOrigin) {
            http_response_code(403);
            throw new InvalidArgumentException('Cross-origin profile changes are not allowed');
        }
    }
    require_once dirname(__DIR__) . '/profile_loader.php';
    require_once dirname(__DIR__, 2) . '/lib/core/npc_master.class.php';
    require_once dirname(__DIR__, 2) . '/lib/' . $GLOBALS['DBDRIVER'] . '.class.php';
    $db = new sql();
    $manager = new NpcMaster();
    $result = ['status' => 'success'];
    if ($method === 'GET') {
        $row = $manager->getActorById((int)($_GET['id'] ?? 0));
        if (!$row) { throw new InvalidArgumentException('NPC profile not found'); }
        $_SESSION['npc_merge_csrf'] ??= bin2hex(random_bytes(32));
        $name = $db->escape(trim($row['npc_name']));
        $candidates = $db->fetchAll("SELECT * FROM core_npc_master c WHERE lower(btrim(npc_name)) = lower('{$name}')
            AND profile_owner_npc_id IS NULL AND COALESCE(refid, '') ~* '^[0-9a-f]{8}$'
            AND upper(refid) NOT LIKE 'FF%' AND COALESCE(metadata->>'refid_source', '') <> ''
            AND NOT EXISTS (SELECT 1 FROM core_npc_master child WHERE child.profile_owner_npc_id = c.id)
            AND id <> " . (int)$row['id'] . ' ORDER BY id LIMIT 200');
        $result += [
            'csrf_token' => $_SESSION['npc_merge_csrf'],
            'current' => chimNpcProfileIdentity($row),
            'candidates' => array_map('chimNpcProfileIdentity', $candidates),
            'sharing' => chimNpcProfileSharing($row),
            'revision' => chimNpcProfileRevision(chimNpcProfileMembers($row)),
        ];
    } else {
        switch ($input['action'] ?? '') {
            case 'preview':
                $ids = $input['ids'] ?? [];
                if (!is_array($ids) || count($ids) !== 2) { throw new InvalidArgumentException('Choose two profiles'); }
                $rows = chimNpcProfileMergePair((int)$ids[0], (int)$ids[1]);
                $result['revision'] = chimNpcProfileRevision($rows);
                $result['profiles'] = array_map(static function ($row) {
                    $extended = chimNpcProfileJson($row['extended_data'] ?? null);
                    $memory = $extended['middle_term_memory'] ?? [];
                    return chimNpcProfileIdentity($row) + [
                        'fields' => array_intersect_key($row, array_flip(CHIM_SHARED_NPC_FIELDS)),
                        'memory' => is_array($memory) && $memory ? (string)end($memory) : '',
                        'relationships' => $extended['relationships'] ?? (object)[],
                    ];
                }, $rows);
                break;
            case 'merge':
                chimNpcMergeProfiles((int)($input['owner_id'] ?? 0), (int)($input['other_id'] ?? 0), (string)($input['revision'] ?? ''));
                break;
            case 'unlink':
                chimNpcUnlinkProfiles((int)($input['id'] ?? 0), (string)($input['revision'] ?? ''));
                break;
            default:
                throw new InvalidArgumentException('Unsupported profile operation');
        }
    }
    echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (UnexpectedValueException $error) {
    http_response_code(409);
    echo json_encode(['status' => 'error', 'message' => $error->getMessage()]);
} catch (InvalidArgumentException $error) {
    if (http_response_code() < 400) { http_response_code(422); }
    echo json_encode(['status' => 'error', 'message' => $error->getMessage()]);
} catch (Throwable $error) {
    error_log('[NPC MERGE] ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Unable to change shared profiles']);
}
