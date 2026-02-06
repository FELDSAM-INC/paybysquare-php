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
     * @var bool Allow skip the XZ binary path check
     */
    public bool $skipXzPathCheck = false;
    
    

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
        // If path is already set, return it
        if ($this->xzBinaryPath !== null) {
            // if allowed to skip
            if($this->skipXzPathCheck) { 
                return $this->xzBinaryPath;
            }
            // else check
            if (file_exists($this->xzBinaryPath) && is_executable($this->xzBinaryPath)) {
                return $this->xzBinaryPath;
            }
            throw new EncodingException("Configured XZ binary not found or not executable: {$this->xzBinaryPath}");
        }
        
        // Try to find XZ binary in common locations
        foreach ($this->commonXzPaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                $this->xzBinaryPath = $path;
                return $path;
            }
        }
        
        throw new EncodingException('XZ binary not found. Please install XZ or provide the path to the binary.');
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
}