<?php

namespace App\Models;

use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'roll', 'sid'])]
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory;

    /**
     * Punches whose device user ID (PIN) equals this student's SID.
     */
    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'pin', 'sid');
    }

    public function deviceCommands(): HasMany
    {
        return $this->hasMany(DeviceCommand::class);
    }

    public function latestDeviceCommand(): HasOne
    {
        return $this->hasOne(DeviceCommand::class)->latestOfMany();
    }

    /**
     * Name as the terminal can store it: no tabs/newlines, max 24 characters.
     */
    public function deviceName(): string
    {
        $name = trim((string) preg_replace('/[\t\r\n]+/', ' ', $this->name));

        return mb_substr($name, 0, 24);
    }
}
