/**
 * 登录页面交互脚本
 * 处理登录和注册表单之间的切换动画
 */

// DOM 元素获取
const signInBtn = document.getElementById("SignIn");
const signUpBtn = document.getElementById("SignUp");
const signInForm = document.getElementById("SignInForm");
const signUpForm = document.getElementById("SignUpForm");
const container = document.querySelector(".container");

// 检查必要元素是否存在
if (!signInBtn || !signUpBtn || !signInForm || !signUpForm || !container) {
    console.error('登录页面必要元素未找到');
}

/**
 * 切换到注册表单
 */
function switchToSignUp() {
    container.classList.remove("active");
    console.log('切换到注册表单');
}

/**
 * 切换到登录表单
 */
function switchToSignIn() {
    container.classList.add("active");
    console.log('切换到登录表单');
}

/**
 * 处理表单提交（阻止默认提交行为）
 * @param {Event} event - 表单提交事件
 */
function handleFormSubmit(event) {
    event.preventDefault();
    console.log('表单提交被阻止，这里可以添加实际的登录/注册逻辑');
    // TODO: 添加实际的表单验证和提交逻辑
}

// 事件监听器
if (signUpBtn) {
    signUpBtn.addEventListener("click", switchToSignUp);
}

if (signInBtn) {
    signInBtn.addEventListener("click", switchToSignIn);
}

if (signInForm) {
    signInForm.addEventListener("submit", handleFormSubmit);
}

if (signUpForm) {
    signUpForm.addEventListener("submit", handleFormSubmit);
}

// 页面加载完成提示
console.log('登录页面脚本加载完成');