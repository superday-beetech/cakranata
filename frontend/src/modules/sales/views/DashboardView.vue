<script setup lang="ts">
import { computed } from "vue";
import { RouterLink } from "vue-router";

const today = computed(() =>
  new Intl.DateTimeFormat("id-ID", {
    weekday: "long",
    day: "numeric",
    month: "long",
    year: "numeric",
  }).format(new Date()),
);

// Data akan dihubungkan ke API dashboard POS.
const statistics = [
  {
    label: "Penjualan Hari Ini",
    value: "—",
    description: "Total nilai penjualan",
    icon: "ti ti-cash",
    color: "primary",
  },
  {
    label: "Transaksi Hari Ini",
    value: "—",
    description: "Jumlah transaksi berhasil",
    icon: "ti ti-receipt",
    color: "success",
  },
  {
    label: "Transaksi Draft",
    value: "—",
    description: "Menunggu penyelesaian",
    icon: "ti ti-clock",
    color: "warning",
  },
  {
    label: "Rata-rata Transaksi",
    value: "—",
    description: "Nilai rata-rata penjualan",
    icon: "ti ti-chart-bar",
    color: "info",
  },
];

const shortcuts = [
  {
    title: "Transaksi Baru",
    description: "Mulai melayani pelanggan.",
    icon: "ti ti-shopping-cart",
    path: "/sales/pos",
    color: "primary",
  },
  {
    title: "Riwayat Penjualan",
    description: "Lihat transaksi penjualan.",
    icon: "ti ti-receipt",
    path: "/sales/history",
    color: "success",
  },
  {
    title: "Daftar Produk",
    description: "Cari informasi produk.",
    icon: "ti ti-package",
    path: "/pharmacy/master/products",
    color: "info",
  },
  {
    title: "Laporan Penjualan",
    description: "Lihat laporan penjualan.",
    icon: "ti ti-chart-line",
    path: "/pharmacy/reports/sales",
    color: "warning",
  },
];
</script>

<template>
  <div class="pos-dashboard">
    <!-- HEADER -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
      <div>
        <div class="text-primary fw-semibold small mb-1">POINT OF SALE</div>
        <h4 class="fw-bold mb-1">Dashboard POS</h4>
        <p class="text-muted mb-0 small">Selamat datang di sistem kasir apotek.</p>
      </div>

      <div class="text-muted small">
        <i class="ti ti-calendar me-1"></i>
        {{ today }}
      </div>
    </div>

    <!-- WELCOME / ACTION -->
    <div class="pos-welcome mb-4">
      <div class="pos-welcome-content">
        <span class="pos-welcome-label">PHARMACY POINT OF SALE</span>
        <h3>Kelola Penjualan Lebih Mudah</h3>
        <p>Mulai transaksi, kelola pembayaran, dan pantau aktivitas penjualan apotek dari satu tempat.</p>

        <RouterLink to="/sales/pos" class="btn btn-light text-primary fw-semibold mt-2">
          <i class="ti ti-shopping-cart me-2"></i>
          Mulai Transaksi
          <i class="ti ti-arrow-right ms-2"></i>
        </RouterLink>
      </div>

      <div class="pos-welcome-icon">
        <i class="ti ti-building-hospital"></i>
      </div>
    </div>

    <!-- STATISTICS -->
    <div class="row g-3 mb-4">
      <div v-for="item in statistics" :key="item.label" class="col-12 col-sm-6 col-xl-3">
        <div class="card pos-stat-card h-100 mb-0">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="text-muted small mb-2">
                  {{ item.label }}
                </div>
                <h4 class="fw-bold mb-1">{{ item.value }}</h4>
                <small class="text-muted">
                  {{ item.description }}
                </small>
              </div>

              <div class="pos-stat-icon" :class="`bg-${item.color}-subtle text-${item.color}`">
                <i :class="item.icon"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- QUICK ACCESS -->
    <div class="mb-4">
      <div class="mb-3">
        <h5 class="fw-bold mb-1">Akses Cepat</h5>
        <p class="text-muted small mb-0">Pilih aktivitas yang ingin dilakukan.</p>
      </div>

      <div class="row g-3">
        <div v-for="item in shortcuts" :key="item.title" class="col-12 col-sm-6 col-xl-3">
          <RouterLink :to="item.path" class="pos-shortcut-card">
            <div class="pos-shortcut-icon" :class="`bg-${item.color}-subtle text-${item.color}`">
              <i :class="item.icon"></i>
            </div>

            <div class="flex-grow-1">
              <h6 class="fw-bold mb-1">{{ item.title }}</h6>
              <p class="text-muted small mb-0">
                {{ item.description }}
              </p>
            </div>

            <i class="ti ti-chevron-right text-muted"></i>
          </RouterLink>
        </div>
      </div>
    </div>

    <!-- BOTTOM CONTENT -->
    <div class="row g-3">
      <div class="col-12 col-xl-8">
        <div class="card h-100 mb-0">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div>
                <h5 class="fw-bold mb-1">Aktivitas Penjualan</h5>
                <p class="text-muted small mb-0">Ringkasan aktivitas transaksi terbaru.</p>
              </div>
              <i class="ti ti-activity fs-4 text-primary"></i>
            </div>

            <div class="pos-empty-state">
              <i class="ti ti-receipt"></i>
              <h6>Belum ada data aktivitas</h6>
              <p>Aktivitas penjualan akan ditampilkan setelah data transaksi dihubungkan.</p>
            </div>
          </div>
        </div>
      </div>

      <div class="col-12 col-xl-4">
        <div class="card h-100 mb-0">
          <div class="card-body">
            <h5 class="fw-bold mb-1">Transaksi Ditahan</h5>
            <p class="text-muted small mb-3">Draft yang belum diselesaikan.</p>

            <div class="pos-empty-state">
              <i class="ti ti-clock"></i>
              <h6>Belum ada transaksi ditahan</h6>
              <p>Transaksi draft akan muncul di bagian ini.</p>
            </div>

            <RouterLink to="/pharmacy/sales/pos" class="btn btn-outline-primary btn-sm w-100"> Buka Kasir </RouterLink>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.pos-dashboard {
  padding-bottom: 24px;
}

