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
 * Fake Codex CLI used by the adapter test suite.
 *
 * Reads the prompt from stdin and emits a synthetic JSONL event stream so
 * the parser and adapter can be exercised offline.
 */

$stdin = stream_get_contents(\STDIN) ?: '';

$events = [
    ['msg' => ['type' => 'session_started', 'session_id' => 'fake-session']],
    ['msg' => ['type' => 'tool_call', 'name' => 'shell', 'arguments' => ['command' => 'ls']]],
    ['msg' => ['type' => 'apply_patch_call', 'name' => 'apply_patch', 'arguments' => []]],
    [
        'msg' => [
            'type' => 'token_count',
            'info' => [
                'total_token_usage' => [
                    'input_tokens' => 800,
                    'output_tokens' => 300,
                    'cached_input_tokens' => 50,
                ],
            ],
            'prompt_bytes' => \strlen($stdin),
        ],
    ],
];

foreach ($events as $event) {
    fwrite(\STDOUT, json_encode($event, \JSON_THROW_ON_ERROR)."\n");
}

exit(0);
