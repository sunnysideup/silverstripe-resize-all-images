<?php

namespace Sunnysideup\ResizeAllImages\Tasks;

use Override;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SilverStripe\Assets\Image;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\BuildTask;
use Symfony\Component\Console\Command\Command;
use SilverStripe\PolyExecution\PolyOutput;
use Sunnysideup\ResizeAllImages\Api\ResizeAssetsRunner;
use Sunnysideup\ScaledUploads\Api\Resizer;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

class ResizeAllImagesTask extends BuildTask
{
    /**
     * Title
     *
     * @var string
     */
    protected string $title = 'Resize Images';

    /**
     * Description
     *
     * @var string
     */
    protected static string $description = 'Resize all images in the assets folder to a maximum width and height.';

    /**
     * Segment URL
     *
     * @var string
     */
    protected static string $commandName = 'resize-all-images';

    protected $useFilesystem = false;

     /**
      * Run
      *
      * @param HTTPRequest $request HTTP request
      */
    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        if (!Environment::isCli()) {
            $output->writeln('Only works in CLI');
            return Command::FAILURE;
        }

        $output->writeln('---');
        $output->writeln('START');
        $output->writeln('---');

        $directory = ASSETS_PATH;
        $dryRun = !$input->getOption('for-real');

        if ($dryRun) {
            $output->writeln('Running in dry-run mode. Use --for-real or -r to apply changes.');
        }

        // RUN!
        if ($this->useFilesystem) {
            /**
             * @var ResizeAssetsRunner $runner
             */
            $runner = ResizeAssetsRunner::create()
                ->setDryRun($dryRun)
                ->setVerbose(true);
            $this->outputVars($runner, $output);
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory), RecursiveIteratorIterator::SELF_FIRST);
            foreach ($files as $file) {
                $runner->runFromFilesystemFileOuter($file);
            }
        } else {
            $runner = Resizer::create()
                ->setDryRun($dryRun)
                ->setVerbose(true);
            $this->outputVars($runner, $output);
            $imagesIds = Image::get()->sort(['ID' => 'DESC'])->columnUnique();
            foreach ($imagesIds as $imageID) {
                $image = Image::get()->byID($imageID);
                if ($image->isPublished()) {
                    $runner->runFromDbFile($image);
                }
            }
        }

        $output->writeln('---');
        $output->writeln('---');
        $output->writeln('Completed resize - consider running vendor/bin/sake dev/tasks/fix-hashes --for-real=1');
        $output->writeln('---');

        return Command::SUCCESS;
    }

    protected function outputVars($runner, PolyOutput $output): void
    {
        foreach ($runner->getAllProperties() as $name => $value) {
            $output->writeln(sprintf('%s: %s', $name, is_scalar($value) ? (string) $value : json_encode($value)));
        }
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
