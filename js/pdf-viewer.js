/**
 * VSD Advanced PDF Viewer (Drive Style)
 * High performance lazy loading with IntersectionObserver
 * Handles rendering of PDF pages with progressive loading.
 */
class VsdPdfViewer {
    constructor(viewerId, pdfPath, options = {}) {
        this.viewer = document.getElementById(viewerId);
        this.pdfPath = pdfPath;
        this.options = {
            maxPreviewPages: options.maxPreviewPages || 5, // Default to 5 pages
            hasPurchased: options.hasPurchased || false,
            dprLimit: 2, // Reduced for better performance with large files
            rootMargin: '500px 0px', // Reduced: Pre-load ~2 pages ahead
            initialBatchSize: 20, // Only create first 20 placeholders initially
            batchSize: 10, // Load next batch of 10 pages when needed
            maxConcurrentRenders: 3, // Limit concurrent renders
            renderQueue: [],
            ...options
        };
        
        this.pdfDoc = null;
        this.numPages = 0;
        this.trackObserver = null;
        this.activePages = new Map(); // pageNum -> { renderTask, canvas }
        this.renderingPages = new Set(); // Track pages currently rendering
        this.pageCounter = document.getElementById('pdfPageCounter');
        this.currentPageNumDisplay = document.getElementById('currentPageNum');
        this.totalPagesNumDisplay = document.getElementById('totalPagesNum');
        this.createdPlaceholders = 0; // Track how many placeholders created
        this.isCreatingPlaceholders = false;
        
        this.init();
    }

    async init() {
        try {
            // Set worker properly if global PDF.js lib exists
            if (typeof pdfjsLib !== 'undefined' && !pdfjsLib.GlobalWorkerOptions.workerSrc) {
                 // Fallback or assume config set elsewhere
                 pdfjsLib.GlobalWorkerOptions.workerSrc = "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";
            }

            const loadingTask = pdfjsLib.getDocument({
                url: this.pdfPath,
                enableWebGL: false, // Fix: Disable WebGL to prevent texture inversion issues
                disableAutoFetch: true, // Lazy loading
                disableStream: false
            });
            
            this.pdfDoc = await loadingTask.promise;
            this.numPages = this.pdfDoc.numPages;
            
            if (this.totalPagesNumDisplay) {
                this.totalPagesNumDisplay.textContent = this.numPages;
            }

            await this.setupPlaceholders();
            // setupObservers will be called after initial batch is created
            
            // Show counter
            if (this.pageCounter) this.pageCounter.classList.add('active');
        } catch (err) {
            console.error('PDF Init Error:', err);
            if (this.viewer) {
                this.viewer.innerHTML = `<div class="p-10 text-center text-error">
                    <i class="fa-solid fa-triangle-exclamation text-4xl mb-2"></i>
                    <p>Không thể hiển thị tài liệu: ${err.message}</p>
                </div>`;
            }
        }
    }

    async setupPlaceholders() {
        this.viewer.innerHTML = '';
        // Get page 1 info for aspect ratio
        const firstPage = await this.pdfDoc.getPage(1);
        const viewport = firstPage.getViewport({ scale: 1 });
        const aspectRatio = viewport.height / viewport.width;

        // Progressive placeholder creation - only create initial batch
        const initialCount = Math.min(this.options.initialBatchSize, this.numPages);
        
        // If all pages fit in initial batch, create all and setup observers
        if (initialCount >= this.numPages) {
            for (let i = 1; i <= this.numPages; i++) {
                const container = this.createPlaceholder(i, viewport);
                this.viewer.appendChild(container);
                this.createdPlaceholders++;
            }
            this.setupObservers();
        } else {
            // Create initial batch progressively, observers will be setup after
            this.createPlaceholderBatch(1, initialCount, viewport, true);
        }
    }

