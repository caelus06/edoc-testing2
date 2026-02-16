let step = 0;
const steps = [
  { key: "front", title: "FRONT ID" },
  { key: "face", title: "BACK ID" },
  { key: "back", title: "FACE VERIFICATION" }
];

function renderStep() {
  const title = document.getElementById("leftTitle");
  const img = document.getElementById("previewImg");
  const front = img.dataset.front;
  const face  = img.dataset.face;
  const back  = img.dataset.back;

  title.textContent = steps[step].title;

  if (steps[step].key === "front") img.src = front;
  if (steps[step].key === "back")  img.src = back;
  if (steps[step].key === "face")  img.src = face;
}

function nextStep() {
  if (step < steps.length - 1) step++;
  renderStep();
}

function prevStep() {
  if (step > 0) step--;
  renderStep();
}

function enableEdit(field) {
  const textEl = document.getElementById(field + "_text");
  const inputEl = document.getElementById(field + "_input");
  if (!textEl || !inputEl) return;

  textEl.classList.add("hidden");
  inputEl.classList.remove("hidden");
  inputEl.focus();
}

window.addEventListener("DOMContentLoaded", renderStep);
