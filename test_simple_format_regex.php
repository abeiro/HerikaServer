<?php
/**
 * Test script to verify simple format regex handles all combinations
 */

require_once(__DIR__ . "/connector/openrouterjsoncached_helpers.php");

$testCases = [
    // Single element tests - with opening paren
    [
        'buffer' => '(lovely) This is the message',
        'includeMood' => true, 'includeListener' => false, 'includeActions' => false, 'includeTarget' => false,
        'expected' => ['found' => true, 'mood' => 'lovely', 'message' => 'This is the message'],
        'name' => 'Mood only - full format (lovely)'
    ],
    [
        'buffer' => '(lovely): This is the message',
        'includeMood' => true, 'includeListener' => false, 'includeActions' => false, 'includeTarget' => false,
        'expected' => ['found' => true, 'mood' => 'lovely', 'message' => 'This is the message'],
        'name' => 'Mood only - full format with colon (lovely):'
    ],

    // Single element tests - WITHOUT opening paren (prefill case)
    [
        'buffer' => 'lovely) This is the message',
        'includeMood' => true, 'includeListener' => false, 'includeActions' => false, 'includeTarget' => false,
        'expected' => ['found' => true, 'mood' => 'lovely', 'message' => 'This is the message'],
        'name' => 'Mood only - prefill format lovely)'
    ],
    [
        'buffer' => 'lovely): This is the message',
        'includeMood' => true, 'includeListener' => false, 'includeActions' => false, 'includeTarget' => false,
        'expected' => ['found' => true, 'mood' => 'lovely', 'message' => 'This is the message'],
        'name' => 'Mood only - prefill format with colon lovely):'
    ],

    // Two element tests - full format
    [
        'buffer' => '(lovely)(Player) This is the message',
        'includeMood' => true, 'includeListener' => true, 'includeActions' => false, 'includeTarget' => false,
        'expected' => ['found' => true, 'mood' => 'lovely', 'listener' => 'Player', 'message' => 'This is the message'],
        'name' => 'Mood+Listener - full format (lovely)(Player)'
    ],
    [
        'buffer' => '(lovely)(Player): This is the message',
        'includeMood' => true, 'includeListener' => true, 'includeActions' => false, 'includeTarget' => false,
        'expected' => ['found' => true, 'mood' => 'lovely', 'listener' => 'Player', 'message' => 'This is the message'],
        'name' => 'Mood+Listener - full format with colon (lovely)(Player):'
    ],

    // Two element tests - prefill format (first paren missing)
    [
        'buffer' => 'lovely)(Player) This is the message',
        'includeMood' => true, 'includeListener' => true, 'includeActions' => false, 'includeTarget' => false,
        'expected' => ['found' => true, 'mood' => 'lovely', 'listener' => 'Player', 'message' => 'This is the message'],
        'name' => 'Mood+Listener - prefill format lovely)(Player)'
    ],
    [
        'buffer' => 'lovely)(Player): This is the message',
        'includeMood' => true, 'includeListener' => true, 'includeActions' => false, 'includeTarget' => false,
        'expected' => ['found' => true, 'mood' => 'lovely', 'listener' => 'Player', 'message' => 'This is the message'],
        'name' => 'Mood+Listener - prefill format with colon lovely)(Player):'
    ],

    // Three element tests - full format
    [
        'buffer' => '(lovely)(Player)(Talk) This is the message',
        'includeMood' => true, 'includeListener' => true, 'includeActions' => true, 'includeTarget' => false,
        'expected' => ['found' => true, 'mood' => 'lovely', 'listener' => 'Player', 'action' => 'Talk', 'message' => 'This is the message'],
        'name' => 'Mood+Listener+Action - full format'
    ],

    // Three element tests - prefill format
    [
        'buffer' => 'lovely)(Player)(Talk) This is the message',
        'includeMood' => true, 'includeListener' => true, 'includeActions' => true, 'includeTarget' => false,
        'expected' => ['found' => true, 'mood' => 'lovely', 'listener' => 'Player', 'action' => 'Talk', 'message' => 'This is the message'],
        'name' => 'Mood+Listener+Action - prefill format'
    ],

    // Four element tests - full format
    [
        'buffer' => '(lovely)(Player)(Talk)(Lydia) This is the message',
        'includeMood' => true, 'includeListener' => true, 'includeActions' => true, 'includeTarget' => true,
        'expected' => ['found' => true, 'mood' => 'lovely', 'listener' => 'Player', 'action' => 'Talk', 'target' => 'Lydia', 'message' => 'This is the message'],
        'name' => 'All elements - full format'
    ],

    // Four element tests - prefill format
    [
        'buffer' => 'lovely)(Player)(Talk)(Lydia) This is the message',
        'includeMood' => true, 'includeListener' => true, 'includeActions' => true, 'includeTarget' => true,
        'expected' => ['found' => true, 'mood' => 'lovely', 'listener' => 'Player', 'action' => 'Talk', 'target' => 'Lydia', 'message' => 'This is the message'],
        'name' => 'All elements - prefill format'
    ],

    // Only listener
    [
        'buffer' => '(Player) This is the message',
        'includeMood' => false, 'includeListener' => true, 'includeActions' => false, 'includeTarget' => false,
        'expected' => ['found' => true, 'listener' => 'Player', 'message' => 'This is the message'],
        'name' => 'Listener only - full format'
    ],
    [
        'buffer' => 'Player) This is the message',
        'includeMood' => false, 'includeListener' => true, 'includeActions' => false, 'includeTarget' => false,
        'expected' => ['found' => true, 'listener' => 'Player', 'message' => 'This is the message'],
        'name' => 'Listener only - prefill format'
    ],

    // Only action
    [
        'buffer' => '(Talk) This is the message',
        'includeMood' => false, 'includeListener' => false, 'includeActions' => true, 'includeTarget' => false,
        'expected' => ['found' => true, 'action' => 'Talk', 'message' => 'This is the message'],
        'name' => 'Action only - full format'
    ],
    [
        'buffer' => 'Talk) This is the message',
        'includeMood' => false, 'includeListener' => false, 'includeActions' => true, 'includeTarget' => false,
        'expected' => ['found' => true, 'action' => 'Talk', 'message' => 'This is the message'],
        'name' => 'Action only - prefill format'
    ],

    // Real-world example from user
    [
        'buffer' => 'lovely) : pauses in her careful arrangement of supplies',
        'includeMood' => true, 'includeListener' => false, 'includeActions' => false, 'includeTarget' => false,
        'expected' => ['found' => true, 'mood' => 'lovely', 'message' => 'pauses in her careful arrangement of supplies'],
        'name' => 'Real-world: lovely) : pauses...'
    ],
];

