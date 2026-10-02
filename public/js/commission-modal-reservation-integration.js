/**
 * Commission File Number Modal - Reservation System Integration
 * 
 * This script automatically integrates the reservation system with the existing
 * Commission File Number modal. Include AFTER file-number-reservation.js
 * 
 * @requires file-number-reservation.js
 * @requires Alpine.js
 */

(function() {
    'use strict';

    console.log('Commission Modal Reservation Integration: Initializing...');

    // Wait for DOM and dependencies
    document.addEventListener('DOMContentLoaded', function() {
        
        // Verify dependencies
        if (typeof reserveSerialNumber !== 'function') {
            console.error('file-number-reservation.js must be loaded first');
            return;
        }

        if (typeof Alpine === 'undefined') {
            console.error('Alpine.js is required for this integration');
            return;
        }

        /**
         * Global wrapper for file number generation with reservation
         */
        window.commissionModalReservation = {
            
            /**
             * Current reservation state
             */
            currentReservation: null,
            currentBatchReservation: null,
            
            /**
             * Reserve a single serial number
             * @param {string} prefix - File number prefix
             * @param {string} landUse - Land use code
             * @param {string|number} year - Year
             * @returns {Promise<Object>} Reservation result
             */
            async reserve(prefix, landUse, year) {
                console.log('[Reservation] Reserving serial:', { prefix, landUse, year });
                
                const result = await reserveSerialNumber(prefix, landUse, year);
                
                if (result.success) {
                    this.currentReservation = result;
                    console.log('[Reservation] Success:', result.serialNumber);
                    
                    // Show indicator if element exists
                    this.showReservationIndicator(result);
                } else {
                    console.error('[Reservation] Failed:', result.message);
                    this.showReservationWarning(result.message);
                }
                
                return result;
            },
            
            /**
             * Reserve batch serial numbers
             * @param {string} prefix - File number prefix
             * @param {string} landUse - Land use code
             * @param {string|number} year - Year
             * @param {number} quantity - Number of serials needed
             * @returns {Promise<Object>} Batch reservation result
             */
            async reserveBatch(prefix, landUse, year, quantity) {
                console.log('[Reservation] Reserving batch:', { prefix, landUse, year, quantity });
                
                const result = await reserveBatchSerialNumbers(prefix, landUse, year, quantity);
                
                if (result.success) {
                    this.currentBatchReservation = result;
                    console.log('[Reservation] Batch success:', result.startSerial, '-', result.endSerial);
                    
                    // Show indicator
                    this.showReservationIndicator(result, true);
                } else {
                    console.error('[Reservation] Batch failed:', result.message);
                    this.showReservationWarning(result.message);
                }
                
                return result;
            },
            
            /**
             * Confirm reservation after successful generation
             * @param {string} uuid - Reservation UUID
             * @returns {Promise<Object>} Confirmation result
             */
            async confirm(uuid = null) {
                const targetUuid = uuid || this.currentReservation?.uuid;
                
                if (!targetUuid) {
                    console.warn('[Reservation] No UUID to confirm');
                    return { success: false, message: 'No reservation to confirm' };
                }
                
                console.log('[Reservation] Confirming:', targetUuid);
                
                const result = await confirmReservation(targetUuid);
                
                if (result.success) {
                    this.currentReservation = null;
                    this.hideReservationIndicator();
                    console.log('[Reservation] Confirmed successfully');
                } else {
                    console.error('[Reservation] Confirmation failed:', result.message);
                }
                
                return result;
            },
            
            /**
             * Confirm batch reservations
             * @param {Array<string>} uuids - Array of reservation UUIDs
             * @returns {Promise<Object>} Confirmation result
             */
            async confirmBatch(uuids = null) {
                const targetUuids = uuids || this.currentBatchReservation?.batchUuids;
                
                if (!targetUuids || targetUuids.length === 0) {
                    console.warn('[Reservation] No batch UUIDs to confirm');
                    return { success: false, message: 'No batch reservations to confirm' };
                }
                
                console.log('[Reservation] Confirming batch:', targetUuids.length, 'reservations');
                
                const result = await confirmBatchReservations(targetUuids);
                
                if (result.success) {
                    this.currentBatchReservation = null;
                    this.hideReservationIndicator();
                    console.log('[Reservation] Batch confirmed successfully');
                } else {
                    console.error('[Reservation] Batch confirmation failed:', result.message);
                }
                
                return result;
            },
            
            /**
             * Release current reservation
             * @returns {Promise<Object>} Release result
             */
            async release() {
                if (this.currentReservation) {
                    console.log('[Reservation] Releasing:', this.currentReservation.uuid);
                    const result = await releaseReservation(this.currentReservation.uuid);
                    this.currentReservation = null;
                    this.hideReservationIndicator();
                    return result;
                }
                
                if (this.currentBatchReservation) {
                    console.log('[Reservation] Releasing batch');
                    await releaseAllReservations();
                    this.currentBatchReservation = null;
                    this.hideReservationIndicator();
                }
                
                return { success: true };
            },
            
            /**
             * Show reservation indicator in UI
             */
            showReservationIndicator(data, isBatch = false) {
                const indicator = document.getElementById('reservationIndicator');
                if (!indicator) return;
                
                const serialDisplay = isBatch 
                    ? `${data.startSerial} - ${data.endSerial}` 
                    : data.serialNumber;
                
                const countdownHtml = data.expiresIn 
                    ? `<span id="reservationCountdown" class="font-mono"></span>` 
                    : '';
                
                indicator.innerHTML = `
                    <div class="flex items-center gap-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                        <svg class="w-5 h-5 text-blue-600 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                        <div class="flex-1">
                            <p class="text-sm font-medium text-blue-800">
                                ${isBatch ? 'Batch Reserved' : 'Serial Reserved'}: <span class="font-mono">${serialDisplay}</span>
                            </p>
                            ${countdownHtml}
                        </div>
                    </div>
                `;
                
                indicator.classList.remove('hidden');
                
                // Start countdown if expiresIn provided
                if (data.expiresIn) {
                    this.startCountdown(data.expiresIn);
                }
            },
            
            /**
             * Show warning message
             */
            showReservationWarning(message) {
                const warning = document.getElementById('reservationWarning');
                if (!warning) return;
                
                warning.innerHTML = `
                    <div class="flex items-center gap-3 p-3 bg-red-50 border border-red-200 rounded-lg">
                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <p class="text-sm text-red-800">${message}</p>
                    </div>
                `;
                
                warning.classList.remove('hidden');
            },
            
            /**
             * Hide reservation indicator
             */
            hideReservationIndicator() {
                const indicator = document.getElementById('reservationIndicator');
                const warning = document.getElementById('reservationWarning');
                
                if (indicator) indicator.classList.add('hidden');
                if (warning) warning.classList.add('hidden');
            },
            
            /**
             * Start countdown timer
             */
            startCountdown(seconds) {
                const countdownEl = document.getElementById('reservationCountdown');
                if (!countdownEl) return;
                
                let remaining = seconds;
                
                const updateTimer = () => {
                    const mins = Math.floor(remaining / 60);
                    const secs = remaining % 60;
                    countdownEl.textContent = `Expires in ${mins}:${secs.toString().padStart(2, '0')}`;
                    
                    // Warning color when < 2 minutes
                    if (remaining < 120) {
                        countdownEl.classList.add('text-red-600', 'font-bold');
                    }
                };
                
                updateTimer();
                
                const interval = setInterval(() => {
                    remaining--;
                    updateTimer();
                    
                    if (remaining <= 0) {
                        clearInterval(interval);
                        countdownEl.textContent = 'Expired!';
                        countdownEl.classList.add('text-red-600', 'font-bold', 'animate-pulse');
                    }
                }, 1000);
            }
        };

        /**
         * Auto-integrate with Alpine.js fileNumberGenerator() if it exists
         */
        if (window.Alpine) {
            // Extend Alpine magic helpers
            window.Alpine.magic('reservation', () => window.commissionModalReservation);
            
            console.log('Commission Modal Reservation Integration: Alpine magic $reservation registered');
        }

        /**
         * Intercept modal close to ensure reservation release
         */
        const originalClose = window.closeCommissionFileNoModal;
        if (typeof originalClose === 'function') {
            window.closeCommissionFileNoModal = async function() {
                console.log('[Reservation] Modal closing, releasing reservations...');
                await window.commissionModalReservation.release();
                await originalClose.apply(this, arguments);
            };
            console.log('Commission Modal Reservation Integration: Close handler patched');
        }

        console.log('Commission Modal Reservation Integration: Ready ✓');
    });

})();
