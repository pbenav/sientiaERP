@php
    $isLabel = $isLabel ?? false;
@endphp

<style>
    /* Forzar eliminación de huecos en el repetidor */
    .document-lines-repeater {
        gap: 0 !important;
    }
    .document-lines-repeater > div, 
    .document-lines-repeater > ul,
    .document-lines-repeater > div > div {
        gap: 0 !important;
    }
    .document-lines-repeater .fi-fo-repeater-item {
        margin-bottom: 0 !important;
        padding: 0 !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        border-bottom: 1px solid #e5e7eb !important;
    }
    .document-lines-repeater .fi-fo-repeater-item-header {
        border-bottom: none !important;
    }
    .document-lines-repeater .fi-fo-repeater-content {
        gap: 0 !important;
        padding: 0 !important;
    }
    /* Reducir altura de contenedores de campos */
    .document-lines-repeater .fi-fo-field-wrp {
        margin-bottom: 0 !important;
    }
</style>

<div class="document-lines-header-container" style="display: flex; align-items: center; gap: 0; padding: 4px 0; background: #f9fafb; border-bottom: 1px solid #e5e7eb; font-weight: 700; font-size: 0.65rem; color: #4b5563; text-transform: uppercase;">
    <!-- Espacio para los botones de acción a la izquierda (50px coincide con .fi-fo-repeater-item-header) -->
    <div style="width: 50px; flex-shrink: 0; border-right: 1px solid #e5e7eb; margin-right: 0.2rem;"></div>
    
    <div style="display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 0.2rem; flex: 1; padding-right: 4px;">
        @if($isLabel)
            <div style="grid-column: span 2;">CÓDIGO</div>
            <div style="grid-column: span 9;">DESCRIPCIÓN</div>
            <div style="grid-column: span 1; text-align: center;">CANT.</div>
        @else
            <div style="grid-column: span 2; padding-left: 4px;">CÓDIGO</div>
            <div style="grid-column: span 5;">PRODUCTO / DESCRIPCIÓN</div>
            <div style="grid-column: span 1; text-align: center;">CANT.</div>
            <div style="grid-column: span 1; text-align: right;">PRECIO</div>
            <div style="grid-column: span 1; text-align: center;">%DTO</div>
            <div style="grid-column: span 1; text-align: right;">BASE</div>
            <div style="grid-column: span 1; text-align: center;">IVA</div>
        @endif
    </div>
</div>
