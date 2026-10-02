/**
 * File Number Reservation System - Frontend Integration
 * 
 * This module integrates the backend reservation API with the Commission File Number modal
 * to prevent race conditions when multiple users generate file numbers simultaneously.
 * 
 * Features:
 * - Automatic reservation when modal opens
 * - Real-time countdown timer showing reservation expiration
 * - Auto-extend functionality before expiration
 * - Reservation confirmation on form submission
 * - Automatic release on modal close/cancel
 * 
 * @version 1.0.0
 * @author KLAES Development Team
 */

class FileNumberReservationManager {
    constructor() {
        this.currentReservation = null;
        this.countdownInterval = null;
        this.extendThreshold = 120; // Extend when 2 minutes remain
        this.autoExtend = true; // Enable auto-extend by default
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    }

    /**
     * Reserve a serial number for single generation
     */
    async reserveSerial(prefix, landUse, year) {
        try {
            const response = await fetch('/api/file-numbers/reserve', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken
                },
                body: JSON.stringify({ prefix, land_use: landUse, year })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (data.success) {
                this.currentReservation = {
                    uuid: data.data.reservation_uuid,
                    serialNumber: data.data.serial_number,
                    expiresAt: data.data.expires_at,
                    expiresInSeconds: data.data.expires_in_seconds,
                    prefix: prefix,
                    landUse: landUse,
                    year: year
                };

                // Start countdown timer
                this.startCountdown();

                console.log('Serial reserved:', this.currentReservation);
                return this.currentReservation;
            } else {
                throw new Error(data.message || 'Failed to reserve serial number');
            }
        } catch (error) {
            console.error('Error reserving serial:', error);
            
            // Show user-friendly error
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Reservation Failed',
                    text: error.message || 'Could not reserve serial number. Please try again.',
                    confirmButtonText: 'OK'
                });
            }
            
            throw error;
        }
    }

    /**
     * Reserve multiple serial numbers for batch generation
     */
    async reserveBatchSerials(prefix, landUse, year, quantity) {
        try {
            const response = await fetch('/api/file-numbers/reserve-batch', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken
                },
                body: JSON.stringify({ prefix, land_use: landUse, year, quantity })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (data.success) {
                this.currentReservation = {
                    uuids: data.data.reservation_uuids,
                    serialNumbers: data.data.serial_numbers,
                    startSerial: data.data.start_serial,
                    endSerial: data.data.end_serial,
                    quantity: data.data.quantity,
                    expiresAt: data.data.expires_at,
                    expiresInSeconds: data.data.expires_in_seconds,
                    prefix: prefix,
                    landUse: landUse,
                    year: year,
                    isBatch: true
                };

                // Start countdown timer
                this.startCountdown();

                console.log('Batch serials reserved:', this.currentReservation);
                return this.currentReservation;
            } else {
                throw new Error(data.message || 'Failed to reserve batch serials');
            }
        } catch (error) {
            console.error('Error reserving batch serials:', error);
            
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Batch Reservation Failed',
                    text: error.message || 'Could not reserve batch serial numbers. Please try again.',
                    confirmButtonText: 'OK'
                });
            }
            
            throw error;
        }
    }

    /**
     * Confirm a reservation after successful file generation
     */
    async confirmReservation(reservationUuid, generatedFileNumber) {
        if (!reservationUuid) {
            console.warn('No reservation UUID to confirm');
            return false;
        }

        try {
            const response = await fetch('/api/file-numbers/confirm-reservation', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken
                },
                body: JSON.stringify({ 
                    reservation_uuid: reservationUuid,
                    generated_file_number: generatedFileNumber 
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (data.success) {
                console.log('Reservation confirmed:', reservationUuid);
                return true;
            } else {
                console.error('Failed to confirm reservation:', data.message);
                return false;
            }
        } catch (error) {
            console.error('Error confirming reservation:', error);
            return false;
        }
    }

    /**
     * Confirm batch reservations after successful batch generation
     */
    async confirmBatchReservations(fileNumbers) {
        if (!this.currentReservation || !this.currentReservation.isBatch) {
            console.warn('No batch reservation to confirm');
            return false;
        }

        const promises = this.currentReservation.uuids.map((uuid, index) => {
            return this.confirmReservation(uuid, fileNumbers[index]);
        });

        try {
            await Promise.all(promises);
            console.log('All batch reservations confirmed');
            return true;
        } catch (error) {
            console.error('Error confirming batch reservations:', error);
            return false;
        }
    }

    /**
     * Release a reservation (user cancelled or closed modal)
     */
    async releaseReservation(reservationUuid = null) {
        const uuid = reservationUuid || this.currentReservation?.uuid;
        
        if (!uuid) {
            console.warn('No reservation to release');
            return false;
        }

        try {
            const response = await fetch('/api/file-numbers/release-reservation', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken
                },
                body: JSON.stringify({ reservation_uuid: uuid })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (data.success) {
                console.log('Reservation released:', uuid);
                return true;
            } else {
                console.error('Failed to release reservation:', data.message);
                return false;
            }
        } catch (error) {
            console.error('Error releasing reservation:', error);
            return false;
        }
    }

    /**
     * Release batch reservations
     */
    async releaseBatchReservations() {
        if (!this.currentReservation || !this.currentReservation.isBatch) {
            console.warn('No batch reservation to release');
            return false;
        }

        const promises = this.currentReservation.uuids.map(uuid => {
            return this.releaseReservation(uuid);
        });

        try {
            await Promise.all(promises);
            console.log('All batch reservations released');
            return true;
        } catch (error) {
            console.error('Error releasing batch reservations:', error);
            return false;
        }
    }

    /**
     * Extend reservation expiration time
     */
    async extendReservation(minutes = 10) {
        if (!this.currentReservation) {
            console.warn('No reservation to extend');
            return false;
        }

        const uuid = this.currentReservation.isBatch 
            ? this.currentReservation.uuids[0] // Extend the first one as a sample
            : this.currentReservation.uuid;

        try {
            const response = await fetch('/api/file-numbers/extend-reservation', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken
                },
                body: JSON.stringify({ reservation_uuid: uuid, minutes })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (data.success) {
                // Update expiration time
                this.currentReservation.expiresAt = data.data.new_expires_at;
                this.currentReservation.expiresInSeconds = data.data.new_expires_in_seconds;
                
                console.log('Reservation extended:', { uuid, newExpiresAt: data.data.new_expires_at });
                return true;
            } else {
                console.error('Failed to extend reservation:', data.message);
                return false;
            }
        } catch (error) {
            console.error('Error extending reservation:', error);
            return false;
        }
    }

    /**
     * Start countdown timer and display remaining time
     */
    startCountdown() {
        // Clear any existing countdown
        this.stopCountdown();

        this.countdownInterval = setInterval(() => {
            if (!this.currentReservation) {
                this.stopCountdown();
                return;
            }

            this.currentReservation.expiresInSeconds--;

            // Update UI
            this.updateCountdownDisplay();

            // Auto-extend if enabled and threshold reached
            if (this.autoExtend && this.currentReservation.expiresInSeconds === this.extendThreshold) {
                console.log('Auto-extending reservation...');
                this.extendReservation(10);
            }

            // Expired
            if (this.currentReservation.expiresInSeconds <= 0) {
                this.handleExpiration();
            }
        }, 1000);
    }

    /**
     * Stop countdown timer
     */
    stopCountdown() {
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
            this.countdownInterval = null;
        }
    }

    /**
     * Update countdown display in the UI
     */
    updateCountdownDisplay() {
        const countdownEl = document.getElementById('reservation-countdown');
        if (!countdownEl || !this.currentReservation) return;

        const seconds = this.currentReservation.expiresInSeconds;
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = seconds % 60;

        const timeString = `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
        
        // Change color based on time remaining
        let colorClass = 'text-green-600';
        if (seconds < 60) {
            colorClass = 'text-red-600';
        } else if (seconds < 180) {
            colorClass = 'text-yellow-600';
        }

        countdownEl.innerHTML = `
            <span class="${colorClass} font-semibold">
                Time remaining: ${timeString}
            </span>
        `;

        // Flash warning when time is low
        if (seconds < 30 && seconds % 2 === 0) {
            countdownEl.classList.add('animate-pulse');
        } else {
            countdownEl.classList.remove('animate-pulse');
        }
    }

    /**
     * Handle reservation expiration
     */
    handleExpiration() {
        this.stopCountdown();

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: 'Reservation Expired',
                text: 'Your serial number reservation has expired. Please close and reopen the modal to get a new reservation.',
                confirmButtonText: 'OK',
                allowOutsideClick: false
            });
        } else {
            alert('Your serial number reservation has expired. Please close and reopen the modal.');
        }

        // Clear current reservation
        this.currentReservation = null;

        // Hide countdown display
        const countdownEl = document.getElementById('reservation-countdown');
        if (countdownEl) {
            countdownEl.innerHTML = '<span class="text-red-600">Reservation Expired</span>';
        }
    }

    /**
     * Clean up on modal close
     */
    cleanup() {
        this.stopCountdown();
        
        if (this.currentReservation) {
            // Release reservation
            if (this.currentReservation.isBatch) {
                this.releaseBatchReservations();
            } else {
                this.releaseReservation();
            }
        }

        this.currentReservation = null;
        
        // Clear countdown display
        const countdownEl = document.getElementById('reservation-countdown');
        if (countdownEl) {
            countdownEl.innerHTML = '';
        }
    }

    /**
     * Check reservation status
     */
    async checkReservationStatus(reservationUuid) {
        try {
            const response = await fetch(`/api/file-numbers/reservation-status/${reservationUuid}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (data.success) {
                return data.data;
            } else {
                throw new Error(data.message || 'Failed to check reservation status');
            }
        } catch (error) {
            console.error('Error checking reservation status:', error);
            return null;
        }
    }

    /**
     * Get current reservation info
     */
    getCurrentReservation() {
        return this.currentReservation;
    }

    /**
     * Check if reservation is active
     */
    hasActiveReservation() {
        return this.currentReservation !== null && this.currentReservation.expiresInSeconds > 0;
    }
}

// Create global instance
window.fileNumberReservationManager = new FileNumberReservationManager();

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = FileNumberReservationManager;
}

console.log('File Number Reservation Manager loaded');
