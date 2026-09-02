<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index()
    {
        $users = User::latest()->get();
        return view('user-management', compact('users'));
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'extension' => 'nullable|string|max:10',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => 'required|in:admin,reader,customer,disconnector',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Generate full name from parts
            $fullName = $request->last_name . ', ' . $request->first_name;
            if (!empty($request->middle_name)) {
                $fullName .= ' ' . substr($request->middle_name, 0, 1) . '.';
            }
            if (!empty($request->extension)) {
                $fullName .= ' ' . $request->extension;
            }

            $user = User::create([
                'name' => $fullName,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'middle_name' => $request->middle_name,
                'extension' => $request->extension,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role,
            ]);

            ActivityLogger::log(
                'user.created',
                Auth::user()->name . ' created user ' . $user->name,
                Auth::user(),
                [
                    'target_user_id' => $user->id,
                    'target_email' => $user->email,
                    'target_role' => $user->role,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'User created successfully!',
                'user' => $user
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, User $user)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'extension' => 'nullable|string|max:10',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8',
            'role' => 'required|in:admin,reader,customer,disconnector',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Generate full name from parts
            $fullName = $request->last_name . ', ' . $request->first_name;
            if (!empty($request->middle_name)) {
                $fullName .= ' ' . substr($request->middle_name, 0, 1) . '.';
            }
            if (!empty($request->extension)) {
                $fullName .= ' ' . $request->extension;
            }

            $data = [
                'name' => $fullName,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'middle_name' => $request->middle_name,
                'extension' => $request->extension,
                'email' => $request->email,
                'role' => $request->role,
            ];

            // Only update password if provided
            $passwordChanged = $request->filled('password');
            if ($passwordChanged) {
                $data['password'] = Hash::make($request->password);
            }

            $user->update($data);

            ActivityLogger::log(
                'user.updated',
                Auth::user()->name . ' updated user ' . $user->name,
                Auth::user(),
                [
                    'target_user_id' => $user->id,
                    'target_email' => $user->email,
                    'target_role' => $user->role,
                    'password_changed' => $passwordChanged,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully!',
                'user' => $user
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified user.
     */
    public function destroy(User $user)
    {
        try {
            $deletedName = $user->name;
            $deletedId = $user->id;
            $deletedEmail = $user->email;

            $user->delete();

            ActivityLogger::log(
                'user.deleted',
                Auth::user()->name . ' deleted user ' . $deletedName,
                Auth::user(),
                [
                    'target_user_id' => $deletedId,
                    'target_email' => $deletedEmail,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully!'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the authenticated user's profile.
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Log request data for debugging
        Log::info('Profile update request', [
            'has_file' => $request->hasFile('profile_picture'),
            'all_files' => array_keys($request->allFiles()),
            'request_keys' => array_keys($request->all())
        ]);

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'extension' => 'nullable|string|max:10',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8',
            'current_password' => 'required_with:password|string',
            'profile_picture' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Verify current password if changing password
            if ($request->filled('password')) {
                if (!Hash::check($request->current_password, $user->password)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Current password is incorrect'
                    ], 422);
                }
            }

            // Generate full name from parts
            $fullName = $request->last_name . ', ' . $request->first_name;
            if (!empty($request->middle_name)) {
                $fullName .= ' ' . substr($request->middle_name, 0, 1) . '.';
            }
            if (!empty($request->extension)) {
                $fullName .= ' ' . $request->extension;
            }

            $data = [
                'name' => $fullName,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'middle_name' => $request->middle_name,
                'extension' => $request->extension,
                'email' => $request->email,
            ];

            // Handle profile picture upload
            Log::info('Checking for profile picture file', [
                'has_file' => $request->hasFile('profile_picture'),
                'all_files' => array_keys($request->allFiles()),
                'content_type' => $request->header('Content-Type'),
                'request_method' => $request->method()
            ]);
            
            if ($request->hasFile('profile_picture')) {
                try {
                    $file = $request->file('profile_picture');
                    
                    // Log file info for debugging
                    Log::info('Profile picture upload attempt', [
                        'user_id' => $user->id,
                        'file_name' => $file->getClientOriginalName(),
                        'file_size' => $file->getSize(),
                        'file_mime' => $file->getMimeType(),
                        'is_valid' => $file->isValid(),
                        'error' => $file->getError(),
                        'error_message' => $file->getErrorMessage()
                    ]);
                    
                    if (!$file->isValid()) {
                        Log::error('Invalid file uploaded', [
                            'error_code' => $file->getError(),
                            'error_message' => $file->getErrorMessage()
                        ]);
                        throw new \Exception('Invalid file uploaded: ' . $file->getErrorMessage());
                    }
                    
                    $this->deleteProfilePictureFile($user->profile_picture);

                    $filename = $this->storeProfilePicture($file, $user->id);

                    Log::info('Profile picture stored', [
                        'filename' => $filename,
                        'storage_path' => storage_path('app/public/profile-pictures/' . $filename),
                        'public_path' => public_path('WDMS/profile-pictures/' . $filename),
                    ]);

                    $data['profile_picture'] = $filename;
                } catch (\Exception $e) {
                    Log::error('Error uploading profile picture', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to upload profile picture: ' . $e->getMessage()
                    ], 500);
                }
            } else {
                Log::info('No profile picture file in request', [
                    'has_file' => $request->hasFile('profile_picture'),
                    'all_files' => $request->allFiles(),
                    'request_all' => array_keys($request->all()),
                    'content_type' => $request->header('Content-Type')
                ]);
            }

            // Only update password if provided
            $passwordChanged = $request->filled('password');
            if ($passwordChanged) {
                $data['password'] = Hash::make($request->password);
            }

            $pictureChanged = isset($data['profile_picture']);

            $user->update($data);
            
            // Refresh user to get updated data
            $user->refresh();

            ActivityLogger::log(
                'profile.updated',
                $user->name . ' updated their profile',
                $user,
                [
                    'password_changed' => $passwordChanged,
                    'profile_picture_changed' => $pictureChanged,
                    'updated_fields' => array_keys($data),
                ]
            );

            Log::info('Profile updated successfully', [
                'user_id' => $user->id,
                'profile_picture' => $user->profile_picture,
                'updated_fields' => array_keys($data)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully!',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'profile_picture' => $user->profile_picture,
                    'profile_picture_url' => $user->profile_picture_url
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Serve an uploaded profile picture from storage (no public/ write required).
     */
    public function serveProfilePicture(string $filename)
    {
        $filename = basename($filename);
        if (!preg_match('/^user_\d+_\d+\.(jpe?g|png|gif|webp)$/i', $filename)) {
            abort(404);
        }

        $storagePath = storage_path('app/public/profile-pictures/' . $filename);
        if (is_file($storagePath)) {
            return response()->file($storagePath);
        }

        $publicPath = public_path('WDMS/profile-pictures/' . $filename);
        if (is_file($publicPath)) {
            return response()->file($publicPath);
        }

        abort(404);
    }

    /**
     * Store a profile picture in storage/app/public (writable on typical deploys).
     * Falls back to public/WDMS/profile-pictures only when that directory is writable.
     */
    protected function storeProfilePicture(\Illuminate\Http\UploadedFile $file, int $userId): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg');
        $filename = 'user_' . $userId . '_' . time() . '.' . $extension;

        try {
            $storageDir = storage_path('app/public/profile-pictures');
            if (!is_dir($storageDir)) {
                File::makeDirectory($storageDir, 0775, true);
            }

            $stored = $file->storeAs('profile-pictures', $filename, 'public');
            if ($stored) {
                return $filename;
            }
        } catch (\Throwable $e) {
            Log::warning('Could not store profile picture in storage/app/public', [
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $publicDir = public_path('WDMS/profile-pictures');
            if (!File::isDirectory($publicDir)) {
                File::makeDirectory($publicDir, 0775, true);
            }

            if (!is_writable($publicDir)) {
                throw new \RuntimeException('Profile picture directory is not writable.');
            }

            $file->move($publicDir, $filename);

            return $filename;
        } catch (\Throwable $e) {
            Log::error('Could not store profile picture in public/WDMS either', [
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException(
                'Unable to save the profile picture. Ask the server administrator to make storage/app/public writable by the web server.'
            );
        }
    }

    protected function deleteProfilePictureFile(?string $filename): void
    {
        if (!$filename) {
            return;
        }

        $filename = basename($filename);
        Storage::disk('public')->delete('profile-pictures/' . $filename);

        $publicPath = public_path('WDMS/profile-pictures/' . $filename);
        if (File::exists($publicPath)) {
            File::delete($publicPath);
        }
    }
}
