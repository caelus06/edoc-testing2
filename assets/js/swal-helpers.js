/**
 * SweetAlert2 helper wrappers for the E-Doc system.
 */

/* ---------- Simple feedback dialogs ---------- */

function swalSuccess(title, text) {
    return Swal.fire({ icon: "success", title, text, timer: 2000, showConfirmButton: false });
}

function swalError(title, text) {
    return Swal.fire({ icon: "error", title, text });
}

function swalWarning(title, text) {
    return Swal.fire({ icon: "warning", title, text });
}

function swalInfo(title, text) {
    return Swal.fire({ icon: "info", title, text });
}

/* ---------- Confirmation dialogs ---------- */

function swalConfirm(title, text, confirmText, callback) {
    Swal.fire({
        icon: "warning",
        title: title,
        text: text,
        showCancelButton: true,
        confirmButtonText: confirmText || "Yes",
        cancelButtonText: "Cancel",
        reverseButtons: true
    }).then(function (result) {
        if (result.isConfirmed && callback) callback();
    });
}

function swalConfirmDanger(title, text, confirmText, callback) {
    Swal.fire({
        icon: "warning",
        title: title,
        text: text,
        showCancelButton: true,
        confirmButtonColor: "#d33",
        confirmButtonText: confirmText || "Yes, delete",
        cancelButtonText: "Cancel",
        reverseButtons: true
    }).then(function (result) {
        if (result.isConfirmed && callback) callback();
    });
}

function swalConfirmSubmit(title, text, formElement) {
    Swal.fire({
        icon: "warning",
        title: title,
        text: text,
        showCancelButton: true,
        confirmButtonText: "Yes",
        cancelButtonText: "Cancel",
        reverseButtons: true
    }).then(function (result) {
        if (result.isConfirmed) formElement.submit();
    });
}

/* ---------- Toast ---------- */

function swalToast(message, icon) {
    var Toast = Swal.mixin({
        toast: true,
        position: "top-end",
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: function (toast) {
            toast.onmouseenter = Swal.stopTimer;
            toast.onmouseleave = Swal.resumeTimer;
        }
    });
    Toast.fire({ icon: icon || "success", title: message });
}
