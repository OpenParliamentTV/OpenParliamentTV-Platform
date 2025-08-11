/**
 * MediaResults - Universal Media Result Display Manager
 * 
 * Handles loading, pagination, and sorting of media search results across all contexts:
 * - URL-driven pages (search, manage-media) 
 * - Embedded views (entity pages with multiple result containers)
 * 
 * Supports both grid and table views, with context-aware pagination.
 */

class MediaResultsManager {
    constructor(options = {}) {
        this.container = options.container || '#speechListContainer';
        this.mode = options.mode || 'embedded'; // 'url-driven' or 'embedded'
        this.view = options.view || 'grid'; // 'grid' or 'table'
        this.baseUrl = options.baseUrl || 'search'; // for URL-driven mode
        this.currentQuery = options.query || '';
        this.isLoading = false;
        
        this.init();
    }
    
    init() {
        // Only initialize if container exists
        if ($(this.container).length === 0) {
            console.warn('MediaResults: Container not found:', this.container);
            return;
        }
        
        // Initial setup - events will be bound when results are loaded
    }
    
    /**
     * Load media results with given query parameters
     */
    loadResults(query = '', updateHistory = true) {
        if (this.isLoading) {
            return;
        }
        
        this.isLoading = true;
        this.currentQuery = query;
        
        $(this.container + ' .loadingIndicator').show();
        
        // Determine component URL and parameters
        const componentUrl = config.dir.root + `/content/components/result.${this.view}.php`;
        const includeAllParam = this.view === 'table' ? '&includeAll=true' : '';
        const paginationModeParam = `&paginationMode=${this.mode}&baseUrl=${this.baseUrl}`;
        
        // Check if we should show home intro (only for search page with no valid criteria)
        const showHomeParam = this.shouldShowHome(query) ? '&showHome=1' : '';
        
        $.ajax({
            method: "POST",
            url: `${componentUrl}?a=search${includeAllParam}&queryOnly=1${paginationModeParam}${showHomeParam}&${query}`
        }).done((data) => {
            $(this.container + ' .resultWrapper').html($(data));
            $(this.container + ' .loadingIndicator').hide();
            
            // Set sort dropdown value
            const sortValue = this.getQueryParam('sort', query) || 'relevance';
            $(this.container + ' [name="sort"]').val(sortValue);
            
            this.setupPagination();
            this.setupSorting();
            this.setupPlayButton();
            
            // Update URL for URL-driven mode
            if (this.mode === 'url-driven' && updateHistory) {
                this.updateBrowserHistory(query);
            }
            
            this.isLoading = false;
            
            // Update filter bar visibility based on search criteria
            this.updateFilterBarVisibility(query);
            
            // Trigger callback if provided
            if (this.onResultsLoaded) {
                this.onResultsLoaded(data);
            }
            
        }).fail((err) => {
            console.error('MediaResults: Failed to load results', err);
            $(this.container + ' .loadingIndicator').hide();
            this.isLoading = false;
        });
    }
    
    /**
     * Setup pagination based on mode
     */
    setupPagination() {
        const self = this;
        
        if (this.mode === 'embedded') {
            // Convert pagination links to hash-based for embedded mode
            $(this.container + ' .resultWrapper > nav a').each(function() {
                const href = $(this).attr('href');
                if (href && href.startsWith('#page=')) {
                    return; // Already hash-based
                }
                
                const page = self.getQueryParam('page', href) || '1';
                $(this).attr('href', '#page=' + page);
            });
        }
        
        // Bind pagination click events
        $(this.container + ' .resultWrapper > nav a').off('click.mediaResults').on('click.mediaResults', function(evt) {
            evt.preventDefault();
            evt.stopPropagation();
            
            const href = $(this).attr('href');
            let page = 1;
            
            if (href.startsWith('#page=')) {
                page = href.split('#page=')[1];
            } else {
                page = self.getQueryParam('page', href) || '1';
            }
            
            self.goToPage(page);
        });
    }
    
    /**
     * Setup sorting dropdown
     */
    setupSorting() {
        const self = this;
        
        $(this.container + ' [name="sort"]').off('change.mediaResults').on('change.mediaResults', function() {
            const sortValue = $(this).val();
            self.updateSort(sortValue);
        });
    }
    
    /**
     * Setup play button
     */
    setupPlayButton() {
        const self = this;
        
        $(this.container + ' #play-submit').off('click.mediaResults').on('click.mediaResults', function() {
            const firstResult = $(self.container + ' .resultList').find('.resultItem').first();
            
            if (firstResult.length > 0) {
                const href = firstResult.find('.resultContent a').eq(0).attr('href');
                if (href) {
                    location.href = href + '&playresults=1';
                }
            }
        });
    }
    
