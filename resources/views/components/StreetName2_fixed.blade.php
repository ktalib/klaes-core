<div class="space-y-2">
    <label for="streetName" class="text-xs text-gray-600">Street Name</label>
    <select id="streetName" class="form-input text-sm property-input" 
            @change="handleStreetChange($event.target.value)"
            name="streetName">
        <option value="" selected>Select Street Name</option>
        <option value="10TH ST">10TH ST</option>
        <option value="11TH AV">11TH AV</option>
        <option value="other">Other</option>
    </select>
    <input 
        type="text" 
        id="otherStreetName" 
        x-show="showOtherStreet" 
        x-model="customStreet" 
        name="streetName" 
        class="form-input text-sm property-input mt-2" 
        placeholder="Please specify other street name"
        x-transition
        @input="handleStreetChange($event.target.value)"
    >
</div>