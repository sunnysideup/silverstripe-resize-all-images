<?php

namespace Sunnysideup\ResizeAllImages\Api;

use Axllent\ScaledUploads\ScaledUploads;
use Exception;
use Imagick;
use SilverStripe\Core\Injector\Injectable;
use SplFileInfo;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SilverStripe\Assets\Image;
use SilverStripe\Assets\Storage\AssetStore;
use SilverStripe\Assets\Storage\FileHashingService;
use SilverStripe\Assets\Storage\Sha1FileHashingService;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DB;

class ResizeAssetsRunner
{
    use Injectable;

    protected bool $useImagick = false;

    protected bool $useGd = false;

    protected array $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    protected array $patternsToSkip = [];

    protected float $maxFileSizeInMb = 1;

    protected float $maxWidth = 2800;
    protected float $maxHeight = 1200;
    protected float $quality = 0.77;

    protected bool $alsoConvertToWebP = true;

    /**
     * When trying to get in range for size, we keep reducing the quality by this step.
     * Until the image is small enough.
     * @var float
     */
    protected float $quality_reduction_step = 0.1;

    public function setImagickAsConverter(): static
    {
        $this->useImagick = true;
        $this->useGd = false;
        return $this;
    }

    public function setImageExtensions(array $array): static
    {
        $this->imageExtensions = $array;
        return $this;
    }

    public function setPatternsToSkip(array $array): static
    {
        $this->patternsToSkip = $array;
        return $this;
    }

    public function setGdAsConverter()
    {
        $this->useGd = true;
        $this->useImagick = false;
        return $this;
    }

    public function setMaxFileSizeInMb(null|float|int $maxFileSizeInMb = 2): static
    {
        $this->maxFileSizeInMb = $maxFileSizeInMb;
        return $this;
    }

    public function setMaxWidth(?float $maxWidth = 2800): static
    {
        $this->maxWidth = $maxWidth;
        return $this;
    }
    public function setMaxHeight(?float $maxHeight = 1200): static
    {
        $this->maxHeight = $maxHeight;
        return $this;
    }

    public function setQuality(?float $quality = 0.77): static
    {
        $this->quality = $quality;
        return $this;
    }

    public function setAlsoConvertToWebP(?bool $alsoConvertToWebP): static
    {
        $this->alsoConvertToWebP = $alsoConvertToWebP;
        return $this;
    }

    public function setQualityReductionStep(?float $quality_reduction_step = 0.1): static
    {
        $this->quality_reduction_step = $quality_reduction_step;
        return $this;
    }

    protected function __construct()
    {
        $this->getImageResizerLib();
        $this->maxWidth = Config::inst()->get(ScaledUploads::class, 'max_width');
        $this->maxHeight = Config::inst()->get(ScaledUploads::class, 'max_height');
        $this->maxFileSizeInMb = Config::inst()->get(ScaledUploads::class, 'max_size_in_mb');
        $this->quality = Config::inst()->get(ScaledUploads::class, 'default_quality');
        $this->alsoConvertToWebP = Config::inst()->get(static::class, 'also_convert_to_webp');

    }

    /**
     * e.g. providing `['___']` will exclude all files with `___` in them.
     */
    public function patternsToSkip(array $array)
    {
        $this->patternsToSkip = $array;
    }

