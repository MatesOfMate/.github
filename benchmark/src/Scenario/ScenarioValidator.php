<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Scenario;

use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;

/**
 * Validates parsed scenario arrays against the scenario JSON schema.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class ScenarioValidator
{
    private readonly object $schema;

    public function __construct(string $schemaPath)
    {
        if (!is_file($schemaPath)) {
            throw new \InvalidArgumentException(\sprintf('Scenario schema "%s" does not exist.', $schemaPath));
        }

        $contents = file_get_contents($schemaPath);

        if (false === $contents) {
            throw new \RuntimeException(\sprintf('Unable to read scenario schema "%s".', $schemaPath));
        }

        $decoded = json_decode($contents, false, 512, \JSON_THROW_ON_ERROR);

        if (!\is_object($decoded)) {
            throw new \RuntimeException(\sprintf('Scenario schema "%s" must decode to a JSON object.', $schemaPath));
        }

        $this->schema = $decoded;
    }

    /**
     * Validates the given scenario data and returns a list of human-readable errors.
     *
     * @param array<string, mixed> $data
     *
     * @return list<string>
     */
    public function validate(array $data): array
    {
        $payload = json_decode(json_encode($data, \JSON_THROW_ON_ERROR), false);

        $validator = new Validator();
        $validator->validate($payload, $this->schema, Constraint::CHECK_MODE_TYPE_CAST);

        if ($validator->isValid()) {
            return [];
        }

        $errors = [];
        foreach ($validator->getErrors() as $error) {
            $pointer = '' !== ($error['property'] ?? '') ? $error['property'] : '(root)';
            $errors[] = \sprintf('%s: %s', $pointer, $error['message'] ?? 'unknown error');
        }

        return $errors;
    }
}
