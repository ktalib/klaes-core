{{-- Instructions Panel with Sliding Animation --}}
<div id="instructionsPanel" class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg shadow-lg overflow-hidden transition-all duration-500 ease-in-out mb-6">
    {{-- Header with Toggle Button --}}
    <div class="px-6 py-4 border-b border-blue-200 bg-gradient-to-r from-blue-100 to-indigo-100 cursor-pointer" onclick="pfToggleInstructions()">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0">
                    <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-blue-900">📌 Pre-Application Instructions</h3>
                    <p class="text-sm text-blue-700">Essential steps before filling the Sectional Titling Application Form</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span id="instructionsToggleText" class="text-sm font-medium text-blue-700">Click to expand</span>
                <svg id="instructionsToggleIcon" class="w-5 h-5 text-blue-600 transform transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </div>
        </div>
    </div>
    
    {{-- Collapsible Content --}}
    <div id="instructionsContent" class="max-h-0 overflow-hidden transition-all duration-500 ease-in-out">
        <div class="px-6 py-6">
            {{-- Carousel Header --}}
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-2">
                    <div class="w-1 h-6 bg-blue-600 rounded-full"></div>
                    <p class="text-sm text-blue-800 font-semibold">Complete these steps before proceeding:</p>
                </div>
                <div class="flex items-center gap-2 text-sm text-blue-600">
                    <span id="currentStepIndicator">Step 1 of 5</span>
                </div>
            </div>
            
            {{-- Carousel Container --}}
            <div class="relative overflow-hidden rounded-xl bg-gradient-to-br from-white to-blue-50/30 border border-blue-200 shadow-md" style="height: 220px;">
                <div id="instructionCarousel" class="flex h-full transition-transform duration-700 ease-in-out" style="width: 500%; transform: translateX(0%);">
                    {{-- Step 1 - Initial Bill Settlement --}}
                    <div class="instruction-slide flex-shrink-0 p-5 flex items-center" style="width: 20%; min-width: 100%;">
                        <div class="flex items-center gap-4 w-full">
                            <div class="flex-shrink-0 w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-700 text-white rounded-2xl flex items-center justify-center text-lg font-bold shadow-lg transform hover:scale-105 transition-transform duration-200">
                                1
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-bold text-blue-900 text-lg mb-2 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                    </svg>
                                    Initial Bill Settlement
                                </h4>
                                <p class="text-blue-700 text-sm leading-relaxed mb-3">
                                    Ensure all initial billing requirements are completed before proceeding.
                                </p>
                                <div class="space-y-1">
                                    <div class="flex items-start gap-2 text-sm text-blue-700">
                                        <div class="w-1.5 h-1.5 bg-blue-500 rounded-full mt-2 flex-shrink-0"></div>
                                        <span>Confirm Initial Bill payment completion</span>
                                    </div>
                                    <div class="flex items-start gap-2 text-sm text-blue-700">
                                        <div class="w-1.5 h-1.5 bg-blue-500 rounded-full mt-2 flex-shrink-0"></div>
                                        <span>Keep receipt copies for verification</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Step 2 - Mother File Scanning --}}
                    <div class="instruction-slide flex-shrink-0 p-5 flex items-center" style="width: 20%; min-width: 100%;">
                        <div class="flex items-center gap-4 w-full">
                            <div class="flex-shrink-0 w-14 h-14 bg-gradient-to-br from-indigo-500 to-indigo-700 text-white rounded-2xl flex items-center justify-center text-lg font-bold shadow-lg transform hover:scale-105 transition-transform duration-200">
                                2
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-bold text-blue-900 text-lg mb-2 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    Mother File Scanning
                                </h4>
                                <p class="text-blue-700 text-sm leading-relaxed mb-3">
                                    All physical documents must be digitized and properly organized.
                                </p>
                                <div class="space-y-1">
                                    <div class="flex items-start gap-2 text-sm text-blue-700">
                                        <div class="w-1.5 h-1.5 bg-indigo-500 rounded-full mt-2 flex-shrink-0"></div>
                                        <span>Complete Mother File scanning</span>
                                    </div>
                                    <div class="flex items-start gap-2 text-sm text-blue-700">
                                        <div class="w-1.5 h-1.5 bg-indigo-500 rounded-full mt-2 flex-shrink-0"></div>
                                        <span>Save in A4 Sub folder with MLSFileNo</span>
                                    </div>
                                    <div class="flex items-start gap-2 text-sm text-blue-700">
                                        <div class="w-1.5 h-1.5 bg-indigo-500 rounded-full mt-2 flex-shrink-0"></div>
                                        <span>Example: <code class="bg-blue-100 px-1.5 py-0.5 rounded text-blue-900 text-xs">COM-1987-234</code></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Step 3 - Buyers List Capture --}}
                    <div class="instruction-slide flex-shrink-0 p-5 flex items-center" style="width: 20%; min-width: 100%;">
                        <div class="flex items-center gap-4 w-full">
                            <div class="flex-shrink-0 w-14 h-14 bg-gradient-to-br from-purple-500 to-purple-700 text-white rounded-2xl flex items-center justify-center text-lg font-bold shadow-lg transform hover:scale-105 transition-transform duration-200">
                                3
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-bold text-blue-900 text-lg mb-2 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                    Buyers' List Capture
                                </h4>
                                <p class="text-blue-700 text-sm leading-relaxed mb-3">
                                    All potential buyers must be registered before application submission.
                                </p>
                                <div class="space-y-1">
                                    <div class="flex items-start gap-2 text-sm text-blue-700">
                                        <div class="w-1.5 h-1.5 bg-purple-500 rounded-full mt-2 flex-shrink-0"></div>
                                        <span>Confirm all buyers pre-captured</span>
                                    </div>
                                    <div class="flex items-start gap-2 text-sm text-blue-700">
                                        <div class="w-1.5 h-1.5 bg-purple-500 rounded-full mt-2 flex-shrink-0"></div>
                                        <span>Use official Buyers' List Template (CSV)</span>
                                    </div>
                                    <div class="flex items-start gap-2 text-sm text-blue-700">
                                        <div class="w-1.5 h-1.5 bg-purple-500 rounded-full mt-2 flex-shrink-0"></div>
                                        <span>Verify information completeness</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Step 4 - Passport Photograph --}}
                    <div class="instruction-slide flex-shrink-0 p-5 flex items-center" style="width: 20%; min-width: 100%;">
                        <div class="flex items-center gap-4 w-full">
                            <div class="flex-shrink-0 w-14 h-14 bg-gradient-to-br from-emerald-500 to-emerald-700 text-white rounded-2xl flex items-center justify-center text-lg font-bold shadow-lg transform hover:scale-105 transition-transform duration-200">
                                4
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-bold text-blue-900 text-lg mb-2 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    Passport Photograph
                                </h4>
                                <p class="text-blue-700 text-sm leading-relaxed mb-3">
                                    Digital photographs of all buyers with proper naming conventions.
                                </p>
                                <div class="space-y-1">
                                    <div class="flex items-start gap-2 text-sm text-blue-700">
                                        <div class="w-1.5 h-1.5 bg-emerald-500 rounded-full mt-2 flex-shrink-0"></div>
                                        <span>Scan passport photo for each buyer</span>
                                    </div>
                                    <div class="flex items-start gap-2 text-sm text-blue-700">
                                        <div class="w-1.5 h-1.5 bg-emerald-500 rounded-full mt-2 flex-shrink-0"></div>
                                        <span>Format: <code class="bg-blue-100 px-1.5 py-0.5 rounded text-blue-900 text-xs">MLSFileNo_PP</code></span>
                                    </div>
                                    <div class="flex items-start gap-2 text-sm text-blue-700">
                                        <div class="w-1.5 h-1.5 bg-emerald-500 rounded-full mt-2 flex-shrink-0"></div>
                                        <span>Example: <code class="bg-blue-100 px-1.5 py-0.5 rounded text-blue-900 text-xs">COM-1987-234_PP</code></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Step 5 - Means of Identification --}}
                    <div class="instruction-slide flex-shrink-0 p-5 flex items-center" style="width: 20%; min-width: 100%;">
                        <div class="flex items-center gap-4 w-full">
                            <div class="flex-shrink-0 w-14 h-14 bg-gradient-to-br from-amber-500 to-amber-700 text-white rounded-2xl flex items-center justify-center text-lg font-bold shadow-lg transform hover:scale-105 transition-transform duration-200">
                                5
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-bold text-blue-900 text-lg mb-2 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path>
                                    </svg>
                                    Means of Identification
                                </h4>
                                <p class="text-blue-700 text-sm leading-relaxed mb-3">
                                    Valid identification documents must be digitized and properly stored.
                                </p>
                                <div class="space-y-1">
                                    <div class="flex items-start gap-2 text-sm text-blue-700">
                                        <div class="w-1.5 h-1.5 bg-amber-500 rounded-full mt-2 flex-shrink-0"></div>
                                        <span>Scan approved identification documents</span>
                                    </div>
                                    <div class="flex items-start gap-2 text-sm text-blue-700">
                                        <div class="w-1.5 h-1.5 bg-amber-500 rounded-full mt-2 flex-shrink-0"></div>
                                        <span>Format: <code class="bg-blue-100 px-1.5 py-0.5 rounded text-blue-900 text-xs">MLSFileNo_ID</code></span>
                                    </div>
                                    <div class="flex items-start gap-2 text-sm text-blue-700">
                                        <div class="w-1.5 h-1.5 bg-amber-500 rounded-full mt-2 flex-shrink-0"></div>
                                        <span>Example: <code class="bg-blue-100 px-1.5 py-0.5 rounded text-blue-900 text-xs">COM-1987-234_ID</code></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Carousel Navigation --}}
            <div class="flex items-center justify-center mt-4 gap-6">
                {{-- Navigation Buttons --}}
                <button id="prevBtn" onclick="pfPreviousSlide()" class="p-2.5 text-blue-600 hover:text-white hover:bg-blue-600 rounded-xl transition-all duration-200 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-transparent disabled:hover:text-blue-600 shadow-sm hover:shadow-md" disabled>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </button>

                {{-- Progress Dots --}}
                <div class="flex items-center gap-2">
                    <div id="progressDot1" class="w-2.5 h-2.5 rounded-full bg-blue-600 transition-all duration-300 cursor-pointer hover:scale-125 shadow-sm" onclick="pfGoToSlide(0)"></div>
                    <div id="progressDot2" class="w-2 h-2 rounded-full bg-blue-300 hover:bg-blue-400 transition-all duration-300 cursor-pointer hover:scale-125" onclick="pfGoToSlide(1)"></div>
                    <div id="progressDot3" class="w-2 h-2 rounded-full bg-blue-300 hover:bg-blue-400 transition-all duration-300 cursor-pointer hover:scale-125" onclick="pfGoToSlide(2)"></div>
                    <div id="progressDot4" class="w-2 h-2 rounded-full bg-blue-300 hover:bg-blue-400 transition-all duration-300 cursor-pointer hover:scale-125" onclick="pfGoToSlide(3)"></div>
                    <div id="progressDot5" class="w-2 h-2 rounded-full bg-blue-300 hover:bg-blue-400 transition-all duration-300 cursor-pointer hover:scale-125" onclick="pfGoToSlide(4)"></div>
                </div>

                <button id="nextBtn" onclick="pfNextSlide()" class="p-2.5 text-blue-600 hover:text-white hover:bg-blue-600 rounded-xl transition-all duration-200 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-transparent disabled:hover:text-blue-600 shadow-sm hover:shadow-md">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>
            </div>
    
        </div>
    </div>
