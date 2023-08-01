<?php

namespace Sunnysideup\ResizeAllImages\Tasks;

use Exception;
use SilverStripe\Assets\Image;
use SilverStripe\Assets\Storage\AssetStore;
use SilverStripe\Assets\Storage\FileHashingService;
use SilverStripe\Assets\Storage\Sha1FileHashingService;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use Sunnysideup\ResizeAssets\ResizeAssetsRunner;

class ResizeImagesNew extends BuildTask
{
    /**
     * Title
     *
     * @var string
     */
    protected $title = 'Fix Assets (images) Hashes';

    /**
     * Description
     *
     * @var string
     */
    protected $description = 'Goes through all images and fixes the hash.';

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
    private static $segment = 'fix-hashes';

    private $dryRun = true;

    /**
     * Run
     *
     * @param HTTPRequest $request HTTP request
     *
     */
    public function run($request)
    {
        if (!Director::is_cli()) {
            exit('Only works in cli');
        }

        echo '---'.PHP_EOL;
        echo '---'.PHP_EOL;

        $this->dryRun = !in_array('--real-run', $_SERVER['argv']);

        /** @var Sha1FileHashingService $hasher */
        $hasher = Injector::inst()->get(FileHashingService::class);
        $imagesIds = Image::get()->columnUnique();
        foreach($imagesIds as $imageID) {
            $image = Image::get()->byID($imageID);
            $isPublished = $image->isPublished();
            try {
                echo 'Fixing '.$image->getFilename().PHP_EOL;
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
                $image = DataObject::get_by_id(Image::class, $image->ID);
                if(! $image->exists()) {
                    echo 'ERROR: hash not fixed yet: '.$image->getFilename().'. Please run task again.' . PHP_EOL;
                } else {
                    echo 'SUCCESS: Image exists: '.$image->getFilename() . PHP_EOL;
                }
            } catch (Exception $e) {
                echo $e->getMessage().PHP_EOL;
            }

        }
    }
}
