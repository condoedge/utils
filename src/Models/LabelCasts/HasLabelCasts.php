<?php

namespace Condoedge\Utils\Models\LabelCasts;

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

    public static function getCastedLabel($attr, $value)
    {
        $model = new static;

        $cast = $model->getLabelCastInstance($attr, $value);

        return $cast ? $cast->getLabel($value, $attr) : $value;
    }

    protected function getDefaultTypeOfAttribute($attr, $castedValue = null)
    {
        $cast = $this->casts[$attr] ?? null;

        if (!$cast) return null;

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
        ];

        return $defaultTypes[$type] ?? null;
    }
}