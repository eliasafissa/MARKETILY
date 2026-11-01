<?php

namespace Tests\Unit;

use App\Services\Bep20DepositVerifier;
use PHPUnit\Framework\TestCase;

class Bep20ReceiptSafetyTest extends TestCase
{
    private function receipt(string $wallet, string $sender, string $contract, string $units, int $status = 1): array
    {
        $topic = fn (string $address) => '0x'.str_pad(strtolower(substr($address, 2)), 64, '0', STR_PAD_LEFT);
        return [
            'status' => $status ? '0x1' : '0x0', 'blockNumber' => '0x3e8',
            'logs' => [[
                'address' => $contract,
                'topics' => [
                    '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef',
                    $topic($sender), $topic($wallet),
                ],
                'data' => $units,
            ]],
        ];
    }

    public function test_exact_bep20_usdt_with_correct_sender_receiver_and_12_confirmations(): void
    {
        $from = '0x1111111111111111111111111111111111111111';
        $to = '0x2222222222222222222222222222222222222222';
        $contract = '0x55d398326f99059ff775485246999027b3197955';
        $receipt = $this->receipt($to, $from, $contract, '0x8ac7230489e80000'); // 10 * 10^18
        (new Bep20DepositVerifier())->validateReceipt($receipt, $to, $contract, $from, 10, 1011);
        $this->assertSame('10000000000000000000', Bep20DepositVerifier::hexToDecimal('0x8ac7230489e80000'));
    }

    public function test_wrong_amount_fails_closed(): void
    {
        $from = '0x1111111111111111111111111111111111111111';
        $to = '0x2222222222222222222222222222222222222222';
        $contract = '0x55d398326f99059ff775485246999027b3197955';
        $this->expectException(\DomainException::class);
        (new Bep20DepositVerifier())->validateReceipt($this->receipt($to, $from, $contract, '0x8ac7230489e80000'), $to, $contract, $from, 11, 1011);
    }

    public function test_insufficient_confirmations_fail_closed(): void
    {
        $from = '0x1111111111111111111111111111111111111111';
        $to = '0x2222222222222222222222222222222222222222';
        $contract = '0x55d398326f99059ff775485246999027b3197955';
        $this->expectException(\DomainException::class);
        (new Bep20DepositVerifier())->validateReceipt($this->receipt($to, $from, $contract, '0x8ac7230489e80000'), $to, $contract, $from, 10, 1010);
    }

    public function test_failed_receipt_fails_closed(): void
    {
        $from = '0x1111111111111111111111111111111111111111';
        $to = '0x2222222222222222222222222222222222222222';
        $contract = '0x55d398326f99059ff775485246999027b3197955';
        $this->expectException(\DomainException::class);
        (new Bep20DepositVerifier())->validateReceipt($this->receipt($to, $from, $contract, '0x8ac7230489e80000', 0), $to, $contract, $from, 10, 1011);
    }
}