</div>

{{-- JavaScript for Carousel Animation --}}
<script>
let pf_instructionsExpanded = false;
let pf_currentSlide = 0;
const pf_totalSlides = 5;
let pf_autoSlideInterval;

function pfUpdateProgressDots() {
    // Reset all dots
    for (let i = 1; i <= pf_totalSlides; i++) {
        const dot = document.getElementById(`progressDot${i}`);
        if (dot) {
            dot.className = 'w-2 h-2 rounded-full bg-blue-300 hover:bg-blue-400 transition-all duration-300 cursor-pointer hover:scale-125';
        }
    }
    
    // Highlight current dot
    const currentDot = document.getElementById(`progressDot${pf_currentSlide + 1}`);
    if (currentDot) {
        currentDot.className = 'w-2.5 h-2.5 rounded-full bg-blue-600 transition-all duration-300 cursor-pointer hover:scale-125 shadow-sm';
    }
}

function pfUpdateNavButtons() {
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    
    if (prevBtn) {
        prevBtn.disabled = pf_currentSlide === 0;
        prevBtn.classList.toggle('opacity-40', pf_currentSlide === 0);
        prevBtn.classList.toggle('cursor-not-allowed', pf_currentSlide === 0);
    }
    
    if (nextBtn) {
        nextBtn.disabled = pf_currentSlide === pf_totalSlides - 1;
        nextBtn.classList.toggle('opacity-40', pf_currentSlide === pf_totalSlides - 1);
        nextBtn.classList.toggle('cursor-not-allowed', pf_currentSlide === pf_totalSlides - 1);
    }
}

