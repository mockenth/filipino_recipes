// Main JavaScript for TastyKitchen
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 TastyKitchen starting...');
    initializeApp();
});

function initializeApp() {
    // Initialize any global functionality
    setupEventListeners();
    checkAuthStatus();
    loadRecipes();
}

function setupEventListeners() {
    // Search functionality
    const searchInput = document.querySelector('.search-input');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            // Real-time search could be implemented here
            console.log('Search:', this.value);
        });
    }

    // Filter buttons
    const filterButtons = document.querySelectorAll('.filter-btn');
    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all buttons in the same group
            const parent = this.closest('.filter-group');
            if (parent) {
                parent.querySelectorAll('.filter-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
            }
            // Add active class to clicked button
            this.classList.add('active');
        });
    });

    // Image preview for file inputs
    const imageInputs = document.querySelectorAll('input[type="file"]');
    imageInputs.forEach(input => {
        input.addEventListener('change', function(e) {
            const file = this.files[0];
            if (file) {
                previewImage(file, this);
            }
        });
    });
}

function checkAuthStatus() {
    // Check if user is logged in (this would typically be handled by PHP sessions)
    // For demo purposes, we'll just log the status
    console.log('Auth status checked');
}

function loadRecipes() {
    const recipesGrid = document.getElementById('recipes-grid');
    if (!recipesGrid) return;

    // Recipes are loaded via PHP, but we can add any client-side enhancements here
    console.log('Recipes loaded successfully');
}

function previewImage(file, inputElement) {
    // Create preview container if it doesn't exist
    let previewContainer = inputElement.parentNode.querySelector('.image-preview');
    if (!previewContainer) {
        previewContainer = document.createElement('div');
        previewContainer.className = 'image-preview';
        inputElement.parentNode.appendChild(previewContainer);
    }

    // Clear previous preview
    previewContainer.innerHTML = '';

    // Create and show preview
    const reader = new FileReader();
    reader.onload = function(e) {
        const img = document.createElement('img');
        img.src = e.target.result;
        img.style.maxWidth = '200px';
        img.style.maxHeight = '200px';
        img.style.marginTop = '10px';
        img.style.borderRadius = '4px';
        previewContainer.appendChild(img);
    };
    reader.readAsDataURL(file);
}

// Utility function to show toast notifications
function showToast(message, type = 'success') {
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <span>${message}</span>
    `;

    // Add styles if not already added
    if (!document.querySelector('#toast-styles')) {
        const styles = document.createElement('style');
        styles.id = 'toast-styles';
        styles.textContent = `
            .toast {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                background: var(--success-color);
                color: white;
                border-radius: 4px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                z-index: 1000;
                display: flex;
                align-items: center;
                transform: translateX(150%);
                transition: transform 0.3s ease;
            }
            .toast.error {
                background: var(--danger-color);
            }
            .toast.show {
                transform: translateX(0);
            }
            .toast i {
                margin-right: 10px;
            }
        `;
        document.head.appendChild(styles);
    }

    // Add to page
    document.body.appendChild(toast);

    // Show toast
    setTimeout(() => toast.classList.add('show'), 100);

    // Remove after 3 seconds
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Form validation helper
function validateForm(form) {
    const inputs = form.querySelectorAll('input[required], textarea[required], select[required]');
    let isValid = true;

    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.style.borderColor = 'var(--danger-color)';
            isValid = false;
        } else {
            input.style.borderColor = '';
        }
    });

    return isValid;
}

// Password strength checker
function checkPasswordStrength(password) {
    const strongRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#\$%\^&\*])(?=.{8,})/;
    const mediumRegex = /^(((?=.*[a-z])(?=.*[A-Z]))|((?=.*[a-z])(?=.*[0-9]))|((?=.*[A-Z])(?=.*[0-9])))(?=.{6,})/;

    if (strongRegex.test(password)) {
        return 'strong';
    } else if (mediumRegex.test(password)) {
        return 'medium';
    } else {
        return 'weak';
    }
}

// Export functions for use in other modules
window.TastyKitchen = {
    showToast,
    validateForm,
    checkPasswordStrength
};

// Mobile logout button visibility fix
function ensureLogoutButtonVisible() {
    const logoutItem = document.querySelector('.logout-item');
    const logoutBtn = document.querySelector('.logout-btn-sidebar');
    const navLinks = document.querySelector('.nav-links');
    const sidebar = document.querySelector('.sidebar');
    
    if (logoutItem && logoutBtn) {
        // Force visibility
        logoutItem.style.display = 'block';
        logoutItem.style.visibility = 'visible';
        logoutItem.style.opacity = '1';
        logoutItem.style.position = 'relative';
        
        logoutBtn.style.display = 'flex';
        logoutBtn.style.visibility = 'visible';
        logoutBtn.style.opacity = '1';
        
        // Ensure it's at the bottom
        if (navLinks) {
            navLinks.style.display = 'flex';
            navLinks.style.flexDirection = 'column';
            navLinks.style.minHeight = '400px';
        }
        
        // Ensure sidebar is scrollable
        if (sidebar) {
            sidebar.style.overflowY = 'auto';
            sidebar.style.maxHeight = '100vh';
        }
        
        console.log('✅ Logout button visibility enforced');
    } else {
        console.log('❌ Logout button elements not found');
    }
}

// Call this function on page load and resize
document.addEventListener('DOMContentLoaded', function() {
    ensureLogoutButtonVisible();
    
    // Re-check on resize
    window.addEventListener('resize', ensureLogoutButtonVisible);
    
    // Re-check after a short delay to ensure everything is loaded
    setTimeout(ensureLogoutButtonVisible, 1000);
});

// Update your existing initializeApp function
function initializeApp() {
    console.log('🚀 TastyKitchen starting...');
    setupEventListeners();
    checkAuthStatus();
    loadRecipes();
    
    // Ensure logout button is visible
    ensureLogoutButtonVisible();
}