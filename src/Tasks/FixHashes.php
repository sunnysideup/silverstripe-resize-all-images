<?php

namespace Sunnysideup\ResizeAllImages\Tasks;

use SilverStripe\Assets\Image;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;
use Sunnysideup\ResizeAllfiles\Api\FileHasher;

class FixHashes extends BuildTask
{
    /**
     * Title
     *
     * @var string
     */
    protected $title = 'Fix Assets (images) Hashes. Use --for-real to actually fix the hashes.';

    /**
     * Description
     *
     * @var string
     */
    protected $description = 'Goes through all images and fixes the hash. Use --for-real to actually fix the hashes.';

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
     */
    public function run($request)
    {
        if (! Director::is_cli()) {
            exit('Only works in cli');
        }

        echo '---' . PHP_EOL;
        echo '---' . PHP_EOL;
        $this->dryRun = ! in_array('--for-real', $_SERVER['argv']);
        $verbose = in_array('--verbose', $_SERVER['argv']) ? true : $this->dryRun;
        $imagesIds = Image::get()->sort(['ID' => 'DESC'])->columnUnique();
        foreach ($imagesIds as $imageID) {
            $image = Image::get()->byID($imageID);
            FileHasher::run($image, $this->dryRun, $verbose);
        }
    }
}
