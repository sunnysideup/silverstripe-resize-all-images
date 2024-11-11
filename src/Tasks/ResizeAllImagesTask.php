<?php

namespace Sunnysideup\ResizeAllImages\Tasks;

use Axllent\ScaledUploads\Api\Resizer;
use Axllent\ScaledUploads\ScaledUploads;
use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SilverStripe\Assets\Image;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Config\Config;
//use SilverStripe\Dev\Tasks\MigrateFileTask;
use SilverStripe\Dev\BuildTask;
use SplFileInfo;
use Sunnysideup\ResizeAllImages\Api\FileHasher;
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
             * @var runFromFilesystemFileOuter $runner
             */
            $runner = ResizeAssetsRunner::create();
            $runner->setDryRun($dryRun);
            $runner->setVerbose(true);
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory), RecursiveIteratorIterator::SELF_FIRST);
            foreach ($files as $file) {
                $runner->runFromFilesystemFileOuter($file);
            }
        } else {
            $runner = Resizer::create();
            $runner->setDryRun($dryRun);
            $runner->setVerbose(true);
            $imagesIds = Image::get()->sort(['ID' => 'DESC'])->columnUnique();
            foreach ($imagesIds as $imageID) {
                $image = Image::get()->byID($imageID);
                if ($image->exists()) {
                    $runner->runFromDbFile($image);
                }
            }
        }
        echo '---' . PHP_EOL;
        echo '---' . PHP_EOL;
        echo 'DONE - consider running dev/tasks/fix-hashes --for-real=1' . PHP_EOL;
        echo '---' . PHP_EOL;
    }
}
