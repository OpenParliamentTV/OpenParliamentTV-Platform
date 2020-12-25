$(function() {

	$("#login-form").ajaxForm({
		url: "server/ajaxServer.php",
		data: {"a":"login"},
		dataType: "json",
		method:"POST",
		success: function(ret) {

			if (typeof ret !== 'undefined') {


				if (ret["success"] == "true") {

					$("#login-response").text("Login successful. You will be redirected");
					setTimeout(function() {
						location.reload();
					},2000);

				} else {

					$("#login-response").text("Your login credentials are not correct.");
					console.log(ret["txt"]);

				}


			} else {

				$("#login-response").text("There was an error (code #02) while logging in. Please try again");

			}

		},

		error: function() {

			$("#login-response").text("There was an error (code #01) while logging in. Please try again");

		}
	});



	$("#register-form").ajaxForm({
		url: "server/ajaxServer.php",
		data: {"a":"registerUser"},
		dataType: "json",
		method:"POST",
		success: function(ret) {

			if (typeof ret !== 'undefined') {

				if (ret["success"] == "true") {

					$("#register-response").text("Registration successful. You will be redirected");
					setTimeout(function() {
						location.reload();
					},2000);


				} else {

					$("#register-response").text("Your registration failed.: "+ret["txt"]);
					console.log(ret["txt"]);

				}

			} else {

				$("#register-response").text("There was an error (code #02) while registration. Please try again");

			}
		},
		error: function() {

			$("#register-response").text("There was an error (code #01) while registration. Please try again");

		}
	});

	$(".button-logout").click(function() {
		$.ajax({
			url: "server/ajaxServer.php",
			data: {"a": "logout"},
			dataType: "json",
			method: "POST",
			success: function (ret) {

				if (typeof ret !== 'undefined') {

					if (ret["success"] == "true") {

						location.reload();

					} else {

						console.log(ret["txt"]);

					}

				} else {

					console.log("Error: No valid response data");

				}

			},
			error: function () {

				console.log("There was an error (code #01) while logging out. Please try again");

			}
		})
	});

});