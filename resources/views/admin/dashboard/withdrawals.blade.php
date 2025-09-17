@extends('admin.layouts.app')

@section('title', 'Withdrawal Management')

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Withdrawal Management</h1>
        <div class="text-muted">
            <i class="fas fa-money-bill-wave me-1"></i>
            {{ $withdrawals->where('status', 'pending')->count() }} pending requests
        </div>
    </div>

    <!-- Withdrawals Table -->
    <div class="card">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold">
                <i class="fas fa-list me-2"></i>All Withdrawal Requests
            </h6>
        </div>
        <div class="card-body">
            @if($withdrawals->count() > 0)
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Tenant</th>
                                <th>Amount</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Admin</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($withdrawals as $withdrawal)
                            <tr class="{{ $withdrawal->status === 'pending' ? 'table-warning' : '' }}">
                                <td>
                                    {{ $withdrawal->created_at->format('M d, Y') }}<br>
                                    <small class="text-muted">{{ $withdrawal->created_at->format('H:i') }}</small>
                                </td>
                                <td>
                                    <strong>{{ $withdrawal->tenant->name }}</strong><br>
                                    <small class="text-muted">{{ $withdrawal->tenant->email }}</small><br>
                                    <small class="text-info">Balance: UGX {{ number_format($withdrawal->tenant->wallet_balance) }}</small>
                                </td>
                                <td>
                                    <strong class="text-success">UGX {{ number_format($withdrawal->amount) }}</strong><br>
                                    @if($withdrawal->fee > 0)
                                        <small class="text-muted">Fee: UGX {{ number_format($withdrawal->fee) }}</small><br>
                                        <small class="text-primary">Net: UGX {{ number_format($withdrawal->net_amount) }}</small>
                                    @endif
                                </td>
                                <td>
                                    <code>{{ $withdrawal->phone_number }}</code>
                                </td>
                                <td>
                                    @switch($withdrawal->status)
                                        @case('pending')
                                            <span class="badge bg-warning">Pending</span>
                                            @break
                                        @case('completed')
                                            <span class="badge bg-success">Completed</span><br>
                                            <small class="text-muted">{{ $withdrawal->completed_at?->format('M d, H:i') }}</small>
                                            @break
                                        @case('failed')
                                            <span class="badge bg-danger">Rejected</span><br>
                                            <small class="text-muted">{{ $withdrawal->failed_at?->format('M d, H:i') }}</small>
                                            @break
                                    @endswitch
                                </td>
                                <td>
                                    @if($withdrawal->admin)
                                        <strong>{{ $withdrawal->admin->name }}</strong><br>
                                        @if($withdrawal->admin_notes)
                                            <small class="text-muted">{{ Str::limit($withdrawal->admin_notes, 30) }}</small>
                                        @endif
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($withdrawal->status === 'pending')
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-success" onclick="approveWithdrawal({{ $withdrawal->id }}, '{{ $withdrawal->tenant->name }}', '{{ number_format($withdrawal->amount) }}')">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button class="btn btn-danger" onclick="rejectWithdrawal({{ $withdrawal->id }}, '{{ $withdrawal->tenant->name }}', '{{ number_format($withdrawal->amount) }}')">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    @else
                                        <span class="text-muted">Processed</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-center mt-4">
                    {{ $withdrawals->links() }}
                </div>
            @else
                <div class="text-center py-5">
                    <i class="fas fa-money-bill-wave fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No withdrawal requests found</h5>
                    <p class="text-muted">Withdrawal requests will appear here when tenants request withdrawals.</p>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Approval Modal -->
<div class="modal fade" id="approvalModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="fas fa-check-circle me-2"></i>Approve Withdrawal
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="approvalForm" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-success">
                        <h6 class="alert-heading">Withdrawal Details</h6>
                        <p class="mb-0">
                            <strong>Tenant:</strong> <span id="approve-tenant"></span><br>
                            <strong>Amount:</strong> UGX <span id="approve-amount"></span>
                        </p>
                    </div>
                    
                    <div class="mb-3">
                        <label for="admin_notes" class="form-label">Admin Notes (Optional)</label>
                        <textarea class="form-control" id="admin_notes" name="admin_notes" rows="3" 
                                  placeholder="Add any notes about this approval..."></textarea>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Important:</strong> Make sure you have manually sent the funds to the tenant before approving this request.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check me-2"></i>Approve & Deduct from Wallet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Rejection Modal -->
<div class="modal fade" id="rejectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-times-circle me-2"></i>Reject Withdrawal
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="rejectionForm" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <h6 class="alert-heading">Withdrawal Details</h6>
                        <p class="mb-0">
                            <strong>Tenant:</strong> <span id="reject-tenant"></span><br>
                            <strong>Amount:</strong> UGX <span id="reject-amount"></span>
                        </p>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reject_notes" class="form-label">Reason for Rejection <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="reject_notes" name="admin_notes" rows="3" 
                                  placeholder="Please provide a clear reason for rejecting this withdrawal..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times me-2"></i>Reject Withdrawal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function approveWithdrawal(id, tenantName = '', amount = '') {
    const form = document.getElementById('approvalForm');
    form.action = `/admin/withdrawals/${id}/approve`;
    
    document.getElementById('approve-tenant').textContent = tenantName;
    document.getElementById('approve-amount').textContent = amount;
    
    new bootstrap.Modal(document.getElementById('approvalModal')).show();
}

function rejectWithdrawal(id, tenantName = '', amount = '') {
    const form = document.getElementById('rejectionForm');
    form.action = `/admin/withdrawals/${id}/reject`;
    
    document.getElementById('reject-tenant').textContent = tenantName;
    document.getElementById('reject-amount').textContent = amount;
    
    new bootstrap.Modal(document.getElementById('rejectionModal')).show();
}
</script>
@endpush
