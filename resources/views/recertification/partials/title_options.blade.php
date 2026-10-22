{{-- Title choices shared by Section A (applicant) and Section B (title holder). A title from the records that is not listed is added as a choice when the land file is read. --}}
<option value="" selected>Select title</option>
@foreach (['Mr.', 'Mrs.', 'Miss', 'Master', 'Alhaji', 'Hajiya', 'Alhaja', 'Mallam', 'Malama', 'Chief', 'High Chief', 'Dr.', 'Prof', 'Engr.', 'Arc.', 'Barr.', 'Capt', 'Coln', 'Honorable', 'Senator', 'Sheikh', 'Pastor', 'Rev.', 'Bishop', 'Sister', 'Lady', 'King', 'Messr', 'Other'] as $titleOption)
    <option value="{{ $titleOption }}">{{ $titleOption }}</option>
@endforeach
