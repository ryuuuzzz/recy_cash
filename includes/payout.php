<?php
/**
 * Helper pembayaran otomatis (disbursement) untuk penarikan saldo nasabah.
 * Mendukung provider "xendit"; jika provider belum dikonfigurasi, fungsi
 * mengembalikan mode manual sehingga petugas membayar sendiri.
 */

require_once __DIR__ . '/../config/payment_config.php';

function payoutGatewayAktif(): bool {
    return PAYOUT_PROVIDER !== '' && PAYOUT_PROVIDER === 'xendit' && PAYOUT_XENDIT_SECRET !== '';
}

function payoutTipeDidukung(string $tipe): bool {
    // Voucher/tukar poin dan tunai tidak melalui gateway
    return in_array($tipe, ['e-wallet', 'bank_transfer'], true);
}

/**
 * Kirim payout ke penyedia gateway.
 * $w = baris withdrawals (harus memuat nominal, detail_tujuan, payout_bank_code,
 *      payout_account_number, payout_beneficiary_name, tipe_penarikan, id).
 * Return: ['ok' => bool, 'ref' => string, 'status' => string, 'message' => string, 'raw' => string]
 */
function payoutKirim(array $w): array {
    if (!payoutGatewayAktif()) {
        return ['ok' => false, 'ref' => '', 'status' => 'manual', 'message' => 'Payment gateway belum dikonfigurasi (config/payment_config.php).', 'raw' => ''];
    }

    if (!payoutTipeDidukung((string) $w['tipe_penarikan'])) {
        return ['ok' => false, 'ref' => '', 'status' => 'manual', 'message' => 'Metode penarikan tidak didukung gateway.', 'raw' => ''];
    }

    $bankCode = strtoupper(trim((string) ($w['payout_bank_code'] ?? '')));
    $account  = trim((string) ($w['payout_account_number'] ?? ''));
    $name     = trim((string) ($w['payout_beneficiary_name'] ?? ''));
    $amount   = (float) $w['nominal'];

    if ($bankCode === '' || $account === '' || $name === '' || $amount <= 0) {
        return ['ok' => false, 'ref' => '', 'status' => 'manual', 'message' => 'Data tujuan payout belum lengkap (kode bank, nomor rekening, nama penerima).', 'raw' => ''];
    }

    $externalId = 'PNK-' . (int) $w['id'] . '-' . time();

    // ---- Provider: Xendit Disbursement ----
    if (PAYOUT_PROVIDER === 'xendit') {
        $payload = json_encode([
            'external_id'          => $externalId,
            'bank_code'            => $bankCode,
            'account_holder_name'  => $name,
            'account_number'       => $account,
            'amount'               => (int) $amount,
            'description'          => 'Penarikan saldo RecyCash PNK-' . (int) $w['id'],
        ]);

        $ch = curl_init('https://api.xendit.co/disbursements');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_USERPWD        => PAYOUT_XENDIT_SECRET . ':',
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_TIMEOUT        => 30,
        ]);
        $res  = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($res === false) {
            return ['ok' => false, 'ref' => '', 'status' => 'gagal', 'message' => 'Gagal terhubung ke Xendit: ' . $err, 'raw' => ''];
        }

        $data = json_decode($res, true) ?: [];

        if ($code >= 200 && $code < 300 && !empty($data['id'])) {
            $status = strtolower((string) ($data['status'] ?? 'pending'));
            // Xendit mengembalikan status "pending" saat disbursement baru dibuat
            // (diproses asinkron). Ini tetap sukses karena disbursement sudah
            // tercatat di gateway. Status final (completed/failed) datang via
            // webhook atau pengecekan manual.
            $berhasil = in_array($status, ['pending', 'completed', 'paid', 'settled'], true);
            return [
                'ok'      => $berhasil,
                'ref'     => (string) $data['id'],
                'status'  => $status,
                'message' => 'Payout terkirim ke gateway (status: ' . $status . ').',
                'raw'     => $res,
            ];
        }

        $msg = $data['message'] ?? ($data['error_code'] ?? 'HTTP ' . $code);
        return ['ok' => false, 'ref' => '', 'status' => 'gagal', 'message' => 'Gateway menolak: ' . $msg, 'raw' => $res];
    }

    return ['ok' => false, 'ref' => '', 'status' => 'manual', 'message' => 'Provider tidak dikenal.', 'raw' => ''];
}
