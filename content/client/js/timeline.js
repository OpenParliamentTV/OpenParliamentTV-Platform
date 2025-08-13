/**
 * Timeline Visualization Module
 * 
 * A reusable timeline visualization component that can be used to display time-based data.
 * This module provides functions to initialize and update a timeline visualization.
 */

(function() {
    'use strict';

    /**
     * Timeline Visualization Class
     * 
     * @param {Object} options - Configuration options for the timeline
     * @param {string|HTMLElement} options.container - The container element where the timeline will be rendered
     * @param {Array} options.data - The data to be displayed in the timeline
     * @param {Date} options.minDate - The minimum date for the timeline
     * @param {Date} options.maxDate - The maximum date for the timeline
     * @param {Object} options.styles - Custom styles for the timeline elements
     * @param {Function} options.onItemClick - Callback function when a timeline item is clicked
     * @param {boolean} options.showElectoralPeriods - Whether to show electoral periods
     * @param {Array} options.electoralPeriods - Electoral periods data
     */
    function TimelineViz(options) {
        this.options = options || {};
        this.container = typeof options.container === 'string' 
            ? document.getElementById(options.container) 
            : options.container;
        
        this.data = options.data || [];
        this.minDate = options.minDate || new Date();
        this.maxDate = options.maxDate || new Date();
        this.styles = options.styles || {};
        this.onItemClick = options.onItemClick || function() {};
        this.showElectoralPeriods = options.showElectoralPeriods || false;
        this.electoralPeriods = options.electoralPeriods || [];
        
        this.init();
        this.setupResizeHandler();
    }

    /**
     * Initialize the timeline visualization
     */
    TimelineViz.prototype.init = function() {
        if (!this.container) {
            console.error('Timeline container not found');
            return;
        }
        
        this.render();
    };

    /**
     * Update the timeline with new data
     * 
     * @param {Array} data - The new data to be displayed
     * @param {Date} minDate - The new minimum date
     * @param {Date} maxDate - The new maximum date
     */
    TimelineViz.prototype.update = function(data, minDate, maxDate) {
        if (data) this.data = data;
        if (minDate) this.minDate = minDate;
        if (maxDate) this.maxDate = maxDate;
        
        this.render();
    };

    /**
     * Render the timeline visualization
     */
    TimelineViz.prototype.render = function() {
        // Clear any existing visualization
        this.container.innerHTML = '';
        
        // Check if the container exists
        if (!this.container) {
            return;
        }
        
        // Get container dimensions
        var containerWidth = this.container.clientWidth;
        var containerHeight = this.container.clientHeight;
        
        // Calculate the total number of days in the date range
        let oneDay = 24 * 60 * 60 * 1000;
        let diffDays = Math.round(Math.abs((this.minDate - this.maxDate) / oneDay));
        
        // Find the highest count for scaling
        let highestCountPerDay = 0;
        for (let i = 0; i < this.data.length; i++) {
            if (this.data[i].count > highestCountPerDay) {
                highestCountPerDay = this.data[i].count;
            }
        }
        
        // Prepare data for D3
        var processedData = [];
        for (let i = 0; i < this.data.length; i++) {
            let item = this.data[i];
            let itemDate = new Date(item.date);
            let daysSinceMinDate = Math.round(Math.abs((this.minDate - itemDate) / oneDay));
            let leftPercent = 100 * (daysSinceMinDate / diffDays);
            
            processedData.push({
                date: item.date,
                count: item.count,
                heightPercent: 100 * (item.count / highestCountPerDay),
                leftPercent: leftPercent,
                year: itemDate.getFullYear(),
                month: itemDate.getMonth(),
                day: itemDate.getDate(),
                originalData: item.originalData || {}
            });
        }
        
        // Create a simple wrapper div for the timeline
        var timelineWrapper = d3.select(this.container)
            .append("div")
            .style("width", "100%")
            .style("height", "100%")
            .style("position", "relative");
        
        // Check if electoral periods will be rendered and add class accordingly
        var willShowElectoralPeriods = this.showElectoralPeriods && this.electoralPeriods && this.electoralPeriods.length > 0 && this.areElectoralPeriodsValid();
        if (willShowElectoralPeriods) {
            d3.select(this.container).classed("hasElectoralPeriods", true);
        } else {
            d3.select(this.container).classed("hasElectoralPeriods", false);
        }
        
        // Line 1: Timeline container with data bars (fixed height)
        var timelineContainer = timelineWrapper.append("div")
            .style("width", "100%")
            .style("height", "22px") // Fixed height instead of percentage
            .style("position", "relative");
            
        // Add a horizontal line at the bottom of the timeline
        timelineContainer.append("div")
            .attr("class", "timelineLine")
        
        // Create the timeline bars
        var bars = timelineContainer.selectAll(".timelineBar")
            .data(processedData)
            .enter()
            .append("div")
            .attr("class", "timelineBar")
            .attr("data-date", d => d.date)
            .attr("data-count", d => d.count)
            .style("position", "absolute")
            .style("left", d => d.leftPercent + "%")
            .style("bottom", "0")
            .style("height", d => d.heightPercent + "%");
        
        // Apply custom styles if provided
        if (this.styles.bar) {
            Object.keys(this.styles.bar).forEach(key => {
                bars.style(key, this.styles.bar[key]);
            });
        }
        
        // Add tooltips
        bars.append("title")
            .text(d => d.date + ": " + d.count + " items");
        
        // Add click event if callback is provided
        if (this.onItemClick) {
            bars.on("click", (event, d) => {
                this.onItemClick(d.originalData, event);
            });
        }

        // Line 2: Year timeline (fixed height, hidden on mobile)
        var yearTimeline = timelineWrapper.append("div")
            .attr("class", "yearTimeline d-none d-lg-block")
            .style("width", "100%")
            .style("height", "18px") // Fixed height
            .style("position", "relative");
        
        // Generate years based on minDate and maxDate
        var startYear = this.minDate.getFullYear();
        var endYear = this.maxDate.getFullYear();
        var years = [];
        for (var year = startYear; year <= endYear; year++) {
            years.push(year);
        }
        
        // Add year labels to year timeline
        var yearLabels = yearTimeline.selectAll(".yearLabel")
            .data(years)
            .enter()
            .append("div")
            .attr("class", (d, i) => i === 0 ? "yearLabel d-none" : "yearLabel")
            .style("position", "absolute")
            .style("left", d => {
                // Calculate position based on the first day of the year
                var firstDayOfYear = new Date(d, 0, 1);
                var daysSinceMinDate = Math.round(Math.abs((this.minDate - firstDayOfYear) / oneDay));
                var leftPercent = 100 * (daysSinceMinDate / diffDays);
                
                // Ensure the label is within the container
                return Math.max(0, Math.min(100, leftPercent)) + "%";
            })
            .style("top", "3px")
            .style("padding-left", "4px")
            .text(d => d)
        
        // Apply custom styles to year labels if provided
        if (this.styles.yearLabel) {
            Object.keys(this.styles.yearLabel).forEach(key => {
                yearLabels.style(key, this.styles.yearLabel[key]);
            });
        }
        
        // Add vertical lines to indicate year boundaries
        var yearBoundaries = [];
        for (var year = startYear; year <= endYear; year++) {
            // Calculate position based on the first day of the year
            var firstDayOfYear = new Date(year, 0, 1);
            var daysSinceMinDate = Math.round(Math.abs((this.minDate - firstDayOfYear) / oneDay));
            var leftPercent = 100 * (daysSinceMinDate / diffDays);
            
            // Only add boundaries that are within the visible range
            if (leftPercent >= 0 && leftPercent <= 100) {
                yearBoundaries.push({
                    year: year,
                    leftPercent: leftPercent
                });
            }
        }
        
        // Create a container for the year boundary lines that extends up to timeline container
        var yearLinesContainer = yearTimeline.append("div")
            .style("width", "100%")
            .style("height", "100%")
            .style("position", "absolute")
            .style("pointer-events", "none");
        
        // Add vertical lines for each year boundary
        var yearLines = yearLinesContainer.selectAll(".yearLine")
            .data(yearBoundaries)
            .enter()
            .append("div")
            .attr("class", (d, i) => i === 0 ? "yearLine d-none" : "yearLine")
            .style("position", "absolute")
            .style("left", d => d.leftPercent + "%")
            .style("height", "100%")
            .style("z-index", "1")
        
        // Apply custom styles to year lines if provided
        if (this.styles.yearLine) {
            Object.keys(this.styles.yearLine).forEach(key => {
                yearLines.style(key, this.styles.yearLine[key]);
            });
        }

        // Line 3: Electoral period timeline (fixed height, always visible)
        if (willShowElectoralPeriods) {
            var electoralPeriodTimeline = timelineWrapper.append("div")
                .attr("class", "electoralPeriodTimeline")
                .style("width", "100%")
                .style("height", "30px") // Fixed height
                .style("position", "relative");

            // Process electoral periods that have at least a start date
            var validElectoralPeriods = this.electoralPeriods.filter(ep => 
                ep.attributes && ep.attributes.dateStart
            );

            // Create SVG container for curly braces
            var svgContainer = electoralPeriodTimeline.append("svg")
                .style("width", "100%")
                .style("height", "19px")
                .style("position", "absolute")
                .style("top", "-2px")
                .style("left", "0")
                .style("pointer-events", "none");

            // Process each electoral period
            validElectoralPeriods.forEach(ep => {
                var startDate = new Date(ep.attributes.dateStart);
                var endDate = ep.attributes.dateEnd ? new Date(ep.attributes.dateEnd) : null;
                var isCurrentPeriod = !endDate; // Current period has no end date
                
                // Calculate positions
                var startDays = Math.round(Math.abs((this.minDate - startDate) / oneDay));
                var startPercent = Math.max(0, Math.min(100, 100 * (startDays / diffDays)));
                
                var endPercent, midPercent;
                if (isCurrentPeriod) {
                    endPercent = 100; // Extend to end of timeline
                    midPercent = startPercent + ((100 - startPercent) / 2);
                } else {
                    var endDays = Math.round(Math.abs((this.minDate - endDate) / oneDay));
                    endPercent = Math.max(0, Math.min(100, 100 * (endDays / diffDays)));
                    midPercent = (startPercent + endPercent) / 2;
                }
                
                // Only render if the period is visible in the timeline
                if (startPercent <= 100) {
                    // Get actual container width and convert percentages to pixels
                    var containerWidth = electoralPeriodTimeline.node().clientWidth;
                    var startX = (startPercent / 100) * containerWidth;
                    var endX = (endPercent / 100) * containerWidth;
                    
                    // Create appropriate path based on period type
                    var pathData;
                    if (isCurrentPeriod) {
                        pathData = this.createCurrentPeriodPath(startX, endX);
                    } else {
                        pathData = this.createCurlyBracePath(startX, endX);
                    }
                    
                    var path = svgContainer.append("path")
                        .attr("d", pathData)
                        .attr("fill", "none");
                    
                    // Add dashed style for current period
                    if (isCurrentPeriod) {
                        path.attr("stroke-dasharray", "3,2");
                    }
                    
                    // Check if current period is wide enough to show label (150px minimum)
                    var currentPeriodWidth = endX - startX;
                    var showCurrentPeriodLabel = !isCurrentPeriod || currentPeriodWidth >= 150;
                    
                    // Add label below the brace (only if wide enough for current period)
                    if (showCurrentPeriodLabel) {
                        var label = electoralPeriodTimeline.append("div")
                            .attr("class", "electoralPeriodLabel")
                            .style("position", "absolute")
                            .style("bottom", "0px")
                            .style("white-space", "nowrap")
                            .html(ep.attributes.number + ". <span class='d-md-none'>" + localizedLabels.electoralPeriodShort + "</span><span class='d-none d-md-inline'>" + localizedLabels.electoralPeriod + "</span>");
                        
                        // Position label differently for current period
                        if (isCurrentPeriod) {
                            label.style("right", "0px")
                                 .style("transform", "none");
                        } else {
                            label.style("left", midPercent + "%")
                                 .style("transform", "translateX(-50%)");
                        }
                    }
                }
            });
        }
    };

    /**
     * Validate electoral periods data to ensure proper date ranges and single parliament
     * Returns true if all electoral periods meet the following criteria:
     * - All periods (except the last one) have both start and end dates
     * - The current/last electoral period has at least a start date
     * - All electoral periods are from the same parliament
     * 
     * @returns {boolean} True if electoral periods are valid for rendering
     */
    TimelineViz.prototype.areElectoralPeriodsValid = function() {
        if (!this.electoralPeriods || !Array.isArray(this.electoralPeriods) || this.electoralPeriods.length === 0) {
            return false;
        }
        
        // Check if all electoral periods are from the same parliament
        var parliaments = new Set();
        for (var i = 0; i < this.electoralPeriods.length; i++) {
            var ep = this.electoralPeriods[i];
            if (ep.attributes && ep.attributes.parliament) {
                parliaments.add(ep.attributes.parliament);
            }
        }
        
        // Only show electoral periods if they're all from the same parliament
        if (parliaments.size !== 1) {
            return false;
        }
        
        // Sort electoral periods by number to identify the last one
        var sortedPeriods = this.electoralPeriods.slice().sort(function(a, b) {
            var aNumber = a.attributes && a.attributes.number ? a.attributes.number : 0;
            var bNumber = b.attributes && b.attributes.number ? b.attributes.number : 0;
            return aNumber - bNumber;
        });
        
        for (var i = 0; i < sortedPeriods.length; i++) {
            var ep = sortedPeriods[i];
            var isLastPeriod = i === sortedPeriods.length - 1;
            
            // Check if electoral period has required attributes
            if (!ep.attributes) {
                return false;
            }
            
            // All electoral periods must have a valid start date
            if (!ep.attributes.dateStart || ep.attributes.dateStart === "") {
                return false;
            }
            
            // All electoral periods except the last one must have a valid end date
            if (!isLastPeriod) {
                if (!ep.attributes.dateEnd || ep.attributes.dateEnd === "") {
                    return false;
                }
            }
            
            // The last electoral period can have no end date (current period)
            // but if it has an end date, it should be valid
            if (isLastPeriod && ep.attributes.dateEnd === "") {
                // Empty string is treated as no end date (current period)
                continue;
            }
        }
        
        return true;
    };

    /**
     * Create SVG path data for a horizontal curly brace
     * 
     * @param {number} startX - Start position in pixels
     * @param {number} endX - End position in pixels  
     * @param {number} containerWidth - Container width in pixels
     * @param {number} containerHeight - Container height in pixels
     * @returns {string} SVG path data
     */
    TimelineViz.prototype.createCurlyBracePath = function(startX, endX) {
        var totalWidth = endX - startX;
        
        // Ensure minimum width for visibility
        if (totalWidth < 20) {
            var expansion = (20 - totalWidth) / 2;
            startX = Math.max(0, startX - expansion);
            endX = endX + expansion; // Don't limit to container, SVG will handle overflow
            totalWidth = endX - startX;
        }
        
        var curveSize = 7; // Curve size in pixels
        var y = 3; // Starting y position
        var midX = startX + (totalWidth / 2); // Exact middle point
        
        // Create path ensuring it spans from startX to endX exactly
        var path = "M" + startX + " " + y + 
                   " c0 " + curveSize + " " + curveSize + " " + curveSize + " " + curveSize + " " + curveSize +
                   " H" + (midX - curveSize) + // Absolute horizontal to middle-left
                   " s" + curveSize + " 0 " + curveSize + " " + curveSize +
                   " c0 -" + curveSize + " " + curveSize + " -" + curveSize + " " + curveSize + " -" + curveSize +
                   " H" + (endX - curveSize) + // Absolute horizontal to end-left
                   " s" + curveSize + " 0 " + curveSize + " -" + curveSize;
        
        return path;
    };

    /**
     * Create SVG path data for current electoral period (starts like curly brace, continues as straight line)
     * 
     * @param {number} startX - Start position in pixels
     * @param {number} endX - End position in pixels (container width)
     * @returns {string} SVG path data
     */
    TimelineViz.prototype.createCurrentPeriodPath = function(startX, endX) {
        var curveSize = 7; // Same curve size as regular curly braces
        var y = 3; // Same starting y position as regular curly braces
        
        // Create path that starts like a curly brace but continues as straight line
        var path = "M" + startX + " " + y + 
                   " c0 " + curveSize + " " + curveSize + " " + curveSize + " " + curveSize + " " + curveSize +
                   " H" + endX; // Continue straight to the end
        
        return path;
    };

    /**
     * Set up resize handler to recalculate curly braces on window resize
     */
    TimelineViz.prototype.setupResizeHandler = function() {
        var self = this;
        var resizeTimeout;
        var isResizing = false;
        
        window.addEventListener('resize', function() {
            // Hide curly braces immediately when resize starts
            if (!isResizing && self.container && self.showElectoralPeriods) {
                isResizing = true;
                var svgContainer = self.container.querySelector('svg');
                if (svgContainer) {
                    svgContainer.style.opacity = '0';
                }
            }
            
            // Debounce resize events to avoid excessive recalculations
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(function() {
                if (self.container && self.showElectoralPeriods) {
                    self.render();
                    // Show curly braces again after re-render
                    var svgContainer = self.container.querySelector('svg');
                    if (svgContainer) {
                        svgContainer.style.opacity = '1';
                    }
                }
                isResizing = false;
            }, 100);
        });
    };

    // Expose the TimelineViz class to the global scope
    window.TimelineViz = TimelineViz;
    
    /**
     * Calculate minimum date from electoral periods data
     * This replaces the server-side calculation to avoid extra database requests
     * 
     * @param {Array} electoralPeriods Electoral periods data from API response
     * @param {string} fallbackDate Fallback date string if no electoral periods available
     * @returns {Date} The calculated minimum date
     */
    window.calculateTimelineMinDate = function(electoralPeriods, fallbackDate) {
        fallbackDate = fallbackDate || "2013-10-01";
        
        if (!electoralPeriods || !Array.isArray(electoralPeriods) || electoralPeriods.length === 0) {
            return new Date(fallbackDate);
        }
        
        var minDate = null;
        for (var i = 0; i < electoralPeriods.length; i++) {
            var ep = electoralPeriods[i];
            if (ep.attributes && ep.attributes.dateStart) {
                var startDate = ep.attributes.dateStart;
                if (minDate === null || startDate < minDate) {
                    minDate = startDate;
                }
            }
        }
        
        return new Date(minDate || fallbackDate);
    };
    
    /**
     * Renders a filtered result timeline based on the provided selector
     * The element matching the selector must have data-filter-key and data-filter-value attributes
     * 
     * @param {string} selector - CSS selector for the container element
     * @param {Object} options - Optional configuration options
     * @param {boolean} options.showElectoralPeriods - Whether to show electoral periods timeline
     */
    window.renderFilteredResultTimeline = function(selector, options) {
        options = options || {};
        var showElectoralPeriods = options.showElectoralPeriods || false;
        // Get the container element using the selector
        var containerElement = document.querySelector(selector);
        
        if (!containerElement) {
            console.error('Container element not found with selector: ' + selector);
            return;
        }
        
        // Get filter key and value from the container element's data attributes
        var filterKey = containerElement.getAttribute('data-filter-key');
        var filterValue = containerElement.getAttribute('data-filter-value');
        
        if (!filterKey || !filterValue) {
            console.error('Container element is missing required data attributes: data-filter-key and data-filter-value');
            return;
        }
        
        // Initialize timeline visualization
        var timelineViz = null;
        
        // Fetch data from the API
        var xhr = new XMLHttpRequest();
        xhr.open('GET', config.dir.root + '/api/v1/search/media/?' + filterKey + '=' + filterValue, true);
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                var response = JSON.parse(xhr.responseText);
                
                if (response && response.meta && response.meta.requestStatus === 'success') {
                    // Process the data for the timeline
                    var daysData = {};
                    
                    // Get days data from the appropriate location in the response
                    if (response.meta && response.meta.attributes && response.meta.attributes.days) {
                        daysData = response.meta.attributes.days;
                    } else if (response.data && response.data.attributes && response.data.attributes.days) {
                        daysData = response.data.attributes.days;
                    } else if (response.data && response.data.days) {
                        daysData = response.data.days;
                    } else if (response.data && response.data.attributes && response.data.attributes.resultsPerDay) {
                        daysData = response.data.attributes.resultsPerDay;
                    } else if (response.data && Array.isArray(response.data)) {
                        // Group media items by date
                        var mediaByDate = {};
                        
                        for (var i = 0; i < response.data.length; i++) {
                            var mediaItem = response.data[i];
                            var date = '';
                            
                            // Extract date from the media item
                            if (mediaItem.attributes && mediaItem.attributes.date) {
                                date = mediaItem.attributes.date.split('T')[0]; // Get just the date part
                            } else if (mediaItem.attributes && mediaItem.attributes.startDate) {
                                date = mediaItem.attributes.startDate.split('T')[0]; // Get just the date part
                            }
                            
                            if (date) {
                                if (!mediaByDate[date]) {
                                    mediaByDate[date] = 0;
                                }
                                mediaByDate[date]++;
                            }
                        }
                        
                        daysData = mediaByDate;
                    }
                    
                    var timelineData = [];
                    
                    // Process each day's data
                    for (var day in daysData) {
                        var count = 0;
                        
                        // Handle different possible structures
                        if (typeof daysData[day] === 'object' && daysData[day].doc_count !== undefined) {
                            count = daysData[day].doc_count;
                        } else if (typeof daysData[day] === 'number') {
                            count = daysData[day];
                        }
                        
                        // Format the date to ensure it can be parsed by JavaScript's Date constructor
                        var formattedDate = day;
                        if (!/^\d{4}-\d{2}-\d{2}$/.test(day)) {
                            // If the date is not in the expected format, try to convert it
                            var dateParts = day.split(/[-T]/);
                            if (dateParts.length >= 3) {
                                formattedDate = dateParts[0] + '-' + dateParts[1] + '-' + dateParts[2];
                            }
                        }
                        
                        timelineData.push({
                            date: formattedDate,
                            count: count
                        });
                    }
                    
                    // Function to initialize timeline with or without electoral periods
                    function initializeTimeline(electoralPeriodsData) {
                        try {
                            var dynamicMinDate = calculateTimelineMinDate(electoralPeriodsData);
                            
                            timelineViz = new TimelineViz({
                                container: containerElement,
                                data: timelineData,
                                minDate: dynamicMinDate,
                                maxDate: new Date(),
                                showElectoralPeriods: showElectoralPeriods,
                                electoralPeriods: electoralPeriodsData || []
                            });
                        } catch (error) {
                            console.error('Error initializing TimelineViz:', error);
                        }
                        
                        // Hide the loading indicator if it exists
                        var loadingIndicator = containerElement.querySelector('.loadingIndicator');
                        if (loadingIndicator) {
                            loadingIndicator.style.display = 'none';
                        }
                    }

                    // Get electoral periods from media search response if available
                    var electoralPeriodsData = [];
                    if (showElectoralPeriods && response.meta && response.meta.attributes && response.meta.attributes.electoralPeriods) {
                        electoralPeriodsData = response.meta.attributes.electoralPeriods;
                    }
                    
                    initializeTimeline(electoralPeriodsData);
                }
            }
        };
        
        xhr.onerror = function() {
            console.error('Error fetching timeline data');
            var loadingIndicator = containerElement.querySelector('.loadingIndicator');
            if (loadingIndicator) {
                loadingIndicator.style.display = 'none';
            }
        };
        
        xhr.send();
    };
})(); 