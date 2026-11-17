<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

final class WorkspaceController extends Controller
{
    public function show(string $module): View
    {
        abort_unless(array_key_exists($module, config('crm.modules')), 404);

        return view('crm.workspace', ['module' => config("crm.modules.{$module}")]);
    }
}