function pfUpdateStepIndicator() {
    const indicator = document.getElementById('currentStepIndicator');
    if (indicator) {
        indicator.textContent = `Step ${pf_currentSlide + 1} of ${pf_totalSlides}`;
    }
}

function pfGoToSlide(slideIndex) {
    if (slideIndex < 0 || slideIndex >= pf_totalSlides) return;
    
    const prevSlide = pf_currentSlide;
    pf_currentSlide = slideIndex;
    
    const carousel = document.getElementById('instructionCarousel');
    if (carousel) {
        const translateX = -(pf_currentSlide * 100);
        carousel.style.transform = `translateX(${translateX}%)`;
        carousel.style.transition = 'transform 0.7s cubic-bezier(0.25, 0.8, 0.25, 1)';
    }
    
    pfUpdateProgressDots();
    pfUpdateNavButtons();
    pfUpdateStepIndicator();
    
    // Reset auto-slide timer
    pfResetAutoSlide();
    
    // Trigger slide animation
    pfAnimateSlideContent(slideIndex);
}

function pfAnimateSlideContent(slideIndex) {
    const currentSlideElement = document.querySelector(`.instruction-slide:nth-child(${slideIndex + 1})`);
    if (currentSlideElement) {
        currentSlideElement.style.opacity = '0';
        currentSlideElement.style.transform = 'translateY(10px)';
        
        setTimeout(() => {
            currentSlideElement.style.transition = 'opacity 0.5s ease-out, transform 0.5s ease-out';
            currentSlideElement.style.opacity = '1';
            currentSlideElement.style.transform = 'translateY(0)';
        }, 200);
    }
}

