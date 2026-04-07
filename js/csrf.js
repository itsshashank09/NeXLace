/**
 * CSRF Token Manager
 * 
 * Handles fetching, caching, and attaching CSRF tokens to all
 * state-changing fetch requests (POST/PUT/DELETE).
 * 
 * Usage in PHP pages:
 *   Token is read from <meta name="csrf-token"> tag (set by server).
 * 
 * Usage in HTML pages:
 *   Token is fetched from api/get_csrf_token.php on first use.
 * 
 * After including this script, use csrfFetch() as a drop-in replacement for fetch():
 *   const response = await csrfFetch('api/post_job.php', { method: 'POST', ... });
 */

const CsrfManager = {
    _token: null,

    /**
     * Get the current CSRF token.
     * - First checks the <meta name="csrf-token"> tag (PHP pages)
     * - Falls back to fetching from API (HTML pages)
     */
    async getToken() {
        if (this._token) return this._token;

        // Try reading from meta tag (set by PHP pages)
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        if (metaTag && metaTag.content) {
            this._token = metaTag.content;
            return this._token;
        }

        // Fetch from API (for HTML pages)
        try {
            const response = await fetch('api/get_csrf_token.php', {
                credentials: 'same-origin'
            });
            const data = await response.json();
            if (data.success && data.csrf_token) {
                this._token = data.csrf_token;
                return this._token;
            }
        } catch (e) {
            console.error('Failed to fetch CSRF token:', e);
        }

        return null;
    },

    /**
     * Clear the cached token (e.g. after logout or session change)
     */
    clearToken() {
        this._token = null;
    }
};

/**
 * Drop-in replacement for fetch() that automatically attaches the CSRF token
 * to state-changing requests (POST, PUT, DELETE, PATCH).
 * 
 * @param {string} url - The URL to fetch
 * @param {object} options - Standard fetch options
 * @returns {Promise<Response>}
 */
async function csrfFetch(url, options = {}) {
    const method = (options.method || 'GET').toUpperCase();

    // Only attach token for state-changing methods
    if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(method)) {
        const token = await CsrfManager.getToken();
        if (token) {
            options.headers = options.headers || {};
            // Handle both Headers object and plain object
            if (options.headers instanceof Headers) {
                options.headers.set('X-CSRF-Token', token);
            } else {
                options.headers['X-CSRF-Token'] = token;
            }
        }
    }

    // Ensure cookies are sent (needed for session-based CSRF)
    if (!options.credentials) {
        options.credentials = 'same-origin';
    }

    return fetch(url, options);
}
