<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KLAES-CORE</title>
  <link rel="icon" type="image/png" href="{{ asset('assets/logo/klas_core_logo.png') }}">
    <meta name="description" content="Official Land Administration System for Kano State - Manage land records, certificates, and property documentation efficiently.">
    <meta name="keywords" content="land administration, Kano State, property management, land registry, land certificates">
    <meta name="author" content="Kano State Government">
    <meta name="robots" content="index, follow">
    <meta name="theme-color" content="#006837">
    <!-- Open Graph / Social Media Meta Tags -->
    <meta property="og:title" content="Kano State LAnd ADmin  Enterprise 
System">
    <meta property="og:description" content="Official Land Administration System for Kano State">
    <meta property="og:type" content="website">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon_io/favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon_io/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon_io/favicon-16x16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('favicon_io/apple-touch-icon.png') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'arcgis-red': '#e32219',
                        'arcgis-black': '#000000',
                        'arcgis-green': '#006837',
                        'arcgis-yellow': '#ffc107',
                        'arcgis-blue': '#0078d4',
                    }
                }
            }
        }
    </script>
    <style>
        /* Theme 1 - Original dark blue/green gradient */
        .bg-gradient-theme1 {
            background: linear-gradient(to bottom, rgba(0, 120, 212, 0.658), rgba(0, 104, 55, 0.8));
        }
        
        /* Theme 2 - Light gradient from index.html */
        .bg-gradient-theme2 {
            background: linear-gradient(to bottom, rgba(255, 255, 255, 0.39), rgba(247, 247, 247, 0.322));
        }
        
        /* Carousel styles with sliding effect */
        .carousel {
            position: relative;
            width: 100%;
            height: 100%;
            overflow: hidden;
        }
        
        .carousel-item {
            position: absolute;
            width: 100%;
            height: 100%;
            opacity: 0;
            transform: translateX(-100%);
            transition: all 0.8s ease-in-out;
            object-fit: cover;
        }
        
        .carousel-item.active {
            opacity: 1;
            transform: translateX(0);
        }
        
        .carousel-item.next-out {
            opacity: 1;
            transform: translateX(100%);
        }
        
        .landing-subtitle {
            display: inline-block;
            max-width: 100%;
            color: #ffffff;
            font-weight: 800;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.9), 0 0 8px rgba(0, 0, 0, 0.5);
        }

        /* Text colors for different themes */
        .theme1-text {
            color: white;
            text-shadow: 0 0 1px #000000; 
            -webkit-text-stroke: 0.5px #000000; 
        }
        
        .theme2-text {
            color: #006837;
            text-shadow: 0 0 1px #006837; 
            -webkit-text-stroke: 0.5px #006837; 
        }
        
        .logo-icon {
            position: relative;
        }
        .logo-icon::before {
            content: "";
            position: absolute;
            width: 20px;
            height: 3px;
            background-color: white;
            top: 10px;
            left: 5px;
        }
        .logo-icon::after {
            content: "";
            position: absolute;
            width: 20px;
            height: 3px;
            background-color: white;
            top: 18px;
            left: 5px;
        }
        .logo-icon .checkmark {
            position: absolute;
            top: 8px;
            right: 5px;
            width: 20px;
            height: 20px;
            border-radius: 2px;
            border: 3px solid white;
        }
        .logo-icon .checkmark::before {
            content: "";
            position: absolute;
            width: 3px;
            height: 10px;
            background-color: white;
            transform: rotate(45deg);
            top: 2px;
            left: 10px;
        }
        .logo-icon .checkmark::after {
            content: "";
            position: absolute;
            width: 15px;
            height: 3px;
            background-color: white;
            transform: rotate(45deg);
            top: 9px;
            left: 2px;
        }
        .logo-icon .bottom-line {
            position: absolute;
            width: 20px;
            height: 3px;
            background-color: white;
            top: 26px;
            left: 5px;
        }
    </style>
