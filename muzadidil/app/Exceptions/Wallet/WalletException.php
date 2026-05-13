<?php

namespace App\Exceptions\Wallet;

use App\Exceptions\KamusReportableException;
use App\Models\PartnerWallet;

class WalletException extends KamusReportableException
{
    protected string $domain = 'wallet';

    public static function insufficientBalance(int $required, int $available): self
    {
        return self::make(
            'insufficient_balance',
            'Saldo tidak cukup untuk mengambil order ini.',
            httpStatus: 422,
        )
            ->withContext(['required' => $required, 'available' => $available])
            ->withPublicData(['required' => $required, 'available' => $available]);
    }

    public static function exceedsMaxBalance(int $resultingBalance): self
    {
        return self::make(
            'exceeds_max_balance',
            'Saldo melebihi batas maksimum Rp '.number_format(PartnerWallet::MAX_BALANCE, 0, ',', '.').'.',
            httpStatus: 422,
        )
            ->withContext(['resulting_balance' => $resultingBalance, 'max' => PartnerWallet::MAX_BALANCE])
            ->withPublicData(['max_balance' => PartnerWallet::MAX_BALANCE]);
    }

    public static function topupAlreadyProcessed(string $status): self
    {
        return self::make(
            'topup_already_processed',
            'Permintaan top-up sudah diproses sebelumnya.',
            httpStatus: 409,
        )->withContext(['previous_status' => $status]);
    }

    public static function topupNotFound(): self
    {
        return self::make(
            'topup_not_found',
            'Permintaan top-up tidak ditemukan.',
            httpStatus: 404,
        );
    }

    public static function walletNotFound(): self
    {
        return self::make(
            'wallet_not_found',
            'Dompet mitra tidak ditemukan.',
            httpStatus: 404,
        );
    }
}
