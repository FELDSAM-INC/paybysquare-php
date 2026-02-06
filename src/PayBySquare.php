<?php

namespace PayBySquare;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Endroid\QrCode\Writer\WriterInterface;
use Endroid\QrCode\Color\Color;
use PayBySquare\Models\Payment;
use PayBySquare\Models\BankAccount;
use PayBySquare\Exceptions\EncodingException;

/**
 * Main class for generating PAY by square QR codes
 */
class PayBySquare
{
    /**
     * PNG format constant
     */
    public const FORMAT_PNG = 'png';
    
    /**
     * SVG format constant
     */
    public const FORMAT_SVG = 'svg';
    
    /**
     * @var Encoder
     */
    private Encoder $encoder;
    
    /**
     * @var Payment|null
     */
    private ?Payment $payment = null;
    
    /**
     * PayBySquare constructor
     * 
     * @param array|null $paymentAttributes Payment attributes array with the following keys:
     *                                     - amount: (float) The payment amount
     *                                     - currencyCode: (string) The currency code (ISO 4217)
     *                                     - iban: (string) The IBAN code
     *                                     - bic: (string|null) The BIC/SWIFT code (optional)
     *                                     - variableSymbol: (string|null) The variable symbol (optional)
     *                                     - constantSymbol: (string|null) The constant symbol (optional)
     *                                     - specificSymbol: (string|null) The specific symbol (optional)
     *                                     - paymentNote: (string|null) The payment note (optional)
     *                                     - paymentDueDate: (string|null) The payment due date in YYYY-MM-DD format (optional)
     *                                     - beneficiaryName: (string|null) The beneficiary name (optional)
     *                                     - beneficiaryAddressLine1: (string|null) The beneficiary address line 1 (optional)
     *                                     - beneficiaryAddressLine2: (string|null) The beneficiary address line 2 (optional)
     * @param string|null $xzBinaryPath Path to the XZ binary (optional)
     */
    public function __construct(?array $paymentAttributes = null, ?string $xzBinaryPath = null)
    {
        $this->encoder = new Encoder($xzBinaryPath);
        
        if ($paymentAttributes !== null) {
            // Create a Payment object from the attributes
            if (!isset($paymentAttributes['amount']) || !isset($paymentAttributes['currencyCode']) || !isset($paymentAttributes['iban'])) {
                throw new \InvalidArgumentException('Payment attributes must include amount, currencyCode, and iban');
            }
            
            $payment = new Payment();
            $payment->setAmount($paymentAttributes['amount']);
            $payment->setCurrencyCode($paymentAttributes['currencyCode']);
            
            $bankAccount = new BankAccount(
                $paymentAttributes['iban'],
                $paymentAttributes['bic'] ?? null
            );
            $payment->addBankAccount($bankAccount);
            
            // Set optional attributes if provided
            if (isset($paymentAttributes['variableSymbol'])) {
                $payment->setVariableSymbol($paymentAttributes['variableSymbol']);
            }
            
            if (isset($paymentAttributes['constantSymbol'])) {
                $payment->setConstantSymbol($paymentAttributes['constantSymbol']);
            }
            
            if (isset($paymentAttributes['specificSymbol'])) {
                $payment->setSpecificSymbol($paymentAttributes['specificSymbol']);
            }
            
            if (isset($paymentAttributes['paymentNote'])) {
                $payment->setPaymentNote($paymentAttributes['paymentNote']);
            }
            
            if (isset($paymentAttributes['paymentDueDate'])) {
                $payment->setPaymentDueDate($paymentAttributes['paymentDueDate']);
            }
            
            // Set beneficiary information if provided
            if (isset($paymentAttributes['beneficiaryName'])) {
                $payment->setBeneficiaryName($paymentAttributes['beneficiaryName']);
                
                if (isset($paymentAttributes['beneficiaryAddressLine1'])) {
                    $payment->setBeneficiaryAddressLine1($paymentAttributes['beneficiaryAddressLine1']);
                }
                
                if (isset($paymentAttributes['beneficiaryAddressLine2'])) {
                    $payment->setBeneficiaryAddressLine2($paymentAttributes['beneficiaryAddressLine2']);
                }
            }
            
            $this->payment = $payment;
        }
    }
    

    /**
     * Allow skip of checking the Xz utility path
     * 
     * @param bool $skip
     * @return bool
     */
    public function skipXzPathCheck(?bool $skip = true) :bool
    {
        $this->encoder->skipXzPathCheck = $skip;
        return $this->encoder->skipXzPathCheck ;
    }
    

    /**
     * Create a new Payment object
     *
     * @param float $amount Payment amount
     * @param string $currencyCode Currency code (ISO 4217)
     * @param string $iban IBAN code
     * @param string|null $bic BIC/SWIFT code
     * @return Payment
     */
    public function createPayment(float $amount, string $currencyCode, string $iban, ?string $bic = null): Payment
    {
        $payment = new Payment();
        $payment->setAmount($amount);
        $payment->setCurrencyCode($currencyCode);
        
        $bankAccount = new BankAccount($iban, $bic);
        $payment->addBankAccount($bankAccount);
        
        return $payment;
    }
    
    /**
     * Generate a PAY by square QR code
     *
     * @param array $options Additional options for QR code generation
     * @return string The QR code image data (PNG format)
     * @throws \InvalidArgumentException If no payment is set
     */
    public function generateQrCode(array $options = []): string
    {
        if ($this->payment === null) {
            throw new \InvalidArgumentException('No payment data set');
        }
        
        // Generate the PAY by square data string
        $payBySquareData = $this->encoder->encode($this->payment);
        
        // Generate QR code using endroid/qr-code
        $result = Builder::create()
            ->writer(new PngWriter())
            ->writerOptions([])
            ->data($payBySquareData)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(new ErrorCorrectionLevelHigh())
            ->size(isset($options['size']) ? $options['size'] : 300)
            ->margin(isset($options['margin']) ? $options['margin'] : 10)
            ->roundBlockSizeMode(new RoundBlockSizeModeMargin())
            ->build();
            
        return $result->getString();
    }
    
