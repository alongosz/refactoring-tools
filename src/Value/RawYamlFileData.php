<?php

/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace App\Value;

use Symfony\Component\Yaml\Yaml;

final class RawYamlFileData
{
    /** @var string */
    private $filePath;

    /** @var string */
    private $rawData;

    /** @var array */
    private $rawDataStructure;

    public function __construct(string $filePath, string $rawData)
    {
        $this->filePath = $filePath;
        $this->rawData = $rawData;
        $this->rawDataStructure = Yaml::parse($rawData, Yaml::PARSE_CUSTOM_TAGS);
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getRawData(): string
    {
        return $this->rawData;
    }

    public function getRawDataStructure(): array
    {
        return $this->rawDataStructure;
    }
}
