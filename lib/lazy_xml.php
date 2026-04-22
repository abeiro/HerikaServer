<?php

/**
 * Fast, non-strict XML fragment parser for two tags: <action> and <reason> and <notification>
 * - Tries SimpleXML with a temporary root (fast & safe if possible)
 * - Falls back to a very lightweight substring-based extractor if SimpleXML fails
 */

/***************** */

function parse_xml_fragment(string $fragment): array
{
    // Try SimpleXML first by wrapping the fragment in a root element
    $wrapped = "<root>$fragment</root>";
    libxml_use_internal_errors(true);
    $sxml = simplexml_load_string($wrapped, 'SimpleXMLElement', LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NOCDATA);
    if ($sxml !== false) {
        // Convert to string and decode entities
        $action       = isset($sxml->action) ? trim((string) $sxml->action) : null;
        $reason       = isset($sxml->reason) ? trim((string) $sxml->reason) : null;
        $notification = isset($sxml->reason) ? trim((string) $sxml->notification) : null;
        // Decode common HTML entities
        $action       = $action !== null ? html_entity_decode($action, ENT_QUOTES | ENT_XML1, 'UTF-8') : null;
        $reason       = $reason !== null ? html_entity_decode($reason, ENT_QUOTES | ENT_XML1, 'UTF-8') : null;
        $notification = $reason !== null ? html_entity_decode($notification, ENT_QUOTES | ENT_XML1, 'UTF-8') : null;
        return [
            'action'       => $action,
            'reason'       => $reason,
            'notification' => $notification,
        ];
    }

    // Fallback: manual extraction tolerant to fragments / small malformations
    return manual_extract_tags($fragment, ['action', 'reason', 'notification']);
}

function parse_xml_fragment_rumors(string $fragment): array
{
    // Try SimpleXML first by wrapping the fragment in a root element
    $wrapped = "<root>$fragment</root>";
    libxml_use_internal_errors(true);
    $sxml = simplexml_load_string($wrapped, 'SimpleXMLElement', LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NOCDATA);
    if ($sxml !== false) {
        // Convert to string and decode entities
        $type     = isset($sxml->type) ? trim((string) $sxml->type) : null;
        $location = isset($sxml->location) ? trim((string) $sxml->location) : null;
        $content  = isset($sxml->content) ? trim((string) $sxml->content) : null;
        // Decode common HTML entities
        $type     = $type !== null ? html_entity_decode($type, ENT_QUOTES | ENT_XML1, 'UTF-8') : null;
        $location = $location !== null ? html_entity_decode($location, ENT_QUOTES | ENT_XML1, 'UTF-8') : null;
        $content  = $content !== null ? html_entity_decode($content, ENT_QUOTES | ENT_XML1, 'UTF-8') : null;
        return [
            'type'     => $type,
            'location' => $location,
            'content'  => $content,
        ];
    }

    // Fallback: manual extraction tolerant to fragments / small malformations
    return manual_extract_tags($fragment, ['type', 'location', 'content']);
}

