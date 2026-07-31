<?php

// Compatibility no-op for older integrations that loaded the removed feature.
if (!function_exists('chimOghmaInjectContextRules')) {
    function chimOghmaInjectContextRules($db, $npcMaster = null): int
    {
        return 0;
    }
}