    public function run(string $fullDirPath, ?bool $dryRun = true, ?bool $verbose = true)
    {

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fullDirPath), RecursiveIteratorIterator::SELF_FIRST);
        $hasher = FileHasher::create();
        foreach ($files as $file) {
            if (in_array(strtolower($file->getExtension()), $this->imageExtensions)) {
                foreach ($this->patternsToSkip as $pattern) {
                    if (strpos($file, $pattern) !== false) {
                        continue 2;
                    }
                }
                $oldPath = $file->getPathname();
                $oldImage = $this->getDbImageFromPath($oldPath);
                try {
                    $newFilePath = $this->resizeImageToSizeAndDimensions(
                        $file->getPathname(),
                        $dryRun,
                        $verbose,
                    );
                } catch (Exception $e) {
                    echo 'ERROR! ' . print_r($file, 1) . ' could not be resized!' . PHP_EOL;
                    continue;
                }
                if ($newFilePath) {

                    $file = new SplFileInfo($newFilePath);
                    $oldImage->setFromLocalFile($newFilePath);
                    $isPublished = $oldImage->isPublished();
                    $oldImage->write();
                    if ($isPublished) {
                        $oldImage->publishSingle();
                    }
                } else {
                    $newFilePath = $oldPath;
                }
                $newDbImage = $this->getDbImageFromPath($newFilePath);
                if (!$newDbImage || !$newDbImage->exists()) {
                    echo 'ERROR! ' . $file . ' is not in the database!' . PHP_EOL;
                    $newDbImage = Image::create();
                    $newDbImage->setFromLocalFile($newFilePath);
                    $newDbImage->write();
                    $newDbImage->publishSingle();
                }
                $hasher->run($newDbImage, $dryRun, $dryRun);
            }
        }
    }

    public function resizeImageToSizeAndDimensions(SplFileInfo $file, ?bool $dryRun = true, ?bool $verbose = true): string|null
    {
        $needsResize = true;
        $retunValue = null;
        $quality = $this->quality;
        // Get MIME type, width, and height, path and path without extension
        $path = $file->getPathname();
        $imageInfo = getimagesize($path);
        if (!$imageInfo) {
            user_error("Error: Could not get image info.\n");
            return null;
        }
        $mimeType = $imageInfo['mime'];
        $width = $imageInfo[0];
        $height = $imageInfo[1];

        // Get path without extension
        $pathInfo = pathinfo($path);
        $pathWithoutExtension = $pathInfo['dirname'] . '/' . $pathInfo['filename'];

        if ($width <= $this->maxWidth && $height <= $this->maxHeight) {
            $newWidth = $width;
            $newHeight = $height;
            $needsResize = false;
        } else {
            $resizeMultiplier = min($this->maxWidth / $width, $this->maxHeight / $height);
            $newWidth = (int) round($width * $resizeMultiplier);
            $newHeight = (int) round($height * $resizeMultiplier);
        }

        if ($this->useImagick) {
            user_error('You will have to manually run imagick.');
            return null;
        } elseif ($this->useGd) {

            // Load the image
            switch ($mimeType) {
                case 'image/jpeg':
                    $sourceImage = imagecreatefromjpeg($path);
                    break;
                case 'image/png':
                    $sourceImage = imagecreatefrompng($path);
                    break;
                case 'image/gif':
                    $sourceImage = imagecreatefromgif($path);
                    break;
                case 'image/webp':
                    $sourceImage = imagecreatefromwebp($path);
                    break;
                default:
                    return null; // Unsupported format
            }
            if ($sourceImage) {
                if ($needsResize) {
                    // Resize the image
                    if (! $dryRun) {
                        if ($dryRun) {
                            echo "-- DRY RUN: $path ({$width}x{$height}) resize to {$newWidth}x{$newHeight}" . PHP_EOL;
                        }
                        //set up the image object only
                        $newImage = imagecreatetruecolor($newWidth, $newHeight);
                        // add source image to new image
                        imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                    }

                } else {
                    $newImage = $sourceImage;
                }
                imagedestroy($sourceImage);
                unset($sourceImage);
                if ($this->alsoConvertToWebP) {
                    if ($mimeType !== 'image/webp') {
                        $webpImagePath = $pathWithoutExtension . '.webp';
                        if (file_exists($webpImagePath)) {
                            echo 'WebP already exists: ' . $webpImagePath . PHP_EOL;
                        } else {
                            if ($dryRun) {
                                echo '-- DRY RUN: Would create WebP: ' . $webpImagePath . PHP_EOL;
                            } else {
                                // Convert to WebP and save
                                imagewebp($newImage, $webpImagePath, -1);

                                // Check if WebP is smaller
                                if (filesize($webpImagePath) < filesize($path)) {
                                    unlink($path); // Delete original image
                                    $returnValue = $webpImagePath;
                                    $path = $webpImagePath;
                                    $newImage = imagecreatefromwebp($webpImagePath);
                                    $mimeType = 'image/webp';
                                } else {
                                    unlink($webpImagePath); // Delete WebP image - no required - as it is bigger
                                }
                            }
                        }
                    }
                }

                $sizeCheck = $this->isFileSizeGreaterThan($path);
                $step = 1;
                while ($sizeCheck && $step > 0) {
                    if ($dryRun) {
                        echo '-- DRY RUN: Would reduce quality to ' . $quality . PHP_EOL;
                        $step = 0;
                    } else {
                        // Save the image
                        switch ($mimeType) {
                            case 'image/jpeg':
                                $jpgQuality = $quality ? round($quality * 100 * $step) : -1;
                                imagejpeg($newImage, $path, $jpgQuality);
                                break;
                            case 'image/png':
                                $pngQuality = $quality ? round($quality * 9 * $step) : -1;
                                imagepng($newImage, $path, $pngQuality);
                                break;
                            case 'image/gif':
                                imagegif($newImage, $path);
                                break;
                            case 'image/webp':
                                $webpQuality = $quality ? round($quality * 99 * $step) : -1;
                                imagewebp($newImage, $path, $webpQuality);
                                break;
                        }
                        $sizeCheck = $this->isFileSizeGreaterThan($path);
                        if (! $quality) {
                            $quality = $this->quality;
                            if (!$quality) {
                                $quality = 0.77;
                            }
                        }
                        $step -= $this->quality_reduction_step;
                    }
                }
                // Free up memory
                imagedestroy($newImage);
                unset($newImage);
            } else {
                user_error("Error: Could not load image.\n");
            }
        } else {
            user_error("Error: Neither Imagick nor GD is installed.\n");
        }
        return $retunValue;
    }


    protected function getImageResizerLib()
    {
        if ($this->useImagick || $this->useGd) {
            return;
        }
        // preferred...
        if (extension_loaded('gd')) {
            $this->useGd = true;
        } elseif (extension_loaded('imagick')) {
            $this->useImagick = true;
        } else {
            exit("Error: Neither Imagick nor GD is installed.\n");
        }
    }

    protected function isFileSizeGreaterThan(string $filePath): ?float
    {
        $fileSize = filesize($filePath);
        $maxSize = $this->maxFileSizeInMb * 1024 * 1024;
        if ($fileSize > $maxSize) {
            return round(($fileSize - $maxSize) / $maxSize * 100);
        }
        return null;
    }

    protected function getOutputPath(string $path): string
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION));
    }

    protected function getDbImageFromPath(string $path): ?Image
    {
        $dbPath = str_replace(ASSETS_PATH, '', $path);
        return Image::get()->filter(['File.Filename' => $dbPath])->first();

    }


}



