<?php
namespace App\Http\Libraries;

use Illuminate\Support\Facades\Storage;
use App\H5PContent;

class H5PFileVersioner
{
    protected $originalH5P, $newH5P;

    protected $h5pDisk = 'h5p-uploads';

    public function __construct(H5PContent $originalH5P, H5PContent $newH5P)
    {
        $this->originalH5P = $originalH5P;
        $this->newH5P = $newH5P;
    }

    public function copy()
    {
        $originalPath = DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . $this->originalH5P->id;

        $storage = Storage::disk($this->h5pDisk);
        $originalH5P = $this->originalH5P;
        $newH5P = $this->newH5P;

        //Create all directories
        collect($storage->allDirectories($originalPath))
            ->each(function ($originalDirectory) use ($originalH5P, $newH5P, $storage) {
                $theNewDirectory = str_replace($originalH5P->id, $this->newH5P->id, $originalDirectory);
                $storage->makeDirectory($theNewDirectory);
            });

        // Copy all files
        collect($storage->allFiles($originalPath))
            ->each(function ($theOriginalFile) use ($originalH5P, $newH5P, $storage) {
                $theNewFile = str_replace($originalH5P->id, $newH5P->id, $theOriginalFile);
                $storage->copy($theOriginalFile, $theNewFile);
            });

        return $this;
    }
}
