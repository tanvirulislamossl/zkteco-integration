<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['device_sn', 'pin', 'punched_at', 'original_punched_at', 'status', 'original_status', 'verify_type', 'edited_at'])]
class AttendanceLog extends Model
{
    public const STATUSES = [
        0 => 'Check In',
        1 => 'Check Out',
        2 => 'Break Out',
        3 => 'Break In',
        4 => 'OT In',
        5 => 'OT Out',
    ];

    private const VERIFY_TYPES = [
        0 => 'Password',
        1 => 'Fingerprint',
        2 => 'Card',
        15 => 'Face',
        25 => 'Palm',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'punched_at' => 'datetime',
            'original_punched_at' => 'datetime',
            'original_status' => 'integer',
            'edited_at' => 'datetime',
            'status' => 'integer',
            'verify_type' => 'integer',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'pin', 'sid');
    }

    public function isEdited(): bool
    {
        return $this->edited_at !== null;
    }

    public function originalStatusLabel(): string
    {
        return self::STATUSES[$this->original_status] ?? 'Punch';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? 'Punch';
    }

    public function verifyLabel(): string
    {
        if ($this->verify_type === null) {
            return '—';
        }

        return self::VERIFY_TYPES[$this->verify_type] ?? 'Other ('.$this->verify_type.')';
    }
}
