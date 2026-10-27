<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkProgramme extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'committed_2d_lkm' => 'float',
        'committed_3d_sqkm' => 'float',
        'achieved_2d_lkm' => 'float',
        'achieved_3d_sqkm' => 'float',
        'additional_2d_lkm' => 'float',
        'additional_3d_sqkm' => 'float',
        'current_drilled_depth_m' => 'float',
        'target_depth_m' => 'float',
        'drilling_completion_pct' => 'float',
    ];

    /**
     * Committed work done, 0-100. Average of 2D, 3D and wells, each capped at 100%,
     * counting only the parts that have a commitment. Null when nothing was committed.
     */
    public function progressPct(): ?float
    {
        $parts = [
            [$this->committed_2d_lkm, $this->achieved_2d_lkm],
            [$this->committed_3d_sqkm, $this->achieved_3d_sqkm],
            [$this->committed_wells, $this->achieved_wells],
        ];

        $ratios = [];
        foreach ($parts as [$committed, $achieved]) {
            if ($committed > 0) {
                $ratios[] = min(($achieved ?? 0) / $committed, 1);
            }
        }

        return $ratios ? round(array_sum($ratios) / count($ratios) * 100, 1) : null;
    }

    /**
     * Drilling completion % is always worked out, never typed:
     * drilled depth ÷ target depth (capped at 100). Empty when there is no well under drilling.
     */
    protected static function booted(): void
    {
        static::saving(function (WorkProgramme $cwp) {
            $cwp->drilling_completion_pct = self::completionPct(
                $cwp->wells_under_drilling, $cwp->current_drilled_depth_m, $cwp->target_depth_m
            );
        });
    }

    public static function completionPct($wellsUnderDrilling, $drilledDepth, $targetDepth): ?float
    {
        if (! $wellsUnderDrilling || ! $drilledDepth || ! $targetDepth || $targetDepth <= 0) {
            return null;
        }

        return round(min($drilledDepth / $targetDepth * 100, 100), 2);
    }

    /** Committed => achieved columns of each part of the CWP */
    public const PARTS = [
        '2D' => ['committed_2d_lkm', 'achieved_2d_lkm'],
        '3D' => ['committed_3d_sqkm', 'achieved_3d_sqkm'],
        'Wells' => ['committed_wells', 'achieved_wells'],
    ];

    /**
     * Progress of one part (2D / 3D / Wells), 0-100, capped at 100.
     * Null when nothing was committed for that part.
     */
    public function partPct(string $part): ?float
    {
        [$committedCol, $achievedCol] = self::PARTS[$part];
        $committed = $this->{$committedCol};

        return $committed > 0 ? round(min(($this->{$achievedCol} ?? 0) / $committed, 1) * 100, 1) : null;
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }
}
