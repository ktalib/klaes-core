<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ArcGIS Survey123</title>
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
        .bg-gradient-custom {
            background: linear-gradient(to bottom, rgba(0, 120, 212, 0.658), rgba(0, 104, 55, 0.8));
        }
        
        /* Slideshow styles */
        .slideshow {
            position: relative;
            width: 100%;
            height: 100%;
            overflow: hidden;
        }
        
        .slideshow-slide {
            position: absolute;
            width: 100%;
            height: 100%;
            animation: slideshow 15s infinite;
            opacity: 0;
            object-fit: cover;
        }
        
        .slideshow-slide:nth-child(1) {
            animation-delay: 0s;
        }
        
        .slideshow-slide:nth-child(2) {
            animation-delay: 5s;
        }
        
        .slideshow-slide:nth-child(3) {
            animation-delay: 10s;
        }
        
        @keyframes slideshow {
            0% { opacity: 0; transform: scale(1.05); }
            5% { opacity: 1; }
            33% { opacity: 1; }
            38% { opacity: 0; transform: scale(1); }
            100% { opacity: 0; }
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
        <div class="slideshow">
            <img src="2.jpeg" alt="City Skyline" class="slideshow-slide">
            <img src="Klase.png" alt="City Skyline" class="slideshow-slide">
            <img src="Klase.png" alt="City Skyline" class="slideshow-slide">
        </div>
        <div class="absolute inset-0 bg-gradient-custom"></div>
    </div>

    <!-- Main Content -->
    <div class="relative z-10 flex flex-col min-h-screen">
        <!-- Header -->
        <header class="p-4 md:p-6">
            <div class="container mx-auto flex justify-between items-center">
                <div class="flex items-center space-x-2">
                    
                   
                </div>
                <div class="flex space-x-4">
                    <a href="#" class="px-6 py-2 bg-arcgis-green text-white font-semibold rounded-lg shadow-md hover:bg-green-700 transition-all duration-300 flex items-center">
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
                <div class="mx-auto mb-8    rounded-md flex items-center justify-center relative">
                 
                   <img src="logo.png" alt="Logo" class="  rounded-md" style="width: 30%; height: auto;">
                   
               
                </div>
                
                <h1 class="text-4xl md:text-6xl font-black text-white mb-6 whitespace-nowrap shadow-text italic" style="text-shadow: 0 0 1px #000000; -webkit-text-stroke: 0.5px #000000; letter-spacing: -0.5px;">Kano State Land Admin System</h1>
              
            </div>
            </div>
        </main>

        <!-- Footer with Bottom Right Logo -->
        <footer class="relative w-full">
            <div class="absolute bottom-4 right-4 md:bottom-8 md:right-8">
                <img src="logo.jpeg" alt="Footer Logo" class="w-30 md:w-40 h-auto opacity-80 hover:opacity-100 transition-opacity duration-300">
            </div>
        </footer>
        
    </div>
</body>
</html>