function pfNextSlide() {
    if (pf_currentSlide < pf_totalSlides - 1) {
        pfGoToSlide(pf_currentSlide + 1);
    }
}

function pfPreviousSlide() {
    if (pf_currentSlide > 0) {
        pfGoToSlide(pf_currentSlide - 1);
    }
}

function pfStartAutoSlide() {
    pf_autoSlideInterval = setInterval(() => {
        if (pf_currentSlide < pf_totalSlides - 1) {
            pfNextSlide();
        } else {
            pfGoToSlide(0);
        }
    }, 4000);
}

function pfStopAutoSlide() {
    if (pf_autoSlideInterval) {
        clearInterval(pf_autoSlideInterval);
        pf_autoSlideInterval = null;
    }
}

function pfResetAutoSlide() {
    pfStopAutoSlide();
    pfStartAutoSlide();
}

function pfToggleInstructions() {
    const content = document.getElementById('instructionsContent');
    const icon = document.getElementById('instructionsToggleIcon');
    const text = document.getElementById('instructionsToggleText');
    
    if (!pf_instructionsExpanded) {
        content.style.maxHeight = content.scrollHeight + 'px';
        icon.style.transform = 'rotate(180deg)';
        text.textContent = 'Click to collapse';
        pf_instructionsExpanded = true;
        
        setTimeout(() => {
            pfGoToSlide(0);
            pfStartAutoSlide();
        }, 300);
        
    } else {
        pfStopAutoSlide();
        content.style.maxHeight = '0px';
        icon.style.transform = 'rotate(0deg)';
        text.textContent = 'Click to expand';
        pf_instructionsExpanded = false;
    }
}

// Auto-expand on page load
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        pfToggleInstructions();
    }, 700);
    
    // Pause auto-slide on hover
    const carouselContainer = document.getElementById('instructionCarousel');
    if (carouselContainer) {
        carouselContainer.addEventListener('mouseenter', pfStopAutoSlide);
        carouselContainer.addEventListener('mouseleave', () => {
            if (pf_instructionsExpanded) {
                pfStartAutoSlide();
            }
        });
    }
});

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    if (!pf_instructionsExpanded) return;
    
    if (e.key === 'ArrowLeft') {
        e.preventDefault();
        pfPreviousSlide();
    } else if (e.key === 'ArrowRight') {
        e.preventDefault();
        pfNextSlide();
    }
});

