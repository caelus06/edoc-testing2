const loginTab = document.getElementById("loginTab");
const signupTab = document.getElementById("signupTab");
const loginForm = document.getElementById("loginForm");
const signupForm = document.getElementById("signupForm");

loginTab.addEventListener("click", () => {
  loginTab.classList.add("active");
  signupTab.classList.remove("active");
  loginForm.hidden = false;
  signupForm.hidden = true;
});

signupTab.addEventListener("click", () => {
  signupTab.classList.add("active");
  loginTab.classList.remove("active");
  loginForm.hidden = true;
  signupForm.hidden = false;
});