    createPlaceholderBatch(startPage, count, viewport, isInitialBatch = false) {
        if (this.isCreatingPlaceholders) return;
        this.isCreatingPlaceholders = true;

        // Use requestIdleCallback for better performance, fallback to setTimeout
        const scheduleNext = window.requestIdleCallback || ((fn) => setTimeout(fn, 16));

        let created = 0;
        const createNext = () => {
            if (created >= count || this.createdPlaceholders >= this.numPages) {
                this.isCreatingPlaceholders = false;
                // Setup observers after initial batch, or observe new placeholders
                if (isInitialBatch && !this.renderObserver) {
                    this.setupObservers();
                } else {
                    // Re-observe new placeholders
                    const newPages = this.viewer.querySelectorAll('.pdf-page-container:not([data-observed])');
                    newPages.forEach(el => {
                        el.setAttribute('data-observed', 'true');
                        if (this.renderObserver) this.renderObserver.observe(el);
                        if (this.trackObserver) this.trackObserver.observe(el);
                    });
                }
                return;
            }

            const i = startPage + created;
            if (i > this.numPages) {
                this.isCreatingPlaceholders = false;
                if (isInitialBatch && !this.renderObserver) {
                    this.setupObservers();
                } else {
                    const newPages = this.viewer.querySelectorAll('.pdf-page-container:not([data-observed])');
                    newPages.forEach(el => {
                        el.setAttribute('data-observed', 'true');
                        if (this.renderObserver) this.renderObserver.observe(el);
                        if (this.trackObserver) this.trackObserver.observe(el);
                    });
                }
                return;
            }

            const container = this.createPlaceholder(i, viewport);
            this.viewer.appendChild(container);
            this.createdPlaceholders++;
            created++;

            // Create next placeholder in next frame to avoid blocking
            scheduleNext(createNext);
        };

        createNext();
    }

    createPlaceholder(i, viewport) {
        const container = document.createElement('div');
        container.className = 'pdf-page-container';
        container.id = `vsd-page-${i}`;
        container.dataset.page = i;
        
        // Maintain aspect ratio exactly
        container.style.aspectRatio = `${viewport.width} / ${viewport.height}`;
        container.style.width = '100%';
        // Reduced maxWidth for better performance with large files
        const maxWidthScale = this.numPages > 50 ? 1.2 : 1.5;
        container.style.maxWidth = (viewport.width * maxWidthScale) + 'px';

        // Loader element
        const loader = document.createElement('div');
        loader.className = 'page-loader absolute inset-0 flex flex-col items-center justify-center bg-base-100 z-10 transition-opacity duration-300';
        loader.innerHTML = `
            <span class="loading loading-spinner loading-md text-primary opacity-50"></span>
            <div class="mt-2 text-[10px] font-bold opacity-20 uppercase tracking-widest text-center">Trang ${i} / ${this.numPages}</div>
        `;
        container.appendChild(loader);

        // Blur logic for non-purchased
        if (!this.options.hasPurchased && i > this.options.maxPreviewPages) {
            container.classList.add('page-limit-blur');
            const blurLabel = document.createElement('div');
            blurLabel.className = 'absolute inset-0 flex items-center justify-center z-20 pointer-events-none';
            blurLabel.innerHTML = `<div class="bg-base-100/90 px-5 py-3 rounded-xl shadow-2xl font-bold text-sm border border-primary/20 backdrop-blur-sm">Mua tài liệu để xem đầy đủ</div>`;
            container.appendChild(blurLabel);
        }

        return container;
    }

