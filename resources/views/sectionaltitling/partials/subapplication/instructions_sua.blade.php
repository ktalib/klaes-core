{{-- SUA Instructions Panel with Sliding Animation --}}
<div id="instructionsPanel" class="bg-gradient-to-r from-purple-50 to-pink-50 border border-purple-200 rounded-lg shadow-lg overflow-hidden transition-all duration-500 ease-in-out mb-6">
    {{-- Header with Toggle Button --}}
    <div class="px-6 py-4 border-b border-purple-200 bg-gradient-to-r from-purple-100 to-pink-100 cursor-pointer" onclick="toggleInstructions()">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0">
                    <div class="w-10 h-10 bg-purple-600 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-purple-900">📌 SUA Pre-Application Instructions</h3>
                    <p class="text-sm text-purple-700">Essential steps before filling the Standalone Unit Application Form</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span id="instructionsToggleText" class="text-sm font-medium text-purple-700">Click to expand</span>
                <svg id="instructionsToggleIcon" class="w-5 h-5 text-purple-600 transform transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                    <div class="w-1 h-6 bg-purple-600 rounded-full"></div>
                    <p class="text-sm text-purple-800 font-semibold">Complete these steps before proceeding:</p>
                </div>
                <div class="flex items-center gap-2 text-sm text-purple-600">
                    <span id="currentStepIndicator">Step 1 of 5</span>
                </div>
            </div>
            
            {{-- Carousel Container --}}
            <div class="relative overflow-hidden rounded-xl bg-gradient-to-br from-white to-purple-50/30 border border-purple-200 shadow-md" style="height: 220px;">
                <div id="instructionCarousel" class="flex h-full transition-transform duration-700 ease-in-out" style="width: 500%; transform: translateX(0%);">
                    {{-- Step 1 - SUA File Number Selection --}}
                    <div class="instruction-slide flex-shrink-0 p-5 flex items-center" style="width: 20%; min-width: 100%;">
                        <div class="flex items-center gap-4 w-full">
                            <div class="flex-shrink-0 w-14 h-14 bg-gradient-to-br from-purple-500 to-purple-700 text-white rounded-2xl flex items-center justify-center text-lg font-bold shadow-lg transform hover:scale-105 transition-transform duration-200">
                                1
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-bold text-purple-900 text-lg mb-2 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    SUA File Number Selection
                                </h4>
                                <p class="text-purple-700 text-sm leading-relaxed mb-3">
                                    Select an existing SUA file number to auto-populate form data.
                                </p>
                                <div class="space-y-1">
                                    <div class="flex items-start gap-2 text-sm text-purple-700">
                                        <div class="w-1.5 h-1.5 bg-purple-500 rounded-full mt-2 flex-shrink-0"></div>
                                        <span>Choose from existing SUA file numbers</span>
                                    </div>
                                    <div class="flex items-start gap-2 text-sm text-purple-700">
                                        <div class="w-1.5 h-1.5 bg-purple-500 rounded-full mt-2 flex-shrink-0"></div>
                                        <span>All form fields will auto-populate</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Step 2 - Applicant Information Verification --}}
                    <div class="instruction-slide flex-shrink-0 p-5 flex items-center" style="width: 20%; min-width: 100%;">
                        <div class="flex items-center gap-4 w-full">
                            <div class="flex-shrink-0 w-14 h-14 bg-gradient-to-br from-indigo-500 to-indigo-700 text-white rounded-2xl flex items-center justify-center text-lg font-bold shadow-lg transform hover:scale-105 transition-transform duration-200">
                                2
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-bold text-purple-900 text-lg mb-2 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                    Applicant Information
                                </h4>
                                <p class="text-purple-700 text-sm leading-relaxed mb-3">
                                    Verify and update applicant information as needed.
                                </p>
                                <div class="space-y-1">
                                    <div class="flex items-start gap-2 text-sm text-purple-700">
                                        <div class="w-1.5 h-1.5 bg-indigo-500 rounded-full mt-2 flex-shrink-0"></div>
                                        <span>Review auto-populated applicant details</span>
                                    </div>
                                    <div class="flex items-start gap-2 text-sm text-purple-700">
                                        <div class="w-1.5 h-1.5 bg-indigo-500 rounded-full mt-2 flex-shrink-0"></div>
                                        <span>Update any changed information</span>
                                    </div>
                                    <div class="flex items-start gap-2 text-sm text-purple-700">
                                        <div class="w-1.5 h-1.5 bg-indigo-500 rounded-full mt-2 flex-shrink-0"></div>
                                        <span>Ensure contact details are current</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Step 3 - Unit Details Completion --}}
                    <div class="instruction-slide flex-shrink-0 p-5 flex items-center" style="width: 20%; min-width: 100%;">
                        <div class="flex items-center gap-4 w-full">
                            <div class="flex-shrink-0 w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-700 text-white rounded-2xl flex items-center justify-center text-lg font-bold shadow-lg transform hover:scale-105 transition-transform duration-200">
                                3
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-bold text-purple-900 text-lg mb-2 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                    </svg>
                                    Unit Details
                                </h4>
                                <p class="text-purple-700 text-sm leading-relaxed mb-3">
                                    Complete unit specifications and location information.
                                </p>
                                <div class="space-y-1">
                                    <div class="flex items-start gap-2 text-sm text-purple-700">
                                        <div class="w-1.5 h-1.5 bg-blue-500 rounded-full mt-2 flex-shrink-0"></div>
                                        <span>Specify block, floor, and unit numbers</span>
                                    </div>
                                    <div class="flex items-start gap-2 text-sm text-purple-700">
                                        <div class="w-1.5 h-1.5 bg-blue-500 rounded-full mt-2 flex-shrink-0"></div>
                                        <span>Enter unit size and specifications</span>
                                    </div>
                                    <div class="flex items-start gap-2 text-sm text-purple-700">
                                        <div class="w-1.5 h-1.5 bg-blue-500 rounded-full mt-2 flex-shrink-0"></div>
                                        <span>Confirm property location details</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Step 4 - Documentation Upload --}}
                    <div class="instruction-slide flex-shrink-0 p-5 flex items-center" style="width: 20%; min-width: 100%;">
                        <div class="flex items-center gap-4 w-full">
                            <div class="flex-shrink-0 w-14 h-14 bg-gradient-to-br from-emerald-500 to-emerald-700 text-white rounded-2xl flex items-center justify-center text-lg font-bold shadow-lg transform hover:scale-105 transition-transform duration-200">
                                4
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-bold text-purple-900 text-lg mb-2 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    Documentation Upload
                                </h4>
                                <p class="text-purple-700 text-sm leading-relaxed mb-3">
                                    Upload required identification documents in proper format.
                                </p>
                                <div class="space-y-1">
                                    <div class="flex items-start gap-2 text-sm text-purple-700">
                                        <div class="w-1.5 h-1.5 bg-emerald-500 rounded-full mt-2 flex-shrink-0"></div>
                                        <span>Upload means of identification</span>
                                    </div>
                                    <div class="flex items-start gap-2 text-sm text-purple-700">
                                        <div class="w-1.5 h-1.5 bg-emerald-500 rounded-full mt-2 flex-shrink-0"></div>
                                        <span>Accepted: JPG, PNG, PDF (Max 5MB)</span>
                                    </div>
                                    <div class="flex items-start gap-2 text-sm text-purple-700">
                                        <div class="w-1.5 h-1.5 bg-emerald-500 rounded-full mt-2 flex-shrink-0"></div>
                                        <span>Ensure document clarity and legibility</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Step 5 - Fee Payment Verification --}}
                    <div class="instruction-slide flex-shrink-0 p-5 flex items-center" style="width: 20%; min-width: 100%;">
                        <div class="flex items-center gap-4 w-full">
                            <div class="flex-shrink-0 w-14 h-14 bg-gradient-to-br from-amber-500 to-amber-700 text-white rounded-2xl flex items-center justify-center text-lg font-bold shadow-lg transform hover:scale-105 transition-transform duration-200">
                                5
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-bold text-purple-900 text-lg mb-2 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                    </svg>
                                    Fee Payment Details
                                </h4>
                                <p class="text-purple-700 text-sm leading-relaxed mb-3">
                                    Complete payment information for all required fees.
                                </p>
                                <div class="space-y-1">
                                    <div class="flex items-start gap-2 text-sm text-purple-700">
                                        <div class="w-1.5 h-1.5 bg-amber-500 rounded-full mt-2 flex-shrink-0"></div>
                                        <span>Fill payment dates for all fees</span>
                                    </div>
                                    <div class="flex items-start gap-2 text-sm text-purple-700">
                                        <div class="w-1.5 h-1.5 bg-amber-500 rounded-full mt-2 flex-shrink-0"></div>
                                        <span>Enter receipt numbers accurately</span>
                                    </div>
                                    <div class="flex items-start gap-2 text-sm text-purple-700">
                                        <div class="w-1.5 h-1.5 bg-amber-500 rounded-full mt-2 flex-shrink-0"></div>
                                        <span>Verify total amount calculation</span>
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
                <button id="prevBtn" onclick="previousSlide()" class="p-2.5 text-purple-600 hover:text-white hover:bg-purple-600 rounded-xl transition-all duration-200 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-transparent disabled:hover:text-purple-600 shadow-sm hover:shadow-md" disabled>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </button>

                {{-- Progress Dots --}}
                <div class="flex items-center gap-2">
                    <div id="progressDot1" class="w-2.5 h-2.5 rounded-full bg-purple-600 transition-all duration-300 cursor-pointer hover:scale-125 shadow-sm" onclick="goToSlide(0)"></div>
                    <div id="progressDot2" class="w-2 h-2 rounded-full bg-purple-300 hover:bg-purple-400 transition-all duration-300 cursor-pointer hover:scale-125" onclick="goToSlide(1)"></div>
                    <div id="progressDot3" class="w-2 h-2 rounded-full bg-purple-300 hover:bg-purple-400 transition-all duration-300 cursor-pointer hover:scale-125" onclick="goToSlide(2)"></div>
                    <div id="progressDot4" class="w-2 h-2 rounded-full bg-purple-300 hover:bg-purple-400 transition-all duration-300 cursor-pointer hover:scale-125" onclick="goToSlide(3)"></div>
                    <div id="progressDot5" class="w-2 h-2 rounded-full bg-purple-300 hover:bg-purple-400 transition-all duration-300 cursor-pointer hover:scale-125" onclick="goToSlide(4)"></div>
                </div>

                <button id="nextBtn" onclick="nextSlide()" class="p-2.5 text-purple-600 hover:text-white hover:bg-purple-600 rounded-xl transition-all duration-200 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-transparent disabled:hover:text-purple-600 shadow-sm hover:shadow-md">
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
let instructionsExpanded = false;
let currentSlide = 0;
const totalSlides = 5;
let autoSlideInterval;

