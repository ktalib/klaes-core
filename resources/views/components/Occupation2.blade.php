<div class="space-y-2">
    <label for="Occupation" class="block text-sm font-medium text-gray-700">Occupation <span class="text-red-500">*</span></label>
    <select id="occupation" name="occupation" class="w-full p-2 border border-gray-300 rounded-md text-sm" onchange="toggleOtherOccupation()" required>
           <option value="">Select Occupation</option>
        <option value="Civil Servant">Civil Servant</option>
        <option value="Trader">Trader</option>
        <option value="Farmer">Farmer</option>
        <option value="House Wife">House Wife</option>
        <option value="Unemployed">Unemployed</option>
        <option value="Student">Student</option>
        <option value="Teacher">Teacher</option>
        <option value="Lecturer">Lecturer</option>
        <option value="Business Man">Business Man</option>
        <option value="Banker">Banker</option>
        <option value="Accountant">Accountant</option>
        <option value="Medical Practitioner">Medical Practitioner</option>
        <option value="Military Personnel">Military Personnel</option>
        <option value="Law Enforcement Officer">Law Enforcement Officer</option>
        <option value="Lawyer">Lawyer</option>
        <option value="Judge">Judge</option>
        <option value="Priest">Priest</option>
        <option value="Sportsman">Sportsman</option>
        <option value="Musician">Musician</option>
        <option value="Architect">Architect</option>
        <option value="Engineer">Engineer</option>
        <option value="IT Professional">IT Professional</option>
        <option value="Pharmacist">Pharmacist</option>
        <option value="Financial Analyst">Financial Analyst</option>
        <option value="Real Estate Agent">Real Estate Agent</option>
        <option value="Real Estate Developer">Real Estate Developer</option>
        <option value="Contractor">Contractor</option>
        <option value="Journalist">Journalist</option>
        <option value="Public Office Holder">Public Office Holder</option>
        <option value="Secretary">Secretary</option>
        <option value="Computer Operator">Computer Operator</option>
        <option value="Town Planner">Town Planner</option>
        <option value="Surveyor">Surveyor</option>
        <option value="Hair Dresser">Hair Dresser</option>
        <option value="Barber">Barber</option>
        <option value="Consultant">Consultant</option>
        <option value="other">other</option>
    </select>
    
    <input type="text" 
           id="otherOccupation" 
           class="w-full p-2 border border-gray-300 rounded-md text-sm mt-2 hidden" 
           placeholder="Please specify your occupation"
    >
</div>

<script>
function toggleOtherOccupation() {
    const select = document.getElementById('occupation');
    const otherInput = document.getElementById('otherOccupation');
    
    if (select.value === 'other') {
        otherInput.classList.remove('hidden');
        select.removeAttribute('name');
        select.removeAttribute('required');
        otherInput.setAttribute('name', 'occupation');
        otherInput.setAttribute('required', 'required');
        otherInput.focus();
    } else {
        otherInput.classList.add('hidden');
        otherInput.removeAttribute('name');
        otherInput.removeAttribute('required');
        otherInput.value = '';
        select.setAttribute('name', 'occupation');
        select.setAttribute('required', 'required');
    }
}
</script>
