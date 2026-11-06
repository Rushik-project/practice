<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['attendance_id', 'break_started_at', 'break_ended_at'])]
class AttendanceBreak extends Model
{
    protected function casts(): array
    {
        return [
            'break_started_at' => 'datetime',
            'break_ended_at' => 'datetime',
        ];
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }
}