function updateProgressDots() {
    // Reset all dots
    for (let i = 1; i <= totalSlides; i++) {
        const dot = document.getElementById(`progressDot${i}`);
        if (dot) {
            dot.className = 'w-2 h-2 rounded-full bg-purple-300 hover:bg-purple-400 transition-all duration-300 cursor-pointer hover:scale-125';
        }
    }
    
    // Highlight current dot
    const currentDot = document.getElementById(`progressDot${currentSlide + 1}`);
    if (currentDot) {
        currentDot.className = 'w-2.5 h-2.5 rounded-full bg-purple-600 transition-all duration-300 cursor-pointer hover:scale-125 shadow-sm';
    }
}

function updateNavButtons() {
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    
    if (prevBtn) {
        prevBtn.disabled = currentSlide === 0;
        prevBtn.classList.toggle('opacity-40', currentSlide === 0);
        prevBtn.classList.toggle('cursor-not-allowed', currentSlide === 0);
    }
    
    if (nextBtn) {
        nextBtn.disabled = currentSlide === totalSlides - 1;
        nextBtn.classList.toggle('opacity-40', currentSlide === totalSlides - 1);
        nextBtn.classList.toggle('cursor-not-allowed', currentSlide === totalSlides - 1);
    }
}

function updateStepIndicator() {
    const indicator = document.getElementById('currentStepIndicator');
    if (indicator) {
        indicator.textContent = `Step ${currentSlide + 1} of ${totalSlides}`;
    }
}

