<?php


namespace Sunnysideup\ResizeAllImages\Tasks;

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
    private static $max_width = 1600;

    /**
     * Image max height - images larger than this height will be downsized
     *
     * @var int px
     */
    private static $max_height = 1600;

    /**
     * test only?
     *
     * @var bool test only?
     */
    private $dryRun = true;

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

        echo '---'.PHP_EOL;
        echo '---'.PHP_EOL;

        // vendor/league/flysystem/src/Adapter/Local.php:288
        // if(file_exists($location.'/'.$file)) {
        //     $result[] = $this->normalizeFileInfo($file);
        // } else {
        //     echo $location.'/'.$file.'----------------------'.PHP_EOL;
        // }
        $directory = ASSETS_PATH;
        ResizeAssetsRunner::run_dir(
            $directory,
            Config::inst()->get(static::class, 'max_width'),
            Config::inst()->get(static::class, 'max_height'),
            $this->dryRun
        );

        // RUN!
        /** @var Sha1FileHashingService $hasher */
        $hasher = Injector::inst()->get(FileHashingService::class);
        $imagesIds = Image::get()->columnUnique();
        foreach($imagesIds as $imageID) {
            $image = Image::get()->byID($imageID);
            try {
                $hasher::flush();
                if($image->isPublished()) {
                    $fs = AssetStore::VISIBILITY_PUBLIC;
                } else {
                    $fs = AssetStore::VISIBILITY_PROTECTED;
                }
                $hash = $hasher->computeFromFile($image->getFilename(), $fs);
                if(! $this->dryRun) {
                    DB::query('UPDATE "File" SET "Filehash" = \''.$hash.'\' WHERE "ID" = '.$image->ID);
                }
                if($image->isPublished()) {
                    if(! $this->dryRun) {
                        DB::query('UPDATE "File_Live" SET "Filehash" = \''.$hash.'\' WHERE "ID" = '.$image->ID);
                    }
                }
                echo 'Publishing '.$image->getFilename().PHP_EOL;
                if(! $image->exists()) {
                    echo 'ERROR: Image does not exist: '.$image->getFilename().PHP_EOL;
                } else {
                    $image->publishSingle();
                }
            } catch (Exception $e) {
                echo $e->getMessage().PHP_EOL;
            }

        }
    }
}
