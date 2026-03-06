<!DOCTYPE html>
<html lang="en">
@include('partials.header')

<body id="page-top">
    <div id="wrapper">
        @include('partials.sidebar')
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                @include('partials.navbar')

                <div class="container-fluid" id="container-wrapper">
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <div>
                            <h1 class="h3 mb-1 text-gray-800 font-weight-bold">Create Pricing Tier</h1>
                            <p class="text-muted mb-0 small">Add a new pricing tier for water billing</p>
                        </div>
                        <a href="{{ route('pricing-tiers.index') }}" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Back to List
                        </a>
                    </div>

                    <div class="row">
                        <div class="col-lg-10">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <form action="{{ route('pricing-tiers.store') }}" method="POST">
                                        @csrf

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="name">Tier Name <span class="text-danger">*</span></label>
                                                    <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                                           id="name" name="name" value="{{ old('name') }}" required>
                                                    @error('name')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="category_id">Category ID</label>
                                                    <input type="text" class="form-control" id="category_id" 
                                                           name="category_id" value="{{ old('category_id') }}" 
                                                           placeholder="12, 22, 32, etc.">
                                                    <small class="form-text text-muted">12=Residential, 22=Government, 32=Industrial, etc.</small>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="rate_code">Rate Code</label>
                                                    <input type="text" class="form-control" id="rate_code" 
                                                           name="rate_code" value="{{ old('rate_code') }}" 
                                                           placeholder="C, D, or leave blank">
                                                    <small class="form-text text-muted">C or D for rate codes</small>
                                                </div>
                                            </div>
                                        </div>

                                        <hr>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="min_charge">Minimum Charge (0-10 m³) <span class="text-danger">*</span></label>
                                                    <div class="input-group">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text">₱</span>
                                                        </div>
                                                        <input type="number" step="0.01" class="form-control @error('min_charge') is-invalid @enderror" 
                                                               id="min_charge" name="min_charge" value="{{ old('min_charge') }}" required>
                                                    </div>
                                                    @error('min_charge')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="meter_rental">Meter Rental</label>
                                                    <div class="input-group">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text">₱</span>
                                                        </div>
                                                        <input type="number" step="0.01" class="form-control" 
                                                               id="meter_rental" name="meter_rental" value="{{ old('meter_rental', 20.00) }}">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <hr>
                                        <h5 class="mb-3">Tiered Pricing Rates</h5>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="tier1_rate">Tier 1 Rate (11-20 m³)</label>
                                                    <div class="input-group">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text">₱</span>
                                                        </div>
                                                        <input type="number" step="0.01" class="form-control" 
                                                               id="tier1_rate" name="tier1_rate" value="{{ old('tier1_rate') }}">
                                                        <div class="input-group-append">
                                                            <span class="input-group-text">/m³</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="tier1_max">Tier 1 Max (cubic meters)</label>
                                                    <input type="number" class="form-control" id="tier1_max" 
                                                           name="tier1_max" value="{{ old('tier1_max', 20) }}">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="tier2_rate">Tier 2 Rate (21-30 m³)</label>
                                                    <div class="input-group">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text">₱</span>
                                                        </div>
                                                        <input type="number" step="0.01" class="form-control" 
                                                               id="tier2_rate" name="tier2_rate" value="{{ old('tier2_rate') }}">
                                                        <div class="input-group-append">
                                                            <span class="input-group-text">/m³</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="tier2_max">Tier 2 Max (cubic meters)</label>
                                                    <input type="number" class="form-control" id="tier2_max" 
                                                           name="tier2_max" value="{{ old('tier2_max', 30) }}">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="tier3_rate">Tier 3 Rate (31-40 m³)</label>
                                                    <div class="input-group">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text">₱</span>
                                                        </div>
                                                        <input type="number" step="0.01" class="form-control" 
                                                               id="tier3_rate" name="tier3_rate" value="{{ old('tier3_rate') }}">
                                                        <div class="input-group-append">
                                                            <span class="input-group-text">/m³</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="tier3_max">Tier 3 Max (cubic meters)</label>
                                                    <input type="number" class="form-control" id="tier3_max" 
                                                           name="tier3_max" value="{{ old('tier3_max', 40) }}">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="tier4_rate">Tier 4 Rate (41+ m³)</label>
                                                    <div class="input-group">
                                                        <div class="input-group-prepend">
                                                            <span class="input-group-text">₱</span>
                                                        </div>
                                                        <input type="number" step="0.01" class="form-control" 
                                                               id="tier4_rate" name="tier4_rate" value="{{ old('tier4_rate') }}">
                                                        <div class="input-group-append">
                                                            <span class="input-group-text">/m³</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <hr>

                                        <div class="form-group">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" id="is_active" 
                                                       name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="is_active">
                                                    Active (tier will be used in calculations)
                                                </label>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="description">Description</label>
                                            <textarea class="form-control" id="description" name="description" rows="3">{{ old('description') }}</textarea>
                                        </div>

                                        <div class="form-group mb-0">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save"></i> Create Pricing Tier
                                            </button>
                                            <a href="{{ route('pricing-tiers.index') }}" class="btn btn-secondary">
                                                Cancel
                                            </a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @include('partials.footer')
        </div>
    </div>
    @include('partials.main-content')
</body>
</html>
