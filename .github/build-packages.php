#!/usr/bin/env php
<?php

/*
 * Prepares monorepo for local development by adding path repositories.
 * Based on Symfony AI build-packages.php
 */

require __DIR__.'/../vendor/autoload.php';

use Symfony\Component\Finder\Finder;

$monorepoRoot = dirname(__DIR__);

// Discover all packages
$packages = [];
$finder = new Finder();
$finder->files()->name('composer.json')->in($monorepoRoot.'/src')->depth('== 1');

foreach ($finder as $file) {
    $packagePath = dirname($file->getRealPath());
    $composerData = json_decode($file->getContents(), true);

    if (isset($composerData['name'])) {
        $packages[$composerData['name']] = [
            'path' => $packagePath,
            'data' => $composerData,
        ];
    }
}

// Update all composer.json files with path repositories
foreach ($packages as $packageName => $packageInfo) {
    $composerFile = $packageInfo['path'].'/composer.json';
    $composerData = $packageInfo['data'];

    // Add path repositories for all MatesOfMate packages
    $repositories = [];
    foreach ($packages as $depName => $depInfo) {
        if ($depName === $packageName) {
            continue; // Skip self
        }

        // Check if package requires this dependency
        if (isset($composerData['require'][$depName]) || isset($composerData['require-dev'][$depName])) {
            $repositories[] = [
                'type' => 'path',
                'url' => $depInfo['path'],
            ];

            // Update to @dev constraint for local development
            if (isset($composerData['require'][$depName])) {
                $composerData['require'][$depName] = '@dev';
            }
            if (isset($composerData['require-dev'][$depName])) {
                $composerData['require-dev'][$depName] = '@dev';
            }
        }
    }

    if (!empty($repositories)) {
        $composerData['repositories'] = $repositories;
        file_put_contents($composerFile, json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        echo "✓ Updated $packageName\n";
    }
}

echo "\nDone! Run 'composer install' in each package.\n";
