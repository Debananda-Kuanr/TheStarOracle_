/**
 * The Star Oracle - Main JavaScript Module
 * Core functionality and utilities
 */

// ===========================================
// Configuration
// ===========================================
const CONFIG = {
    API_BASE: '../backend/api',
    NASA_API_KEY: 'QQ7SB7m487vCmeNr9KHy26qgYxrhFBKDrksYwEaO',
    NASA_API_URL: 'https://api.nasa.gov/neo/rest/v1/feed',
    NASA_NEO_LOOKUP: 'https://api.nasa.gov/neo/rest/v1/neo/',
    SESSION_KEY: 'token',
    USER_KEY: 'user',
    REFRESH_INTERVAL: 300000 // 5 minutes
};

// ===========================================
// Authentication Module
// ===========================================
const Auth = {
    /**
     * Check if user is authenticated
     */
    isAuthenticated() {
        return !!localStorage.getItem(CONFIG.SESSION_KEY);
    },

    /**
     * Get current user data
     */
    getUser() {
        const userData = localStorage.getItem(CONFIG.USER_KEY);
        return userData ? JSON.parse(userData) : null;
    },

    /**
     * Get authentication token
     */
    getToken() {
        return localStorage.getItem(CONFIG.SESSION_KEY);
    },

    /**
     * Store authentication data
     */
    setAuth(token, user) {
        localStorage.setItem(CONFIG.SESSION_KEY, token);
        localStorage.setItem(CONFIG.USER_KEY, JSON.stringify(user));
    },

    /**
     * Clear authentication data
     */
    clearAuth() {
        localStorage.clear();
        sessionStorage.clear();
    },

    /**
     * Logout user
     */
    logout() {
        API.post('/logout.php').catch(() => {});
        this.clearAuth();
        window.location.replace('index.html');
    },

    /**
     * Require authentication (redirect if not logged in)
     */
    requireAuth(redirectUrl = 'index.html') {
        if (!this.isAuthenticated()) {
            window.location.replace(redirectUrl);
            return false;
        }
        return true;
    },

    /**
     * Require researcher role
     */
    requireResearcher(redirectUrl = 'dashboard.html') {
        const user = this.getUser();
        if (!user || (user.role !== 'researcher' && user.role !== 'admin')) {
            window.location.href = redirectUrl;
            return false;
        }
        return true;
    }
};

// ===========================================
// API Module
// ===========================================
const API = {
    /**
     * Make authenticated API request
     */
    async request(endpoint, options = {}) {
        const token = Auth.getToken();
        
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                ...(token && { 'Authorization': `Bearer ${token}` })
            }
        };

        const response = await fetch(`${CONFIG.API_BASE}${endpoint}`, {
            ...defaultOptions,
            ...options,
            headers: {
                ...defaultOptions.headers,
                ...options.headers
            }
        });

        if (!response.ok) {
            const error = await response.json().catch(() => ({ message: 'Request failed' }));
            throw new Error(error.message || 'Request failed');
        }

        return response.json();
    },

    /**
     * GET request
     */
    get(endpoint) {
        return this.request(endpoint, { method: 'GET' });
    },

    /**
     * POST request
     */
    post(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    },

    /**
     * PUT request
     */
    put(endpoint, data = {}) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    },

    /**
     * DELETE request
     */
    delete(endpoint) {
        return this.request(endpoint, { method: 'DELETE' });
    }
};

// ===========================================
// NASA API Module
// ===========================================
const NasaAPI = {
    /**
     * Get asteroid feed for date range
     */
    async getFeed(startDate, endDate) {
        const url = `${CONFIG.NASA_API_URL}?start_date=${startDate}&end_date=${endDate}&api_key=${CONFIG.NASA_API_KEY}`;
        const response = await fetch(url);
        
        if (!response.ok) {
            throw new Error('Failed to fetch NASA data');
        }
        
        return response.json();
    },

    /**
     * Get single asteroid details
     */
    async getAsteroid(id) {
        const url = `${CONFIG.NASA_NEO_LOOKUP}${id}?api_key=${CONFIG.NASA_API_KEY}`;
        const response = await fetch(url);
        
        if (!response.ok) {
            throw new Error('Failed to fetch asteroid data');
        }
        
        return response.json();
    },

    /**
     * Get today's asteroids
     */
    async getTodayAsteroids() {
        const today = Utils.formatDate(new Date());
        return this.getFeed(today, today);
    },

    /**
     * Get week's asteroids
     */
    async getWeekAsteroids() {
        const today = new Date();
        const nextWeek = new Date(today);
        nextWeek.setDate(nextWeek.getDate() + 7);
        
        return this.getFeed(Utils.formatDate(today), Utils.formatDate(nextWeek));
    }
};

