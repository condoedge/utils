<?php 

namespace Condoedge\Utils\Models\Tags;

trait HasManyTagsTrait
{
	/* RELATIONS */
    public function tags()
	{
		return $this->hasMany(Tag::class);
	}
}