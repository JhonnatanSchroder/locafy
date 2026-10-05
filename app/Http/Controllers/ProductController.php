<?php

namespace App\Http\Controllers;

use App\Enums\ProductType;
use App\Http\Requests\Products\StoreProductRequest;
use App\Http\Requests\Products\UpdateProductRequest;
use App\Models\Company;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    /**
     * Display a listing of the products.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Product::class);

        $company = $this->userCompany($request);
        $search = $request->string('search')->trim()->toString();
        $type = $request->string('type')->trim()->toString();
        $active = $request->string('active')->trim()->toString();

        $products = Product::query()
            ->whereBelongsTo($company)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where('name', 'like', "%{$search}%");
            })
            ->when($type !== '', function (Builder $query) use ($type): void {
                $query->where('type', $type);
            })
            ->when($active !== '', function (Builder $query) use ($active): void {
                $query->where('active', $active === 'active');
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Product $product): array => $this->productData($product));

        return Inertia::render('products/Index', [
            'products' => $products,
            'filters' => [
                'search' => $search,
                'type' => $type,
                'active' => $active,
            ],
            'productTypes' => $this->productTypes(),
        ]);
    }

    /**
     * Show the form for creating a new product.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', Product::class);
        $this->userCompany($request);

        return Inertia::render('products/Create', [
            'productTypes' => $this->productTypes(),
        ]);
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(StoreProductRequest $request): RedirectResponse
    {
        $product = $this->userCompany($request)
            ->products()
            ->create($this->productPayload($request->validated()));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Produto Criado com Sucesso.')]);

        return to_route('products.show', $product);
    }

    /**
     * Display the specified product.
     */
    public function show(Request $request, int $product): Response
    {
        $product = $this->findProductForUser($request, $product);

        Gate::authorize('view', $product);

        return Inertia::render('products/Show', [
            'product' => $this->productData($product),
        ]);
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(Request $request, int $product): Response
    {
        $product = $this->findProductForUser($request, $product);

        Gate::authorize('update', $product);

        return Inertia::render('products/Edit', [
            'product' => $this->productData($product),
            'productTypes' => $this->productTypes(),
        ]);
    }

    /**
     * Update the specified product in storage.
     */
    public function update(UpdateProductRequest $request, int $product): RedirectResponse
    {
        $product = $this->findProductForUser($request, $product);

        Gate::authorize('update', $product);

        $product->update($this->productPayload($request->validated()));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Produto Atualizado com Sucesso.')]);

        return to_route('products.show', $product);
    }

    private function findProductForUser(Request $request, int $product): Product
    {
        return Product::query()
            ->whereBelongsTo($this->userCompany($request))
            ->findOrFail($product);
    }

    private function userCompany(Request $request): Company
    {
        $company = $request->user()?->company;

        abort_if($company === null, 403);

        return $company;
    }

    /**
     * @param  array{name: string, type: string, default_price?: numeric-string|float|int|null, unit?: string|null, stock_total?: int|null, active: bool}  $data
     * @return array{name: string, type: string, default_price: mixed, unit: string|null, stock_total: int|null, active: bool}
     */
    private function productPayload(array $data): array
    {
        if ($data['type'] === ProductType::Individual->value) {
            $data['stock_total'] = null;
        }

        return $data;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function productTypes(): array
    {
        return collect(ProductType::cases())
            ->map(fn (ProductType $type): array => [
                'value' => $type->value,
                'label' => $this->productTypeLabel($type),
            ])
            ->all();
    }

    /**
     * @return array{id: int, name: string, type: string, type_label: string, default_price: string|null, unit: string|null, stock_total: int|null, available: int|null, active: bool, active_label: string}
     */
    private function productData(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'type' => $product->type->value,
            'type_label' => $this->productTypeLabel($product->type),
            'default_price' => $product->default_price,
            'unit' => $product->unit,
            'stock_total' => $product->stock_total,
            'available' => $product->type === ProductType::Quantity ? $product->stock_total : null,
            'active' => $product->active,
            'active_label' => $product->active ? 'Ativo' : 'Inativo',
        ];
    }

    private function productTypeLabel(ProductType $type): string
    {
        return match ($type) {
            ProductType::Quantity => 'Quantidade',
            ProductType::Individual => 'Individual',
        };
    }
}
