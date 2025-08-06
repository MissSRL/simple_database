let currentScreenshot = 1;
const totalScreenshots = 6;
let baseUrl = '';


const screenshots = {
    1: {
        title: "Query Builder"
    },
    2: {
        title: "Backup & Restore"
    },
    3: {
        title: "Data Management"
    },
    4: {
        title: "Advanced Updates"
    },
    5: {
        title: "Advanced Deletions"
    },
};

document.addEventListener('DOMContentLoaded', function() {
    
    const scriptTags = document.getElementsByTagName('script');
    for (let script of scriptTags) {
        if (script.src && script.src.includes('homepage.js')) {
            baseUrl = script.src.replace('/assets/js/homepage.js', '/');
            break;
        }
    }
    
    
    if (!baseUrl) {
        baseUrl = window.location.pathname.replace(/\/[^\/]*$/, '/');
        if (!baseUrl.endsWith('/')) baseUrl += '/';
    }
    
    initializeScreenshotGallery();
});

function initializeScreenshotGallery() {
    
    const thumbnails = document.querySelectorAll('.screenshot-thumb');
    thumbnails.forEach(thumb => {
        thumb.addEventListener('click', function() {
            const screenshotNumber = parseInt(this.getAttribute('data-screenshot'));
            openScreenshotModal(screenshotNumber);
        });
        
        
        thumb.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                const screenshotNumber = parseInt(this.getAttribute('data-screenshot'));
                openScreenshotModal(screenshotNumber);
            }
        });
        
        
        thumb.setAttribute('tabindex', '0');
        thumb.setAttribute('role', 'button');
    });
    
    
    const prevBtn = document.getElementById('prevModalBtn');
    const nextBtn = document.getElementById('nextModalBtn');
    
    if (prevBtn) {
        prevBtn.addEventListener('click', showPreviousScreenshot);
    }
    
    if (nextBtn) {
        nextBtn.addEventListener('click', showNextScreenshot);
    }
    
    
    const modal = document.getElementById('screenshotModal');
    if (modal) {
        modal.addEventListener('hidden.bs.modal', function() {
            
            const backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(backdrop => backdrop.remove());
            
            
            document.body.classList.remove('modal-open');
            
            
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        });
        
        modal.addEventListener('shown.bs.modal', function() {
            
            modal.focus();
        });
        
        
        const closeBtn = modal.querySelector('.btn-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                setTimeout(forceModalCleanup, 300);
            });
        }
    }
    
    
    document.addEventListener('keydown', function(e) {
        const modal = document.getElementById('screenshotModal');
        if (modal && modal.classList.contains('show')) {
            switch(e.key) {
                case 'ArrowLeft':
                    e.preventDefault();
                    showPreviousScreenshot();
                    break;
                case 'ArrowRight':
                    e.preventDefault();
                    showNextScreenshot();
                    break;
                case 'Escape':
                    e.preventDefault();
                    closeScreenshotModal();
                    break;
            }
        }
    });
}

function openScreenshotModal(screenshotNumber) {
    currentScreenshot = screenshotNumber;
    updateModalContent();
    
    
    const modal = new bootstrap.Modal(document.getElementById('screenshotModal'));
    modal.show();
}

function updateModalContent() {
    const modalImg = document.getElementById('modalScreenshot');
    const modalTitle = document.getElementById('screenshotModalLabel');
    const counter = document.getElementById('screenshotCounter');
    const prevBtn = document.getElementById('prevModalBtn');
    const nextBtn = document.getElementById('nextModalBtn');
    const modalBody = document.querySelector('.modal-body');
    
    
    if (modalBody) {
        modalBody.classList.add('loading');
    }
    
    if (modalImg) {
        modalImg.classList.add('loading');
        
        
        const newImg = new Image();
        newImg.onload = function() {
            modalImg.src = this.src;
            modalImg.alt = screenshots[currentScreenshot].title;
            modalImg.classList.remove('loading');
            if (modalBody) {
                modalBody.classList.remove('loading');
            }
        };
        newImg.onerror = function() {
            modalImg.alt = "Image could not be loaded";
            modalImg.classList.remove('loading');
            if (modalBody) {
                modalBody.classList.remove('loading');
            }
        };
        newImg.src = `${baseUrl}images/5earleDatabase${currentScreenshot}.png`;
    }
    
    if (modalTitle) {
        modalTitle.textContent = screenshots[currentScreenshot].title;
    }
    
    if (counter) {
        counter.textContent = `${currentScreenshot} of ${totalScreenshots}`;
    }
    
    
    if (prevBtn) {
        prevBtn.disabled = currentScreenshot === 1;
        prevBtn.style.opacity = currentScreenshot === 1 ? '0.5' : '1';
    }
    
    if (nextBtn) {
        nextBtn.disabled = currentScreenshot === totalScreenshots;
        nextBtn.style.opacity = currentScreenshot === totalScreenshots ? '0.5' : '1';
    }
}

function showPreviousScreenshot() {
    if (currentScreenshot > 1) {
        currentScreenshot--;
        updateModalContent();
    }
}

function showNextScreenshot() {
    if (currentScreenshot < totalScreenshots) {
        currentScreenshot++;
        updateModalContent();
    }
}


function forceModalCleanup() {
    
    const backdrops = document.querySelectorAll('.modal-backdrop');
    backdrops.forEach(backdrop => {
        backdrop.remove();
    });
    
    
    document.body.classList.remove('modal-open');
    
    
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
    
    
    document.documentElement.style.overflow = '';
}


function closeScreenshotModal() {
    const modal = document.getElementById('screenshotModal');
    if (modal) {
        const bsModal = bootstrap.Modal.getInstance(modal);
        if (bsModal) {
            bsModal.hide();
        }
    }
    
    
    setTimeout(forceModalCleanup, 300);
}


function addScreenshotHoverEffects() {
    const thumbnails = document.querySelectorAll('.screenshot-thumb');
    
    thumbnails.forEach(thumb => {
        thumb.addEventListener('mouseenter', function() {
            this.style.filter = 'brightness(1.1)';
        });
        
        thumb.addEventListener('mouseleave', function() {
            this.style.filter = 'brightness(1)';
        });
    });
}


document.addEventListener('DOMContentLoaded', addScreenshotHoverEffects);


window.screenshotGallery = {
    openModal: openScreenshotModal,
    closeModal: closeScreenshotModal,
    next: showNextScreenshot,
    previous: showPreviousScreenshot,
    cleanup: forceModalCleanup
};
