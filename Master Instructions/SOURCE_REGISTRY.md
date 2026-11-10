# SOURCE REGISTRY — ACN ERP

**Project:** ACN ERP  
**Document:** SOURCE_REGISTRY.md  
**Purpose:** Mencatat source code utama, lokasi, tanggung jawab, dependensi, dan status pemeriksaan.

---

# 1. REGISTRY RULES

AI wajib mengikuti ketentuan berikut:

1. Setiap file yang sudah diberikan dan diperiksa harus dicatat.
2. Jangan meminta ulang file yang masih tersedia dalam konteks Project.
3. Sebelum meminta file, periksa percakapan, Project Sources, dan registry.
4. Jangan menganggap file yang pernah diperiksa sebagai versi terbaru tanpa verifikasi.
5. Setiap perubahan source code harus memperbarui catatan registry jika berdampak pada fungsi atau arsitektur.
6. Jangan mengarang path, nama class, method, atau isi file.
7. Jika file belum pernah diperiksa, statusnya harus `NOT_INSPECTED`.
8. Jika file sudah diperiksa tetapi mungkin berubah, statusnya `REVERIFY`.
9. Jika file tidak tersedia, catat `UNAVAILABLE` dan jelaskan alasannya.
10. Registry ini merupakan indeks dokumentasi, bukan pengganti source code aktual.

# 2. STATUS DEFINITIONS

| Status | Arti |
|---|---|
| NOT_INSPECTED | Belum pernah diperiksa |
| INSPECTED | Sudah diperiksa pada versi tertentu |
| REVERIFY | Perlu diperiksa ulang karena ada perubahan atau ketidakpastian |
| MODIFIED | Sudah mengalami perubahan |
| VERIFIED | Perubahan sudah diuji dan diverifikasi |
| UNAVAILABLE | Source code tidak tersedia |
| DEPRECATED | Tidak lagi digunakan |

**Penting:** `INSPECTED` tidak berarti implementasi benar. `VERIFIED` hanya boleh diberikan jika pengujian benar-benar dilakukan.

# 3. BACKEND SOURCE REGISTRY

Root backend:

```text
C:\laragon\www\cakra
```

| ID | File / Folder | Fungsi | Status |
|---|---|---|---|
| BE-001 | `app/Config/` | Konfigurasi utama CI4 | NOT_INSPECTED |
| BE-002 | `app/Config/Routes.php` | Routing statis dan konfigurasi routing | NOT_INSPECTED |
| BE-003 | `app/Config/Autoload.php` | Konfigurasi autoload | NOT_INSPECTED |
| BE-004 | `app/Config/Services.php` | Registrasi service | NOT_INSPECTED |
| BE-005 | `app/Config/Filters.php` | Konfigurasi filter | NOT_INSPECTED |
| BE-006 | `app/Config/Database.php` | Konfigurasi database | NOT_INSPECTED |
| BE-007 | `app/Core/` | Komponen inti aplikasi | NOT_INSPECTED |
| BE-008 | `app/Modules/` | PERLU VERIFIKASI: lokasi module jika digunakan | NOT_INSPECTED |
| BE-009 | `Modules/` | Lokasi module yang pernah digunakan | REVERIFY |
| BE-010 | `app/Database/Migrations/` | Migration database | NOT_INSPECTED |
| BE-011 | `app/Config/Filters.php` | Filter dan middleware | NOT_INSPECTED |
| BE-012 | `composer.json` | Dependency PHP | NOT_INSPECTED |

Catatan: Berdasarkan pembahasan sebelumnya, module pernah menggunakan `ROOTPATH/Modules`. Jangan menganggap `app/Modules` sebagai lokasi aktual sebelum diperiksa.

# 4. DYNAMIC ROUTER REGISTRY

DynamicRouterBase adalah komponen penting dan termasuk area dengan risiko perubahan tinggi.

