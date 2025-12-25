<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TournamentPermit extends Model
{
    use HasFactory;

    protected $table = 'tournament_permits';

    /**
     * Mass assignable attributes.
     */
    protected $fillable = [
        'tournament_id',
        'permit_type',
        'permit_number',
        'issuer',
        'issued_at',
        'expired_at',
        'document_path',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    /**
     * Attribute casting.
     */
    protected $casts = [
        'issued_at'   => 'date',
        'expired_at'  => 'date',
        'reviewed_at' => 'datetime',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    /**
     * Enum-like constants for status & type.
     */
    public const STATUS_DRAFT     = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_ACCEPTED  = 'accepted';
    public const STATUS_REJECTED  = 'rejected';

    public const TYPE_VENUE       = 'venue';
    public const TYPE_DISPORA     = 'dispora';
    public const TYPE_SCHOOL      = 'school';
    public const TYPE_FEDERATION  = 'federation';
    public const TYPE_POLICE      = 'police';
    public const TYPE_OTHER       = 'other';

    /**
     * Relations
     */

    // Permit milik 1 tournament
    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    // User yang mereview (admin)
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Scopes
     */

    public function scopeForTournament($query, $tournamentId)
    {
        return $query->where('tournament_id', $tournamentId);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', self::STATUS_SUBMITTED);
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', self::STATUS_ACCEPTED);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    /**
     * Helpers kecil kalau mau dipakai di view
     */

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }
}
