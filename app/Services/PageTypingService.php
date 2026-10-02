<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\FileIndexing;
use App\Models\Scanning;
use App\Models\PageTyping;
use Exception;
use setasign\Fpdi\Fpdi;

class PageTypingService
{
    /**
     * Process file for page typing - split PDFs or handle images
     */
    public function processFileForPageTyping($fileIndexingId)
    {
        try {
            $fileIndexing = FileIndexing::on('sqlsrv')->find($fileIndexingId);
            if (!$fileIndexing) {
                throw new Exception("File indexing not found");
            }

            // Get scannings for this file
            $scannings = $fileIndexing->scannings;
            $processedPages = [];

            foreach ($scannings as $scanning) {
                $filePath = $scanning->document_path;
                $originalFilename = $scanning->original_filename;

                // Determine file type
                if ($this->isPdfFile($originalFilename)) {
                    // Process PDF - split into pages
                    $pdfPages = $this->splitPdfIntoPages($filePath, $fileIndexing->file_number, $fileIndexing->registry, $scanning->paper_size);
                    $processedPages = array_merge($processedPages, $pdfPages);
                } else if ($this->isImageFile($originalFilename)) {
                    // Process image - copy to page typing directory
                    $imagePage = $this->processImageFile($filePath, $fileIndexing->file_number, $originalFilename, $fileIndexing->registry, $scanning->paper_size);
                    if ($imagePage) {
                        $processedPages[] = $imagePage;
                    }
                } else {
                    Log::warning("Unsupported file type for page typing", [
                        'file_indexing_id' => $fileIndexingId,
                        'filename' => $originalFilename,
                        'path' => $filePath
                    ]);
                }
            }

            return [
                'success' => true,
                'processed_pages' => $processedPages,
                'total_pages' => count($processedPages)
            ];

        } catch (Exception $e) {
            Log::error("Error processing file for page typing", [
                'file_indexing_id' => $fileIndexingId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Split PDF into individual pages using FPDI
     */
    private function splitPdfIntoPages($filePath, $fileNumber, $registry = null, $paperSize = 'A4')
    {
        try {
            // Get full path to the PDF file
            $fullPath = file_storage_path('app/public/' . ltrim($filePath, '/'));

            if (!file_exists($fullPath)) {
                throw new Exception("PDF file not found: " . $fullPath);
            }

            // Create directory for page typing files with Registry and PaperSize
            $registrySlug = $this->getRegistrySlug($registry);
            $paperSize = !empty($paperSize) ? strtoupper($paperSize) : 'A4';
            $pageTypingDir = "EDMS/PAGETYPING/{$registrySlug}/{$fileNumber}/{$paperSize}";
            Storage::disk('public')->makeDirectory($pageTypingDir);

            // First, copy the combined PDF to the page typing directory
            $combinedPdfPath = "{$pageTypingDir}/combined.pdf";
            Storage::disk('public')->copy(ltrim($filePath, '/'), $combinedPdfPath);

            // Initialize FPDI
            $pdf = new Fpdi();
            $pageCount = $pdf->setSourceFile($fullPath);

            $processedPages = [];

            // Split each page
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                try {
                    // Create new PDF for this page
                    $singlePagePdf = new Fpdi();
                    $singlePagePdf->AddPage();

                    // Import the page
                    $templateId = $singlePagePdf->importPage($pageNo);
                    $singlePagePdf->useTemplate($templateId);

                    // Save the single page PDF
                    $pageFileName = "{$fileNumber}_page{$pageNo}.pdf";
                    $pageFilePath = "{$pageTypingDir}/{$pageFileName}";
                    $fullPagePath = file_storage_path('app/public/' . $pageFilePath);

                    $singlePagePdf->Output($fullPagePath, 'F');

                    $processedPages[] = [
                        'page_number' => $pageNo,
                        'file_path' => $pageFilePath,
                        'file_name' => $pageFileName,
                        'file_type' => 'pdf',
                        'source' => 'pdf_split',
                        'combined_file_path' => $combinedPdfPath
                    ];

                } catch (Exception $e) {
                    Log::error("Error splitting PDF page", [
                        'file_path' => $filePath,
                        'page_number' => $pageNo,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info("PDF split successfully", [
                'file_path' => $filePath,
                'total_pages' => $pageCount,
                'processed_pages' => count($processedPages)
            ]);

            return $processedPages;

        } catch (Exception $e) {
            Log::error("Error splitting PDF", [
                'file_path' => $filePath,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Process image file for page typing
     */
    private function processImageFile($filePath, $fileNumber, $originalFilename, $registry = null, $paperSize = 'A4')
    {
        try {
            // Create directory for page typing files with Registry and PaperSize
            $registrySlug = $this->getRegistrySlug($registry);
            $paperSize = !empty($paperSize) ? strtoupper($paperSize) : 'A4';
            $pageTypingDir = "EDMS/PAGETYPING/{$registrySlug}/{$fileNumber}/{$paperSize}";
            Storage::disk('public')->makeDirectory($pageTypingDir);

            // Get file extension
            $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);

            // Create new filename with page numbering
            $pageFileName = "page_001." . strtolower($extension);
            $pageFilePath = "{$pageTypingDir}/{$pageFileName}";

            // Copy image to page typing directory
            if (Storage::disk('public')->copy(ltrim($filePath, '/'), $pageFilePath)) {
                return [
                    'page_number' => 1,
                    'file_path' => $pageFilePath,
                    'file_name' => $pageFileName,
                    'file_type' => 'image',
                    'source' => 'image_copy',
                    'original_filename' => $originalFilename
                ];
            }

            return null;

        } catch (Exception $e) {
            Log::error("Error processing image file", [
                'file_path' => $filePath,
                'original_filename' => $originalFilename,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Handle "Upload More" scenario - merge old and new pages
     */
    public function handleUploadMore($fileIndexingId)
    {
        try {
            $fileIndexing = FileIndexing::on('sqlsrv')->find($fileIndexingId);
            if (!$fileIndexing) {
                throw new Exception("File indexing not found");
            }

            // Get existing page typings
            $existingPageTypings = $fileIndexing->pagetypings;

            // Process new scannings
            $result = $this->processFileForPageTyping($fileIndexingId);

            if ($result['success']) {
                // Mark new pages with source = 'upload_more'
                foreach ($result['processed_pages'] as &$page) {
                    $page['source'] = 'upload_more';
                }

                // Update file indexing to mark as updated
                $fileIndexing->update(['is_updated' => 1]);

                return [
                    'success' => true,
                    'existing_pages' => $existingPageTypings->count(),
                    'new_pages' => count($result['processed_pages']),
                    'processed_pages' => $result['processed_pages']
                ];
            }

            return $result;

        } catch (Exception $e) {
            Log::error("Error handling upload more", [
                'file_indexing_id' => $fileIndexingId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Save page typing with metadata
     */
    public function savePageTyping($data)
    {
        try {
            DB::connection('sqlsrv')->beginTransaction();

            $pageTyping = PageTyping::on('sqlsrv')->create([
                'file_indexing_id' => $data['file_indexing_id'],
                'scanning_id' => $data['scanning_id'] ?? null,
                'page_number' => $data['page_number'],
                'page_type' => $data['page_type'],
                'page_subtype' => $data['page_subtype'] ?? null,
                'serial_number' => $data['serial_number'],
                'page_code' => $data['page_code'] ?? null,
                'file_path' => $data['file_path'],
                'source' => $data['source'] ?? 'manual',
                'typed_by' => $data['typed_by'],
                'qc_status' => 'pending'
            ]);

            DB::connection('sqlsrv')->commit();

            return [
                'success' => true,
                'page_typing' => $pageTyping
            ];

        } catch (Exception $e) {
            DB::connection('sqlsrv')->rollBack();

            Log::error("Error saving page typing", [
                'data' => $data,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check if file is PDF
     */
    private function isPdfFile($filename)
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'pdf';
    }

    /**
     * Check if file is image
     */
    private function isImageFile($filename)
    {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'tiff', 'tif'];
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, $imageExtensions);
    }

    /**
     * Get file type from filename
     */
    public function getFileType($filename)
    {
        if ($this->isPdfFile($filename)) {
            return 'pdf';
        } elseif ($this->isImageFile($filename)) {
            return 'image';
        } else {
            return 'unknown';
        }
    }

    /**
     * Generate thumbnail for file
     */
    public function generateThumbnail($filePath, $fileType)
    {
        try {
            $thumbnailDir = "EDMS/THUMBNAILS";
            Storage::disk('public')->makeDirectory($thumbnailDir);

            $filename = pathinfo($filePath, PATHINFO_FILENAME);
            $thumbnailPath = "{$thumbnailDir}/{$filename}_thumb.jpg";

            if ($fileType === 'pdf') {
                // For PDFs, we'll rely on PDF.js for client-side thumbnails
                // Or implement server-side PDF to image conversion if needed
                return null;
            } elseif ($fileType === 'image') {
                // For images, create a smaller thumbnail
                $fullPath = file_storage_path('app/public/' . ltrim($filePath, '/'));
                $thumbnailFullPath = file_storage_path('app/public/' . $thumbnailPath);

                // Simple thumbnail creation (requires GD extension)
                if (extension_loaded('gd')) {
                    $this->createImageThumbnail($fullPath, $thumbnailFullPath);
                    return $thumbnailPath;
                }
            }

            return null;

        } catch (Exception $e) {
            Log::error("Error generating thumbnail", [
                'file_path' => $filePath,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Create image thumbnail using GD
     */
    private function createImageThumbnail($sourcePath, $thumbnailPath, $maxWidth = 200, $maxHeight = 200)
    {
        try {
            $imageInfo = getimagesize($sourcePath);
            if (!$imageInfo) {
                return false;
            }

            $sourceWidth = $imageInfo[0];
            $sourceHeight = $imageInfo[1];
            $mimeType = $imageInfo['mime'];

            // Calculate thumbnail dimensions
            $ratio = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
            $thumbWidth = intval($sourceWidth * $ratio);
            $thumbHeight = intval($sourceHeight * $ratio);

            // Create source image
            switch ($mimeType) {
                case 'image/jpeg':
                    $sourceImage = imagecreatefromjpeg($sourcePath);
                    break;
                case 'image/png':
                    $sourceImage = imagecreatefrompng($sourcePath);
                    break;
                case 'image/gif':
                    $sourceImage = imagecreatefromgif($sourcePath);
                    break;
                default:
                    return false;
            }

            if (!$sourceImage) {
                return false;
            }

            // Create thumbnail
            $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);

            // Preserve transparency for PNG and GIF
            if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
                imagealphablending($thumbnail, false);
                imagesavealpha($thumbnail, true);
                $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
                imagefilledrectangle($thumbnail, 0, 0, $thumbWidth, $thumbHeight, $transparent);
            }

            // Resize image
            imagecopyresampled($thumbnail, $sourceImage, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $sourceWidth, $sourceHeight);

            // Save thumbnail as JPEG
            $result = imagejpeg($thumbnail, $thumbnailPath, 85);

            // Clean up
            imagedestroy($sourceImage);
            imagedestroy($thumbnail);

            return $result;

        } catch (Exception $e) {
            Log::error("Error creating image thumbnail", [
                'source_path' => $sourcePath,
                'thumbnail_path' => $thumbnailPath,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Clean up temporary files
     */
    public function cleanupTempFiles($fileNumber)
    {
        try {
            $tempDir = "EDMS/TEMP/{$fileNumber}";
            if (Storage::disk('public')->exists($tempDir)) {
                Storage::disk('public')->deleteDirectory($tempDir);
            }
        } catch (Exception $e) {
            Log::error("Error cleaning up temp files", [
                'file_number' => $fileNumber,
                'error' => $e->getMessage()
            ]);
        }
    }
}