<?php

namespace Sunnysideup\ResizeAllImages\Tasks;

use SilverStripe\Assets\File;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\Image;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use Sunnysideup\ResizeAllImages\Api\FileHasher;

class FixHashes extends BuildTask
{
    /**
     * Title
     *
     * @var string
     */
    protected $title = 'Fix Assets (files) Hashes.';

    /**
     * Description
     *
     * @var string
     */
    protected $description = '
        Goes through all files and fixes the hash.
        Use sake dev/tasks/fix-hashes -r to actually fix the hashes.
    ';

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
        if (!Director::is_cli()) {
            exit('Only works in CLI.');
        }

        echo str_repeat('-', 30) . PHP_EOL;
        echo 'Starting Script' . PHP_EOL;
        echo str_repeat('-', 30) . PHP_EOL;

        // Parse options
        $arguments = (array) $_SERVER['argv'];

        if (
            in_array('-r', $arguments) ||
            in_array('--for-real', $arguments) ||
            in_array('--real', $arguments) ||
            isset($_GET['for-real']) ||
            in_array('for-real', $arguments)
        ) {
            $this->dryRun = false;
        } else {
            echo 'Running in dry-run mode. Use --for-real=1 or -r to apply changes.' . PHP_EOL;
        }

        $fileIds = File::get()
            ->filter(['ClassName:not' => Folder::class])
            ->sort(['ID' => 'DESC'])
            ->columnUnique();

        $hasher = FileHasher::create();

        // Process files
        foreach ($fileIds as $fileId) {
            $file = File::get()->byID($fileId);
            if ($file) {
                $hasher->run($file, $this->dryRun, true);
            } else {
                echo "File ID $fileId not found." . PHP_EOL;
            }
        }

        echo str_repeat('-', 30) . PHP_EOL;
        echo 'DONE' . PHP_EOL;
        echo str_repeat('-', 30) . PHP_EOL;

        if ($this->dryRun) {
            echo 'This was a dry run. Use --real or -r to actually fix the hashes.' . PHP_EOL;
        }
    }
}
