/**
 * FilterController - Manages filter UI and state
 * 
 * Handles all filter-related functionality that can be shared between:
 * - Search page (with full search UI)
 * - Manage/media page (filters only)
 * - Future pages with filtering capabilities
 */

class FilterController {
    constructor(options = {}) {
        this.mode = options.mode || 'url-driven'; // 'url-driven' or 'embedded'
        this.baseUrl = options.baseUrl || 'search';
        this.onFilterChange = options.onFilterChange || null;
        
        // Date range configuration - use fallback date, actual minDate calculated dynamically from electoral periods
        this.minDate = new Date("2013-10-01");
        this.maxDate = new Date();
        
        // Chart instances
        this.factionChart = null;
        this.timelineViz = null;
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.initDateRangeSlider();
        this.initParliamentSelectMenu();
    }
    
    /**
     * Bind all filter-related events
     */
    bindEvents() {
        // Faction checkboxes, status dropdowns
        $('[name="factionID[]"], [name="numberOfTexts"], [name="aligned"], [name="public"]').off('change.filterController').on('change.filterController', () => {
            this.triggerFilterUpdate();
        });
        
        // Agenda item title search
        $('[name="agendaItemTitle"]').off('keyup.filterController').on('keyup.filterController', this.debounce(() => {
            this.triggerFilterUpdate();
        }, 1000));
        
        // Sort dropdown (delegated to handle dynamically loaded content)
        $('main').off('change.filterController', '[name="sort"]').on('change.filterController', '[name="sort"]', () => {
            this.triggerFilterUpdate();
        });
        
        // Faction hover effects for result highlighting (disabled on mobile)
        if (!config.isMobile) {
            $('#filterForm .formCheckbox').hover(
                function() {
                    $('.resultItem, #filterForm .formCheckbox').addClass('inactive');
                    $('.resultItem[data-faction="'+ $(this).children('input').val() +'"]').removeClass('inactive');
                    $(this).removeClass('inactive');
                },
                function() {
                    $('.resultItem, #filterForm .formCheckbox').removeClass('inactive');
                }
            );
        }
    }
    
    /**
     * Initialize date range slider with timeline
     */
    initDateRangeSlider() {
        const self = this;
        const options = { year: 'numeric', month: '2-digit', day: '2-digit' };
        
        // Get initial values from URL or use defaults
        const queryFrom = getQueryVariable('dateFrom');
        const queryTo = getQueryVariable('dateTo');
        const queryFromDate = queryFrom ? new Date(queryFrom) : this.minDate;
        const queryToDate = queryTo ? new Date(queryTo) : this.maxDate;
        
        if ($('.sliderRange').length > 0) {
            $('.sliderRange').slider({
                range: true,
                min: this.minDate.getTime(),
                max: this.maxDate.getTime(),
                slide: function (event, ui) {
                    const date1 = new Date(ui.values[0]);
                    const date2 = new Date(ui.values[1]);
                    
                    const date2String = (date2.toISOString().slice(0,10) === self.maxDate.toISOString().slice(0,10)) 
                        ? localizedLabels.today 
                        : date2.toLocaleDateString('de-DE', options);
                    
                    $("#timeRange").val(date1.toLocaleDateString('de-DE', options) + " - " + date2String);
                    $('#dateFrom').val(date1.toISOString().slice(0,10));
                    $('#dateTo').val(date2.toISOString().slice(0,10));
                },
                stop: function (event, ui) {
                    self.triggerFilterUpdate();
                },
                values: [queryFromDate.getTime(), queryToDate.getTime()]
            });
            
            // Set initial display values
            const startDate = queryFromDate.toLocaleDateString('de-DE', options);
            const endDate = queryToDate.toLocaleDateString('de-DE', options);
            const endDateString = (endDate === self.maxDate.toLocaleDateString('de-DE', options)) 
                ? localizedLabels.today 
                : endDate;
            
            $("#timeRange").val(startDate + " - " + endDateString);
            $('#dateFrom').val(queryFromDate.toISOString().slice(0,10));
            $('#dateTo').val(queryToDate.toISOString().slice(0,10));
        }
    }
    
    /**
     * Initialize parliament selection menu
     */
    initParliamentSelectMenu() {
        const self = this;
        
        $('.parliamentFilterContainer').off('change.filterController', 'select').on('change.filterController', 'select', function(evt) {
            const targetSelectMenu = $(evt.currentTarget);
            
            if (targetSelectMenu.attr('name') === 'parliament') {
                $('.parliamentFilterContainer #selectElectoralPeriod').remove();
                $('.parliamentFilterContainer #selectSession').remove();
            } else if (targetSelectMenu.attr('name') === 'electoralPeriod') {
                $('.parliamentFilterContainer #selectSession').remove();
            }
            
            self.triggerFilterUpdate();
            
            // Update dependent dropdowns
            $.ajax({
                method: "POST",
                url: "./content/pages/search/content.filter.parliaments.php?" + self.getSerializedForm()
            }).done(function(data) {
                $('.parliamentFilterContainer').html($(data));
            }).fail(function(err) {
                console.warn('Failed to update parliament filters', err);
            });
        });
    }
    