function xml_fragment_escape_text($value): string
{
    return htmlspecialchars(trim((string) $value), ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function build_rumor_prompt_xml(array $rumors, int $maxRumors = 3): string
{
    $blocks = [];
    $seen = [];

    foreach ($rumors as $rumor) {
        $content = trim((string) ($rumor['content'] ?? ''));
        if ($content === '') {
            continue;
        }

        $type = trim((string) ($rumor['type'] ?? 'General'));
        if ($type === '') {
            $type = 'General';
        }

        $location = trim((string) ($rumor['hold'] ?? ($rumor['location'] ?? 'Skyrim')));
        if ($location === '') {
            $location = 'Skyrim';
        }

        $dedupeKey = strtolower(
            preg_replace('/\s+/u', ' ', $type . '|' . $location . '|' . $content)
        );

        if (isset($seen[$dedupeKey])) {
            continue;
        }
        $seen[$dedupeKey] = true;

        $blocks[] = "\n<rumor>\n"
            . "<type>" . xml_fragment_escape_text($type) . "</type>\n"
            . "<location>" . xml_fragment_escape_text($location) . "</location>\n"
            . "<content>" . xml_fragment_escape_text($content) . "</content>\n"
            . "</rumor>";

        if (count($blocks) >= $maxRumors) {
            break;
        }
    }

    return implode('', $blocks);
}

function manual_extract_tags(string $text, array $tags): array
{
    $result = [];
    foreach ($tags as $tag) {
        $result[$tag] = manual_get_tag_content($text, $tag);
    }
    return $result;
}

function manual_get_tag_content(string $text, string $tag): ?string
{
    // Case-insensitive search for opening tag (allows attributes like <tag attr="...">)
    $openPos = stripos($text, "<$tag");
    if ($openPos === false) {
        return null;
    }

    // Find '>' that closes the opening tag (skip attributes)
    $gtPos = strpos($text, '>', $openPos);
    if ($gtPos === false) {
        return null;
    }

    $startContent = $gtPos + 1;

    // Look for CDATA first
    $cdataStart = substr($text, $startContent, 9) === '<![CDATA[';
    if ($cdataStart) {
        $cdataOpen  = strpos($text, '<![CDATA[', $startContent - 1);
        $cdataClose = strpos($text, ']]>', $cdataOpen);
        if ($cdataClose !== false) {
            $content = substr($text, $cdataOpen + 9, $cdataClose - ($cdataOpen + 9));
            return trim(html_entity_decode($content, ENT_QUOTES | ENT_XML1, 'UTF-8'));
        }
        // if no closing CDATA, fall through to general extraction
    }

    // Find closing tag
    $closeTag = "</$tag>";
    $closePos = stripos($text, $closeTag, $startContent);
    if ($closePos === false) {
        // Maybe tag wasn't closed properly — try to take until next tag or end of string
        // Find next '<' after startContent
        $nextLt = strpos($text, '<', $startContent);
        if ($nextLt === false) {
            $content = substr($text, $startContent);
        } else {
            $content = substr($text, $startContent, $nextLt - $startContent);
        }
    } else {
        $content = substr($text, $startContent, $closePos - $startContent);
    }

    // Trim and decode entities
    $content = trim($content);
    $content = strip_tags($content);

    return $content === '' ? null : $content;
}


/**
 * Extract all occurrences of a tag's content (case-insensitive).
 * Returns an array of decoded, trimmed strings.
 */
function manual_get_all_tag_contents(string $text, string $tag): array
{
    $results = [];
    $offset = 0;
    $lower = strtolower($text);
    $ltag = "<" . strtolower($tag);
    while (true) {
        $pos = stripos($lower, $ltag, $offset);
        if ($pos === false) break;

        // find '>' closing this open tag
        $gt = strpos($text, '>', $pos);
        if ($gt === false) break;
        $start = $gt + 1;

        // find closing tag from start
        $close = stripos($lower, "</" . strtolower($tag) . ">", $start);
        if ($close === false) {
            // take until next '<' or end
            $nextLt = strpos($text, '<', $start);
            $content = $nextLt === false ? substr($text, $start) : substr($text, $start, $nextLt - $start);
            $offset = $start + strlen($content);
        } else {
            $content = substr($text, $start, $close - $start);
            $offset = $close + strlen($tag) + 3; // move past </tag>
        }

        $content = trim(html_entity_decode($content, ENT_QUOTES | ENT_XML1, 'UTF-8'));
        if ($content !== '') $results[] = $content;
    }

    return $results;
}


/**
 * Parse a <character_sheet> fragment and return the main sections as well-formatted plain text.
 * Returns an associative array with keys for the main sections (core, npc_static_bio, personality,
 * appearance, relationships, occupation, skills, speechstyle, goals). Values are strings or null.
 */
function parse_character_sheet_fragment(string $fragment): array
{
    $wrapped = "<root>$fragment</root>";
    libxml_use_internal_errors(true);
    $sxml = simplexml_load_string($wrapped, 'SimpleXMLElement', LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NOCDATA);

    $out = [
        'core' => null,
        'npc_static_bio' => null,
        'personality' => null,
        'appearance' => null,
        'relationships' => null,
        'occupation' => null,
        'skills' => null,
        'speechstyle' => null,
        'goals' => null,
    ];

    if ($sxml !== false && isset($sxml->character_sheet)) {
        $cs = $sxml->character_sheet;

        // core
        if (isset($cs->core)) {
            $lines = [];
            foreach (['name', 'race', 'gender', 'remarkable_job'] as $k) {
                if (isset($cs->core->$k) && strlen(trim((string)$cs->core->$k))>0) {
                    $label = ucfirst(str_replace('_', ' ', $k));
                    $lines[] = "$label: " . trim((string)$cs->core->$k);
                }
            }
            if (!empty($lines)) $out['core'] = implode("\n", $lines);
        }

        // static bio
        if (isset($cs->npc_static_bio)) {
            $parts = [];
            if (isset($cs->npc_static_bio->summary) && strlen(trim((string)$cs->npc_static_bio->summary))>0) {
                $parts[] = trim((string)$cs->npc_static_bio->summary);
            }
            if (isset($cs->npc_static_bio->bio) && strlen(trim((string)$cs->npc_static_bio->bio))>0) {
                $parts[] = trim((string)$cs->npc_static_bio->bio);
            }
            if (!empty($parts)) $out['npc_static_bio'] = implode("\n\n", $parts);
        }

        // personality
        if (isset($cs->personality)) {
            $parts = [];
            // traits
            $traits = [];
            if (isset($cs->personality->traits->trait)) {
                foreach ($cs->personality->traits->trait as $t) $traits[] = trim((string)$t);
            }
            if (!empty($traits)) $parts[] = "Traits: " . implode(", ", $traits);

            if (isset($cs->personality->traumas) && strlen(trim((string)$cs->personality->traumas))>0) {
                $parts[] = "Traumas: " . trim((string)$cs->personality->traumas);
            }

            $likes = [];
            if (isset($cs->personality->likes->like)) {
                foreach ($cs->personality->likes->like as $l) $likes[] = trim((string)$l);
            }
            if (!empty($likes)) $parts[] = "Likes: " . implode(", ", $likes);

            if (!empty($parts)) $out['personality'] = implode("\n", $parts);
        }

        // appearance
        if (isset($cs->appearance)) {
            if (isset($cs->appearance->description) && strlen(trim((string)$cs->appearance->description))>0) {
                $out['appearance'] = trim((string)$cs->appearance->description);
            }
        }

        // relationships
        if (isset($cs->relationships)) {
            $rels = [];
            if (isset($cs->relationships->relationship)) {
                foreach ($cs->relationships->relationship as $r) {
                    $who = isset($r->character) ? trim((string)$r->character) : null;
                    $rel = isset($r->relation) ? trim((string)$r->relation) : null;
                    if ($who !== null && $rel !== null) $rels[] = "- $who: $rel";
                }
            }
            if (!empty($rels)) $out['relationships'] = implode("\n", $rels);
        }

        // occupation
        if (isset($cs->occupation)) {
            $lines = [];
            if (isset($cs->occupation->main_occupation) && strlen(trim((string)$cs->occupation->main_occupation))>0) {
                $lines[] = "Main occupation: " . trim((string)$cs->occupation->main_occupation);
            }
            if (isset($cs->occupation->role) && strlen(trim((string)$cs->occupation->role))>0) {
                $lines[] = "Role: " . trim((string)$cs->occupation->role);
            }
            if (!empty($lines)) $out['occupation'] = implode("\n", $lines);
        }

        // skills
        if (isset($cs->skills)) {
            $s = [];
            if (isset($cs->skills->skill)) {
                foreach ($cs->skills->skill as $sk) $s[] = trim((string)$sk);
            }
            if (!empty($s)) $out['skills'] = "- " . implode("\n- ", $s);
        }

        // speechstyle
        if (isset($cs->speechstyle)) {
            if (isset($cs->speechstyle->style) && strlen(trim((string)$cs->speechstyle->style))>0) {
                $out['speechstyle'] = trim((string)$cs->speechstyle->style);
            }
        }

        // goals
        if (isset($cs->goals)) {
            $g = [];
            if (isset($cs->goals->goal)) {
                foreach ($cs->goals->goal as $gl) $g[] = trim((string)$gl);
            }
            if (!empty($g)) $out['goals'] = "- " . implode("\n- ", $g);
        }

        return $out;
    }

    // Fallback: use manual extractors and convert subitems to plain text
    $sections = [
        'core' => ['name','race','gender','remarkable_job'],
        'npc_static_bio' => ['summary','bio'],
        'personality' => ['traits','traumas','likes'],
        'appearance' => ['description'],
        'relationships' => ['relationship'],
        'occupation' => ['main_occupation','role'],
        'skills' => ['skill'],
        'speechstyle' => ['style'],
        'goals' => ['goal'],
    ];

    foreach ($sections as $sec => $subtags) {
        $inner = manual_get_tag_content($fragment, $sec);
        if ($inner === null) continue;

        // handle each section specifically
        if ($sec === 'core') {
            $lines = [];
            foreach ($subtags as $k) {
                $v = manual_get_tag_content($inner, $k);
                if ($v !== null) $lines[] = ucfirst(str_replace('_',' ',$k)) . ": $v";
            }
            if (!empty($lines)) $out[$sec] = implode("\n", $lines);
            continue;
        }

        if ($sec === 'npc_static_bio') {
            $parts = [];
            foreach ($subtags as $k) {
                $v = manual_get_tag_content($inner, $k);
                if ($v !== null) $parts[] = $v;
            }
            if (!empty($parts)) $out[$sec] = implode("\n\n", $parts);
            continue;
        }

        if ($sec === 'personality') {
            $parts = [];
            $traits = manual_get_all_tag_contents($inner, 'trait');
            if (!empty($traits)) $parts[] = 'Traits: ' . implode(', ', $traits);
            $traumas = manual_get_tag_content($inner, 'traumas');
            if ($traumas !== null) $parts[] = 'Traumas: ' . $traumas;
            $likes = manual_get_all_tag_contents($inner, 'like');
            if (!empty($likes)) $parts[] = 'Likes: ' . implode(', ', $likes);
            if (!empty($parts)) $out[$sec] = implode("\n", $parts);
            continue;
        }

        if ($sec === 'relationships') {
            $rels = manual_get_all_tag_contents($inner, 'relationship');
            $lines = [];
            foreach ($rels as $rxml) {
                $who = manual_get_tag_content($rxml, 'character');
                $rel = manual_get_tag_content($rxml, 'relation');
                if ($who !== null && $rel !== null) $lines[] = "- $who: $rel";
            }
            if (!empty($lines)) $out[$sec] = implode("\n", $lines);
            continue;
        }

        if ($sec === 'skills') {
            $items = manual_get_all_tag_contents($inner, 'skill');
            if (!empty($items)) $out[$sec] = "- " . implode("\n- ", $items);
            continue;
        }

        if ($sec === 'goals') {
            $items = manual_get_all_tag_contents($inner, 'goal');
            if (!empty($items)) $out[$sec] = "- " . implode("\n- ", $items);
            continue;
        }

        // generic handlers
        if ($sec === 'appearance') {
            $v = manual_get_tag_content($inner, 'description');
            if ($v !== null) $out[$sec] = $v;
            continue;
        }

        if ($sec === 'occupation') {
            $lines = [];
            foreach ($subtags as $k) {
                $v = manual_get_tag_content($inner, $k);
                if ($v !== null) $lines[] = ucfirst(str_replace('_',' ',$k)) . ": $v";
            }
            if (!empty($lines)) $out[$sec] = implode("\n", $lines);
            continue;
        }

        if ($sec === 'speechstyle') {
            $v = manual_get_tag_content($inner, 'style');
            if ($v !== null) $out[$sec] = $v;
            continue;
        }
    }

    return $out;
}
