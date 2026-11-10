# CHANGELOG — ACN ERP

**Project:** ACN ERP  
**Document:** CHANGELOG.md  
**Purpose:** Mencatat riwayat perubahan source code, database, API, arsitektur, konfigurasi, dan modul bisnis.

---

# 1. CHANGELOG POLICY

Setiap perubahan yang berdampak terhadap sistem harus dicatat.

Perubahan yang wajib masuk changelog:

- Penambahan fitur.
- Perbaikan bug.
- Perubahan arsitektur.
- Perubahan database.
- Perubahan API.
- Perubahan authentication dan authorization.
- Perubahan routing.
- Perubahan frontend.
- Perubahan konfigurasi deployment.
- Penghapusan atau penghentian fitur.
- Perubahan dependency yang berdampak.

Perubahan kosmetik kecil yang tidak memengaruhi fungsi boleh digabung dalam satu catatan.

**Jangan mencatat perubahan yang belum benar-benar diterapkan sebagai perubahan selesai.**

# 2. CHANGE ID

Gunakan format:

```text
CHG-YYYY-NNN
```

Contoh:

```text
CHG-2026-001
CHG-2026-002
CHG-2026-003
```

Setiap perubahan memiliki ID unik.

Jika satu perubahan menyelesaikan temuan audit, cantumkan Finding ID terkait.

# 3. CHANGE CATEGORIES

| Category | Keterangan |
|---|---|
| FEATURE | Fitur baru |
| FIX | Perbaikan bug |
| SECURITY | Perubahan keamanan |
| REFACTOR | Restrukturisasi kode |
| ARCHITECTURE | Perubahan arsitektur |
| DATABASE | Perubahan database |
| API | Perubahan kontrak atau endpoint |
| FRONTEND | Perubahan Vue/UI |
| BACKEND | Perubahan CI4/backend |
| CONFIG | Perubahan konfigurasi |
| PERFORMANCE | Optimasi performa |
| DEPRECATED | Fitur dihentikan |
| DOCUMENTATION | Perubahan dokumentasi |

# 4. CHANGE STATUS

| Status | Arti |
|---|---|
| PROPOSED | Baru diusulkan |
| APPROVED | Disetujui pengguna |
| IN_PROGRESS | Sedang dikerjakan |
| IMPLEMENTED | Sudah diterapkan |
| VERIFIED | Sudah diuji dan berhasil |
| FAILED | Implementasi atau pengujian gagal |
| ROLLED_BACK | Perubahan dibatalkan |
| DEFERRED | Ditunda |

Status `VERIFIED` hanya boleh digunakan jika hasil pengujian benar-benar diketahui.

# 5. CHANGE RECORD TEMPLATE

Setiap perubahan menggunakan format berikut:

```text
CHANGE ID:
Date:
Title:
Category:
Module:
Related Finding:
Related Resolution:
Status:
Approval:

1. BACKGROUND
Mengapa perubahan diperlukan?

2. OBJECTIVE
Apa yang ingin dicapai?

3. FILES AFFECTED
Daftar file yang berubah.

4. DATABASE IMPACT
Apakah ada perubahan struktur atau data database?

5. API IMPACT
Apakah ada perubahan endpoint, payload, response, atau HTTP method?

6. AUTHORIZATION IMPACT
Apakah ada dampak terhadap authentication, RBAC, atau permission?

7. IMPLEMENTATION
Apa yang diubah?

8. COMPATIBILITY
Apakah perubahan kompatibel dengan implementasi sebelumnya?

9. TESTING
Pengujian yang dilakukan.

10. TEST RESULT
Hasil aktual pengujian.

11. REGRESSION RISK
Risiko terhadap fungsi lain.

12. NOTES
Catatan tambahan.
```

# 6. CHANGELOG ENTRIES

Catatan perubahan terbaru ditempatkan di bagian paling atas.

Format ringkas:

```text
## [CHG-2026-001] Judul Perubahan

- Date:
- Category:
- Module:
- Related Finding:
- Status:

### Changed
- File atau komponen yang diubah.

### Reason
- Alasan perubahan.

### Impact
- Dampak terhadap sistem.

### Verification
- Pengujian dan hasil aktual.
```

# 7. ARCHITECTURE DECISION HISTORY

Perubahan arsitektur harus memiliki catatan keputusan tersendiri.

