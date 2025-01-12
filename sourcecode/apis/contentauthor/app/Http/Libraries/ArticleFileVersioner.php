<?php

namespace App\Http\Libraries;

use App\Article;
use Illuminate\Support\Facades\Storage;
use App\Libraries\NDLA\Importers\Handlers\Helpers\HTMLHelper;

class ArticleFileVersioner
{
    use HTMLHelper;

    protected $originalArticle, $newArticle;

    protected $disk = 'article-uploads';

    public function __construct(Article $originalArticle, Article $newArticle)
    {
        $this->originalArticle = $originalArticle;
        $this->newArticle = $newArticle;
    }

    public function copy()
    {
        $originalPath = DIRECTORY_SEPARATOR . $this->originalArticle->id;

        $originalFiles = Storage::disk($this->disk)->files($originalPath);

        foreach ($originalFiles as $originalFile) {
            $newPath = str_replace($this->originalArticle->id, $this->newArticle->id, $originalFile);
            Storage::disk($this->disk)->copy($originalFile, $newPath);
        }

        return $this;
    }

    public function updateDatabase()
    {
        $originalFiles = $this->originalArticle->files;

        $originalFiles->each(function ($file) {
            $newFile = $file->replicate();
            $this->newArticle->files()->save($newFile);
        });

        return $this;
    }

    public function rewriteFilePath()
    {
        $dom = new \DOMDocument();
        $fullContent = $this->addHtml5($this->newArticle->content);
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $fullContent);
        libxml_clear_errors();
        $images = $dom->getElementsByTagName('img');
        foreach ($images as $image) {
            $src = $image->getAttribute('src');
            $src = str_replace($this->originalArticle->id . '/', $this->newArticle->id . '/', $src);
            $image->setAttribute('src', $src);
        }

        $this->newArticle->content = $this->getBody($dom->saveHTML());

        $this->newArticle->save();

        return $this;
    }

    public function getNewArticle()
    {
        return $this->newArticle;
    }
}
