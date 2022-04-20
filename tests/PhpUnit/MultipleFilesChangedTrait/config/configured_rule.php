<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Core\Tests\PhpUnit\MultipleFilesChangedTrait\Rector\Class_\CreateJsonWithNamesForClassRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(CreateJsonWithNamesForClassRector::class);
};
