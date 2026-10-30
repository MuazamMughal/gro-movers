<?php

namespace App\Models;

use App\Enums\LeadStatus;
use App\Support\Phone;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'full_name', 'email', 'phone',
    'from_address', 'from_house_number', 'from_street', 'from_neighborhood', 'from_city', 'from_county',
    'from_state', 'from_state_name', 'from_zip', 'from_country', 'from_country_code', 'from_lat', 'from_lng', 'from_osm_id',
    'to_address', 'to_house_number', 'to_street', 'to_neighborhood', 'to_city', 'to_county',
    'to_state', 'to_state_name', 'to_zip', 'to_country', 'to_country_code', 'to_lat', 'to_lng', 'to_osm_id',
    'move_date', 'move_type_id', 'move_type_name', 'status',
    'source_url', 'referrer', 'utm', 'ip', 'user_agent', 'read_at',
])]
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'move_date' => 'date',
            'status' => LeadStatus::class,
            'utm' => 'array',
            'from_lat' => 'decimal:7',
            'from_lng' => 'decimal:7',
            'to_lat' => 'decimal:7',
            'to_lng' => 'decimal:7',
            'read_at' => 'datetime',
        ];
    }

    #[Scope]
    protected function unread(Builder $query): void
    {
        $query->whereNull('read_at');
    }

    /** "(214) 555-0100" */
    public function phoneDisplay(): string
    {
        return Phone::display($this->phone);
    }

    /** Google Maps search link for the "from" or "to" address. */
    public function mapsUrl(string $side): string
    {
        $lat = $this->{"{$side}_lat"};
        $lng = $this->{"{$side}_lng"};
        $query = $lat !== null && $lng !== null ? "{$lat},{$lng}" : $this->{"{$side}_address"};

        return 'https://www.google.com/maps/search/?api=1&query='.rawurlencode((string) $query);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function moveType(): BelongsTo
    {
        return $this->belongsTo(MoveType::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(LeadNote::class)->latest();
    }
}
