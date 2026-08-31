<?php

namespace App\Http\Controllers\GuestRespondents;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuestRespondents\StoreGuestRespondentRequest;
use App\Http\Requests\GuestRespondents\UpdateGuestRespondentRequest;
use App\Http\Resources\GuestRespondents\GuestRespondentResource;
use App\Models\Company;
use App\Models\GuestRespondent;
use App\Services\GuestRespondents\GuestRespondentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuestRespondentController extends Controller
{
    public function __construct(private readonly GuestRespondentService $service) {}

    public function index(Request $request, Company $empresa): JsonResponse
    {
        $this->authorize('viewAny', [GuestRespondent::class, $empresa]);

        $guests = $this->service->search($empresa, $request->only(['q', 'per_page', 'page']))
            ->through(fn (GuestRespondent $guest) => (new GuestRespondentResource($guest))->resolve());

        return ApiResponse::success($guests);
    }

    public function store(StoreGuestRespondentRequest $request, Company $empresa): JsonResponse
    {
        $this->authorize('create', [GuestRespondent::class, $empresa]);

        $guest = $this->service->resolveOrCreate($empresa, $request->validated(), $request->user());

        return ApiResponse::success((new GuestRespondentResource($guest))->resolve(), 201);
    }

    public function show(Company $empresa, GuestRespondent $guestRespondent): JsonResponse
    {
        abort_unless($guestRespondent->company_id === $empresa->id, 404);
        $this->authorize('view', $guestRespondent);

        return ApiResponse::success((new GuestRespondentResource($guestRespondent))->resolve());
    }

    public function update(UpdateGuestRespondentRequest $request, Company $empresa, GuestRespondent $guestRespondent): JsonResponse
    {
        abort_unless($guestRespondent->company_id === $empresa->id, 404);
        $this->authorize('update', $guestRespondent);

        $guest = $this->service->update($guestRespondent, $request->validated(), $request->user());

        return ApiResponse::success((new GuestRespondentResource($guest))->resolve());
    }

    public function destroy(Company $empresa, GuestRespondent $guestRespondent): JsonResponse
    {
        abort_unless($guestRespondent->company_id === $empresa->id, 404);
        $this->authorize('update', $guestRespondent);

        $this->service->delete($guestRespondent);

        return ApiResponse::success();
    }
}
