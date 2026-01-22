<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Listen for keydown events globally
        document.addEventListener('keydown', function(event) {
            // Check if it's the Enter key
            if (event.key === 'Enter') {
                // Check if the target is an input or select within our repeater
                const target = event.target;
                const isRepeaterInput = target.closest('.document-lines-repeater');
                
                if (isRepeaterInput && (target.tagName === 'INPUT' || target.tagName === 'SELECT')) {
                    // Prevent default form submission or newline
                    event.preventDefault();
                    
                    // Find all focusable inputs in the repeater
                    const repeater = target.closest('.document-lines-repeater');
                    const inputs = Array.from(repeater.querySelectorAll('input:not([type="hidden"]):not([disabled]), select:not([disabled])'));
                    
                    // Find current index
                    const currentIndex = inputs.indexOf(target);
                    
                    // Focus next input if exists
                    if (currentIndex > -1 && currentIndex < inputs.length - 1) {
                        inputs[currentIndex + 1].focus();
                    }
                }
            }
        });
    });
</script>
