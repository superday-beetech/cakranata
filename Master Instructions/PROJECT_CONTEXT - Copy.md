# PROJECT CONTEXT — ACN ERP

**Project:** ACN ERP  
**Organization:** PT ADI CAKRA NATA (ACN)  
**Tagline:** Integrated Commodity Ecosystem  
**Document:** PROJECT_CONTEXT.md  
**Purpose:** Menjadi sumber konteks utama bagi AI dalam memahami sistem ACN ERP.

---

# 1. PROJECT OVERVIEW

ACN ERP adalah sistem Enterprise Resource Planning yang dikembangkan secara modular menggunakan CodeIgniter 4 sebagai backend dan Vue 3 sebagai frontend.

Sistem dirancang untuk mendukung pengelolaan operasional bisnis ACN dan pengembangan ekosistem usaha terintegrasi.

Prinsip utama pengembangan:

- Modular dan terstruktur.
- Backend sebagai pusat otorisasi dan pengelolaan data.
- Frontend sebagai antarmuka pengguna.
- API sebagai kontrak komunikasi backend dan frontend.
- RBAC sebagai pusat pengaturan akses.
- Route Registry sebagai sumber pengelolaan endpoint.
- Dapat dikembangkan untuk integrasi web, mobile, dan layanan eksternal.
- Mengutamakan keamanan, konsistensi data, dan maintainability.

AI wajib memahami arsitektur yang sudah berjalan sebelum mengusulkan perubahan.

# 2. TECHNOLOGY STACK

## 2.1 Backend

| Komponen | Teknologi |
|---|---|
| Framework | CodeIgniter 4 |
| Authentication | CodeIgniter Shield |
| Settings | CodeIgniter Settings |
| Database | MySQL |
| Database development | Laragon |
| API | REST-style API |
| Backend language | PHP |

Informasi versi yang pernah digunakan:

- CodeIgniter 4.7.5
- CodeIgniter Shield 1.4.1

Versi aktual harus diverifikasi melalui file konfigurasi dan dependency project sebelum perubahan teknis dilakukan.

## 2.2 Frontend

| Komponen | Teknologi |
|---|---|
| Framework | Vue 3 |
| Build tool | Vite |
| Language | TypeScript |
| State management | Pinia |
| Routing | Vue Router |
| HTTP client | Axios |
| UI theme | Adminmart Modernize |

Preferensi implementasi:

- Vue 3 Composition API.
- `<script setup lang="ts">`.
- TypeScript strict.
- Pinia untuk state management.
- Axios melalui satu konfigurasi client terpusat.
- Vue Router untuk navigasi.
- Tidak menggunakan jQuery untuk implementasi frontend baru.
- Tidak membuat ulang fungsi yang sudah tersedia tanpa alasan teknis.

Versi dependency yang pernah digunakan:
- Vue 3.5.44
- Vite 8.3.5
- Vue Router 5.4.0
- Pinia 4.1.0
- Axios

Versi aktual wajib diperiksa melalui `package.json` dan lock file.

# 3. PROJECT DIRECTORY

Lokasi pengembangan lokal yang diketahui:

`C:\laragon\www\cakra`

Backend menggunakan struktur CodeIgniter 4.

Frontend Vue berada dalam direktori:

`frontend/`

Struktur frontend yang pernah digunakan:

```text
frontend/
└── src/
    ├── api/
		├── client.ts/
	├── assets/
		├── css/
		├── js/
	├── components/
		├── backend/
			├── AppHeader.vue/
			├── MenuItem.vue/
			├── Navbar.vue/
			├── Sidebar.vue/
		├── ui/
			├── ModalHost.vue/
			├── NotificationHost.vue/
			├── OffCanvasHost.vue/
		├── DynamicPage.vue
	├── composables/
		├── modal.ts/
		├── navigation.ts/
		├── notification.ts/
		├── offcanvas.ts/
		├── useTheme.ts/
	├── config/
    ├── modules/
	├── layouts/
    ├── router/
    ├── stores/
    ├── types/
    ├── utils/
    ├── main.ts
	├── App.vue/
    └── ...
```

Frontend memiliki pendekatan modular. Setiap module dapat memiliki file router sendiri.

Contoh mekanisme auto-route yang pernah digunakan:

```ts
import.meta.glob('../modules/*/router.ts')
```

Struktur direktori backend, namespace aktual, dan seluruh module harus diverifikasi dari source code terbaru.

AI tidak boleh mengarang struktur direktori yang belum diperiksa.

# 4. BACKEND ARCHITECTURE

## 4.1 CodeIgniter Modules

Sistem menggunakan pendekatan modular dengan namespace `Modules`.

Contoh namespace API:

```php
Modules\{Module}\Controllers\Api\V1
```

