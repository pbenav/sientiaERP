<script>
    document.addEventListener('keydown', function(event) {
        // Ctrl+F2 para TPV
        if (event.ctrlKey && event.key === 'F2') {
            event.preventDefault();
            window.location.href = "{{ \App\Filament\Resources\TicketResource::getUrl('create') }}";
        }
    });
</script>