echo "=== SIMPLE FORMAT REGEX TEST SUITE ===\n\n";

$passed = 0;
$failed = 0;

foreach ($testCases as $test) {
    $result = extractSimpleFormatFromBuffer(
        $test['buffer'],
        $test['includeMood'],
        $test['includeListener'],
        $test['includeActions'],
        $test['includeTarget']
    );

    $testPassed = true;
    $errors = [];

    // Check if found status matches
    if ($result['found'] !== $test['expected']['found']) {
        $testPassed = false;
        $errors[] = "  Expected found={$test['expected']['found']}, got found={$result['found']}";
    }

    // Check each field if found is true
    if ($test['expected']['found'] && $result['found']) {
        foreach (['mood', 'listener', 'action', 'target', 'message'] as $field) {
            if (isset($test['expected'][$field])) {
                if ($result[$field] !== $test['expected'][$field]) {
                    $testPassed = false;
                    $errors[] = "  Expected $field='{$test['expected'][$field]}', got '$field='{$result[$field]}'";
                }
            }
        }
    }

    if ($testPassed) {
        echo "✓ PASS: {$test['name']}\n";
        $passed++;
    } else {
        echo "✗ FAIL: {$test['name']}\n";
        echo "  Buffer: {$test['buffer']}\n";
        foreach ($errors as $error) {
            echo "$error\n";
        }
        $failed++;
    }
}

echo "\n=== RESULTS ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Total:  " . ($passed + $failed) . "\n";

if ($failed === 0) {
    echo "\n✓ ALL TESTS PASSED!\n";
    exit(0);
} else {
    echo "\n✗ SOME TESTS FAILED!\n";
    exit(1);
}
