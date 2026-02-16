function openNotif() {
  document.getElementById("notifBackdrop").style.display = "flex";
}
function closeNotif() {
  document.getElementById("notifBackdrop").style.display = "none";
}
window.addEventListener("keydown", (e) => {
  if (e.key === "Escape") closeNotif();
});
