<?php

namespace Condoedge\Utils\Models\Files;

use Condoedge\Utils\Facades\FileModel;

trait MorphManyFilesTrait
{
    /* RELATIONS */
    public function file()
    {
        return $this->morphOne(FileModel::getClass(), 'fileable');
    }

    public function files()
    {
        return $this->morphMany(FileModel::getClass(), 'fileable');
    }

    /* CALCULATED FIELDS */
    protected function defaultImageUrl()
    {
        return avatarFromText($this->getNameDisplay());
    }

    public function getMainImageUrl()
    {
        return publicUrlFromFileModel($this->file, $this->defaultImageUrl());
    }

    /* ACTIONS */
    public function deleteFiles()
    {
        $this->files->each->delete();
    }

    /* ELEMENTS */
    public function getMainImagePill($class = null)
    {
        return _ImgPill($this->getMainImageUrl(), $class);
    }
}
