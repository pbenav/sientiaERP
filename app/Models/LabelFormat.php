<?php

namespace App\Models;

use App\Traits\HasUppercaseDisplay;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabelFormat extends Model
{
    use HasFactory, SoftDeletes, HasUppercaseDisplay;

    protected $fillable = [
        'nombre',
        'tipo',
        'document_width',
        'document_height',
        'label_width',
        'label_height',
        'labels_per_row',
        'labels_per_column',
        'labels_per_sheet',
        'margin_top',
        'margin_bottom',
        'margin_left',
        'margin_right',
        'horizontal_spacing',
        'vertical_spacing',
        'manufacturer',
        'model_number',
        'activo',
    ];

    protected $casts = [
        'document_width' => 'decimal:2',
        'document_height' => 'decimal:2',
        'label_width' => 'decimal:2',
        'label_height' => 'decimal:2',
        'labels_per_row' => 'integer',
        'labels_per_column' => 'integer',
        'labels_per_sheet' => 'integer',
        'margin_top' => 'decimal:2',
        'margin_bottom' => 'decimal:2',
        'margin_left' => 'decimal:2',
        'margin_right' => 'decimal:2',
        'horizontal_spacing' => 'decimal:2',
        'vertical_spacing' => 'decimal:2',
        'activo' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($format) {
            // Auto-calcular labels_per_column y labels_per_sheet
            if (empty($format->labels_per_column)) {
                $availableHeight = $format->document_height - $format->margin_top - $format->margin_bottom;
                $format->labels_per_column = floor($availableHeight / ($format->label_height + $format->vertical_spacing));
            }
            
            $format->labels_per_sheet = $format->labels_per_row * $format->labels_per_column;
        });
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeStandard($query)
    {
        return $query->where('tipo', 'standard');
    }

    public function scopeCustom($query)
    {
        return $query->where('tipo', 'custom');
    }
}
