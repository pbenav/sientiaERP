<?php

namespace Database\Seeders;

use App\Models\LabelFormat;
use Illuminate\Database\Seeder;

class LabelFormatSeeder extends Seeder
{
    public function run(): void
    {
        $formats = [
            // MATTIO MTT7040116 - El formato del usuario
            [
                'nombre' => 'MATTIO MTT7040116',
                'tipo' => 'standard',
                'document_width' => 210,
                'document_height' => 297,
                'label_width' => 48.5,
                'label_height' => 25.4,
                'labels_per_row' => 4,
                'labels_per_column' => 11,
                'labels_per_sheet' => 44,
                'margin_top' => 13.5,
                'margin_bottom' => 13.5,
                'margin_left' => 4.65,
                'margin_right' => 4.65,
                'horizontal_spacing' => 2.5,
                'vertical_spacing' => 0,
                'manufacturer' => 'MATTIO',
                'model_number' => 'MTT7040116',
                'activo' => true,
            ],
            
            // Avery L7160 - Popular 21 etiquetas
            [
                'nombre' => 'Avery L7160',
                'tipo' => 'standard',
                'document_width' => 210,
                'document_height' => 297,
                'label_width' => 63.5,
                'label_height' => 38.1,
                'labels_per_row' => 3,
                'labels_per_column' => 7,
                'labels_per_sheet' => 21,
                'margin_top' => 15.5,
                'margin_bottom' => 15.5,
                'margin_left' => 7,
                'margin_right' => 7,
                'horizontal_spacing' => 2.5,
                'vertical_spacing' => 0,
                'manufacturer' => 'Avery',
                'model_number' => 'L7160',
                'activo' => true,
            ],
            
            // Avery L7163 - 14 etiquetas
            [
                'nombre' => 'Avery L7163',
                'tipo' => 'standard',
                'document_width' => 210,
                'document_height' => 297,
                'label_width' => 99.1,
                'label_height' => 38.1,
                'labels_per_row' => 2,
                'labels_per_column' => 7,
                'labels_per_sheet' => 14,
                'margin_top' => 15.5,
                'margin_bottom' => 15.5,
                'margin_left' => 6,
                'margin_right' => 6,
                'horizontal_spacing' => 0,
                'vertical_spacing' => 0,
                'manufacturer' => 'Avery',
                'model_number' => 'L7163',
                'activo' => true,
            ],
            
            // Herma 4360 - 24 etiquetas
            [
                'nombre' => 'Herma 4360',
                'tipo' => 'standard',
                'document_width' => 210,
                'document_height' => 297,
                'label_width' => 70,
                'label_height' => 36,
                'labels_per_row' => 3,
                'labels_per_column' => 8,
                'labels_per_sheet' => 24,
                'margin_top' => 8.5,
                'margin_bottom' => 8.5,
                'margin_left' => 0,
                'margin_right' => 0,
                'horizontal_spacing' => 0,
                'vertical_spacing' => 0,
                'manufacturer' => 'Herma',
                'model_number' => '4360',
                'activo' => true,
            ],
        ];

        foreach ($formats as $format) {
            LabelFormat::firstOrCreate(
                ['nombre' => $format['nombre']],
                $format
            );
        }
    }
}
