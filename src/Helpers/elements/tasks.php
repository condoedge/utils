<?php

if (!function_exists('_TagsMultiSelect')) {
    function _TagsMultiSelect($label = '', $relatedToModel = true)
    {
        return _TagsMultiSelectEmpty($label, $relatedToModel)
            ->options(
                currentTeam()->tags()->get()->sortBy('name')->mapWithKeys(
                    fn($tag) => [$tag->id => $tag->name]
                )
            );
    }
}

if (!function_exists('_TagsMultiSelectEmpty')) {
    function _TagsMultiSelectEmpty($label = '', $relatedToModel = true)
    {
        return _MultiSelect($label)->placeholder('Tags')->name('tags', $relatedToModel)->icon(_Sax('tag'));
    }
}