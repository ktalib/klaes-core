<div>
  <label for="applicantTitle" class="block text-sm font-medium text-gray-700 mb-1">
    Title <span class="text-red-500">*</span>
  </label>

  <select 
    id="applicantTitle" 
    name="applicant_title" 
    required
    class="w-full py-3 px-4 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm uppercase"
    onchange="toggleOtherTitleInput(); updateApplicantNamePreview?.()"
  >
    <option value="" disabled selected>Select title</option>
    <option value="Mr.">Mr.</option>
    <option value="Mrs.">Mrs.</option>
    <option value="Chief">Chief</option>
    <option value="Master">Master</option>
    <option value="Capt">Capt</option>
    <option value="Coln">Coln</option>
    <option value="HRH">HRH</option>
    <option value="Mallam">Mallam</option>
    <option value="Prof">Prof</option>
    <option value="Dr.">Dr.</option>
    <option value="Alhaji">Alhaji</option>
    <option value="Hajia">Hajia</option>
    <option value="High Chief">High Chief</option>
    <option value="Lady">Lady</option>
    <option value="Senator">Senator</option>
    <option value="Messr">Messr</option>
    <option value="Honorable">Honorable</option>
    <option value="Miss">Miss</option>
    <option value="Rev.">Rev.</option>
    <option value="Barr.">Barr.</option>
    <option value="Arc.">Arc.</option>
    <option value="Sister">Sister</option>
    <option value="Other">Other</option>
  </select>

  <!-- Hidden "Other" input -->
  <div id="otherTitleWrapper" class="mt-3 hidden">
    <label for="otherTitle" class="block text-sm font-medium text-gray-700 mb-1">
      Specify Title <span class="text-red-500">*</span>
    </label>
    <input 
      type="text" 
      id="otherTitle" 
      name="other_title" 
      placeholder="Enter custom title"
      class="w-full py-3 px-4 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all shadow-sm uppercase"
    />
  </div>
</div>

<script>
function toggleOtherTitleInput() {
  const select = document.getElementById("applicantTitle");
  const otherWrapper = document.getElementById("otherTitleWrapper");
  const otherInput = document.getElementById("otherTitle");

  if (select.value === "Other") {
    otherWrapper.classList.remove("hidden");
    otherInput.required = true;
  } else {
    otherWrapper.classList.add("hidden");
    otherInput.required = false;
    otherInput.value = "";
  }
}
</script>