.pos-welcome {
  position: relative;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 210px;
  padding: 30px 36px;
  border-radius: 12px;
  color: white;
  background: linear-gradient(115deg, #172554, #2563eb);
}

.pos-welcome-content {
  position: relative;
  z-index: 1;
  max-width: 570px;
}

.pos-welcome-label {
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 2px;
  color: #bfdbfe;
}

.pos-welcome h3 {
  font-size: 26px;
  font-weight: 750;
  margin: 12px 0 8px;
}

.pos-welcome p {
  color: #dbeafe;
  font-size: 13px;
  margin: 0;
  max-width: 480px;
  line-height: 1.7;
}

.pos-welcome-icon {
  position: absolute;
  right: 65px;
  font-size: 150px;
  color: rgb(255 255 255 / 12%);
}

.pos-stat-card,
.pos-shortcut-card {
  border: 1px solid var(--bs-border-color);
  border-radius: 10px;
  transition: 0.2s ease;
}

.pos-stat-card:hover,
.pos-shortcut-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 5px 18px rgb(0 0 0 / 5%);
}

.pos-stat-icon,
.pos-shortcut-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 46px;
  height: 46px;
  border-radius: 10px;
  font-size: 23px;
}

.pos-shortcut-card {
  display: flex;
  align-items: center;
  gap: 13px;
  height: 100%;
  padding: 17px;
  background: var(--bs-card-bg);
  text-decoration: none;
  color: var(--bs-body-color);
}

.pos-shortcut-icon {
  width: 42px;
  height: 42px;
  font-size: 20px;
}

.pos-empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 190px;
  text-align: center;
  padding: 20px;
}

.pos-empty-state > i {
  font-size: 38px;
  color: var(--bs-secondary-color);
  margin-bottom: 12px;
}

.pos-empty-state h6 {
  font-weight: 700;
}

.pos-empty-state p {
  max-width: 300px;
  color: var(--bs-secondary-color);
  font-size: 12px;
  margin-bottom: 0;
}

@media (max-width: 767px) {
  .pos-welcome {
    padding: 24px;
  }

  .pos-welcome h3 {
    font-size: 21px;
  }

  .pos-welcome-icon {
    right: 15px;
    font-size: 90px;
    opacity: 0.5;
  }
}
</style>
