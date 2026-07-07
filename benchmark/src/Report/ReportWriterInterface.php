<?php

/*
 * This file is part of the MatesOfMate Organisation.
 *
 * (c) Johannes Wachter <johannes@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MatesOfMate\Benchmark\Report;

/**
 * Writes one report artefact for a finished benchmark run.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
interface ReportWriterInterface
{
    public function write(ReportContext $context): void;
}