Format:

```text
ADR ID:
Date:
Decision Title:
Status: PROPOSED / ACCEPTED / SUPERSEDED / REJECTED

Context:
Masalah atau kebutuhan yang melatarbelakangi keputusan.

Decision:
Keputusan yang disetujui.

Alternatives:
Alternatif yang dipertimbangkan.

Consequences:
Dampak dan konsekuensi.

Affected Components:
Komponen yang terdampak.

Related Changes:
CHANGE ID terkait.
```

Contoh kategori keputusan:

- Pemilihan session authentication.
- Mekanisme custom RBAC.
- DynamicRouterBase.
- Route Registry.
- Vue frontend architecture.
- API client.
- Database strategy.

Jangan mengubah keputusan yang telah disetujui secara diam-diam. Jika keputusan diganti, catat alasan dan hubungkan dengan ADR sebelumnya.

# 8. DATABASE CHANGE HISTORY

Perubahan database harus mencantumkan:

- Nama tabel.
- Nama kolom.
- Tipe perubahan.
- Migration yang digunakan.
- Dampak terhadap data lama.
- Dampak terhadap relasi.
- Strategi rollback.
- Hasil pengujian.

Contoh:

```text
Database Change:
Table:
Migration:
Operation:
Affected Relations:
Data Migration:
Rollback Plan:
Verification:
```

Dilarang mencatat perubahan database sebagai selesai jika migration belum benar-benar diterapkan.

# 9. API CHANGE HISTORY

Perubahan API harus mencatat:

- Endpoint.
- HTTP method.
- Request payload.
- Response format.
- Authentication requirement.
- Permission requirement.
- Compatibility.
- Frontend dependency.

Perubahan kontrak API harus dianalisis terhadap seluruh client yang menggunakannya.

Jangan mengubah URL atau format response hanya untuk menyesuaikan satu komponen frontend tanpa analisis dampak.

# 10. RELEASE HISTORY

Jika sistem memiliki versi release, gunakan format:

```text
Version:
Release Date:
Release Scope:
Included Changes:
Known Issues:
Verification:
Deployment Status:
```

Versi release tidak boleh dibuat berdasarkan perkiraan.

# 11. ROLLBACK RECORD

Jika perubahan dibatalkan, catat:

```text
Rollback ID:
Related Change ID:
Date:
Reason:
Files Reverted:
Database Reverted:
API Impact:
Data Impact:
Verification:
Final Status:
```

Rollback database harus mempertimbangkan kemungkinan kehilangan atau perubahan data.

# 12. CHANGE RELATIONSHIP

Satu pekerjaan dapat menghubungkan beberapa catatan:

```text
FND-2026-001
    |
    v
RES-2026-001
    |
    v
CHG-2026-001
    |
    v
VERIFICATION
```

Dengan demikian, setiap perubahan dapat ditelusuri dari masalah awal sampai hasil pengujiannya.

# 13. AI CHANGE CONTROL

AI wajib:

1. Membaca changelog sebelum mengusulkan perubahan pada komponen yang pernah dimodifikasi.
2. Memeriksa apakah perubahan serupa sudah pernah dilakukan.
3. Tidak mengulang perubahan yang sudah diterapkan.
4. Tidak menganggap usulan sebagai implementasi.
5. Memperbarui changelog setelah pengguna mengonfirmasi perubahan telah diterapkan.
6. Memisahkan perubahan yang diusulkan dan yang benar-benar dilakukan.
7. Menyebutkan file yang berubah.
8. Menghubungkan perubahan dengan Finding ID jika relevan.
9. Memperbarui status setelah hasil pengujian diterima.
10. Mencatat keputusan arsitektur yang memiliki dampak jangka panjang.

# 14. INITIAL CHANGELOG

Dokumen ini dimulai sebagai catatan kosong.

Jangan membuat riwayat perubahan fiktif dari percakapan lama.

Jika riwayat perubahan lama berhasil direkonstruksi dari bukti percakapan atau source code, tandai sebagai:

`RECONSTRUCTED — NOT FULLY VERIFIED`

---

# 15. CORE PRINCIPLE

**Setiap perubahan harus memiliki alasan, ruang lingkup, dampak, dan status yang jelas.**

Changelog bukan sekadar daftar file yang diedit. Changelog adalah riwayat evolusi teknis ACN ERP.