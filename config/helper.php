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

    <!-- Global Prompt Modal -->
    <dialog id="vsd_global_prompt" class="modal modal-bottom sm:modal-middle">
      <div class="modal-box border border-base-300 shadow-2xl">
        <h3 id="vsd_prompt_title" class="font-bold text-lg flex items-center gap-2 text-primary">
            <i class="fa-solid fa-pen-to-square"></i>
            Nhập thông tin
        </h3>
        <p id="vsd_prompt_message" class="py-4 text-sm opacity-70"></p>
        <div id="vsd_prompt_inputs" class="space-y-4 py-2">
            <!-- Dynamic inputs will be injected here -->
        </div>
        <div class="modal-action gap-2 mt-8">
          <button type="button" class="btn btn-ghost" onclick="this.closest(\'dialog\').close()">Hủy bỏ</button>
          <button id="vsd_prompt_btn" class="btn btn-primary px-8">Xác nhận</button>
        </div>
      </div>
      <form method="dialog" class="modal-backdrop">
        <button>close</button>
      </form>
    </dialog>

    <!-- Global Alert (Toast) -->
    <div id="vsd_toast_container" class="toast toast-top toast-end z-[9999]"></div>

    <script>
    window.showAlert = function(message, type = "info") {
        const container = document.getElementById("vsd_toast_container");
        if (!container) return;

        const alert = document.createElement("div");
        const alertClass = type === "success" ? "alert-success" : (type === "error" ? "alert-error" : "alert-info");
        const icon = type === "success" ? "fa-circle-check" : (type === "error" ? "fa-circle-exclamation" : "fa-circle-info");
        
        alert.className = `alert ${alertClass} shadow-lg animate-in slide-in-from-right duration-300 mb-2`;
        alert.innerHTML = `
            <div class="flex items-center gap-2">
                <i class="fa-solid ${icon}"></i>
                <span class="text-sm font-bold">${message}</span>
            </div>
        `;
        container.appendChild(alert);
        setTimeout(() => {
            alert.classList.add("animate-out", "fade-out", "slide-out-to-right");
            setTimeout(() => alert.remove(), 300);
        }, 4000);
    };

    window.vsdConfirm = function(options) {
        const modal = document.getElementById("vsd_global_confirm");
        const titleEl = document.getElementById("vsd_confirm_title");
        const msgEl = document.getElementById("vsd_confirm_message");
        const btnEl = document.getElementById("vsd_confirm_btn");

        titleEl.innerHTML = `<i class="fa-solid ${options.icon || "fa-circle-question"}"></i> ${options.title || "Xác nhận"}`;
        msgEl.innerText = options.message || "Bạn có chắc chắn?";
        btnEl.innerText = options.confirmText || "Xác nhận";
        btnEl.className = "btn " + (options.type ? "btn-" + options.type : "btn-primary");

        const newBtn = btnEl.cloneNode(true);
        btnEl.parentNode.replaceChild(newBtn, btnEl);

        newBtn.addEventListener("click", function() {
            if (typeof options.onConfirm === "function") options.onConfirm();
            modal.close();
        });
        modal.showModal();
    };

    window.vsdPrompt = function(options) {
        const modal = document.getElementById("vsd_global_prompt");
        const titleEl = document.getElementById("vsd_prompt_title");
        const msgEl = document.getElementById("vsd_prompt_message");
        const inputsEl = document.getElementById("vsd_prompt_inputs");
        const btnEl = document.getElementById("vsd_prompt_btn");

        titleEl.innerHTML = `<i class="fa-solid ${options.icon || "fa-pen-to-square"}"></i> ${options.title || "Nhập thông tin"}`;
        msgEl.innerText = options.message || "";
        btnEl.innerText = options.confirmText || "Xác nhận";
        
        inputsEl.innerHTML = "";
        
        options.inputs.forEach(input => {
            const container = document.createElement("div");
            container.className = "form-control w-full";
            
            let inputHtml = `<label class="label"><span class="label-text font-bold text-xs uppercase opacity-60">${input.label}</span></label>`;
            
            if (input.type === "select") {
                inputHtml += `<select name="${input.name}" class="select select-bordered w-full bg-base-200">
                    ${input.options.map(opt => `<option value="${opt.value}">${opt.label}</option>`).join("")}
                </select>`;
            } else if (input.type === "textarea") {
                inputHtml += `<textarea name="${input.name}" class="textarea textarea-bordered h-24 bg-base-200" placeholder="${input.placeholder || ""}"></textarea>`;
            } else {
                inputHtml += `<input type="${input.type || "text"}" name="${input.name}" class="input input-bordered w-full bg-base-200" placeholder="${input.placeholder || ""}" />`;
            }
            
            container.innerHTML = inputHtml;
            inputsEl.appendChild(container);
        });

        const newBtn = btnEl.cloneNode(true);
        btnEl.parentNode.replaceChild(newBtn, btnEl);

        newBtn.addEventListener("click", function() {
            const data = {};
            options.inputs.forEach(input => {
                const el = inputsEl.querySelector(`[name="${input.name}"]`);
                if (el) data[input.name] = el.value;
            });

            if (typeof options.onConfirm === "function") {
                options.onConfirm(data);
            }
            modal.close();
        });

        modal.showModal();
    };
    </script>
    ';
}