// ===========================================
// Risk Calculator Module
// ===========================================
const RiskCalculator = {
    /**
     * Calculate risk score (0-100)
     */
    calculateScore(asteroid) {
        const isHazardous = asteroid.is_potentially_hazardous_asteroid;
        const closeApproach = asteroid.close_approach_data?.[0];
        const missDistance = parseFloat(closeApproach?.miss_distance?.lunar) || 999;
        const diameter = (asteroid.estimated_diameter?.kilometers?.estimated_diameter_max + 
                        asteroid.estimated_diameter?.kilometers?.estimated_diameter_min) / 2 || 0;
        const velocity = parseFloat(closeApproach?.relative_velocity?.kilometers_per_hour) || 0;
        
        let score = 0;
        
        // Hazardous flag (40 points)
        if (isHazardous) score += 40;
        
        // Distance scoring (30 points max)
        if (missDistance < 5) score += 30;
        else if (missDistance < 10) score += 20;
        else if (missDistance < 20) score += 10;
        
        // Size scoring (20 points max)
        if (diameter > 0.5) score += 20;
        else if (diameter > 0.1) score += 10;
        else if (diameter > 0.05) score += 5;
        
        // Velocity scoring (10 points max)
        if (velocity > 50000) score += 10;
        else if (velocity > 30000) score += 5;
        
        return Math.min(score, 100);
    },

    /**
     * Get risk level from score
     */
    getLevel(score, isHazardous = false) {
        if (isHazardous || score >= 60) return 'hazardous';
        if (score >= 40) return 'high';
        if (score >= 25) return 'medium';
        if (score >= 10) return 'low';
        return 'safe';
    },

    /**
     * Get risk color class
     */
    getColor(level) {
        const colors = {
            hazardous: 'text-red-400',
            high: 'text-orange-400',
            medium: 'text-yellow-400',
            low: 'text-green-400',
            safe: 'text-cyan-400'
        };
        return colors[level] || 'text-gray-400';
    },

    /**
     * Get risk badge classes
     */
    getBadgeClass(level) {
        const classes = {
            hazardous: 'bg-red-500/20 text-red-400 border border-red-500/30',
            high: 'bg-orange-500/20 text-orange-400 border border-orange-500/30',
            medium: 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/30',
            low: 'bg-green-500/20 text-green-400 border border-green-500/30',
            safe: 'bg-cyan-500/20 text-cyan-400 border border-cyan-500/30'
        };
        return classes[level] || 'bg-gray-500/20 text-gray-400';
    }
};

