<?php

namespace Condoedge\Utils\Models\Traits;

use Condoedge\Utils\Models\UserSetting;

trait HasUserSettings
{
    /* RELATIONSHIPS */
    public function getSetting($name)
    {
        return $this->hasOne(UserSetting::class)->where('name', $name);
    }

    public function settings()
    {
        return $this->hasMany(UserSetting::class);
    }

    /* ACTIONS */
    public function saveSetting($name, $value)
    {
        if (!($setting = $this->getSetting($name)->first())) {
            $setting = new UserSetting();
            $setting->name = $name;
            $setting->setUserId($this->id);
        }
        
        $setting->value = $value;
        $setting->save();
    }

    /* CALCULATED FIELDS */
    public function getSettingValue($name)
    {
        $setting = $this->getSetting($name)->first();
        
        return $setting ? $setting->value : null;
    }
}
