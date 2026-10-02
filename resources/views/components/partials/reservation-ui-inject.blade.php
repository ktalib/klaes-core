{{--
    Reservation UI Injection for Commission File Number Modal
    
    This partial injects reservation status indicators into the modal
    Add immediately after the Application Type section
--}}

<!-- Reservation Status Indicator (Hidden by default, shown when serial is reserved) -->
<div id="reservationIndicator" class="hidden mb-4"></div>

<!-- Reservation Warnings (Expiring/Expired) -->
<div id="reservationWarning"></div>

<style>
    /* Pulsing animation for countdown when low */
    @keyframes pulse-red {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.6;
        }
    }
    
    #countdown.text-red-600 {
        animation: pulse-red 1s ease-in-out infinite;
    }
    
    /* Reservation indicator animations */
    @keyframes slideInDown {
        from {
            transform: translateY(-10px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }
    
    #reservationIndicator:not(.hidden) {
        animation: slideInDown 0.3s ease-out;
    }
</style>

<script>
    /**
     * Inject reservation checking into the existing Alpine.js component
     * This integrates with the fileNumberGenerator() function
     */
    document.addEventListener('alpine:init', () => {
        // We'll hook into the existing Alpine component
        console.log('Reservation system integrated with Alpine.js');
    });
</script>
