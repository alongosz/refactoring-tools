<?php

/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace App\Loader;

use App\Value\RawYamlFileData;
use App\Value\RawYamlFileDataList;

final class RawYamlFileLoader
{
    /**
     * @param \SplFileInfo[] $files
     */
    public function loadAll(iterable $files, callable $perFileCallback = null): RawYamlFileDataList
    {
        $fileList = new RawYamlFileDataList();
        $hasCallback = is_callable($perFileCallback);
        foreach ($files as $file) {
            $filePath = $file->getPathname();
            $fileList->appendRawYamlFileData(
                new RawYamlFileData($filePath, file_get_contents($filePath))
            );
            if ($hasCallback) {
                $perFileCallback($filePath);
            }
        }

        return $fileList;
    }
}
