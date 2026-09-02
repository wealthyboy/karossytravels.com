<div class="table-actions">
    <a href="{{ $showUrl }}" class="btn table-action-btn"><i class="bi bi-eye"></i><span>View</span></a>
    @if($canManage)
        {{-- Modify (admin-level action) --}}
        @if(isset($modifyUrl) && $modifyUrl)
            <a href="{{ $modifyUrl }}" class="btn table-action-btn"><i class="bi bi-pencil-square"></i><span>Modify</span></a>
        @endif

        {{-- Cancel action --}}
        @if(isset($cancelUrl) && $cancelUrl && ($canCancel ?? false))
            <form method="POST" action="{{ $cancelUrl }}" data-confirm="Cancel this booking?" class="d-inline">@csrf<button class="btn table-action-btn table-action-warning"><i class="bi bi-x-circle"></i><span>Cancel</span></button></form>
        @endif

        {{-- Delete fallback --}}
        @if($canDelete)
            <form method="POST" action="{{ $deleteUrl }}" data-confirm="Are you sure you want to delete this item?" class="d-inline">@csrf @method('DELETE')<button class="btn table-action-btn table-action-danger"><i class="bi bi-trash3"></i><span>Delete</span></button></form>
        @endif
    @endif
</div>
