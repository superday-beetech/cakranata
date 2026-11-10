# FINDING REGISTER — ACN ERP

**Project:** ACN ERP  
**Document:** FINDING_REGISTER.md  
**Purpose:** Pusat pencatatan temuan audit, defect, risiko teknis, dan penyelesaiannya.

---

# 1. OBJECTIVES

Finding Register digunakan untuk:

1. Mencatat seluruh temuan dari pemeriksaan source code.
2. Menghindari temuan yang sama dianalisis berulang kali.
3. Menunjukkan lokasi masalah secara presisi.
4. Menghubungkan setiap temuan dengan solusi.
5. Melacak status perbaikan.
6. Menyimpan riwayat keputusan teknis.
7. Memastikan perbaikan tidak merusak fungsi lain.

Setiap temuan wajib memiliki ID unik.

# 2. FINDING ID STANDARD

Gunakan format:

```text
FND-YYYY-NNN
```

Contoh:

```text
FND-2026-001
FND-2026-002
FND-2026-003
```

Ketentuan:

- ID tidak boleh digunakan ulang.
- Satu ID mewakili satu temuan.
- Temuan berbeda harus memiliki ID berbeda.
- Temuan yang sama pada file berbeda boleh dihubungkan sebagai satu root cause jika memang terbukti saling berkaitan.
- Jangan menggabungkan beberapa masalah yang tidak berkaitan hanya agar jumlah temuan lebih sedikit.

# 3. SEVERITY LEVEL

| Severity | Definisi | Contoh |
|---|---|---|
| CRITICAL | Kerusakan serius, celah keamanan berat, atau kehilangan data | Bypass otorisasi, penghapusan data tidak terkendali |
| HIGH | Fitur utama gagal atau terdapat risiko keamanan besar | RBAC tidak berjalan, autentikasi dapat dilewati |
| MEDIUM | Gangguan fungsional atau inkonsistensi yang berdampak nyata | Validasi tidak konsisten, request duplikat |
| LOW | Masalah terbatas dengan dampak kecil | Penanganan edge case minor |
| INFO | Catatan desain, potensi peningkatan, atau observasi | Duplikasi kode yang belum menimbulkan defect |

Severity harus didasarkan pada dampak dan kemungkinan terjadinya, bukan sekadar preferensi gaya coding.

# 4. FINDING STATUS

| Status | Arti |
|---|---|
| OPEN | Temuan baru dan belum diputuskan |
| CONFIRMED | Temuan telah dibuktikan |
| APPROVED | Solusi atau tindakan telah disetujui |
| IN_PROGRESS | Perbaikan sedang dilakukan |
| RESOLVED | Perubahan telah diterapkan |
| VERIFIED | Perbaikan telah diuji dan hasilnya sesuai |
| REJECTED | Temuan tidak valid atau tidak akan ditindaklanjuti |
| DEFERRED | Ditunda dengan alasan yang dicatat |

**Penting:**

- `CONFIRMED` berarti masalah terbukti ada.
- `RESOLVED` berarti kode sudah diubah.
- `VERIFIED` berarti hasil perubahan sudah diuji.
- Jangan menyamakan ketiganya.

# 5. REQUIRED FINDING FORMAT

Setiap temuan wajib menggunakan bahsa indonesia mengikuti format berikut:

```text
FINDING ID:
Title:
Date:
Module:
File:
Absolute / Relative Path:
Class:
Method:
Line Start:
Line End:
Code Version:
Severity:
Status:

1. OBSERVATION
Apa yang ditemukan?

2. EVIDENCE
Potongan source code yang membuktikan temuan.

3. EXPECTED BEHAVIOR
Perilaku yang seharusnya terjadi.

4. ACTUAL BEHAVIOR
Perilaku aktual atau kondisi yang teridentifikasi.

5. ROOT CAUSE
Penyebab masalah.

6. IMPACT
Dampak terhadap sistem, pengguna, data, keamanan, atau module lain.

7. DEPENDENCIES
Komponen lain yang berkaitan.

8. RECOMMENDED SOLUTION
Solusi yang disarankan.

9. REGRESSION RISK
Risiko terhadap fitur yang sudah berjalan.

10. VERIFICATION PLAN
Langkah untuk membuktikan solusi berhasil.

11. RELATED FINDINGS
Temuan lain yang terkait.

12. DECISION
Keputusan pengguna.

13. RESOLUTION ID
ID penyelesaian jika sudah diperbaiki.
```

# 6. LINE NUMBER ACCURACY

Ketentuan lokasi temuan:

1. Gunakan nomor baris dari source code yang benar-benar diperiksa.
2. Jangan menebak nomor baris berdasarkan ingatan.
3. Cantumkan rentang baris sekecil mungkin tetapi tetap mencakup konteks yang diperlukan.
4. Sertakan nama class dan method jika tersedia.
5. Sertakan potongan kode aktual.
6. Jika nomor baris belum diketahui, tulis `NOT_VERIFIED`.
7. Jika source code berubah, periksa ulang nomor baris.
8. Jangan menggunakan nomor baris dari versi file lama untuk menyatakan lokasi pada versi baru.

Format lokasi:

```text
File: DynamicRouterBase.php
Class: DynamicRouterBase
Method: resolve
Line: 120-138
```

Contoh tersebut hanya ilustrasi format, bukan hasil audit aktual.

# 7. EVIDENCE & CONFIDENCE

