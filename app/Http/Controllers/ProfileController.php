<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * The signed-in user's own record.
 *
 * Everything identifying — name, employee number, role, department, company and
 * location grants — stays with the administrator, because those decide what the
 * user can see. What is genuinely theirs to maintain is their contact number and
 * their photo; the password lives on its own screen and is linked from here.
 */
class ProfileController extends Controller
{
    /** Where avatars live on the public disk, relative to its root. */
    private const AVATAR_DIRECTORY = 'avatars';

    public function __construct(private AuditLogger $audit)
    {
    }

    public function edit(Request $request): View
    {
        $user = $request->user()->load([
            'role', 'department.company', 'designation', 'defaultCompany', 'defaultLocation',
        ]);

        return view('profile.edit', [
            'profileUser' => $user,
            'companies'   => $user->accessibleCompanies(),
            'locations'   => $user->accessibleLocations(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $original = $user->getOriginal();

        $user->update(['phone' => $data['phone'] ?? null]);

        if ($user->wasChanged()) {
            $this->audit->updated('USER', $user->id, $original, $user->getChanges(),
                "{$user->name} updated their profile");
        }

        return back()->with('success', 'Your details have been saved.');
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'avatar.max' => 'The photo must be 2 MB or smaller.',
        ]);

        $user     = $request->user();
        $previous = $user->avatar_path;

        $path = $request->file('avatar')->store(self::AVATAR_DIRECTORY . '/' . $user->id, 'public');

        $user->update(['avatar_path' => $path]);

        // Only once the new path is committed, so a failed write never leaves the
        // user with a record pointing at a file that is already gone.
        if ($previous && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        $this->audit->log('AVATAR_CHANGE', 'USER', $user->id, "{$user->name} updated their photo");

        return back()->with('success', 'Your photo has been updated.');
    }

    public function destroyAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);

            $this->audit->log('AVATAR_CHANGE', 'USER', $user->id, "{$user->name} removed their photo");
        }

        return back()->with('success', 'Your photo has been removed.');
    }
}