    /**
     * Navigate to specific page
     */
    goToPage(page) {
        const newQuery = this.updateQueryParam(this.currentQuery, 'page', page);
        this.loadResults(newQuery);
    }
    
    /**
     * Update sort order
     */
    updateSort(sortValue) {
        let newQuery = this.removeQueryParam(this.currentQuery, 'sort');
        if (sortValue && sortValue !== 'relevance') {
            newQuery = this.updateQueryParam(newQuery, 'sort', sortValue);
        }
        this.loadResults(newQuery);
    }
    
    /**
     * Update browser history for URL-driven mode
     */
    updateBrowserHistory(query) {
        if (this.mode === 'url-driven') {
            // Ensure baseUrl starts with / for absolute path
            const baseUrl = this.baseUrl.startsWith('/') ? this.baseUrl : '/' + this.baseUrl;
            const newUrl = baseUrl + (query ? '?' + query : '');
            history.pushState(null, '', newUrl);
        }
    }
    
    /**
     * Determine if home intro should be shown (search page only, no valid criteria)
     */
    shouldShowHome(query) {
        // Only show home intro on search page
        if (this.baseUrl !== 'search' && this.baseUrl !== '/search') {
            return false;
        }
        
        // Check if there are valid search criteria in the query
        const urlParams = new URLSearchParams(query);
        const hasValidCriteria = urlParams.get('q') || 
                                urlParams.get('personID') || urlParams.has('personID[]') ||
                                urlParams.get('organisationID') || urlParams.has('organisationID[]') ||
                                urlParams.get('documentID') || urlParams.has('documentID[]') ||
                                urlParams.get('termID') || urlParams.has('termID[]') ||
                                urlParams.get('sessionID') || urlParams.has('sessionID[]') ||
                                urlParams.get('agendaItemID') || urlParams.has('agendaItemID[]') ||
                                urlParams.get('electoralPeriodID') || urlParams.has('electoralPeriodID[]');
        
        return !hasValidCriteria;
    }
    
    /**
     * Update filter bar visibility based on search criteria
     * Shows filter bar when there are valid search criteria (opposite of shouldShowHome logic)
     */
    updateFilterBarVisibility(query) {
        // Show filter container when there are valid search criteria
        // (inverse of shouldShowHome logic)
        const shouldShowFilters = !this.shouldShowHome(query);
        
        if (shouldShowFilters) {
            $('.filterContainer').css('display', '');
        } else {
            $('.filterContainer').hide();
        }
    }
    
    /**
     * Utility: Extract parameter value from query string
     */
    getQueryParam(param, queryString) {
        if (!queryString) return null;
        
        const urlParams = new URLSearchParams(queryString.split('?')[1] || queryString);
        return urlParams.get(param);
    }
    
    /**
     * Utility: Update parameter in query string
     */
    updateQueryParam(queryString, param, value) {
        const separator = queryString.includes('?') ? '&' : (queryString ? '&' : '');
        const cleanQuery = this.removeQueryParam(queryString, param);
        return cleanQuery + separator + param + '=' + encodeURIComponent(value);
    }
    
    /**
     * Utility: Remove parameter from query string
     */
    removeQueryParam(queryString, param) {
        if (!queryString) return '';
        
        return queryString.replace(new RegExp(`[?&]${param}=[^&]*`, 'g'), '')
                         .replace(/^&/, '?')
                         .replace(/[?&]$/, '');
    }
    
    /**
     * Set callback for when results are loaded
     */
    onLoaded(callback) {
        this.onResultsLoaded = callback;
    }
    
    /**
     * Get current query
     */
    getCurrentQuery() {
        return this.currentQuery;
    }
    
    /**
     * Destroy the manager and clean up events
     */
    destroy() {
        $(this.container + ' .resultWrapper > nav a').off('click.mediaResults');
        $(this.container + ' [name="sort"]').off('change.mediaResults');
        $(this.container + ' #play-submit').off('click.mediaResults');
    }
}

// Global instances registry for multiple managers on same page
window.mediaResultsManagers = window.mediaResultsManagers || {};

/**
 * Factory function to create or get MediaResults manager
 */
function getMediaResultsManager(containerId, options = {}) {
    const id = containerId.replace('#', '');
    
    if (!window.mediaResultsManagers[id]) {
        window.mediaResultsManagers[id] = new MediaResultsManager({
            container: containerId,
            ...options
        });
    }
    
    return window.mediaResultsManagers[id];
}

/**
 * Legacy compatibility function - replaces updateMediaList from searchResults.js
 */
function updateMediaList(query, targetSelector) {
    targetSelector = targetSelector || '#speechListContainer';
    
    const manager = getMediaResultsManager(targetSelector, {
        mode: 'embedded',
        view: 'grid'
    });
    
    manager.loadResults(query, false);
}