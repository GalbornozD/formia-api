<?php

namespace App\Http\Controllers\DistributionLists;

use App\Enums\DistributionMemberType;
use App\Http\Controllers\Controller;
use App\Http\Requests\DistributionLists\AddDistributionListMembersRequest;
use App\Http\Requests\DistributionLists\RemoveDistributionListMembersRequest;
use App\Http\Resources\DistributionLists\DistributionListMemberResource;
use App\Models\Company;
use App\Models\DistributionList;
use App\Models\DistributionListMember;
use App\Services\DistributionLists\DistributionListService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DistributionListMemberController extends Controller
{
    public function __construct(private readonly DistributionListService $service) {}

    public function index(Request $request, Company $empresa, DistributionList $distributionList): JsonResponse
    {
        abort_unless($distributionList->company_id === $empresa->id, 404);
        $this->authorize('view', $distributionList);

        $members = $this->service->searchMembers($distributionList, $request->only(['q', 'member_type', 'per_page', 'page']))
            ->through(fn (DistributionListMember $member) => (new DistributionListMemberResource($member))->resolve());

        return ApiResponse::success($members);
    }

    public function store(AddDistributionListMembersRequest $request, Company $empresa, DistributionList $distributionList): JsonResponse
    {
        abort_unless($distributionList->company_id === $empresa->id, 404);
        $this->authorize('update', $distributionList);

        $added = $this->service->addMembers(
            $distributionList,
            DistributionMemberType::from($request->validated('member_type')),
            $request->validated('ids'),
            $request->user(),
        );

        return ApiResponse::success(['added' => $added], 201);
    }

    public function destroy(RemoveDistributionListMembersRequest $request, Company $empresa, DistributionList $distributionList): JsonResponse
    {
        abort_unless($distributionList->company_id === $empresa->id, 404);
        $this->authorize('update', $distributionList);

        $removed = $this->service->removeMembers($distributionList, $request->validated('member_ids'), $request->user());

        return ApiResponse::success(['removed' => $removed]);
    }
}
