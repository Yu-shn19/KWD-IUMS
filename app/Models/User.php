<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'middle_name',
        'extension',
        'email',
        'password',
        'role',
        'profile_picture',
    ];

    /**
     * Get the URL for the user's profile picture.
     *
     * @return string|null
     */
    public function getProfilePictureUrlAttribute()
    {
        if (!$this->profile_picture) {
            return null;
        }

        // Profile pictures are stored in public/WDMS/profile-pictures
        $filePath = public_path('WDMS/profile-pictures/' . $this->profile_picture);
        if (file_exists($filePath)) {
            return asset('WDMS/profile-pictures/' . $this->profile_picture);
        }

        // Fallback to default image if file doesn't exist
        return null;
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
