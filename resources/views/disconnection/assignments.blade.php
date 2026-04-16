<!DOCTYPE html>
<html lang="en">
@include('partials.header')

<body id="page-top">
    <div id="wrapper">
        <!-- Sidebar -->
        @include('partials.sidebar')
        <!-- Sidebar -->
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <!-- Topbar -->
                @include('partials.navbar')
                <!-- Topbar -->

                <!-- Container Fluid-->
                <div class="container-fluid" id="container-wrapper">
                    <!-- Page Header -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <div>
                            <h1 class="h3 mb-1 text-gray-800 font-weight-bold">Disconnection Orders Management</h1>
                            <p class="text-muted mb-0 small">Manage and assign disconnection orders to disconnectors</p>
                        </div>
                        <a href="{{ route('disconnection.index') }}" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Create New Orders
                        </a>
                    </div>

                    <!-- Success/Error Messages -->
                    @if($message = session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ $message }}
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    @endif

                    @if($message = session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            {{ $message }}
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    @endif

                    <!-- Filter Card -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Filters</h6>
                        </div>
                        <div class="card-body">
                            <form method="GET" action="{{ route('disconnection.assignments') }}" class="form-inline">
                                <div class="form-group mr-3">
                                    <label class="small font-weight-bold mr-2">Status</label>
                                    <select name="status" class="form-control form-control-sm">
                                        <option value="">All Status</option>
                                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                                        <option value="assigned" {{ request('status') == 'assigned' ? 'selected' : '' }}>Assigned</option>
                                        <option value="in-progress" {{ request('status') == 'in-progress' ? 'selected' : '' }}>In Progress</option>
                                        <option value="disconnected" {{ request('status') == 'disconnected' ? 'selected' : '' }}>Disconnected</option>
                                        <option value="reconnected" {{ request('status') == 'reconnected' ? 'selected' : '' }}>Reconnected</option>
                                    </select>
                                </div>
                                <div class="form-group mr-3">
                                    <label class="small font-weight-bold mr-2">Zone</label>
                                    <select name="zone" class="form-control form-control-sm">
                                        <option value="">All Zones</option>
                                        @foreach($zones as $zone)
                                            <option value="{{ $zone }}" {{ request('zone') == $zone ? 'selected' : '' }}>
                                                Zone {{ $zone }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group mr-3">
                                    <label class="small font-weight-bold mr-2">Disconnector</label>
                                    <select name="disconnector_id" class="form-control form-control-sm">
                                        <option value="">All Disconnectors</option>
                                        @foreach($disconnectors as $disconnector)
                                            <option value="{{ $disconnector->id }}" {{ request('disconnector_id') == $disconnector->id ? 'selected' : '' }}>
                                                {{ $disconnector->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                                <a href="{{ route('disconnection.assignments') }}" class="btn btn-secondary btn-sm ml-2">
                                    <i class="fas fa-redo"></i> Reset
                                </a>
                            </form>
                        </div>
                    </div>

                    <!-- Orders Table -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                Disconnection Orders ({{ $orders->total() }})
                            </h6>
                        </div>
                        <div class="card-body">
                            @if($orders->count() == 0)
                                <div class="text-center py-5">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No disconnection orders found</p>
                                </div>
                            @else
                                <form id="assignmentForm" method="POST" action="{{ route('disconnection.assign-orders') }}">
                                    @csrf
                                    <div class="row mb-3">
                                        <div class="col-md-8">
                                            <label class="small font-weight-bold">Assign to Disconnector</label>
                                            <select name="disconnector_id" class="form-control form-control-sm" id="disconnectorSelect">
                                                <option value="">-- Select Disconnector --</option>
                                                @foreach($disconnectors as $disconnector)
                                                    <option value="{{ $disconnector->id }}">{{ $disconnector->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-4 d-flex align-items-end">
                                            <button type="submit" class="btn btn-success btn-sm btn-block" id="assignBtn">
                                                <i class="fas fa-share"></i> Assign Selected Orders
                                            </button>
                                        </div>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover table-sm">
                                            <thead class="bg-primary text-white">
                                                <tr>
                                                    <th width="30">
                                                        <input type="checkbox" id="selectAllCheckbox">
                                                    </th>
                                                    <th>Account No.</th>
                                                    <th>Account Name</th>
                                                    <th>Zone</th>
                                                    <th>Total Outstanding</th>
                                                    <th>Disconnection Date</th>
                                                    <th>Status</th>
                                                    <th>Assigned To</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($orders as $order)
                                                    <tr>
                                                        <td>
                                                            <input type="checkbox" name="order_ids[]" value="{{ $order->id }}" class="order-checkbox">
                                                        </td>
                                                        <td>{{ $order->account_no }}</td>
                                                        <td>{{ $order->account_name }}</td>
                                                        <td>{{ $order->zone_code }}</td>
                                                        <td class="text-right font-weight-bold text-danger">
                                                            ₱{{ number_format($order->total_outstanding, 2) }}
                                                        </td>
                                                        <td>{{ $order->disconnection_date->format('M d, Y') }}</td>
                                                        <td>
                                                            @switch($order->status)
                                                                @case('pending')
                                                                    <span class="badge badge-secondary">Pending</span>
                                                                    @break
                                                                @case('assigned')
                                                                    <span class="badge badge-info">Assigned</span>
                                                                    @break
                                                                @case('in-progress')
                                                                    <span class="badge badge-warning">In Progress</span>
                                                                    @break
                                                                @case('disconnected')
                                                                    <span class="badge badge-danger">Disconnected</span>
                                                                    @break
                                                                @case('reconnected')
                                                                    <span class="badge badge-success">Reconnected</span>
                                                                    @break
                                                                @default
                                                                    <span class="badge badge-light">{{ $order->status }}</span>
                                                            @endswitch
                                                        </td>
                                                        <td>
                                                            @if($order->disconnector)
                                                                <span class="badge badge-primary">{{ $order->disconnector->name }}</span>
                                                            @else
                                                                <span class="text-muted">—</span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            <a href="{{ route('disconnection.index') }}?view={{ $order->id }}" 
                                                               class="btn btn-sm btn-info" title="View Details">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </form>
                            @endif
                        </div>
                    </div>

                    <!-- Pagination -->
                    @if($orders->count() > 0)
                        <div class="d-flex justify-content-center">
                            {{ $orders->links() }}
                        </div>
                    @endif
                </div>
                <!---Container Fluid-->
            </div>
            <!-- Footer -->
            @include('partials.footer')
            <!-- Footer -->
        </div>
    </div>

    <!-- Scroll to top -->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <script>
        // Select all checkboxes
        document.getElementById('selectAllCheckbox').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.order-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });

        // Form submission validation
        document.getElementById('assignmentForm').addEventListener('submit', function(e) {
            const selected = document.querySelectorAll('.order-checkbox:checked');
            const disconnector = document.getElementById('disconnectorSelect').value;

            if (selected.length === 0) {
                e.preventDefault();
                alert('Please select at least one order to assign.');
                return false;
            }

            if (!disconnector) {
                e.preventDefault();
                alert('Please select a disconnector.');
                return false;
            }

            if (!confirm(`Are you sure you want to assign ${selected.length} order(s) to this disconnector?`)) {
                e.preventDefault();
                return false;
            }
        });
    </script>
</body>
</html>
