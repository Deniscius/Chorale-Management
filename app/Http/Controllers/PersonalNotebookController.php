<?php

namespace App\Http\Controllers;

use App\Services\PersonalNotebook;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class PersonalNotebookController extends Controller
{
    public function __invoke(Request $request, PersonalNotebook $notebook): View
    {
        return view('personal-notebook.show', $notebook->for($request->user()->member));
    }
}
