<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (c) 2022-2026 pbenav <info@sientia.com>

namespace App\Http\Controllers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DocumentationController extends Controller
{
    /**
     * Display the documentation page.
     */
    public function index($slug = 'user-manual')
    {
        $lang = app()->getLocale();
        $fallbackLang = 'es';

        $path = resource_path("docs/{$lang}/{$slug}.md");

        // Fallback to default lang if not found in current lang
        if (!File::exists($path)) {
            $path = resource_path("docs/{$fallbackLang}/{$slug}.md");
        }

        // If still not found, return 404
        if (!File::exists($path)) {
            abort(404, "Documento no encontrado: {$slug}");
        }

        $contentMd = File::get($path);

        // Convert Markdown to HTML using Laravel's built-in Str::markdown (CommonMark)
        $contentHtml = Str::markdown($contentMd, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        // Define the menu structure
        $menu = [
            'es' => [
                'user-manual'  => 'Manual de Usuario',
                'admin-manual' => 'Manual de Administración',
                'installation' => 'Guía de Instalación',
                'verifactu'    => 'Veri*Factu',
                'facturae'     => 'FacturaE (Factura Electrónica)',
                'architecture' => 'Arquitectura Técnica',
                'changelog'    => 'Historial de Cambios',
            ],
            'en' => [
                'user-manual'  => 'User Manual',
                'admin-manual' => 'Admin Manual',
                'installation' => 'Installation Guide',
                'verifactu'    => 'Veri*Factu',
                'facturae'     => 'FacturaE (Electronic Invoice)',
                'architecture' => 'Technical Architecture',
                'changelog'    => 'Changelog',
            ],
        ];

        $currentMenu = $menu[$lang] ?? $menu[$fallbackLang];

        return view('docs.index', [
            'content' => $contentHtml,
            'slug'    => $slug,
            'menu'    => $currentMenu,
        ]);
    }
}
