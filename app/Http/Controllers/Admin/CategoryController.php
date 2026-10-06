<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Category::class);

        return view('admin.categories.index', [
            'categories' => Category::withCount('products')->orderBy('name')->get(),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        Gate::authorize('create', Category::class);

        Category::create($request->validated());

        return back()->with('success', __('products.categories.created'));
    }

    public function edit(Category $category): View
    {
        Gate::authorize('update', $category);

        return view('admin.categories.edit', ['category' => $category]);
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        Gate::authorize('update', $category);

        $category->update($request->validated());

        return redirect()->route('admin.categories.index')->with('success', __('products.categories.updated'));
    }

    public function destroy(Category $category): RedirectResponse
    {
        Gate::authorize('delete', $category);

        $category->delete();

        return back()->with('success', __('products.categories.deleted'));
    }
}
