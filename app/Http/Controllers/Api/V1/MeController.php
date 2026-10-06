<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    /**
     * Display the authenticated API user.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if($user->company_id === null, 403);

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'company_id' => $user->company_id,
                'role' => $user->role->value,
                'must_change_password' => $user->mustChangePassword(),
            ],
            'must_change_password' => $user->mustChangePassword(),
        ]);
    }
}
