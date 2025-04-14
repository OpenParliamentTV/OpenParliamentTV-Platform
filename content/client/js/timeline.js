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
        
        this.init();
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
        
        // Create the timeline container - 60% of the height
        var timelineContainer = timelineWrapper.append("div")
            .style("width", "100%")
            .style("height", "60%") 
            .style("position", "relative");
        
        // Create the timeline bars
        var bars = timelineContainer.selectAll(".timeline-bar")
            .data(processedData)
            .enter()
            .append("div")
            .attr("class", "timelineVizItem")
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
        
        // Create the labels container 
        var labelsContainer = timelineWrapper.append("div")
            .style("width", "100%")
            .style("height", "40%") 
            .style("position", "relative")
            .style("padding-top", "20px"); 
        
        // Generate years based on minDate and maxDate
        var startYear = this.minDate.getFullYear();
        var endYear = this.maxDate.getFullYear();
        var years = [];
        for (var year = startYear; year <= endYear; year++) {
            years.push(year);
        }
        
        // Add year labels
        var yearLabels = labelsContainer.selectAll(".year-label")
            .data(years)
            .enter()
            .append("div")
            .attr("class", "year-label")
            .style("position", "absolute")
            .style("left", d => {
                // Calculate position based on the first day of the year
                var firstDayOfYear = new Date(d, 0, 1);
                var daysSinceMinDate = Math.round(Math.abs((this.minDate - firstDayOfYear) / oneDay));
                var leftPercent = 100 * (daysSinceMinDate / diffDays);
                
                // Ensure the label is within the container
                return Math.max(0, Math.min(100, leftPercent)) + "%";
            })
            .style("top", "50%")
            .style("transform", "translateY(-50%)")
            .style("padding-left", "4px")
            .style("font-size", "12px")
            .text(d => d)
            .style("display", (d, i) => i === 0 ? "none" : "block"); // Hide the first year label
        
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
        
        // Create a container for the year boundary lines
        var yearLinesContainer = timelineWrapper.append("div")
            .style("width", "100%")
            .style("height", "20px")
            .style("position", "absolute")
            .style("top", "22px")
            .style("left", "0")
            .style("pointer-events", "none"); // Make sure lines don't interfere with interactions
        
        // Add vertical lines for each year boundary
        var yearLines = yearLinesContainer.selectAll(".year-line")
            .data(yearBoundaries)
            .enter()
            .append("div")
            .attr("class", "year-line")
            .style("position", "absolute")
            .style("left", d => d.leftPercent + "%")
            .style("top", "0")
            .style("width", "2px")
            .style("height", "100%")
            .style("background-color", "rgba(0, 0, 0, 0.2)")
            .style("z-index", "1")
            .style("display", (d, i) => i === 0 ? "none" : "block"); // Hide the first year line
        
        // Apply custom styles to year lines if provided
        if (this.styles.yearLine) {
            Object.keys(this.styles.yearLine).forEach(key => {
                yearLines.style(key, this.styles.yearLine[key]);
            });
        }
    };

    // Export the TimelineViz class to the global scope
    window.TimelineViz = TimelineViz;
})(); 