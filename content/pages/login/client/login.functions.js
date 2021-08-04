$(function() {

	$("#login-form").ajaxForm({
		url: config["dir"]["root"]+"/server/ajaxServer.php",
		data: {"a":"login"},
		dataType: "json",
		method:"POST",
		success: function(ret) {

			if (typeof ret !== 'undefined') {


				if (ret["success"] == "true") {

					$("#login-response").text(ret["txt"]);
					setTimeout(function() {
						location.reload();
					},2000);

				} else {

					$("#login-response").text(ret["txt"]);

				}


			} else {

				$("#login-response").text("There was an error (code #02) while logging in. Please try again");

			}

		},

		error: function() {

			$("#login-response").text("There was an error (code #01) while logging in. Please try again");

		}
	});

	$(".button-logout").click(function() {
		$.ajax({
			url: config["dir"]["root"]+"/server/ajaxServer.php",
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