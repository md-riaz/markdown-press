<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'bio', 'avatar_url', 'username',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool { return $this->role === 'admin'; }
    public function isEditor(): bool { return $this->role === 'editor'; }
    public function isAuthor(): bool { return $this->role === 'author'; }
    public function canEditAnyPost(): bool { return in_array($this->role, ['admin', 'editor']); }

    public function posts(): HasMany { return $this->hasMany(Post::class); }
    public function revisions(): HasMany { return $this->hasMany(Revision::class); }
    public function media(): HasMany { return $this->hasMany(Media::class); }
    public function apiTokens(): HasMany { return $this->hasMany(ApiToken::class); }
    public function comments(): HasMany { return $this->hasMany(Comment::class); }
}
