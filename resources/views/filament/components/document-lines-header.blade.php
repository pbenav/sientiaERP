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

<div class="document-lines-header-container" style="display: flex; align-items: center; gap: 0; padding: 1px 0; background: #f3f4f6; border-bottom: 1px solid #e5e7eb; font-weight: 600; font-size: 0.7rem; color: #374151; text-transform: uppercase; letter-spacing: 0.025em;">
    <div style="display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 0; flex: 1; padding: 0;">
        @if($isLabel)
            <div style="grid-column: span 2 / span 2; padding-left: 0.5rem;">CÓD.</div>
            <div style="grid-column: span 9 / span 9;">DESCRIPCIÓN</div>
            <div style="grid-column: span 1 / span 1; text-align: center; padding-right: 0.5rem;">CANT.</div>
        @else
            <div style="grid-column: span 2 / span 2; text-align: center; padding-left: 5rem;">CÓD.</div>
            <div style="grid-column: span 4 / span 4; text-align: center; padding-left: 5rem;">DESCRIPCIÓN</div>
            <div style="grid-column: span 1 / span 1; text-align: center; padding-left: 10.5rem;">CANT.</div>
            <div style="grid-column: span 1 / span 1; text-align: center; padding-left: 10.5rem;">PRECIO</div>
            <div style="grid-column: span 1 / span 1; text-align: center; padding-left: 10.5rem;">%DTO</div>
            <div style="grid-column: span 2 / span 2; text-align: center; padding-left: 10.5rem;">IMPORTE</div>
            <div style="grid-column: span 1 / span 1; text-align: center; padding-left: 4.5rem;">%IVA</div>
        @endif
    </div>
    <div style="width: 50px;"></div>
</div>
