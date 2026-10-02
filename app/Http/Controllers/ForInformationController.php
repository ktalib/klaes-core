<?php

namespace App\Http\Controllers;

use App\Models\ForInformation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ForInformationController extends Controller
{
    public function index()
    {
        $records = ForInformation::orderBy('created_at', 'desc')->get();

        return view('for_information.index', compact('records'));
    }

    public function create()
    {
        return view('for_information.partials.modal');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'serial_no' => 'nullable|string',
            'ref_no' => 'nullable|string',
            'file_number' => 'nullable|string',
            'title' => 'nullable|string',
            'commencement_day' => 'nullable|string',
            'signer_name' => 'nullable|string',
            'signer_rank' => 'nullable|string',
        ]);

        $data['user_id'] = Auth::id();
        $data['status'] = 'Generated';

        $record = ForInformation::create($data);

        return response()->json([
            'success' => true,
            'message' => 'For Information record generated successfully.',
            'data' => $record,
        ]);
    }

    public function show($id)
    {
        $record = ForInformation::findOrFail($id);
        $isView = true;

        return view('for_information.partials.modal', compact('record', 'isView'));
    }

    public function edit($id)
    {
        $record = ForInformation::findOrFail($id);

        return view('for_information.partials.modal', compact('record'));
    }

    public function update(Request $request, $id)
    {
        $record = ForInformation::findOrFail($id);

        $data = $request->validate([
            'serial_no' => 'nullable|string',
            'ref_no' => 'nullable|string',
            'file_number' => 'nullable|string',
            'title' => 'nullable|string',
            'commencement_day' => 'nullable|string',
            'signer_name' => 'nullable|string',
            'signer_rank' => 'nullable|string',
        ]);

        $record->update($data);

        return response()->json([
            'success' => true,
            'message' => 'For Information record updated successfully.',
            'data' => $record,
        ]);
    }

    public function destroy($id)
    {
        $record = ForInformation::findOrFail($id);
        $record->delete();

        return response()->json([
            'success' => true,
            'message' => 'For Information record deleted successfully.',
            'data' => ['id' => $id],
        ]);
    }

    public function print($id)
    {
        $record = ForInformation::findOrFail($id);
        $record->increment('print_count');

        $watermark = $record->print_count > 1 ? 'ORIGINAL' : 'ORIGINAL';

        return view('for_information.print.show', compact('record', 'watermark'));
    }
}
