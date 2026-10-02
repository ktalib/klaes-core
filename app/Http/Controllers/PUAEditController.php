<?php

namespace App\Http\Controllers;

use App\Models\ApplicationMother;
use App\Models\Subapplications;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PUAEditController extends Controller
{
    /**
     * Show the edit form for a Parented Unit Application (PUA)
     */
    public function edit($id)
    {
        $subApplication = Subapplications::findOrFail($id);

        // PUA should have a main_application_id
        $motherApplication = null;
        if ($subApplication->main_application_id) {
            $motherApplication = ApplicationMother::find($subApplication->main_application_id);
        }

        // Get some metadata if mother app exists, otherwise use sub-application's own location (for SUA)
        $propertyLocation = 'N/A';
        if ($motherApplication) {
            $parts = [];
            if (!empty($motherApplication->property_house_no)) $parts[] = $motherApplication->property_house_no;
            if (!empty($motherApplication->property_plot_no)) $parts[] = $motherApplication->property_plot_no;
            if (!empty($motherApplication->property_street_name)) $parts[] = $motherApplication->property_street_name;
            
            $mainLocation = implode(' ', $parts);
            
            $region = [];
            if (!empty($motherApplication->property_district)) $region[] = $motherApplication->property_district;
            if (!empty($motherApplication->property_lga)) $region[] = $motherApplication->property_lga;
            
            if (!empty($region)) {
                $mainLocation .= ($mainLocation ? ' , ' : '') . implode(' ', $region);
            }
            
            $propertyLocation = strtoupper($mainLocation ?: ($motherApplication->property_location ?? 'N/A'));
        } else {
            $propertyLocation = strtoupper($subApplication->property_location ?? 'N/A');
        }

        return view('sectionaltitling.pua_edit.edit', compact('subApplication', 'motherApplication', 'propertyLocation'));
    }

    /**
     * Update the Parented Unit Application (PUA)
     */
    public function update(Request $request, $id)
    {
        $subApplication = Subapplications::findOrFail($id);

        $validated = $request->validate([
            'applicant_type' => 'required|string',
            'first_name' => 'nullable|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'surname' => 'nullable|string|max:255',
            'applicant_title' => 'nullable|string|max:50',
            'corporate_name' => 'nullable|string|max:255',
            'rc_number' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone_number' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'address_house_no' => 'nullable|string|max:255',
            'unit_district' => 'nullable|string|max:255',
            'unit_lga' => 'nullable|string|max:255',
            'unit_state' => 'nullable|string|max:255',
            'block_number' => 'nullable|string|max:255',
            'floor_number' => 'nullable|string|max:255',
            'unit_number' => 'nullable|string|max:255',
            'unit_type' => 'nullable|string|max:255',
            'ownership' => 'nullable|string|max:255',
            'unit_area' => 'nullable|string|max:255',
            'comments' => 'nullable|string',
            'identification_type' => 'nullable|string|max:255',
            'passport' => 'nullable|image|max:5120',
            'identification_image' => 'nullable|file|max:5120',
            'shared_areas' => 'nullable|array',
            'compound_locations' => 'nullable|array',
            'other_areas_detail' => 'nullable|string',
            'commercial_type' => 'nullable|string|max:255',
            'residence_type' => 'nullable|string|max:255',
            'industrial_type' => 'nullable|string|max:255',
            'unit_type_others' => 'nullable|string|max:255',
        ]);

        // Determine the actual unit type from various possible inputs
        $unitType = $request->unit_type;
        if ($request->commercial_type && $request->commercial_type !== 'Others') {
            $unitType = $request->commercial_type;
        } elseif ($request->residence_type && $request->residence_type !== 'Others') {
            $unitType = $request->residence_type;
        } elseif ($request->industrial_type && $request->industrial_type !== 'Others') {
            $unitType = $request->industrial_type;
        } elseif ($request->unit_type_others) {
            $unitType = $request->unit_type_others;
        }

        // Extract raw input for fields not in validation array yet or handled manually
        $data = $request->only([
            'applicant_type',
            'first_name',
            'middle_name',
            'surname',
            'applicant_title',
            'corporate_name',
            'rc_number',
            'email',
            'phone_number',
            'address',
            'address_house_no',
            'unit_district',
            'unit_lga',
            'unit_state',
            'block_number',
            'floor_number',
            'unit_number',
            'unit_size',
            'unit_area',
            'unit_district',
            'unit_lga',
            'unit_state',
            'ownership',
            'comments',
            'identification_type',
            'other_areas_detail'
        ]);

        $data['unit_type'] = $unitType;

        // Handle File Uploads
        if ($request->hasFile('passport')) {
            $path = $request->file('passport')->store('passports', 'public');
            $data['passport'] = $path;
        }

        if ($request->hasFile('identification_image')) {
            $path = $request->file('identification_image')->store('identifications', 'public');
            $data['identification_image'] = $path;
        }

        // Handle Shared Areas & Compound Locations
        $data['shared_areas'] = $request->has('shared_areas') ? json_encode($request->shared_areas) : null;
        $data['compound_locations'] = $request->has('compound_locations') ? json_encode($request->compound_locations) : null;

        // Handle Multiple Owners Data
        if ($request->applicant_type === 'multiple') {
            $multipleOwnersData = [];
            $names = $request->multiple_owners_names ?? [];
            $addresses = $request->multiple_owners_address ?? [];
            $emails = $request->multiple_owners_email ?? [];
            $phones = $request->multiple_owners_phone ?? [];

            foreach ($names as $index => $name) {
                $owner = [
                    'name' => $name,
                    'address' => $addresses[$index] ?? '',
                    'email' => $emails[$index] ?? '',
                    'phone' => $phones[$index] ?? '',
                ];

                // Check for existing passport and ID in data if not uploading new ones
                // (This is an edit view, so we should keep old paths if not changed)
                // Note: File inputs for multiple owners are harder to manage in a simple loop without AJAX
                // For now,we just keep it simple as originally planned.

                $multipleOwnersData[] = $owner;
            }
            $data['multiple_owners_data'] = json_encode($multipleOwnersData);
            $data['multiple_owners_names'] = json_encode($names);
            $data['multiple_owners_address'] = json_encode($addresses);
            $data['multiple_owners_email'] = json_encode($emails);
            $data['multiple_owners_phone'] = json_encode($phones);
        }

        $subApplication->update($data);

        return redirect()->back()->with('success', 'Parented Unit Application updated successfully!');
    }
}
