<?php

// This file is managed by Mate - use `discover` or `skills:*` commands
// over manual editing. Only changes to `mode` or `enabled` are kept,
// every other key is overwritten by Mate.

return [
    'matesofmate/composer-extension' => [
        'enabled' => true,
        'skills' => [
            'composer-dependency-changes' => [
                'enabled' => true,
                'mode' => 'managed',
                'state' => 'managed',
                'source' => 'vendor/matesofmate/composer-extension/skills/composer-dependency-changes',
                'source_hash' => 'sha256:a606582cc5c83bea9ed812e4ccd3f1f1d34cc275efbbd45130844b60a3f15662',
                'hash' => 'sha256:d5fbd0ff8a3905ad7bdf96ddb0ddc7d2eb18c6759b9d64ed90004da268094fa8',
                'targets' => [
                    '.agents/skills/mate-composer-dependency-changes',
                    '.claude/skills/mate-composer-dependency-changes',
                ],
            ],
            'composer-dependency-conflicts' => [
                'enabled' => true,
                'mode' => 'managed',
                'state' => 'managed',
                'source' => 'vendor/matesofmate/composer-extension/skills/composer-dependency-conflicts',
                'source_hash' => 'sha256:5c04957aa53caf766a4f26ec70aae5f8bebd771cad9ef0439231a0f53599f368',
                'hash' => 'sha256:c192c289d2a9cc739d5bb849fd1c83d1f9c4adb5aa7f2fff76fcb0f7b690154d',
                'targets' => [
                    '.agents/skills/mate-composer-dependency-conflicts',
                    '.claude/skills/mate-composer-dependency-conflicts',
                ],
            ],
        ],
    ],
    'matesofmate/phpstan-extension' => [
        'enabled' => true,
        'skills' => [
            'phpstan-static-analysis' => [
                'enabled' => true,
                'mode' => 'managed',
                'state' => 'managed',
                'source' => 'vendor/matesofmate/phpstan-extension/skills/phpstan-static-analysis',
                'source_hash' => 'sha256:d0e4ca34edcd363c67b8b8e36bbc0f171c08c96a9669febdb06dcda44763d04e',
                'hash' => 'sha256:aecc4c0a5345cc0171ff856e413046dcc071cfe6173729836c58b5237f1134e8',
                'targets' => [
                    '.agents/skills/mate-phpstan-static-analysis',
                    '.claude/skills/mate-phpstan-static-analysis',
                ],
            ],
        ],
    ],
    'matesofmate/phpunit-extension' => [
        'enabled' => true,
        'skills' => [
            'phpunit-test-run' => [
                'enabled' => true,
                'mode' => 'managed',
                'state' => 'managed',
                'source' => 'vendor/matesofmate/phpunit-extension/skills/phpunit-test-run',
                'source_hash' => 'sha256:05ebe56eaed94f4d06e6877d9ee85a8f299f0a2981cb0146125a2c724b28037f',
                'hash' => 'sha256:7d1e2f079406261f5ac46d318b1af068db13d50ed7e4f54c7e7d8a6a71f854fd',
                'targets' => [
                    '.agents/skills/mate-phpunit-test-run',
                    '.claude/skills/mate-phpunit-test-run',
                ],
            ],
        ],
    ],
    'matesofmate/rector-extension' => [
        'enabled' => true,
        'skills' => [
            'rector-refactoring' => [
                'enabled' => true,
                'mode' => 'managed',
                'state' => 'managed',
                'source' => 'vendor/matesofmate/rector-extension/skills/rector-refactoring',
                'source_hash' => 'sha256:e86618bb1bb08e493a9c64486508cacccaf1c928b8f08a187a4eb968e63fbfe3',
                'hash' => 'sha256:878a593593d840b153d0b2d32fc1efbb3185838bf80e0d80b1a0769c4ab18076',
                'targets' => [
                    '.agents/skills/mate-rector-refactoring',
                    '.claude/skills/mate-rector-refactoring',
                ],
            ],
        ],
    ],
    'symfony/ai-mate' => [
        'enabled' => true,
        'skills' => [
            'php-environment-check' => [
                'enabled' => true,
                'mode' => 'managed',
                'state' => 'managed',
                'source' => 'vendor/symfony/ai-mate/skills/php-environment-check',
                'source_hash' => 'sha256:475400c87571d228335fb80f414c56a80110e182268df65f9f84bc9bfb2aa6f3',
                'hash' => 'sha256:56de92962de6233284c439de64b1cef66487c56c07e6c3357658139cf4d82527',
                'targets' => [
                    '.agents/skills/mate-php-environment-check',
                    '.claude/skills/mate-php-environment-check',
                ],
            ],
            'system-information' => [
                'enabled' => true,
                'mode' => 'managed',
                'state' => 'managed',
                'source' => 'vendor/symfony/ai-mate/skills/system-information',
                'source_hash' => 'sha256:7249544b603ecd416fae22cd92b465257619306f88a50dc8efd9653606e9e460',
                'hash' => 'sha256:fcbfe6ea831b35299f5ba646bf63dbd761e184e7ad196087a350a060f9915973',
                'targets' => [
                    '.agents/skills/mate-system-information',
                    '.claude/skills/mate-system-information',
                ],
            ],
        ],
    ],
    'symfony/ai-monolog-mate-extension' => [
        'enabled' => true,
        'skills' => [
            'symfony-log-investigation' => [
                'enabled' => true,
                'mode' => 'managed',
                'state' => 'managed',
                'source' => 'vendor/symfony/ai-monolog-mate-extension/skills/symfony-log-investigation',
                'source_hash' => 'sha256:061adaf34bc73cabd4be47b56e4834b584d5b4848ca09a964d20651b471cd590',
                'hash' => 'sha256:83967b3e29d7f51a55b144db7e6af4f0716fee68379b6d69c62e1669c04aa8a2',
                'targets' => [
                    '.agents/skills/mate-symfony-log-investigation',
                    '.claude/skills/mate-symfony-log-investigation',
                ],
            ],
        ],
    ],
    'symfony/ai-symfony-mate-extension' => [
        'enabled' => true,
        'skills' => [
            'symfony-profiler-debugging' => [
                'enabled' => true,
                'mode' => 'managed',
                'state' => 'managed',
                'source' => 'vendor/symfony/ai-symfony-mate-extension/skills/symfony-profiler-debugging',
                'source_hash' => 'sha256:bf384469e0e92af2b40ede7367090e4a41ff48f75a11f2ac5e64a2e4335068be',
                'hash' => 'sha256:adddef46d3cf8613d2d664f5f214e91572b6ae859406bd4d1d0196c11af1f7cb',
                'targets' => [
                    '.agents/skills/mate-symfony-profiler-debugging',
                    '.claude/skills/mate-symfony-profiler-debugging',
                ],
            ],
            'symfony-request-triage' => [
                'enabled' => true,
                'mode' => 'managed',
                'state' => 'managed',
                'source' => 'vendor/symfony/ai-symfony-mate-extension/skills/symfony-request-triage',
                'source_hash' => 'sha256:3b10b98407be5b6081423aeb8146b16eb81984264e50f2a5a38ac1706cc65581',
                'hash' => 'sha256:5d2c994451acdd9c07b18eba18833a15d7598b7f2cab3b7f8c5346ebc3928f4e',
                'targets' => [
                    '.agents/skills/mate-symfony-request-triage',
                    '.claude/skills/mate-symfony-request-triage',
                ],
            ],
            'symfony-service-inspection' => [
                'enabled' => true,
                'mode' => 'managed',
                'state' => 'managed',
                'source' => 'vendor/symfony/ai-symfony-mate-extension/skills/symfony-service-inspection',
                'source_hash' => 'sha256:de76644032930c9128c13967212e8517eb6d1e42d42985be4a3b6d43b71133b6',
                'hash' => 'sha256:8616e7c88e3414ee3730285f6a0236b0e0c8c7a91608dd285af84af92c443a29',
                'targets' => [
                    '.agents/skills/mate-symfony-service-inspection',
                    '.claude/skills/mate-symfony-service-inspection',
                ],
            ],
        ],
    ],
];
