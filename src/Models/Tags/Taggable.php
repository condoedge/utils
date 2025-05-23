<?php

namespace Condoedge\Utils\Models\Tags;

use Condoedge\Utils\Models\ModelBase;

class Taggable extends ModelBase
{
	protected $table = 'taggable_tag';

	/* RELATIONS */
	public function tag()
	{
		return $this->belongsTo(Tag::class);
	}

    public function taggable()
    {
        return $this->morphTo();
    }
}
