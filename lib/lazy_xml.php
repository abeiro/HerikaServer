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
    $content = html_entity_decode($content, ENT_QUOTES | ENT_XML1, 'UTF-8');

    return $content === '' ? null : $content;
}
