<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ActivityImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ActivityImageController extends Controller
{
    public function index(Request $request)
    {


        return Inertia::render('ActivityImages/Index', [
            'activityImages' => $activityImages->latest()->paginate(10),
        ]);
    }
    public function store(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            'activity_date' => 'required|date',
        ]);

        $path = $request->file('image')->store('activity_images', 'public');

        $activityImage = ActivityImage::create([
            'activity_date' => $request->activity_date,
            'image_path' => $path,
        ]);

        return response()->json([
            'message' => 'Image uploaded successfully',
            'data' => $activityImage,
        ], 201);
    }
}
