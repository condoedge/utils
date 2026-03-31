<?php

use Illuminate\Support\Collection;

if (!function_exists("_CheckAllItems")) {
    function _CheckAllItems()
    {
        $id = 'checkall-checkbox' . uniqid();
        
        return _Checkbox()->class('pl-3 pt-4 mb-0')->id($id)->emit('checkAllItems')->run('() => {checkAllCheckboxes("' . $id . '");}')->attr([
            'onclick' => 'event.stopPropagation()',
        ]);
    }
}

if (!function_exists('_CheckSingleItem')) {
    function _CheckSingleItem($itemId)
    {
        return _Rows(_Checkbox()->class('mb-0 child-checkbox')->emit('checkItemId', ['id' => $itemId]))
            ->attr([
                'onclick' => 'event.stopPropagation()',
            ]);
    }
}

if (!function_exists('_DragIcon')) {
    function _DragIcon()
    {
        return _Html()->icon(_Svg('selector')->class('w-8 h-8 text-gray-400'))->class('cursor-move');
    }
}

if (!function_exists('_CheckButton')) {
    function _CheckButton($label = '')
    {
        return _Button($label)->config(['withCheckedItemIds' => true]);
    }
}

if (!function_exists('_InputSearch')) {
    function _InputSearch()
    {
        return _Input()->icon(_Sax('search-normal'))->inputClass('px-6 py-4');
    }
}


if (!function_exists('_JsComponentWhen')) {
    /**
     * Swap entire Kompo element trees based on a field's value.
     */
    function _JsComponentWhen($fieldName, array|Collection $map, $default = null)
    {
        $map = $map instanceof Collection ? $map->all() : $map;
        $map = array_filter($map);
        $children = [];

        foreach ($map as $key => $element) {
            $wrapper = is_array($element) ? _Rows($element) : $element;
            $wrapper->jsShowWhen($fieldName, (string) $key);
            $children[] = $wrapper;
        }

        if ($default !== null) {
            $defaultWrapper = is_array($default) ? _Rows($default) : $default;
            $allKeys = array_map('strval', array_keys($map));
            $defaultWrapper->jsHideWhenIn($fieldName, $allKeys);
            $children[] = $defaultWrapper;
        }

        return _Rows($children);
    }
}

if (!function_exists('_JsTextWhen')) {
    /**
     * Sugar for _JsComponentWhen when map values are plain text strings.
     */
    function _JsTextWhen($fieldName, array|Collection $textMap, $default = null)
    {
        $textMap = $textMap instanceof Collection ? $textMap->all() : $textMap;
        $textMap = array_filter($textMap);

        $elementMap = [];
        foreach ($textMap as $key => $text) {
            $elementMap[$key] = _Html($text);
        }

        return _JsComponentWhen(
            $fieldName,
            $elementMap,
            $default !== null ? _Html($default) : null
        );
    }
}