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
                'source_hash' => 'sha256:74b0a0aa44f5a2d742ac3afa6404135243735c60101b3e90d9b4d5c136539d0d',
                'hash' => 'sha256:f4cd7855bf57839ac489698c8d44e2636ad5ecb3296c3b5ecd2d3b3637b27b4e',
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
                'source_hash' => 'sha256:2d9660b78bbe1bba10ed5440652db7358a5c5d2bfeecabea64e96cedb51d3855',
                'hash' => 'sha256:02784906ebc956d1f650d59275b8ee60ebe59f37d0265fa3aeda850d143e1171',
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
                'source_hash' => 'sha256:bab5873472d28c2db9cf21a4859fcdeccf64a976e78de87727ee969811349859',
                'hash' => 'sha256:adf79054c1b988179714277697ad2a51d422e64969eb4a5e4cd98bdb15eda0eb',
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
