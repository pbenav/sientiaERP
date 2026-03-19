<script>
    document.addEventListener('keydown', function(event) {
        // Alt+T o F2 para TPV
        if (event.key === 'F2') {
            event.preventDefault();
            window.location.href = "{{ \App\Filament\Resources\TicketResource::getUrl('create') }}";
        }
    });
</script>
