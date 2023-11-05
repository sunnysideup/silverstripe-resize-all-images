<?php

namespace Sunnysideup\ResizeAllImages\Tasks;

use Axllent\ScaledUploads\ScaledUploads;
use Exception;
use SilverStripe\Assets\Image;
use SilverStripe\Assets\Storage\AssetStore;
use SilverStripe\Assets\Storage\FileHashingService;
use SilverStripe\Assets\Storage\Sha1FileHashingService;
use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
//use SilverStripe\Dev\Tasks\MigrateFileTask;
use SilverStripe\ORM\DB;
use Sunnysideup\ResizeAssets\ResizeAssetsRunner;
use SilverStripe\Core\Config\Config;

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
     * Enabled
     *
     * @var mixed
     */
    protected $enabled = true;

    /**
     * Segment URL
     *
     * @var string
     */
    private static $segment = 'resize-all-images';

    /**
     * Image max width - images larger than this width will be downsized
     *
     * @var int px
     */
    private static $max_width = 2800;

    /**
     * Image max height - images larger than this height will be downsized
     *
     * @var int px
     */
    private static $max_height = 1900;

    /**
     *
     * @var int mb
     */
    private static $max_size_in_mb = 2;

    /**
     *
     * @var float
     */
    private static $default_quality = 0.77;

    /**
     *
     * @var float
     */
    private static $large_size_default_quality = 0.67;

    /**
     * Run
     *
     * @param HTTPRequest $request HTTP request
     *
     * @return HTTPResponse
     */
    public function run($request)
    {


        if (!Director::is_cli()) {
            exit('Only works in cli');
        }

        echo '---' . PHP_EOL;
        echo '---' . PHP_EOL;


        $directory = ASSETS_PATH;
        $maxWidth = Config::inst()->get(ScaledUploads::class, 'max_width') ?: Config::inst()->get(static::class, 'max_width');
        $maxHeight = Config::inst()->get(ScaledUploads::class, 'max_height') ?: Config::inst()->get(static::class, 'max_height');
        $maxSize = Config::inst()->get(ScaledUploads::class, 'max_size_in_mb') ?: Config::inst()->get(static::class, 'max_size_in_mb');
        $quality = Config::inst()->get(ScaledUploads::class, 'default_quality') ?: Config::inst()->get(static::class, 'default_quality');
        $largeSizeQuality = Config::inst()->get(ScaledUploads::class, 'large_size_default_quality') ?: Config::inst()->get(static::class, 'large_size_default_quality');
        $dryRun = !in_array('--for-real', $_SERVER['argv']); // Pass --dry-run as an argument to perform a dry run

        echo "--- DIRECTORY: " . $directory . PHP_EOL;
        echo "--- MAX-WIDTH: " . $maxWidth . PHP_EOL;
        echo "--- MAX-HEIGHT: " . $maxHeight . PHP_EOL;
        echo "--- MAX-SIZE: " . $maxSize . PHP_EOL;
        echo "--- QUALITY: " . $quality . PHP_EOL;
        echo "--- LARGE SIZE QUALITY: " . $largeSizeQuality . PHP_EOL;
        echo "--- DRY-RUN: " . ($dryRun ? 'YES' : 'NO') . PHP_EOL;
        // RUN!

        ResizeAssetsRunner::set_max_file_size_in_mb($maxSize);
        ResizeAssetsRunner::set_default_quality(0.77);
        ResizeAssetsRunner::set_large_size_default_quality(0.67);

        ResizeAssetsRunner::run_dir($directory, $maxWidth, $maxHeight, $dryRun);

        echo '---' . PHP_EOL;
        echo '---' . PHP_EOL;
        echo 'DONE - consider running dev/tasks/fix-hashes --for-real' . PHP_EOL;
        echo '---' . PHP_EOL;
    }
}
