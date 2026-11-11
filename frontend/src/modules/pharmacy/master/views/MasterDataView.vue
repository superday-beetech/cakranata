<script setup>
// 9 menu Master Data apotek.
// fields = isian form, examples = contoh isi awal.
const masters = [
  {
    key: "product",
    label: "Product",
    icon: "ti-pill",
    desc: "Data obat dan barang yang dijual",
    fields: ["Kode", "Nama dagang", "Nama generik", "Barcode", "Kategori", "Satuan", "Pabrik", "Rak", "Harga beli", "Harga jual", "Stok minimum", "No. izin edar (BPOM)"],
    examples: ["Paracetamol 500 mg", "Amoxicillin 500 mg", "Vitamin C 500 mg", "Masker Medis 3 Ply"],
  },
  {
    key: "category",
    label: "Category",
    icon: "ti-category",
    desc: "Golongan obat dan barang",
    fields: ["Nama kategori", "Keterangan"],
    examples: ["Obat Bebas", "Obat Bebas Terbatas", "Obat Keras", "Narkotika", "Psikotropika", "Obat Herbal/Tradisional", "Alat Kesehatan", "Suplemen/Vitamin", "Kosmetik/Perawatan"],
  },
  {
    key: "unit",
    label: "Unit",
    icon: "ti-ruler-measure",
    desc: "Satuan jual/beli dan konversinya",
    fields: ["Nama satuan", "Konversi ke satuan terkecil"],
    examples: ["Tablet", "Kapsul", "Strip (1 strip = 10 tablet)", "Box (1 box = 10 strip)", "Botol", "Tube", "Ampul", "Sachet", "Pcs"],
  },
  {
    key: "supplier",
    label: "Supplier",
    icon: "ti-truck-delivery",
    desc: "PBF/distributor tempat membeli obat",
    fields: ["Nama PBF", "Alamat", "Telepon", "Nama sales", "NPWP", "Termin pembayaran"],
    examples: ["PBF Sehat Sentosa (tempo 30 hari)", "PBF Medika Jaya (tunai)"],
  },
  {
    key: "customer",
    label: "Customer",
    icon: "ti-users",
    desc: "Pelanggan/pasien",
    fields: ["Nama", "Telepon", "Alamat", "Tanggal lahir", "Riwayat alergi"],
    examples: ["Umum (tanpa data)", "Budi Santoso (alergi penisilin)", "Siti Aminah"],
  },
  {
    key: "doctor",
    label: "Doctor",
    icon: "ti-stethoscope",
    desc: "Dokter penulis resep",
    fields: ["Nama dokter", "No. SIP", "Spesialisasi", "Tempat praktik", "Telepon"],
    examples: ["dr. Andi Pratama, Sp.PD", "dr. Rina Lestari (umum)"],
  },
  {
    key: "warehouse",
    label: "Warehouse & Rack",
    icon: "ti-building-warehouse",
    desc: "Lokasi penyimpanan dan rak di dalamnya",
    fields: ["Kode lokasi", "Nama lokasi", "Kode rak", "Nama rak", "Keterangan"],
    examples: ["Etalase depan: Rak A1, Rak A2", "Gudang belakang: Rak B1, Rak B2", "Lemari narkotika: Laci 1", "Lemari pendingin: Rak dingin 1"],
  },
  {
    key: "manufacturer",
    label: "Manufacturer",
    icon: "ti-building-factory-2",
    desc: "Pabrik/produsen obat",
    fields: ["Nama pabrik", "Alamat", "Telepon"],
    examples: ["Kimia Farma", "Kalbe Farma", "Dexa Medica", "Sanbe Farma", "Combiphar"],
  },
  {
    key: "signa",
    label: "Dosage Rule",
    icon: "ti-clipboard-text",
    desc: "Aturan pakai (signa) untuk etiket",
    fields: ["Kode", "Aturan pakai"],
    examples: ["3 x 1 sesudah makan", "2 x 1 sebelum makan", "1 x 1 malam hari", "Bila perlu", "Dioleskan tipis-tipis"],
  },
];
</script>

<template>
  <ul class="nav nav-pills user-profile-tab justify-content-start mt-2 bg-primary-subtle rounded-2 rounded-top-0 flex-nowrap overflow-auto" id="pills-tab" role="tablist">
    <li v-for="(m, i) in masters" :key="m.key" class="nav-item" role="presentation">
      <button
        class="nav-link hstack gap-2 rounded-0 py-6 text-nowrap"
        :class="{ active: i === 0 }"
        :id="`pills-${m.key}-tab`"
        data-bs-toggle="pill"
        :data-bs-target="`#pills-${m.key}`"
        type="button"
        role="tab"
        :aria-controls="`pills-${m.key}`"
        :aria-selected="i === 0"
      >
        <i class="ti fs-5" :class="m.icon"></i>
        <span class="d-none d-md-block">{{ m.label }}</span>
      </button>
    </li>
  </ul>

  <div class="tab-content" id="pills-tabContent">
    <div v-for="(m, i) in masters" :key="m.key" class="tab-pane fade" :class="{ 'show active': i === 0 }" :id="`pills-${m.key}`" role="tabpanel" :aria-labelledby="`pills-${m.key}-tab`" tabindex="0">
      <div class="row">
        <div class="col-lg-6">
          <div class="card shadow-none border">
            <div class="card-body">
              <h4 class="mb-3">Isian {{ m.label }}</h4>
              <p class="card-subtitle">{{ m.desc }}</p>
              <div class="vstack gap-3 mt-4">
                <div v-for="f in m.fields" :key="f" class="hstack gap-6">
                  <i class="ti ti-forms text-dark fs-6"></i>
                  <h6 class="mb-0">{{ f }}</h6>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="card shadow-none border">
            <div class="card-body">
              <h4 class="mb-3">Contoh isi</h4>
              <p class="card-subtitle">Data awal untuk {{ m.label }}</p>
              <div class="vstack gap-3 mt-4">
                <div v-for="(e, idx) in m.examples" :key="idx" class="hstack gap-6">
                  <i class="ti ti-circle-check text-dark fs-6"></i>
                  <h6 class="mb-0">{{ e }}</h6>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
