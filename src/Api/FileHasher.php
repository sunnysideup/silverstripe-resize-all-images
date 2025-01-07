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

    protected $dryRun = true;
    protected $verbose = false;

    public function run(File $file, ?bool $dryRun = false, ?bool $verbose = false)
    {
        $this->dryRun = $dryRun;
        $this->verbose = $verbose;
        /** @var Sha1FileHashingService $hasher */
        $hasher = Injector::inst()->get(FileHashingService::class);
        try {
            $this->output('Fixing (' . ($this->dryRun ? 'DRY RUN' : 'FOR REAL') . '): ' . $file->getFilename());
            $hasher::flush();
            $file = $this->getFileFilename($file);
            $file = $this->publishMismatchCheck($file);
            $path = $file->getFilename();
            if ($file->isPublished()) {
                $fs = AssetStore::VISIBILITY_PUBLIC;
            } else {
                $fs = AssetStore::VISIBILITY_PROTECTED;
            }
            if ($path) {
                $hash = $hasher->computeFromFile((string) $path, $fs);
                if ($this->dryRun !== true) {
                    DB::query('UPDATE "File" SET "Filehash" = \'' . $hash . '\' WHERE "ID" = ' . $file->ID);
                }
                if ($file->isPublished() && ! $file->isModifiedOnDraft() && $this->dryRun !== true) {
                    DB::query('UPDATE "File_Live" SET "Filehash" = \'' . $hash . '\' WHERE "ID" = ' . $file->ID);
                }
                $file = File::get()->byID($file->ID);
                if (!$file->exists()) {
                    $this->output('ERROR (' . ($this->dryRun ? 'DRY RUN' : 'FOR REAL') . '): hash not fixed yet: ' . $file->getFilename() . '. Please run task again.');
                }
            }
        } catch (Exception $e) {
            $this->output('ERROR: ' . $e->getMessage());
        }
    }


    public function getFileFilename(File $file): File
    {
        $name = $file->getFilename();
        if ($name) {
            return $file;
        }
        $name = $file->generateFilename();
        if ($name) {
            if ($this->dryRun) {
                $this->output("ERROR: Would set file name to: " . $name);
            } else {
                $this->output("ERROR: Setting file name to: " . $name);
                $file->setFilename($name);
                if (! $file->getFilename()) {
                    user_error("ERROR: Could not set file name to: " . $name);
                }
                DB::query('UPDATE "File" SET "FileFilename" = \'' . $name . '\' WHERE "ID" = ' . $file->ID);
                DB::query('UPDATE "File_Live" SET "FileFilename" = \'' . $name . '\' WHERE "ID" = ' . $file->ID);
            }
            $file = File::get()->byID($file->ID);
        }
        return $file;
    }

    public function publishMismatchCheck($file): File
    {
        if (! $file->isPublished()) {
            $path = $file->getFilename();
            $fullPath = ASSETS_PATH . '/' . $path;
            if (file_exists($fullPath)) {
                $file->publishSingle();
                if (! $file->isPublished()) {
                    user_error('Could not publish file: ' . $file->getFilename() . ' but file exists!', E_USER_ERROR);
                }
                $file = File::get()->byID($file->ID);
            }
        }
        return $file;
    }

    protected function output($message): void
    {
        if ($this->verbose) {
            echo $message . PHP_EOL;
        }
    }
}
