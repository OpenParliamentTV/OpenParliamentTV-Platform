var updateAjax,
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

});

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

