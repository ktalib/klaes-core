<div class="form-section" id="step2">
  <div class="p-6">
    <div class="flex justify-between items-center mb-4">
      <h2 class="text-xl font-bold text-center text-gray-800">MINISTRY OF LAND AND PHYSICAL PLANNING</h2>
      <button id="closeModal2" class="text-gray-500 hover:text-gray-700">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>
      
      <div class="mb-6">
        <div class="flex items-center mb-2">
          <i data-lucide="file-text" class="w-5 h-5 mr-2 text-green-600"></i>
          <h3 class="text-lg font-bold">Application for Sectional Titling - Main Application</h3>
          <div class="ml-auto flex items-center">
            <span class="text-gray-600 mr-2">Land Use:</span>
            <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-sm">
               @if (request()->query('landuse') === 'Commercial')
                              Commercial
                            @elseif (request()->query('landuse') === 'Residential')
                                Residential
                            @elseif (request()->query('landuse') === 'Industrial')
                                Industrial
                            @else
                                Mixed Use
                            @endif 
            </span>
          </div>
        </div>
        <p class="text-gray-600">Complete the form below to submit a new primary application for sectional titling</p>
      </div>

      <div class="flex items-center mb-8">
        <div class="flex items-center mr-4">
          <div class="step-circle inactive cursor-pointer" onclick="goToStep(1)">1</div>
        </div>
        <div class="flex items-center mr-4">
          <div class="step-circle active cursor-pointer" onclick="goToStep(2)">2</div>
        </div>
        <div class="flex items-center mr-4">
          <div class="step-circle inactive cursor-pointer" onclick="goToStep(3)">3</div>
        </div> 
        <div class="flex items-center mr-4">
          <div class="step-circle inactive cursor-pointer" onclick="goToStep(4)">4</div>
        </div>
        <div class="flex items-center mr-4">
          <div class="step-circle inactive cursor-pointer" onclick="goToStep(5)">5</div>
        </div>
        <div class="ml-4 step-status-text" data-step-indicator data-step-label="Shared Areas">Step 2 - Shared Areas</div>
      </div>

      <div class="mb-6">
        <div class="flex items-start mb-4">
          <i data-lucide="home" class="w-5 h-5 mr-2 text-green-600"></i>
          <span class="font-medium">Shared Areas</span>
        </div>
        
     
  <div class="space-y-4">
    <p class="mb-2 text-gray-700">Select all shared areas that apply:</p>
    
    <!-- Search Box -->
    <div class="mb-4">
      <div class="relative">
        <input type="text" id="sharedAreasSearch" 
               class="w-full px-4 py-2 pl-10 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500" 
               placeholder="Search shared areas..."
               onkeyup="filterSharedAreas()">
        <i data-lucide="search" class="w-5 h-5 text-gray-400 absolute left-3 top-2.5"></i>
      </div>
    </div>
    
    <!-- Check All / Uncheck All Buttons -->
    <div class="mb-2 flex gap-2">
      <button type="button" class="px-3 py-1 bg-green-100 text-green-800 rounded text-sm border border-green-200 hover:bg-green-200" onclick="checkAllSharedAreas()">Check All</button>
      <button type="button" class="px-3 py-1 bg-red-100 text-red-800 rounded text-sm border border-red-200 hover:bg-red-200" onclick="uncheckAllSharedAreas()">Uncheck All</button>
      <span id="selectedCount" class="ml-auto px-3 py-1 bg-blue-100 text-blue-800 rounded text-sm">0 selected</span>
    </div>
    
    <div class="grid grid-cols-3 gap-4" id="sharedAreasGrid">
      <div class="flex items-center shared-area-item" data-search-term="hallways corridors passages">
        <input type="checkbox" id="hallways" name="shared_areas[]" value="Hallways" class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500" onchange="updateSelectedCount()">
        <label for="hallways" class="ml-2 text-gray-700 flex items-center cursor-pointer">
          <i data-lucide="door-open" class="w-4 h-4 mr-1 text-gray-500"></i>
          Hallways
        </label>
      </div>
      
      <div class="flex items-center shared-area-item" data-search-term="gardens landscaping green space">
        <input type="checkbox" id="gardens" name="shared_areas[]" value="Gardens" class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500" onchange="updateSelectedCount()">
        <label for="gardens" class="ml-2 text-gray-700 flex items-center cursor-pointer">
          <i data-lucide="flower" class="w-4 h-4 mr-1 text-gray-500"></i>
          Gardens
        </label>
      </div>
      
      <div class="flex items-center shared-area-item" data-search-term="parking lots carport garage">
        <input type="checkbox" id="parking_lots" name="shared_areas[]" value="Parking Lots" class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500" onchange="updateSelectedCount()">
        <label for="parking_lots" class="ml-2 text-gray-700 flex items-center cursor-pointer">
          <i data-lucide="car" class="w-4 h-4 mr-1 text-gray-500"></i>
          Parking Lots
        </label>
      </div>
      
      <div class="flex items-center shared-area-item" data-search-term="swimming pool aquatic center">
        <input type="checkbox" id="swimming_pool" name="shared_areas[]" value="Swimming Pool" class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500" onchange="updateSelectedCount()">
        <label for="swimming_pool" class="ml-2 text-gray-700 flex items-center cursor-pointer">
          <i data-lucide="droplets" class="w-4 h-4 mr-1 text-gray-500"></i>
          Swimming Pool
        </label>
      </div>
      
      <div class="flex items-center shared-area-item" data-search-term="gym fitness center workout exercise">
        <input type="checkbox" id="gym" name="shared_areas[]" value="Gym" class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500" onchange="updateSelectedCount()">
        <label for="gym" class="ml-2 text-gray-700 flex items-center cursor-pointer">
          <i data-lucide="dumbbell" class="w-4 h-4 mr-1 text-gray-500"></i>
          Gym/Fitness Center
        </label>
      </div>
      
      <div class="flex items-center shared-area-item" data-search-term="rooftop terrace deck patio">
        <input type="checkbox" id="rooftop" name="shared_areas[]" value="Rooftop Terrace" class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500" onchange="updateSelectedCount()">
        <label for="rooftop" class="ml-2 text-gray-700 flex items-center cursor-pointer">
          <i data-lucide="mountain" class="w-4 h-4 mr-1 text-gray-500"></i>
          Rooftop Terrace
        </label>
      </div>
      
      <div class="flex items-center shared-area-item" data-search-term="lobby reception entrance foyer">
        <input type="checkbox" id="lobby" name="shared_areas[]" value="Lobby" class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500" onchange="updateSelectedCount()">
        <label for="lobby" class="ml-2 text-gray-700 flex items-center cursor-pointer">
          <i data-lucide="sofa" class="w-4 h-4 mr-1 text-gray-500"></i>
          Lobby
        </label>
      </div>
      
      <div class="flex items-center shared-area-item" data-search-term="elevator lift">
        <input type="checkbox" id="elevator" name="shared_areas[]" value="Elevator" class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500" onchange="updateSelectedCount()">
        <label for="elevator" class="ml-2 text-gray-700 flex items-center cursor-pointer">
          <i data-lucide="arrow-up-down" class="w-4 h-4 mr-1 text-gray-500"></i>
          Elevator
        </label>
      </div>
      
      <div class="flex items-center shared-area-item" data-search-term="storage areas lockers warehouse">
        <input type="checkbox" id="storage" name="shared_areas[]" value="Storage Areas" class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500" onchange="updateSelectedCount()">
        <label for="storage" class="ml-2 text-gray-700 flex items-center cursor-pointer">
          <i data-lucide="package" class="w-4 h-4 mr-1 text-gray-500"></i>
          Storage Areas
        </label>
      </div>
      
      <!-- Additional shared areas -->
      <div class="flex items-center shared-area-item" data-search-term="conference room meeting boardroom">
        <input type="checkbox" id="conference_room" name="shared_areas[]" value="Conference Room" class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500" onchange="updateSelectedCount()">
        <label for="conference_room" class="ml-2 text-gray-700 flex items-center cursor-pointer">
          <i data-lucide="users" class="w-4 h-4 mr-1 text-gray-500"></i>
          Conference Room
        </label>
      </div>
      
      <div class="flex items-center shared-area-item" data-search-term="playground children kids recreation">
        <input type="checkbox" id="playground" name="shared_areas[]" value="Playground" class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500" onchange="updateSelectedCount()">
        <label for="playground" class="ml-2 text-gray-700 flex items-center cursor-pointer">
          <i data-lucide="sparkles" class="w-4 h-4 mr-1 text-gray-500"></i>
          Playground
        </label>
      </div>
      
      <div class="flex items-center shared-area-item" data-search-term="security post guardhouse gatehouse">
        <input type="checkbox" id="security_post" name="shared_areas[]" value="Security Post" class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500" onchange="updateSelectedCount()">
        <label for="security_post" class="ml-2 text-gray-700 flex items-center cursor-pointer">
          <i data-lucide="shield" class="w-4 h-4 mr-1 text-gray-500"></i>
          Security Post
        </label>
      </div>
      
      <div class="flex items-center shared-area-item" data-search-term="generator room power backup">
        <input type="checkbox" id="generator_room" name="shared_areas[]" value="Generator Room" class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500" onchange="updateSelectedCount()">
        <label for="generator_room" class="ml-2 text-gray-700 flex items-center cursor-pointer">
          <i data-lucide="zap" class="w-4 h-4 mr-1 text-gray-500"></i>
          Generator Room
        </label>
      </div>
      
      <div class="flex items-center shared-area-item" data-search-term="laundry room washing drying">
        <input type="checkbox" id="laundry_room" name="shared_areas[]" value="Laundry Room" class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500" onchange="updateSelectedCount()">
        <label for="laundry_room" class="ml-2 text-gray-700 flex items-center cursor-pointer">
          <i data-lucide="shirt" class="w-4 h-4 mr-1 text-gray-500"></i>
          Laundry Room
        </label>
      </div>
      
      <div class="flex items-center shared-area-item" data-search-term="community hall function hall events">
        <input type="checkbox" id="community_hall" name="shared_areas[]" value="Community Hall" class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500" onchange="updateSelectedCount()">
        <label for="community_hall" class="ml-2 text-gray-700 flex items-center cursor-pointer">
          <i data-lucide="home" class="w-4 h-4 mr-1 text-gray-500"></i>
          Community Hall
        </label>
      </div>

      <div class="flex items-center shared-area-item" data-search-term="shared compounds outdoor yard">
        <input type="checkbox" id="shared_compounds" name="shared_areas[]" value="Shared Compounds" class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500" onchange="updateSelectedCount()">
        <label for="shared_compounds" class="ml-2 text-gray-700 flex items-center cursor-pointer">
          <i data-lucide="layers" class="w-4 h-4 mr-1 text-gray-500"></i>
          Shared Compound
        </label>
      </div>
      
      <!-- More Shared Areas -->
      <div class="flex items-center shared-area-item" data-search-term="bbq area barbecue grill outdoor cooking">
        <input type="checkbox" id="bbq_area" name="shared_areas[]" value="BBQ Area" class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500" onchange="updateSelectedCount()">
        <label for="bbq_area" class="ml-2 text-gray-700 flex items-center cursor-pointer">
          <i data-lucide="flame" class="w-4 h-4 mr-1 text-gray-500"></i>
          BBQ Area
        </label>
      </div>
      
      <div class="flex items-center shared-area-item" data-search-term="tennis court sports recreation">
        <input type="checkbox" id="tennis_court" name="shared_areas[]" value="Tennis Court" class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500" onchange="updateSelectedCount()">
        <label for="tennis_court" class="ml-2 text-gray-700 flex items-center cursor-pointer">
          <i data-lucide="circle" class="w-4 h-4 mr-1 text-gray-500"></i>
          Tennis Court
        </label>
      </div>
      
      <div class="flex items-center shared-area-item" data-search-term="basketball court sports recreation">
        <input type="checkbox" id="basketball_court" name="shared_areas[]" value="Basketball Court" class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500" onchange="updateSelectedCount()">
        <label for="basketball_court" class="ml-2 text-gray-700 flex items-center cursor-pointer">
          <i data-lucide="circle-dot" class="w-4 h-4 mr-1 text-gray-500"></i>
          Basketball Court
        </label>
      </div>
      
      <div class="flex items-center shared-area-item" data-search-term="jogging track running trail fitness">
        <input type="checkbox" id="jogging_track" name="shared_areas[]" value="Jogging Track" class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500" onchange="updateSelectedCount()">
        <label for="jogging_track" class="ml-2 text-gray-700 flex items-center cursor-pointer">
          <i data-lucide="footprints" class="w-4 h-4 mr-1 text-gray-500"></i>
          Jogging Track
        </label>
      </div>
      
      <div class="flex items-center shared-area-item" data-search-term="sauna spa wellness steam">
        <input type="checkbox" id="sauna" name="shared_areas[]" value="Sauna" class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500" onchange="updateSelectedCount()">
        <label for="sauna" class="ml-2 text-gray-700 flex items-center cursor-pointer">
          <i data-lucide="waves" class="w-4 h-4 mr-1 text-gray-500"></i>
          Sauna/Spa
        </label>
      </div>
      
      <div class="flex items-center shared-area-item" data-search-term="library reading room study">
        <input type="checkbox" id="library" name="shared_areas[]" value="Library" class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500" onchange="updateSelectedCount()">
        <label for="library" class="ml-2 text-gray-700 flex items-center cursor-pointer">
          <i data-lucide="book-open" class="w-4 h-4 mr-1 text-gray-500"></i>
          Library
        </label>
      </div>
      
      <div class="flex items-center shared-area-item" data-search-term="cinema theater movie room entertainment">
        <input type="checkbox" id="cinema_room" name="shared_areas[]" value="Cinema Room" class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500" onchange="updateSelectedCount()">
        <label for="cinema_room" class="ml-2 text-gray-700 flex items-center cursor-pointer">
          <i data-lucide="film" class="w-4 h-4 mr-1 text-gray-500"></i>
          Cinema Room
        </label>
      </div>
      
      <div class="flex items-center shared-area-item" data-search-term="game room billiards entertainment recreation">
        <input type="checkbox" id="game_room" name="shared_areas[]" value="Game Room" class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500" onchange="updateSelectedCount()">
        <label for="game_room" class="ml-2 text-gray-700 flex items-center cursor-pointer">
          <i data-lucide="gamepad-2" class="w-4 h-4 mr-1 text-gray-500"></i>
          Game Room
        </label>
      </div>
      
      <div class="flex items-center shared-area-item" data-search-term="mailroom mail delivery packages">
        <input type="checkbox" id="mailroom" name="shared_areas[]" value="Mailroom" class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500" onchange="updateSelectedCount()">
        <label for="mailroom" class="ml-2 text-gray-700 flex items-center cursor-pointer">
          <i data-lucide="mail" class="w-4 h-4 mr-1 text-gray-500"></i>
          Mailroom
        </label>
      </div>
      
      <div class="flex items-center shared-area-item" data-search-term="bike storage bicycle parking">
        <input type="checkbox" id="bike_storage" name="shared_areas[]" value="Bike Storage" class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500" onchange="updateSelectedCount()">
        <label for="bike_storage" class="ml-2 text-gray-700 flex items-center cursor-pointer">
          <i data-lucide="bike" class="w-4 h-4 mr-1 text-gray-500"></i>
          Bike Storage
        </label>
      </div>
      
      <div class="flex items-center shared-area-item" data-search-term="pet area dog park animals">
        <input type="checkbox" id="pet_area" name="shared_areas[]" value="Pet Area" class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500" onchange="updateSelectedCount()">
        <label for="pet_area" class="ml-2 text-gray-700 flex items-center cursor-pointer">
          <i data-lucide="paw-print" class="w-4 h-4 mr-1 text-gray-500"></i>
          Pet Area
        </label>
      </div>
      
      <div class="flex items-center shared-area-item" data-search-term="coworking space office workspace">
        <input type="checkbox" id="coworking_space" name="shared_areas[]" value="Coworking Space" class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500" onchange="updateSelectedCount()">
        <label for="coworking_space" class="ml-2 text-gray-700 flex items-center cursor-pointer">
          <i data-lucide="briefcase" class="w-4 h-4 mr-1 text-gray-500"></i>
          Co-working Space
        </label>
      </div>
      
      <div class="flex items-center shared-area-item" data-search-term="reception area waiting lounge">
        <input type="checkbox" id="reception_area" name="shared_areas[]" value="Reception Area" class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500" onchange="updateSelectedCount()">
        <label for="reception_area" class="ml-2 text-gray-700 flex items-center cursor-pointer">
          <i data-lucide="user-round" class="w-4 h-4 mr-1 text-gray-500"></i>
          Reception Area
        </label>
      </div>
      
      <div class="flex items-center shared-area-item" data-search-term="staircase stairs emergency exit">
        <input type="checkbox" id="staircase" name="shared_areas[]" value="Staircase" class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500" onchange="updateSelectedCount()">
        <label for="staircase" class="ml-2 text-gray-700 flex items-center cursor-pointer">
          <i data-lucide="move-up" class="w-4 h-4 mr-1 text-gray-500"></i>
          Staircase
        </label>
      </div>
      
      <!-- Other Option -->
      <div class="flex items-center shared-area-item" data-search-term="other custom specify">
        <input type="checkbox" id="other_areas" name="shared_areas[]" value="Other" class="w-4 h-4 text-green-600 border-gray-300 rounded focus:ring-green-500" onchange="updateSelectedCount(); toggleOtherAreasTextarea()">
        <label for="other_areas" class="ml-2 text-gray-700 flex items-center cursor-pointer">
          <i data-lucide="plus-circle" class="w-4 h-4 mr-1 text-gray-500"></i>
          Other
        </label>
      </div>
    </div>
    
    <!-- Other Areas Textarea (Initially Hidden) -->
    <div id="other_areas_container" class="mt-4" style="display: none;">
      <label for="other_areas_detail" class="block text-sm font-medium text-gray-700 mb-1">Please specify other shared areas:</label>
      <p class="text-xs text-gray-500 mb-2">
        Enter the items in this exact order and separate them with commas: <span class="font-semibold text-gray-700">Access, Hallway, Generator Room, Passage</span>.
        <span class="block mt-1">Example: <span class="font-semibold">Access, Hallway, Generator Room, Passage</span></span>
      </p>
      <textarea id="other_areas_detail" name="other_areas_detail" rows="3" class="w-full p-2 border border-gray-300 rounded-md" placeholder="Access, Hallway, Generator Room, Passage"></textarea>
      <div id="other_areas_hidden_inputs"></div>
    </div>
  </div>
  
  <div class="flex justify-between mt-8">
    <button class="px-4 py-2 bg-white border border-gray-300 rounded-md" id="backStep2">Back</button>
    <div class="flex items-center">
      <span class="text-sm text-gray-500 mr-4 step-status-text" data-step-indicator data-step-total="5">Step 2 of 5</span>
      <button class="px-4 py-2 bg-black text-white rounded-md" id="nextStep2">Next</button>
    </div>
  </div>
