<script>
    document.addEventListener('keydown', function(event) {
        // F2 para TPV
        if (event.key === 'F2') {
            event.preventDefault();
            window.location.href = "{{ \App\Filament\Resources\TicketResource::getUrl('create') }}";
        }
    });
</script>
