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
      <div class="modal-box border border-base-300 shadow-2xl rounded-[2rem]">
        <h3 id="vsd_confirm_title" class="font-black text-xl flex items-center gap-3 text-primary uppercase tracking-tighter">
            <i class="fa-solid fa-circle-question"></i>
            Xác nhận
        </h3>
        <p id="vsd_confirm_message" class="py-6 text-base-content/80 font-medium leading-relaxed">Bạn có chắc chắn muốn thực hiện hành động này?</p>
        <div class="modal-action gap-3">
          <button type="button" class="btn btn-ghost rounded-xl font-bold" onclick="this.closest(\'dialog\').close()">Hủy bỏ</button>
          <button id="vsd_confirm_btn" class="btn btn-primary rounded-xl px-8 font-black uppercase tracking-widest">Xác nhận</button>
        </div>
      </div>
      <form method="dialog" class="modal-backdrop bg-base-neutral/20 backdrop-blur-[2px]">
        <button>close</button>
      </form>
    </dialog>

    <!-- Global Prompt Modal -->
    <dialog id="vsd_global_prompt" class="modal modal-bottom sm:modal-middle">
      <div class="modal-box border border-base-300 shadow-2xl rounded-[2rem]">
        <h3 id="vsd_prompt_title" class="font-black text-xl flex items-center gap-3 text-primary uppercase tracking-tighter">
            <i class="fa-solid fa-pen-to-square"></i>
            Nhập thông tin
        </h3>
        <p id="vsd_prompt_message" class="py-2 text-sm opacity-70 font-medium"></p>
        <div id="vsd_prompt_inputs" class="space-y-4 py-2">
            <!-- Dynamic inputs will be injected here -->
        </div>
        <div class="modal-action gap-3 mt-8">
          <button type="button" class="btn btn-ghost rounded-xl font-bold" onclick="this.closest(\'dialog\').close()">Hủy bỏ</button>
          <button id="vsd_prompt_btn" class="btn btn-primary rounded-xl px-10 font-black uppercase tracking-widest">Xác nhận</button>
        </div>
      </div>
      <form method="dialog" class="modal-backdrop bg-base-neutral/20 backdrop-blur-[2px]">
        <button>close</button>
      </form>
    </dialog>

    <!-- Global Alert (Toast) Container -->
    <div id="vsd_toast_container" class="fixed top-20 right-6 z-[9999] flex flex-col gap-3 pointer-events-none"></div>

    <style>
    .vsd-toast {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        padding: 16px 24px;
        border-radius: 1.5rem;
        box-shadow: 0 20px 40px -10px rgba(0,0,0,0.1);
        display: flex;
        align-items: center;
        gap: 16px;
        min-width: 320px;
        max-width: 450px;
        transform: translateX(120%);
        transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        pointer-events: auto;
    }
    [data-theme="dark"] .vsd-toast {
        background: rgba(15, 23, 42, 0.85);
        border-color: rgba(255, 255, 255, 0.1);
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    }
    .vsd-toast.show { transform: translateX(0); }
    .vsd-toast-icon {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
    }
    .vsd-toast-success .vsd-toast-icon { background: #dcfce7; color: #16a34a; }
    .vsd-toast-error .vsd-toast-icon { background: #fee2e2; color: #dc2626; }
    .vsd-toast-info .vsd-toast-icon { background: #e0f2fe; color: #0284c7; }
    .vsd-toast-warning .vsd-toast-icon { background: #fef3c7; color: #d97706; }
    </style>

    <script>
    window.showAlert = function(message, type = "success") {
        const container = document.getElementById("vsd_toast_container");
        if (!container) return;

        const toast = document.createElement("div");
        toast.className = `vsd-toast vsd-toast-${type}`;
        
        const icons = {
            success: "fa-circle-check",
            error: "fa-circle-xmark",
            info: "fa-circle-info",
            warning: "fa-triangle-exclamation"
        };
        const icon = icons[type] || icons.info;
        
        toast.innerHTML = `
            <div class="vsd-toast-icon">
                <i class="fa-solid ${icon}"></i>
            </div>
            <div class="flex-1">
                <div class="text-[10px] font-black uppercase opacity-40 leading-none mb-1">${type === \'error\' ? \'Lỗi\' : (type === \'success\' ? \'Thành công\' : \'Thông báo\')}</div>
                <div class="text-sm font-bold text-base-content/80 leading-snug">${message}</div>
            </div>
        `;

        container.appendChild(toast);
        setTimeout(() => toast.classList.add("show"), 10);

        setTimeout(() => {
            toast.classList.remove("show");
            setTimeout(() => toast.remove(), 500);
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
        btnEl.className = "btn rounded-xl px-8 font-black uppercase tracking-widest " + (options.type ? "btn-" + options.type : "btn-primary");

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
        // ... (rest of vsdPrompt logic same as before, but with updated UI classes)
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
                inputHtml += `<select name="${input.name}" class="select select-bordered w-full bg-base-200 rounded-xl font-bold">
                    ${input.options.map(opt => `<option value="${opt.value}">${opt.label}</option>`).join("")}
                </select>`;
            } else if (input.type === "textarea") {
                inputHtml += `<textarea name="${input.name}" class="textarea textarea-bordered h-24 bg-base-200 rounded-xl font-medium" placeholder="${input.placeholder || ""}"></textarea>`;
            } else {
                inputHtml += `<input type="${input.type || "text"}" name="${input.name}" class="input input-bordered w-full bg-base-200 rounded-xl font-bold" placeholder="${input.placeholder || ""}" />`;
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
            if (typeof options.onConfirm === "function") options.onConfirm(data);
            modal.close();
        });
        modal.showModal();
    };
    </script>
    ';
}
