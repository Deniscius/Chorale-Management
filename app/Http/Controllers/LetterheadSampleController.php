<?php

namespace App\Http\Controllers;

use App\Services\ChoraleIdentity;
use App\Services\Letterhead;

final class LetterheadSampleController
{
    public function __invoke(Letterhead $letterhead, ChoraleIdentity $identity)
    {
        return view('documents.letterhead-sample', [
            'header' => $letterhead->headerDataUri(),
            'identity' => $identity,
        ]);
    }
}
