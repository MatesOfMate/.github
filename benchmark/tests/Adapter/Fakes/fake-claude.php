<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * Fake Claude Code CLI used by the adapter test suite.
 *
 * Reads the prompt from stdin and emits a synthetic stream-json sequence so
 * the parser and adapter can be exercised offline.
 */

$stdin = stream_get_contents(\STDIN) ?: '';
$mateConfig = null;
foreach ($argv as $arg) {
    if (str_starts_with((string) $arg, '--mcp-config=')) {
        $mateConfig = substr((string) $arg, \strlen('--mcp-config='));
    }
}

$events = [
    [
        'type' => 'system',
        'subtype' => 'init',
        'session_id' => 'fake-session',
    ],
    [
        'type' => 'assistant',
        'message' => [
            'content' => [
                ['type' => 'text', 'text' => 'reading the workspace'],
                ['type' => 'tool_use', 'name' => 'Read', 'input' => ['path' => 'app.php']],
                ['type' => 'tool_use', 'name' => 'Bash', 'input' => ['command' => 'php -v']],
            ],
        ],
    ],
    [
        'type' => 'result',
        'subtype' => 'success',
        'is_error' => false,
        'usage' => [
            'input_tokens' => 1234,
            'output_tokens' => 567,
            'cache_read_input_tokens' => 100,
            'cache_creation_input_tokens' => 50,
        ],
        'prompt_bytes' => \strlen($stdin),
        'mcp_config' => $mateConfig,
    ],
];

foreach ($events as $event) {
    fwrite(\STDOUT, json_encode($event, \JSON_THROW_ON_ERROR)."\n");
}

exit(0);
