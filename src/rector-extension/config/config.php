<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use MatesOfMate\RectorExtension\Capability\ApplyTool;
use MatesOfMate\RectorExtension\Capability\InspectTool;
use MatesOfMate\RectorExtension\Capability\PreviewTool;
use MatesOfMate\RectorExtension\Discovery\RectorDiscovery;
use MatesOfMate\RectorExtension\Formatter\ToonFormatter;
use MatesOfMate\RectorExtension\Parser\RectorOutputParser;
use MatesOfMate\RectorExtension\Runner\RectorRunner;
use MatesOfMate\RectorExtension\Validation\PathValidator;
use MatesOfMate\RectorExtension\Workflow\RectorWorkflow;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $container->parameters()->set('matesofmate_rector.custom_command', []);

    $services->set(RectorDiscovery::class)
        ->arg('$projectRoot', '%mate.root_dir%')
        ->arg('$customCommand', '%matesofmate_rector.custom_command%');

    $services->set(PathValidator::class)
        ->arg('$projectRoot', '%mate.root_dir%');

    $services->set(RectorRunner::class)
        ->arg('$projectRoot', '%mate.root_dir%');

    $services->set(RectorOutputParser::class);
    $services->set(ToonFormatter::class);
    $services->set(RectorWorkflow::class);

    // Tools - automatically discovered by #[McpTool] attribute
    $services->set(InspectTool::class);
    $services->set(PreviewTool::class);
    $services->set(ApplyTool::class);
};
