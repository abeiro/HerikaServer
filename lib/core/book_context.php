<?php

if (!function_exists('chimBookNormalizeText')) {
    function chimBookNormalizeText($value): string
    {
        $text = html_entity_decode(strip_tags((string)$value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\0", '', $text);
        $text = preg_replace("/\r\n?|\x{2028}|\x{2029}/u", "\n", $text) ?? $text;
        return trim($text);
    }
}

if (!function_exists('chimBookXmlEscape')) {
    function chimBookXmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}

if (!function_exists('chimBookFindLatestOpenedTitle')) {
    function chimBookFindLatestOpenedTitle($db, int $requestGamets): array
    {
        $timeFilter = $requestGamets > 0 ? "AND gamets <= {$requestGamets}" : '';
        $row = $db->fetchOne("
            SELECT rowid, title, gamets, ts
              FROM public.books
             WHERE title IS NOT NULL
               AND BTRIM(title) <> ''
               {$timeFilter}
             ORDER BY gamets DESC, ts DESC NULLS LAST, localts DESC, rowid DESC
             LIMIT 1
        ");

        if (empty($row)) {
            return [];
        }

        $title = chimBookNormalizeText($row['title'] ?? '');
        return $title === '' ? [] : [
            'rowid' => (int)($row['rowid'] ?? 0),
            'title' => $title,
            'gamets' => (int)($row['gamets'] ?? 0),
            'ts' => (int)($row['ts'] ?? 0),
        ];
    }
}

if (!function_exists('chimBookFindCompleteByTitle')) {
    function chimBookFindCompleteByTitle($db, string $title, int $requestGamets): array
    {
        $title = chimBookNormalizeText($title);
        if ($title === '') {
            return [];
        }

        $titleSql = $db->escape($title);
        $row = $db->fetchOne("
            SELECT rowid, title, content, gamets, ts
              FROM public.books
             WHERE LOWER(BTRIM(title)) = LOWER(BTRIM('{$titleSql}'))
               AND content IS NOT NULL
               AND BTRIM(content) <> ''
             ORDER BY gamets DESC, ts DESC NULLS LAST, localts DESC, rowid DESC
             LIMIT 1
        ");

        if (empty($row)) {
            return [];
        }

        $content = chimBookNormalizeText($row['content'] ?? '');
        if ($content === '') {
            return [];
        }

        return [
            'rowid' => (int)($row['rowid'] ?? 0),
            'title' => chimBookNormalizeText($row['title'] ?? $title),
            'content' => $content,
            'gamets' => (int)($row['gamets'] ?? 0),
            'ts' => (int)($row['ts'] ?? 0),
            'available' => true,
        ];
    }
}

if (!function_exists('chimBookFindLatestComplete')) {
    function chimBookFindLatestComplete($db, int $requestGamets): array
    {
        $timeFilter = $requestGamets > 0 ? "AND gamets <= {$requestGamets}" : '';
        $row = $db->fetchOne("
            SELECT rowid, title, content, gamets, ts
              FROM public.books
             WHERE title IS NOT NULL
               AND BTRIM(title) <> ''
               AND content IS NOT NULL
               AND BTRIM(content) <> ''
               {$timeFilter}
             ORDER BY gamets DESC, ts DESC NULLS LAST, localts DESC, rowid DESC
             LIMIT 1
        ");

        if (empty($row)) {
            return [];
        }

        $title = chimBookNormalizeText($row['title'] ?? '');
        $content = chimBookNormalizeText($row['content'] ?? '');
        if ($title === '' || $content === '') {
            return [];
        }

        return [
            'rowid' => (int)($row['rowid'] ?? 0),
            'title' => $title,
            'content' => $content,
            'gamets' => (int)($row['gamets'] ?? 0),
            'ts' => (int)($row['ts'] ?? 0),
            'available' => true,
        ];
    }
}

if (!function_exists('chimResolveGroundedBook')) {
    function chimResolveGroundedBook($db, int $requestGamets, int $waitMilliseconds = 0, ?callable $sleep = null): array
    {
        $opened = chimBookFindLatestOpenedTitle($db, $requestGamets);
        if (empty($opened)) {
            $fallback = chimBookFindLatestComplete($db, $requestGamets);
            return !empty($fallback) ? $fallback : ['available' => false, 'title' => 'the open book'];
        }

        $deadline = microtime(true) + (max(0, $waitMilliseconds) / 1000);
        do {
            $complete = chimBookFindCompleteByTitle($db, $opened['title'], $requestGamets);
            if (!empty($complete)) {
                return $complete;
            }

            if (microtime(true) >= $deadline) {
                break;
            }

            if ($sleep !== null) {
                $sleep(100000);
            } else {
                usleep(100000);
            }
        } while (true);

        return [
            'available' => false,
            'title' => $opened['title'],
            'gamets' => $opened['gamets'],
            'ts' => $opened['ts'],
        ];
    }
}

if (!function_exists('chimBuildGroundedBookContext')) {
    function chimBuildGroundedBookContext(array $book): array
    {
        $title = chimBookNormalizeText($book['title'] ?? 'the open book');
        if ($title === '') {
            $title = 'the open book';
        }

        if (empty($book['available']) || chimBookNormalizeText($book['content'] ?? '') === '') {
            $content = "<book_being_read>\n"
                . '<title>' . chimBookXmlEscape($title) . "</title>\n"
                . "<captured_text available=\"false\" />\n"
                . "<grounding_rules>The book text is not available. Do not invent its contents, plot, author, claims, or quotations. State that you cannot read the text yet.</grounding_rules>\n"
                . '</book_being_read>';
            return [['role' => 'user', 'content' => $content]];
        }

        $bookText = chimBookNormalizeText($book['content']);
        $content = "<book_being_read>\n"
            . '<title>' . chimBookXmlEscape($title) . "</title>\n"
            . '<captured_text>' . chimBookXmlEscape($bookText) . "</captured_text>\n"
            . "<grounding_rules>Base the summary and discussion only on the captured text above. Clearly distinguish direct content from your interpretation. Do not invent missing passages, authorship, lore, quotations, or conclusions.</grounding_rules>\n"
            . '</book_being_read>';

        return [['role' => 'user', 'content' => $content]];
    }
}
