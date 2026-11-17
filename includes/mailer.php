<?php
require_once __DIR__ . '/../config/mail_config.php';

function smtp_baca_baris($conn)
{
    $baris = '';
    while (!feof($conn)) {
        $chunk = fgets($conn, 515);
        if ($chunk === false) {
            break;
        }
        $baris .= $chunk;
        if (substr($baris, -2) === "\r\n" || substr($baris, -1) === "\n") {
            break;
        }
    }
    return rtrim($baris, "\r\n");
}

function smtp_tunggu_kode($conn, array $diharapkan)
{
    $baris = '';
    do {
        $baris    = smtp_baca_baris($conn);
        $kode     = (int) substr($baris, 0, 3);
        $pemisah  = $baris[3] ?? ' ';
    } while ($pemisah === '-' && $baris !== '');

    if (!in_array($kode, $diharapkan, true)) {
        throw new RuntimeException("Respons SMTP tidak sesuai: $baris");
    }
    return $baris;
}

function kirim_email(string $penerima, string $subjek, string $bodyHtml, string $bodyTeks = ''): array
{
    if (SMTP_USER === '' || SMTP_USER === 'pengirim@gmail.com' || SMTP_PASS === '' || SMTP_PASS === 'AAAA BBBB CCCC DDDD') {
        return ['success' => false, 'message' => 'Konfigurasi SMTP belum diisi di config/mail_config.php.'];
    }

    $errno  = 0;
    $errstr = '';
    $conn = @stream_socket_client(
        'tcp://' . SMTP_HOST . ':' . SMTP_PORT,
        $errno,
        $errstr,
        15
    );

    if (!$conn) {
        return ['success' => false, 'message' => "Tidak dapat terhubung ke server email: $errstr"];
    }

    try {
        smtp_tunggu_kode($conn, [220]);

        fwrite($conn, "EHLO " . SMTP_HOST . "\r\n");
        smtp_tunggu_kode($conn, [250]);

        fwrite($conn, "STARTTLS\r\n");
        smtp_tunggu_kode($conn, [220]);

        if (!stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('Gagal mengaktifkan enkripsi TLS ke server email.');
        }

        fwrite($conn, "EHLO " . SMTP_HOST . "\r\n");
        smtp_tunggu_kode($conn, [250]);

        fwrite($conn, "AUTH LOGIN\r\n");
        smtp_tunggu_kode($conn, [334]);
        fwrite($conn, base64_encode(SMTP_USER) . "\r\n");
        smtp_tunggu_kode($conn, [334]);
        fwrite($conn, base64_encode(SMTP_PASS) . "\r\n");
        smtp_tunggu_kode($conn, [235]);

        fwrite($conn, "MAIL FROM:<" . SMTP_USER . ">\r\n");
        smtp_tunggu_kode($conn, [250]);

        fwrite($conn, "RCPT TO:<" . $penerima . ">\r\n");
        smtp_tunggu_kode($conn, [250, 251]);

        fwrite($conn, "DATA\r\n");
        smtp_tunggu_kode($conn, [354]);

        if ($bodyTeks === '') {
            $bodyTeks = trim(preg_replace('/\s+/', ' ', strip_tags($bodyHtml)));
        }

        $boundary  = 'b' . uniqid('', true);
        $hostname  = preg_replace('/[^a-z0-9.\-]/i', '', SMTP_HOST);
        $messageId = '<' . time() . '.' . bin2hex(random_bytes(8)) . '@' . $hostname . '>';

        $headers = "From: " . SMTP_FROM_NAME . " <" . SMTP_USER . ">\r\n";
        $headers .= "To: <" . $penerima . ">\r\n";
        $headers .= "Reply-To: " . SMTP_FROM_NAME . " <" . SMTP_USER . ">\r\n";
        $headers .= "Return-Path: <" . SMTP_USER . ">\r\n";
        $headers .= "Subject: " . $subjek . "\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        $headers .= "Message-ID: " . $messageId . "\r\n";
        $headers .= "X-Mailer: BankSampah/PHP\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"" . $boundary . "\"\r\n";

        $pesan  = "--$boundary\r\n";
        $pesan .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $pesan .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $pesan .= $bodyTeks . "\r\n\r\n";
        $pesan .= "--$boundary\r\n";
        $pesan .= "Content-Type: text/html; charset=UTF-8\r\n";
        $pesan .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $pesan .= $bodyHtml . "\r\n\r\n";
        $pesan .= "--$boundary--\r\n";

        $pesan = str_replace(["\r\n", "\r"], "\n", $pesan);
        $pesan = str_replace("\n", "\r\n", $pesan);
        $pesan = preg_replace('/^\./m', '..', $pesan);

        fwrite($conn, $headers . "\r\n" . $pesan . "\r\n.\r\n");
        smtp_tunggu_kode($conn, [250]);

        fwrite($conn, "QUIT\r\n");
    } catch (RuntimeException $e) {
        @fclose($conn);
        return ['success' => false, 'message' => $e->getMessage()];
    }

    fclose($conn);
    return ['success' => true, 'message' => 'Email terkirim.'];
}