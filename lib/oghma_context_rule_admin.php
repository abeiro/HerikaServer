<?php

// Compatibility no-op for older direct includes of the removed admin handler.
function oghma_context_rule_handle_post($conn, string $schema, array $post, string $method): string
{
    return '';
}
