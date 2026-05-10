<?php

namespace Sunnysideup\ResizeAllImages\Tasks;

use Override;
use SilverStripe\Assets\Image;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\BuildTask;
use Symfony\Component\Console\Command\Command;
use SilverStripe\PolyExecution\PolyOutput;
use Sunnysideup\ScaledUploads\Api\Resizer;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

class ResizeAllImagesTask extends BuildTask
{
    protected string $title = 'Resize Images';

    protected static string $description = 'Resize all images in the assets folder to a maximum width and height.';

    protected static string $commandName = 'resize-all-images';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        if (!Environment::isCli()) {
            $output->writeln('Only works in CLI');
            return Command::FAILURE;
        }

        $output->writeln('---');
        $output->writeln('START');
        $output->writeln('---');

        $dryRun = !$input->getOption('for-real');
        $limit = (int) $input->getOption('limit');
        $start = (int) $input->getOption('start');
        $nukeTmp = (bool) $input->getOption('nuke-tmp');

        if ($dryRun) {
            $output->writeln('Running in dry-run mode. Use --for-real or -r to apply changes.');
        }

        if ($nukeTmp) {
            $output->writeln('Nuke tmp enabled: will clean up ImageMagick temp files after each resize.');
        }

        $runner = Resizer::create()
            ->setDryRun($dryRun)
            ->setVerbose(true);
        $this->outputVars($runner, $output);

        $imagesIds = Image::get()
            ->sort(['ID' => 'DESC'])
            ->limit($limit ?: null, $start)
            ->columnUnique();

        foreach ($imagesIds as $imageID) {
            $image = Image::get()->byID($imageID);
            if ($image->isPublished()) {
                $runner->runFromDbFile($image);
                if ($nukeTmp) {
                    $this->nukeTmp();
                }
            }
        }

        $output->writeln('---');
        $output->writeln('---');
        $output->writeln('Completed resize - consider running vendor/bin/sake dev/tasks/fix-hashes --for-real=1');
        $output->writeln('---');

        return Command::SUCCESS;
    }

    protected function nukeTmp(): void
    {
        exec('find /tmp -name "interventionimage_*" -delete');
        exec('find /tmp -name "magick-*" -delete');
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
                new InputOption('for-real', 'r', InputOption::VALUE_NONE, 'Apply changes instead of dry run'),
                new InputOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Maximum number of images to process', 0),
                new InputOption('start', 's', InputOption::VALUE_OPTIONAL, 'Number of images to skip before processing', 0),
                new InputOption('nuke-tmp', null, InputOption::VALUE_NONE, 'Clean up ImageMagick temp files after each resize'),
            ]
        );
    }
}
