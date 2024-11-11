<?php

namespace Sunnysideup\ResizeAllImages\Tasks;

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


    /**
     * Run
     *
     * @param HTTPRequest $request HTTP request
     *
     * @return HTTPResponse
     */
    public function run($request)
    {
        if (! Director::is_cli()) {
            exit('Only works in cli');
        }
        $options = getopt('', ['for-real', ]);
        echo '---' . PHP_EOL;
        echo '---' . PHP_EOL;

        $directory = ASSETS_PATH;
        $dryRun = ! isset($options['for-real']); // Pass --dry-run as an argument to perform a dry run


        // RUN!
        $runner = ResizeAssetsRunner::create();
        $runner->setDryRun($dryRun);
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory), RecursiveIteratorIterator::SELF_FIRST);

        foreach ($files as $file) {
            $runner->runFromFilesystemFileOuter($file);
        }
        echo '---' . PHP_EOL;
        echo '---' . PHP_EOL;
        echo 'DONE - consider running dev/tasks/fix-hashes --for-real' . PHP_EOL;
        echo '---' . PHP_EOL;
    }
}