// KEEP!
// KEEP!
// KEEP!
// KEEP!
// KEEP!
// /** @var \Imagick $Image */
// $image = new \Imagick($img);
// $image->resizeImage($newWidth, $newHeight, Imagick::FILTER_LANCZOS, 1);
// $outputFormat = $this->getOutputPath($path);
// try {

//     // Set the compression quality for JPEG
//     if ($outputFormat === 'jpeg' || $outputFormat === 'jpg') {
//         $jpgQuality = round($quality * 99);
//         $image->setImageCompression(Imagick::COMPRESSION_JPEG);
//         $image->setImageCompressionQuality($jpgQuality);
//     }

//     // Set the format to PNG for output if needed
//     if ($outputFormat === 'png') {
//         $pngQuality = round($quality * 9);
//         $image->setImageFormat('png');
//         $image->setImageCompressionQuality($pngQuality);
//     }

//     // For GIF, you may want to handle animations
//     if ($outputFormat === 'gif') {
//         $image = $image->coalesceImages();
//         foreach ($image as $frame) {
//             $frame->resizeImage($newWidth, $newHeight, Imagick::FILTER_LANCZOS, 1);
//             $frame->setImageCompressionQuality($quality);
//         }
//         $imagick = $image->deconstructImages();
//         $imagick->setImageFormat('gif');
//     }

//     // Write the image back to the file
//     $image->writeImages($path, true);

//     // Clear memory
//     $imagick->clear();
//     $imagick->destroy();

//     return true;
// } catch (ImagickException $e) {
//     error_log("An error occurred while compressing the image: " . $e->getMessage());
//     return false;
// }

// KEEP!
// KEEP!
// KEEP!
// KEEP!
// KEEP!
