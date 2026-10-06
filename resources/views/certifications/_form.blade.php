{{-- Shared by create and edit. Expects $certification, $types, $products, $lots. --}}
<div class="row">

    <div class="col-lg-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">{{ __('certifications.information') }}</h6>
            </div>
            <div class="card-body">
                @if ($certification->exists)
                    <div class="form-group">
                        <label for="covers">{{ __('certifications.fields.target') }}</label>
                        <input type="text" id="covers" class="form-control" readonly
                            value="{{ $certification->targetLabel() }}">
                    </div>
                @else
                    <div class="form-group">
                        <label for="target">{{ __('certifications.fields.target') }}</label>
                        <select name="target" id="target" class="custom-select @error('target') is-invalid @enderror" required>
                            <option value="">{{ __('certifications.fields.choose_target') }}</option>
                            <optgroup label="{{ __('certifications.fields.products_group') }}">
                                @foreach ($products as $product)
                                    <option value="product:{{ $product->id }}" @selected(old('target', request('target')) === 'product:'.$product->id)>{{ $product->name }}</option>
                                @endforeach
                            </optgroup>
                            <optgroup label="{{ __('certifications.fields.lots_group') }}">
                                @foreach ($lots as $lot)
                                    <option value="lot:{{ $lot->id }}" @selected(old('target', request('target')) === 'lot:'.$lot->id)>{{ $lot->lot_number }} - {{ $lot->product->name }}</option>
                                @endforeach
                            </optgroup>
                        </select>
                        @error('target')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                @endif

                <div class="form-row">
                    <div class="form-group col-md-7">
                        <label for="name">{{ __('certifications.fields.name') }}</label>
                        <input type="text" name="name" id="name" value="{{ old('name', $certification->name) }}"
                            class="form-control @error('name') is-invalid @enderror" required maxlength="255">
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-group col-md-5">
                        <label for="type">{{ __('certifications.fields.type') }}</label>
                        <select name="type" id="type" class="custom-select @error('type') is-invalid @enderror" required>
                            @foreach ($types as $type)
                                <option value="{{ $type->value }}" @selected(old('type', $certification->type?->value) === $type->value)>{{ $type->label() }}</option>
                            @endforeach
                        </select>
                        @error('type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-7">
                        <label for="issuing_organization">{{ __('certifications.fields.issuing_organization') }}</label>
                        <input type="text" name="issuing_organization" id="issuing_organization"
                            value="{{ old('issuing_organization', $certification->issuing_organization) }}"
                            class="form-control @error('issuing_organization') is-invalid @enderror" required maxlength="255">
                        @error('issuing_organization')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-group col-md-5">
                        <label for="certificate_number">{{ __('certifications.fields.certificate_number') }}</label>
                        <input type="text" name="certificate_number" id="certificate_number"
                            value="{{ old('certificate_number', $certification->certificate_number) }}"
                            class="form-control @error('certificate_number') is-invalid @enderror" maxlength="100">
                        @error('certificate_number')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6 mb-md-0">
                        <label for="issue_date">{{ __('certifications.fields.issue_date') }}</label>
                        <input type="date" name="issue_date" id="issue_date" max="{{ today()->toDateString() }}"
                            value="{{ old('issue_date', $certification->issue_date?->toDateString()) }}"
                            class="form-control @error('issue_date') is-invalid @enderror" required>
                        @error('issue_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-group col-md-6 mb-0">
                        <label for="expiration_date">{{ __('certifications.fields.expiration_date') }}</label>
                        <input type="date" name="expiration_date" id="expiration_date"
                            value="{{ old('expiration_date', $certification->expiration_date?->toDateString()) }}"
                            class="form-control @error('expiration_date') is-invalid @enderror">
                        @error('expiration_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">{{ __('certifications.proof_card') }}</h6>
            </div>
            <div class="card-body">
                <div class="form-group mb-0">
                    <label for="document">{{ __('certifications.fields.document') }}</label>
                    <input type="file" name="document" id="document" accept="application/pdf,image/jpeg,image/png,image/webp"
                        class="form-control-file @error('document') is-invalid @enderror" aria-describedby="documentHelp">
                    <small id="documentHelp" class="form-text text-muted">
                        {{ __('certifications.fields.document_help') }}
                        @if ($certification->document_path)
                            {{ __('certifications.fields.replace_document') }}
                        @endif
                    </small>
                    @error('document')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <div class="alert alert-info" role="note">
            <i class="fas fa-info-circle mr-1"></i>
            {{ $certification->exists ? __('certifications.resubmit_notice') : __('certifications.pending_notice') }}
        </div>

        <button type="submit" class="btn btn-primary btn-block">
            <i class="fas fa-save fa-sm mr-1"></i> {{ __('ui.save') }}
        </button>
        <a href="{{ area_route('certifications.index') }}" class="btn btn-light btn-block mb-4">{{ __('ui.cancel') }}</a>
    </div>

</div>
