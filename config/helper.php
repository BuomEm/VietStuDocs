<?php
/**
 * Helper functions for UI components like Modals
 */

/**
 * Renders a standard DaisyUI Confirmation Modal
 * @param string $id Modal ID
 * @param string $title Modal Title
 * @param string $message Modal Message
 * @param string $confirmButtonText Text for the confirm button
 * @param string $confirmAction JS code to execute on confirm
 * @param string $type primary, success, warning, error
 */
function renderConfirmModal($id, $title, $message, $confirmButtonText = 'Đồng ý', $confirmAction = '', $type = 'primary') {
    $btnClass = "btn-$type";
    $icon = 'fa-circle-info';
    if ($type === 'error') $icon = 'fa-triangle-exclamation';
    if ($type === 'success') $icon = 'fa-circle-check';
    if ($type === 'warning') $icon = 'fa-circle-exclamation';
    
    echo "
    <dialog id=\"$id\" class=\"modal modal-bottom sm:modal-middle\">
      <div class=\"modal-box border border-base-300\">
        <h3 class=\"font-bold text-lg flex items-center gap-2\">
            <i class=\"fa-solid $icon text-$type\"></i>
            $title
        </h3>
        <p class=\"py-4 text-base-content/80\">$message</p>
        <div class=\"modal-action gap-2\">
          <button type=\"button\" class=\"btn btn-ghost\" onclick=\"this.closest('dialog').close()\">Hủy bỏ</button>
          <button onclick=\"$confirmAction\" class=\"btn $btnClass\">$confirmButtonText</button>
        </div>
      </div>
      <form method=\"dialog\" class=\"modal-backdrop\">
        <button>close</button>
      </form>
    </dialog>
    ";
}

/**
 * Renders a global Confirmation Modal at the end of the body
 * Used by showConfirm() JS function
 */
function renderGlobalModal() {
    echo '
    <!-- Global Confirm Modal -->
    <dialog id="vsd_global_confirm" class="modal modal-bottom sm:modal-middle">
      <div class="modal-box border border-base-300 shadow-2xl">
        <h3 id="vsd_confirm_title" class="font-bold text-lg flex items-center gap-2 text-primary">
            <i class="fa-solid fa-circle-question"></i>
            Xác nhận
        </h3>
        <p id="vsd_confirm_message" class="py-4 text-base-content/80">Bạn có chắc chắn muốn thực hiện hành động này?</p>
        <div class="modal-action gap-2">
          <button type="button" class="btn btn-ghost" onclick="this.closest(\'dialog\').close()">Hủy bỏ</button>
          <button id="vsd_confirm_btn" class="btn btn-primary">Xác nhận</button>
        </div>
      </div>
      <form method="dialog" class="modal-backdrop">
        <button>close</button>
      </form>
    </dialog>

    <script>
    window.vsdConfirm = function(options) {
        const modal = document.getElementById("vsd_global_confirm");
        const titleEl = document.getElementById("vsd_confirm_title");
        const msgEl = document.getElementById("vsd_confirm_message");
        const btnEl = document.getElementById("vsd_confirm_btn");

        titleEl.innerHTML = `<i class="fa-solid ${options.icon || "fa-circle-question"}"></i> ${options.title || "Xác nhận"}`;
        msgEl.innerText = options.message || "Bạn có chắc chắn?";
        btnEl.innerText = options.confirmText || "Xác nhận";
        
        // Remove old classes and add new one
        btnEl.className = "btn " + (options.type ? "btn-" + options.type : "btn-primary");

        // Clone button to remove old event listeners
        const newBtn = btnEl.cloneNode(true);
        btnEl.parentNode.replaceChild(newBtn, btnEl);

        newBtn.addEventListener("click", function() {
            if (typeof options.onConfirm === "function") {
                options.onConfirm();
            }
            modal.close();
        });

        modal.showModal();
    };
    </script>
    ';
}
