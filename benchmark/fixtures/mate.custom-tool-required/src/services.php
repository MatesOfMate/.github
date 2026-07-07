<?php

require_once __DIR__.'/Container.php';
require_once __DIR__.'/ReportExporter.php';
require_once __DIR__.'/ReportRunner.php';

return static function (Container $c): void {
    // BUG: ReportRunner asks the container for "report.exporter", but the service
    // is never registered. The application logs in var/logs/runtime.log identify
    // exactly which service id is missing.
};
