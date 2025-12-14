<?php
/**
 * STRICT GCASH PAYMENT VALIDATION SYSTEM
 * This file contains additional validation functions for GCash payments
 * to prevent fake payments and ensure only legitimate transactions are accepted.
 */

class PaymentValidator {
    
    /**
     * Validate GCash reference number format and authenticity
     */
    public static function validateReferenceNumber($reference) {
        $errors = [];
        
        // Basic format validation
        if (empty($reference)) {
            $errors[] = 'Reference number is required.';
            return $errors;
        }
        
        // Length validation (GCash refs are typically 8-20 characters)
        if (strlen($reference) < 8 || strlen($reference) > 20) {
            $errors[] = 'Reference number must be 8-20 characters long.';
        }
        
        // Character validation (alphanumeric only)
        if (!preg_match('/^[A-Za-z0-9]+$/', $reference)) {
            $errors[] = 'Reference number must contain only letters and numbers.';
        }
        
        // Check for suspicious patterns
        $suspiciousPatterns = [
            '12345678', '00000000', '11111111', '22222222', '33333333',
            '44444444', '55555555', '66666666', '77777777', '88888888',
            '99999999', 'test1234', 'fake1234', 'sample123', 'demo1234',
            'admin123', 'user1234', 'order123', 'payment1', 'gcash123'
        ];
        
        if (in_array(strtolower($reference), array_map('strtolower', $suspiciousPatterns))) {
            $errors[] = 'Invalid reference number. Please use your actual GCash transaction reference.';
        }
        
        // Check for sequential patterns (common in fake receipts)
        if (preg_match('/(.)\1{3,}/', $reference)) {
            $errors[] = 'Reference number appears to be invalid. Please use your actual GCash transaction reference.';
        }
        
        // Check for common fake patterns
        if (preg_match('/^(test|fake|demo|sample|admin|user|order|payment|gcash)/i', $reference)) {
            $errors[] = 'Reference number appears to be fake. Please use your actual GCash transaction reference.';
        }
        
        return $errors;
    }
    
    /**
     * Validate payment amount - must be EXACT match
     */
    public static function validateAmount($amountPaid, $orderTotal) {
        $errors = [];
        
        if ($amountPaid <= 0) {
            $errors[] = 'Amount paid must be greater than zero.';
        }
        
        // EXACT match required - no tolerance for rounding errors
        if ($amountPaid != $orderTotal) {
            $errors[] = 'Amount paid (₱' . number_format($amountPaid, 2) . ') must EXACTLY match the order total (₱' . number_format($orderTotal, 2) . '). No partial payments allowed.';
        }
        
        return $errors;
    }
    
    /**
     * Validate receipt image for authenticity - STRICT GCASH RECEIPT VALIDATION
     */
    public static function validateReceiptImage($file) {
        $errors = [];
        
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'GCash receipt image is required.';
            return $errors;
        }
        
        // File type validation
        $allowedTypes = ['image/jpeg', 'image/png'];
        $mime = mime_content_type($file['tmp_name']);
        
        if (!in_array($mime, $allowedTypes)) {
            $errors[] = 'Receipt must be a JPG or PNG image. Other formats are not accepted.';
            return $errors;
        }
        
        // File size validation
        if ($file['size'] > 5 * 1024 * 1024) { // 5MB max
            $errors[] = 'Receipt image must be 5MB or smaller.';
            return $errors;
        }
        
        if ($file['size'] < 1024) { // 1KB min
            $errors[] = 'Receipt image appears to be corrupted or too small. Please upload a valid GCash receipt.';
            return $errors;
        }
        