Contoh pola endpoint API:

```text
/api/v1/{module}/{endpoint}
```

Pembagian tanggung jawab:

- Controller menangani HTTP request dan response.
- Service menangani business logic.
- Repository atau model menangani akses data sesuai pola yang sudah diterapkan.
- Core menangani layanan lintas module.
- Module menangani domain bisnis masing-masing.

AI harus mengikuti pola aktual yang sudah ada. Jangan memperkenalkan pola baru secara sepihak.

## 4.2 API Response Standard

API menggunakan format response standar yang pernah diterapkan:

```json
{
  "status": 200,
  "error": false,
  "message": "Success",
  "data": {},
  "csrf_token": "..."
}
```

Ketentuan:

- Pertahankan kontrak response yang sudah digunakan frontend.
- Jangan mengubah nama field tanpa persetujuan.
- Jangan membuat format response berbeda untuk setiap module tanpa kebutuhan yang jelas.
- Error response harus konsisten dengan standar API yang berlaku.

# 5. DYNAMIC ROUTER & ROUTE REGISTRY

## 5.1 DynamicRouterBase

ACN ERP memiliki sistem DynamicRouterBase untuk pengelolaan routing secara dinamis.

Komponen yang pernah dikembangkan:

- RouterConfig
- ControllerScanner
- MethodPolicy
- RouteResolver
- AuthenticationResolver
- AccessChecker
- HttpVerbPolicy
- RateLimiter
- RouterResponseHandler

Konfigurasi yang pernah digunakan mencakup:

- allowedMethods
- publicMethods
- requiredPermissions
- requiredRoles
- allowedHttpVerbs
- httpVerbExclusions
- rateLimits
- authApiExceptions
- authRequired

Pengaturan terkait pernah menggunakan Settings:

- Core.httpVerbs
- Core.checkAccess

AI wajib memeriksa implementasi aktual sebelum mengubah komponen-komponen tersebut.

## 5.2 Route Registry

Route Registry merupakan pusat pengelolaan endpoint yang ditemukan dan didaftarkan oleh sistem.

Tabel yang diketahui:

- route_registry
- route_registry_verbs

Field status yang pernah digunakan:

- is_discovered
- is_routable
- is_allowed

Endpoint API yang pernah dikembangkan:

```text
/api/v1/route-registry
/api/v1/backend/settings/sve
```

Operasi yang pernah tersedia:

- scan
- apply
- registered
- getVerbs
- saveVerbs

HTTP verbs yang dikelola:

```text
GET
POST
PUT
PATCH
DELETE
OPTIONS
HEAD
```

Route Registry menjadi salah satu sumber acuan pengelolaan endpoint dan konfigurasi routing.

AI tidak boleh membuat sistem routing paralel atau mengabaikan Route Registry tanpa persetujuan.

# 6. AUTHENTICATION & AUTHORIZATION

## 6.1 Authentication

Sistem menggunakan CodeIgniter Shield dengan pengembangan mekanisme autentikasi yang disesuaikan dengan kebutuhan ACN ERP.

Arah implementasi frontend saat ini adalah session-based authentication untuk aplikasi Vue yang berjalan di browser.

Konfigurasi yang pernah dibahas:

- Axios `withCredentials: true`
- CSRF protection menggunakan session
- `tokenRandomize: true`
- CSRF token dikirimkan melalui response API dengan field `csrf_token`
- Client menyimpan token CSRF di memory dan mengirimkannya pada request yang memerlukan proteksi.

Endpoint autentikasi yang pernah digunakan:

```text
/api/v1/auth/login
/api/v1/auth/me
```

Payload login:

```json
{
  "identity": "username atau email",
  "password": "password",
  "remember": false
}
```

Catatan:

- Session browser menjadi arah implementasi saat ini.
- Token-based authentication untuk kebutuhan mobile atau integrasi lain merupakan pembahasan terpisah.
- Jangan mengubah mekanisme autentikasi tanpa analisis dampak dan persetujuan.

## 6.2 RBAC

ACN ERP menggunakan sistem Role-Based Access Control yang dikembangkan secara khusus.

**Prinsip penting: Shield bukan master utama definisi RBAC ACN ERP.**

Tabel yang diketahui:

- auth_groups
- auth_permissions
- auth_groups_permissions
- auth_groups_users
- auth_permissions_users

Service yang pernah dikembangkan:

- RbacService
- AccessControlService
- RouteRegistryAccessService

Group yang pernah digunakan:

| ID | Group |
|---:|---|
| 1 | superadmin |
| 2 | admin |
| 3 | User |
| 4 | developer |

Nama, ID, dan kondisi aktual wajib diverifikasi dari database.

Ketentuan RBAC:

