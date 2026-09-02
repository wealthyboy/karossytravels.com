<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Visa;
use Illuminate\Http\Request;

final class VisaController extends Controller
{
    public function index()
    {
        $visas = Visa::orderBy('country')->paginate(25);

        return view('admin.visas.index', compact('visas'));
    }

    public function create()
    {
        return view('admin.visas.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'country' => 'required|string',
            'duration_days' => 'required|integer',
            'fee_cents' => 'required|integer',
            'requirements' => 'nullable|string',
            'active' => 'boolean',
        ]);

        Visa::create($data);

        return redirect()->route('admin.visas.index');
    }

    public function edit(Visa $visa)
    {
        return view('admin.visas.edit', ['visa' => $visa]);
    }

    public function update(Request $request, Visa $visa)
    {
        $data = $request->validate([
            'country' => 'required|string',
            'duration_days' => 'required|integer',
            'fee_cents' => 'required|integer',
            'requirements' => 'nullable|string',
            'active' => 'boolean',
        ]);

        $visa->update($data);

        return redirect()->route('admin.visas.index');
    }

    public function destroy(Visa $visa)
    {
        $visa->delete();

        return back();
    }
}
