
var updateAjax,
	// Global chart registry for auto-resizing
	chartRegistry = new Map(),
	factionColors = {
		"DIE LINKE": "#bc3475",
		"BÜNDNIS 90/DIE GRÜNEN": "#4a932b",
		"CDU/CSU": "#000000",
		"SPD": "#df0b25",
		"FDP": "#feeb34",
		"AfD": "#1a9fdd",
		"BSW": "#792150"
	};
	factionIDColors = {
		"Q1826856": "#bc3475", //linke
		"Q1007353": "#4a932b", //gruene
		"Q1023134": "#000000", //cdu/csu
		"Q2207512": "#df0b25", //spd
		"Q1387991": "#feeb34", //fdp
		"Q42575708": "#1a9fdd", //afd
		"Q127785176": "#792150" //bsw
	};

$(document).ready(function() {

	$(".langswitch").on("click",function(e) {
		e.preventDefault();
		var lang = $(this).data("lang");
		$.ajax({
			url:config["dir"]["root"]+"/api/v1/lang/set",
			data: {
				lang:lang
			},
			method: "POST",
			success: function(response) {
				// Check the new response structure from api.php
				if (response && response.meta && response.meta.requestStatus === "success") {
					// Force a complete page reload to ensure all language changes are applied
					window.location.reload(true);
				} else {
					// Log the error message from the API if available
					let errorMessage = "Unknown error";
					if (response && response.errors && response.errors.length > 0 && response.errors[0].detail) {
						errorMessage = response.errors[0].detail;
					} else if (response && response.meta && response.meta.message) {
						errorMessage = response.meta.message;
					}
					console.error("Language switch failed:", errorMessage, response);
				}
			},
			error: function(jqXHR, textStatus, errorThrown) {
				let responseJSON = jqXHR.responseJSON;
				let errorMessage = textStatus + (errorThrown ? ", " + errorThrown : "");
				if (responseJSON && responseJSON.errors && responseJSON.errors.length > 0 && responseJSON.errors[0].detail) {
					errorMessage = responseJSON.errors[0].detail;
				} else if (responseJSON && responseJSON.meta && responseJSON.meta.message) {
					errorMessage = responseJSON.meta.message;
				}
				console.error("Language switch request failed:", errorMessage, jqXHR);
				// Still reload the page as a fallback, or handle more gracefully
				// window.location.reload(true); // Consider if this is still desired on AJAX error
			}
		});
	});

	$('#toggleDarkmode').click(function(evt) {
		if ($(this).hasClass('active')) {
			$(this).removeClass('active');
			$('body').removeClass('darkmode');
			setCookie('color_scheme', 'light', 30);
		} else {
			$(this).addClass('active');
			$('body').addClass('darkmode');
			setCookie('color_scheme', 'dark', 30);
		}
		evt.stopPropagation();
	});

	window.setTimeout(function() {
		$('body').addClass('ready');
	}, 900);

	$(document).ajaxComplete(function( event,request, settings ) {
	    updateLinkTransitions();
	});

	updateLinkTransitions();

	// Global resize handler for all registered charts
	$(window).resize(function() {
		// Only resize charts, don't update their data
		for (let [chartId, chartInfo] of chartRegistry.entries()) {
			if (chartInfo.updateFunction && chartInfo.currentData) {
				// Update currentData reference and call update with existing data to trigger resize only
				chartInfo.updateFunction(chartInfo.currentData);
			}
		}
	});

	// Generic tab activation handler - triggers resize event for chart redraws
	$(document).on('shown.bs.tab', '[data-bs-toggle="tab"]', function (e) {
		// Trigger native window resize event to ensure charts and timelines redraw properly
		setTimeout(function() {
			// Use native event dispatch instead of jQuery trigger for better compatibility
			window.dispatchEvent(new Event('resize'));
			
			// Also trigger jQuery resize for any jQuery-based handlers
			$(window).trigger('resize');
		}, 100);
	});

});

/**
 * Formats time duration or time ago with multilingual support
 * 
 * @param {Object} options Configuration object
 * @param {number|Date} options.input - Seconds (for duration) or Date (for time ago)
 * @param {string} options.mode - 'duration' or 'ago' (default: 'duration')
 * @param {boolean} options.short - Show only 2 largest units (default: false)
 * @param {boolean} options.showAgo - Add "ago" suffix for ago mode (default: true)
 * @param {Object} options.labels - Override labels (optional, uses localizedLabels by default)
 * @returns {string} Formatted time string
 */
