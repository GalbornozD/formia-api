<?php

namespace App\Services\DistributionLists;

use App\Enums\AuditAction;
use App\Enums\DistributionMemberType;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\DistributionList;
use App\Models\GuestRespondent;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DistributionListService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Company $company, array $data, User $actor): DistributionList
    {
        return DB::transaction(function () use ($company, $data, $actor): DistributionList {
            $list = DistributionList::query()->create([
                'company_id' => $company->id,
                'name' => (string) $data['name'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'] ?? true,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->auditLogger->registrar(AuditAction::ListaDistribucionCreada, $actor, $company->id, 'distribution_list', (string) $list->id);

            return $list;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(DistributionList $list, array $data, User $actor): DistributionList
    {
        return DB::transaction(function () use ($list, $data, $actor): DistributionList {
            $list = DistributionList::query()->lockForUpdate()->findOrFail($list->id);

            $list->forceFill([
                'name' => (string) ($data['name'] ?? $list->name),
                'description' => $data['description'] ?? $list->description,
                'status' => $data['status'] ?? $list->status,
                'updated_by' => $actor->id,
            ])->save();

            $this->auditLogger->registrar(AuditAction::ListaDistribucionActualizada, $actor, $list->company_id, 'distribution_list', (string) $list->id);

            return $list->refresh();
        });
    }

    public function delete(DistributionList $list): void
    {
        $list->delete();
    }

    /**
     * @param  list<int|string>  $ids
     */
    public function addMembers(DistributionList $list, DistributionMemberType $type, array $ids, User $actor): int
    {
        return DB::transaction(function () use ($list, $type, $ids, $actor): int {
            $validIds = $type === DistributionMemberType::User
                ? $this->validUserIds($list->company_id, $ids)
                : $this->validGuestIds($list->company_id, $ids);

            if (count($validIds) !== count(array_unique($ids))) {
                throw ValidationException::withMessages([
                    'ids' => 'Todos los miembros deben pertenecer activos a la empresa de la lista.',
                ]);
            }

            $column = $type === DistributionMemberType::User ? 'user_id' : 'guest_respondent_id';
            $now = now();
            $added = 0;

            foreach (array_chunk($validIds, 500) as $chunk) {
                $rows = array_map(fn ($id) => [
                    'distribution_list_id' => $list->id,
                    'member_type' => $type->value,
                    'user_id' => $type === DistributionMemberType::User ? $id : null,
                    'guest_respondent_id' => $type === DistributionMemberType::Guest ? $id : null,
                    'created_at' => $now,
                    'created_by' => $actor->id,
                ], $chunk);

                $added += DB::table('distribution_list_members')->insertOrIgnore($rows);
            }

            if ($added > 0) {
                $this->auditLogger->registrar(
                    AuditAction::ListaDistribucionMiembroAgregado,
                    $actor,
                    $list->company_id,
                    'distribution_list',
                    (string) $list->id,
                    ['member_type' => $type->value, 'count' => $added],
                );
            }

            return $added;
        });
    }

    /**
     * @param  list<int>  $memberIds
     */
    public function removeMembers(DistributionList $list, array $memberIds, User $actor): int
    {
        return DB::transaction(function () use ($list, $memberIds, $actor): int {
            $removed = $list->members()->whereIn('id', $memberIds)->delete();

            if ($removed > 0) {
                $this->auditLogger->registrar(
                    AuditAction::ListaDistribucionMiembroEliminado,
                    $actor,
                    $list->company_id,
                    'distribution_list',
                    (string) $list->id,
                    ['count' => $removed],
                );
            }

            return $removed;
        });
    }

    /**
     * @param  array{q?: string, member_type?: string, per_page?: int, page?: int}  $filters
     */
    public function searchMembers(DistributionList $list, array $filters): LengthAwarePaginator
    {
        $query = $list->members()->with(['user', 'guestRespondent']);

        if (! empty($filters['member_type'])) {
            $query->where('member_type', $filters['member_type']);
        }

        $term = trim((string) ($filters['q'] ?? ''));
        if ($term !== '') {
            $query->where(function ($inner) use ($term): void {
                $inner->whereHas('user', fn ($q) => $q->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%"))
                    ->orWhereHas('guestRespondent', fn ($q) => $q->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%"));
            });
        }

        return $query->orderByDesc('id')
            ->paginate(
                perPage: max(1, min(100, (int) ($filters['per_page'] ?? 20))),
                page: max(1, (int) ($filters['page'] ?? 1)),
            );
    }

    /**
     * @param  list<int|string>  $ids
     * @return list<int>
     */
    private function validUserIds(int $companyId, array $ids): array
    {
        return CompanyUser::query()
            ->where('company_id', $companyId)
            ->where('status', true)
            ->whereIn('user_id', $ids)
            ->pluck('user_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<int|string>  $ids
     * @return list<string>
     */
    private function validGuestIds(int $companyId, array $ids): array
    {
        return GuestRespondent::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->unique()
            ->values()
            ->all();
    }
}
