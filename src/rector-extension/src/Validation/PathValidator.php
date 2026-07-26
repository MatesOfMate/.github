<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\RectorExtension\Validation;

/**
 * Validates that requested Rector paths stay inside the project root.
 *
 * @internal
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class PathValidator
{
    public function __construct(
        private readonly string $projectRoot,
    ) {
    }

    public function validate(?string $path): ?string
    {
        if (null === $path || '' === $path) {
            return null;
        }

        $root = realpath($this->projectRoot);
        if (false === $root) {
            throw new \InvalidArgumentException('Project root does not exist.');
        }

        $candidate = str_starts_with($path, '/') ? $path : $root.'/'.$path;
        $realPath = realpath($candidate);

        if (false === $realPath) {
            throw new \InvalidArgumentException(\sprintf('Path does not exist: %s', $path));
        }

        if ($realPath !== $root && !str_starts_with($realPath, $root.'/')) {
            throw new \InvalidArgumentException(\sprintf('Path must be inside the project root: %s', $path));
        }

        if ($realPath === $root) {
            return '.';
        }

        return ltrim(substr($realPath, \strlen($root)), '/');
    }
}
