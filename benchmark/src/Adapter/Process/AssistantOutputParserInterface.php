<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Adapter\Process;

/**
 * Converts the raw stdout/stderr of an assistant CLI run into structured data.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
interface AssistantOutputParserInterface
{
    public function parse(string $stdout, string $stderr): ParsedAssistantOutput;
}