Setiap temuan harus dibedakan berdasarkan kekuatan bukti.

| Evidence Level | Keterangan |
|---|---|
| VERIFIED_SOURCE | Terbukti dari source code aktual |
| VERIFIED_RUNTIME | Terbukti melalui pengujian runtime |
| VERIFIED_DATABASE | Terbukti dari struktur atau data database |
| VERIFIED_LOG | Terbukti dari log aktual |
| INFERRED | Kesimpulan berdasarkan indikasi, belum dibuktikan |
| UNCONFIRMED | Belum cukup bukti |

AI dilarang menyajikan temuan `INFERRED` atau `UNCONFIRMED` sebagai defect yang sudah terbukti.

# 8. RESOLUTION REGISTER

Setiap solusi harus mempunyai ID tersendiri.

Format:

```text
RES-YYYY-NNN
```

Contoh:

```text
RES-2026-001
```

Template:

```text
RESOLUTION ID:
Related Finding:
Date:
File:
Path:
Class:
Method:
Original Line:
New Line:
Implementation Status:

1. PROBLEM
Ringkasan masalah yang diperbaiki.

2. CHANGE
Penjelasan perubahan.

3. ORIGINAL CODE
Kode sebelum perubahan.

4. REPLACEMENT CODE
Kode pengganti atau patch.

5. REASON
Alasan teknis perubahan.

6. COMPATIBILITY
Dampak terhadap API, database, routing, RBAC, dan module lain.

7. TEST CASE
Langkah pengujian.

8. TEST RESULT
Hasil aktual pengujian.

9. REGRESSION CHECK
Pemeriksaan fitur terkait.

10. FINAL STATUS
RESOLVED / VERIFIED / DEFERRED.
```

Setiap solusi harus mengacu kepada Finding ID yang jelas.

# 9. CHANGE SAFETY RULES

Sebelum melakukan perbaikan:

1. Pastikan temuan benar-benar valid.
2. Periksa alur pemanggilan terkait.
3. Periksa dependensi antarfile.
4. Identifikasi kontrak API yang terdampak.
5. Identifikasi dampak database.
6. Identifikasi dampak authentication dan RBAC.
7. Tentukan risiko regresi.
8. Pilih perubahan paling minimal.

Jangan melakukan refactor besar untuk memperbaiki defect kecil.

Perubahan arsitektur harus mendapatkan persetujuan pengguna terlebih dahulu.

# 10. DUPLICATE FINDING CONTROL

Sebelum membuat Finding ID baru:

1. Periksa Finding Register.
2. Cari apakah masalah serupa sudah tercatat.
3. Jika sama, tambahkan bukti atau informasi ke temuan lama.
4. Jika masalah berbeda, buat ID baru.
5. Jika satu masalah memiliki beberapa penyebab, catat hubungan antar-temuan.

Tujuannya menghindari duplikasi audit dan pekerjaan yang berulang.

# 11. AUDIT SUMMARY

Setiap sesi audit harus menghasilkan ringkasan:

```text
AUDIT SESSION:
Date:
Scope:
Files Inspected:
Files Not Inspected:

CRITICAL:
HIGH:
MEDIUM:
LOW:
INFO:

New Findings:
Existing Findings Updated:
Resolved:
Verified:
Deferred:

Overall Assessment:
Next Action:
```

AI tidak boleh menyatakan seluruh module aman jika pemeriksaan hanya mencakup sebagian file.

# 12. FINDING REGISTER TABLE

Daftar ringkas seluruh temuan:

| Finding ID | Module | File | Severity | Status | Resolution ID |
|---|---|---|---|---|---|
| FND-2026-001 | - | - | - | - | - |

Baris baru hanya ditambahkan ketika temuan aktual sudah ditemukan.

Jangan membuat temuan fiktif untuk mengisi tabel.

# 13. VERIFICATION POLICY

Perbaikan baru boleh berstatus `VERIFIED` apabila:

- Pengujian yang relevan sudah dilakukan.
- Hasil aktual sudah diketahui.
- Tidak ada kegagalan pada fungsi utama yang terdampak.
- Kontrak API tetap sesuai jika perubahan menyentuh API.
- Otorisasi tetap berjalan jika perubahan menyentuh RBAC.
- Database tetap konsisten jika perubahan menyentuh persistence.

Jika AI hanya memberikan instruksi pengujian kepada pengguna tetapi belum menerima hasilnya, status tetap `RESOLVED` atau `PENDING_VERIFICATION`.

# 14. FINAL AUDIT REPORT

Setiap audit besar harus dapat menghasilkan laporan:

1. Executive Summary.
2. Scope of Audit.
3. Files Inspected.
4. Architecture Observations.
5. Findings by Severity.
6. Root Cause Analysis.
7. Recommended Resolutions.
8. Regression Risk.
9. Verification Results.
10. Outstanding Issues.
11. Next Audit Scope.
 setiap detail menggunakan bahasa indonesia, untuk point tetap bahasa inggris
---

# 15. CORE PRINCIPLE

**Tidak ada temuan tanpa bukti.  
Tidak ada solusi tanpa referensi temuan.  
Tidak ada status VERIFIED tanpa pengujian.  
Tidak ada perubahan besar tanpa analisis dampak dan persetujuan.**

Finding Register adalah catatan teknis berkelanjutan untuk menjaga kualitas dan integritas ACN ERP.