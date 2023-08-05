<?php

namespace Sunnysideup\ResizeAllfiles\Api;

use Exception;
use SilverStripe\Assets\file;
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

class FileHasher
{
    public static function run($file, ?bool $dryRun = false, ?bool $verbose = false)
    {
        $hasher = Injector::inst()->get(FileHashingService::class);
        $isPublished = $file->isPublished();
        try {
            if($verbose) {
                echo 'Fixing '.$file->getFilename().PHP_EOL;
            }
            $hasher::flush();
            if($file->isPublished()) {
                $fs = AssetStore::VISIBILITY_PUBLIC;
            } else {
                $fs = AssetStore::VISIBILITY_PROTECTED;
            }
            $hash = $hasher->computeFromFile($file->getFilename(), $fs);
            if(! $dryRun) {
                DB::query('UPDATE "File" SET "Filehash" = \''.$hash.'\' WHERE "ID" = '.$file->ID);
            }
            if($file->isPublished()) {
                if(! $dryRun) {
                    DB::query('UPDATE "File_Live" SET "Filehash" = \''.$hash.'\' WHERE "ID" = '.$file->ID);
                }
            }
            $file = DataObject::get_by_id(file::class, $file->ID);
            if($verbose) {
                if(! $file->exists()) {
                    echo 'ERROR: hash not fixed yet: '.$file->getFilename().'. Please run task again.' . PHP_EOL;
                } else {
                    echo 'SUCCESS: file exists: '.$file->getFilename() . PHP_EOL;
                }
            }
        } catch (Exception $e) {
            if($verbose) {
                echo 'ERROR: '.$e->getMessage().PHP_EOL;
            }
        }

    }
}
