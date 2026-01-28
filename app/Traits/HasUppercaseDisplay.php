<?php

namespace App\Traits;

use App\Helpers\TextHelper;

trait HasUppercaseDisplay
{
    /**
     * Get an attribute with uppercase formatting applied
     * 
     * @param string $key
     * @return mixed
     */
    public function getFormattedAttribute(string $key)
    {
        $value = $this->getAttribute($key);
        
        if (is_string($value)) {
            return TextHelper::formatText($value);
        }
        
        return $value;
    }

    /**
     * Override getAttribute to apply formatting to string attributes
     * 
     * @param string $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);
        
        // Only format string attributes, skip relations and special fields
        if (is_string($value) && !in_array($key, $this->getExcludedFromFormatting())) {
            return TextHelper::formatText($value);
        }
        
        return $value;
    }

    /**
     * Get list of attributes excluded from formatting
     * 
     * @return array
     */
    protected function getExcludedFromFormatting(): array
    {
        return array_merge([
            'id',
            'created_at',
            'updated_at',
            'deleted_at',
            'password',
            'remember_token',
            'email',
            'email_verified_at',
        ], $this->excludedFromFormatting ?? []);
    }
}
