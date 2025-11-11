# PayBySquare PHP

A PHP library for generating PAY by square payment QR codes for Slovak payment systems.

[![Latest Stable Version](https://img.shields.io/packagist/v/feldsam-inc/paybysquare-php.svg)](https://packagist.org/packages/feldsam-inc/paybysquare-php)
[![Total Downloads](https://img.shields.io/packagist/dt/feldsam-inc/paybysquare-php.svg)](https://packagist.org/packages/feldsam-inc/paybysquare-php)
[![License](https://img.shields.io/github/license/feldsam-inc/paybysquare-php)](https://github.com/feldsam-inc/paybysquare-php/blob/master/LICENSE)

### PHP Compatibility

[![PHP 7.4](https://github.com/feldsam-inc/paybysquare-php/actions/workflows/php74.yml/badge.svg)](https://github.com/feldsam-inc/paybysquare-php/actions/workflows/php74.yml)
[![PHP 8.0](https://github.com/feldsam-inc/paybysquare-php/actions/workflows/php80.yml/badge.svg)](https://github.com/feldsam-inc/paybysquare-php/actions/workflows/php80.yml)
[![PHP 8.1](https://github.com/feldsam-inc/paybysquare-php/actions/workflows/php81.yml/badge.svg)](https://github.com/feldsam-inc/paybysquare-php/actions/workflows/php81.yml)
[![PHP 8.2](https://github.com/feldsam-inc/paybysquare-php/actions/workflows/php82.yml/badge.svg)](https://github.com/feldsam-inc/paybysquare-php/actions/workflows/php82.yml)
[![PHP 8.3](https://github.com/feldsam-inc/paybysquare-php/actions/workflows/php83.yml/badge.svg)](https://github.com/feldsam-inc/paybysquare-php/actions/workflows/php83.yml)

## Requirements

- PHP 7.4 or higher
- XZ binary installed on the server (automatically detected in common locations or configurable)
- Composer

## Installation

```bash
composer require feldsam-inc/paybysquare-php
```

## Usage

### Basic Usage

```php
<?php

require 'vendor/autoload.php';

use PayBySquare\PayBySquare;

// Create a new PayBySquare instance with payment attributes
$payBySquare = new PayBySquare([
    'amount' => 15.00,
    'currencyCode' => 'EUR',
    'iban' => 'SK2483300000002403097934',
    'bic' => 'FIOZSKBAXXX',
    'paymentDueDate' => '20250301',  // Format: YYYYMMDD
    'constantSymbol' => '0308',
    'paymentNote' => 'MMSR-2025-V0SU9'
]);

// Generate QR code and save to file
$payBySquare->saveQrCode('payment-qr.png');

// Get the PAY by square data string (for custom QR code generation)
$payBySquareData = $payBySquare->getPayBySquareData();
echo $payBySquareData;

// You can also convert the PayBySquare object directly to a string
echo (string)$payBySquare; // Same as $payBySquare->getPayBySquareData()
```

### Advanced Usage

#### Multiple Bank Accounts

You can add multiple bank accounts to a single payment:

```php
<?php

require 'vendor/autoload.php';

use PayBySquare\PayBySquare;
use PayBySquare\Models\Payment;
use PayBySquare\Models\BankAccount;

// Create a new PayBySquare instance
$payBySquare = new PayBySquare();

// Create a payment manually
$payment = new Payment();
$payment->setAmount(15.00);
$payment->setCurrencyCode('EUR');
$payment->setPaymentDueDate('20250301');

// Add multiple bank accounts
$bankAccount1 = new BankAccount('SK2483300000002403097934', 'FIOZSKBAXXX');
$payment->addBankAccount($bankAccount1);

$bankAccount2 = new BankAccount('SK1234500000000000123456', 'TATRSKBXXXX');
$payment->addBankAccount($bankAccount2);

// Set the payment on the PayBySquare instance
$payBySquare->setPayment($payment);

// Generate QR code
$qrCodePng = $payBySquare->generateQrCode();
file_put_contents('payment-qr.png', $qrCodePng);
```

#### Beneficiary Information

You can include beneficiary information in the payment:

```php
// Set beneficiary information
$payment->setBeneficiaryName('Company Name Ltd.');
$payment->setBeneficiaryAddressLine1('Street Name 123');
$payment->setBeneficiaryAddressLine2('12345 City, Country');
```

#### QR Code Customization

You can customize the generated QR code with various options:

```php
// Generate QR code with custom options
$qrCodePng = $payBySquare->generateQrCode([
    'size' => 400,    // QR code size in pixels
    'margin' => 20,   // QR code margin in pixels
]);
```

#### Payment Symbols

Slovak payment systems use specific symbols for payment identification:

```php
// Set payment symbols
$payment->setVariableSymbol('1234567890');  // Variable symbol (up to 10 digits)
$payment->setConstantSymbol('0308');        // Constant symbol (up to 4 digits)
$payment->setSpecificSymbol('9876543210');  // Specific symbol (up to 10 digits)
```

#### Payment Reference

Alternatively, you can use a payment reference instead of symbols:

```php
// Set payment reference (up to 35 characters)
$payment->setReference('REF123456789');
```

**Note:** Payment reference and symbols (VS, KS, SS) are mutually exclusive. Setting a reference will clear any previously set symbols, and setting any symbol will clear the reference.

```php
// This will clear any previously set symbols
$payment->setReference('REF123456789');

// This will clear the reference
$payment->setVariableSymbol('1234567890');
```

**Bank Compatibility:** Some banking applications (like mBank SK) may not display the reference field when scanning the QR code - it might be ignored or hidden in the app interface. If you want to ensure your reference information is visible to the end user, consider using the `paymentNote` field instead:

```php
// Using paymentNote for reference information
$payment->setPaymentNote('REF123456789: Your payment description');
```

#### HTML and Data URI Output

You can get the QR code as an HTML img tag or as a data URI:

```php
// Get QR code as HTML img tag
$htmlTag = $payBySquare->getQRCodeImage(true, 300, 10);
echo $htmlTag; // Outputs: <img src="data:image/png;base64,..." width="300" height="300" alt="QR Platba" />

// Get QR code as data URI
$dataUri = $payBySquare->getQRCodeImage(false, 300, 10);
echo $dataUri; // Outputs: data:image/png;base64,...

// Or use the dedicated method
$dataUri = $payBySquare->getDataUri(300, 10);
```

#### Multiple Output Formats

You can save the QR code in different formats (PNG, SVG):

```php
// Save as PNG (default)
$payBySquare->saveQRCodeImage('payment-qr.png', PayBySquare::FORMAT_PNG);

// Save as SVG
$payBySquare->saveQRCodeImage('payment-qr.svg', PayBySquare::FORMAT_SVG);
```

#### Color Customization

You can customize the QR code colors:

```php
use Endroid\QrCode\Color\Color;

// Create custom colors
$black = new Color(0, 0, 0); // RGB values for black
$blue = new Color(0, 0, 255); // RGB values for blue

// Generate QR code with custom colors
$qrCodePng = $payBySquare->generateQrCode([
    'foregroundColor' => $black,
    'backgroundColor' => new Color(255, 255, 255), // White background
]);

// Or with the HTML tag method
$htmlTag = $payBySquare->getQRCodeImage(true, 300, 10, [
    'foregroundColor' => $blue,
    'backgroundColor' => new Color(255, 255, 0), // Yellow background
]);

// Save with custom colors
$payBySquare->saveQRCodeImage('payment-qr.png', PayBySquare::FORMAT_PNG, 300, 10, [
    'foregroundColor' => $black,
    'backgroundColor' => new Color(255, 255, 255),
]);
```

#### Custom XZ Binary Path

By default, the library will search for the XZ binary in common locations (`/usr/bin/xz`, `/usr/local/bin/xz`, `/opt/homebrew/bin/xz`, `/opt/local/bin/xz`, `/bin/xz`). If your XZ binary is installed in a different location, you can specify the path when creating a PayBySquare instance:

```php
// Specify the path to the XZ binary
$payBySquare = new PayBySquare([
    'amount' => 15.00,
    'currencyCode' => 'EUR',
    'iban' => 'SK2483300000002403097934',
    // other payment attributes...
], '/custom/path/to/xz');

// Or when creating an instance without payment attributes
$payBySquare = new PayBySquare(null, '/custom/path/to/xz');
```

#### Raw Data String

If you want to use your own QR code generation library, you can get the raw PAY by square data string:

```php
// Get the PAY by square data string
$payBySquareData = $payBySquare->getPayBySquareData();
echo $payBySquareData;

// You can also convert the PayBySquare object directly to a string
echo (string)$payBySquare;

// Now you can use this data string with any QR code library
```

### Exception Handling

The library throws custom exceptions when errors occur during the encoding process. The main exception type is `EncodingException`, which is thrown when the encoding process fails, typically due to issues with the XZ binary.

```php
<?php

require 'vendor/autoload.php';

use PayBySquare\PayBySquare;
use PayBySquare\Exceptions\EncodingException;

// Create a PayBySquare instance with payment attributes
$payBySquare = new PayBySquare([
    'amount' => 15.00,
    'currencyCode' => 'EUR',
    'iban' => 'SK2483300000002403097934',
    'bic' => 'FIOZSKBAXXX'
]);

try {
    // Generate QR code
    $qrCode = $payBySquare->generateQrCode();
    file_put_contents('payment-qr.png', $qrCode);
} catch (EncodingException $e) {
    // Handle encoding errors (e.g., XZ binary not installed)
    echo "Encoding error: " . $e->getMessage();
}
```

Common error scenarios:
- XZ binary not installed or not found in any of the common locations
- XZ binary not found at the specified custom path
- Insufficient permissions to execute the XZ binary
- Invalid payment data that cannot be encoded

## PAY by square Format

This library implements the PAY by square format as defined in the XSD schema. The format includes:

- Payment amount and currency
- Payment due date
- Variable, constant, and specific symbols
- Payment reference
- Payment note
- Bank account information (IBAN, BIC/SWIFT)
- Beneficiary information

## License

MIT License
