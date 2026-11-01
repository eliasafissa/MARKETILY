<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Strict read-only chain verification: never handle private wallet keys. */
class Bep20DepositVerifier
{
    private const TRANSFER_TOPIC = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';
    private const RECOVER_PRECOMPILE = '0x0000000000000000000000000000000000000001';

    private function rpc(string $action, array $params): mixed
    {
        $url = (string) config('services.bsc.rpc_url');
        if (!str_starts_with($url, 'https://')) throw new RuntimeException('BSC RPC HTTPS URL is not configured.');
        $response = Http::timeout(12)->connectTimeout(4)->post($url, [
            'jsonrpc' => '2.0', 'id' => random_int(1, 999999), 'method' => $action, 'params' => $params,
        ]);
        if (!$response->successful() || !is_array($response->json()) || isset($response->json()['error'])) {
            throw new RuntimeException('BSC RPC could not verify transaction / wallet proof.');
        }
        return $response->json('result');
    }

    private function receipt(string $hash): array
    {
        $apiKey = (string) config('services.bsc.etherscan_key');
        if ($apiKey !== '') {
            try {
                $response = Http::timeout(12)->connectTimeout(4)->get('https://api.etherscan.io/v2/api', [
                    'chainid' => '56', 'module' => 'proxy', 'action' => 'eth_getTransactionReceipt',
                    'txhash' => $hash, 'apikey' => $apiKey,
                ]);
                $data = $response->json();
                if ($response->successful() && isset($data['result']) && is_array($data['result'])) return $data['result'];
            } catch (\Throwable $e) {
                // The independently configured BSC JSON-RPC remains the read-only fallback.
            }
        }
        $receipt = $this->rpc('eth_getTransactionReceipt', [$hash]);
        if (!is_array($receipt)) throw new \DomainException('Transaction is not confirmed on BSC yet.');
        return $receipt;
    }

    /** Wallet signature is over a server-issued, user+deposit+nonce-bound challenge. */
    public function verifyWalletSignature(string $wallet, string $signature, string $message): void
    {
        if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet) || !preg_match('/^0x[a-fA-F0-9]{130}$/', $signature)) {
            throw new \DomainException('Invalid wallet or wallet signature.');
        }
        // web3_sha3 is Keccak-256 (SHA3-256 is NOT interchangeable with Ethereum Keccak).
        $payload = "\x19Ethereum Signed Message:\n".strlen($message).$message;
        $digest = $this->rpc('web3_sha3', ['0x'.bin2hex($payload)]);
        if (!is_string($digest) || !preg_match('/^0x[0-9a-fA-F]{64}$/', $digest)) {
            throw new RuntimeException('BSC RPC does not support wallet signature verification.');
        }
        $r = substr($signature, 2, 64);
        $s = substr($signature, 66, 64);
        $v = hexdec(substr($signature, 130, 2));
        if ($v < 27) $v += 27;
        if (!in_array($v, [27, 28], true)) throw new \DomainException('Invalid signature recovery id.');
        $callData = '0x'.substr($digest, 2).str_pad(dechex($v), 64, '0', STR_PAD_LEFT).$r.$s;
        $recovered = $this->rpc('eth_call', [['to' => self::RECOVER_PRECOMPILE, 'data' => $callData], 'latest']);
        if (!is_string($recovered) || !preg_match('/^0x[0-9a-fA-F]{64}$/', $recovered) ||
            strtolower(substr($recovered, -40)) !== strtolower(substr($wallet, 2))) {
            throw new \DomainException('Wallet signature does not match sender address.');
        }
    }

    public static function hexToDecimal(string $hex): string
    {
        $hex = strtolower(preg_replace('/^0x/', '', $hex));
        if ($hex === '' || !ctype_xdigit($hex)) throw new \DomainException('Invalid ERC-20 amount encoding.');
        $dec = '0';
        foreach (str_split($hex) as $digit) {
            $carry = hexdec($digit);
            $output = '';
            for ($i = strlen($dec) - 1; $i >= 0; $i--) {
                $n = (int) $dec[$i] * 16 + $carry;
                $output = ($n % 10).$output;
                $carry = intdiv($n, 10);
            }
            while ($carry) { $output = ($carry % 10).$output; $carry = intdiv($carry, 10); }
            $dec = ltrim($output, '0') ?: '0';
        }
        return $dec;
    }

    /** Verify exact ERC-20 Transfer logs; tx.to may be the token contract and is not the receiver. */
    public function validateReceipt(array $receipt, string $destination, string $tokenContract, string $senderWallet, float $usdAmount, int $latestBlock, int $minConfirmations = 12): void
    {
        if (strtolower((string) ($receipt['status'] ?? '')) !== '0x1') throw new \DomainException('Transaction did not succeed.');
        $block = hexdec((string) ($receipt['blockNumber'] ?? '0x0'));
        if ($block < 1 || $latestBlock < $block || ($latestBlock - $block + 1) < $minConfirmations) {
            throw new \DomainException('Waiting for required BSC confirmations.');
        }
        $amountCents = (int) round($usdAmount * 100);
        if ($amountCents < 1) throw new \DomainException('Invalid transfer amount.');
        // USDT (18 decimals) => cents * 10^16; compare as exact base-unit integer, never float.
        $expectedUnits = $amountCents.'0000000000000000';
        $matched = 0;
        foreach (($receipt['logs'] ?? []) as $log) {
            if (strtolower((string) ($log['address'] ?? '')) !== strtolower($tokenContract)) continue;
            $topics = $log['topics'] ?? [];
            if (count($topics) !== 3 || strtolower((string) $topics[0]) !== self::TRANSFER_TOPIC) continue;
            $from = '0x'.substr(strtolower((string) $topics[1]), -40);
            $to = '0x'.substr(strtolower((string) $topics[2]), -40);
            if ($from !== strtolower($senderWallet) || $to !== strtolower($destination)) continue;
            if (self::hexToDecimal((string) ($log['data'] ?? '')) === $expectedUnits) $matched++;
        }
        if ($matched !== 1) throw new \DomainException('No unique exact USDT transfer from signed sender to merchant wallet.');
    }

    public function check(string $hash, string $wallet, string $signature, string $message, float $usdAmount): array
    {
        if (!preg_match('/^0x[a-fA-F0-9]{64}$/', $hash)) throw new \DomainException('Invalid BSC transaction hash.');
        $destination = (string) config('services.bsc.merchant_wallet');
        $contract = (string) config('services.bsc.usdt_contract');
        if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $destination) || !preg_match('/^0x[a-fA-F0-9]{40}$/', $contract)) {
            throw new \DomainException('USDT receiving wallet / contract is not configured.');
        }
        $this->verifyWalletSignature($wallet, $signature, $message);
        $receipt = $this->receipt($hash);
        if (strtolower((string) ($receipt['transactionHash'] ?? '')) !== strtolower($hash)) {
            throw new \DomainException('Receipt transaction hash mismatch.');
        }
        $latest = $this->rpc('eth_blockNumber', []);
        if (!is_string($latest) || !preg_match('/^0x[0-9a-fA-F]+$/', $latest)) throw new RuntimeException('Could not verify BSC block height.');
        $this->validateReceipt($receipt, $destination, $contract, $wallet, $usdAmount, (int) hexdec($latest));
        return ['tx_hash' => strtolower($hash), 'sender' => strtolower($wallet), 'block' => (int) hexdec($receipt['blockNumber'])];
    }
}