        // Image dimension validation
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            $errors[] = 'Invalid image file. Please upload a valid GCash receipt image.';
            return $errors;
        }
        
        // STRICT GCASH RECEIPT VALIDATION
        $receiptValidation = self::validateGCashReceiptContent($file['tmp_name']);
        if (!$receiptValidation['isValid']) {
            $errors = array_merge($errors, $receiptValidation['errors']);
        }
        
        return $errors;
    }
    
    /**
     * STRICT GCASH RECEIPT CONTENT VALIDATION
     * This function analyzes the image content to detect if it's a legitimate GCash receipt
     */
    public static function validateGCashReceiptContent($imagePath) {
        $errors = [];
        $isValid = true;
        
        // Basic image validation
        $imageInfo = getimagesize($imagePath);
        if ($imageInfo === false) {
            return ['isValid' => false, 'errors' => ['Invalid image file.']];
        }
        
        // Check image dimensions (GCash receipts are typically portrait orientation)
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        
        if ($width < 200 || $height < 300) {
            $errors[] = 'Receipt image is too small. Please upload a higher resolution GCash receipt.';
            $isValid = false;
        }
        
        // Check aspect ratio (GCash receipts are typically portrait)
        $aspectRatio = $height / $width;
        if ($aspectRatio < 1.0) {
            $errors[] = 'Receipt does not appear to be a valid GCash receipt format.';
            $isValid = false;
        }
        
        // STRICT VALIDATION: Check for common fake receipt patterns
        $fakePatterns = [
            'screenshot' => ['screenshot', 'screen shot', 'capture'],
            'fake' => ['fake', 'test', 'demo', 'sample'],
            'random' => ['random', 'any', 'whatever']
        ];
        
        // Get image filename for analysis
        $filename = basename($imagePath);
        $filenameLower = strtolower($filename);
        
        foreach ($fakePatterns as $pattern => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($filenameLower, $keyword) !== false) {
                    $errors[] = 'Receipt appears to be fake or test image. Please upload your actual GCash receipt.';
                    $isValid = false;
                    break 2;
                }
            }
        }
        
        // Additional validation: Check if image is too generic
        if ($width < 300 || $height < 400) {
            $errors[] = 'Receipt image is too small or low quality. Please upload a clear, high-resolution GCash receipt.';
            $isValid = false;
        }
        
        // Check for suspicious file names
        $suspiciousNames = ['test', 'fake', 'demo', 'sample', 'random', 'any', 'whatever', 'screenshot'];
        foreach ($suspiciousNames as $suspicious) {
            if (strpos($filenameLower, $suspicious) !== false) {
                $errors[] = 'Receipt filename appears suspicious. Please upload your actual GCash receipt.';
                $isValid = false;
                break;
            }
        }
        
        // STRICT VALIDATION: Require specific image characteristics
        if ($isValid) {
            // Check if image has reasonable file size (not too small, not too large)
            $fileSize = filesize($imagePath);
            if ($fileSize < 10000) { // Less than 10KB
                $errors[] = 'Receipt image is too small. Please upload a clear, high-resolution GCash receipt.';
                $isValid = false;
            }
            
            if ($fileSize > 5 * 1024 * 1024) { // More than 5MB
                $errors[] = 'Receipt image is too large. Please compress the image and try again.';
                $isValid = false;
            }
        }
        
        return ['isValid' => $isValid, 'errors' => $errors];
    }
    
    /**
     * Check if reference number has been used before (prevent duplicates)
     */
    public static function checkDuplicateReference($pdo, $reference) {
        $errors = [];
        
        // Check user orders
        $userCheck = $pdo->prepare("SELECT COUNT(*) FROM user_orders WHERE payment_method='gcash' AND JSON_EXTRACT(payment_details, '$.reference') = ?");
        $userCheck->execute([$reference]);
        
        // Check admin orders
        require_once __DIR__ . '/admin_config.php';
        $adminPdo = get_admin_pdo();
        $adminCheck = $adminPdo->prepare("SELECT COUNT(*) FROM orders WHERE payment_method='gcash' AND JSON_EXTRACT(payment_details, '$.reference') = ?");
        $adminCheck->execute([$reference]);
        
        $userCount = $userCheck->fetchColumn();
        $adminCount = $adminCheck->fetchColumn();
        
        if ($userCount > 0 || $adminCount > 0) {
            $errors[] = 'This GCash reference number has already been used. Please use a different reference.';
        }
        
        return $errors;
    }
    
    /**
     * Log payment attempt for admin review
     */
    public static function logPaymentAttempt($orderNumber, $reference, $amount, $receiptPath, $customerEmail) {
        $logMessage = sprintf(
            "GCASH PAYMENT ATTEMPT - Order: %s, Reference: %s, Amount: ₱%.2f, Receipt: %s, Customer: %s, Time: %s",
            $orderNumber,
            $reference,
            $amount,
            $receiptPath,
            $customerEmail,
            date('Y-m-d H:i:s')
        );
        
        error_log($logMessage);
        
        // Also log to a dedicated payment log file
        $logFile = __DIR__ . '/logs/payment_attempts.log';
        if (!is_dir(dirname($logFile))) {
            @mkdir(dirname($logFile), 0777, true);
        }
        
        file_put_contents($logFile, $logMessage . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Generate secure filename for receipt storage
     */
    public static function generateReceiptFilename($reference, $mimeType) {
        $allowedTypes = ['image/jpeg' => '.jpg', 'image/png' => '.png'];
        $extension = $allowedTypes[$mimeType] ?? '.jpg';
        
        // Create secure filename with timestamp, reference, and random component
        $timestamp = date('Ymd_His');
        $refPart = substr($reference, 0, 8);
        $random = bin2hex(random_bytes(4));
        
        return "gcash_{$timestamp}_{$refPart}_{$random}{$extension}";
    }
}

/**
 * Additional security functions for payment validation
 */
class PaymentSecurity {
    
    /**
     * Check for suspicious payment patterns - ENHANCED SECURITY
     */
    public static function checkSuspiciousPatterns($reference, $amount, $customerEmail) {
        $suspicious = false;
        $reasons = [];
        
        // Check for rapid successive payments from same email
        // (This would require additional database queries in a real implementation)
        
        // Check for unusual amount patterns
        if ($amount < 1.00) {
            $suspicious = true;
            $reasons[] = 'Amount too low';
        }
        
        if ($amount > 50000.00) {
            $suspicious = true;
            $reasons[] = 'Amount unusually high';
        }
        
        // Check for test email domains
        $testDomains = ['test.com', 'example.com', 'fake.com', 'demo.com'];
        $emailDomain = substr(strrchr($customerEmail, "@"), 1);
        if (in_array(strtolower($emailDomain), $testDomains)) {
            $suspicious = true;
            $reasons[] = 'Test email domain';
        }
        
        // ENHANCED SECURITY: Check for suspicious reference patterns
        $suspiciousRefPatterns = [
            'test', 'fake', 'demo', 'sample', 'random', 'any', 'whatever',
            'screenshot', 'capture', 'image', 'photo', 'pic'
        ];
        
        $referenceLower = strtolower($reference);
        foreach ($suspiciousRefPatterns as $pattern) {
            if (strpos($referenceLower, $pattern) !== false) {
                $suspicious = true;
                $reasons[] = 'Suspicious reference pattern';
                break;
            }
        }
        
        return ['suspicious' => $suspicious, 'reasons' => $reasons];
    }
    
    /**
     * STRICT GCASH EXPRESS SEND VALIDATION
     * This function specifically validates GCash Express Send receipts
     */
    public static function validateGCashExpressSend($file, $reference, $amount) {
        $errors = [];
        
        // First, validate the image content using PaymentValidator
        $imageValidation = PaymentValidator::validateGCashReceiptContent($file['tmp_name']);
        if (!$imageValidation['isValid']) {
            $errors = array_merge($errors, $imageValidation['errors']);
        }
        
        // STRICT VALIDATION: Check for GCash Express Send specific characteristics
        $filename = basename($file['name']);
        $filenameLower = strtolower($filename);
        
        // Check for suspicious file names that indicate fake receipts
        $fakeIndicators = [
            'screenshot', 'screen shot', 'capture', 'random', 'any', 'whatever',
            'test', 'fake', 'demo', 'sample', 'trial', 'practice'
        ];
        
        foreach ($fakeIndicators as $indicator) {
            if (strpos($filenameLower, $indicator) !== false) {
                $errors[] = 'Receipt filename indicates this is not a legitimate GCash receipt. Please upload your actual GCash Express Send receipt.';
                break;
            }
        }
        
        // STRICT VALIDATION: Check image dimensions for GCash receipt format
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo !== false) {
            $width = $imageInfo[0];
            $height = $imageInfo[1];
            
            // GCash receipts are typically portrait and have specific dimensions
            if ($width < 200 || $height < 300) {
                $errors[] = 'Receipt image is too small. Please upload a clear, high-resolution GCash Express Send receipt.';
            }
            
            // Check aspect ratio (GCash receipts are typically portrait)
            $aspectRatio = $height / $width;
            if ($aspectRatio < 1.0) {
                $errors[] = 'Receipt does not appear to be a valid GCash Express Send receipt format.';
            }
        }
        
        // STRICT VALIDATION: Check file size (GCash receipts are typically 10KB-5MB)
        $fileSize = $file['size'];
        if ($fileSize < 10000) { // Less than 10KB
            $errors[] = 'Receipt image is too small. Please upload a clear, high-resolution GCash receipt.';
        }
        
        if ($fileSize > 5 * 1024 * 1024) { // More than 5MB
            $errors[] = 'Receipt image is too large. Please compress the image and try again.';
        }
        
        return $errors;
    }
}
?>
