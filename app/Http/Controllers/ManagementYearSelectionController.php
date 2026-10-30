<?php

namespace App\Http\Controllers;

use App\Services\ManagementYearContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ManagementYearSelectionController extends Controller
{
    public function __invoke(Request $request, ManagementYearContext $years): RedirectResponse
    {
        $data = $request->validate(['management_year_id' => ['required', 'integer']]);
        $years->choose((int) $data['management_year_id']);

        return back();
    }
}
