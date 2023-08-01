<?php

namespace Sunnysideup\ResizeAssets;

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
    private static $segment = 'FixHashes';

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


        /** @var Sha1FileHashingService $hasher */
        $hasher = Injector::inst()->get(FileHashingService::class);
        $imagesIds = Image::get()->columnUnique();
        foreach($imagesIds as $imageID) {
            $image = Image::get()->byID($imageID);
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
                if(! $image->exists()) {
                    if(! $this->dryRun) {
                        $image->doArchive();
                        echo 'ERROR: Image does not exist: '.$image->getFilename().'. It has been archived' . PHP_EOL;
                    } else {
                        echo 'ERROR: Image does not exist: '.$image->getFilename().'. It would have been archived' . PHP_EOL;
                    }
                } else {
                    if(! $this->dryRun) {
                        $image->publishSingle();
                    }
                }
            } catch (Exception $e) {
                echo $e->getMessage().PHP_EOL;
            }

        }
    }
}