// ===========================================
// Utilities Module
// ===========================================
const Utils = {
    /**
     * Format date to YYYY-MM-DD
     */
    formatDate(date) {
        return date.toISOString().split('T')[0];
    },

    /**
     * Format date for display
     */
    formatDisplayDate(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    },

    /**
     * Format date with time
     */
    formatDateTime(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    },

    /**
     * Format relative time
     */
    formatRelativeTime(dateStr) {
        const date = new Date(dateStr);
        const now = new Date();
        const diff = date - now;
        
        if (diff < 0) return 'Passed';
        
        const hours = Math.floor(diff / (1000 * 60 * 60));
        const days = Math.floor(hours / 24);
        
        if (days > 0) return `in ${days} day${days > 1 ? 's' : ''}`;
        if (hours > 0) return `in ${hours} hour${hours > 1 ? 's' : ''}`;
        return 'Soon';
    },

    /**
     * Format number with commas
     */
    formatNumber(num) {
        return Number(num).toLocaleString();
    },

    /**
     * Debounce function
     */
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    /**
     * Throttle function
     */
    throttle(func, limit) {
        let inThrottle;
        return function(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    },

    /**
     * Generate unique ID
     */
    generateId() {
        return Date.now().toString(36) + Math.random().toString(36).substr(2);
    },

    /**
     * Copy text to clipboard
     */
    async copyToClipboard(text) {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch (err) {
            console.error('Failed to copy:', err);
            return false;
        }
    }
};

// ===========================================
// UI Module
// ===========================================
const UI = {
    /**
     * Create animated stars background
     */
    createStars(containerId = 'stars', count = 100) {
        const container = document.getElementById(containerId);
        if (!container) return;
        
        container.innerHTML = '';
        
        for (let i = 0; i < count; i++) {
            const star = document.createElement('div');
            star.className = 'star';
            star.style.left = Math.random() * 100 + '%';
            star.style.top = Math.random() * 100 + '%';
            star.style.animationDelay = Math.random() * 3 + 's';
            star.style.width = Math.random() * 2 + 1 + 'px';
            star.style.height = star.style.width;
            container.appendChild(star);
        }
    },

    /**
     * Show loading spinner
     */
    showLoading(targetId) {
        const target = document.getElementById(targetId);
        if (!target) return;
        
        target.innerHTML = `
            <div class="flex items-center justify-center py-12">
                <div class="spinner"></div>
            </div>
        `;
    },

    /**
     * Show error message
     */
    showError(targetId, message = 'An error occurred') {
        const target = document.getElementById(targetId);
        if (!target) return;
        
        target.innerHTML = `
            <div class="text-center py-8">
                <svg class="w-12 h-12 text-red-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <p class="text-red-400">${message}</p>
            </div>
        `;
    },

    /**
     * Show empty state
     */
    showEmpty(targetId, message = 'No data available', icon = null) {
        const target = document.getElementById(targetId);
        if (!target) return;
        
        target.innerHTML = `
            <div class="text-center py-12 text-gray-400">
                ${icon || `
                    <svg class="w-16 h-16 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                    </svg>
                `}
                <p>${message}</p>
            </div>
        `;
    },

    /**
     * Show toast notification
     */
    showToast(message, type = 'info', duration = 3000) {
        const toast = document.createElement('div');
        toast.className = `fixed bottom-4 right-4 z-50 glass rounded-lg px-6 py-4 fade-in ${
            type === 'error' ? 'border-red-500' : 
            type === 'success' ? 'border-green-500' : 
            'border-cyan-500'
        } border`;
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(20px)';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    },

    /**
     * Update user display in sidebar
     */
    updateUserDisplay() {
        const user = Auth.getUser();
        if (!user) return;
        
        const nameEl = document.getElementById('userName');
        const initialEl = document.getElementById('userInitial');
        const roleEl = document.getElementById('userRole');
        
        if (nameEl) nameEl.textContent = user.full_name || user.username || 'User';
        if (initialEl) initialEl.textContent = (user.full_name || user.username || 'U').charAt(0).toUpperCase();
        if (roleEl) roleEl.textContent = user.role === 'researcher' ? 'Researcher' : 'Observer';
    }
};

// ===========================================
// Watchlist Module
// ===========================================
const Watchlist = {
    STORAGE_KEY: 'watchlist',
    
    /**
     * Get watchlist items
     */
    getItems() {
        return JSON.parse(localStorage.getItem(this.STORAGE_KEY) || '[]');
    },

    /**
     * Add item to watchlist
     */
    add(asteroidId) {
        const items = this.getItems();
        if (!items.includes(asteroidId)) {
            items.push(asteroidId);
            localStorage.setItem(this.STORAGE_KEY, JSON.stringify(items));
            return true;
        }
        return false;
    },

    /**
     * Remove item from watchlist
     */
    remove(asteroidId) {
        const items = this.getItems().filter(id => id !== asteroidId);
        localStorage.setItem(this.STORAGE_KEY, JSON.stringify(items));
    },

    /**
     * Check if item is in watchlist
     */
    contains(asteroidId) {
        return this.getItems().includes(asteroidId);
    },

    /**
     * Toggle watchlist item
     */
    toggle(asteroidId) {
        if (this.contains(asteroidId)) {
            this.remove(asteroidId);
            return false;
        } else {
            this.add(asteroidId);
            return true;
        }
    },

    /**
     * Get watchlist count
     */
    count() {
        return this.getItems().length;
    }
};

// ===========================================
// Notification Settings Module
// ===========================================
const NotificationSettings = {
    STORAGE_KEY: 'notificationSettings',
    
    defaults: {
        email: true,
        sms: false,
        push: true,
        threshold: 'medium',
        distance: 10
    },

    /**
     * Get notification settings
     */
    get() {
        const stored = localStorage.getItem(this.STORAGE_KEY);
        return stored ? { ...this.defaults, ...JSON.parse(stored) } : this.defaults;
    },

    /**
     * Save notification settings
     */
    save(settings) {
        localStorage.setItem(this.STORAGE_KEY, JSON.stringify(settings));
    },

    /**
     * Update single setting
     */
    update(key, value) {
        const settings = this.get();
        settings[key] = value;
        this.save(settings);
    },

    /**
     * Request push notification permission
     */
    async requestPushPermission() {
        if ('Notification' in window) {
            const permission = await Notification.requestPermission();
            return permission === 'granted';
        }
        return false;
    }
};

// ===========================================
// Initialize on DOM Ready
// ===========================================
document.addEventListener('DOMContentLoaded', () => {
    // Create stars background if container exists
    UI.createStars();
    
    // Update user display
    UI.updateUserDisplay();
});

// Export modules for use in other scripts
window.StarOracle = {
    CONFIG,
    Auth,
    API,
    NasaAPI,
    RiskCalculator,
    Utils,
    UI,
    Watchlist,
    NotificationSettings
};
