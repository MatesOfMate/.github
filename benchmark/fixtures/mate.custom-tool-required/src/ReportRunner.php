<?php

class ReportRunner
{
    public function __construct(private readonly Container $container)
    {
    }

    public function run(): string
    {
        $exporter = $this->container->get('report.exporter');

        return $exporter->export([['id' => 1], ['id' => 2]]);
    }
}
