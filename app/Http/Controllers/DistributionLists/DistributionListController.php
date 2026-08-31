<?php

namespace App\Http\Controllers\DistributionLists;

use App\Http\Controllers\Controller;
use App\Http\Requests\DistributionLists\StoreDistributionListRequest;
use App\Http\Requests\DistributionLists\UpdateDistributionListRequest;
use App\Http\Resources\DistributionLists\DistributionListResource;
use App\Models\Company;
use App\Models\DistributionList;
use App\Services\DistributionLists\DistributionListService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD de listas de distribución de una empresa. Autorización vía
 * DistributionListPolicy: mismo criterio (master/administrador de su
 * empresa) que EmpresaUsuarioController/EmpresaTipoFormularioController.
 */
class DistributionListController extends Controller
{
    public function __construct(private readonly DistributionListService $service) {}

    public function index(Request $request, Company $empresa): JsonResponse
    {
        $this->authorize('viewAny', [DistributionList::class, $empresa]);

        $term = trim((string) $request->string('q'));

        $lists = DistributionList::query()
            ->where('company_id', $empresa->id)
            ->when($term !== '', fn ($query) => $query->where('name', 'like', "%{$term}%"))
            ->withCount('members')
            ->orderBy('name')
            ->paginate(perPage: min(100, max(1, (int) $request->input('per_page', 20))))
            ->through(fn (DistributionList $list) => (new DistributionListResource($list))->resolve());

        return ApiResponse::success($lists);
    }

    public function store(StoreDistributionListRequest $request, Company $empresa): JsonResponse
    {
        $this->authorize('create', [DistributionList::class, $empresa]);

        $list = $this->service->create($empresa, $request->validated(), $request->user());

        return ApiResponse::success((new DistributionListResource($list))->resolve(), 201);
    }

    public function show(Company $empresa, DistributionList $distributionList): JsonResponse
    {
        abort_unless($distributionList->company_id === $empresa->id, 404);
        $this->authorize('view', $distributionList);

        $distributionList->loadCount('members');

        return ApiResponse::success((new DistributionListResource($distributionList))->resolve());
    }

    public function update(UpdateDistributionListRequest $request, Company $empresa, DistributionList $distributionList): JsonResponse
    {
        abort_unless($distributionList->company_id === $empresa->id, 404);
        $this->authorize('update', $distributionList);

        $list = $this->service->update($distributionList, $request->validated(), $request->user());

        return ApiResponse::success((new DistributionListResource($list))->resolve());
    }

    public function destroy(Company $empresa, DistributionList $distributionList): JsonResponse
    {
        abort_unless($distributionList->company_id === $empresa->id, 404);
        $this->authorize('delete', $distributionList);

        $this->service->delete($distributionList);

        return ApiResponse::success();
    }
}
