<?php

declare(strict_types=1);

namespace App\Libraries;

use Config\Services;
use CodeIgniter\Session\Session;
use RuntimeException;
use Throwable;

final class MathCaptcha
{
    private const SESSION_RESULT   = 'math_captcha_result';
    private const SESSION_QUESTION = 'math_captcha_question';
    private const SESSION_FILE     = 'math_captcha_file';

    private const WIDTH          = 180;
    private const HEIGHT         = 50;
    private const FILE_LIFETIME  = 600;
    private const MAX_INPUT      = 3;

    private Session $session;
    private string $captchaPath;
    private string $fontPath;

    public function __construct(?Session $session = null)
    {
        $this->session = $session ?? Services::session();

        $this->captchaPath = WRITEPATH . 'captcha' . DIRECTORY_SEPARATOR;
        $this->fontPath = WRITEPATH . 'fonts'
            . DIRECTORY_SEPARATOR . 'Quicksand-Regular.ttf';

        if (! is_dir($this->captchaPath)) {
            if (! mkdir($this->captchaPath, 0750, true) && ! is_dir($this->captchaPath)) {
                throw new RuntimeException(
                    'Direktori CAPTCHA tidak dapat dibuat.'
                );
            }
        }

        if (! is_writable($this->captchaPath)) {
            throw new RuntimeException(
                'Direktori CAPTCHA tidak dapat ditulis.'
            );
        }
    }

    /**
     * Membuat soal matematika dan menyimpan jawaban di session.
     */
    public function generate(): string
    {
        $a = random_int(1, 9);
        $b = random_int(1, 9);
        $operator = random_int(0, 1) === 0 ? '+' : '-';

        if ($operator === '-') {
            // Hasil pengurangan selalu tidak negatif.
            if ($a < $b) {
                [$a, $b] = [$b, $a];
            }

            $result = $a - $b;
        } else {
            $result = $a + $b;
        }

        $question = "{$a} {$operator} {$b} =";

        $this->session->set([
            self::SESSION_RESULT   => (string) $result,
            self::SESSION_QUESTION => $question,
        ]);

        return $question;
    }

    /**
     * Mengambil soal yang sedang aktif.
     */
    public function getQuestion(): ?string
    {
        $question = $this->session->get(self::SESSION_QUESTION);

        return is_string($question) ? $question : null;
    }

    /**
     * Memvalidasi jawaban. Setiap jawaban hanya dapat digunakan sekali.
     */
    public function validate(mixed $input): bool
    {
        $expected = $this->session->get(self::SESSION_RESULT);

        // Hapus lebih dahulu agar jawaban tidak bisa dipakai ulang.
        $this->session->remove([
            self::SESSION_RESULT,
            self::SESSION_QUESTION,
        ]);

        if (
            $expected === null ||
            ! is_scalar($input) ||
            is_bool($input)
        ) {
            return false;
        }

        $answer = trim((string) $input);

        if (
            $answer === '' ||
            strlen($answer) > self::MAX_INPUT ||
            ! ctype_digit($answer)
        ) {
            return false;
        }

        return hash_equals((string) $expected, (string) (int) $answer);
    }

