<?php

namespace Sunnysideup\ResizeAllImages\Api;

use Axllent\ScaledUploads\Api\Resizer;
use Exception;
use SplFileInfo;
use SilverStripe\Assets\Image;

class ResizeAssetsRunner extends Resizer
{
    protected bool $useImagick = false;

    protected bool $useGd = false;

    protected array $imageExtensions;


    protected FileHasher $hasher;

    private static array $image_extensions = [
        'jpg',
        'jpeg',
        'png',
        'gif',
        'webp',
    ];

    public function setGdAsConverter()
    {
        $this->useGd = true;
        $this->useImagick = false;
        return $this;
    }

    public function setImageExtensions(array $array): static
    {
        $this->imageExtensions = $array;
        return $this;
    }

    protected function __construct()
    {
        parent::__construct();
        $this->imageExtensions = $this->config()->get('image_extensions');
        $this->getImageResizerLib();
        $this->hasher = FileHasher::create();

    }

    public function runFromFilesystemFileOuter(SplFileInfo $file): void
    {
        $oldPath = $file->getPathname();
        $dbImage = $this->getDbImageFromPath($oldPath);
        try {
            $newFilePath = $this->runFromFilesystemFile($file);
        } catch (Exception $e) {
            echo 'ERROR! ' . print_r($file, 1) . ' could not be resized!' . PHP_EOL;
            return;
        }
        if ($newFilePath) {
            $file = new SplFileInfo($newFilePath);
            $dbImage->setFromLocalFile($newFilePath, $dbImage->getFilename());
            $this->saveAndPublish($dbImage);
        } else {
            $newFilePath = $oldPath;
        }
        $newDbImage = $this->getDbImageFromPath($newFilePath);

        $this->hasher->run($newDbImage, $this->dryRun, true);
    }

    public function runFromFilesystemFile(SplFileInfo $file): string|null
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
        $extension = $pathInfo['extension'];
        if (! $this->canBeConverted($path, $extension)) {
            return null;
        }

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
                    if (! $this->dryRun) {
                        if ($this->dryRun) {
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
                if ($this->useWebp) {
                    if ($mimeType !== 'image/webp') {
                        $webpImagePath = $pathWithoutExtension . '.webp';
                        if (file_exists($webpImagePath)) {
                            echo 'WebP already exists: ' . $webpImagePath . PHP_EOL;
                        } else {
                            if ($this->dryRun) {
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

                $sizeCheck = $this->fileIsTooBig($path);
                $step = 1;
                while ($sizeCheck && $step > 0) {
                    if ($this->dryRun) {
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
                                $webpQuality = $quality ? round($quality * 100 * $step) : -1;
                                imagewebp($newImage, $path, $webpQuality);
                                break;
                        }
                        $sizeCheck = $this->fileIsTooBig($path);
                        if (! $quality) {
                            $quality = $this->quality;
                            if (!$quality) {
                                $quality = 0.77;
                            }
                        }
                        $step -= $this->qualityReductionIncrement;
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

    protected function getDbImageFromPath(string $path): ?Image
    {
        $dbPath = str_replace(ASSETS_PATH, '', $path);
        $newDbImage = Image::get()->filter(['File.Filename' => $dbPath])->first();
        if (!$newDbImage || !$newDbImage->exists()) {
            echo 'ERROR! ' . $path . ' is not in the database!' . PHP_EOL;
            $newDbImage = Image::create();
            $newDbImage->setFromLocalFile($path, $dbPath);
            $newDbImage->write();
            $newDbImage->publishSingle();
        }
        return $newDbImage;

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
