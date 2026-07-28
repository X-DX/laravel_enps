<?php

namespace App\Support\Captcha;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Session;

/**
 * A small, self-contained image CAPTCHA (no external service, no font files),
 * mirroring the legacy numeric CAPTCHA but with modern styling. The generated
 * code is kept server-side in the session; only the image is sent to the browser.
 */
class CaptchaService
{
    private const SESSION_KEY = 'captcha';

    private const LENGTH = 5;

    private const TTL_SECONDS = 300;

    /** Characters without visually ambiguous glyphs (no 0/O, 1/I, etc.). */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * Generate a fresh code, remember it in the session, and return a PNG image.
     */
    public function generateImage(): Response
    {
        $code = $this->randomCode();

        Session::put(self::SESSION_KEY, [
            'code' => $code,
            'expires_at' => now()->addSeconds(self::TTL_SECONDS)->timestamp,
        ]);

        return response($this->render($code))
            ->header('Content-Type', 'image/png')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    /**
     * Verify user input against the session code (case-insensitive, time-limited).
     */
    public function verify(?string $input): bool
    {
        $captcha = Session::get(self::SESSION_KEY);

        if (! is_array($captcha) || blank($input)) {
            return false;
        }

        if (now()->timestamp > ($captcha['expires_at'] ?? 0)) {
            return false;
        }

        return hash_equals($captcha['code'], strtoupper(trim($input)));
    }

    /**
     * Invalidate the current code (called after a successful login).
     */
    public function forget(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    private function randomCode(): string
    {
        $code = '';
        $max = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }

        return $code;
    }

    private function render(string $code): string
    {
        // Draw on a small canvas, then scale up for chunkier, harder-to-OCR glyphs.
        $sw = 110;
        $sh = 30;
        $small = imagecreatetruecolor($sw, $sh);
        imagefilledrectangle($small, 0, 0, $sw, $sh, imagecolorallocate($small, 15, 23, 42));

        $font = 5;
        $x = 8;
        for ($i = 0, $n = strlen($code); $i < $n; $i++) {
            $glyph = imagecolorallocate($small, random_int(200, 255), random_int(200, 255), random_int(225, 255));
            $y = random_int(2, $sh - imagefontheight($font) - 2);
            imagestring($small, $font, $x, $y, $code[$i], $glyph);
            $x += imagefontwidth($font) + random_int(8, 12);
        }

        $w = 200;
        $h = 56;
        $img = imagecreatetruecolor($w, $h);
        imagecopyresampled($img, $small, 0, 0, 0, 0, $w, $h, $sw, $sh);
        imagedestroy($small);

        // Noise: wavy lines + speckle, drawn over the scaled glyphs.
        for ($i = 0; $i < 6; $i++) {
            $line = imagecolorallocate($img, random_int(70, 130), random_int(90, 150), random_int(120, 190));
            imageline($img, random_int(0, $w), random_int(0, $h), random_int(0, $w), random_int(0, $h), $line);
        }
        for ($i = 0; $i < 350; $i++) {
            $dot = imagecolorallocate($img, random_int(40, 90), random_int(50, 110), random_int(70, 140));
            imagesetpixel($img, random_int(0, $w), random_int(0, $h), $dot);
        }

        ob_start();
        imagepng($img);
        $data = ob_get_clean();
        imagedestroy($img);

        return $data;
    }
}
