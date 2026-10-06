{{-- Shared by create and edit. Expects $product, $categories, $statuses. --}}
<div class="row">

    <div class="col-lg-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">{{ __('products.information') }}</h6>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label for="name">{{ __('products.fields.name') }}</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $product->name) }}"
                        class="form-control @error('name') is-invalid @enderror" required maxlength="255">
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="category_id">{{ __('products.fields.category') }}</label>
                        <select name="category_id" id="category_id"
                            class="custom-select @error('category_id') is-invalid @enderror" required>
                            <option value="">{{ __('products.fields.choose_category') }}</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((int) old('category_id', $product->category_id) === $category->id)>
                                    {{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('category_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-group col-md-6">
                        <label for="origin">{{ __('products.fields.origin') }}</label>
                        <input type="text" name="origin" id="origin" value="{{ old('origin', $product->origin) }}"
                            class="form-control @error('origin') is-invalid @enderror" maxlength="150"
                            aria-describedby="originHelp">
                        <small id="originHelp" class="form-text text-muted">{{ __('products.fields.origin_help') }}</small>
                        @error('origin')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="form-group mb-0">
                    <label for="description">{{ __('products.fields.description') }}</label>
                    <textarea name="description" id="description" rows="5"
                        class="form-control @error('description') is-invalid @enderror">{{ old('description', $product->description) }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow mb-4">
            <div class="card-body">
                <div class="form-group">
                    <label for="status">{{ __('products.fields.status') }}</label>
                    <select name="status" id="status" class="custom-select @error('status') is-invalid @enderror"
                        required aria-describedby="statusHelp">
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(old('status', $product->status->value) === $status->value)>
                                {{ $status->label() }}</option>
                        @endforeach
                    </select>
                    <small id="statusHelp" class="form-text text-muted">{{ __('products.fields.status_help') }}</small>
                    @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="barcode">{{ __('products.fields.barcode') }}</label>
                    <input type="text" name="barcode" id="barcode" value="{{ old('barcode', $product->barcode) }}"
                        class="form-control @error('barcode') is-invalid @enderror" inputmode="numeric" maxlength="13"
                        aria-describedby="barcodeHelp">
                    <small id="barcodeHelp" class="form-text text-muted">{{ __('products.fields.barcode_help') }}</small>
                    @error('barcode')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group mb-0">
                    <label for="image">{{ __('products.fields.image') }}</label>
                    @if ($product->imageUrl())
                        <img src="{{ $product->imageUrl() }}" alt="" class="img-fluid rounded mb-2 d-block">
                    @endif
                    <input type="file" name="image" id="image" accept="image/jpeg,image/png,image/webp"
                        class="form-control-file @error('image') is-invalid @enderror" aria-describedby="imageHelp">
                    <small id="imageHelp" class="form-text text-muted">{{ __('account.fields.image_help') }}</small>
                    @error('image')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block">
            <i class="fas fa-save fa-sm mr-1"></i> {{ __('ui.save') }}
        </button>
        <a href="{{ area_route('products.index') }}" class="btn btn-light btn-block mb-4">{{ __('ui.cancel') }}</a>
    </div>

</div>
