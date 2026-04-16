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
                        <h1 class="h3 mb-0 text-dark">User Management</h1>
                        <button class="btn btn-primary" data-toggle="modal" data-target="#userModal" data-bs-toggle="modal" data-bs-target="#userModal" onclick="openAddModal()">
                            <i class="fas fa-user-plus me-1"></i>Add New User
                        </button>
                    </div>

                    <!-- Users Table Card -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">All Users</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover" id="usersTable">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Created At</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($users as $user)
                                        <tr id="user-row-{{ $user->id }}">
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $user->name }}</td>
                                            <td>{{ $user->email }}</td>
                                            <td>
                                                @if($user->role == 'admin')
                                                    <span class="badge badge-danger">Admin</span>
                                                @elseif($user->role == 'reader')
                                                    <span class="badge badge-info">Reader</span>
                                                @elseif($user->role == 'disconnector')
                                                    <span class="badge badge-warning">Disconnector</span>
                                                @else
                                                    <span class="badge badge-success">Customer</span>
                                                @endif
                                            </td>
                                            <td>{{ $user->created_at->format('M d, Y') }}</td>
                                            <td>
                                                <button class="btn btn-sm btn-warning" onclick='editUser({{ $user->id }}, "{{ addslashes($user->first_name) }}", "{{ addslashes($user->last_name) }}", "{{ addslashes($user->middle_name ?? '') }}", "{{ addslashes($user->extension ?? '') }}", "{{ $user->email }}", "{{ $user->role }}")'>
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="deleteUser({{ $user->id }}, '{{ addslashes($user->name) }}')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">
                                                <i class="fas fa-users fa-3x mb-3"></i>
                                                <p>No users found. Add your first user!</p>
                                            </td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
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

    <!-- User Modal -->
    <div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="userModalLabel">
                        <i class="fas fa-user me-2"></i><span id="modalTitle">Add New User</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="userForm">
                    @csrf
                    <input type="hidden" id="userId" name="user_id">
                    <input type="hidden" id="formMethod" value="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="first_name" name="first_name" placeholder="Enter first name" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="mb-3">
                            <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="last_name" name="last_name" placeholder="Enter last name" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="middle_name" class="form-label">Middle Name</label>
                                    <input type="text" class="form-control" id="middle_name" name="middle_name" placeholder="Enter middle name">
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="extension" class="form-label">Extension</label>
                                    <select class="form-select" id="extension" name="extension">
                                        <option value="">None</option>
                                        <option value="Jr.">Jr.</option>
                                        <option value="Sr.">Sr.</option>
                                        <option value="II">II</option>
                                        <option value="III">III</option>
                                        <option value="IV">IV</option>
                                    </select>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="Enter email" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password <span class="text-danger" id="passwordRequired">*</span></label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Enter password">
                            <small class="text-muted" id="passwordHint">Minimum 8 characters</small>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="mb-3">
                            <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="">Select Role</option>
                                <option value="admin">Admin</option>
                                <option value="reader">Reader</option>
                                <option value="customer">Customer</option>
                                <option value="disconnector">Disconnector</option>
                            </select>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-save me-1"></i>Save User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        let isEditMode = false;

        // Open Add User Modal
        function openAddModal() {
            isEditMode = false;
            $('#modalTitle').text('Add New User');
            $('#userId').val('');
            $('#formMethod').val('POST');
            $('#userForm')[0].reset();
            $('#password').prop('required', true);
            $('#passwordRequired').show();
            $('#passwordHint').text('Minimum 8 characters');
            $('.is-invalid').removeClass('is-invalid');
            $('.invalid-feedback').text('');
        }

        // Open Edit User Modal
        function editUser(id, firstName, lastName, middleName, extension, email, role) {
            isEditMode = true;
            $('#modalTitle').text('Edit User');
            $('#userId').val(id);
            $('#formMethod').val('PUT');
            $('#first_name').val(firstName);
            $('#last_name').val(lastName);
            $('#middle_name').val(middleName);
            $('#extension').val(extension);
            $('#email').val(email);
            $('#role').val(role);
            $('#password').val('').prop('required', false);
            $('#passwordRequired').hide();
            $('#passwordHint').text('Leave blank to keep current password');
            $('.is-invalid').removeClass('is-invalid');
            $('.invalid-feedback').text('');
            
            $('#userModal').modal('show');
        }

        // Handle Form Submission
        $('#userForm').on('submit', function(e) {
            e.preventDefault();
            
            $('.is-invalid').removeClass('is-invalid');
            $('.invalid-feedback').text('');
            
            $('#submitBtn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Saving...');
            
            const userId = $('#userId').val();
            const method = $('#formMethod').val();
            const url = isEditMode ? `/user-management/${userId}` : '{{ route("user.store") }}';
            
            let formData = {
                _token: '{{ csrf_token() }}',
                first_name: $('#first_name').val(),
                last_name: $('#last_name').val(),
                middle_name: $('#middle_name').val(),
                extension: $('#extension').val(),
                email: $('#email').val(),
                role: $('#role').val()
            };
            
            // Add password if provided
            if ($('#password').val()) {
                formData.password = $('#password').val();
            }
            
            // Add method override for PUT
            if (method === 'PUT') {
                formData._method = 'PUT';
            }
            
            $.ajax({
                url: url,
                method: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        $('#userModal').modal('hide');
                        
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: response.message,
                            confirmButtonColor: '#28a745'
                        }).then(() => {
                            location.reload();
                        });
                    }
                },
                error: function(xhr) {
                    if (xhr.status === 422) {
                        const errors = xhr.responseJSON.errors;
                        
                        $.each(errors, function(field, messages) {
                            const input = $('[name="' + field + '"]');
                            input.addClass('is-invalid');
                            input.siblings('.invalid-feedback').text(messages[0]);
                        });
                        
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            text: 'Please check the form fields and try again.',
                            confirmButtonColor: '#dc3545'
                        });
                    } else {
                        const message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to save user';
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: message,
                            confirmButtonColor: '#dc3545'
                        });
                    }
                },
                complete: function() {
                    $('#submitBtn').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Save User');
                }
            });
        });

        // Delete User
        function deleteUser(id, name) {
            Swal.fire({
                title: 'Are you sure?',
                text: `Do you want to delete user "${name}"? This action cannot be undone!`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: `/user-management/${id}`,
                        method: 'DELETE',
                        data: {
                            _token: '{{ csrf_token() }}'
                        },
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Deleted!',
                                    text: response.message,
                                    confirmButtonColor: '#28a745'
                                }).then(() => {
                                    $('#user-row-' + id).fadeOut(300, function() {
                                        $(this).remove();
                                        
                                        // Check if table is empty
                                        if ($('#usersTable tbody tr').length === 0) {
                                            location.reload();
                                        }
                                    });
                                });
                            }
                        },
                        error: function(xhr) {
                            const message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to delete user';
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: message,
                                confirmButtonColor: '#dc3545'
                            });
                        }
                    });
                }
            });
        }

        // Reset modal when closed
        $('#userModal').on('hidden.bs.modal hidden', function() {
            $('#userForm')[0].reset();
            $('.is-invalid').removeClass('is-invalid');
            $('.invalid-feedback').text('');
        });
    </script>
</body>
</html>