| ID | Komponen | Tanggung jawab | Status |
|---|---|---|---|
| DR-001 | DynamicRouterBase | Koordinasi routing dinamis | REVERIFY |
| DR-002 | RouterConfig | Konfigurasi routing | REVERIFY |
| DR-003 | ControllerScanner | Pencarian controller dan method | REVERIFY |
| DR-004 | MethodPolicy | Kebijakan akses method | REVERIFY |
| DR-005 | RouteResolver | Resolusi route | REVERIFY |
| DR-006 | AuthenticationResolver | Resolusi autentikasi | REVERIFY |
| DR-007 | AccessChecker | Pemeriksaan akses | REVERIFY |
| DR-008 | HttpVerbPolicy | Kebijakan HTTP verbs | REVERIFY |
| DR-009 | RateLimiter | Pembatasan request | REVERIFY |
| DR-010 | RouterResponseHandler | Penanganan response routing | REVERIFY |

Path setiap komponen harus diisi setelah source code diperiksa.

**Aturan khusus:**

- Jangan mengubah alur DynamicRouterBase tanpa memahami seluruh dependensinya.
- Jangan membuat routing paralel.
- Jangan memindahkan komponen tanpa persetujuan.
- Audit harus mempertimbangkan hubungan antarkomponen, bukan hanya satu file.

# 5. ROUTE REGISTRY

| ID | Komponen | Tanggung jawab | Status |
|---|---|---|---|
| RR-001 | RouteRegistry Controller | Endpoint pengelolaan registry | REVERIFY |
| RR-002 | RouteRegistryAccessService | Pemeriksaan akses registry | REVERIFY |
| RR-003 | Route Registry Model | Akses data registry | NOT_INSPECTED |
| RR-004 | Route Registry Migration | Struktur tabel registry | NOT_INSPECTED |
| RR-005 | Route Registry Verbs | Pengelolaan HTTP verbs | REVERIFY |
| RR-006 | Route Registry Menus | Pengelolaan menu dari registry | REVERIFY |

Tabel database yang diketahui:

- `route_registry`
- `route_registry_verbs`
- `route_registry_menus` (pernah dibahas; perlu verifikasi skema aktual)

Endpoint dasar yang pernah digunakan:

```text
/api/v1/route-registry
```

# 6. AUTHENTICATION & RBAC REGISTRY

| ID | Komponen | Tanggung jawab | Status |
|---|---|---|---|
| AUTH-001 | Shield integration | Autentikasi pengguna | REVERIFY |
| AUTH-002 | Auth API Controller | Login dan identitas pengguna | REVERIFY |
| AUTH-003 | Auth Store (Vue) | State autentikasi frontend | REVERIFY |
| RBAC-001 | RbacService | Koordinasi RBAC | REVERIFY |
| RBAC-002 | AccessControlService | Pemeriksaan permission | REVERIFY |
| RBAC-003 | RouteRegistryAccessService | Akses Route Registry | REVERIFY |
| RBAC-004 | Permission synchronization | Sinkronisasi permission | NOT_INSPECTED |
| RBAC-005 | Menu authorization | Penyaringan menu berdasarkan akses | REVERIFY |

**Aturan khusus:**

- Custom RBAC ACN ERP merupakan acuan utama.
- Jangan menjadikan Shield AuthGroups.php sebagai master RBAC.
- Jangan mengubah relasi group, user, dan permission tanpa pemeriksaan skema serta dependensi.

# 7. FRONTEND SOURCE REGISTRY

Root frontend:

```text
frontend/
```

| ID | File / Folder | Tanggung jawab | Status |
|---|---|---|---|
| FE-001 | `src/main.ts` | Bootstrap Vue | REVERIFY |
| FE-002 | `src/router/` | Routing frontend | REVERIFY |
| FE-003 | `src/modules/` | Module frontend | REVERIFY |
| FE-004 | `src/stores/auth.ts` | State autentikasi | REVERIFY |
| FE-005 | `src/stores/menu.ts` | State menu | REVERIFY |
| FE-006 | `src/stores/permission.ts` | State permission | NOT_INSPECTED |
| FE-007 | `src/api/client.ts` | API client | REVERIFY |
| FE-008 | `src/utils/axios.ts` | Konfigurasi Axios jika masih digunakan | REVERIFY |
| FE-009 | `src/types/` | TypeScript types | NOT_INSPECTED |
| FE-010 | `vite.config.ts` | Konfigurasi Vite | REVERIFY |
| FE-011 | `package.json` | Dependency frontend | REVERIFY |
| FE-012 | `tsconfig.json` | Konfigurasi TypeScript | NOT_INSPECTED |

Jika terdapat perbedaan antara `src/api/client.ts` dan `src/utils/axios.ts`, AI harus memeriksa mana yang benar-benar digunakan sebelum menyarankan perubahan.