- Backend merupakan sumber keputusan otorisasi.
- Vue tidak boleh menjadi satu-satunya lapisan keamanan.
- Menu yang disembunyikan di frontend bukan pengganti pemeriksaan permission backend.
- Permission dan role harus mengikuti sumber data RBAC ACN ERP.
- Proteksi akun superadmin/root harus dipertahankan.
- Jangan mengubah struktur tabel atau mekanisme permission tanpa persetujuan.

# 7. FRONTEND ARCHITECTURE

## 7.1 Vue Application

Frontend menggunakan Vue 3 dengan pendekatan modular.

Entry point yang pernah digunakan:

```text
frontend/src/main.ts
```

Inisialisasi aplikasi mencakup:

- createApp
- createPinia
- Vue Router
- Mount ke elemen `#app`

Module dapat mendaftarkan route melalui file:

```text
modules/*/router.ts
```

AI harus memeriksa konfigurasi router aktual sebelum menambahkan route.

## 7.2 State Management

Pinia digunakan untuk state management.

Store yang pernah dikembangkan atau direncanakan:

- auth store
- menu store
- permission store

Prinsip:

- State autentikasi dikelola secara terpusat.
- State menu tidak boleh menduplikasi sumber otorisasi backend.
- Komponen UI tidak boleh mengambil alih business logic yang seharusnya berada dalam store atau service.
- Hindari request API berulang yang tidak diperlukan.

## 7.3 API Client

Axios digunakan melalui konfigurasi client terpusat.

Ketentuan:

- `withCredentials: true` untuk session browser.
- Base URL API harus mendukung lingkungan development dan production.
- Jangan hardcode host development sebagai konfigurasi production.
- CSRF harus dikelola secara konsisten.
- Jangan membuat Axios instance baru di setiap module tanpa alasan.

## 7.4 Navigation & Menu

Menu frontend bersumber dari backend.

Prinsip:

- Backend menentukan menu yang boleh diakses pengguna.
- Menu mengikuti konfigurasi dan permission yang berlaku.
- Vue bertugas menampilkan menu.
- Layout tidak boleh menjadi pusat pengambilan keputusan RBAC.
- Jangan membuat menu statis yang menduplikasi konfigurasi backend tanpa persetujuan.

# 8. ENVIRONMENT & DEPLOYMENT

Lingkungan development yang pernah digunakan:

| Komponen | Konfigurasi |
|---|---|
| OS | Windows |
| Local server | Laragon |
| Backend project | `C:\laragon\www\cakra` |
| Local domain | `cakra.test` |
| Database | MySQL |
| Frontend dev server | Vite |
| Frontend build | Vite |

Frontend pernah menggunakan:

```text
public/acn/
```

sebagai lokasi hasil build yang disajikan oleh backend CI4.

Konfigurasi build, base path, output directory, manifest, dan URL API harus diperiksa dari konfigurasi aktual.

Target deployment:

- Development lokal.
- Production hosting.
- Kemungkinan pengembangan client mobile di masa mendatang.

Jangan menganggap konfigurasi development sama dengan production.

# 9. BUSINESS MODULE ROADMAP

Modul bisnis yang direncanakan atau menjadi bagian roadmap ACN ERP:

| Domain | Cakupan |
|---|---|
| Core | Authentication, RBAC, Settings, Audit, konfigurasi sistem |
| ERP | Procurement, Inventory, POS, Finance |
| HRM | Employee, Attendance, Member, Simpan Pinjam |
| Logistics | Pengelolaan logistik |
| Distribution | Distribusi dan jaringan penyaluran |
| EIS | Executive Information System |

Rencana tambahan yang pernah dibahas:

- Persediaan SPHP, pupuk, dan LPG 3 kg.
- Pengelolaan transaksi dan penjualan.
- GPS tracking.
- Face attendance.
- QRIS dan PPOB.
- Realtime chat.
- Integrasi aplikasi web dan mobile.

Daftar ini merupakan roadmap dan bukan bukti bahwa seluruh modul sudah selesai dibuat.

AI wajib membedakan:
- Sudah berjalan.
- Sedang dikembangkan.
- Baru direncanakan.

# 10. DATABASE GOVERNANCE

Database utama development yang pernah digunakan:

```text
cakra
```

Ketentuan:

- Jangan melakukan reset database tanpa instruksi eksplisit.
- Jangan menghapus tabel atau kolom yang sudah digunakan.
- Jangan mengubah nama tabel, kolom, index, foreign key, atau relasi tanpa analisis dampak.
- Migration harus mengikuti pola project.
- Jangan menganggap struktur database berdasarkan nama model saja.
- Periksa migration, model, service, dan skema aktual sebelum mengubah database.

# 11. DEVELOPMENT WORKFLOW

Setiap pekerjaan harus mengikuti tahapan berikut:

