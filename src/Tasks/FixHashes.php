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
        $options = getopt('', ['for-real', 'verbose']);
        $this->dryRun = ! isset($options['for-real']);
        $verbose = isset($options['verbose']) ? true : $this->dryRun;
        $imagesIds = Image::get()->sort(['ID' => 'DESC'])->columnUnique();
        $hasher = FileHasher::create();
        foreach ($imagesIds as $imageID) {
            $image = Image::get()->byID($imageID);
            $hasher->run($image, $this->dryRun, $verbose);
        }
        echo '---' . PHP_EOL;
        echo 'DONE' . PHP_EOL;
        echo '---' . PHP_EOL;
    }
}
