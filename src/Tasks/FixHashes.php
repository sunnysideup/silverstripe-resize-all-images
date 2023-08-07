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
use Sunnysideup\ResizeAllfiles\Api\FileHasher;
use Sunnysideup\ResizeAssets\ResizeAssetsRunner;

class FixHashes extends BuildTask
{
    /**
     * Title
     *
     * @var string
     */
    protected $title = 'Fix Assets (images) Hashes. Use --for-real to actually fix the hash.';

    /**
     * Description
     *
     * @var string
     */
    protected $description = 'Goes through all images and fixes the hash. Use --for-real to actually fix the hash.';

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
        $imagesIds = Image::get()->columnUnique();
        foreach($imagesIds as $imageID) {
            $image = Image::get()->byID($imageID);
            FileHasher::run($image, $this->dryRun, true);
        }
    }
}
