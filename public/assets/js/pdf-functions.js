/**
 * Shared PDF.js functions for page counting and thumbnail generation
 * 
 * Dependencies:
 * - PDF.js CDN: https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js
 * - Worker: https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js
 */

// Set PDF.js worker source
if (typeof pdfjsLib !== 'undefined') {
    pdfjsLib.GlobalWorkerOptions.workerSrc =
        "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";
}

/**
 * Get CSRF token from server
 * @returns {Promise<string>} Token string
 */
async function getPdfToken() {
    try {
        // Use absolute path to work from any page (root or subdirectory)
        const response = await fetch('/handler/pdf_functions.php?get_token=1');
        const data = await response.json();
        if (data.success && data.token) {
            return data.token;
        }
        throw new Error('Failed to get token');
    } catch (error) {
        console.error('Error getting PDF token:', error);
        throw error;
    }
}

/**
 * Count pages in PDF and save to database
 * @param {string} pdfUrl - URL to PDF file
 * @param {number} docId - Document ID
 * @param {string} token - CSRF token (optional, will fetch if not provided)
 * @returns {Promise<number>} Number of pages
 */
async function getPdfPageCount(pdfUrl, docId, token = null) {
    try {
        // Get token if not provided
        if (!token) {
            token = await getPdfToken();
        }

        // Load PDF document
        const pdf = await pdfjsLib.getDocument(pdfUrl).promise;
        const pages = pdf.numPages;

        // Send page count to server
        const formData = new FormData();
        formData.append('action', 'save_pdf_pages');
        formData.append('doc_id', docId);
        formData.append('pages', pages);
        formData.append('token', token);

        // Use absolute path to work from any page (root or subdirectory)
        const response = await fetch('/handler/pdf_functions.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message || 'Failed to save page count');
        }

        console.log(`PDF page count saved: ${pages} pages for document ${docId}`);
        return pages;

    } catch (error) {
        console.error('Error getting PDF page count:', error);
        throw error;
    }
}

/**
 * Generate thumbnail from PDF first page and upload to server
 * @param {string} pdfUrl - URL to PDF file
 * @param {number} docId - Document ID
 * @param {string} token - CSRF token (optional, will fetch if not provided)
 * @param {number} thumbnailWidth - Width of thumbnail in pixels (default: 400)
 * @returns {Promise<object>} Result object with thumbnail path
 */
async function generatePdfThumbnail(pdfUrl, docId, token = null, thumbnailWidth = 400) {
    try {
        // Get token if not provided
        if (!token) {
            token = await getPdfToken();
        }

        // Load PDF document
        const pdfDoc = await pdfjsLib.getDocument(pdfUrl).promise;

        // Get first page
        const page = await pdfDoc.getPage(1);

        // Calculate scale for desired width
        const viewport = page.getViewport({ scale: 1.0 });
        const scale = thumbnailWidth / viewport.width;
        const scaledViewport = page.getViewport({ scale: scale });

        // Create canvas
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d');
        canvas.width = scaledViewport.width;
        canvas.height = scaledViewport.height;

        // Render page to canvas
        await page.render({
            canvasContext: context,
            viewport: scaledViewport
        }).promise;

        // Convert canvas to blob
        const blob = await new Promise(resolve => {
            canvas.toBlob(resolve, 'image/jpeg', 0.85);
        });

        if (!blob) {
            throw new Error('Failed to convert canvas to blob');
        }

        // Upload thumbnail to server
        const formData = new FormData();
        formData.append('action', 'save_thumbnail');
        formData.append('doc_id', docId);
        formData.append('thumbnail', blob, `thumb_${docId}.jpg`);
        formData.append('token', token);

        // Use absolute path to work from any page (root or subdirectory)
        const response = await fetch('/handler/pdf_functions.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message || 'Failed to save thumbnail');
        }

        console.log(`PDF thumbnail generated and saved for document ${docId}:`, result.thumbnail);
        return result;

    } catch (error) {
        console.error('Error generating PDF thumbnail:', error);
        throw error;
    }
}

/**
 * Process PDF document - count pages and generate thumbnail
 * @param {string} pdfUrl - URL to PDF file
 * @param {number} docId - Document ID
 * @param {object} options - Options object
 * @param {boolean} options.countPages - Whether to count pages (default: true)
 * @param {boolean} options.generateThumbnail - Whether to generate thumbnail (default: true)
 * @param {number} options.thumbnailWidth - Width of thumbnail in pixels (default: 400)
 * @returns {Promise<object>} Result object with pages and thumbnail info
 */
async function processPdfDocument(pdfUrl, docId, options = {}) {
    const {
        countPages = true,
        generateThumbnail = true,
        thumbnailWidth = 400
    } = options;

    const result = {
        success: false,
        pages: 0,
        thumbnail: null
    };

    try {
        // Get token once for both operations
        const token = await getPdfToken();

        // Count pages if requested
        if (countPages) {
            try {
                result.pages = await getPdfPageCount(pdfUrl, docId, token);
            } catch (error) {
                console.warn('Failed to count pages:', error);
            }
        }

        // Generate thumbnail if requested
        if (generateThumbnail) {
            try {
                const thumbResult = await generatePdfThumbnail(pdfUrl, docId, token, thumbnailWidth);
                result.thumbnail = thumbResult.thumbnail;
            } catch (error) {
                console.warn('Failed to generate thumbnail:', error);
            }
        }

        result.success = (countPages ? result.pages > 0 : true) &&
            (generateThumbnail ? result.thumbnail !== null : true);

        return result;

    } catch (error) {
        console.error('Error processing PDF document:', error);
        throw error;
    }
}

// Export functions for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        getPdfToken,
        getPdfPageCount,
        generatePdfThumbnail,
        processPdfDocument
    };
}

