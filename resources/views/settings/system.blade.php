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
                            <h1 class="h3 mb-0 text-gray-800">System Settings</h1>
                            <p class="text-muted mb-0 small">Customize district name, address, logo, and login images for this installation.</p>
                        </div>
                    </div>

                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <ul class="mb-0 pl-3">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('settings.system.update') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="row">
                            <div class="col-lg-7 mb-4">
                                <div class="card shadow-sm h-100">
                                    <div class="card-header bg-primary text-white">
                                        <h6 class="mb-0"><i class="fas fa-building mr-2"></i>District Content</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label for="org_name" class="font-weight-bold">Organization Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('org_name') is-invalid @enderror" id="org_name" name="org_name"
                                                   value="{{ old('org_name', $branding['org_name']) }}" required maxlength="150">
                                            <small class="form-text text-muted">Shown on dashboard, login, reports, and printed documents.</small>
                                            @error('org_name')
                                                <span class="invalid-feedback">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="form-group">
                                            <label for="org_short_name" class="font-weight-bold">Short Name / System Code <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('org_short_name') is-invalid @enderror" id="org_short_name" name="org_short_name"
                                                   value="{{ old('org_short_name', $branding['org_short_name']) }}" required maxlength="50">
                                            <small class="form-text text-muted">Sidebar brand text and page titles (e.g. MWS-IUMS).</small>
                                            @error('org_short_name')
                                                <span class="invalid-feedback">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="form-group">
                                            <label for="org_address" class="font-weight-bold">Address <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('org_address') is-invalid @enderror" id="org_address" name="org_address"
                                                   value="{{ old('org_address', $branding['org_address']) }}" required maxlength="255">
                                            <small class="form-text text-muted">Printed under the organization name on reports and notices.</small>
                                            @error('org_address')
                                                <span class="invalid-feedback">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="form-group mb-0">
                                            <label for="org_tagline" class="font-weight-bold">Tagline / Subtitle <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control @error('org_tagline') is-invalid @enderror" id="org_tagline" name="org_tagline"
                                                   value="{{ old('org_tagline', $branding['org_tagline']) }}" required maxlength="255">
                                            <small class="form-text text-muted">Shown on the login page under the organization name.</small>
                                            @error('org_tagline')
                                                <span class="invalid-feedback">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-5 mb-4">
                                <div class="card shadow-sm h-100">
                                    <div class="card-header bg-info text-white">
                                        <h6 class="mb-0"><i class="fas fa-images mr-2"></i>Logo & Images</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label class="font-weight-bold">Current Logo</label>
                                            <div class="border rounded p-3 text-center bg-light mb-2">
                                                <img src="{{ $branding['logo_url'] }}" alt="Logo" style="max-height: 90px; max-width: 100%; object-fit: contain;">
                                            </div>
                                            <input type="file" class="form-control-file branding-upload @error('logo') is-invalid @enderror" id="logo" name="logo" accept="image/*" data-label="Logo">
                                            <small class="form-text text-muted">PNG/JPG recommended. Max 5MB. Used in sidebar, login, and print headers.</small>
                                            @error('logo')
                                                <span class="invalid-feedback d-block">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="form-group">
                                            <label class="font-weight-bold">Favicon</label>
                                            <div class="border rounded p-2 text-center bg-light mb-2">
                                                <img src="{{ $branding['favicon_url'] }}" alt="Favicon" style="max-height: 40px; max-width: 40px; object-fit: contain;">
                                            </div>
                                            <input type="file" class="form-control-file branding-upload @error('favicon') is-invalid @enderror" id="favicon" name="favicon" accept="image/*,.ico" data-label="Favicon">
                                            <small class="form-text text-muted">Optional. Max 5MB. If empty when uploading a logo, favicon follows the logo.</small>
                                            @error('favicon')
                                                <span class="invalid-feedback d-block">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="form-group mb-0">
                                            <label class="font-weight-bold">Login Hero Background</label>
                                            <div class="border rounded p-2 text-center bg-light mb-2">
                                                <img src="{{ $branding['hero_url'] }}" alt="Hero" style="max-height: 100px; max-width: 100%; object-fit: cover; border-radius: 6px;">
                                            </div>
                                            <input type="file" class="form-control-file branding-upload @error('hero_image') is-invalid @enderror" id="hero_image" name="hero_image" accept="image/*" data-label="Login Hero Background">
                                            <small class="form-text text-muted">Full-bleed background image on the login page. Max 5MB.</small>
                                            @error('hero_image')
                                                <span class="invalid-feedback d-block">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow-sm mb-4">
                            <div class="card-body d-flex flex-wrap align-items-center justify-content-between">
                                <div class="text-muted small mb-2 mb-md-0">
                                    Changes apply immediately across login, sidebar, reports, and printed documents.
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save mr-1"></i>Save System Settings
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @include('partials.footer')
    <script>
        (function () {
            var MAX_BYTES = 5 * 1024 * 1024; // 5MB

            function formatMb(bytes) {
                return (bytes / (1024 * 1024)).toFixed(2);
            }

            document.querySelectorAll('.branding-upload').forEach(function (input) {
                input.addEventListener('change', function () {
                    var file = input.files && input.files[0];
                    if (!file) {
                        return;
                    }

                    if (file.size > MAX_BYTES) {
                        var label = input.getAttribute('data-label') || 'Image';
                        alert(
                            label + ' is too large (' + formatMb(file.size) + ' MB).\n\n' +
                            'Maximum allowed size is 5 MB. Please compress or resize the image and try again.'
                        );
                        input.value = '';
                    }
                });
            });
        })();
    </script>
</body>
</html>
