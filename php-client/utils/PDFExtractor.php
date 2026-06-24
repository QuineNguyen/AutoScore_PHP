<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../models/config.php';

/**
 * Class PDFExtractor
 * Xử lý trích xuất text từ file PDF
 * 
 * Requirements: Cài đặt thư viện smalot/pdfparser
 * composer require smalot/pdfparser
 */
class PDFExtractor {
    
    /**
     * Trích xuất text từ file PDF
     * 
     * @param string $filePath Đường dẫn đến file PDF
     * @param string|null $originalFileName Tên file gốc (dùng khi $filePath là file tạm không có extension)
     * @return array ['success' => bool, 'text' => string, 'metadata' => array, 'error' => string]
     */
    public static function extractText($filePath, $originalFileName = null) {
        try {
            if (!file_exists($filePath)) {
                return [
                    'success' => false,
                    'text' => '',
                    'metadata' => [],
                    'error' => 'File không tồn tại'
                ];
            }
            
            if ($originalFileName) {
                $extension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
                error_log("PDFExtractor: Using original filename extension: $extension");
            } else {
                $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                error_log("PDFExtractor: Detected file extension from path: $extension");
            }
            
            if (empty($extension) || $extension === 'tmp') {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $filePath);
                
                error_log("PDFExtractor: MIME type detected: $mimeType");
                
                if ($mimeType === 'application/pdf') {
                    $extension = 'pdf';
                } else {
                    return [
                        'success' => false,
                        'text' => '',
                        'metadata' => [],
                        'error' => "File không phải định dạng PDF (MIME: $mimeType)"
                    ];
                }
            }
            
            if ($extension !== 'pdf') {
                return [
                    'success' => false,
                    'text' => '',
                    'metadata' => [],
                    'error' => 'File không phải định dạng PDF'
                ];
            }
            
            // Sử dụng smalot/pdfparser
            if (class_exists('\Smalot\PdfParser\Parser')) {
                return self::extractWithSmalot($filePath);
            }
            
            return [
                'success' => false,
                'text' => '',
                'metadata' => [],
                'error' => 'Không có thư viện PDF parser. Vui lòng cài: composer require smalot/pdfparser'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'text' => '',
                'metadata' => [],
                'error' => 'Lỗi xử lý PDF: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Trích xuất text bằng thư viện smalot/pdfparser
     */
    private static function extractWithSmalot($filePath) {
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($filePath);
            
            $text = $pdf->getText();
            $details = $pdf->getDetails();
            
            $metadata = [
                'pages' => count($pdf->getPages()),
                'title' => $details['Title'] ?? '',
                'author' => $details['Author'] ?? '',
                'subject' => $details['Subject'] ?? '',
                'creator' => $details['Creator'] ?? '',
                'producer' => $details['Producer'] ?? '',
                'creation_date' => $details['CreationDate'] ?? '',
                'mod_date' => $details['ModDate'] ?? ''
            ];
            
            return [
                'success' => true,
                'text' => trim($text),
                'metadata' => $metadata,
                'error' => ''
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'text' => '',
                'metadata' => [],
                'error' => 'Lỗi parse PDF: ' . $e->getMessage()
            ];
        }
    }
    

}
?>