    /**
     * Save the QR code to a file
     *
     * @param string $filename The filename to save the QR code to
     * @param array $options Additional options for QR code generation
     * @return bool True if the file was saved successfully
     * @throws \InvalidArgumentException If no payment is set
     */
    public function saveQrCode(string $filename, array $options = []): bool
    {
        $qrCode = $this->generateQrCode($options);
        return file_put_contents($filename, $qrCode) !== false;
    }
    
    /**
     * Set the payment data
     * 
     * @param Payment $payment The payment data
     * @return self
     */
    public function setPayment(Payment $payment): self
    {
        $this->payment = $payment;
        return $this;
    }
    
    /**
     * Get the payment data
     * 
     * @return Payment|null
     */
    public function getPayment(): ?Payment
    {
        return $this->payment;
    }
    
    /**
     * Get the PAY by square data string without generating a QR code
     *
     * @param Payment|null $payment The payment data (optional if set in constructor)
     * @return string The PAY by square data string
     * @throws \InvalidArgumentException If no payment is provided
     */
    public function getPayBySquareData(?Payment $payment = null): string
    {
        $payment = $payment ?? $this->payment;
        
        if ($payment === null) {
            throw new \InvalidArgumentException('No payment data provided');
        }
        
        return $this->encoder->encode($payment);
    }
    
    /**
     * Convert to PAY by square data string
     * 
     * @return string
     * @throws \InvalidArgumentException If no payment is set
     */
    public function __toString(): string
    {
        return $this->getPayBySquareData();
    }
    
    /**
     * Get QR code as HTML tag or data URI
     *
     * @param bool $htmlTag Whether to return as HTML tag or data URI
     * @param int $size QR code size in pixels
     * @param int $margin QR code margin in pixels
     * @param array $options Additional options (foregroundColor, backgroundColor)
     * @return string HTML tag or data URI
     * @throws \InvalidArgumentException If no payment is set
     */
    public function getQRCodeImage(bool $htmlTag = true, int $size = 300, int $margin = 10, array $options = []): string
    {
        $data = $this->getDataUri($size, $margin, $options);

        return $htmlTag
            ? sprintf('<img src="%s" width="%2$d" height="%2$d" alt="QR Platba" />', $data, $size)
            : $data;
    }

    /**
     * Get QR code as data URI
     *
     * @param int $size QR code size in pixels
     * @param int $margin QR code margin in pixels
     * @param array $options Additional options (foregroundColor, backgroundColor)
     * @return string Data URI
     * @throws \InvalidArgumentException If no payment is set
     */
    public function getDataUri(int $size = 300, int $margin = 10, array $options = []): string
    {
        if ($this->payment === null) {
            throw new \InvalidArgumentException('No payment data set');
        }
        
        $builder = $this->createQrCodeBuilder(
            new PngWriter(),
            $size,
            $margin,
            $options
        );
        
        $result = $builder->build();
        return $result->getDataUri();
    }

    /**
     * Save QR code to file in specified format
     *
     * @param string|null $filename Filename to save to
     * @param string|null $format File format (png or svg)
     * @param int $size QR code size in pixels
     * @param int $margin QR code margin in pixels
     * @param array $options Additional options (foregroundColor, backgroundColor)
     * @return self
     * @throws EncodingException
     * @throws \InvalidArgumentException If no payment is set
     */
    public function saveQRCodeImage(?string $filename = null, ?string $format = 'png', int $size = 300, int $margin = 10, array $options = []): self
    {
        switch ($format) {
            case self::FORMAT_PNG:
                $writer = new PngWriter();
                break;
            case self::FORMAT_SVG:
                $writer = new SvgWriter();
                break;
            default:
                throw new EncodingException('Unknown file format');
        }
        
        $builder = $this->createQrCodeBuilder(
            $writer,
            $size,
            $margin,
            $options
        );
        
        $result = $builder->build();
        $result->saveToFile($filename);

        return $this;
    }
    
    /**
     * Create and configure a QR code builder
     *
     * @param WriterInterface $writer The writer to use
     * @param int $size QR code size in pixels
     * @param int $margin QR code margin in pixels
     * @param array $options Additional options (foregroundColor, backgroundColor)
     * @return Builder The configured builder
     */
    private function createQrCodeBuilder(WriterInterface $writer, int $size, int $margin, array $options = []): Builder
    {
        $payBySquareData = $this->getPayBySquareData();
        
        $builder = Builder::create()
            ->writer($writer)
            ->writerOptions([])
            ->data($payBySquareData)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(new ErrorCorrectionLevelHigh())
            ->size($size)
            ->margin($margin)
            ->roundBlockSizeMode(new RoundBlockSizeModeMargin());
            
        // Set foreground color if provided
        if (isset($options['foregroundColor'])) {
            $builder->foregroundColor($options['foregroundColor']);
        } else {
            $builder->foregroundColor(new Color(0, 0, 0, 0)); // Default black
        }
        
        // Set background color if provided
        if (isset($options['backgroundColor'])) {
            $builder->backgroundColor($options['backgroundColor']);
        } else {
            $builder->backgroundColor(new Color(255, 255, 255, 0)); // Default white
        }
        
        return $builder;
    }
}