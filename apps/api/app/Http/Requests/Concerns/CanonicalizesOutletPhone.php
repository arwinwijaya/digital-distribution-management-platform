<?php

namespace App\Http\Requests\Concerns;

use App\Models\Outlet;
use Illuminate\Contracts\Validation\Validator;

/**
 * Shared canonical-phone validation for every outlet-writing request.
 *
 * The phone is stored twice: the raw `phone` and a normalized `canonical_phone`
 * (unique). This concern keeps the "must canonicalize to something and must not
 * collide with another outlet" rule in ONE place instead of copying the same
 * `withValidator` closure into every request.
 *
 * Two behaviours matter:
 * - Requests whose phone is optional (e.g. PATCH) must NOT run the check when
 *   the key is absent/blank — otherwise a phone-less update would fail with a
 *   bogus "phone taken" error. We early-return when the field is not filled.
 * - On update, the outlet being edited must be excluded from the uniqueness
 *   check, otherwise saving an unchanged phone would 422. The route parameter
 *   for the admin update route is literally `id`.
 */
trait CanonicalizesOutletPhone
{
    /**
     * Append the canonical-phone uniqueness check after the base rules run.
     *
     * @param  Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            // Optional-phone requests (update) skip the check entirely when no
            // phone was supplied. Blank values are handled by the `required`
            // rule on the request itself (so an empty string is a 422, never a
            // model-level InvalidArgumentException/500).
            if (! $this->filled('phone')) {
                return;
            }

            $canonical = Outlet::canonicalizePhone((string) $this->input('phone'));
            if ($canonical === '') {
                $validator->errors()->add('phone', 'The phone has already been taken or is invalid.');

                return;
            }

            $exceptId = $this->route('id');

            $query = Outlet::where('canonical_phone', $canonical);
            if ($exceptId !== null) {
                $query->where('id', '!=', $exceptId);
            }

            if ($query->exists()) {
                $validator->errors()->add('phone', 'The phone has already been taken or is invalid.');
            }
        });
    }
}
