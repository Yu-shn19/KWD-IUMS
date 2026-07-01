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
                            <h1 class="h3 mb-1 text-gray-800 font-weight-bold">Pricing Tiers Management</h1>
                            <p class="text-muted mb-0 small">Manage water billing pricing tiers for all categories</p>
                        </div>
                        <div>
                            <a href="{{ route('pricing-tiers.create') }}" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus"></i> Add New Tier
                            </a>
                            @if($pricingTiers->isEmpty())
                                <form action="{{ route('pricing-tiers.initialize') }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-info btn-sm" onclick="return confirm('Initialize default pricing tiers?')">
                                        <i class="fas fa-database"></i> Initialize Defaults
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>

                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="close" data-dismiss="alert">
                                <span>&times;</span>
                            </button>
                        </div>
                    @endif

                    @if(session('warning'))
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            {{ session('warning') }}
                            <button type="button" class="close" data-dismiss="alert">
                                <span>&times;</span>
                            </button>
                        </div>
                    @endif

                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover" id="pricingTiersTable">
                                            <thead class="thead-light">
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Category ID</th>
                                                    <th>Rate Code</th>
                                                    <th>Min Charge</th>
                                                    <th>Meter Rental</th>
                                                    <th>Tier 1 (11-20)</th>
                                                    <th>Tier 2 (21-30)</th>
                                                    <th>Tier 3 (31-40)</th>
                                                    <th>Tier 4 (41+)</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse($pricingTiers as $tier)
                                                    <tr>
                                                        <td><strong>{{ $tier->name }}</strong></td>
                                                        <td>{{ $tier->category_id ?? '-' }}</td>
                                                        <td>{{ $tier->rate_code ?? '-' }}</td>
                                                        <td>₱{{ number_format($tier->min_charge, 2) }}</td>
                                                        <td>₱{{ number_format($tier->meter_rental, 2) }}</td>
                                                        <td>
                                                            @if($tier->tier1_rate)
                                                                ₱{{ number_format($tier->tier1_rate, 2) }}/m³
                                                            @else
                                                                -
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @if($tier->tier2_rate)
                                                                ₱{{ number_format($tier->tier2_rate, 2) }}/m³
                                                            @else
                                                                -
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @if($tier->tier3_rate)
                                                                ₱{{ number_format($tier->tier3_rate, 2) }}/m³
                                                            @else
                                                                -
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @if($tier->tier4_rate)
                                                                ₱{{ number_format($tier->tier4_rate, 2) }}/m³
                                                            @else
                                                                -
                                                            @endif
                                                        </td>
                                                        <td>
                                                            @if($tier->is_active)
                                                                <span class="badge badge-success">Active</span>
                                                            @else
                                                                <span class="badge badge-secondary">Inactive</span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            <a href="{{ route('pricing-tiers.edit', $tier->id) }}" class="btn btn-sm btn-primary">
                                                                <i class="fas fa-edit"></i> Edit
                                                            </a>
                                                            <form action="{{ route('pricing-tiers.destroy', $tier->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this pricing tier?')">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-sm btn-danger">
                                                                    <i class="fas fa-trash"></i> Delete
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="11" class="text-center py-4">
                                                            <p class="text-muted mb-0">No pricing tiers found.</p>
                                                            <a href="{{ route('pricing-tiers.create') }}" class="btn btn-primary btn-sm mt-2">
                                                                <i class="fas fa-plus"></i> Create First Tier
                                                            </a>
                                                        </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @include('partials.footer')
        </div>
    </div>
</body>
</html>
