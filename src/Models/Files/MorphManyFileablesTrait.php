<?php

namespace Condoedge\Utils\Models\Files;

use Condoedge\Utils\Models\Files\FileableFile;

trait MorphManyFileablesTrait
{
    /* RELATIONS */
    public function fileable()
    {
        return $this->morphOne(FileableFile::class, 'fileable');
    }

    public function fileables()
    {
        return $this->morphMany(FileableFile::class, 'fileable');
    }

    /* CALCULATED FIELDS */
    public function getRelatedFiles()
    {
        return $this->files->concat($this->getPureRelatedFiles());
    }

    public function getPureRelatedFiles()
    {
        return $this->fileables->map->file;
    }

    /* ACTIONS */
    public function deleteFileables()
    {
        $this->fileables()->delete();
    }

    public function associateFile($file)
    {
        $link = new FileableFile();
        $link->file_id = $file->id;
        $this->fileables()->save($link);
    }

    /* ELEMENTS */
}
