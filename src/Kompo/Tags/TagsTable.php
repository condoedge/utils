<?php

namespace Condoedge\Utils\Kompo\Tags;

use Condoedge\Utils\Kompo\Common\Table;
use Condoedge\Utils\Models\Tags\Tag;
use Condoedge\Utils\Models\Tags\TagContextEnum;

class TagsTable extends Table
{
    protected $tagContext = TagContextEnum::ALL;
    protected $tagType = Tag::TAG_TYPE_GENERAL;
    protected $title = 'tags.manage-tag';

    public function query()
    {
        return Tag::ofType($this->tagType)->ofContext($this->tagContext)->visibleForTeam();
    }

    public function top()
    {
        return _Rows(
            _FlexBetween(
                _Html($this->title)->miniTitle(),
                _Button('tags.add-tags')->selfCreate('getTagForm')->inModal()
            )->class('mb-4'),
        );
    }

    public function headers()
    {
        return [
            _Th('general.name')->sort('name'),
            _Th(),
        ];
    }

    public function render($tag)
    {
    	return _TableRow(
            _Html($tag->name),
            _FlexEnd(
                _DeleteLink()->byKey($tag)
            ),
        )->selfUpdate('getTagForm', [
            'id' => $tag->id,
        ])->inModal();
    }

    public function getTagForm($id = null)
    {
        return new TagForm($id, [
            'tag_type' => $this->tagType,
            'tag_context' => $this->tagContext,
        ]);
    }
}
