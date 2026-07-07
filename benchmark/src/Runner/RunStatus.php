<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Runner;

/**
 * High-level status of a single scenario attempt.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
enum RunStatus: string
{
    case Passed = 'passed';
    case Failed = 'failed';
    case AdapterError = 'adapter_error';
    case SetupError = 'setup_error';
}
