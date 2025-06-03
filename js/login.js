const signInBtn = document.getElementById("SignIn");
const signUpBtn = document.getElementById("SignUp");
const firstForm = document.getElementById("SignInForm");
const secondForm = document.getElementById("SignUpForm");
const container = document.querySelector(".container");

signUpBtn.addEventListener("click", () => {
	container.classList.remove("active");
});

signInBtn.addEventListener("click", () => {
	container.classList.add("active");
});

firstForm.addEventListener("submit", (e) => e.preventDefault());
secondForm.addEventListener("submit", (e) => e.preventDefault());