// Smooth scroll when clicking "Got it" button
function pfScrollToForm() {
    const formElement = document.querySelector('#primaryApplicationForm');
    if (formElement) {
        formElement.scrollIntoView({ 
            behavior: 'smooth',
            block: 'start'
        });
    }
    pfToggleInstructions(); // Also collapse the instructions
}
</script>

{{-- Enhanced Carousel CSS Styles --}}
<style>
/* Carousel container with improved transitions */
#instructionCarousel {
    transition: transform 0.7s cubic-bezier(0.25, 0.8, 0.25, 1);
    will-change: transform;
}

/* Individual slide styling with better animations */
.instruction-slide {
    opacity: 1;
    transition: opacity 0.5s ease-out, transform 0.5s ease-out;
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
}

/* Step number enhanced hover effects */
.instruction-slide .w-14.h-14:hover {
    transform: scale(1.05) rotate(5deg);
    box-shadow: 0 12px 30px rgba(59, 130, 246, 0.3);
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

/* Progress dots with sophisticated animations */
.cursor-pointer {
    position: relative;
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.cursor-pointer:hover {
    transform: scale(1.25);
}

.cursor-pointer::before {
    content: '';
    position: absolute;
    inset: -4px;
    border-radius: 50%;
    background: rgba(59, 130, 246, 0.1);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.cursor-pointer:hover::before {
    opacity: 1;
}

/* Navigation buttons enhanced styling */
button:not(:disabled) {
    position: relative;
    overflow: hidden;
}

button:not(:disabled):hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

button:not(:disabled)::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
    transform: translateX(-100%);
    transition: transform 0.6s ease;
}

button:not(:disabled):hover::before {
    transform: translateX(100%);
}

/* Carousel container subtle effects */
.relative.overflow-hidden {
    box-shadow: 
        0 4px 6px -1px rgba(0, 0, 0, 0.1), 
        0 2px 4px -1px rgba(0, 0, 0, 0.06),
        inset 0 1px 0 rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
}

/* Step indicator with pulse animation */
#currentStepIndicator {
    animation: subtlePulse 3s ease-in-out infinite;
    font-weight: 600;
    background: linear-gradient(135deg, #3b82f6, #6366f1);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

@keyframes subtlePulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.8; transform: scale(1.02); }
}

/* Smooth content transitions */
.instruction-slide h4 {
    transition: all 0.3s ease;
}

.instruction-slide:hover h4 {
    color: #1e40af;
    transform: translateX(2px);
}

/* Enhanced bullet points */
.w-1\.5.h-1\.5 {
    transition: all 0.3s ease;
    box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.4);
}

.instruction-slide:hover .w-1\.5.h-1\.5 {
    transform: scale(1.2);
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.2);
}

/* Code snippets enhanced styling */
code {
    transition: all 0.3s ease;
    position: relative;
}

code::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(99, 102, 241, 0.1));
    border-radius: inherit;
    opacity: 0;
    transition: opacity 0.3s ease;
}

code:hover::before {
    opacity: 1;
}

/* Responsive enhancements */
@media (max-width: 768px) {
    .instruction-slide {
        padding: 1.25rem;
    }
    
    .instruction-slide .w-14.h-14 {
        width: 3rem;
        height: 3rem;
        font-size: 1rem;
    }
    
    .instruction-slide h4 {
        font-size: 1rem;
    }
    
    .instruction-slide p {
        font-size: 0.875rem;
    }
}

/* Loading state with shimmer effect */
.carousel-loading {
    background: linear-gradient(
        90deg,
        rgba(244, 244, 245, 0.8) 25%,
        rgba(229, 231, 235, 0.8) 50%,
        rgba(244, 244, 245, 0.8) 75%
    );
    background-size: 200% 100%;
    animation: shimmer 2s infinite;
}

@keyframes shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* Smooth focus states for accessibility */
button:focus-visible,
.cursor-pointer:focus-visible {
    outline: 2px solid #3b82f6;
    outline-offset: 2px;
    border-radius: 6px;
}

/* Performance optimizations */
.instruction-slide * {
    will-change: auto;
}

#instructionCarousel:hover .instruction-slide * {
    will-change: transform;
}
</style>


 