# 8. UI & THEME REGISTRY

| ID | Komponen | Tanggung jawab | Status |
|---|---|---|---|
| UI-001 | Adminmart Modernize | UI theme | REVERIFY |
| UI-002 | Main Layout | Layout utama Vue | NOT_INSPECTED |
| UI-003 | Sidebar | Navigasi menu | REVERIFY |
| UI-004 | MenuItem | Render item navigasi | REVERIFY |
| UI-005 | Header | Header aplikasi | NOT_INSPECTED |
| UI-006 | Theme assets | CSS, JS, fonts, icons | REVERIFY |

Aturan:

- Pertahankan theme yang sudah digunakan.
- Hindari duplikasi fungsi theme.
- Jangan mengedit vendor assets jika solusi dapat dilakukan melalui konfigurasi atau integrasi aplikasi.
- Periksa dependensi CSS, JS, dan fonts sebelum mengubah path asset.

# 9. DATABASE REGISTRY

Database development yang diketahui:

```text
cakra
```

| ID | Area | Tanggung jawab | Status |
|---|---|---|---|
| DB-001 | Shield tables | Identitas dan autentikasi | REVERIFY |
| DB-002 | RBAC tables | Group dan permission | REVERIFY |
| DB-003 | route_registry | Master endpoint | REVERIFY |
| DB-004 | route_registry_verbs | HTTP verbs | REVERIFY |
| DB-005 | route_registry_menus | Relasi menu registry | NOT_INSPECTED |
| DB-006 | ERP tables | Data operasional ERP | NOT_INSPECTED |

Setiap perubahan database harus dicatat bersama migration terkait.

# 10. FILE INSPECTION RECORD

Setiap pemeriksaan file harus dicatat dengan format:

```text
ID:
File:
Path:
Version / Last Updated:
Date Inspected:
Inspector:
Purpose:
Important Classes / Methods:
Dependencies:
Findings:
Related Findings:
Status:
```

Contoh ilustratif:

```text
ID: DR-001
File: DynamicRouterBase.php
Path: [isi berdasarkan source aktual]
Date Inspected: YYYY-MM-DD
Purpose: Audit alur routing dinamis
Important Classes / Methods: [hasil pemeriksaan]
Dependencies: [hasil pemeriksaan]
Findings: FND-001, FND-002
Status: INSPECTED
```

Jangan mengisi informasi yang belum diketahui dengan asumsi.

# 11. FILE CHANGE RECORD

Setiap perubahan source code harus dicatat:

```text
Change ID:
Related Finding:
File:
Path:
Previous State:
New State:
Reason:
Approval:
Implementation Date:
Verification:
Status:
```

Contoh:

```text
Change ID: CHG-001
Related Finding: FND-001
File: [nama file]
Path: [path aktual]
Reason: [alasan perubahan]
Verification: PENDING
Status: MODIFIED
```

# 12. AI PRE-FLIGHT CHECK

Sebelum meminta source code kepada pengguna, AI wajib:

1. Memeriksa apakah file sudah pernah diberikan.
2. Memeriksa apakah file tersedia dalam Project Sources.
3. Memeriksa catatan percakapan terkait file tersebut.
4. Memeriksa apakah ada versi lebih baru.
5. Memastikan bagian file yang dibutuhkan belum tersedia.

Jika file sudah tersedia dan relevan, lanjutkan pekerjaan tanpa meminta ulang.

Jika file tidak tersedia, jelaskan secara spesifik:
- File apa yang dibutuhkan.
- Mengapa dibutuhkan.
- Bagian mana yang belum dapat diverifikasi.
- Apakah file sebelumnya tidak dapat diakses atau diduga sudah berubah.

Jangan meminta seluruh project jika hanya satu file yang dibutuhkan.

---

# 13. REGISTRY MAINTENANCE

Registry diperbarui ketika:

- File baru ditambahkan.
- File dipindahkan atau dihapus.
- Fungsi utama berubah.
- Hasil audit baru ditemukan.
- Perubahan kode disetujui.
- Status verifikasi berubah.
- Struktur module berubah.

**Prinsip:** SOURCE REGISTRY adalah indeks navigasi dan riwayat pemeriksaan source code ACN ERP, bukan daftar file yang dibuat berdasarkan asumsi.