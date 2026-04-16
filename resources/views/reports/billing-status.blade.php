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
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h3 class="mb-0 text-primary font-weight-bold">{{ $period ?? now()->format('F-Y') }}</h3>
                        <h1 class="h3 mb-0 text-gray-800">Billing Status</h1>
                    </div>

                    <div class="row mb-3">
                        <div class="col-lg-12">
                            <form method="get" action="{{ route('billing-status') }}" class="form-inline float-right">
                                <label class="mr-2 mb-0">Bill month:</label>
                                <input type="month" name="bill_month" class="form-control form-control-sm mr-2" value="{{ request('bill_month', now()->format('Y-m')) }}" style="max-width: 160px;">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-sync-alt"></i> Refresh
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Billing Status Table -->
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card mb-4">
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-hover mb-0">
                                            <thead class="thead-light">
                                                <tr class="text-center">
                                                    <th rowspan="2" class="align-middle">ZONE</th>
                                                    <th rowspan="2" class="align-middle">Bill Date</th>
                                                    <th rowspan="2" class="align-middle">Due Date</th>
                                                    <th rowspan="2" class="align-middle">Discon Date</th>
                                                    <th colspan="1">Meter Reading Preparation</th>
                                                    <th colspan="1">Reading Download </th>
                                                    <th colspan="1">Reading Upload</th>
                                                    <th colspan="1">Reading Posting</th>
                                                    <th rowspan="2" class="align-middle">Bill Printing</th>
                                                    <th rowspan="2" class="align-middle">Surcharge Generation</th>
                                                    <th rowspan="2" class="align-middle">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse(($rows ?? []) as $row)
                                                <tr>
                                                    <td class="text-center font-weight-bold">{{ $row['zone'] }}</td>
                                                    <td class="text-center">{{ $row['bill_date'] }}</td>
                                                    <td class="text-center">{{ $row['due_date'] }}</td>
                                                    <td class="text-center">{{ $row['discon_date'] }}</td>
                                                    <td class="text-center">{{ $row['preparation'] }}</td>
                                                    <td class="text-center">{{ $row['reading_download'] }}</td>
                                                    <td class="text-center">{{ $row['reading_upload'] }}</td>
                                                    <td class="text-center">{{ $row['reading_posting'] }}</td>
                                                    <td class="text-center">{{ $row['bill_printing'] }}</td>
                                                    <td class="text-center">{{ $row['surcharge_generation'] }}</td>
                                                    <td class="text-center">{{ $row['status'] }}</td>
                                                </tr>
                                                @empty
                                                <tr>
                                                    <td colspan="11" class="text-center text-muted py-4">No schedule data for this month. Select another bill month or run Meter Reading Preparation first.</td>
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

    