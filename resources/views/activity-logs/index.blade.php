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
                        <h1 class="h3 mb-0 text-gray-800">Activity Log</h1>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Filter Activity</h6>
                        </div>
                        <div class="card-body">
                            <form method="GET" action="{{ route('activity-logs.index') }}" class="row">
                                <div class="col-md-3 form-group">
                                    <label for="search">Search</label>
                                    <input type="text" name="search" id="search" class="form-control"
                                           value="{{ request('search') }}"
                                           placeholder="Description, user, IP...">
                                </div>
                                <div class="col-md-3 form-group">
                                    <label for="user_id">User</label>
                                    <select name="user_id" id="user_id" class="form-control">
                                        <option value="">All users</option>
                                        @foreach($users as $user)
                                            <option value="{{ $user->id }}" @selected(request('user_id') == $user->id)>
                                                {{ $user->name }} ({{ $user->email }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2 form-group">
                                    <label for="action">Action</label>
                                    <select name="action" id="action" class="form-control">
                                        <option value="">All actions</option>
                                        @foreach($actions as $action)
                                            <option value="{{ $action }}" @selected(request('action') === $action)>
                                                {{ \App\Services\ActivityLogger::labelFor($action) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-2 form-group">
                                    <label for="date_from">From</label>
                                    <input type="date" name="date_from" id="date_from" class="form-control"
                                           value="{{ request('date_from') }}">
                                </div>
                                <div class="col-md-2 form-group">
                                    <label for="date_to">To</label>
                                    <input type="date" name="date_to" id="date_to" class="form-control"
                                           value="{{ request('date_to') }}">
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fas fa-filter mr-1"></i>Apply Filters
                                    </button>
                                    <a href="{{ route('activity-logs.index') }}" class="btn btn-outline-secondary btn-sm">
                                        Clear
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">System Activity Logs</h6>
                            <span class="text-muted small">{{ $logs->total() }} record(s) · Admin actions & account events</span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th style="width: 160px;">Date / Time</th>
                                            <th>User</th>
                                            <th style="width: 140px;">Action</th>
                                            <th>Description</th>
                                            <th style="width: 130px;">IP Address</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($logs as $log)
                                            <tr>
                                                <td>{{ $log->created_at?->format('M d, Y h:i A') }}</td>
                                                <td>
                                                    @if($log->user)
                                                        {{ $log->user->name }}
                                                        <br><small class="text-muted">{{ $log->user->email }}</small>
                                                    @else
                                                        <span class="text-muted">System / Unknown</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @php
                                                        $label = strtolower($log->action_label);
                                                        $badge = match (true) {
                                                            str_contains($label, 'failed') => 'badge-danger',
                                                            str_contains($label, 'deleted') || str_contains($label, 'cancelled') => 'badge-danger',
                                                            str_contains($label, 'logged in') => 'badge-success',
                                                            str_contains($label, 'logged out') => 'badge-secondary',
                                                            str_contains($label, 'added') || str_contains($label, 'created') || str_contains($label, 'registered') || str_contains($label, 'imported') => 'badge-info',
                                                            str_contains($label, 'updated') || str_contains($label, 'changed') => 'badge-primary',
                                                            default => 'badge-warning',
                                                        };
                                                    @endphp
                                                    <span class="badge {{ $badge }}">{{ $log->action_label }}</span>
                                                </td>
                                                <td>{{ $log->description }}</td>
                                                <td>{{ $log->ip_address ?? '—' }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-4">
                                                    <i class="fas fa-list fa-3x mb-3 d-block"></i>
                                                    No activity logs found.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            @if($logs->hasPages())
                                <div class="mt-3">
                                    {{ $logs->links() }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @include('partials.footer')
        </div>
    </div>

    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>
</body>
</html>
