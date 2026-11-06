<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        return view('profile', [
            'user' => $user,
            'photoUrl' => $user->profile_photo_path
                ? Storage::disk('public')->url($user->profile_photo_path)
                : null,
        ]);
    }

    public function updatePhoto(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'profile_photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
        $user = $request->user();
        $previousPath = $user->profile_photo_path;
        $photoPath = $validated['profile_photo']->store('profile-photos', 'public');

        if ($photoPath === false) {
            throw new RuntimeException('The profile photo could not be stored.');
        }

        $user->update(['profile_photo_path' => $photoPath]);

        if ($previousPath && $previousPath !== $photoPath && ! Storage::disk('public')->delete($previousPath)) {
            throw new RuntimeException('The previous profile photo could not be removed.');
        }

        return redirect()->route('profile')->with('status', 'Profile photo updated.');
    }
}
