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
                        <h1 class="h3 mb-0 text-gray-800">Consumer Edit PIN</h1>
                        <a href="{{ route('consumer') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-arrow-left mr-1"></i>Back to Consumers
                        </a>
                    </div>

                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    @endif

                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card shadow-sm">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0"><i class="fas fa-lock mr-2"></i>Change PIN</h6>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted small mb-3">
                                        This PIN is required to edit consumer details, delete a consumer, or save the Previous Reading on the Consumer Details page.
                                    </p>
                                    <form method="POST" action="{{ route('settings.consumer-edit-pin.update') }}">
                                        @csrf
                                        <div class="form-group">
                                            <label for="current_pin" class="font-weight-bold">Current PIN <span class="text-danger">*</span></label>
                                            <input type="password" class="form-control @error('current_pin') is-invalid @enderror" id="current_pin" name="current_pin" required autocomplete="off" maxlength="20">
                                            @error('current_pin')
                                                <span class="invalid-feedback">{{ $message }}</span>
                                            @enderror
                                        </div>
                                        <div class="form-group">
                                            <label for="new_pin" class="font-weight-bold">New PIN <span class="text-danger">*</span></label>
                                            <input type="password" class="form-control @error('new_pin') is-invalid @enderror" id="new_pin" name="new_pin" required minlength="4" autocomplete="off" maxlength="20">
                                            <small class="form-text text-muted">At least 4 characters.</small>
                                            @error('new_pin')
                                                <span class="invalid-feedback">{{ $message }}</span>
                                            @enderror
                                        </div>
                                        <div class="form-group">
                                            <label for="new_pin_confirmation" class="font-weight-bold">Confirm New PIN <span class="text-danger">*</span></label>
                                            <input type="password" class="form-control" id="new_pin_confirmation" name="new_pin_confirmation" required minlength="4" autocomplete="off" maxlength="20">
                                        </div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save mr-1"></i>Update PIN
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('partials.footer')
</body>
</html>
