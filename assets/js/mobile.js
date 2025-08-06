
function scrollToElementOnMobile(elementId, options = {}) {
    if (window.innerWidth <= 768) {
        setTimeout(() => {
            const element = document.getElementById(elementId);
            if (element) {
                element.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'start',
                    ...options
                });
                
                
                if (element.focus && (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA' || element.tagName === 'SELECT')) {
                    element.focus();
                }
            }
        }, 100);
    }
}


function scrollToTopOnMobile() {
    if (window.innerWidth <= 768) {
        setTimeout(() => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }, 100);
    }
}

function toggleMobileSidebar() {
    const sidebar = document.getElementById('mobileSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (sidebar && overlay) {
        const isOpen = sidebar.classList.contains('show');
        
        if (isOpen) {
            closeMobileSidebar();
        } else {
            openMobileSidebar();
        }
    }
}

function openMobileSidebar() {
    const sidebar = document.getElementById('mobileSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (sidebar && overlay) {
        sidebar.classList.add('show');
        overlay.style.display = 'block';
        document.body.style.overflow = 'hidden'; 
        
        
        sidebar.style.transition = 'left 0.3s ease';
        
        
        const firstLink = sidebar.querySelector('.list-group-item');
        if (firstLink) {
            setTimeout(() => firstLink.focus(), 300);
        }
    }
}

function closeMobileSidebar() {
    const sidebar = document.getElementById('mobileSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (sidebar && overlay) {
        sidebar.classList.remove('show');
        overlay.style.display = 'none';
        document.body.style.overflow = ''; 
        
        
        const toggleBtn = document.querySelector('.mobile-toggle-btn');
        if (toggleBtn) {
            toggleBtn.focus();
        }
    }
}


let touchStartX = 0;
let touchStartY = 0;
let touchEndX = 0;
let touchEndY = 0;

function handleTouchStart(e) {
    touchStartX = e.changedTouches[0].screenX;
    touchStartY = e.changedTouches[0].screenY;
}

function handleTouchEnd(e) {
    touchEndX = e.changedTouches[0].screenX;
    touchEndY = e.changedTouches[0].screenY;
    handleSwipeGesture();
}

function handleSwipeGesture() {
    const swipeThreshold = 50;
    const swipeDistanceX = touchEndX - touchStartX;
    const swipeDistanceY = Math.abs(touchEndY - touchStartY);
    
    
    if (Math.abs(swipeDistanceX) > swipeThreshold && swipeDistanceY < 100) {
        const sidebar = document.getElementById('mobileSidebar');
        
        
        if (swipeDistanceX > 0 && touchStartX < 50 && !sidebar.classList.contains('show')) {
            openMobileSidebar();
        }
        
        else if (swipeDistanceX < 0 && sidebar.classList.contains('show')) {
            closeMobileSidebar();
        }
    }
}


function handleSidebarLinkClick() {
    closeMobileSidebar();
}


window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
        closeMobileSidebar();
    }
});


document.addEventListener('DOMContentLoaded', function() {
    
    const sidebarLinks = document.querySelectorAll('.sidebar-mobile .list-group-item');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', handleSidebarLinkClick);
    });
    
    
    const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            const navbarCollapse = document.querySelector('.navbar-collapse');
            if (navbarCollapse && navbarCollapse.classList.contains('show')) {
                const bsCollapse = new bootstrap.Collapse(navbarCollapse, {
                    hide: true
                });
            }
        });
    });
    
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeMobileSidebar();
            
            const navbarCollapse = document.querySelector('.navbar-collapse');
            if (navbarCollapse && navbarCollapse.classList.contains('show')) {
                const bsCollapse = new bootstrap.Collapse(navbarCollapse, {
                    hide: true
                });
            }
        }
    });
    
    
    document.addEventListener('touchstart', handleTouchStart, { passive: true });
    document.addEventListener('touchend', handleTouchEnd, { passive: true });
    
    
    const tableContainers = document.querySelectorAll('.table-responsive');
    tableContainers.forEach(container => {
        container.style.webkitOverflowScrolling = 'touch';
    });
    
    
    const mobileToggleBtn = document.querySelector('.mobile-toggle-btn');
    if (mobileToggleBtn) {
        mobileToggleBtn.addEventListener('click', function() {
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = '';
            }, 150);
        });
    }
    
    
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(button => {
        button.addEventListener('touchstart', function() {
            this.style.transform = 'scale(0.98)';
        }, { passive: true });
        
        button.addEventListener('touchend', function() {
            setTimeout(() => {
                this.style.transform = '';
            }, 100);
        }, { passive: true });
    });
});
