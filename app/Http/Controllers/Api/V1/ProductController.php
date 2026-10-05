<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Company;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        return ProductResource::collection(
            Product::query()
                ->whereBelongsTo($this->userCompany($request))
                ->orderBy('name')
                ->paginate(15)
        );
    }

    private function userCompany(Request $request): Company
    {
        $company = $request->user()?->company;

        abort_if($company === null, 403);

        return $company;
    }
}