    /**
     * Update filters from URL parameters
     */
    updateFromUrl() {
        // Handle entity types
        const entityTypes = ['person', 'organisation', 'document', 'term'];
        for (const entityType of entityTypes) {
            const paramName = entityType + 'ID[]';
            $('#filterForm input[name="' + paramName + '"]').remove();
            
            const entityIDs = getQueryVariable(entityType + 'ID');
            if (Array.isArray(entityIDs)) {
                entityIDs.forEach(id => {
                    $('#filterForm').append(`<input type="hidden" name="${paramName}" value="${id}">`);
                });
            } else if (entityIDs) {
                $('#filterForm').append(`<input type="hidden" name="${paramName}" value="${entityIDs}">`);
            }
        }
        
        // Update form fields
        $('[name="context"]').val(getQueryVariable('context') || '');
        $('[name="sort"]').val(getQueryVariable('sort') || 'relevance');
        
        // Update faction checkboxes
        $('[name="factionID[]"]').each(function() {
            this.checked = false;
        });
        
        const factionQueries = getQueryVariable('factionID');
        if (factionQueries) {
            const factions = Array.isArray(factionQueries) ? factionQueries : [factionQueries];
            factions.forEach(faction => {
                const cleanValue = faction.replace('+', ' ').toUpperCase();
                const checkbox = $(`[name="factionID[]"][value="${cleanValue}"]`);
                if (checkbox.length > 0) {
                    checkbox.prop('checked', true);
                } else {
                    console.warn('FilterController: Faction checkbox not found for value:', cleanValue);
                }
            });
        }
        
        // Update other filter fields
        $('[name="agendaItemTitle"]').val(getQueryVariable('agendaItemTitle') || '');
        
        // Update dropdown filters
        $('[name="aligned"]').val(getQueryVariable('aligned') || '');
        $('[name="public"]').val(getQueryVariable('public') || '');
        $('[name="numberOfTexts"]').val(getQueryVariable('numberOfTexts') || '');
        
        // Update parliament-related filters
        $('[name="parliament"]').val(getQueryVariable('parliament') || 'all');
        $('[name="electoralPeriod"]').val(getQueryVariable('electoralPeriod') || 'all');
        $('[name="sessionNumber"]').val(getQueryVariable('sessionNumber') || 'all');
        
        // Refresh charts and visualizations
        this.updateFactionChart();
        this.updateTimelineViz();
    }
    
    /**
     * Update faction chart (delegated to global function)
     */
    updateFactionChart() {
        if (typeof updateFactionChart === 'function') {
            updateFactionChart();
        }
    }
    
    /**
     * Update timeline visualization (delegated to global function)
     */
    updateTimelineViz() {
        if (typeof updateTimelineViz === 'function') {
            $('#timelineVizWrapper').empty();
            updateTimelineViz();
        }
    }
    
    /**
     * Trigger filter update callback
     */
    triggerFilterUpdate() {
        if (this.onFilterChange) {
            const formData = this.getSerializedForm();
            this.onFilterChange(formData);
        }
    }
    
    /**
     * Get serialized form data for API requests
     */
    getSerializedForm() {
        const formData = $('#filterForm :input, select[name="sort"]').filter((index, element) => {
            const $el = $(element);
            const name = $el.attr('name');
            const value = $el.val();
            
            // Skip empty values and default values
            if (!value || value === '') return false;
            if (name === 'dateFrom' && value === this.minDate.toISOString().slice(0,10)) return false;
            if (name === 'dateTo' && value === this.maxDate.toISOString().slice(0,10)) return false;
            if (name === 'sort' && value === 'relevance') return false;
            if (name === 'edit-query') return false;
            if (name === 'parliament' && value === 'all') return false;
            if (name === 'electoralPeriod' && value === 'all') return false;
            if (name === 'sessionNumber' && value === 'all') return false;
            
            return true;
        }).serialize();
        
        return formData;
    }
    
    /**
     * Utility: Debounce function
     */
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func.apply(this, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    /**
     * Clean up event listeners
     */
    destroy() {
        $('[name="factionID[]"], [name="numberOfTexts"], [name="aligned"], [name="public"]').off('change.filterController');
        $('[name="agendaItemTitle"]').off('keyup.filterController');
        $('main').off('change.filterController', '[name="sort"]');
        $('.parliamentFilterContainer').off('change.filterController', 'select');
        $('#filterForm .formCheckbox').off('mouseenter mouseleave');
    }
}

// Export for global use
window.FilterController = FilterController;