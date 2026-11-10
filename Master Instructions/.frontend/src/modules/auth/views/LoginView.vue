<script setup lang="ts">
import { reactive } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth";

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const form = reactive({
  identity: "",
  password: "",
  remember: false,
});

async function handleLogin() {
  const success = await auth.login(form);

  if (success) {
    const redirect = typeof route.query.redirect === "string" ? route.query.redirect : "/dashboard";

    await router.push(redirect);
  }
}

function socialLogin(provider: "google" | "facebook") {
  console.info(`Login ${provider} belum diintegrasikan.`);
}
</script>

<template>
  <main class="acn-login-page">
    <div class="container">
      <div class="row justify-content-center align-items-center min-vh-100 py-5">
        <div class="col-12 col-xl-10">
          <div class="card acn-login-card border-0 shadow-lg overflow-hidden">
            <div class="row g-0">
              <!-- Branding -->
              <div class="col-lg-6 d-none d-lg-flex">
                <div class="acn-login-brand w-100">
                  <div class="brand-content">
                    <div class="brand-logo">
                      <i class="ti ti-hexagon-letter-a"></i>
                    </div>

                    <h1>ACN ERP</h1>

                    <h3>Integrated Commodity Ecosystem</h3>

                    <p>Sistem pengelolaan usaha terintegrasi untuk mendukung operasional, distribusi, dan pertumbuhan bisnis.</p>

                    <div class="brand-decoration">
                      <div class="decoration-circle circle-one"></div>
                      <div class="decoration-circle circle-two"></div>
                      <div class="decoration-circle circle-three"></div>
                      <i class="ti ti-chart-dots-3 decoration-icon"></i>
                    </div>

                    <div class="brand-footer">
                      <span>© {{ new Date().getFullYear() }} ACN ERP</span>
                      <span>Integrated Business Management</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Login Form -->
              <div class="col-lg-6">
                <div class="acn-login-form">
                  <div class="text-center mb-4 d-lg-none">
                    <div class="mobile-logo">
                      <i class="ti ti-hexagon-letter-a"></i>
                    </div>
                    <h3 class="fw-bold mt-2 mb-1">ACN ERP</h3>
                  </div>

                  <div class="mb-4">
                    <h2 class="fw-bold mb-2">Welcome Back!</h2>
                    <p class="text-muted mb-0">Silakan masuk menggunakan akun Anda.</p>
                  </div>

                  <form @submit.prevent="handleLogin">
                    <div class="mb-4">
                      <label for="identity" class="form-label fw-semibold"> Username atau Email </label>

                      <div class="input-group acn-input-group">
                        <span class="input-group-text">
                          <i class="ti ti-user"></i>
                        </span>

                        <input id="identity" v-model="form.identity" type="text" class="form-control" placeholder="Username atau email" autocomplete="username" required />
                      </div>
                    </div>

                    <div class="mb-3">
                      <label for="password" class="form-label fw-semibold"> Password </label>

                      <div class="input-group acn-input-group">
                        <span class="input-group-text">
                          <i class="ti ti-lock"></i>
                        </span>

                        <input id="password" v-model="form.password" type="password" class="form-control" placeholder="Masukkan password" autocomplete="current-password" required />
                      </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between mb-4">
                      <div class="form-check">
                        <input id="remember" v-model="form.remember" class="form-check-input" type="checkbox" />

                        <label class="form-check-label text-muted" for="remember"> Remember this device </label>
                      </div>
                    </div>

                    <div v-if="auth.error" class="alert alert-danger d-flex align-items-center gap-2" role="alert">
                      <i class="ti ti-alert-circle fs-5"></i>
                      <span>{{ auth.error }}</span>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 acn-login-button" :disabled="auth.loading">
                      <span v-if="auth.loading" class="spinner-border spinner-border-sm me-2"></span>

                      <i v-else class="ti ti-login-2 me-2"></i>

                      {{ auth.loading ? "Memproses..." : "Sign In" }}
                    </button>
                  </form>
                  <div class="d-flex align-items-center gap-3 my-4">
                    <div class="flex-grow-1 border-top"></div>
                    <small class="text-muted">Or sign in with</small>
                    <div class="flex-grow-1 border-top"></div>
                  </div>

                  <div class="row g-3">
                    <div class="col-6">
                      <button type="button" class="btn btn-outline-secondary w-100 social-button" @click="socialLogin('google')">
                        <i class="ti ti-brand-google fs-5 me-2"></i>
                        Google
                      </button>
                    </div>

                    <div class="col-6">
                      <button type="button" class="btn btn-outline-secondary w-100 social-button" @click="socialLogin('facebook')">
                        <i class="ti ti-brand-facebook fs-5 me-2"></i>
                        Facebook
                      </button>
                    </div>
                  </div>
                  <div class="text-center mt-5">
                    <small class="text-muted"> Protected by ACN ERP Authentication </small>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="text-center mt-4 text-muted">
            <small> ACN ERP · Integrated Commodity Ecosystem </small>
          </div>
        </div>
      </div>
    </div>
  </main>
