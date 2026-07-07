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
 * Runs the configured set of report writers in a stable, deterministic order.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
class ReportPipeline
{
    /**
     * @var list<ReportWriterInterface>
     */
    private array $writers;

    /**
     * @param iterable<ReportWriterInterface>|null $writers
     */
    public function __construct(?iterable $writers = null)
    {
        if (null === $writers) {
            $this->writers = self::defaultWriters();

            return;
        }

        $list = [];
        foreach ($writers as $writer) {
            $list[] = $writer;
        }
        $this->writers = $list;
    }

    public function emit(ReportContext $context): void
    {
        foreach ($this->writers as $writer) {
            $writer->write($context);
        }
    }

    /**
     * @return list<ReportWriterInterface>
     */
    public static function defaultWriters(): array
    {
        return [
            new ArtifactsWriter(),
            new JsonReportWriter(),
            new MarkdownReportWriter(),
        ];
    }
}
