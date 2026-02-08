<?php

namespace PayBySquare;

use PayBySquare\Models\Payment;
use PayBySquare\Exceptions\EncodingException;

/**
 * Class Encoder
 * Handles the encoding of payment data into PAY by square format
 */
class Encoder
{
    /**
     * @var string|null Path to the XZ binary
     */
    private ?string $xzBinaryPath = null;
    
    /**
     * @var array Common paths where XZ binary might be located
     */
    private array $commonXzPaths = [
        '/usr/bin/xz',
        '/usr/local/bin/xz',
        '/opt/homebrew/bin/xz',
        '/opt/local/bin/xz',
        '/bin/xz'
    ];
    
    /**
     * Constructor
     * 
     * @param string|null $xzBinaryPath Path to the XZ binary (optional)
     */
    public function __construct(?string $xzBinaryPath = null)
    {
        $this->xzBinaryPath = $xzBinaryPath;
    }
    /**
     * Encode payment data into PAY by square format
     *
     * @param Payment $payment The payment data
     * @return string The encoded PAY by square data
     * @throws EncodingException If encoding fails
     */
    public function encode(Payment $payment): string
    {
        // Generate tab-delimited string
        $tabDelimitedString = $payment->toTabDelimitedString();
        
        // Add CRC32B hash
        $dataWithHash = $this->addCrc32bHash($tabDelimitedString);
        
        // Compress with LZMA using XZ binary
        $compressed = $this->compressWithLzma($dataWithHash);
        
        // Convert to base-32
        $base32 = $this->convertToBase32($compressed, strlen($dataWithHash));
        
        return $base32;
    }
    
    /**
     * Calculate CRC32B hash from data
     * 
     * @param string $data The data to hash
     * @return string The hash
     */
    private function calculateCrc32bHash(string $data): string
    {
        return strrev(hash("crc32b", $data, TRUE));
    }

    /**
     * Add CRC32B hash to the data
     *
     * @param string $data The data to hash
     * @return string The data with hash prepended
     */
    private function addCrc32bHash(string $data): string
    {
        return  $this->calculateCrc32bHash($data) . $data;
    }
    
    /**
     * Compress data with LZMA using XZ binary
     *
     * @param string $data The data to compress
     * @return string The compressed data
     * @throws EncodingException If compression fails
     */
    private function compressWithLzma(string $data): string
    {
        $xzPath = $this->getXzBinaryPath();
        
        $descriptorSpec = [
            0 => ["pipe", "r"],  // stdin
            1 => ["pipe", "w"],  // stdout
            2 => ["pipe", "w"]   // stderr
        ];
        
        $process = proc_open(
            "$xzPath '--format=raw' '--lzma1=lc=3,lp=0,pb=2,dict=128KiB' '-c' '-'",
            $descriptorSpec,
            $pipes
        );
        
        if (!is_resource($process)) {
            throw new EncodingException('Failed to execute XZ binary. Make sure it is installed on the server.');
        }
        
        fwrite($pipes[0], $data);
        fclose($pipes[0]);
        
        $compressed = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        
        fclose($pipes[1]);
        fclose($pipes[2]);
        
        $exitCode = proc_close($process);
        
        if ($exitCode !== 0) {
            throw new EncodingException('XZ compression failed: ' . $error);
        }
        
        return $compressed;
    }
    
    /**
     * Get the path to the XZ binary
     * 
     * @return string Path to the XZ binary
     * @throws EncodingException If XZ binary is not found
     */
    private function getXzBinaryPath(): string
    {
        // 1) If path is already set, validate and return it
        if ($this->xzBinaryPath !== null) {
            $path = $this->xzBinaryPath;

            if ($this->isBinaryUsable($path)) {
                return $path;
            }

            throw new EncodingException("Configured XZ binary not found or not executable: {$path}");
        }

        // 2) Try to find XZ binary in common locations
        foreach ($this->commonXzPaths as $path) {
            if ($this->isBinaryUsable($path)) {
                $this->xzBinaryPath = $path;
                return $path;
            }
        }

        throw new EncodingException('XZ binary not found. Please install XZ or provide the path to the binary.');
    }

    /**
     * Checks whether the binary is usable.
     * - Prefer filesystem checks when allowed (fast).
     * - If open_basedir blocks them, fall back to actually running the binary.
     */
    private function isBinaryUsable(string $path): bool
    {
        $openBasedirBlocked = false;

        $prev = set_error_handler(function ($severity, $message) use (&$openBasedirBlocked) {
            if (stripos($message, 'open_basedir restriction') !== false) {
                $openBasedirBlocked = true;
                return true; // swallow the warning
            }
            return false; // let other warnings behave normally
        });

        try {
            // Fast path
            if (file_exists($path) && is_executable($path)) {
                return true;
            }
        } finally {
            restore_error_handler();
        }

        // Fallback path (only when the reason is open_basedir)
        if ($openBasedirBlocked) {
            return $this->isRunnableBinary($path);
        }

        return false;
    }
    
    /**
     * Convert compressed data to base-32 format
     *
     * @param string $data The compressed data
     * @param int $dataLength The length of data before compression
     * @return string The base-32 encoded data
     */
    private function convertToBase32(string $data, int $dataLength): string
    {
        // Convert to hex
        $hex = bin2hex("\x00\x00" . pack("v", $dataLength) . $data);
        
        // Convert hex to binary
        $binary = '';
        for ($i = 0; $i < strlen($hex); $i++) {
            $binary .= str_pad(base_convert($hex[$i], 16, 2), 4, "0", STR_PAD_LEFT);
        }
        
        // Pad binary to multiple of 5
        $length = strlen($binary);
        $remainder = $length % 5;
        if ($remainder > 0) {
            $padding = 5 - $remainder;
            $binary .= str_repeat("0", $padding);
            $length += $padding;
        }
        
        // Convert binary to base-32
        $base32Chars = "0123456789ABCDEFGHIJKLMNOPQRSTUV";
        $base32 = str_repeat("_", $length / 5);
        
        for ($i = 0; $i < $length / 5; $i++) {
            $chunk = substr($binary, $i * 5, 5);
            $value = bindec($chunk);
            $base32[$i] = $base32Chars[$value];
        }
        
        return $base32;
    }

    /**
     * Check if a binary is runnable
     *
     * @param string $path The path to the binary
     * @return bool True if the binary is runnable, false otherwise
     */
    private function isRunnableBinary(string $path): bool
    {
        // Avoid shell; pass argv array
        $cmd = [$path, '--version'];

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = @proc_open($cmd, $descriptors, $pipes);
        if (!\is_resource($proc)) {
            return false;
        }

        // No input needed
        fclose($pipes[0]);

        // Drain output (optional, but prevents pipe blocking in some cases)
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($proc);

        return $exitCode === 0;
}
}