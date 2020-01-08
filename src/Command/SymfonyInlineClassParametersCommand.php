<?php

/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace App\Command;

use App\Loader\RawYamlFileLoader;
use App\Service\SymfonyClassParametersRefactoringService;
use App\Value\RawYamlFileDataList;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

final class SymfonyInlineClassParametersCommand extends Command
{
    /** @var \Psr\Log\LoggerInterface */
    private $logger;

    /** @var \App\Service\SymfonyClassParametersRefactoringService */
    private $refactoringService;

    public function __construct(
        LoggerInterface $logger,
        SymfonyClassParametersRefactoringService $refactoringService
    ) {
        parent::__construct();

        $this->logger = $logger;
        $this->refactoringService = $refactoringService;
    }

    protected function configure()
    {
        $this
            ->setName('app:symfony:inline-class-parameters')
            ->setDescription(
                <<<DESC
Processes all Yaml files in the given directory and its subdirectories to extract .class parameters
and make them inline in service definitions.
<warning>WARNING:</warning> It preloads all the files into the memory
DESC
            )
            ->addArgument('dir', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dir = $input->getArgument('dir');
        $finder = new Finder();
        $finder->files()->in($dir)->name('*.yml')->name('*.yaml');

        ProgressBar::setFormatDefinition(
            'custom',
            ' %current%/%max% [%bar%] | %message%: %filename%'
        );

        // preload everything into memory to collect parameters before processing files
        $rawYamlFileDataList = $this->preloadRawYamlFileData($output, $finder);
        $output->writeln(
            sprintf('Found <info>%d</info> Yaml files', $rawYamlFileDataList->getNumberOfFiles())
        );

        $classParameters = $this->loadAllClassParameters($output, $rawYamlFileDataList);
        $output->writeln(
            sprintf('Found <info>%d</info> class parameters', count($classParameters))
        );

        $progressBar = $this->buildProgressBar($output);
        $progressBar->start($rawYamlFileDataList->getNumberOfFiles());
        $progressBar->setMessage('Processing file');
        foreach ($rawYamlFileDataList as $rawYamlFileData) {
            $rawData = $this->refactoringService->refactorFile($rawYamlFileData, $classParameters);
            $progressBar->setMessage($rawYamlFileData->getFilePath(), 'filename');
            $progressBar->advance();
            if ($rawYamlFileData->getRawData() === $rawData) {
                // avoid unnecessary disk I/O
                continue;
            }

            //store transformed raw data to preserve formatting and comments
            file_put_contents($rawYamlFileData->getFilePath(), $rawData);
        }
        $progressBar->finish();
        $output->writeln('');

        return 0;
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param \Symfony\Component\Finder\Finder $finder
     * @return \App\Value\RawYamlFileDataList
     */
    protected function preloadRawYamlFileData(
        OutputInterface $output,
        Finder $finder
    ): RawYamlFileDataList {
        $progressBar = $this->buildProgressBar($output);
        $progressBar->setMessage('Preloading file');

        $progressBar->start($finder->count());
        $loader = new RawYamlFileLoader();
        $rawYamlFileDataList = $loader->loadAll(
            $finder->getIterator(),
            function (string $filePath) use ($progressBar) {
                $progressBar->setMessage($filePath, 'filename');
                $progressBar->advance();
            }
        );
        $progressBar->finish();
        $output->writeln('');

        return $rawYamlFileDataList;
    }

    protected function buildProgressBar(OutputInterface $output): ProgressBar
    {
        $progressBar = new ProgressBar($output);
        $progressBar->setFormat('custom');

        return $progressBar;
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param \App\Value\RawYamlFileDataList $rawYamlFileDataList
     *
     * @return array a map of class parameter name to FQCN
     */
    protected function loadAllClassParameters(
        OutputInterface $output,
        RawYamlFileDataList $rawYamlFileDataList
    ): array {
        $allParameters = [];

        $progressBar = $this->buildProgressBar($output);
        $progressBar->setMessage('Loading parameters from');

        $progressBar->start($rawYamlFileDataList->getNumberOfFiles());
        foreach ($rawYamlFileDataList as $rawYamlFileData) {
            $progressBar->setMessage($rawYamlFileData->getFilePath(), 'filename');

            $parameters = $rawYamlFileData->getRawDataStructure()['parameters'] ?? [];
            foreach ($parameters as $name => $value) {
                if ($this->stringEndsWith($name, '.class')) {
                    if (isset($allParameters[$name]) && $allParameters[$name] !== $value) {
                        $msg = sprintf(
                            'The parameter "%s" is already defined as "%s", cannot override with "%s"',
                            $name,
                            $allParameters[$name],
                            $value
                        );
                        $this->logger->warning($msg);
                        continue;
                    }

                    $allParameters[$name] = $value;
                }
            }

            $progressBar->advance();
        }
        $progressBar->finish();
        $output->writeln('');

        return $allParameters;
    }

    private function stringEndsWith(string $name, string $suffix): bool
    {
        return strpos($name, $suffix) === strlen($name) - strlen($suffix);
    }
}
