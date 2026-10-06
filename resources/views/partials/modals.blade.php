<!-- Logout Modal-->
<div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="logoutModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="logoutModalLabel">{{ __('ui.logout_modal.title') }}</h5>
                <button class="close" type="button" data-dismiss="modal" aria-label="{{ __('ui.close') }}">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="modal-body">{{ __('ui.logout_modal.body') }}</div>
            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" data-dismiss="modal">{{ __('ui.cancel') }}</button>
                @if (Route::has('logout'))
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn btn-primary" type="submit">{{ __('ui.topbar.logout') }}</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Delete confirmation modal: open it with data-toggle="modal" data-target="#confirmDeleteModal" data-action="..." -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" role="dialog" aria-labelledby="confirmDeleteModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmDeleteModalLabel">{{ __('ui.delete_modal.title') }}</h5>
                <button class="close" type="button" data-dismiss="modal" aria-label="{{ __('ui.close') }}">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="modal-body">
                {{ __('ui.delete_modal.body') }}
                <div class="font-weight-bold mt-2 js-delete-label"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" data-dismiss="modal">{{ __('ui.cancel') }}</button>
                <form method="POST" action="#">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger" type="submit">{{ __('ui.delete') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
