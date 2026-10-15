<?php

namespace App\Models;

use App\Models\Lookups\BorderType;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A land plot of a unit with its four boundaries (legacy building.plots rows). */
#[Guarded(['id'])]
class UnitPlot extends Model
{
    public const SIDES = ['north', 'east', 'south', 'west'];

    protected function casts(): array
    {
        return ['area' => 'decimal:2', 'sides' => 'array'];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Legacy processDimensions: "شارع (15)" — the border title with its width (W) or the neighbouring
     * plot number (N); plain title otherwise. Legacy checked W|N first so N never showed; fixed here.
     */
    public function describeSide(string $side, bool $withUnit = false): string
    {
        $data = $this->sides[$side] ?? [];
        $border = filled($data['type'] ?? null) ? BorderType::query()->where('code', $data['type'])->orWhere('id', is_numeric($data['type']) ? $data['type'] : 0)->first() : null;
        if ($border === null) {
            return filled($data['type'] ?? null) ? __('غير معروف') : '';
        }
        $attrs = (array) ($border->getAttribute('attributes') ?? []); // not ->attributes: that is the model's own property
        $suffix = $withUnit ? ' '.__('م') : '';

        return match (true) {
            in_array('W', $attrs, true) && filled($data['width'] ?? null) => $border->title.' ('.$data['width'].')'.$suffix,
            in_array('N', $attrs, true) && filled($data['plot_number'] ?? null) => $border->title.' ('.$data['plot_number'].')',
            default => $border->title,
        };
    }
}