</template>

<style scoped>
.acn-login-page {
  height: 100vh;
  min-height: 0;
  overflow: hidden;
}

.acn-login-page > .container {
  height: 100%;
}

.acn-login-page .row {
  height: 100%;
  min-height: 0 !important;
  padding-top: 12px !important;
  padding-bottom: 12px !important;
}

.acn-login-card {
  height: min(680px, calc(100vh - 24px));
  min-height: 0;
}

.acn-login-brand {
  position: relative;
  overflow: hidden;
  padding: 15px 15px 20px;
  color: #fff;
  background: linear-gradient(145deg, #2878e8 0%, #1554b8 55%, #103d91 100%);
}

.brand-content {
  position: relative;
  z-index: 2;
  display: flex;
  flex-direction: column;
  height: 100%;
}

.brand-logo,
.mobile-logo {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 58px;
  height: 58px;
  border-radius: 15px;
  background: rgba(255, 255, 255, 0.17);
  font-size: 34px;
  margin-bottom: 35px;
}

.acn-login-brand h1 {
  font-size: 36px;
  font-weight: 800;
  letter-spacing: 1px;
  margin-bottom: 12px;
}

.acn-login-brand h3 {
  font-size: 19px;
  font-weight: 600;
  line-height: 1.5;
  margin-bottom: 18px;
}

.acn-login-brand p {
  color: rgba(255, 255, 255, 0.78);
  line-height: 1.8;
  max-width: 390px;
}

.brand-decoration {
  position: relative;
  flex: 1;
  min-height: 180px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.decoration-circle {
  position: absolute;
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 50%;
}

.circle-one {
  width: 180px;
  height: 180px;
}

.circle-two {
  width: 270px;
  height: 270px;
}

.circle-three {
  width: 360px;
  height: 360px;
}

.decoration-icon {
  font-size: 100px;
  opacity: 0.85;
}

.brand-footer {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  color: rgba(255, 255, 255, 0.65);
  font-size: 11px;
}

.acn-login-form {
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 30px 45px;
}

.social-button {
  height: 45px;
  border-radius: 8px;
  font-weight: 500;
}

.acn-login-form h2 {
  font-size: 27px;
}

.acn-input-group {
  border: 1px solid #e4e9f0;
  border-radius: 8px;
  overflow: hidden;
}

.acn-input-group .input-group-text {
  background: #fff;
  border: 0;
  color: #7c8da5;
  padding-left: 15px;
}

.acn-input-group .form-control {
  border: 0;
  box-shadow: none;
  min-height: 48px;
  padding-left: 5px;
}

.acn-input-group:focus-within {
  border-color: #5d9cec;
  box-shadow: 0 0 0 3px rgba(45, 126, 232, 0.1);
}

.acn-login-button {
  min-height: 48px;
  border-radius: 8px;
  font-weight: 600;
  box-shadow: 0 5px 12px rgba(45, 126, 232, 0.2);
}

.mobile-logo {
  margin: 0 auto;
  color: #2878e8;
  background: #eaf2ff;
}

@media (max-width: 991.98px) {
  .acn-login-card {
    min-height: auto;
  }

  .acn-login-form {
    padding: 45px 35px;
  }
}

@media (max-width: 575.98px) {
  .acn-login-form {
    padding: 35px 22px;
  }

  .acn-login-form h2 {
    font-size: 24px;
  }
}
</style>
