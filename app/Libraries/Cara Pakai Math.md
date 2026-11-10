Cara memakai di controller login/register
Buat soal sekaligus gambar:
use App\Libraries\MathCaptcha;

public function index()
{
    $captcha = new MathCaptcha();

    $filename = $captcha->generateAndSave();

    return view('Modules\Auth\Views\login', [
        'captchaQuestion' => $captcha->getQuestion(),
        'captchaFilename' => $filename,
    ]);
}

Contoh field pada view:
<label for="captcha_answer">Jawaban CAPTCHA</label>

<p><?= esc($captchaQuestion ?? '') ?></p>

<input
    type="number"
    name="captcha_answer"
    id="captcha_answer"
    min="0"
    max="18"
    inputmode="numeric"
    required

Untuk validasi ketika form dikirim:
$captcha = new \App\Libraries\MathCaptcha();

if (! $captcha->validate($this->request->getPost('captcha_answer'))) {
    return redirect()->back()
        ->withInput()
        ->with('error', 'Jawaban CAPTCHA salah atau sudah kedaluwarsa.');
}





$throttler = \Config\Services::throttler();

$key = 'auth-login:' . hash(
    'sha256',
    $this->request->getIPAddress()
);

if (! $throttler->check($key, 5, MINUTE)) {
    return $this->response
        ->setStatusCode(429)
        ->setJSON([
            'status'  => false,
            'message' => 'Terlalu banyak percobaan. Silakan coba lagi nanti.',
        ]);
}