function goToSlide(slideIndex) {
    if (slideIndex < 0 || slideIndex >= totalSlides) return;
    
    const prevSlide = currentSlide;
    currentSlide = slideIndex;
    
    const carousel = document.getElementById('instructionCarousel');
    if (carousel) {
        const translateX = -(currentSlide * 100);
        carousel.style.transform = `translateX(${translateX}%)`;
        carousel.style.transition = 'transform 0.7s cubic-bezier(0.25, 0.8, 0.25, 1)';
    }
    
    updateProgressDots();
    updateNavButtons();
    updateStepIndicator();
    resetAutoSlide();
    animateSlideContent(slideIndex);
}

function animateSlideContent(slideIndex) {
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

function nextSlide() {
    if (currentSlide < totalSlides - 1) {
        goToSlide(currentSlide + 1);
    }
}

function previousSlide() {
    if (currentSlide > 0) {
        goToSlide(currentSlide - 1);
    }
}

function startAutoSlide() {
    autoSlideInterval = setInterval(() => {
        if (currentSlide < totalSlides - 1) {
            nextSlide();
        } else {
            goToSlide(0);
        }
    }, 4000);
}

function stopAutoSlide() {
    if (autoSlideInterval) {
        clearInterval(autoSlideInterval);
        autoSlideInterval = null;
    }
}

function resetAutoSlide() {
    stopAutoSlide();
    startAutoSlide();
}

function toggleInstructions() {
    const content = document.getElementById('instructionsContent');
    const icon = document.getElementById('instructionsToggleIcon');
    const text = document.getElementById('instructionsToggleText');
    
    if (!instructionsExpanded) {
        content.style.maxHeight = content.scrollHeight + 'px';
        icon.style.transform = 'rotate(180deg)';
        text.textContent = 'Click to collapse';
        instructionsExpanded = true;
        
        setTimeout(() => {
            goToSlide(0);
            startAutoSlide();
        }, 300);
        
    } else {
        stopAutoSlide();
        content.style.maxHeight = '0px';
        icon.style.transform = 'rotate(0deg)';
        text.textContent = 'Click to expand';
        instructionsExpanded = false;
    }
}

// Auto-expand on page load
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        toggleInstructions();
    }, 700);
    
    const carouselContainer = document.getElementById('instructionCarousel');
    if (carouselContainer) {
        carouselContainer.addEventListener('mouseenter', stopAutoSlide);
        carouselContainer.addEventListener('mouseleave', () => {
            if (instructionsExpanded) {
                startAutoSlide();
            }
        });
    }
});