## Tahap 1 — Understand

Pahami kebutuhan dan tujuan perubahan.

## Tahap 2 — Inspect

Periksa source code, konfigurasi, route, service, model, database, dan frontend yang relevan.

## Tahap 3 — Analyze

Identifikasi:
- Kondisi saat ini.
- Alur kerja.
- Ketergantungan.
- Risiko regresi.
- Dampak terhadap module lain.

## Tahap 4 — Propose

Jelaskan solusi sebelum melakukan perubahan besar.

## Tahap 5 — Implement

Berikan perubahan dengan:
- Path file.
- Nama class atau function.
- Lokasi perubahan.
- Kode lengkap atau patch yang jelas.

## Tahap 6 — Verify

Berikan langkah pengujian yang dapat dijalankan.

Jangan menyatakan berhasil diuji jika belum benar-benar diuji.

## Tahap 7 — Document

Catat keputusan arsitektur dan perubahan penting agar dapat dilanjutkan pada percakapan berikutnya.

# 12. ARCHITECTURE CHANGE CONTROL

Perubahan berikut memerlukan analisis dampak dan persetujuan:

- Struktur database.
- Kontrak API.
- Authentication.
- Authorization dan RBAC.
- DynamicRouterBase.
- Route Registry.
- Struktur module.
- Mekanisme session dan CSRF.
- Konfigurasi deployment.
- Mekanisme frontend-backend communication.

Perubahan kecil yang tidak mengubah kontrak dan arsitektur dapat diusulkan langsung dengan penjelasan yang memadai.

# 13. CURRENT DEVELOPMENT STATUS

Status berikut merupakan gambaran berdasarkan diskusi sebelumnya, bukan hasil pemeriksaan source code terbaru.

| Area | Status konteks |
|---|---|
| CI4 backend | Sudah menjadi fondasi sistem |
| Shield authentication | Sudah diintegrasikan dan dikembangkan |
| Custom RBAC | Sudah memiliki desain dan implementasi awal |
| DynamicRouterBase | Sudah dikembangkan |
| Route Registry | Sudah dikembangkan |
| Vue 3 migration | Sedang dikembangkan |
| Pinia | Sudah digunakan dalam frontend |
| Axios API client | Sudah dikembangkan |
| Adminmart integration | Sedang disesuaikan |
| ERP business modules | Bertahap sesuai roadmap |

AI harus memperbarui status ini berdasarkan source code terbaru dan konfirmasi pengguna.

# 14. KNOWN ARCHITECTURAL DECISIONS

Keputusan yang harus dijaga selama pengembangan:

1. Backend tetap menjadi pusat business logic dan otorisasi.
2. Vue menjadi frontend utama yang sedang dikembangkan.
3. Custom RBAC ACN ERP menjadi acuan otorisasi, bukan konfigurasi default Shield semata.
4. Route Registry menjadi sumber pengelolaan endpoint.
5. DynamicRouterBase tidak boleh diganti dengan routing paralel tanpa persetujuan.
6. API contract harus dipertahankan.
7. Session browser merupakan arah autentikasi Vue saat ini.
8. Module harus tetap memiliki batas tanggung jawab yang jelas.
9. Perubahan dilakukan bertahap dan tidak merusak fitur yang sudah berjalan.

# 15. UNKNOWN / MUST VERIFY

AI tidak boleh mengarang atau menganggap informasi berikut sudah pasti:

- Struktur direktori backend terbaru.
- Seluruh daftar module aktif.
- Struktur database terkini.
- Isi file konfigurasi CI4.
- Konfigurasi aktual DynamicRouterBase.
- Implementasi terbaru Shield dan RBAC.
- Seluruh route API aktif.
- Isi aktual setiap Pinia store.
- Konfigurasi Vite terbaru.
- Status deployment production.
- Status penyelesaian setiap modul bisnis.

Jika informasi tersebut diperlukan untuk pekerjaan, periksa source code atau minta hanya file yang benar-benar belum tersedia.

# 16. CONTEXT UPDATE POLICY

Dokumen ini harus diperbarui ketika terjadi:

- Perubahan arsitektur.
- Penambahan module utama.
- Perubahan teknologi.
- Perubahan kontrak API.
- Perubahan authentication atau RBAC.
- Perubahan struktur database yang disetujui.
- Keputusan teknis permanen.
- Perubahan status development yang signifikan.

Jangan menimpa keputusan lama tanpa mencatat perubahan dan alasannya.

---

**PRINCIPLE**

ACN ERP adalah sistem yang berkembang secara bertahap.

AI harus memahami kondisi sistem yang sebenarnya, mempertahankan keputusan yang sudah disepakati, dan membantu mengembangkan sistem tanpa merusak fondasi yang sudah dibangun.