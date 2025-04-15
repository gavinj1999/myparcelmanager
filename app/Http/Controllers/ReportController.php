<?php

// app/Http/Controllers/ReportsController.php
namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\DatePeriod;
use App\Models\ParcelType;
use App\Models\Round;
use Inertia\Inertia;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Reports/Index', [
            'activities' => ['data' => Activity::with('parcel_type')->get()],
            'parcelTypes' => ParcelType::all(),
            'rounds' => Round::with('parcel_types')->get(),
            'datePeriods' => DatePeriod::all(),
        ]);
    }
}
