<?php

class ReportExporter
{
    public function export(array $rows): string
    {
        return "rows=".\count($rows);
    }
}
