<script setup>
import { reactive, ref } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useAuthStore } from "../stores/auth.js";

const router = useRouter();
const route = useRoute();
const auth = useAuthStore();

const form = reactive({
  login: "",
  password: "",
});

const submitting = ref(false);
const errorMessage = ref("");
const showPassword = ref(false);

async function submitLogin() {
  if (submitting.value) return;

  submitting.value = true;
  errorMessage.value = "";

  try {
    await auth.login({
      login: form.login.trim(),
      password: form.password,
    });

    const redirect = route.query.redirect;

    const target = typeof redirect === "string" && redirect.startsWith("/") && !redirect.startsWith("//") && !redirect.startsWith("/\\") ? redirect : { name: "dashboard" };

    await router.replace(target);
  } catch (error) {
    errorMessage.value = error?.message || "Login gagal. Periksa username dan password Anda.";
  } finally {
    submitting.value = false;
  }
}
</script>

<template>
  <main class="auth-page">
    <div class="auth-container">
      <section class="auth-card">
        <header class="auth-heading">
          <div class="brand-mark">ACN</div>

          <h1>Welcome to ACN ERP</h1>
          <p>Your Integrated Business Ecosystem</p>
        </header>

        <div class="auth-divider">
          <span>Sign in to continue</span>
        </div>

        <form class="auth-form" @submit.prevent="submitLogin">
          <div class="form-group">
            <label for="login">Email or Username</label>

            <div class="input-wrap">
              <span class="input-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                  <circle cx="12" cy="8" r="4" />
                  <path d="M4 21v-2a8 8 0 0 1 16 0v2" />
                </svg>
              </span>

              <input id="login" v-model="form.login" type="text" placeholder="Enter your email or username" autocomplete="username" required :disabled="submitting" />
            </div>
          </div>

          <div class="form-group">
            <label for="password">Password</label>

            <div class="input-wrap">
              <span class="input-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none">
                  <rect x="4" y="10" width="16" height="11" rx="2" />
                  <path d="M8 10V7a4 4 0 1 1 8 0v3" />
                </svg>
              </span>

              <input id="password" v-model="form.password" :type="showPassword ? 'text' : 'password'" placeholder="Enter your password" autocomplete="current-password" required :disabled="submitting" />

              <button type="button" class="password-toggle" :aria-label="showPassword ? 'Hide password' : 'Show password'" :disabled="submitting" @click="showPassword = !showPassword">
                <svg viewBox="0 0 24 24" fill="none">
                  <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
                  <circle cx="12" cy="12" r="3" />
                </svg>
              </button>
            </div>
          </div>

          <div v-if="errorMessage" class="error-message" role="alert" aria-live="polite">
            {{ errorMessage }}
          </div>

          <div class="auth-options">
            <span class="secure-note">
              <span class="status-dot"></span>
              Secure authentication
            </span>

            <RouterLink :to="{ name: 'forgot-password' }"> Forgot Password? </RouterLink>
          </div>

          <button type="submit" class="submit-button" :disabled="submitting">
            <span v-if="submitting" class="spinner"></span>
            {{ submitting ? "Signing in..." : "Sign In" }}
          </button>
        </form>

        <footer class="auth-footer">
          <p>
            New to ACN ERP?
            <RouterLink :to="{ name: 'register' }"> Create an account </RouterLink>
          </p>

          <span>© {{ new Date().getFullYear() }} ACN ERP</span>
        </footer>
      </section>
    </div>
  </main>
</template>

<style scoped>
.auth-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  background: #f6f9fc;
  color: #2a3547;
  font-family: inherit;
}

.auth-container {
  width: 100%;
  max-width: 470px;
}

.auth-card {
  padding: 42px 40px 30px;
  background: #fff;
  border: 1px solid #e8edf3;
  border-radius: 12px;
  box-shadow: 0 8px 30px rgb(42 53 71 / 7%);
}

.auth-heading {
  text-align: center;
  margin-bottom: 30px;
}

