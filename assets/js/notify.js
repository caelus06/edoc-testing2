function openNotify() {
  const overlay = document.getElementById("notifyOverlay");
  if (overlay) overlay.style.display = "flex";
}

function closeNotify() {
  const overlay = document.getElementById("notifyOverlay");
  if (overlay) overlay.style.display = "none";
}

// close when clicking outside modal
document.addEventListener("click", (e) => {
  const overlay = document.getElementById("notifyOverlay");
  const modal = document.getElementById("notifyModal");
  if (!overlay || !modal) return;

  if (overlay.style.display === "flex" && e.target === overlay) {
    closeNotify();
  }
});

// escape to close
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") closeNotify();
});