    /**
     * Membuat gambar CAPTCHA PNG dan mengembalikan nama filenya.
     */
    public function generateAndSave(): string
    {
        $this->removePreviousCaptcha();

        if (
            ! extension_loaded('gd') ||
            ! function_exists('imagettftext') ||
            ! function_exists('imagettfbbox')
        ) {
            throw new RuntimeException(
                'Ekstensi GD dengan dukungan FreeType wajib diaktifkan.'
            );
        }

        if (! is_file($this->fontPath) || ! is_readable($this->fontPath)) {
            throw new RuntimeException(
                'Font CAPTCHA tidak ditemukan atau tidak dapat dibaca.'
            );
        }

        $question = $this->generate();
        $image = null;
        $filePath = null;

        try {
            $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

            if ($image === false) {
                throw new RuntimeException(
                    'Gagal membuat gambar CAPTCHA.'
                );
            }

            $background = imagecolorallocate(
                $image,
                random_int(220, 255),
                random_int(220, 255),
                random_int(220, 255)
            );

            imagefilledrectangle(
                $image,
                0,
                0,
                self::WIDTH - 1,
                self::HEIGHT - 1,
                $background
            );

            // Noise ringan untuk mengurangi pembacaan otomatis sederhana.
            for ($i = 0; $i < 100; $i++) {
                $noise = imagecolorallocate(
                    $image,
                    random_int(150, 220),
                    random_int(150, 220),
                    random_int(150, 220)
                );

                imagesetpixel(
                    $image,
                    random_int(0, self::WIDTH - 1),
                    random_int(0, self::HEIGHT - 1),
                    $noise
                );
            }

            $textColor = imagecolorallocate(
                $image,
                random_int(0, 80),
                random_int(0, 80),
                random_int(0, 80)
            );

            $fontSize = 20;
            $angle = random_int(-4, 4);

            $bbox = imagettfbbox(
                $fontSize,
                $angle,
                $this->fontPath,
                $question
            );

            if ($bbox === false) {
                throw new RuntimeException(
                    'Gagal menghitung ukuran teks CAPTCHA.'
                );
            }

            $textWidth = $bbox[2] - $bbox[0];
            $textHeight = $bbox[1] - $bbox[7];

            $x = (int) max(
                5,
                (self::WIDTH - $textWidth) / 2 - $bbox[0]
            );

            $y = (int) max(
                $textHeight + 2,
                (self::HEIGHT + $textHeight) / 2 - $bbox[7]
            );

            $drawResult = imagettftext(
                $image,
                $fontSize,
                $angle,
                $x,
                $y,
                $textColor,
                $this->fontPath,
                $question
            );

            if ($drawResult === false) {
                throw new RuntimeException(
                    'Gagal menggambar teks CAPTCHA.'
                );
            }

            // Nama acak yang aman dan tidak memakai input pengguna.
            $filename = 'captcha_' . bin2hex(random_bytes(16)) . '.png';
            $filePath = $this->captchaPath . $filename;

            if (! imagepng($image, $filePath)) {
                throw new RuntimeException(
                    'Gagal menyimpan gambar CAPTCHA.'
                );
            }

            if (! chmod($filePath, 0640)) {
                @unlink($filePath);

                throw new RuntimeException(
                    'Gagal mengatur izin file CAPTCHA.'
                );
            }

            $this->session->set(self::SESSION_FILE, $filename);

            return $filename;
        } catch (Throwable $e) {
            $this->session->remove([
                self::SESSION_RESULT,
                self::SESSION_QUESTION,
                self::SESSION_FILE,
            ]);

            if ($filePath !== null && is_file($filePath)) {
                @unlink($filePath);
            }

            throw $e;
        } finally {
            if ($image !== null && $image !== false) {
                imagedestroy($image);
            }

            $this->cleanup();
        }
    }

    /**
     * Menghapus file CAPTCHA lama berdasarkan batas usia.
     */
    public function cleanup(): void
    {
        $files = glob($this->captchaPath . 'captcha_*.png');

        if ($files === false) {
            return;
        }

        $cutoff = time() - self::FILE_LIFETIME;

        foreach ($files as $file) {
            if (
                is_file($file) &&
                ! is_link($file) &&
                filemtime($file) !== false &&
                filemtime($file) < $cutoff
            ) {
                @unlink($file);
            }
        }
    }

    /**
     * Menghapus gambar CAPTCHA milik session saat ini.
     */
    public function removePreviousCaptcha(): void
    {
        $filename = $this->session->get(self::SESSION_FILE);

        $this->session->remove(self::SESSION_FILE);

        if (
            ! is_string($filename) ||
            ! preg_match('/\Acaptcha_[a-f0-9]{32}\.png\z/', $filename)
        ) {
            return;
        }

        $filePath = $this->captchaPath . $filename;

        if (is_file($filePath) && ! is_link($filePath)) {
            @unlink($filePath);
        }
    }
}
