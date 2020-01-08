<?php

/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace App\Value;

use ArrayIterator;
use IteratorAggregate;
use Traversable;

final class RawYamlFileDataList implements IteratorAggregate
{
    /** @var array */
    private $items = [];

    public function appendRawYamlFileData(RawYamlFileData $rawYamlFileData)
    {
        $this->items[] = $rawYamlFileData;
    }

    /**
     * @return \App\Value\RawYamlFileData[]|\Traversable
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    public function getNumberOfFiles(): int
    {
        return count($this->items);
    }
}