</div>
</div>
</div>

<script>
  const OTHER_AREAS_EXPECTED_ORDER = ['Access', 'Hallway', 'BA', 'BCD'];

  function syncOtherAreasToHiddenInputs() {
    const checkbox = document.getElementById('other_areas');
    const textarea = document.getElementById('other_areas_detail');
    const hiddenContainer = document.getElementById('other_areas_hidden_inputs');

    if (!hiddenContainer) {
      return;
    }

    hiddenContainer.innerHTML = '';

    if (!checkbox || !checkbox.checked || !textarea) {
      updateSelectedCount();
      return;
    }

    const rawTokens = textarea.value
      .split(',')
      .map(token => token.trim())
      .filter(token => token.length > 0);

    if (rawTokens.length === 0) {
      updateSelectedCount();
      return;
    }

    const remainingTokens = [...rawTokens];
    const orderedMatches = [];

    OTHER_AREAS_EXPECTED_ORDER.forEach(expected => {
      const matchIndex = remainingTokens.findIndex(token => token.toLowerCase() === expected.toLowerCase());
      if (matchIndex > -1) {
        orderedMatches.push(expected);
        remainingTokens.splice(matchIndex, 1);
      }
    });

    const finalValues = [...orderedMatches];

    remainingTokens.forEach(token => {
      if (!finalValues.some(existing => existing.toLowerCase() === token.toLowerCase())) {
        finalValues.push(token);
      }
    });

    finalValues.forEach(value => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'shared_areas[]';
      input.value = value;
      input.setAttribute('data-other-area', 'true');
      hiddenContainer.appendChild(input);
    });

    updateSelectedCount();
  }

  // Filter shared areas based on search input
  function filterSharedAreas() {
    const searchInput = document.getElementById('sharedAreasSearch');
    const searchTerm = searchInput.value.toLowerCase();
    const areaItems = document.querySelectorAll('.shared-area-item');
    let visibleCount = 0;

    areaItems.forEach(item => {
      const searchTerms = item.getAttribute('data-search-term') || '';
      if (searchTerms.toLowerCase().includes(searchTerm)) {
        item.style.display = 'flex';
        visibleCount++;
      } else {
        item.style.display = 'none';
      }
    });

    // Show message if no results
    const grid = document.getElementById('sharedAreasGrid');
    let noResultsMsg = document.getElementById('noResultsMessage');
    
    if (visibleCount === 0 && searchTerm !== '') {
      if (!noResultsMsg) {
        noResultsMsg = document.createElement('div');
        noResultsMsg.id = 'noResultsMessage';
        noResultsMsg.className = 'col-span-3 text-center py-8 text-gray-500';
        noResultsMsg.innerHTML = '<i data-lucide="search-x" class="w-8 h-8 mx-auto mb-2 text-gray-400"></i><p>No shared areas found matching "' + searchTerm + '"</p>';
        grid.appendChild(noResultsMsg);
        if (typeof lucide !== 'undefined') {
          lucide.createIcons();
        }
      }
    } else if (noResultsMsg) {
      noResultsMsg.remove();
    }
  }

  // Update selected count
  function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('input[name="shared_areas[]"]:checked');
    const syncedOtherInputs = document.querySelectorAll('#other_areas_hidden_inputs input[name="shared_areas[]"]');
    const count = checkboxes.length + syncedOtherInputs.length;
    const countElement = document.getElementById('selectedCount');
    if (countElement) {
      countElement.textContent = count + ' selected';
    }
  }

  // Toggle other areas textarea
  function toggleOtherAreasTextarea() {
    const checkbox = document.getElementById('other_areas');
    const container = document.getElementById('other_areas_container');
    
    if (checkbox && checkbox.checked) {
      container.style.display = 'block';
    } else {
      container.style.display = 'none';
      // Clear the textarea when unchecked
      const textarea = document.getElementById('other_areas_detail');
      if (textarea) textarea.value = '';
    }

    syncOtherAreasToHiddenInputs();
  }

  // Check all shared areas except "Other"
  function checkAllSharedAreas() {
    document.querySelectorAll('input[name="shared_areas[]"]').forEach(cb => {
      if (cb.value.toLowerCase() !== 'other' && cb.closest('.shared-area-item').style.display !== 'none') { 
        cb.checked = true;
      }
    });
    updateSelectedCount();
    toggleOtherAreasTextarea();
  }

  // Uncheck all shared areas
  function uncheckAllSharedAreas() {
    document.querySelectorAll('input[name="shared_areas[]"]').forEach(cb => {
      cb.checked = false;
    });
    updateSelectedCount();
    toggleOtherAreasTextarea();
  }
  
  // Initialize on page load to handle pre-filled forms
  document.addEventListener('DOMContentLoaded', function() {
    toggleOtherAreasTextarea();
    syncOtherAreasToHiddenInputs();
    updateSelectedCount();
    
    // Re-initialize Lucide icons
    if (typeof lucide !== 'undefined') {
      lucide.createIcons();
    }

    const otherAreasTextarea = document.getElementById('other_areas_detail');
    if (otherAreasTextarea) {
      otherAreasTextarea.addEventListener('input', syncOtherAreasToHiddenInputs);
      otherAreasTextarea.addEventListener('change', syncOtherAreasToHiddenInputs);
      otherAreasTextarea.addEventListener('blur', syncOtherAreasToHiddenInputs);
    }

    const otherAreasCheckbox = document.getElementById('other_areas');
    if (otherAreasCheckbox) {
      otherAreasCheckbox.addEventListener('change', syncOtherAreasToHiddenInputs);
    }

    const nextStepButton = document.getElementById('nextStep2');
    if (nextStepButton) {
      nextStepButton.addEventListener('click', syncOtherAreasToHiddenInputs);
    }
  });
</script>