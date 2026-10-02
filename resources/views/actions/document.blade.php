<div id="survey-plan-modal" class="fixed inset-0  hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
      <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" id="modal-backdrop"></div>
      
      <div class="relative bg-white rounded-lg max-w-3xl w-full mx-auto shadow-xl z-10">
        <div class="flex items-center justify-between p-4 border-b">
          <h3 class="text-lg font-medium">Survey Plan Documents</h3>
          <button type="button" id="close-modal" class="text-gray-500 hover:text-gray-700">
            <i data-lucide="x" class="w-5 h-5"></i>
          </button>
        </div>
        
        <div class="p-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @if(isset($application->documents) && is_array(json_decode($application->documents, true)))
              @php $documents = json_decode($application->documents, true); @endphp
              
              @if(isset($documents['building_plan']))
                <div class="border rounded-lg overflow-hidden">
                  <div class="p-3 bg-gray-50 border-b">
                    <h4 class="font-medium">Building Plan</h4>
                    <p class="text-xs text-gray-500">
                      {{ $documents['building_plan']['original_name'] ?? 'Unknown File Name' }}
                    </p>
                  </div>
                  <div class="p-4 flex justify-center">
                    <img 
                      src="{{ asset('storage/app/public/' . $documents['building_plan']['path']) }}" 
                      alt="Building Plan" 
                      class="max-h-64 object-contain document-preview"
                      data-full-src="{{ asset('storage/app/public/' . $documents['building_plan']['path']) }}">
                  </div>
                </div>
              @endif
              
              @if(isset($documents['architectural_design']))
                <div class="border rounded-lg overflow-hidden">
                  <div class="p-3 bg-gray-50 border-b">
                    <h4 class="font-medium">Architectural Design</h4>
                    <p class="text-xs text-gray-500">
                      {{ $documents['architectural_design']['original_name'] ?? '' }}
                    </p>
                  </div>
                  <div class="p-4 flex justify-center">
                    <img 
                      src="{{ asset('storage/' . $documents['architectural_design']['path']) }}" 
                      alt="Architectural Design" 
                      class="max-h-64 object-contain document-preview"
                      data-full-src="{{ asset('storage/' . $documents['architectural_design']['path']) }}">
                  </div>
                </div>
              @endif
            @else
              <div class="col-span-2 p-8 text-center text-gray-500">
                <i data-lucide="file-x" class="w-12 h-12 mx-auto mb-4 text-gray-400"></i>
                <p>No survey plan documents available</p>
              </div>
            @endif
          </div>
        </div>
        
        <div class="flex justify-end p-4 border-t">
          <button type="button" class="close-modal px-4 py-2 bg-gray-200 rounded hover:bg-gray-300 text-sm">
            Close
          </button>
        </div>
      </div>
    </div>
  </div>


  <div id="image-viewer-modal" class="fixed inset-0 z-[70] hidden overflow-hidden bg-black bg-opacity-90">
    <div class="absolute top-4 right-4 flex space-x-2">
      <button type="button" id="download-image" class="p-2 bg-black bg-opacity-50 rounded-full text-white hover:bg-opacity-70" title="Download">
        <i data-lucide="download" class="w-6 h-6"></i>
      </button>
      <button type="button" id="zoom-in" class="p-2 bg-black bg-opacity-50 rounded-full text-white hover:bg-opacity-70" title="Zoom In">
        <i data-lucide="zoom-in" class="w-6 h-6"></i>
      </button>
      <button type="button" id="zoom-out" class="p-2 bg-black bg-opacity-50 rounded-full text-white hover:bg-opacity-70" title="Zoom Out">
        <i data-lucide="zoom-out" class="w-6 h-6"></i>
      </button>
      <button type="button" id="rotate-image" class="p-2 bg-black bg-opacity-50 rounded-full text-white hover:bg-opacity-70" title="Rotate">
        <i data-lucide="rotate-cw" class="w-6 h-6"></i>
      </button>
      <button type="button" id="close-image-viewer" class="p-2 bg-black bg-opacity-50 rounded-full text-white hover:bg-opacity-70" title="Close">
        <i data-lucide="x" class="w-6 h-6"></i>
      </button>
    </div>
    <div class="absolute left-4 top-1/2 transform -translate-y-1/2">
      <button type="button" id="prev-image" class="p-2 bg-black bg-opacity-50 rounded-full text-white hover:bg-opacity-70 disabled:opacity-30" title="Previous Image">
        <i data-lucide="chevron-left" class="w-8 h-8"></i>
      </button>
    </div>
    <div class="absolute right-4 top-1/2 transform -translate-y-1/2">
      <button type="button" id="next-image" class="p-2 bg-black bg-opacity-50 rounded-full text-white hover:bg-opacity-70 disabled:opacity-30" title="Next Image">
        <i data-lucide="chevron-right" class="w-8 h-8"></i>
      </button>
    </div>
    <div class="flex items-center justify-center h-full p-4">
      <div id="image-loading" class="absolute flex items-center justify-center bg-black bg-opacity-30 rounded-lg p-4 hidden">
        <i data-lucide="loader" class="w-8 h-8 text-white animate-spin"></i>
      </div>
      <img id="full-size-image" src="" alt="Full size document" class="max-w-full max-h-[90vh] object-contain transition-transform duration-200" style="transform: scale(1) rotate(0deg);">
    </div>
    <div class="absolute bottom-4 left-0 right-0 text-center text-white">
      <span id="image-counter" class="bg-black bg-opacity-50 px-4 py-2 rounded-full"></span>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Initialize modal functionality
      const modal = document.getElementById('survey-plan-modal');
      const imageViewerModal = document.getElementById('image-viewer-modal');
      const fullSizeImage = document.getElementById('full-size-image');
      const imageLoading = document.getElementById('image-loading');
      const imageCounter = document.getElementById('image-counter');
      const prevButton = document.getElementById('prev-image');
      const nextButton = document.getElementById('next-image');
      
      let currentScale = 1;
      let currentRotation = 0;
      let documentPreviews = [];
      let currentImageIndex = 0;
      
      // Open modal
      document.getElementById('view-survey-plan').addEventListener('click', function() {
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        lucide.createIcons(); // Reinitialize icons in modal
      });
      
      // Close modal handlers
      document.getElementById('close-modal').addEventListener('click', closeModal);
      document.getElementById('modal-backdrop').addEventListener('click', closeModal);
      document.querySelectorAll('.close-modal').forEach(btn => {
        btn.addEventListener('click', closeModal);
      });
      
      function closeModal() {
        modal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
      }
      
      // Image preview handlers
      document.querySelectorAll('.document-preview').forEach((img, index) => {
        documentPreviews.push(img);
        img.addEventListener('click', function() {
          showImageLoading();
          currentImageIndex = index;
          fullSizeImage.src = this.getAttribute('data-full-src');
          imageViewerModal.classList.remove('hidden');
          document.body.classList.add('overflow-hidden');
          updateNavButtons();
          updateImageCounter();
          resetImageTransforms();
          lucide.createIcons(); // Ensure icons are initialized
        });
      });
      
      // Show loading indicator
      function showImageLoading() {
        imageLoading.classList.remove('hidden');
        fullSizeImage.addEventListener('load', function onLoad() {
          imageLoading.classList.add('hidden');
          fullSizeImage.removeEventListener('load', onLoad);
        });
      }
      
      // Update image counter display
      function updateImageCounter() {
        if (documentPreviews.length > 1) {
          imageCounter.textContent = `${currentImageIndex + 1} / ${documentPreviews.length}`;
          imageCounter.classList.remove('hidden');
        } else {
          imageCounter.classList.add('hidden');
        }
      }
      
      // Update navigation button states
      function updateNavButtons() {
        prevButton.disabled = currentImageIndex === 0;
        nextButton.disabled = currentImageIndex === documentPreviews.length - 1;
      }
      
      // Navigation handlers
      prevButton.addEventListener('click', function() {
        if (currentImageIndex > 0) {
          currentImageIndex--;
          showImageLoading();
          fullSizeImage.src = documentPreviews[currentImageIndex].getAttribute('data-full-src');
          updateNavButtons();
          updateImageCounter();
          resetImageTransforms();
        }
      });
      
      nextButton.addEventListener('click', function() {
        if (currentImageIndex < documentPreviews.length - 1) {
          currentImageIndex++;
          showImageLoading();
          fullSizeImage.src = documentPreviews[currentImageIndex].getAttribute('data-full-src');
          updateNavButtons();
          updateImageCounter();
          resetImageTransforms();
        }
      });
      
      // Zoom handlers
      document.getElementById('zoom-in').addEventListener('click', function() {
        currentScale += 0.25;
        updateImageTransform();
      });
      
      document.getElementById('zoom-out').addEventListener('click', function(e) {
        e.preventDefault(); // Prevent default action
        if (currentScale > 0.5) {
          currentScale -= 0.25;
          updateImageTransform();
        }
      });
      
      // Rotation handler
      document.getElementById('rotate-image').addEventListener('click', function() {
        currentRotation += 90;
        if (currentRotation >= 360) currentRotation = 0;
        updateImageTransform();
      });
      
      // Download handler
      document.getElementById('download-image').addEventListener('click', function() {
        if (fullSizeImage.src) {
          const a = document.createElement('a');
          a.href = fullSizeImage.src;
          a.download = 'document-' + (currentImageIndex + 1) + '.jpg';
          a.click();
        }
      });
      
      // Update image transform
      function updateImageTransform() {
        fullSizeImage.style.transform = `scale(${currentScale}) rotate(${currentRotation}deg)`;
      }
      
      // Reset transforms
      function resetImageTransforms() {
        currentScale = 1;
        currentRotation = 0;
        updateImageTransform();
      }
      
      // Close image viewer
      document.getElementById('close-image-viewer').addEventListener('click', function(e) {
        e.preventDefault(); // Prevent default action
        closeImageViewer();
      });
      
      imageViewerModal.addEventListener('click', function(e) {
        if (e.target === imageViewerModal) {
          e.preventDefault(); // Prevent default action
          closeImageViewer();
        }
      });
      
      function closeImageViewer() {
        imageViewerModal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
        resetImageTransforms();
      }
      
      // Keyboard navigation
      document.addEventListener('keydown', function(e) {
        if (imageViewerModal.classList.contains('hidden')) return;
        
        switch (e.key) {
          case 'Escape':
            closeImageViewer();
            break;
          case 'ArrowLeft':
            if (!prevButton.disabled) prevButton.click();
            break;
          case 'ArrowRight':
            if (!nextButton.disabled) nextButton.click();
            break;
          case '+':
            document.getElementById('zoom-in').click();
            break;
          case '-':
            document.getElementById('zoom-out').click();
            break;
          case 'r':
            document.getElementById('rotate-image').click();
            break;
        }
      });
      
      // Add touch swipe support for mobile
      let touchStartX = 0;
      let touchEndX = 0;
      
      imageViewerModal.addEventListener('touchstart', function(e) {
        touchStartX = e.changedTouches[0].screenX;
      }, false);
      
      imageViewerModal.addEventListener('touchend', function(e) {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
      }, false);
      
      function handleSwipe() {
        const swipeThreshold = 50;
        if (touchEndX < touchStartX - swipeThreshold) {
          // Swipe left - next image
          if (!nextButton.disabled) nextButton.click();
        }
        if (touchEndX > touchStartX + swipeThreshold) {
          // Swipe right - previous image
          if (!prevButton.disabled) prevButton.click();
        }
      }
    });
  </script>