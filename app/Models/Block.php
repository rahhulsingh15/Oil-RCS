<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Block extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'area_original' => 'float',
        'area_revised' => 'float',
        'area_revision_date' => 'date',
        'rsc_signing_date' => 'date',
        'relinquishment_date' => 'date',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'Active');
    }

    /** Only blocks the given user is allowed to see. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $ids = $user->visibleBlockIds();

        return $ids === null ? $query : $query->whereIn($this->qualifyColumn('id'), $ids);
    }

    public function users(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function isRelinquished(): bool
    {
        return strcasecmp($this->status, 'Relinquished') === 0;
    }

    /** URL of the block's base map image (from the client's slides), or null. */
    public function mapImageUrl(): ?string
    {
        $path = config("block_map.blocks.{$this->code}.image");

        // No file check here: on shared hosting the web folder is often public_html,
        // not public/, so public_path() would wrongly say the file is missing.
        // The page falls back to the location map if the image does not load.
        return $path ? asset($path) : null;
    }

    /** [lat, lng] of the block centre from config/block_map.php, or null. */
    public function mapPoint(): ?array
    {
        return config("block_map.blocks.{$this->code}.point");
    }

    /**
     * Block outline for the map. Real boundary from config when given, otherwise
     * an approximate square of the block's area around its centre.
     *
     * @return array{points: list<array{0: float, 1: float}>, approximate: bool}|null
     */
    public function mapBoundary(): ?array
    {
        $real = config("block_map.blocks.{$this->code}.boundary");
        if (! empty($real)) {
            return ['points' => $real, 'approximate' => false];
        }

        $point = $this->mapPoint();
        if (! $point) {
            return null;
        }

        // Square with the same area: side in km, converted to degrees
        $halfKm = sqrt($this->currentArea() ?: 1000) / 2;
        $dLat = $halfKm / 111;
        $dLng = $halfKm / (111 * cos(deg2rad($point[0])));
        [$lat, $lng] = $point;

        return ['points' => [
            [$lat + $dLat, $lng - $dLng], [$lat + $dLat, $lng + $dLng],
            [$lat - $dLat, $lng + $dLng], [$lat - $dLat, $lng - $dLng],
        ], 'approximate' => true];
    }

    /** Current block area: revised if available, otherwise original. */
    public function currentArea(): ?float
    {
        return $this->area_revised ?? $this->area_original;
    }

    public function licence(): HasOne
    {
        return $this->hasOne(Licence::class);
    }

    public function timeline(): HasOne
    {
        return $this->hasOne(BlockTimeline::class);
    }

    public function workProgramme(): HasOne
    {
        return $this->hasOne(WorkProgramme::class);
    }

    public function bankGuarantee(): HasOne
    {
        return $this->hasOne(BankGuarantee::class);
    }

    public function investment(): HasOne
    {
        return $this->hasOne(Investment::class);
    }

    public function mcMeetings(): HasMany
    {
        return $this->hasMany(McMeeting::class)->orderBy('meeting_no');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function clearances(): HasMany
    {
        return $this->hasMany(Clearance::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class)->latest('id');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class);
    }
}
