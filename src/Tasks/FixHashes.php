<?php

namespace Sunnysideup\ResizeAllImages\Tasks;

use Override;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Folder;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\Command\Command;
use SilverStripe\PolyExecution\PolyOutput;
use Sunnysideup\ResizeAllImages\Api\FileHasher;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

class FixHashes extends BuildTask
{
    /**
     * Title
     *
     * @var string
     */
    protected string $title = 'Fix Assets (files) Hashes.';

    /**
     * Description
     *
     * @var string
     */
    protected static string $description = '
        Goes through all files and fixes the hash.
        Use sake dev/tasks/fix-hashes -r to actually fix the hashes.
    ';

    /**
     * Segment URL
     *
     * @var string
     */
    protected static string $commandName = 'fix-hashes';

    private $dryRun = true;

     /**
      * Run
      *
      * @param HTTPRequest $request HTTP request
      */
    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        if (!Environment::isCli()) {
            $output->writeln('Only works in CLI.');
            return Command::FAILURE;
        }

        $output->writeln(str_repeat('-', 30));
        $output->writeln('Starting Script');
        $output->writeln(str_repeat('-', 30));

        $this->dryRun = !$input->getOption('for-real');
        if ($this->dryRun) {
            $output->writeln('Running in dry-run mode. Use --for-real or -r to apply changes.');
        }

        $fileIds = File::get()
            ->filter(['ClassName:not' => Folder::class])
            ->sort(['ID' => 'DESC'])
            ->columnUnique();

        $hasher = FileHasher::create();

        // Process files
        foreach ($fileIds as $fileId) {
            $file = File::get()->byID($fileId);
            if ($file) {
                $hasher->run($file, $this->dryRun, true);
            } else {
                $output->writeln(sprintf('File ID %s not found.', $fileId));
            }
        }

        $output->writeln(str_repeat('-', 30));
        $output->writeln('Completed hash fix run');
        $output->writeln(str_repeat('-', 30));

        if ($this->dryRun) {
            $output->writeln('This was a dry run. Use --for-real or -r to actually fix the hashes.');
        }

        return \Symfony\Component\Console\Command\Command::SUCCESS;
    }

    #[Override]
    public function getOptions(): array
    {
        return array_merge(
            parent::getOptions(),
            [
                new InputOption('for-real', 'r', InputOption::VALUE_NONE, 'Apply changes instead of dry run')
            ]
        );
    }
}
