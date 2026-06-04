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
                            <nav class="mb-2" aria-label="breadcrumb">
                                <ol class="breadcrumb small bg-transparent p-0 mb-0">
                                    <li class="breadcrumb-item"><a href="{{ route('systemreport') }}">System Reports</a></li>
                                    <li class="breadcrumb-item active" aria-current="page">Disconnected consumer</li>
                                </ol>
                            </nav>
                            <h1 class="h3 mb-1 text-gray-800 font-weight-bold">Disconnected consumer</h1>
                            <p class="text-muted mb-0 small">Disconnected accounts by actual disconnect time recorded when service was cut off.</p>
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
                            <h6 class="m-0 font-weight-bold text-primary">Disconnection date range</h6>
                        </div>
                        <div class="card-body">
                            <form method="GET" action="{{ route('disconnection.assignments') }}" class="form-inline flex-wrap align-items-end">
                                <div class="form-group mr-3 mb-2 mb-md-0">
                                    <label class="small font-weight-bold d-block mb-1">Date from</label>
                                    <input type="date" name="disconnected_from" class="form-control form-control-sm"
                                           value="{{ $range['from_date'] }}">
                                </div>
                                <div class="form-group mr-3 mb-2 mb-md-0">
                                    <label class="small font-weight-bold d-block mb-1">Date to</label>
                                    <input type="date" name="disconnected_to" class="form-control form-control-sm"
                                           value="{{ $range['to_date'] }}">
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm mb-2 mb-md-0">
                                    <i class="fas fa-filter"></i> Apply
                                </button>
                                <a href="{{ route('disconnection.assignments') }}" class="btn btn-secondary btn-sm ml-md-2 mb-2 mb-md-0">
                                    <i class="fas fa-redo"></i> Reset to this month
                                </a>
                            </form>
                            <p class="text-muted small mb-0 mt-2">
                                Showing disconnects from <strong>{{ \Carbon\Carbon::parse($range['from_date'])->format('M d, Y') }}</strong>
                                through <strong>{{ \Carbon\Carbon::parse($range['to_date'])->format('M d, Y') }}</strong> (inclusive).
                            </p>
                        </div>
                    </div>

                    <!-- Orders Table -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-wrap justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">
                                Records ({{ $orders->total() }})
                            </h6>
                            @if($orders->total() > 0)
                                <a href="{{ route('disconnection.assignments.export') }}{{ request()->getQueryString() ? '?' . request()->getQueryString() : '' }}"
                                   class="btn btn-success btn-sm mt-2 mt-md-0">
                                    <i class="fas fa-file-excel"></i> Export Excel
                                </a>
                            @endif
                        </div>
                        <div class="card-body">
                            @if($orders->count() == 0)
                                <div class="text-center py-5">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No disconnected accounts in this date range.</p>
                                </div>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover table-sm">
                                        <thead class="bg-primary text-white">
                                            <tr>
                                                <th class="align-middle" style="min-width: 150px;">Account No.</th>
                                                <th class="align-middle" style="min-width: 280px;">Account Name</th>
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
                                                    <td style="min-width: 150px;">{{ $order->account_no }}</td>
                                                    <td style="min-width: 280px;">{{ $order->account_name }}</td>
                                                    <td>{{ $order->zone_code }}</td>
                                                    <td class="text-right font-weight-bold text-danger">
                                                        ₱{{ number_format($order->total_outstanding, 2) }}
                                                    </td>
                                                    <td>{{ $order->disconnected_at->format('M d, Y h:i A') }}</td>
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
                            @endif
                        </div>
                    </div>

                    <!-- Pagination -->
                    @if($orders->total() > 0)
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-3 px-1">
                            <p class="text-muted small mb-2 mb-md-0">
                                Showing <strong>{{ $orders->firstItem() }}</strong> to <strong>{{ $orders->lastItem() }}</strong>
                                of <strong>{{ $orders->total() }}</strong> results
                            </p>
                            @if($orders->hasPages())
                                <nav aria-label="Disconnection history pages">
                                    {{ $orders->links() }}
                                </nav>
                            @endif
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
</body>
</html>
