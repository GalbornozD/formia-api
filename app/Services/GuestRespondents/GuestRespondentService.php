<?php

namespace App\Services\GuestRespondents;

use App\Models\Company;
use App\Models\GuestRespondent;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GuestRespondentService
{
    public function normalizeEmail(?string $email): ?string
    {
        $email = trim((string) $email);

        return $email === '' ? null : mb_strtolower($email);
    }

    public function normalizePhone(?string $phone): ?string
    {
        $phone = trim((string) $phone);

        if ($phone === '') {
            return null;
        }

        $hasPlus = str_starts_with($phone, '+');
        $digits = preg_replace('/\D/', '', $phone);

        if ($digits === '' || $digits === null) {
            return null;
        }

        return ($hasPlus ? '+' : '').$digits;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function resolveOrCreate(Company $company, array $attributes, ?User $actor = null): GuestRespondent
    {
        $email = $this->normalizeEmail($attributes['email'] ?? null);
        $phone = $this->normalizePhone($attributes['phone'] ?? null);
        $whatsapp = $this->normalizePhone($attributes['whatsapp_phone'] ?? null);
        $externalReference = isset($attributes['external_reference']) ? trim((string) $attributes['external_reference']) : null;
        $externalReference = $externalReference === '' ? null : $externalReference;

        if ($email === null && $phone === null && $whatsapp === null && $externalReference === null) {
            throw ValidationException::withMessages([
                'email' => 'El invitado debe tener al menos un dato de identificacion (email, telefono, whatsapp o referencia externa).',
            ]);
        }

        $existing = GuestRespondent::query()
            ->where('company_id', $company->id)
            ->where(function ($query) use ($email, $phone, $whatsapp, $externalReference): void {
                if ($email !== null) {
                    $query->orWhere('email', $email);
                }
                if ($phone !== null) {
                    $query->orWhere('phone', $phone);
                }
                if ($whatsapp !== null) {
                    $query->orWhere('whatsapp_phone', $whatsapp);
                }
                if ($externalReference !== null) {
                    $query->orWhere('external_reference', $externalReference);
                }
            })
            ->first();

        $data = [
            'company_id' => $company->id,
            'name' => isset($attributes['name']) ? trim((string) $attributes['name']) : $existing?->name,
            'email' => $email ?? $existing?->email,
            'phone' => $phone ?? $existing?->phone,
            'whatsapp_phone' => $whatsapp ?? $existing?->whatsapp_phone,
            'external_reference' => $externalReference ?? $existing?->external_reference,
            'identity_hash' => $email !== null ? hash('sha256', $company->id.'|'.$email) : $existing?->identity_hash,
            'metadata' => $attributes['metadata'] ?? $existing?->metadata,
            'status' => true,
        ];

        if ($existing !== null) {
            $existing->forceFill($data)->save();

            return $existing;
        }

        return GuestRespondent::query()->create([
            ...$data,
            'created_by' => $actor?->id,
            'updated_by' => $actor?->id,
        ]);
    }

    /**
     * A diferencia de resolveOrCreate() (pensado para el flujo público, donde
     * la identidad puede ser ambigua), esta actualiza siempre EL MISMO
     * registro — nunca "fusiona" hacia otro invitado que ya tenga ese email.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(GuestRespondent $guestRespondent, array $attributes, User $actor): GuestRespondent
    {
        $email = array_key_exists('email', $attributes)
            ? $this->normalizeEmail($attributes['email'])
            : $guestRespondent->email;
        $phone = array_key_exists('phone', $attributes)
            ? $this->normalizePhone($attributes['phone'])
            : $guestRespondent->phone;
        $whatsapp = array_key_exists('whatsapp_phone', $attributes)
            ? $this->normalizePhone($attributes['whatsapp_phone'])
            : $guestRespondent->whatsapp_phone;
        $externalReference = array_key_exists('external_reference', $attributes)
            ? (trim((string) $attributes['external_reference']) ?: null)
            : $guestRespondent->external_reference;

        if ($email === null && $phone === null && $whatsapp === null && $externalReference === null) {
            throw ValidationException::withMessages([
                'email' => 'El invitado debe tener al menos un dato de identificacion (email, telefono, whatsapp o referencia externa).',
            ]);
        }

        $guestRespondent->forceFill([
            'name' => array_key_exists('name', $attributes) ? trim((string) $attributes['name']) : $guestRespondent->name,
            'email' => $email,
            'phone' => $phone,
            'whatsapp_phone' => $whatsapp,
            'external_reference' => $externalReference,
            'identity_hash' => $email !== null ? hash('sha256', $guestRespondent->company_id.'|'.$email) : $guestRespondent->identity_hash,
            'metadata' => $attributes['metadata'] ?? $guestRespondent->metadata,
            'status' => $attributes['status'] ?? $guestRespondent->status,
            'updated_by' => $actor->id,
        ])->save();

        return $guestRespondent;
    }

    /**
     * @param  array{q?: string, per_page?: int, page?: int}  $filters
     */
    public function search(Company $company, array $filters): LengthAwarePaginator
    {
        $query = GuestRespondent::query()->where('company_id', $company->id);

        $term = trim((string) ($filters['q'] ?? ''));
        if ($term !== '') {
            $query->where(function ($inner) use ($term): void {
                $inner->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('external_reference', 'like', "%{$term}%");
            });
        }

        return $query->orderBy('name')
            ->paginate(
                perPage: max(1, min(100, (int) ($filters['per_page'] ?? 20))),
                page: max(1, (int) ($filters['page'] ?? 1)),
            );
    }

    public function delete(GuestRespondent $guestRespondent): void
    {
        if ($guestRespondent->assignments()->exists() || $guestRespondent->responses()->exists()) {
            throw ValidationException::withMessages([
                'guest_respondent' => 'No se puede eliminar un invitado con asignaciones o respuestas registradas; desactivalo en su lugar.',
            ]);
        }

        DB::transaction(function () use ($guestRespondent): void {
            $guestRespondent->delete();
        });
    }
}
