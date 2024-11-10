<?php

namespace Sunnysideup\ResizeAllImages\Api;

use Exception;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\Assets\Storage\AssetStore;
use SilverStripe\Assets\Storage\FileHashingService;
use SilverStripe\Assets\Storage\Sha1FileHashingService;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;

class FileHasher
{
    use Injectable;

    public function run(File $file, ?bool $dryRun = false, ?bool $verbose = false)
    {
        /** @var Sha1FileHashingService $hasher */
        $hasher = Injector::inst()->get(FileHashingService::class);
        try {
            if ($verbose) {
                echo 'Fixing (' . ($dryRun ? 'DRY RUN' : 'FOR REAL') . '): ' . $file->getFilename() . PHP_EOL;
            }
            $hasher::flush();
            if ($file->isPublished()) {
                $fs = AssetStore::VISIBILITY_PUBLIC;
            } else {
                $fs = AssetStore::VISIBILITY_PROTECTED;
            }
            $name = $file->getFilename();
            if (! $name) {
                $name = DB::query('SELECT FileFileName FROM File WHERE ID = ' . $file->ID)->value();
            }
            $hash = $hasher->computeFromFile((string) $name, $fs);
            if ($dryRun !== true) {
                DB::query('UPDATE "File" SET "Filehash" = \'' . $hash . '\' WHERE "ID" = ' . $file->ID);
            }
            if ($file->isPublished() && ! $file->isModifiedOnDraft() && $dryRun !== true) {
                DB::query('UPDATE "File_Live" SET "Filehash" = \'' . $hash . '\' WHERE "ID" = ' . $file->ID);
            }
            $file = Image::get()->byID($file->ID);
            if ($verbose) {
                if (! $file->exists()) {
                    echo 'ERROR (' . ($dryRun ? 'DRY RUN' : 'FOR REAL') . '): hash not fixed yet: ' . $file->getFilename() . '. Please run task again.' . PHP_EOL;
                } else {
                    echo 'SUCCESS (' . ($dryRun ? 'DRY RUN' : 'FOR REAL') . '): file exists: ' . $file->getFilename() . PHP_EOL;
                }
            }
        } catch (Exception $e) {
            if ($verbose) {
                echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
            }
        }
    }

}