// Keyboard navigation
document.addEventListener('keydown', function(e) {
    if (!instructionsExpanded) return;
    
    if (e.key === 'ArrowLeft') {
        e.preventDefault();
        previousSlide();
    } else if (e.key === 'ArrowRight') {
        e.preventDefault();
        nextSlide();
    }
});
</script>

{{-- Enhanced Carousel CSS Styles for SUA --}}
<style>
#instructionCarousel {
    transition: transform 0.7s cubic-bezier(0.25, 0.8, 0.25, 1);
    will-change: transform;
}

.instruction-slide {
    opacity: 1;
    transition: opacity 0.5s ease-out, transform 0.5s ease-out;
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
}

.instruction-slide .w-14.h-14:hover {
    transform: scale(1.05) rotate(5deg);
    box-shadow: 0 12px 30px rgba(147, 51, 234, 0.3);
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

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
    background: rgba(147, 51, 234, 0.1);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.cursor-pointer:hover::before {
    opacity: 1;
}

button:not(:disabled) {
    position: relative;
    overflow: hidden;
}

button:not(:disabled):hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(147, 51, 234, 0.3);
}

#currentStepIndicator {
    animation: subtlePulse 3s ease-in-out infinite;
    font-weight: 600;
    background: linear-gradient(135deg, #9333ea, #c084fc);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

@keyframes subtlePulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.8; transform: scale(1.02); }
}

.instruction-slide h4 {
    transition: all 0.3s ease;
}

.instruction-slide:hover h4 {
    color: #7c3aed;
    transform: translateX(2px);
}

.w-1\.5.h-1\.5 {
    transition: all 0.3s ease;
    box-shadow: 0 0 0 0 rgba(147, 51, 234, 0.4);
}

.instruction-slide:hover .w-1\.5.h-1\.5 {
    transform: scale(1.2);
    box-shadow: 0 0 0 4px rgba(147, 51, 234, 0.2);
}

button:focus-visible,
.cursor-pointer:focus-visible {
    outline: 2px solid #9333ea;
    outline-offset: 2px;
    border-radius: 6px;
}
</style>