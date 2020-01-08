<?php

/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace App\Service;

use App\Value\RawYamlFileData;
use Psr\Log\LoggerInterface;

final class SymfonyClassParametersRefactoringService
{
    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function refactorFile(RawYamlFileData $rawYamlFileData, array $classParameters): string
    {
        if (empty($rawYamlFileData->getRawDataStructure()['services'])) {
            return $rawYamlFileData->getRawData();
        }

        // perform actual replacement on raw data to preserve formatting and comments
        $raw = preg_replace_callback(
            '/class: ([\'"]%(.*\.class)%[\'"])/',
            function (array $matches) use ($classParameters) {
                // $subject of replacement: full parameter name with %delimiters% and quotes
                [$fullMatch, , $paramName] = $matches;

                if (!isset($classParameters[$paramName])) {
                    $this->logger->warning(sprintf('Class parameter "%s" not found', $paramName));

                    return $fullMatch;
                }

                return "class: {$classParameters[$paramName]}";
            },
            $rawYamlFileData->getRawData()
        );

        // remove no longer used parameters
        $raw = preg_replace_callback(
            '/ *(.*\.class): .*/',
            function (array $matches) use ($classParameters) {
                return array_key_exists($matches[1], $classParameters) ? '' : $matches[0];
            },
            $raw
        );

        $fileParameters = $rawYamlFileData->getRawDataStructure()['parameters'] ?? [];
        foreach (array_keys($classParameters) as $paramName) {
            unset($fileParameters[$paramName]);
        }

        // remove empty parameter list
        if (empty($fileParameters)) {
            $raw = preg_replace('/^parameters:\n*/m', '', $raw);
        }

        return $raw;
    }
}
