<?php

namespace App\Http\Controllers;

use App\Events\EncodingChanged;
use App\Services\Conflicts\ConflictReporter;
use Illuminate\Http\Request;

class ConflictsController extends Controller {
    function getConflictsReport(Request $request, $encoding_id){
        event(new EncodingChanged($encoding_id));
        return ConflictReporter::getReport($encoding_id);
    }
}
