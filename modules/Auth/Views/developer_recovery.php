<?php
$otpConfigured = (bool) ($otpConfigured ?? false);
$setupSecret = (string) ($setupSecret ?? '');
$setupUri = (string) ($setupUri ?? '');
?>

<div class="container-fluid py-3">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1">Developer Recovery</h4>
            <p class="text-muted mb-0">
                Akses pemulihan sementara dengan verifikasi OTP.
            </p>
        </div>
    </div>

    <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success">
            <?= esc(session()->getFlashdata('success')) ?>
        </div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger">
            <?= esc(session()->getFlashdata('error')) ?>
        </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">1. Pengaturan OTP</h5>

            <?php if ($otpConfigured): ?>
                <div class="alert alert-success mb-0">
                    <strong>OTP sudah terdaftar.</strong>
                    <p class="mb-0">
                        Gunakan kode 6 digit terbaru dari aplikasi authenticator
                        untuk mengaktifkan Developer Recovery.
                    </p>
                </div>

            <?php elseif ($setupSecret !== '' && $setupUri !== ''): ?>
                <p>
                    Buka Google Authenticator atau Aegis, lalu pindai QR Code
                    berikut. Jika tidak bisa memindai, masukkan kunci setup
                    secara manual.
                </p>

                <div class="row align-items-start">
                    <div class="col-md-5 mb-3">
                        <div id="developerRecoveryQr"
                            class="border rounded p-3 d-inline-block bg-white"></div>

                        <div id="developerRecoveryQrError"
                            class="text-danger small mt-2"
                            style="display:none">
                            QR Code tidak dapat dibuat. Gunakan kunci manual.
                        </div>
                    </div>

                    <div class="col-md-7 mb-3">
                        <label class="form-label">Kunci setup manual</label>
                        <div class="input-group mb-2">
                            <input
                                type="text"
                                id="developerRecoverySecret"
                                class="form-control"
                                value="<?= esc($setupSecret) ?>"
                                readonly
                                autocomplete="off">
                            <button
                                type="button"
                                class="btn btn-outline-secondary"
                                onclick="copyDeveloperRecoverySecret()">
                                Salin
                            </button>
                        </div>

                        <div class="alert alert-warning">
                            Jangan bagikan QR Code atau kunci ini kepada siapa pun.
                            Simpan di authenticator sebelum melanjutkan.
                        </div>
                    </div>
                </div>

                <hr>

                <h6>2. Verifikasi setup</h6>
                <p class="text-muted">
                    Masukkan kode 6 digit dari authenticator untuk mengonfirmasi
                    bahwa setup berhasil.
                </p>

                <form method="post"
                    action="<?= site_url('auth/developer-recovery/verify-otp') ?>">
                    <?= csrf_field() ?>

                    <div class="mb-3" style="max-width: 320px">
                        <label for="setupOtp" class="form-label">Kode OTP</label>
                        <input
                            type="text"
                            name="otp"
                            id="setupOtp"
                            class="form-control"
                            inputmode="numeric"
                            pattern="[0-9]{6}"
                            minlength="6"
                            maxlength="6"
                            autocomplete="one-time-code"
                            required>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Verifikasi dan Simpan OTP
                    </button>
                </form>

            <?php else: ?>
                <p class="text-muted">
                    OTP belum terdaftar. Buat secret setup untuk menampilkan QR Code.
                </p>

                <form method="post"
                    action="<?= site_url('auth/developer-recovery/setup-otp') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary">
                        Setup OTP
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($otpConfigured): ?>
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">3. Aktivasi Developer Recovery</h5>
                <p class="text-muted">
                    Setelah aktivasi berhasil, akses pemulihan berlaku maksimal
                    30 menit. Gunakan hanya saat diperlukan.
                </p>

                <form method="post"
                    action="<?= site_url('auth/developer-recovery/activate') ?>">
                    <?= csrf_field() ?>

                    <div class="mb-3" style="max-width: 320px">
                        <label for="activationOtp" class="form-label">
                            Kode OTP
                        </label>
                        <input
                            type="text"
                            name="otp"
                            id="activationOtp"
                            class="form-control"
                            inputmode="numeric"
                            pattern="[0-9]{6}"
                            minlength="6"
                            maxlength="6"
                            autocomplete="one-time-code"
                            required>
                    </div>

                    <button type="submit" class="btn btn-warning">
                        Aktifkan Recovery 30 Menit
                    </button>
                </form>

                <hr>

                <h6>Nonaktifkan Recovery</h6>
                <form method="post"
                    action="<?= site_url('auth/developer-recovery/deactivate') ?>"
                    onsubmit="return confirm('Nonaktifkan Developer Recovery sekarang?')">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline-danger">
                        Nonaktifkan Sekarang
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if (! $otpConfigured && $setupSecret !== '' && $setupUri !== ''): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        (function() {
            const uri = <?= json_encode(
                            $setupUri,
                            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
                        ) ?>;

            function renderQr() {
                if (typeof QRCode === 'undefined') {
                    document.getElementById('developerRecoveryQrError')
                        .style.display = 'block';
                    return;
                }

                new QRCode(document.getElementById('developerRecoveryQr'), {
                    text: uri,
                    width: 220,
                    height: 220,
                    correctLevel: QRCode.CorrectLevel.M
                });
            }

            renderQr();
        })();

        function copyDeveloperRecoverySecret() {
            const input = document.getElementById('developerRecoverySecret');
            input.select();
            input.setSelectionRange(0, input.value.length);

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(input.value).catch(function() {
                    document.execCommand('copy');
                });
            } else {
                document.execCommand('copy');
            }
        }
    </script>
<?php endif; ?>
