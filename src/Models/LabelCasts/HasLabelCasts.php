<?php

namespace Condoedge\Utils\Models\LabelCasts;

use Condoedge\Utils\Casts\FileArray;

/**
 * @param array<string, \Condoedge\Utils\Models\LabelCasts\AbstractLabelCast> $labelCasts
 */
trait HasLabelCasts 
{
    public function getLabelCastInstance($attr, $castedValue = null)
    {
        if (!property_exists($this, 'labelCasts')) {
            return null;
        }

        $class = $this->labelCasts[$attr] ?? $this->getDefaultLabelCasts($attr, $castedValue) ?? null;

        if (!$class) {
            return null;
        }

        $options = is_array($class) ? $class[1] : [];
        $class = is_array($class) ? $class[0] : $class;

        return new $class($this, $options);
    }

    public function getLabelAttr($attr)
    {
        $cast = $this->getLabelCastInstance($attr, $this->getAttribute($attr));

        $rawAttribute = $this->getAttributes()[$attr];

        return $cast ? $cast->getLabel($this->getAttribute($attr) ?? null, $attr) : $rawAttribute;
    }

    public function getCastedLabel($attr, $value)
    {
        $model = $this;

        $cast = $model->getLabelCastInstance($attr, $value);

        return $cast ? $cast->getLabel($value, $attr) : $value;
    }

    protected function getDefaultTypeOfAttribute($attr, $castedValue = null)
    {
        $cast = $this->casts[$attr] ?? null;

        if (!$cast) return null;

        if ($cast == 'boolean' || $cast == 'bool') {
            return 'boolean';
        }

        if ($cast == FileArray::class) {
            return 'file_array';
        }

        if ($cast == 'date' || $cast == 'datetime' || $cast == 'custom_datetime') {
            return 'carbon';
        }

        if (enum_exists($cast) || $castedValue instanceof \BackedEnum) {
            return 'enum';
        }

        return is_string($cast) ? $cast : null;
    }

    protected function getDefaultLabelCasts($attr, $castedValue = null)
    {
        $type = $this->getDefaultTypeOfAttribute($attr, $castedValue);

        $defaultTypes = [
            'enum' => \Condoedge\Utils\Models\LabelCasts\EnumLabelCast::class,
            'relationship' => \Condoedge\Utils\Models\LabelCasts\RelationshipLabelCast::class,
            'carbon' => \Condoedge\Utils\Models\LabelCasts\CarbonLabelCast::class,
            'boolean' => \Condoedge\Utils\Models\LabelCasts\YesNoLabelCast::class,
            'file_array' => \Condoedge\Utils\Models\LabelCasts\ArrayFileLabelCast::class,
            'array_image' => \Condoedge\Utils\Models\LabelCasts\ArrayImageLabelCast::class,  
        ];

        return $defaultTypes[$type] ?? null;
    }
}