    setupObservers() {
        // Prevent multiple observers
        if (this.renderObserver) {
            this.renderObserver.disconnect();
        }
        if (this.trackObserver) {
            this.trackObserver.disconnect();
        }

        // Dynamic rootMargin based on file size - smaller for large files
        const dynamicRootMargin = this.numPages > 100 ? '300px 0px' : 
                                 this.numPages > 50 ? '500px 0px' : 
                                 this.options.rootMargin;

        // 1. Rendering Observer (Load on approach, destroy on leave)
        this.renderObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                const pageNum = parseInt(entry.target.dataset.page);
                if (entry.isIntersecting) {
                    // Create more placeholders if needed (progressive loading)
                    if (pageNum > this.createdPlaceholders - 5 && this.createdPlaceholders < this.numPages && !this.isCreatingPlaceholders && this.pdfDoc) {
                        const nextBatchStart = this.createdPlaceholders + 1;
                        const batchSize = Math.min(this.options.batchSize, this.numPages - this.createdPlaceholders);
                        // Create next batch asynchronously
                        this.pdfDoc.getPage(1).then(page => {
                            const viewport = page.getViewport({ scale: 1 });
                            this.createPlaceholderBatch(nextBatchStart, batchSize, viewport);
                        }).catch(err => console.warn('Error getting page 1 for placeholder:', err));
                    }
                    this.queueRenderPage(pageNum, entry.target);
                } else {
                    this.destroyPage(pageNum);
                }
            });
        }, {
            root: this.viewer,
            rootMargin: dynamicRootMargin,
            threshold: 0.01
        });

        // 2. Tracking Observer (Update page number indicator)
        this.trackObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    if (this.currentPageNumDisplay) {
                        this.currentPageNumDisplay.textContent = entry.target.dataset.page;
                    }
                }
            });
        }, {
            root: this.viewer,
            threshold: 0.51 // Trigger when more than half is visible
        });

        // Observe existing placeholders (mark as observed)
        const pages = this.viewer.querySelectorAll('.pdf-page-container');
        pages.forEach(el => {
            el.setAttribute('data-observed', 'true');
            this.renderObserver.observe(el);
            this.trackObserver.observe(el);
        });
    }

    queueRenderPage(pageNum, container) {
        // Limit concurrent renders
        if (this.renderingPages.size >= this.options.maxConcurrentRenders) {
            this.options.renderQueue.push({ pageNum, container });
            return;
        }
        this.renderPage(pageNum, container);
    }

    async renderPage(pageNum, container) {
        // Check both active AND rendering states
        if (this.activePages.has(pageNum) || this.renderingPages.has(pageNum)) return; 
        if (!this.options.hasPurchased && pageNum > this.options.maxPreviewPages) return;

        // Lock the page immediately
        this.renderingPages.add(pageNum);

        try {
            const page = await this.pdfDoc.getPage(pageNum);
            
            // Update container dimensions to match the ACTUAL page dimensions
            const naturalViewport = page.getViewport({ scale: 1 });
            container.style.aspectRatio = `${naturalViewport.width} / ${naturalViewport.height}`;
            // Reduced maxWidth for better performance with large files
            const maxWidthScale = this.numPages > 50 ? 1.2 : 1.5;
            container.style.maxWidth = (naturalViewport.width * maxWidthScale) + 'px';

            // Adaptive DPR based on file size - lower for large files
            const adaptiveDprLimit = this.numPages > 100 ? 1.5 : 
                                   this.numPages > 50 ? 2 : 
                                   this.options.dprLimit;
            const dpr = Math.min(window.devicePixelRatio || 1, adaptiveDprLimit);
            const viewport = page.getViewport({ scale: dpr });
            
            const canvas = document.createElement('canvas');
            const context = canvas.getContext('2d', { alpha: false });
            
            canvas.width = viewport.width;
            canvas.height = viewport.height;
            canvas.style.width = '100%';
            canvas.style.height = 'auto';
            canvas.style.opacity = '0';
            canvas.style.transition = 'opacity 0.3s ease-in-out';

            const renderContext = {
                canvasContext: context,
                viewport: viewport
            };

            const renderTask = page.render(renderContext);
            
            // Store task immediately
            this.activePages.set(pageNum, { renderTask, canvas });

            await renderTask.promise;
            
            // Ensure no duplicate canvases exist before appending
            const existingCanvas = container.querySelector('canvas');
            if (existingCanvas) existingCanvas.remove();

            container.appendChild(canvas);
            requestAnimationFrame(() => {
                canvas.style.opacity = '1';
                const loader = container.querySelector('.page-loader');
                if (loader) {
                    loader.style.opacity = '0';
                    setTimeout(() => loader.remove(), 300);
                }
            });

            // Process render queue if there's space
            if (this.options.renderQueue.length > 0 && this.renderingPages.size < this.options.maxConcurrentRenders) {
                const next = this.options.renderQueue.shift();
                if (next) {
                    this.renderPage(next.pageNum, next.container);
                }
            }

        } catch (err) {
            if (err.name === 'RenderingCancelledException') return;
            console.warn(`Render error page ${pageNum}:`, err);
            this.activePages.delete(pageNum); // Clean up if failed
            
            // Try next in queue even if this failed
            if (this.options.renderQueue.length > 0) {
                const next = this.options.renderQueue.shift();
                if (next) {
                    this.renderPage(next.pageNum, next.container);
                }
            }
        } finally {
            // Release the lock
            this.renderingPages.delete(pageNum);
        }
    }

    destroyPage(pageNum) {
        const item = this.activePages.get(pageNum);
        if (!item) return;

        if (item.renderTask) item.renderTask.cancel();
        if (item.canvas) item.canvas.remove();
        
        // Reset placeholder state (add loader back if needed)
        const container = document.getElementById(`vsd-page-${pageNum}`);
        if (container && !container.querySelector('.page-loader')) {
            const loader = document.createElement('div');
            loader.className = 'page-loader absolute inset-0 flex flex-col items-center justify-center bg-base-100 z-10';
            loader.innerHTML = `<span class="loading loading-spinner loading-md text-primary opacity-30"></span>`;
            container.appendChild(loader);
        }

        this.activePages.delete(pageNum);
    }
}
