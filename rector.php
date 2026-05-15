<?php

declare(strict_types=1);

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Rector\Config\RectorConfig;
use Rector\Php70\Rector\FunctionLike\ExceptionHandlerTypehintRector;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\NarrowUnusedSetUpDefinedPropertyRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\RemoveNeverUsedMockPropertyRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\LevelSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__.'/src',
        __DIR__.'/tests',
    ]);

    // Track the project's PHP 8.4 / PHPUnit 12 floor.
    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_84,
        PHPUnitSetList::PHPUNIT_120,
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
    ]);

    $rectorConfig->importNames();
    $rectorConfig->importShortClasses(false);
    $rectorConfig->skip([
        ExceptionHandlerTypehintRector::class,
        // ReadOnlyPropertyRector: we already apply readonly explicitly where
        // it's safe; auto-applying it across the board collides with the
        // PHPStan bleedingEdge stance on readonly + __clone (see ProxyQuery).
        ReadOnlyPropertyRector::class,
        NullToStrictStringFuncCallArgRector::class,
        PreferPHPUnitThisCallRector::class,
        NarrowUnusedSetUpDefinedPropertyRector::class,
        RemoveNeverUsedMockPropertyRector::class,
    ]);
};
