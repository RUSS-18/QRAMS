<!-- ============================================================
     STANDARD CONFIRMATION MODAL (Minimal)
     Uses a CUSTOM overlay instead of Bootstrap's backdrop
     to prevent z-index stacking issues with other modals.
     ============================================================ -->

<style>
#confirmModal .modal-dialog { max-width: 400px; }
#confirmModal .modal-title  { font-size: 1.05rem; font-weight: 600; }
#confirmModal .modal-body   { padding: 1.5rem 1.5rem 0.5rem; }
#confirmModal .modal-body p { margin: 0; color: #6c757d; font-size: 0.9rem; }
#confirmModal .modal-footer { border-top: none; padding: 1rem 1.5rem 1.25rem; }

/* Confirm modal sits above everything */
#confirmModal { z-index: 1090 !important; }

/* Custom overlay (replaces Bootstrap's backdrop) */
#confirmOverlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    z-index: 1085;
}
</style>

<div id="confirmOverlay"></div>

<div class="modal fade" id="confirmModal" tabindex="-1" 
     data-bs-backdrop="false"
     data-bs-keyboard="true"
     aria-labelledby="confirmModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">

      <div class="modal-body">
        <h5 class="modal-title mb-1" id="confirmModalTitle">Are you sure?</h5>
        <p id="confirmModalMessage"></p>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light border btn-sm px-3" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary btn-sm px-3" id="confirmModalOk">Confirm</button>
      </div>

    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const modalEl = document.getElementById('confirmModal');
    const overlay = document.getElementById('confirmOverlay');
    if (!modalEl) return;

    if (typeof bootstrap === 'undefined') {
        console.error('[confirm_modal] Bootstrap JS not loaded.');
        return;
    }

    const modal   = new bootstrap.Modal(modalEl);
    const titleEl = document.getElementById('confirmModalTitle');
    const msgEl   = document.getElementById('confirmModalMessage');
    const okBtn   = document.getElementById('confirmModalOk');

    let pendingAction = null;

    // ------------------------------------------------------------
    // APPLY DATA ATTRIBUTES
    // ------------------------------------------------------------
    function configureModal(data) {
        titleEl.textContent = data.confirm || 'Are you sure?';
        msgEl.textContent   = data.confirmMessage || '';
        msgEl.style.display = data.confirmMessage ? 'block' : 'none';
        okBtn.textContent   = data.confirmButton || 'Confirm';

        const color = data.confirmColor || 'primary';
        okBtn.className = 'btn btn-' + color + ' btn-sm px-3';
    }

    // ------------------------------------------------------------
    // SHOW / HIDE CUSTOM OVERLAY ALONGSIDE THE MODAL
    // ------------------------------------------------------------
    modalEl.addEventListener('show.bs.modal', function () {
        overlay.style.display = 'block';
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
        overlay.style.display = 'none';
        pendingAction = null;
    });

    // Clicking the overlay cancels
    overlay.addEventListener('click', function () {
        modal.hide();
    });

    // ------------------------------------------------------------
    // INTERCEPT CLICKS ON <a> / <button> WITH data-confirm
    // ------------------------------------------------------------
    document.addEventListener('click', function (e) {
        const trigger = e.target.closest('[data-confirm]');
        if (!trigger || trigger.tagName === 'FORM') return;

        e.preventDefault();
        configureModal(trigger.dataset);
        pendingAction = { type: 'link', element: trigger };
        modal.show();
    });

    // ------------------------------------------------------------
    // INTERCEPT FORM SUBMITS WITH data-confirm-form
    // ------------------------------------------------------------
    document.addEventListener('submit', function (e) {
        const form = e.target.closest('form[data-confirm-form]');
        if (!form) return;

        if (form.dataset.confirmed === '1') {
            delete form.dataset.confirmed;
            return;
        }

        e.preventDefault();
        configureModal(form.dataset);
        pendingAction = { type: 'form', element: form, submitter: e.submitter };
        modal.show();
    });

    // ------------------------------------------------------------
    // ON CONFIRM — RESUME ORIGINAL ACTION
    // ------------------------------------------------------------
    okBtn.addEventListener('click', function () {
        if (!pendingAction) return;

        if (pendingAction.type === 'link') {
            const el = pendingAction.element;
            if (el.tagName === 'A' && el.href) {
                window.location.href = el.href;
            } else if (el.tagName === 'BUTTON') {
                el.click();
            }
        } else {
            const form = pendingAction.element;
            form.dataset.confirmed = '1';
            if (form.requestSubmit) {
                form.requestSubmit(pendingAction.submitter);
            } else {
                form.submit();
            }
        }

        modal.hide();
    });

});
</script>