function getTimeDistanceString(options = {}) {
    const defaults = {
        input: 0,
        mode: 'duration',
        short: false,
        showAgo: true,
        labels: null
    };
    
    const config = { ...defaults, ...options };
    
    // Use provided labels or fall back to global localizedLabels
    const labels = config.labels || (typeof localizedLabels !== 'undefined' ? localizedLabels : {});
    
    // Helper function to get plural/singular form
    function getTimeLabel(value, singularKey, pluralKey) {
        if (!labels[singularKey] || !labels[pluralKey]) {
            // Fallback if labels are missing
            const fallback = {
                timeDay: 'day', timeDays: 'days',
                timeHour: 'hour', timeHours: 'hours',
                timeMinute: 'minute', timeMinutes: 'minutes',
                timeSecond: 'second', timeSeconds: 'seconds'
            };
            return value === 1 ? fallback[singularKey] : fallback[pluralKey];
        }
        return value === 1 ? labels[singularKey] : labels[pluralKey];
    }
    
    let seconds;
    
    if (config.mode === 'ago') {
        // Calculate seconds from date to now
        const inputDate = config.input instanceof Date ? config.input : new Date(config.input);
        const now = new Date();
        seconds = Math.floor((now - inputDate) / 1000);
        
        // Handle future dates
        if (seconds < 0) {
            seconds = Math.abs(seconds);
        }
    } else {
        // Duration mode - input is already in seconds
        seconds = Math.floor(config.input);
    }
    
    // Handle zero or negative values
    if (seconds <= 0) {
        if (config.mode === 'ago') {
            return labels.timeAgo ? labels.timeAgo : 'ago';
        }
        return '0 ' + getTimeLabel(0, 'timeSecond', 'timeSeconds');
    }
    
    // Calculate time units
    const days = Math.floor(seconds / 86400);
    const hours = Math.floor((seconds % 86400) / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const remainingSeconds = seconds % 60;
    
    // Build parts array
    const parts = [];
    
    if (days > 0) {
        parts.push(days + ' ' + getTimeLabel(days, 'timeDay', 'timeDays'));
    }
    
    if (hours > 0) {
        parts.push(hours + ' ' + getTimeLabel(hours, 'timeHour', 'timeHours'));
    }
    
    if (minutes > 0) {
        parts.push(minutes + ' ' + getTimeLabel(minutes, 'timeMinute', 'timeMinutes'));
    }
    
    if (remainingSeconds > 0) {
        parts.push(remainingSeconds + ' ' + getTimeLabel(remainingSeconds, 'timeSecond', 'timeSeconds'));
    }
    
    // Handle edge case where all units are 0 (shouldn't happen with our logic, but safety)
    if (parts.length === 0) {
        parts.push('0 ' + getTimeLabel(0, 'timeSecond', 'timeSeconds'));
    }
    
    // Apply short mode (only 2 largest units)
    if (config.short && parts.length > 2) {
        parts.splice(2);
    }
    
    // Join parts
    let result = parts.join(' ');
    
    // Add "ago" suffix for ago mode
    if (config.mode === 'ago' && config.showAgo && labels.timeAgo) {
        // Different languages have different word orders
        // German: "vor 2 Stunden" (ago at beginning)
        // English: "2 hours ago" (ago at end)
        // French: "il y a 2 heures" (ago at beginning)
        // Turkish: "2 saat önce" (ago at end)
        
        const currentLang = document.documentElement.lang || 'en';
        
        if (currentLang === 'de' || currentLang === 'fr') {
            // German and French: "vor/il y a" + time
            result = labels.timeAgo + ' ' + result;
        } else {
            // English, Turkish, and others: time + "ago/önce"
            result = result + ' ' + labels.timeAgo;
        }
    }
    
    return result;
}

function updateLinkTransitions() {
	$('a[href^="/"], a[href^="./"], a[href^="../"], a[href^="'+ config.dir.root +'"]')
	.not('a[target="_blank"]')
	.not('.langswitch')
	.click(function(evt) {
		if (evt.shiftKey || evt.ctrlKey || evt.altKey || evt.metaKey) {
			// click with meta key down
		} else {
			$('body').removeClass('ready');
			$('body > main').hide();
			evt.stopPropagation();
			evt.preventDefault();
			
			var currentHREF = $(this).attr('href');

			window.setTimeout(function(href) {
				window.location = currentHREF;
			}, 400, currentHREF);
		}
	});
}

function setCookie(identifier, value, expiryDays) {
	let date = new Date();
	date.setTime(date.getTime() + (expiryDays * 24 * 60 * 60 * 1000));
	const expires = "expires=" + date.toUTCString();
	document.cookie = identifier + "=" + value + "; " + expires + "; path=/";
}

function getCookie(identifier) {
	const name = identifier + "=";
	const cDecoded = decodeURIComponent(document.cookie); //to be careful
	const cArr = cDecoded .split('; ');
	let res;
	cArr.forEach(val => {
		if (val.indexOf(name) === 0) res = val.substring(name.length);
	})
	return res;
}

function delay(callback, ms) {
	var timer = 0;
	return function() {
		var context = this, args = arguments;
		clearTimeout(timer);
		timer = setTimeout(function () {
			callback.apply(context, args);
		}, ms || 0);
	};
}

function getQueryVariable(variable) {
	var query = window.location.search.substring(1),
		vars = query.split("&"),
		pair,
		returnValues = null;
	for (var i = 0; i < vars.length; i++) {
		pair = vars[i].split("=");
		
		pair[0] = decodeURIComponent(pair[0]);
		pair[1] = decodeURIComponent(pair[1]).replace(/\+/g, ' ');
		
		if (pair[0].indexOf('[]') != -1) {
			if (pair[0].replace('[]', '') == variable) {
				if (!returnValues) returnValues = [];
				returnValues.push(pair[1]);
			}
		} else if (pair[0] == variable) {
			returnValues = pair[1];
		}
	}

	return returnValues;
}

function getQueryVariableFromString(variable, queryString) {
	var splitQuery = queryString.split('?');
	var query = '';
	if (splitQuery.length > 1) {
		query = splitQuery[1];
	} else {
		query = queryString.replace('?', '')
	}
	
	var vars = query.split("&"),
		pair,
		returnValues = null;
	for (var i = 0; i < vars.length; i++) {
		pair = vars[i].split("=");
		
		pair[0] = decodeURIComponent(pair[0]);
		pair[1] = decodeURIComponent(pair[1]).replace(/\+/g, ' ');
		
		if (pair[0].indexOf('[]') != -1) {
			if (pair[0].replace('[]', '') == variable) {
				if (!returnValues) returnValues = [];
				returnValues.push(pair[1]);
			}
		} else if (pair[0] == variable) {
			returnValues = pair[1];
		}
	}

	return returnValues;
}

// Generic password field functionality
function initPasswordFields(options = {}) {
    const defaults = {
        passwordFieldId: 'password',
        confirmFieldId: 'passwordConfirm',
        strengthBarId: 'passwordStrength',
        strengthTextId: 'passwordStrengthText',
        matchTextId: 'passwordMatchText',
        showPasswordBtnId: 'showPassword',
        showPasswordConfirmBtnId: 'showPasswordConfirm',
        minLength: 8
    };

    const settings = { ...defaults, ...options };
    
    const passwordInput = document.getElementById(settings.passwordFieldId);
    const passwordConfirmInput = document.getElementById(settings.confirmFieldId);
    const passwordStrength = document.getElementById(settings.strengthBarId);
    const passwordStrengthText = document.getElementById(settings.strengthTextId);
    const passwordMatchText = document.getElementById(settings.matchTextId);
    const showPasswordBtn = document.getElementById(settings.showPasswordBtnId);
    const showPasswordConfirmBtn = document.getElementById(settings.showPasswordConfirmBtnId);

    if (!passwordInput || !passwordConfirmInput) return;

    // Toggle password visibility
    if (showPasswordBtn) {
        showPasswordBtn.addEventListener('click', function() {
            const type = passwordInput.type === 'password' ? 'text' : 'password';
            passwordInput.type = type;
            this.querySelector('i').className = type === 'password' ? 'icon-eye' : 'icon-eye-off';
        });
    }

    if (showPasswordConfirmBtn) {
        showPasswordConfirmBtn.addEventListener('click', function() {
            const type = passwordConfirmInput.type === 'password' ? 'text' : 'password';
            passwordConfirmInput.type = type;
            this.querySelector('i').className = type === 'password' ? 'icon-eye' : 'icon-eye-off';
        });
    }

    // Check password strength
    function checkPasswordStrength(password) {
        if (!passwordStrength || !passwordStrengthText) return 0;

        let strength = 0;
        let feedback = [];
        
        if (password.length >= settings.minLength) strength += 20;
        if (password.match(/[a-z]/)) strength += 20;
        if (password.match(/[A-Z]/)) strength += 20;
        if (password.match(/[0-9]/)) strength += 20;
        if (password.match(/[^\w]/)) strength += 20;
        
        // Set progress bar color based on strength
        if (strength <= 20) {
            passwordStrength.className = 'progress-bar bg-danger';
        } else if (strength <= 40) {
            passwordStrength.className = 'progress-bar bg-warning';
        } else if (strength <= 60) {
            passwordStrength.className = 'progress-bar bg-info';
        } else if (strength <= 80) {
            passwordStrength.className = 'progress-bar bg-primary';
        } else {
            passwordStrength.className = 'progress-bar bg-success';
        }
        
        passwordStrength.style.width = strength + '%';
        
        // Set feedback text
        if (password.length < settings.minLength) feedback.push(localizedLabels.messagePasswordTooShort);
        if (!password.match(/[a-z]/)) feedback.push(localizedLabels.messagePasswordNoLowercase);
        if (!password.match(/[A-Z]/)) feedback.push(localizedLabels.messagePasswordNoUppercase);
        if (!password.match(/[0-9]/)) feedback.push(localizedLabels.messagePasswordNoNumber);
        if (!password.match(/[^\w]/)) feedback.push(localizedLabels.messagePasswordNoSpecial);
        
        passwordStrengthText.innerHTML = feedback.join(', ');
        return strength === 100;
    }

    // Check password match
    function checkPasswordMatch() {
        if (!passwordMatchText) return true;

        const match = passwordInput.value === passwordConfirmInput.value;
        
        if (passwordConfirmInput.value && !match) {
            passwordMatchText.innerHTML = localizedLabels.messagePasswordNotIdentical;
        } else {
            passwordMatchText.innerHTML = '';
        }
        
        return match;
    }

    // Add event listeners
    passwordInput.addEventListener('input', function() {
        checkPasswordStrength(this.value);
        checkPasswordMatch();
    });
    
    passwordConfirmInput.addEventListener('input', checkPasswordMatch);

    // Return validation functions for external use
    return {
        checkPasswordStrength: () => checkPasswordStrength(passwordInput.value),
        checkPasswordMatch,
        isValid: () => checkPasswordStrength(passwordInput.value) && checkPasswordMatch()
    };
}

/**
 * Applies a CSS animation to a Bootstrap Table row and returns a Promise.
 * Assumes the table has a uniqueIdField set and rows have a 'data-item-id' attribute.
 * 
 * @param {string} tableId The ID of the <table> element.
 * @param {string|number} itemId The unique ID of the item/row to animate.
 * @param {'success'|'delete'} animationType The type of animation.
 * @param {number} animationDuration The duration of the CSS animation in milliseconds.
 * @returns {Promise<void>} A promise that resolves when the animation is considered complete.
 */
function animateBootstrapTableRow(tableId, itemId, animationType, animationDuration) {
    return new Promise((resolve, reject) => {
        const $table = $('#' + tableId);
        if (!$table.length) {
            console.error(`animateBootstrapTableRow: Table with ID '${tableId}' not found.`);
            reject(`Table not found: ${tableId}`);
            return;
        }

        // Find the row using data-uniqueid, which Bootstrap Table adds if uniqueId option is set.
        const $row = $table.find('tr[data-uniqueid="' + itemId + '"]');

        if (!$row.length) {
            console.warn(`animateBootstrapTableRow: Row with item ID '${itemId}' not found in table '${tableId}'. It might have been removed or not yet loaded.`);
            // Resolve immediately if row isn't found, as there's nothing to animate.
            // This can happen if a delete animation is rapidly followed by a table refresh.
            resolve(); 
            return;
        }

        if (animationType === 'success') {
            $row.addClass('row-action-success');
            setTimeout(() => {
                $row.removeClass('row-action-success'); // Clean up class for potential re-animation
                resolve();
            }, animationDuration);
        } else if (animationType === 'delete') {
            $row.addClass('row-action-delete');
            setTimeout(() => {
                // It's important that the table's uniqueId option is set correctly for removeByUniqueId to work.
                // This removeByUniqueId will trigger internal table refresh/re-rendering.
                $table.bootstrapTable('removeByUniqueId', itemId); 
                resolve();
            }, animationDuration);
        } else {
            console.error(`animateBootstrapTableRow: Unknown animationType '${animationType}'.`);
            reject(`Unknown animationType: ${animationType}`);
        }
    });
}


/**
 * Generic donut/pie chart rendering function using D3.js
 * 
 * @param {Object} options Configuration object
 * @param {string} options.container - CSS selector for container element
 * @param {Array} options.data - Array of objects with value and color properties
 * @param {string} options.type - Chart type: 'donut' or 'pie' (default: 'donut')
 * @param {string} options.colorType - Color mode: 'factions' or 'data' (default: 'data')
 * @param {string} options.valueField - Field name for values (default: 'value')
 * @param {string} options.labelField - Field name for labels (default: 'label')
 * @param {string} options.colorField - Field name for colors (default: 'color', ignored if colorType='factions')
 * @param {string} options.idField - Field name for faction IDs (required if colorType='factions')
 * @param {boolean} options.animate - Enable animations (default: true)
 * @param {number} options.animationDuration - Animation duration in ms (default: 750)
 * @param {number} options.innerRadius - Inner radius ratio for donut charts (default: 0.4)
 * @param {number} options.margin - Margin around chart (default: 10)
 * @returns {Object} Chart instance with update method
 */
function renderDonutChart(options = {}) {
    // Default options
    const defaults = {
        container: null,
        data: [],
        type: 'donut',
        colorType: 'data',
        valueField: 'value',
        labelField: 'label',
        colorField: 'color',
        idField: 'id',
        animate: true,
        animationDuration: 750,
        innerRadius: 0.4,
        margin: 0,
        showTooltips: true
    };
    
    const config = { ...defaults, ...options };
    
    if (!config.container) {
        console.error('renderDonutChart: container selector is required');
        return null;
    }
    
    const container = d3.select(config.container);
    if (container.empty()) {
        console.error('renderDonutChart: container not found:', config.container);
        return null;
    }
    
    // Clear existing content
    container.selectAll("*").remove();
    
    // Get container dimensions
    const containerNode = container.node();
    const containerWidth = containerNode.offsetWidth || 300;
    const containerHeight = containerNode.offsetHeight || 300;
    
    // For square containers (like faction charts), use the smaller dimension for both
    const size = Math.min(containerWidth, containerHeight);
    const width = size - (config.margin * 2);
    const height = size - (config.margin * 2);
    const radius = Math.min(width, height) / 2;
    
    // Create SVG
    const svg = container
        .append("svg")
        .attr("width", size)
        .attr("height", size);
    
    const chartGroup = svg
        .append("g")
        .attr("transform", `translate(${size / 2}, ${size / 2})`);
    
    // Create tooltip
    const tooltip = d3.select("body")
        .selectAll(".donut-chart-tooltip")
        .data([0])
        .enter()
        .append("div")
        .attr("class", "donut-chart-tooltip")
        .style("position", "absolute")
        .style("background", "rgba(0, 0, 0, 0.8)")
        .style("color", "white")
        .style("padding", "8px 12px")
        .style("border-radius", "4px")
        .style("font-size", "12px")
        .style("pointer-events", "none")
        .style("opacity", 0)
        .style("z-index", 1000);
    
    // Create arc generators
    const outerRadius = radius - config.margin;
    const innerRadius = config.type === 'donut' ? outerRadius * config.innerRadius : 0;
    
    const arc = d3.arc()
        .innerRadius(innerRadius)
        .outerRadius(outerRadius);
    
    // Create pie generator
    const pie = d3.pie()
        .value(d => d[config.valueField])
        .sort(null);
    
    function updateChart(newData) {
        // Save current arc states before clearing
        let previousStates = new Map();
        if (config.animate) {
            container.selectAll(".arc path").each(function(d) {
                if (d && d.data) {
                    const key = d.data[config.labelField] || d.data[config.idField];
                    previousStates.set(key, this._current || { startAngle: 0, endAngle: 0 });
                }
            });
        }
        
        // Clear and recalculate dimensions
        container.selectAll("*").remove();
        
        if (!newData || !Array.isArray(newData) || newData.length === 0) {
            return;
        }
        
        // Recalculate container dimensions every time
        const containerNode = container.node();
        const containerWidth = containerNode.offsetWidth || 300;
        const containerHeight = containerNode.offsetHeight || 300;
        
        // For square containers, use the smaller dimension for both
        const size = Math.min(containerWidth, containerHeight);
        const radius = Math.min(size - (config.margin * 2), size - (config.margin * 2)) / 2;
        
        // Recreate SVG with current dimensions
        const svg = container
            .append("svg")
            .attr("width", size)
            .attr("height", size);
        
        const chartGroup = svg
            .append("g")
            .attr("transform", `translate(${size / 2}, ${size / 2})`);
        
        // Recreate arc generators with current radius
        const outerRadius = radius - config.margin;
        const innerRadius = config.type === 'donut' ? outerRadius * config.innerRadius : 0;
        
        const arc = d3.arc()
            .innerRadius(innerRadius)
            .outerRadius(outerRadius);
        
        // Process data and assign colors
        const processedData = newData.map(d => {
            const item = { ...d };
            
            if (config.colorType === 'factions' && item[config.idField]) {
                item.color = factionIDColors[item[config.idField]] || '#999999';
            } else if (config.colorType === 'data' && item[config.colorField]) {
                item.color = item[config.colorField];
            } else {
                item.color = '#999999';
            }
            
            return item;
        });
        
        // Recreate pie generator with current config
        const pie = d3.pie()
            .value(d => d[config.valueField])
            .sort(null);
            
        // Generate pie data
        const pieData = pie(processedData);
        
        // Create arcs directly (no transitions since we're recreating everything)
        const arcs = chartGroup.selectAll(".arc")
            .data(pieData)
            .enter()
            .append("g")
            .attr("class", "arc");
        
        arcs.append("path")
            .attr("fill", d => d.data.color)
            .attr("stroke", "#ffffff")
            .attr("stroke-width", "1px")
            .each(function(d) {
                // Use previous state if available, otherwise start from 0
                const key = d.data[config.labelField] || d.data[config.idField];
                this._current = previousStates.get(key) || { startAngle: 0, endAngle: 0 };
            })
            .transition()
            .duration(config.animate ? config.animationDuration : 0)
            .attrTween("d", function(d) {
                const interpolate = d3.interpolate(this._current, d);
                this._current = interpolate(1); // Store final state
                return function(t) {
                    return arc(interpolate(t));
                };
            });
        
        if (config.showTooltips) {
            arcs.select("path")
                .on("mouseover", function(event, d) {
                    tooltip
                        .style("opacity", 1)
                        .html(`${d.data[config.labelField]}: ${d.data[config.valueField]}`)
                        .style("left", (event.pageX + 10) + "px")
                        .style("top", (event.pageY - 10) + "px");
                })
                .on("mousemove", function(event) {
                    tooltip
                        .style("left", (event.pageX + 10) + "px")
                        .style("top", (event.pageY - 10) + "px");
                })
                .on("mouseout", function() {
                    tooltip.style("opacity", 0);
                });
        }
    }
    
    // Initial render
    updateChart(config.data);
    
    // Store current data for resize
    let currentData = config.data;
    
    function updateWithResize(newData) {
        currentData = newData;
        // Update registry with new data
        for (let [key, value] of chartRegistry.entries()) {
            if (value.container === config.container) {
                value.currentData = newData;
                break;
            }
        }
        updateChart(newData);
    }
    
    // Register chart for auto-resizing (enabled by default, can be disabled with enableAutoResize: false)
    if (config.enableAutoResize !== false) {
        const chartId = config.container + '_chart_' + Date.now();
        chartRegistry.set(chartId, {
            updateFunction: updateWithResize,
            currentData: currentData,
            container: config.container
        });
    }
    
    // Return chart instance with update method only
    return {
        update: updateWithResize,
        destroy: function() {
            container.selectAll("*").remove();
            d3.select("body").selectAll(".donut-chart-tooltip").remove();
            // Remove from registry
            for (let [key, value] of chartRegistry.entries()) {
                if (value.container === config.container) {
                    chartRegistry.delete(key);
                    break;
                }
            }
        }
    };
}

