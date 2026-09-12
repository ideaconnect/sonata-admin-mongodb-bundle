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

namespace IDCT\Adminata\DoctrineMongoDB\Filter;

use IDCT\Adminata\Form\Type\DateTimeRangeType;

final class DateTimeRangeFilter extends AbstractDateFilter
{
    protected bool $time = true;

    protected bool $range = true;

    protected function getDateFieldType(): string
    {
        return DateTimeRangeType::class;
    }
}
