<div class="relative dropdown-container">
   <!-- Dropdown Toggle Button -->
   <button type="button" class="dropdown-toggle p-2 hover:bg-gray-100 focus:outline-none rounded-full" onclick="customToggleDropdown(this, event)">
      <i data-lucide="more-horizontal" class="w-5 h-5"></i>
   </button>
   <!-- Dropdown Menu -->
   <ul class="fixed action-menu z-50 bg-white border rounded-lg shadow-lg hidden w-56">
      <li> 
         <a href="{{ route('sectionaltitling.viewrecorddetail_sub', $app->id) }}" class="block w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
            <i data-lucide="eye" class="w-4 h-4 text-blue-600"></i>
            <span>View Unit Application</span>
         </a>
      </li>
     
      <li>
         <a href="{{ route('sub-actions.other-departments', $app->id) }}?is=survey" class="block w-full text-left px-4 py-2 hover:bg-gray-100 flex items-center space-x-2">
         <i data-lucide="layout-grid" class="w-4 h-4 text-red-500"></i>
         <span>Survey Record</span>
         </a>
      </li>
      
 
  
    
   </ul>
 </div>

<script>
function customToggleDropdown(button, event) {
   event.stopPropagation();
   const dropdown = button.closest('.dropdown-container').querySelector('.action-menu');
   
   // Toggle visibility
   dropdown.classList.toggle('hidden');
   
   if (!dropdown.classList.contains('hidden')) {
      // Get button position
      const rect = button.getBoundingClientRect();
      
      // Position dropdown above the button
      dropdown.style.top = (rect.top - dropdown.offsetHeight - 5) + 'px';
      dropdown.style.left = (rect.left - dropdown.offsetWidth + rect.width) + 'px';
      
      // Check if dropdown would appear off the top of the screen
      if (rect.top - dropdown.offsetHeight < 0) {
         // If so, position it below the button instead
         dropdown.style.top = (rect.bottom + 5) + 'px';
      }
   }
}

// Close dropdown when clicking outside
document.addEventListener('click', function (event) {
   const dropdowns = document.querySelectorAll('.action-menu');
   dropdowns.forEach(dropdown => {
      if (!dropdown.contains(event.target) && 
         !dropdown.previousElementSibling?.contains(event.target)) {
         dropdown.classList.add('hidden');
      }
   });
});
</script>


