<?php

namespace App\Http\Requests;

use App\Models\Lot;
use App\Models\Transport;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * New transport from the Transport module: same rules as sending a lot,
 * but the lot is picked in the form instead of coming from the URL.
 */
class TransportStoreRequest extends SendLotRequest
{
    private ?Lot $lot = null;

    public function authorize(): bool
    {
        return $this->user()->can('create', Transport::class);
    }

    public function lot(): ?Lot
    {
        return $this->lot ??= Lot::find($this->integer('lot_id'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // A lot the user holds and can send right now.
            'lot_id' => ['required', 'integer', function (string $attribute, mixed $value, Closure $fail) {
                if (! $this->lot() || $this->user()->cannot('send', $this->lot())) {
                    $fail(__('transfers.transports.lot_unavailable'));
                }
            }],
        ] + parent::rules();
    }
}
