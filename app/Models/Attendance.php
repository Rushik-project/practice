<?php

namespace App\Models;

use Database\Factories\AttendanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['employee_id', 'date', 'checked_in_at', 'checked_out_at'])]
class Attendance extends Model
{
    /** @use HasFactory<AttendanceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['checked_in_at' => 'datetime', 'checked_out_at' => 'datetime'];
    }

    public function breaks(): HasMany
    {
        return $this->hasMany(AttendanceBreak::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function workedSeconds(): int
    {
        $endTimestamp = $this->checked_out_at?->getTimestamp() ?? now()->getTimestamp();
        $workedSeconds = max(0, $endTimestamp - $this->checked_in_at->getTimestamp());

        foreach ($this->breaks as $break) {
            $breakStart = max($this->checked_in_at->getTimestamp(), $break->break_started_at->getTimestamp());
            $breakEnd = min($endTimestamp, $break->break_ended_at?->getTimestamp() ?? $endTimestamp);
            $workedSeconds -= max(0, $breakEnd - $breakStart);
        }

        return max(0, $workedSeconds);
    }
}