.brand-mark {
  display: grid;
  place-items: center;
  width: 60px;
  height: 60px;
  margin: 0 auto 20px;
  border-radius: 15px;
  background: #5d87ff;
  color: white;
  font-size: 19px;
  font-weight: 800;
  letter-spacing: 1px;
}

.auth-heading h1 {
  margin: 0 0 9px;
  font-size: 25px;
  font-weight: 700;
  letter-spacing: -0.5px;
}

.auth-heading p {
  margin: 0;
  color: #7c8fac;
  font-size: 14px;
}

.auth-divider {
  display: flex;
  align-items: center;
  gap: 14px;
  margin: 26px 0;
  color: #7c8fac;
  font-size: 13px;
}

.auth-divider::before,
.auth-divider::after {
  content: "";
  flex: 1;
  height: 1px;
  background: #e8edf3;
}

.auth-form {
  display: grid;
  gap: 21px;
}

.form-group {
  display: grid;
  gap: 9px;
}

.form-group label {
  font-size: 14px;
  font-weight: 600;
}

.input-wrap {
  display: flex;
  align-items: center;
  min-height: 49px;
  padding: 0 13px;
  border: 1px solid #dfe5ef;
  border-radius: 7px;
  background: #fff;
  transition:
    border-color 0.2s,
    box-shadow 0.2s;
}

.input-wrap:focus-within {
  border-color: #5d87ff;
  box-shadow: 0 0 0 3px rgb(93 135 255 / 12%);
}

.input-icon {
  display: flex;
  flex-shrink: 0;
  margin-right: 11px;
  color: #7c8fac;
}

.input-icon svg,
.password-toggle svg {
  width: 19px;
  height: 19px;
  stroke: currentColor;
  stroke-width: 1.7;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.input-wrap input {
  width: 100%;
  min-width: 0;
  padding: 13px 0;
  border: 0;
  outline: 0;
  background: transparent;
  color: #2a3547;
  font: inherit;
  font-size: 14px;
}

.input-wrap input::placeholder {
  color: #a0aec0;
}

.password-toggle {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  padding: 5px;
  border: 0;
  background: transparent;
  color: #7c8fac;
  cursor: pointer;
}

.auth-options {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-top: -5px;
  font-size: 12px;
}

.auth-options a,
.auth-footer a {
  color: #5d87ff;
  font-weight: 600;
  text-decoration: none;
}

.auth-options a:hover,
.auth-footer a:hover {
  text-decoration: underline;
}

.secure-note {
  display: flex;
  align-items: center;
  gap: 7px;
  color: #7c8fac;
}

.status-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #13deb9;
}

.error-message {
  padding: 11px 13px;
  border: 1px solid #fecdca;
  border-radius: 7px;
  background: #fef3f2;
  color: #b42318;
  font-size: 13px;
  line-height: 1.5;
}

.submit-button {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 9px;
  min-height: 48px;
  padding: 12px 18px;
  border: 0;
  border-radius: 7px;
  background: #5d87ff;
  color: #fff;
  font: inherit;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition:
    background 0.2s,
    transform 0.2s;
}

.submit-button:hover:not(:disabled) {
  background: #4570e8;
}

.submit-button:disabled {
  cursor: not-allowed;
  opacity: 0.7;
}

.spinner {
  width: 16px;
  height: 16px;
  border: 2px solid rgb(255 255 255 / 40%);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}

.auth-footer {
  margin-top: 30px;
  text-align: center;
  font-size: 13px;
}

.auth-footer p {
  margin: 0 0 20px;
  color: #7c8fac;
}

.auth-footer span {
  color: #a0aec0;
  font-size: 12px;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 480px) {
  .auth-page {
    padding: 20px 14px;
  }

  .auth-card {
    padding: 32px 22px 25px;
  }

  .auth-heading h1 {
    font-size: 22px;
  }

  .auth-options {
    align-items: flex-start;
    flex-direction: column;
  }
}
</style>
