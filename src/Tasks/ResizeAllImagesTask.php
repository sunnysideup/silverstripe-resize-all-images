<?php

namespace Sunnysideup\ResizeAllImages\Tasks;

use Sunnysideup\ScaledUploads\Api\Resizer;
use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SilverStripe\Assets\Image;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
//use SilverStripe\Dev\Tasks\MigrateFileTask;
use SilverStripe\Dev\BuildTask;
use SplFileInfo;
use Sunnysideup\ResizeAllImages\Api\ResizeAssetsRunner;

class ResizeAllImagesTask extends BuildTask
{
    /**
     * Title
     *
     * @var string
     */
    protected $title = 'Resize Images';

    /**
     * Description
     *
     * @var string
     */
    protected $description = 'Resize all images in the assets folder to a maximum width and height.';

    /**
     * Segment URL
     *
     * @var string
     */
    private static $segment = 'resize-all-images';

    protected $useFilesystem = false;


    /**
     * Run
     *
     * @param HTTPRequest $request HTTP request
     *
     */
    public function run($request)
    {
        if (! Director::is_cli()) {
            exit('Only works in cli');
        }
        echo '---' . PHP_EOL;
        echo 'START' . PHP_EOL;
        echo '---' . PHP_EOL;
        $directory = ASSETS_PATH;
        $dryRun = !isset($_GET['for-real']);

        // RUN!
        if ($this->useFilesystem) {
            /**
             * @var ResizeAssetsRunner $runner
             */
            $runner = ResizeAssetsRunner::create()
                ->setDryRun($dryRun)
                ->setVerbose(true);
            $this->outputVars($runner);
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory), RecursiveIteratorIterator::SELF_FIRST);
            foreach ($files as $file) {
                $runner->runFromFilesystemFileOuter($file);
            }
        } else {
            $runner = Resizer::create()
                ->setDryRun($dryRun)
                ->setVerbose(true);
            $this->outputVars($runner);
            $imagesIds = Image::get()->sort(['ID' => 'DESC'])->columnUnique();
            foreach ($imagesIds as $imageID) {
                $image = Image::get()->byID($imageID);
                if ($image->isPublished()) {
                    $runner->runFromDbFile($image);
                }
            }
        }
        echo '---' . PHP_EOL;
        echo '---' . PHP_EOL;
        echo 'DONE - consider running vendor/bin/sake dev/tasks/fix-hashes --for-real=1' . PHP_EOL;
        echo '---' . PHP_EOL;
    }

    protected function outputVars($runner)
    {
        print_r($runner->getAllProperties());
    }
}