</head>
<body class="min-h-screen font-sans">
    <!-- Background Image with Gradient Overlay -->
    <div class="fixed inset-0 z-0">
        <div id="background-carousel" class="carousel">
            <img src="{{ asset('storage/upload/logo/2.jpeg') }}" alt="City Skyline" class="carousel-item active">
            <img src="{{ asset('storage/upload/logo/3.jpeg') }}" alt="City Skyline" class="carousel-item">
            <img src="{{ asset('storage/upload/logo/4.jpeg') }}" alt="City Skyline" class="carousel-item">
            <img src="{{ asset('storage/upload/logo/5.jpeg') }}" alt="City Skyline" class="carousel-item">
        </div>
        <div id="gradient-overlay" class="absolute inset-0 bg-gradient-theme1"></div>
    </div>


    <!-- Main Content -->
    <div class="relative z-10 flex flex-col min-h-screen">
        <!-- Header -->
        <header class="p-4 md:p-6">
            <div class="container mx-auto flex justify-between items-center">
                <div class="flex items-center space-x-2">
                    
                   
                </div>
                <div class="flex space-x-4">
                    <a href="{{route('login')}}" class="px-6 py-2 bg-arcgis-green text-white font-semibold rounded-lg shadow-md hover:bg-green-700 transition-all duration-300 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3 3a1 1 0 011-1h12a1 1 0 011 1v3a1 1 0 01-.293.707L12 11.414V15a1 1 0 01-.293.707l-2 2A1 1 0 018 17v-5.586L3.293 6.707A1 1 0 013 6V3z" clip-rule="evenodd" />
                        </svg>
                        Login
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="flex-grow flex items-center">
            <div class="container mx-auto px-4 md:px-6 py-12 md:py-24">
            <div class="max-w-3xl mx-auto text-center">
                <!-- Added logo -->
                <div class="mx-auto mb-8 rounded-md flex items-center justify-center relative">
                <img src="{{ asset('assets/logo/klas_core_logo.png') }}" alt="KLAES-CORE Logo" class="rounded-md" style="width: 30%; height: auto;">
                </div>
                
                <h4 id="main-title" class="text-2xl md:text-4xl font-bold mb-4 shadow-text italic theme1-text" style="letter-spacing: -0.3px;">
                    KLAES-CORE
                </h4>
                <p class="landing-subtitle text-xl md:text-2xl leading-relaxed">Kano State LAnd ADmin Enterprise System</p>
            </div>
            </div>
        </main>

        <!-- Footer with Bottom Right Logo -->
        <footer class="relative w-full">
            <div class="absolute bottom-4 right-4 md:bottom-8 md:right-8">
                <img src="{{ asset('storage/upload/logo/las.jpeg') }}" alt="Footer Logo" class="w-30 md:w-40 h-auto opacity-80 hover:opacity-100 transition-opacity duration-300">
            </div>
        </footer>
        
    </div>

    <!-- Theme Switching and Carousel Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Theme switching functionality
            const gradientOverlay = document.getElementById('gradient-overlay');
            const mainTitle = document.getElementById('main-title');
            let currentTheme = 1;
            
            function toggleTheme() {
                if (currentTheme === 1) {
                    // Switch to theme 2
                    gradientOverlay.classList.remove('bg-gradient-theme1');
                    gradientOverlay.classList.add('bg-gradient-theme2');
                    mainTitle.classList.remove('theme1-text');
                    mainTitle.classList.add('theme2-text');
                    currentTheme = 2;
                } else {
                    // Switch to theme 1
                    gradientOverlay.classList.remove('bg-gradient-theme2');
                    gradientOverlay.classList.add('bg-gradient-theme1');
                    mainTitle.classList.remove('theme2-text');
                    mainTitle.classList.add('theme1-text');
                    currentTheme = 1;
                }
            }
            
            // Toggle theme every 3 seconds
            setInterval(toggleTheme, 3000);
            
            // New carousel implementation with direct style manipulation
            const carousel = document.getElementById('background-carousel');
            const carouselItems = Array.from(carousel.querySelectorAll('.carousel-item'));
            let currentSlide = 0;
            let isAnimating = false;
            
            // Initialize all slides to start position
            carouselItems.forEach((item, index) => {
                if (index === 0) {
                    item.style.opacity = '1';
                    item.style.transform = 'translateX(0)';
                } else {
                    item.style.opacity = '0';
                    item.style.transform = 'translateX(-100%)';
                }
            });
            
            function nextSlide() {
                if (isAnimating) return;
                isAnimating = true;
                
                const nextSlideIndex = (currentSlide + 1) % carouselItems.length;
                const currentItem = carouselItems[currentSlide];
                const nextItem = carouselItems[nextSlideIndex];
                
                // Prepare next slide
                nextItem.style.transition = 'none';
                nextItem.style.opacity = '0';
                nextItem.style.transform = 'translateX(-100%)';
                
                // Force browser reflow
                void nextItem.offsetWidth;
                
                // Re-enable transitions
                nextItem.style.transition = 'all 0.8s ease-in-out';
                
                // Move current slide out to right and next slide in from left
                currentItem.style.transform = 'translateX(100%)';
                nextItem.style.opacity = '1';
                nextItem.style.transform = 'translateX(0)';
                
                // After transition completes
                setTimeout(() => {
                    // Reset old slide position
                    currentItem.style.transition = 'none';
                    currentItem.style.opacity = '0';
                    currentItem.style.transform = 'translateX(-100%)';
                    
                    // Update current slide index
                    currentSlide = nextSlideIndex;
                    isAnimating = false;
                }, 800); // Match transition duration
            }
            
            // Start the carousel after a short delay
            setTimeout(() => {
                // Change slide every 5 seconds
                setInterval(nextSlide, 5000);
            }, 1000);
        });
    </script>
</body>
</html>
